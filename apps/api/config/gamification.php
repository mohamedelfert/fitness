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

    /*
    | Badge catalog (FR-ENG-003) — config, not a table. Each badge is earned when the named
    | gamification `stat` (xp | level | streak_days) reaches `gte`; earning is persisted in
    | person_badges (a historical award, never recomputed). Extend here, not in code.
    */
    'badges' => [
        ['slug' => 'streak_7', 'name' => '7-Day Streak', 'stat' => 'streak_days', 'gte' => 7],
        ['slug' => 'streak_30', 'name' => '30-Day Streak', 'stat' => 'streak_days', 'gte' => 30],
        ['slug' => 'level_5', 'name' => 'Level 5', 'stat' => 'level', 'gte' => 5],
        ['slug' => 'level_10', 'name' => 'Level 10', 'stat' => 'level', 'gte' => 10],
        ['slug' => 'xp_1000', 'name' => '1,000 XP', 'stat' => 'xp', 'gte' => 1000],
    ],

];
