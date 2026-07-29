<?php

namespace App\Http\Controllers\API;

use App\Enums\SalaryCalculation;
use App\Enums\SalaryComponentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\StoreSalaryComponentRequest;
use App\Http\Resources\SalaryComponentResource;
use App\Models\SalaryComponent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The lines that make up a payslip: earnings, deductions and employer
 * contributions.
 *
 * Flat rather than nested under structures, because a component with no
 * structure applies to every payslip in the workspace and so has nowhere to
 * nest. Filter by salary_structure_id to narrow.
 */
class SalaryComponentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $components = SalaryComponent::query()
            ->where('owner_id', $request->user()->workspaceOwnerId())
            ->when($request->filled('salary_structure_id'), fn ($query) => $query
                ->where('salary_structure_id', $request->integer('salary_structure_id')))
            ->when($request->boolean('global_only'), fn ($query) => $query->whereNull('salary_structure_id'))
            ->with('structure')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return SalaryComponentResource::collection($components)
            ->additional(['meta' => [
                // The form builds its selects from these rather than hardcoding
                // strings that would drift from the enums.
                'types' => $this->options(SalaryComponentType::cases()),
                'calculations' => $this->options(SalaryCalculation::cases()),
            ]])
            ->response();
    }

    public function store(StoreSalaryComponentRequest $request): JsonResponse
    {
        $component = SalaryComponent::create([
            ...$request->validated(),
            'code' => $request->code(),
            'owner_id' => $request->user()->workspaceOwnerId(),
            'is_taxable' => $request->boolean('is_taxable', true),
            'is_statutory' => $request->boolean('is_statutory'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return (new SalaryComponentResource($component))->response()->setStatusCode(201);
    }

    public function update(StoreSalaryComponentRequest $request, SalaryComponent $component): SalaryComponentResource
    {
        $this->authorizeComponent($request, $component);

        $component->update([
            ...$request->validated(),
            'code' => $request->code(),
            'is_taxable' => $request->boolean('is_taxable', $component->is_taxable),
            'is_statutory' => $request->boolean('is_statutory', $component->is_statutory),
            'is_active' => $request->boolean('is_active', $component->is_active),
        ]);

        return new SalaryComponentResource($component->refresh());
    }

    public function destroy(Request $request, SalaryComponent $component): JsonResponse
    {
        $this->authorizeComponent($request, $component);

        /*
        | Payslip lines are frozen copies rather than references, so a deleted
        | component leaves historic slips intact. Only future runs change.
        */
        $component->delete();

        return response()->json(['message' => 'Salary component deleted.']);
    }

    /** @param array<int, \BackedEnum> $cases */
    private function options(array $cases): array
    {
        return array_map(fn ($case) => $case->value, $cases);
    }

    private function authorizeComponent(Request $request, SalaryComponent $component): void
    {
        abort_unless($component->owner_id === $request->user()->workspaceOwnerId(), 403);
    }
}
