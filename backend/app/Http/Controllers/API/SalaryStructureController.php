<?php

namespace App\Http\Controllers\API;

use App\Enums\PayrollCountry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\StoreSalaryStructureRequest;
use App\Http\Resources\SalaryStructureResource;
use App\Models\SalaryComponent;
use App\Models\SalaryStructure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The salary templates an employee can be assigned to.
 *
 * A structure names a country and currency and carries the components that
 * make up a payslip. Components may also exist without a structure, in which
 * case they apply across the workspace.
 */
class SalaryStructureController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $structures = SalaryStructure::query()
            ->where('owner_id', $request->user()->workspaceOwnerId())
            ->withCount(['components', 'assignments'])
            ->orderBy('name')
            ->get();

        return SalaryStructureResource::collection($structures)
            ->additional(['meta' => ['countries' => $this->countryOptions()]])
            ->response();
    }

    /**
     * The countries payroll can be run for, so the form need not restate what
     * config/payroll.php already defines.
     *
     * statutory_count tells the caller whether offering to seed the country's
     * deductions is worth showing at all.
     *
     * @return list<array<string, mixed>>
     */
    private function countryOptions(): array
    {
        return array_map(fn (PayrollCountry $country) => [
            'value' => $country->value,
            'label' => $country->label(),
            'currency_code' => $country->currencyCode(),
            'currency_symbol' => $country->currencySymbol(),
            'statutory_count' => count($country->statutoryComponents()),
        ], PayrollCountry::cases());
    }

    public function show(Request $request, SalaryStructure $structure): SalaryStructureResource
    {
        $this->authorizeStructure($request, $structure);

        return new SalaryStructureResource($structure->load('components')->loadCount('assignments'));
    }

    public function store(StoreSalaryStructureRequest $request): JsonResponse
    {
        $ownerId = $request->user()->workspaceOwnerId();

        $structure = DB::transaction(function () use ($request, $ownerId): SalaryStructure {
            $structure = SalaryStructure::create([
                ...$request->safe()->except('seed_statutory'),
                'owner_id' => $ownerId,
                'currency_code' => $request->currencyCode(),
                'is_active' => $request->boolean('is_active', true),
            ]);

            if ($request->boolean('seed_statutory')) {
                $this->seedStatutoryComponents($structure);
            }

            return $structure;
        });

        return (new SalaryStructureResource($structure->load('components')))->response()->setStatusCode(201);
    }

    public function update(StoreSalaryStructureRequest $request, SalaryStructure $structure): SalaryStructureResource
    {
        $this->authorizeStructure($request, $structure);

        $structure->update([
            ...$request->safe()->except('seed_statutory'),
            'currency_code' => $request->currencyCode(),
            'is_active' => $request->boolean('is_active', $structure->is_active),
        ]);

        return new SalaryStructureResource($structure->refresh()->load('components'));
    }

    public function destroy(Request $request, SalaryStructure $structure): JsonResponse
    {
        $this->authorizeStructure($request, $structure);

        /*
        | Refuse while anyone is still on it. Assignments are the record of what
        | somebody was actually paid against, so removing the structure under
        | them would leave historic payslips unexplainable.
        */
        $assigned = $structure->assignments()->count();

        abort_if($assigned > 0, 422, "This structure cannot be deleted while {$assigned} salary assignment(s) reference it. Deactivate it instead.");

        $structure->delete();

        return response()->json(['message' => 'Salary structure deleted.']);
    }

    /**
     * Copies the country's statutory deductions in from config/payroll.php.
     *
     * Those rates are starting defaults rather than verified law, which is why
     * they land as ordinary editable components: correcting one is a form
     * submission, not a code change.
     */
    private function seedStatutoryComponents(SalaryStructure $structure): void
    {
        foreach ($structure->country->statutoryComponents() as $index => $definition) {
            SalaryComponent::create([
                'owner_id' => $structure->owner_id,
                'salary_structure_id' => $structure->id,
                'code' => $definition['code'],
                'name' => $definition['name'],
                'type' => $definition['type'],
                'calculation' => $definition['calculation'],
                'value' => $definition['value'],
                'is_statutory' => true,
                'is_taxable' => false,
                'country' => $structure->country->value,
                'sort_order' => ($index + 1) * 10,
                'is_active' => true,
            ]);
        }
    }

    private function authorizeStructure(Request $request, SalaryStructure $structure): void
    {
        abort_unless($structure->owner_id === $request->user()->workspaceOwnerId(), 403);
    }
}
