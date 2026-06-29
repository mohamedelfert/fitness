<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Gamification (FR-ENG-003)
    |--------------------------------------------------------------------------
    | XP is derived on read from the Person's append-only activity (no xp_ledger yet — see the
    | ponytail note in GamificationCalculator). `xp_per_level` is a flat curve: level =
    | floor(xp / xp_per_level) + 1. `points` are the XP awarded per counted activity; keep the
    | source set small (each new source couples gamification into another module).
    */
    'xp_per_level' => 100,

    'points' => [
        'session' => 50,
        'habit_log' => 10,
    ],

];
