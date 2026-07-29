<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The rules a day was judged under, kept with the day.
 *
 * break_minutes was already copied from the shift onto the row. The other two
 * were read from config at check-out, which was fine while the figures were
 * only ever computed once -- but recalculating a corrected record would then
 * re-judge an old day under today's settings, quietly changing hours somebody
 * may already have been paid for.
 *
 * Existing rows take the current configuration as their default, which is the
 * only rule they can have been computed under.
 */
return new class extends Migration
{
    public function up(): void
    {
        $breakAfter = (int) config('attendance.break_after_minutes');
        $overtimeAfter = (int) config('attendance.overtime_after_minutes');

        Schema::table('attendances', function (Blueprint $table) use ($breakAfter, $overtimeAfter): void {
            $table->unsignedInteger('break_after_minutes')->default($breakAfter)->after('break_minutes');
            $table->unsignedInteger('overtime_after_minutes')->default($overtimeAfter)->after('break_after_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->dropColumn(['break_after_minutes', 'overtime_after_minutes']);
        });
    }
};
