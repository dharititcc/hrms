<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\EmployeeInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkShiftTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Day', 'starts_at' => '09:00', 'ends_at' => '18:00',
            'grace_minutes' => 15, 'break_minutes' => 60, 'is_default' => true,
            ...$overrides,
        ];
    }

    public function test_a_shift_can_be_created_listed_updated_and_deleted(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        $id = $this->postJson('/api/auth/work-shifts', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.starts_at', '09:00')
            // Nine hours less the hour's break, so the screen need not work it out.
            ->assertJsonPath('data.paid_minutes', 480)
            ->json('data.id');

        $this->getJson('/api/auth/work-shifts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            // What a workspace with no shift is judged against.
            ->assertJsonPath('meta.fallback.starts_at', '09:00');

        $this->putJson("/api/auth/work-shifts/{$id}", $this->payload(['ends_at' => '17:00']))
            ->assertOk()->assertJsonPath('data.paid_minutes', 420);

        $this->deleteJson("/api/auth/work-shifts/{$id}")->assertOk();
        $this->assertDatabaseCount('work_shifts', 0);
    }

    public function test_only_one_shift_can_be_the_default(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        $first = $this->postJson('/api/auth/work-shifts', $this->payload(['name' => 'Day']))->json('data.id');
        $second = $this->postJson('/api/auth/work-shifts', $this->payload(['name' => 'Late', 'starts_at' => '12:00', 'ends_at' => '21:00']))->json('data.id');

        // Two defaults would make which one a check-in is judged against
        // depend on row order.
        $this->assertFalse(WorkShift::find($first)->is_default);
        $this->assertTrue(WorkShift::find($second)->is_default);
        $this->assertSame(1, WorkShift::where('is_default', true)->count());
    }

    public function test_a_shift_that_ends_before_it_starts_is_refused(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        // Overnight shifts are not modelled elsewhere in attendance.
        $this->postJson('/api/auth/work-shifts', $this->payload(['starts_at' => '22:00', 'ends_at' => '06:00']))
            ->assertStatus(422)->assertJsonValidationErrors('ends_at');
    }

    public function test_a_break_cannot_be_as_long_as_the_shift(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        $this->postJson('/api/auth/work-shifts', $this->payload(['starts_at' => '09:00', 'ends_at' => '12:00', 'break_minutes' => 180]))
            ->assertStatus(422)->assertJsonValidationErrors('break_minutes');
    }

    public function test_employees_may_read_the_hours_but_not_change_them(): void
    {
        $owner = User::factory()->create();
        $employee = Employee::create([
            'owner_id' => $owner->id, 'name' => 'Grace', 'email' => 'g@example.com',
            'role' => 'employee', 'status' => 'active',
        ]);
        app(EmployeeInvitationService::class)->invite($employee);

        Sanctum::actingAs($employee->refresh()->user);

        // They are judged against these, so they get to see them.
        $this->getJson('/api/auth/work-shifts')->assertOk();
        $this->postJson('/api/auth/work-shifts', $this->payload())->assertForbidden();
    }

    public function test_shifts_are_scoped_to_the_workspace(): void
    {
        $owner = User::factory()->create();
        $shift = WorkShift::create([...$this->payload(), 'owner_id' => $owner->id, 'is_active' => true]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/auth/work-shifts')->assertOk()->assertJsonCount(0, 'data');
        $this->putJson("/api/auth/work-shifts/{$shift->id}", $this->payload())->assertForbidden();
        $this->deleteJson("/api/auth/work-shifts/{$shift->id}")->assertForbidden();
    }
}
