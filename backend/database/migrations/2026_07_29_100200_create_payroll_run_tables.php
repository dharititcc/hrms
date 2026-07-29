<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll runs, the slips they produce and the payments against them.
 *
 * Slips store their own figures rather than recomputing from the structure.
 * A payslip is a statement of what was paid on a date; if a salary structure
 * is corrected next month, last month's slip must not change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();

            $table->string('title');
            $table->string('country', 2);
            $table->string('currency_code', 3);
            $table->date('period_start');
            $table->date('period_end');
            $table->date('pay_date')->nullable();

            $table->string('status', 30)->default('draft');

            // Totals across the run, denormalised for listing without joins.
            $table->decimal('total_earnings', 16, 2)->default(0);
            $table->decimal('total_deductions', 16, 2)->default(0);
            $table->decimal('total_net', 16, 2)->default(0);
            $table->unsignedInteger('slip_count')->default(0);

            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_id', 'status']);
            $table->index(['owner_id', 'period_start']);
        });

        Schema::create('salary_slips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            // Which salary this was calculated from, for auditability.
            $table->foreignId('employee_salary_assignment_id')->nullable()->constrained('employee_salary_assignments', 'id', 'slips_assignment_fk')->nullOnDelete();

            // Human reference printed on the payslip.
            $table->string('slip_number', 40);

            $table->string('country', 2);
            $table->string('currency_code', 3);
            $table->decimal('basic_salary', 14, 2)->default(0);
            $table->decimal('total_earnings', 14, 2)->default(0);
            $table->decimal('total_deductions', 14, 2)->default(0);
            $table->decimal('employer_contributions', 14, 2)->default(0);
            $table->decimal('gross_salary', 14, 2)->default(0);
            $table->decimal('net_salary', 14, 2)->default(0);

            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->string('status', 30)->default('draft');
            $table->timestamp('emailed_at')->nullable();

            $table->timestamps();

            $table->unique(['owner_id', 'slip_number']);
            $table->index(['payroll_run_id', 'staff_id']);
            $table->index(['owner_id', 'staff_id']);
        });

        // The frozen breakdown printed on the slip.
        Schema::create('salary_slip_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_slip_id')->constrained()->cascadeOnDelete();

            $table->string('type', 30);
            $table->string('code', 40);
            $table->string('name');
            $table->decimal('amount', 14, 2);
            $table->boolean('is_statutory')->default(false);
            $table->unsignedInteger('sort_order')->default(0);

            $table->index(['salary_slip_id', 'type']);
        });

        Schema::create('salary_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('salary_slip_id')->constrained()->cascadeOnDelete();

            $table->decimal('amount', 14, 2);
            $table->string('currency_code', 3);
            $table->timestamp('paid_at');
            $table->string('method', 40)->default('bank_transfer');
            $table->string('reference')->nullable();
            $table->text('note')->nullable();

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['owner_id', 'paid_at']);
            $table->index('salary_slip_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_payments');
        Schema::dropIfExists('salary_slip_lines');
        Schema::dropIfExists('salary_slips');
        Schema::dropIfExists('payroll_runs');
    }
};
