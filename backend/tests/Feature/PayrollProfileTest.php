<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\EmployeePayrollProfile;
use App\Models\User;
use App\Services\EmployeeInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PayrollProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
    }

    private function staff(string $name = 'Grace', string $email = 'g@example.com'): Employee
    {
        return Employee::create([
            'owner_id' => $this->owner->id, 'name' => $name, 'email' => $email,
            'role' => 'member', 'status' => 'active',
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'country' => 'IN',
            'bank_name' => 'State Bank',
            'account_holder_name' => 'Grace Hopper',
            'account_number' => '123456789012',
            'bank_code' => 'SBIN0001234',
            ...$overrides,
        ];
    }

    public function test_a_profile_can_be_created_read_back_and_deleted(): void
    {
        $employee = $this->staff();
        Sanctum::actingAs($this->owner);

        $this->putJson("/api/auth/employees/{$employee->id}/payroll-profile", $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.bank_name', 'State Bank')
            // Currency follows the country when not given.
            ->assertJsonPath('data.currency_code', 'INR')
            ->assertJsonPath('data.is_payable', true)
            // India's own words for these fields, from config/payroll.php.
            ->assertJsonPath('data.labels.tax_identifier', 'PAN')
            ->assertJsonPath('data.labels.bank_code', 'IFSC code');

        $this->getJson("/api/auth/employees/{$employee->id}/payroll-profile")
            ->assertOk()
            ->assertJsonPath('data.account_number_masked', '••••••••9012');

        // A second write updates rather than creating a second row.
        $this->putJson("/api/auth/employees/{$employee->id}/payroll-profile", $this->payload(['bank_name' => 'HDFC']))
            ->assertOk()
            ->assertJsonPath('data.bank_name', 'HDFC');
        $this->assertDatabaseCount('employee_payroll_profiles', 1);

        $this->deleteJson("/api/auth/employees/{$employee->id}/payroll-profile")->assertOk();
        $this->assertDatabaseCount('employee_payroll_profiles', 0);
    }

    public function test_the_account_number_is_never_returned_in_full(): void
    {
        $employee = $this->staff();
        Sanctum::actingAs($this->owner);

        $this->putJson("/api/auth/employees/{$employee->id}/payroll-profile", $this->payload())->assertCreated();

        $response = $this->getJson("/api/auth/employees/{$employee->id}/payroll-profile")->assertOk();

        $this->assertStringNotContainsString('123456789012', $response->getContent());
        $this->assertTrue($response->json('data.has_account_number'));
    }

    public function test_the_account_number_is_encrypted_at_rest(): void
    {
        $employee = $this->staff();
        Sanctum::actingAs($this->owner);

        $this->putJson("/api/auth/employees/{$employee->id}/payroll-profile", $this->payload())->assertCreated();

        $stored = DB::table('employee_payroll_profiles')->value('account_number');

        // The column holds ciphertext; only the model can read it back.
        $this->assertNotSame('123456789012', $stored);
        $this->assertStringNotContainsString('123456789012', (string) $stored);
        $this->assertSame('123456789012', EmployeePayrollProfile::first()->account_number);
    }

    public function test_omitting_a_secret_keeps_it_while_an_empty_string_clears_it(): void
    {
        $employee = $this->staff();
        Sanctum::actingAs($this->owner);

        $this->putJson("/api/auth/employees/{$employee->id}/payroll-profile", $this->payload())->assertCreated();

        // The API only ever returns these masked, so a form cannot send back
        // what it never received. Omitting the key must not wipe the value.
        $this->putJson("/api/auth/employees/{$employee->id}/payroll-profile", [
            'country' => 'IN', 'bank_name' => 'State Bank', 'account_holder_name' => 'Grace Hopper',
        ])->assertOk()->assertJsonPath('data.has_account_number', true);

        $this->assertSame('123456789012', EmployeePayrollProfile::first()->account_number);

        // Sending it empty is how it is deliberately cleared.
        $this->putJson("/api/auth/employees/{$employee->id}/payroll-profile", $this->payload(['account_number' => '']))
            ->assertOk()->assertJsonPath('data.has_account_number', false);
    }

    public function test_a_profile_without_an_account_is_not_payable(): void
    {
        $employee = $this->staff();
        Sanctum::actingAs($this->owner);

        // Tax details alone are not enough to send anybody money.
        $this->putJson("/api/auth/employees/{$employee->id}/payroll-profile", [
            'country' => 'IN', 'tax_identifier' => 'ABCDE1234F',
        ])->assertCreated()->assertJsonPath('data.is_payable', false);

        // An IBAN counts instead of an account number.
        $this->putJson("/api/auth/employees/{$employee->id}/payroll-profile", [
            'country' => 'GB', 'account_holder_name' => 'Grace Hopper', 'iban' => 'GB33BUKB20201555555555',
        ])->assertOk()->assertJsonPath('data.is_payable', true);
    }

    public function test_changing_bank_details_is_logged_without_recording_them(): void
    {
        $employee = $this->staff();
        Sanctum::actingAs($this->owner);

        $this->putJson("/api/auth/employees/{$employee->id}/payroll-profile", $this->payload())->assertCreated();
        $this->putJson("/api/auth/employees/{$employee->id}/payroll-profile", $this->payload(['account_number' => '999988887777']))
            ->assertOk();

        $log = AuditLog::where('entity', 'employee_payroll_profile')->where('action', 'updated')->latest()->first();

        // Diverting payroll to another account is the attack this guards; that
        // it changed is the signal, the number itself is not needed.
        $this->assertNotNull($log);
        $this->assertContains('account_number', $log->metadata['changed']);
        $this->assertSame('[redacted]', $log->metadata['new']['account_number']);
        $this->assertSame('[redacted]', $log->metadata['old']['account_number']);
        $this->assertStringNotContainsString('999988887777', json_encode($log->metadata));
    }

    public function test_an_employee_maintains_their_own_but_cannot_reach_a_colleagues(): void
    {
        $mine = $this->staff('Grace', 'g@example.com');
        $theirs = $this->staff('Ada', 'a@example.com');
        app(EmployeeInvitationService::class)->invite($mine);

        Sanctum::actingAs($mine->refresh()->user);

        // Keeping your own bank details current is ordinary self-service.
        $this->putJson("/api/auth/employees/{$mine->id}/payroll-profile", $this->payload())->assertCreated();
        $this->getJson("/api/auth/employees/{$mine->id}/payroll-profile")->assertOk();

        $this->getJson("/api/auth/employees/{$theirs->id}/payroll-profile")->assertForbidden();
        $this->putJson("/api/auth/employees/{$theirs->id}/payroll-profile", $this->payload())->assertForbidden();
        // Deleting one is an administrative act, not self-service.
        $this->deleteJson("/api/auth/employees/{$mine->id}/payroll-profile")->assertForbidden();
    }

    public function test_profiles_are_scoped_to_the_workspace(): void
    {
        $employee = $this->staff();
        Sanctum::actingAs($this->owner);
        $this->putJson("/api/auth/employees/{$employee->id}/payroll-profile", $this->payload())->assertCreated();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/auth/employees/{$employee->id}/payroll-profile")->assertForbidden();
        $this->putJson("/api/auth/employees/{$employee->id}/payroll-profile", $this->payload())->assertForbidden();
    }

    public function test_input_is_validated(): void
    {
        $employee = $this->staff();
        Sanctum::actingAs($this->owner);

        $this->putJson("/api/auth/employees/{$employee->id}/payroll-profile", ['country' => 'ZZ'])
            ->assertStatus(422)->assertJsonValidationErrors('country');

        $this->putJson("/api/auth/employees/{$employee->id}/payroll-profile", $this->payload(['swift_code' => str_repeat('X', 20)]))
            ->assertStatus(422)->assertJsonValidationErrors('swift_code');
    }
}
