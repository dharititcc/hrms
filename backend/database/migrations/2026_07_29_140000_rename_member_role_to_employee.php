<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The "member" employee role is now "employee", matching the workspace role it
 * has always mapped onto and the module it belongs to.
 *
 * The column is a plain string rather than a database enum, so only the stored
 * values need moving.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('staff')->where('role', 'member')->update(['role' => 'employee']);
    }

    public function down(): void
    {
        DB::table('staff')->where('role', 'employee')->update(['role' => 'member']);
    }
};
