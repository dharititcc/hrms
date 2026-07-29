<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Salary structures and the components they are built from.
 *
 * Earnings, deductions and employer contributions share one table with a type
 * column rather than the separate salary_components and salary_deductions the
 * brief suggested: they carry identical fields and identical calculation
 * rules, so splitting them would duplicate every query and constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Bank and tax details belong to the person, not to a salary, and are
        // kept out of the staff table as requested.
        Schema::create('employee_payroll_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();

            $table->string('country', 2);
            $table->string('currency_code', 3);

            $table->string('bank_name')->nullable();
            $table->string('account_holder_name')->nullable();
            $table->string('account_number')->nullable();
            // IFSC, routing, sort code, BSB, transit — label varies by country.
            $table->string('bank_code')->nullable();
            $table->string('swift_code')->nullable();
            $table->string('iban')->nullable();

            // PAN, SSN, NI number, TFN, NRIC — label comes from config.
            $table->string('tax_identifier')->nullable();
            $table->string('tax_regime')->nullable();
            $table->text('tax_notes')->nullable();

            $table->timestamps();

            $table->unique('staff_id');
            $table->index(['owner_id', 'country']);
        });

        Schema::create('salary_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('country', 2);
            $table->string('currency_code', 3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['owner_id', 'name']);
            $table->index(['owner_id', 'country']);
        });

        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            // Null means a workspace-wide component reusable by any structure.
            $table->foreignId('salary_structure_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('code', 40);
            $table->string('name');
            $table->string('type', 30);
            $table->string('calculation', 30);
            // Percentage or fixed amount, depending on calculation.
            $table->decimal('value', 12, 4)->default(0);

            $table->boolean('is_taxable')->default(true);
            // Statutory components come from country rules and should not be
            // silently deleted.
            $table->boolean('is_statutory')->default(false);
            $table->string('country', 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['owner_id', 'salary_structure_id', 'code'], 'salary_components_unique_code');
            $table->index(['owner_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_components');
        Schema::dropIfExists('salary_structures');
        Schema::dropIfExists('employee_payroll_profiles');
    }
};
