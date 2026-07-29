<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-employee departures from what their role grants.
 *
 * Only differences are stored. Somebody whose access matches their role has no
 * rows here at all, so the role stays the answer to "what can this person do"
 * for almost everybody, and the table reads as a list of exceptions rather
 * than a second copy of the matrix.
 *
 * granted distinguishes the two directions: true adds a permission the role
 * does not include, false takes away one it does.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_permission_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();

            // "module.action", matching the gates registered in AppServiceProvider.
            $table->string('permission', 64);
            $table->boolean('granted');

            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['staff_id', 'permission']);
            $table->index(['owner_id', 'staff_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_permission_overrides');
    }
};
