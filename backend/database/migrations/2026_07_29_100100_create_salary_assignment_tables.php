<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Salary assignments: what an employee is paid, from when.
 *
 * A revision is a new row rather than an edit. The previous row is marked
 * superseded and keeps its effective dates, so salary history is complete and
 * a payslip issued last year can still be explained.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salary_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_structure_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('basic_salary', 14, 2);
            $table->string('currency_code', 3);
            $table->string('country', 2);

            $table->date('effective_from');
            // Null means current. Set when a revision supersedes this row.
            $table->date('effective_to')->nullable();
            $table->string('status', 20)->default('active');

            // Why the salary changed: promotion, annual review, correction.
            $table->string('revision_reason')->nullable();
            $table->foreignId('supersedes_id')->nullable()->constrained('employee_salary_assignments')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Explicit names: the generated ones exceed MySQL's 64-character
            // identifier limit on a table with this long a name.
            $table->index(['owner_id', 'staff_id', 'effective_from'], 'esa_owner_staff_effective_idx');
            $table->index(['staff_id', 'status'], 'esa_staff_status_idx');
        });

        // Per-employee amounts, overriding the structure's defaults.
        Schema::create('employee_salary_component_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_salary_assignment_id')->constrained('employee_salary_assignments', 'id', 'esc_values_assignment_fk')->cascadeOnDelete();
            $table->foreignId('salary_component_id')->constrained()->cascadeOnDelete();
            // Overrides the component's own value for this employee.
            $table->decimal('value', 14, 4);
            $table->timestamps();

            $table->unique(['employee_salary_assignment_id', 'salary_component_id'], 'esc_values_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_component_values');
        Schema::dropIfExists('employee_salary_assignments');
    }
};
