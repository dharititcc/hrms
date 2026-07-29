<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\Task;
use App\Models\User;
use App\Services\EmployeeInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Row-level visibility: view means "mine", view-all means "everyone's".
 */
class RecordScopeTest extends TestCase
{
    use RefreshDatabase;

    private function staffFor(User $owner, string $role, string $email): Employee
    {
        return Employee::create(['owner_id' => $owner->id, 'name' => ucfirst($role), 'email' => $email, 'role' => $role, 'status' => 'active']);
    }

    private function invited(User $owner, string $role, string $email): array
    {
        $employee = $this->staffFor($owner, $role, $email);
        app(EmployeeInvitationService::class)->invite($employee);

        return [$employee->refresh(), $employee->user];
    }

    public function test_employees_see_only_their_own_attendance_leave_and_expenses(): void
    {
        $owner = User::factory()->create();
        [$employee, $account] = $this->invited($owner, 'member', 'employee@example.com');
        $colleague = $this->staffFor($owner, 'member', 'colleague@example.com');

        $type = LeaveType::create(['owner_id' => $owner->id, 'name' => 'Annual leave', 'days_per_year' => 20, 'is_active' => true]);

        foreach ([$employee->id, $colleague->id] as $employeeId) {
            Attendance::create(['owner_id' => $owner->id, 'staff_id' => $employeeId, 'work_date' => now()->toDateString(), 'status' => 'present']);
            LeaveRequest::create(['owner_id' => $owner->id, 'staff_id' => $employeeId, 'leave_type_id' => $type->id, 'start_date' => now()->toDateString(), 'end_date' => now()->addDay()->toDateString(), 'status' => 'pending']);
            Expense::create(['owner_id' => $owner->id, 'staff_id' => $employeeId, 'title' => 'Taxi', 'category' => 'Travel', 'amount' => 20, 'expense_date' => now()->toDateString(), 'status' => 'pending']);
        }

        Sanctum::actingAs($account);

        foreach (['/api/auth/attendance', '/api/auth/leave/requests', '/api/auth/expenses'] as $endpoint) {
            $this->getJson($endpoint)->assertOk()->assertJsonCount(1, 'data');
        }

        // The owner holds view-all and sees both people's rows.
        Sanctum::actingAs($owner);
        foreach (['/api/auth/attendance', '/api/auth/leave/requests', '/api/auth/expenses'] as $endpoint) {
            $this->getJson($endpoint)->assertOk()->assertJsonCount(2, 'data');
        }
    }

    public function test_employees_cannot_file_records_in_a_colleagues_name(): void
    {
        $owner = User::factory()->create();
        [, $account] = $this->invited($owner, 'member', 'employee@example.com');
        $colleague = $this->staffFor($owner, 'member', 'colleague@example.com');
        $type = LeaveType::create(['owner_id' => $owner->id, 'name' => 'Annual leave', 'days_per_year' => 20, 'is_active' => true]);

        Sanctum::actingAs($account);

        $this->postJson('/api/auth/expenses', [
            'staff_id' => $colleague->id, 'title' => 'Not mine', 'category' => 'Travel',
            'amount' => 50, 'expense_date' => now()->toDateString(),
        ])->assertForbidden();

        $this->postJson('/api/auth/leave/requests', [
            'staff_id' => $colleague->id, 'leave_type_id' => $type->id,
            'start_date' => now()->toDateString(), 'end_date' => now()->addDay()->toDateString(),
        ])->assertForbidden();

        $this->postJson('/api/auth/attendance/check-in', ['staff_id' => $colleague->id])->assertForbidden();
    }

