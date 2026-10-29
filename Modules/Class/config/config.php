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
        // An SMS to the guardians (with «پیامک» on) of an attendee marked
        // absent, once per absence (ADR 0029).
        'absence_sms' => (bool) env('CLASS_ABSENCE_SMS', true),
    ],

    // Recording what a session covered (ADR 0025): opens like attendance and
    // closes for teachers this many days after the session.
    'content' => [
        'locks_after_days' => (int) env('CLASS_CONTENT_LOCKS_AFTER_DAYS', 7),
    ],

    // Handing in homework (ADR 0030).
    'homework' => [
        // What a homework is scored out of unless the teacher says otherwise.
        'default_max_score' => (int) env('CLASS_HOMEWORK_MAX_SCORE', 20),
        // After the due day, homework is still taken (marked late) ...
        'accepts_late' => (bool) env('CLASS_HOMEWORK_ACCEPTS_LATE', true),
    ],
];
