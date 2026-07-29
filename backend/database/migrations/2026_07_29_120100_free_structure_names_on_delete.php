<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Salary structures are soft-deleted, but the unique index was on
 * (owner_id, name) alone, so a deleted structure kept its name reserved
 * forever and it could never be recreated.
 *
 * Adding deleted_at to the index fixes that: NULL compares as distinct in a
 * unique index, so many deleted rows may share a name while only one live row
 * can hold it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_structures', function (Blueprint $table): void {
            $table->dropUnique(['owner_id', 'name']);
            $table->unique(['owner_id', 'name', 'deleted_at'], 'salary_structures_owner_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('salary_structures', function (Blueprint $table): void {
            $table->dropUnique('salary_structures_owner_name_unique');
            $table->unique(['owner_id', 'name']);
        });
    }
};
