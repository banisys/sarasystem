<?php

return [
    'name' => 'Campus',

    // How often the building and the map fetch the windows' state; 0 turns it off (ADR 0053).
    'live_seconds' => (int) env('CAMPUS_LIVE_SECONDS', 60),
];
