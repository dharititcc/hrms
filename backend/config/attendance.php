<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Working day defaults
    |--------------------------------------------------------------------------
    |
    | Used when a workspace has not defined a shift. Times are local to the
    | workspace, stored as HH:MM.
    |
    */
    'default_shift' => [
        'starts_at' => env('ATTENDANCE_SHIFT_START', '09:00'),
        'ends_at' => env('ATTENDANCE_SHIFT_END', '18:00'),
        // Minutes after the start before someone is marked late.
        'grace_minutes' => (int) env('ATTENDANCE_GRACE_MINUTES', 15),
        // Unpaid break deducted from worked hours.
        'break_minutes' => (int) env('ATTENDANCE_BREAK_MINUTES', 60),
    ],

    /** Below this many worked minutes the day counts as a half day. */
    'half_day_threshold_minutes' => (int) env('ATTENDANCE_HALF_DAY_MINUTES', 240),

    /** Worked minutes beyond this count as overtime. */
    'overtime_after_minutes' => (int) env('ATTENDANCE_OVERTIME_AFTER_MINUTES', 480),

    'geofence' => [
        /*
        | When true, an office check-in outside every configured location is
        | refused. Off by default: a workspace with no locations configured
        | would otherwise be unable to check anybody in.
        */
        'enforce' => (bool) env('ATTENDANCE_ENFORCE_GEOFENCE', false),

        /** Fallback radius, in metres, when a location does not set one. */
        'default_radius_metres' => (int) env('ATTENDANCE_DEFAULT_RADIUS', 200),
    ],

];
