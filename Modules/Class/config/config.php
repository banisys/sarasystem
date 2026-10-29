<?php

return [
    'name' => 'Class',

    // Taking attendance (ADR 0024).
    'attendance' => [
        // It opens this many minutes before a session starts ...
        'opens_minutes_before' => (int) env('CLASS_ATTENDANCE_OPENS_MINUTES_BEFORE', 15),
        // ... and closes for teachers this many days after (at the end of
        // that day); whoever may edit the class can still change it.
        'locks_after_days' => (int) env('CLASS_ATTENDANCE_LOCKS_AFTER_DAYS', 3),
    ],
];
