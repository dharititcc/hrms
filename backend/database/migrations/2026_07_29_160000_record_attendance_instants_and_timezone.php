<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance recorded the wall-clock time only, read from the server's clock.
 *
 * That is the wrong reading twice over: it is the server's idea of the time
 * rather than the employee's, and a bare "09:12" cannot be converted for
 * anybody looking at it from elsewhere.
 *
 * Three columns fix it, and the wall clock stays because it is what a shift
 * start like 09:00 has to be compared against:
 *
 *   check_in_at, check_out_at  the absolute instant, so any viewer can be
 *                              shown it in their own zone
 *   timezone                   where the employee was when they recorded it,
 *                              which is what makes the wall clock meaningful
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->timestamp('check_in_at')->nullable()->after('check_in');
            $table->timestamp('check_out_at')->nullable()->after('check_out');
            // IANA identifiers run long: "America/Argentina/ComodRivadavia".
            $table->string('timezone', 64)->nullable()->after('check_out_at');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->dropColumn(['check_in_at', 'check_out_at', 'timezone']);
        });
    }
};
