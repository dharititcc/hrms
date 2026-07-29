<?php

namespace App\Http\Controllers\API;

use App\Enums\ExpenseStatus;
use App\Enums\Module;
use App\Http\Controllers\Controller;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Support\RecordScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Expense::query()
            ->with('staff')
            ->where('owner_id', $request->user()->workspaceOwnerId());

        // Without expenses.view-all, a claimant sees only their own claims.
        RecordScope::apply($query, $request->user(), Module::Expenses);

        return ExpenseResource::collection($query->latest()->paginate(20))->response();
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'staff_id' => ['required', 'exists:staff,id'],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $request->user()->workspaceStaff()->findOrFail($validated['staff_id']);

        // Nobody may file a claim in a colleague's name unless they oversee the
        // module; otherwise the claim is forced onto the caller's own record.
        abort_unless(
            RecordScope::allows($request->user(), Module::Expenses, (int) $validated['staff_id']),
            403,
            'You can only submit expenses for yourself.',
        );

        $expense = Expense::create([
            ...$validated,
            'owner_id' => $request->user()->workspaceOwnerId(),
            'status' => ExpenseStatus::Pending->value,
        ]);

        return response()->json(new ExpenseResource($expense->load('staff')), 201);
    }

    public function updateStatus(Request $request, Expense $expense): JsonResponse
    {
        abort_unless($expense->owner_id === $request->user()->workspaceOwnerId(), 403);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(ExpenseStatus::class)],
        ]);

        $expense->update($validated);

        return response()->json(new ExpenseResource($expense->load('staff')));
    }
}