    public function test_employees_can_still_act_for_themselves(): void
    {
        $owner = User::factory()->create();
        [$employee, $account] = $this->invited($owner, 'member', 'employee@example.com');

        Sanctum::actingAs($account);

        $this->postJson('/api/auth/attendance/check-in', ['staff_id' => $employee->id])->assertOk();
        $this->postJson('/api/auth/expenses', [
            'staff_id' => $employee->id, 'title' => 'Taxi', 'category' => 'Travel',
            'amount' => 20, 'expense_date' => now()->toDateString(),
        ])->assertCreated();
    }

    public function test_managers_may_act_on_behalf_of_others(): void
    {
        $owner = User::factory()->create();
        [, $manager] = $this->invited($owner, 'manager', 'manager@example.com');
        $colleague = $this->staffFor($owner, 'member', 'colleague@example.com');

        Sanctum::actingAs($manager);

        $this->postJson('/api/auth/attendance/check-in', ['staff_id' => $colleague->id])->assertOk();
    }

    public function test_clients_see_only_tasks_they_belong_to(): void
    {
        $owner = User::factory()->create();
        [, $client] = $this->invited($owner, 'client', 'client@example.com');

        $mine = Task::create(['owner_id' => $owner->id, 'subject' => 'Theirs', 'status' => 'pending', 'priority' => 'medium', 'created_by' => $owner->id]);
        $mine->assignees()->sync([$client->id]);

        Task::create(['owner_id' => $owner->id, 'subject' => 'Hidden', 'status' => 'pending', 'priority' => 'medium', 'created_by' => $owner->id]);
        Task::create(['owner_id' => $owner->id, 'subject' => 'Shared', 'status' => 'pending', 'priority' => 'medium', 'is_public' => true, 'created_by' => $owner->id]);

        Sanctum::actingAs($client);

        // The assigned one plus the public one, never the third.
        $this->getJson('/api/auth/tasks')->assertOk()->assertJsonCount(2, 'data');

        // An employee holds view-all and sees all three.
        [, $account] = $this->invited($owner, 'member', 'employee@example.com');
        Sanctum::actingAs($account);
        $this->getJson('/api/auth/tasks')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_clients_see_only_meetings_they_attend(): void
    {
        $owner = User::factory()->create();
        [, $client] = $this->invited($owner, 'client', 'client@example.com');

        $attending = Meeting::create([
            'owner_id' => $owner->id, 'title' => 'Review', 'type' => 'google_meet', 'status' => 'scheduled',
            'host_id' => $owner->id, 'organizer_id' => $owner->id,
            'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(), 'timezone' => 'UTC',
        ]);
        MeetingParticipant::create(['meeting_id' => $attending->id, 'user_id' => $client->id]);

        $hidden = Meeting::create([
            'owner_id' => $owner->id, 'title' => 'Internal', 'type' => 'google_meet', 'status' => 'scheduled',
            'host_id' => $owner->id, 'organizer_id' => $owner->id,
            'starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHour(), 'timezone' => 'UTC',
        ]);

        Sanctum::actingAs($client);

        $this->getJson('/api/auth/meetings')->assertOk()->assertJsonCount(1, 'data');
        // Nor by guessing the id.
        $this->getJson("/api/auth/meetings/{$hidden->id}")->assertForbidden();
        $this->getJson("/api/auth/meetings/{$attending->id}")->assertOk();
    }

    public function test_attendance_month_filter_works_on_this_database(): void
    {
        $owner = User::factory()->create();
        $employee = $this->staffFor($owner, 'member', 'employee@example.com');

        Attendance::create(['owner_id' => $owner->id, 'staff_id' => $employee->id, 'work_date' => now()->toDateString(), 'status' => 'present']);
        Attendance::create(['owner_id' => $owner->id, 'staff_id' => $employee->id, 'work_date' => now()->subMonths(2)->toDateString(), 'status' => 'present']);

        Sanctum::actingAs($owner);

        // Previously used strftime, which does not exist on MySQL.
        $this->getJson('/api/auth/attendance?month='.now()->format('Y-m'))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/auth/attendance?month=not-a-month')->assertStatus(422);
    }
}
