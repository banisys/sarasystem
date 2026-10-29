<?php

return [
    'name' => 'Contract',

    // Reminders, sent daily by `contracts:send-reminders` (ADR 0021).
    'reminders' => [
        // Staff are told once when a signed contract ends within this many
        // days and has no renewal.
        'expiring_within_days' => (int) env('CONTRACT_EXPIRING_WITHIN_DAYS', 30),
        // The person is sent an SMS when an issued contract is still not
        // signed this many days after it was issued (or last reminded) ...
        'unsigned_every_days' => (int) env('CONTRACT_UNSIGNED_REMINDER_DAYS', 3),
        // ... at most this many times.
        'unsigned_max' => (int) env('CONTRACT_UNSIGNED_REMINDER_MAX', 3),
    ],
];
