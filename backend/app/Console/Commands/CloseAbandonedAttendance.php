<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Services\AttendanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Closes days somebody checked into and never checked out of.
 *
 * Left alone these sit at zero worked forever: the person vanishes from the
 * hours total, and the day can never become a half day or anything else,
 * because every derived figure is written at check-out and check-out never
 * came.
 *
 * The close is deliberately conservative. It does not invent a leaving time —
 * it uses the end of the shift the day was opened against, marks the record
 * manual and sends it for approval, so a human confirms the figure rather than
 * a scheduled job quietly deciding what somebody was paid for.
 */
class CloseAbandonedAttendance extends Command
{
    protected $signature = 'attendance:close-abandoned
                            {--days=1 : How many days back to look, so a run is not left open mid-shift}
                            {--dry-run : Report what would be closed without touching anything}';

    protected $description = 'Close attendance left open past its shift, for approval';

    public function handle(AttendanceService $service): int
    {
        $before = now()->subDays(max(1, (int) $this->option('days')))->toDateString();

        $abandoned = Attendance::query()
            ->whereNotNull('check_in')
            ->whereNull('check_out')
            ->whereDate('work_date', '<=', $before)
            ->with('shift')
            ->get();

        if ($abandoned->isEmpty()) {
            $this->info('Nothing left open.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        foreach ($abandoned as $attendance) {
            $closesAt = $this->shiftEndFor($attendance);

            $this->line(sprintf(
                '%s employee %d on %s: %s -> %s',
                $dryRun ? 'Would close' : 'Closing',
                $attendance->staff_id,
                $attendance->work_date->toDateString(),
                $attendance->check_in,
                $closesAt,
            ));

            if (! $dryRun) {
                $service->correct($attendance, $attendance->employee?->user ?? $attendance->owner, [
                    'check_out' => $closesAt,
                    'notes' => trim(($attendance->notes ? $attendance->notes."\n" : '')
                        .'Closed automatically at the end of the shift; no check-out was recorded.'),
                ]);
            }
        }

        $this->info(sprintf('%s %d record(s).', $dryRun ? 'Would close' : 'Closed', $abandoned->count()));

        return self::SUCCESS;
    }

    /**
     * The end of the shift the day was opened against, or the configured
     * default. Never earlier than the check-in, since a day cannot close
     * before it opened.
     */
    private function shiftEndFor(Attendance $attendance): string
    {
        $end = $attendance->shift?->ends_at ?? config('attendance.default_shift.ends_at');
        $normalised = Carbon::parse($end)->format('H:i:s');

        return $normalised > (string) $attendance->check_in ? $normalised : '23:59:00';
    }
}
