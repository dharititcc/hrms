<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retires the original payroll tables in favour of the richer payroll module.
 *
 * payroll_periods becomes a payroll run and each payroll_record becomes a
 * salary slip with two summary lines, since the old schema stored allowances
 * and deductions as single totals rather than itemised components. That is
 * lossy in the sense that the detail never existed, not that it is discarded.
 */
return new class extends Migration
{
    private const DEFAULT_COUNTRY = 'IN';

    private const DEFAULT_CURRENCY = 'INR';

    public function up(): void
    {
        if (Schema::hasTable('payroll_periods')) {
            $this->migratePeriods();
        }

        Schema::dropIfExists('payroll_records');
        Schema::dropIfExists('payroll_periods');
    }

    private function migratePeriods(): void
    {
        $country = config('payroll.default_country', self::DEFAULT_COUNTRY);
        $currency = config("payroll.countries.{$country}.currency_code", self::DEFAULT_CURRENCY);

        DB::table('payroll_periods')->orderBy('id')->chunkById(100, function ($periods) use ($country, $currency): void {
            foreach ($periods as $period) {
                $runId = DB::table('payroll_runs')->insertGetId([
                    'owner_id' => $period->owner_id,
                    'title' => $period->name,
                    'country' => $country,
                    'currency_code' => $currency,
                    'period_start' => $period->start_date,
                    'period_end' => $period->end_date,
                    // The old vocabulary had only draft and processed.
                    'status' => $period->status === 'processed' ? 'approved' : 'draft',
                    'created_at' => $period->created_at,
                    'updated_at' => $period->updated_at,
                ]);

                $this->migrateRecords($period, $runId, $country, $currency);
                $this->updateRunTotals($runId);
            }
        });
    }

    private function migrateRecords(object $period, int $runId, string $country, string $currency): void
    {
        $records = DB::table('payroll_records')->where('payroll_period_id', $period->id)->get();

        foreach ($records as $record) {
            $basic = (float) $record->basic_salary;
            $allowances = (float) $record->allowances;
            $deductions = (float) $record->deductions;

            $slipId = DB::table('salary_slips')->insertGetId([
                'owner_id' => $record->owner_id,
                'payroll_run_id' => $runId,
                'staff_id' => $record->staff_id,
                // Marked so a migrated slip is recognisable in support.
                'slip_number' => 'LEGACY-'.$record->id,
                'country' => $country,
                'currency_code' => $currency,
                'basic_salary' => $basic,
                'total_earnings' => $allowances,
                'total_deductions' => $deductions,
                'employer_contributions' => 0,
                'gross_salary' => $basic + $allowances,
                'net_salary' => (float) $record->net_salary,
                'status' => 'draft',
                'created_at' => $record->created_at,
                'updated_at' => $record->updated_at,
            ]);

            // The old schema held totals, not components, so the breakdown is
            // a single line each rather than invented detail.
            if ($allowances > 0) {
                DB::table('salary_slip_lines')->insert([
                    'salary_slip_id' => $slipId, 'type' => 'earning', 'code' => 'ALLOWANCES',
                    'name' => 'Allowances', 'amount' => $allowances, 'is_statutory' => false, 'sort_order' => 1,
                ]);
            }

            if ($deductions > 0) {
                DB::table('salary_slip_lines')->insert([
                    'salary_slip_id' => $slipId, 'type' => 'deduction', 'code' => 'DEDUCTIONS',
                    'name' => 'Deductions', 'amount' => $deductions, 'is_statutory' => false, 'sort_order' => 1,
                ]);
            }
        }
    }

    private function updateRunTotals(int $runId): void
    {
        $totals = DB::table('salary_slips')
            ->where('payroll_run_id', $runId)
            ->selectRaw('count(*) as slips, coalesce(sum(total_earnings),0) as earnings, coalesce(sum(total_deductions),0) as deductions, coalesce(sum(net_salary),0) as net')
            ->first();

        DB::table('payroll_runs')->where('id', $runId)->update([
            'slip_count' => $totals->slips ?? 0,
            'total_earnings' => $totals->earnings ?? 0,
            'total_deductions' => $totals->deductions ?? 0,
            'total_net' => $totals->net ?? 0,
        ]);
    }

    /**
     * Recreates the empty tables so a rollback leaves a working schema. Rows
     * are not moved back; this migration is not data-reversible.
     */
    public function down(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        Schema::create('payroll_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $table->decimal('basic_salary', 12, 2);
            $table->decimal('allowances', 12, 2)->default(0);
            $table->decimal('deductions', 12, 2)->default(0);
            $table->decimal('net_salary', 12, 2);
            $table->timestamps();
        });
    }
};
