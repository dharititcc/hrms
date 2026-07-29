<?php

namespace App\Http\Controllers\API;

use App\Enums\Action;
use App\Enums\Module;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\StorePayrollProfileRequest;
use App\Http\Resources\PayrollProfileResource;
use App\Models\Employee;
use App\Models\EmployeePayrollProfile;
use App\Support\RecordScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Where an employee's pay actually goes, and under what tax identity.
 *
 * Lives under the employee resource because these details belong to a person
 * rather than to a payroll run, and there is exactly one per employee.
 */
class PayrollProfileController extends Controller
{
    public function show(Request $request, Employee $employee): JsonResponse
    {
        $this->authorizeEmployee($request, $employee);

        $profile = EmployeePayrollProfile::where('staff_id', $employee->id)->first();

        return response()->json([
            'data' => $profile === null ? null : new PayrollProfileResource($profile),
        ]);
    }

    /**
     * Creates or replaces the profile.
     *
     * A single upsert rather than separate create and update: there is one row
     * per employee, so a caller should not have to know whether it exists.
     */
    public function store(StorePayrollProfileRequest $request, Employee $employee): JsonResponse
    {
        $this->authorizeEmployee($request, $employee, forWriting: true);

        $existing = EmployeePayrollProfile::where('staff_id', $employee->id)->first();

        $profile = EmployeePayrollProfile::updateOrCreate(
            ['staff_id' => $employee->id],
            [...$request->payload(), 'owner_id' => $employee->owner_id],
        );

        return (new PayrollProfileResource($profile->refresh()))
            ->response()
            ->setStatusCode($existing === null ? 201 : 200);
    }

    public function destroy(Request $request, Employee $employee): JsonResponse
    {
        $this->authorizeEmployee($request, $employee, forWriting: true);

        EmployeePayrollProfile::where('staff_id', $employee->id)->delete();

        return response()->json(['message' => 'Payroll profile deleted.']);
    }

    /**
     * Bank details are more sensitive than the salary figure, but an employee
     * keeping their own up to date is ordinary self-service, so "own record"
     * is enough for both reading and writing. The record scope is what stops
     * it reaching anybody else's.
     *
     * Changes are recorded on the activity timeline, values withheld: altering
     * bank details is the classic payroll diversion attack, and the trail is
     * the defence rather than a permission nobody could work with.
     */
    private function authorizeEmployee(Request $request, Employee $employee, bool $forWriting = false): void
    {
        $user = $request->user();

        abort_unless($employee->owner_id === $user->workspaceOwnerId(), 403);
        abort_unless(RecordScope::allows($user, Module::Payroll, $employee->id), 403);

        if ($forWriting) {
            // Their own, or the payroll permission to maintain anybody's.
            abort_unless(
                $user->employeeId() === $employee->id || $user->hasPermission(Module::Payroll, Action::Edit),
                403,
            );
        }
    }
}
