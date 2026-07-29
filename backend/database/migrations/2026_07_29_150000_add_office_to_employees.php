<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The office an employee normally works from.
 *
 * Nullable, because remote and field staff have none, and because a workspace
 * that has not defined any offices must still be able to add people.
 *
 * Nulls on delete rather than cascading: removing an office should not remove
 * the people who worked there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table): void {
            $table->foreignId('attendance_location_id')
                ->nullable()
                ->after('status')
                ->constrained('attendance_locations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('attendance_location_id');
        });
    }
};
