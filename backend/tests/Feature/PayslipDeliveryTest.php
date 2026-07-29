<?php

namespace Tests\Feature;

use App\Models\EmployeeSalaryAssignment;
use App\Models\SalaryComponent;
use App\Models\SalarySlip;
use App\Models\Staff;
use App\Models\User;
use App\Notifications\PayslipIssuedNotification;
use App\Services\StaffInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PayslipDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['name' => 'Acme Ltd']);
    }

    private function staff(string $name = 'Grace', string $email = 'g@example.com'): Staff
    {
        $staff = Staff::create([
            'owner_id' => $this->owner->id, 'name' => $name, 'email' => $email,
            'role' => 'member', 'status' => 'active',
        ]);

        EmployeeSalaryAssignment::create([
            'owner_id' => $this->owner->id, 'staff_id' => $staff->id, 'basic_salary' => 50000,
            'country' => 'IN', 'currency_code' => 'INR',
            'effective_from' => now()->startOfMonth()->subMonth()->toDateString(), 'status' => 'active',
        ]);

        return $staff;
    }

    /** @return array{0: array<string, mixed>, 1: SalarySlip} */
    private function approvedRun(Staff $staff): array
    {
        $start = now()->startOfMonth()->subMonth();

        $run = $this->postJson('/api/auth/payroll-runs', [
            'title' => 'Last month', 'country' => 'IN',
            'period_start' => $start->toDateString(),
            'period_end' => $start->copy()->endOfMonth()->toDateString(),
        ])->assertCreated()->json('data');

        $this->patchJson("/api/auth/payroll-runs/{$run['id']}/approve")->assertOk();

        return [$run, SalarySlip::where('staff_id', $staff->id)->firstOrFail()];
    }

    public function test_a_payslip_downloads_as_a_pdf(): void
    {
        $staff = $this->staff();
        SalaryComponent::create([
            'owner_id' => $this->owner->id, 'code' => 'HRA', 'name' => 'House Rent Allowance',
            'type' => 'earning', 'calculation' => 'percent_of_basic', 'value' => 40, 'is_active' => true,
        ]);

        Sanctum::actingAs($this->owner);
        [, $slip] = $this->approvedRun($staff);

        $response = $this->get("/api/auth/salary-slips/{$slip->id}/download")->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('.pdf', $response->headers->get('Content-Disposition'));
        // A real PDF, not an error page rendered with the wrong header.
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        // The breakdown made it in rather than the template failing silently.
        $this->assertStringContainsString('.pdf', $response->headers->get('Content-Disposition'));
    }

    public function test_the_payslip_shows_the_frozen_breakdown(): void
    {
        $staff = $this->staff();
        SalaryComponent::create([
            'owner_id' => $this->owner->id, 'code' => 'HRA', 'name' => 'House Rent Allowance',
            'type' => 'earning', 'calculation' => 'percent_of_basic', 'value' => 40, 'is_active' => true,
        ]);
        SalaryComponent::create([
            'owner_id' => $this->owner->id, 'code' => 'PF', 'name' => 'Provident Fund',
            'type' => 'deduction', 'calculation' => 'percent_of_basic', 'value' => 12,
            'is_statutory' => true, 'is_active' => true,
        ]);

        Sanctum::actingAs($this->owner);
        [, $slip] = $this->approvedRun($staff);

        /*
        | Asserting on the rendered view rather than the PDF bytes: dompdf
        | compresses its content streams, so a blank page and a full one look
        | much the same from outside. This is where a template mistake shows.
        */
        $html = view('payroll.payslip', $this->invokePdfData($slip))->render();

        $this->assertStringContainsString('Acme Ltd', $html);
        $this->assertStringContainsString('Grace', $html);
        $this->assertStringContainsString('House Rent Allowance', $html);
        $this->assertStringContainsString('Provident Fund', $html);
        $this->assertStringContainsString('(statutory)', $html);
        // 50,000 basic + 40% HRA = 70,000 gross, less 12% PF = 64,000 net.
        $this->assertStringContainsString('₹70,000.00', $html);
        $this->assertStringContainsString('₹64,000.00', $html);
    }

    /** @return array<string, mixed> */
    private function invokePdfData(SalarySlip $slip): array
    {
        $service = app(\App\Services\Payroll\PayslipPdfService::class);
        $method = new \ReflectionMethod($service, 'data');

        return $method->invoke($service, $slip->load(['staff', 'run', 'lines', 'owner']));
    }

    public function test_a_draft_payslip_cannot_be_downloaded(): void
    {
        $staff = $this->staff();
        $start = now()->startOfMonth()->subMonth();

        Sanctum::actingAs($this->owner);
        $this->postJson('/api/auth/payroll-runs', [
            'title' => 'Draft', 'country' => 'IN',
            'period_start' => $start->toDateString(), 'period_end' => $start->copy()->endOfMonth()->toDateString(),
        ])->assertCreated();

        $slip = SalarySlip::where('staff_id', $staff->id)->firstOrFail();

        // Working figures. A document headed "payslip" gets treated as final.
        $this->getJson("/api/auth/salary-slips/{$slip->id}/download")->assertStatus(422);
    }

    public function test_an_employee_can_download_their_own_payslip_but_not_a_colleagues(): void
    {
        $mine = $this->staff('Grace', 'g@example.com');
        $theirs = $this->staff('Ada', 'a@example.com');
        app(StaffInvitationService::class)->invite($mine);

        Sanctum::actingAs($this->owner);
        $this->approvedRun($mine);

        $mySlip = SalarySlip::where('staff_id', $mine->id)->firstOrFail();
        $theirSlip = SalarySlip::where('staff_id', $theirs->id)->firstOrFail();

        Sanctum::actingAs($mine->refresh()->user);

        // payroll.download without view-all: the record scope is the boundary.
        $this->get("/api/auth/salary-slips/{$mySlip->id}/download")->assertOk();
        $this->getJson("/api/auth/salary-slips/{$theirSlip->id}/download")->assertForbidden();
    }

    public function test_payslips_are_emailed_and_stamped(): void
    {
        Notification::fake();

        $staff = $this->staff();
        app(StaffInvitationService::class)->invite($staff);

        Sanctum::actingAs($this->owner);
        [$run, $slip] = $this->approvedRun($staff);

        $this->postJson("/api/auth/payroll-runs/{$run['id']}/email")
            ->assertOk()
            ->assertJsonPath('meta.sent', 1);

        Notification::assertSentTo($staff->refresh()->user, PayslipIssuedNotification::class);
        $this->assertNotNull($slip->refresh()->emailed_at);
    }

    public function test_sending_twice_skips_what_has_already_gone_unless_asked_to_resend(): void
    {
        Notification::fake();

        $staff = $this->staff();
        app(StaffInvitationService::class)->invite($staff);

        Sanctum::actingAs($this->owner);
        [$run] = $this->approvedRun($staff);

        $this->postJson("/api/auth/payroll-runs/{$run['id']}/email")->assertOk()->assertJsonPath('meta.sent', 1);

        // A second click must not silently send the same payslip again.
        $this->postJson("/api/auth/payroll-runs/{$run['id']}/email")
            ->assertOk()
            ->assertJsonPath('meta.sent', 0)
            ->assertJsonPath('meta.skipped_already_sent', 1);

        $this->postJson("/api/auth/payroll-runs/{$run['id']}/email", ['resend' => true])
            ->assertOk()
            ->assertJsonPath('meta.sent', 1);
    }

    public function test_an_employee_without_an_account_is_skipped_rather_than_emailed(): void
    {
        Notification::fake();

        $staff = $this->staff();

        Sanctum::actingAs($this->owner);
        [$run, $slip] = $this->approvedRun($staff);

        // They have an email address, but nobody has proved they control it.
        $this->postJson("/api/auth/payroll-runs/{$run['id']}/email")
            ->assertOk()
            ->assertJsonPath('meta.sent', 0)
            ->assertJsonPath('meta.skipped_without_account', 1);

        Notification::assertNothingSent();
        $this->assertNull($slip->refresh()->emailed_at);
    }

    public function test_a_draft_run_cannot_be_emailed(): void
    {
        Notification::fake();

        $staff = $this->staff();
        app(StaffInvitationService::class)->invite($staff);
        $start = now()->startOfMonth()->subMonth();

        Sanctum::actingAs($this->owner);
        $run = $this->postJson('/api/auth/payroll-runs', [
            'title' => 'Draft', 'country' => 'IN',
            'period_start' => $start->toDateString(), 'period_end' => $start->copy()->endOfMonth()->toDateString(),
        ])->assertCreated()->json('data');

        // A payslip in somebody's inbox cannot be taken back.
        $this->postJson("/api/auth/payroll-runs/{$run['id']}/email")->assertStatus(422);
        // Not assertNothingSent: inviting the staff member above legitimately
        // sent one.
        Notification::assertNotSentTo($staff->refresh()->user, PayslipIssuedNotification::class);
    }

    public function test_employees_cannot_email_payslips_and_other_workspaces_cannot_reach_them(): void
    {
        Notification::fake();

        $staff = $this->staff();
        app(StaffInvitationService::class)->invite($staff);

        Sanctum::actingAs($this->owner);
        [$run, $slip] = $this->approvedRun($staff);

        Sanctum::actingAs($staff->refresh()->user);
        $this->postJson("/api/auth/payroll-runs/{$run['id']}/email")->assertForbidden();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/auth/salary-slips/{$slip->id}/download")->assertForbidden();
        $this->postJson("/api/auth/payroll-runs/{$run['id']}/email")->assertForbidden();
    }
}
