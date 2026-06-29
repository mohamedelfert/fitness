<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Program generation (FR-AI-001)
    |--------------------------------------------------------------------------
    | `tier` selects the model class (strong = full-plan quality; cheap = swaps,
    | per the model-tiering margin lever in ARCH §6). `max_attempts` bounds the
    | reject-and-regenerate safety loop (FR-AI-007): after this many tries without
    | a safe, valid plan the request fails rather than persist anything (INV-005).
    */
    'program' => [
        'tier' => 'strong',
        'max_attempts' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Meal-plan generation (FR-AI-002)
    |--------------------------------------------------------------------------
    | Same safety-loop semantics as program generation; the dietary post-eval
    | (DietaryScanner) plays the contraindication-scan role.
    */
    'meal_plan' => [
        'tier' => 'strong',
        'max_attempts' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Exercise alternatives (FR-AI-003)
    |--------------------------------------------------------------------------
    | Swaps under equipment/injury constraints. A `cheap` tier — a single-exercise
    | substitution doesn't need full-plan reasoning (the model-tiering margin lever,
    | ARCH §6). Same reject-and-regenerate safety loop as the plan generators.
    */
    'exercise_alternatives' => [
        'tier' => 'cheap',
        'max_attempts' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Plan-adjustment proposals (FR-AI-006)
    |--------------------------------------------------------------------------
    | Reviews a current program and proposes safe incremental changes. `strong`
    | tier — adjustments reason over the whole program's progression, closer to
    | full-plan generation than a single swap. Same reject-and-regenerate safety
    | loop; persists nothing (proposals the member applies later).
    */
    'plan_adjustment' => [
        'tier' => 'strong',
        'max_attempts' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Daily recommendation (FR-AI-004)
    |--------------------------------------------------------------------------
    | A short advisory nudge — `cheap` tier (a motivational line needs no full-plan
    | reasoning). No safety sandwich (it prescribes no library entities; safety is by
    | construction — the prompt forbids specific prescriptions), so no max_attempts loop.
    | Materialised once per Person per day; a same-day refresh is served from cache.
    */
    'daily_recommendation' => [
        'tier' => 'cheap',
    ],

    /*
    |--------------------------------------------------------------------------
    | Conversational coach (FR-AI-008)
    |--------------------------------------------------------------------------
    | A multi-turn chat grounded in the Person's profile. `default` tier — coaching
    | quality is the retention thesis, not a throwaway line (revisit vs cost when Q5
    | pricing lands). No safety sandwich (prescribes no library entities; safety is by
    | construction via the prompt). `history_limit` caps how many recent turns are
    | replayed into the prompt — the cost guardrail on an unbounded conversation
    | (NFR-AI-001). Streaming (SSE) is deferred to the real adapter.
    */
    'coach_chat' => [
        'tier' => 'default',
        'history_limit' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Recovery tips (FR-AI-005)
    |--------------------------------------------------------------------------
    | Advisory recovery readout from recent wearable signals + soreness — `cheap` tier (a short
    | guidance line). No safety sandwich (prescribes no library entities; safety by construction
    | via the prompt). Generated fresh each call (reflects live data); cost bounded by metering,
    | not caching.
    */
    'recovery' => [
        'tier' => 'cheap',
    ],

    /*
    |--------------------------------------------------------------------------
    | Habit nudge (FR-ENG-002)
    |--------------------------------------------------------------------------
    | Advisory behavioural nudge grounded in the Person's habits + streaks — `cheap` tier (a short
    | line). No safety sandwich (prescribes no library entities; safety by construction via the
    | prompt). Generated fresh each call (habit state changes intraday); cost bounded by metering.
    */
    'habit_nudge' => [
        'tier' => 'cheap',
    ],

    /*
    |--------------------------------------------------------------------------
    | Weekly report (FR-AN-005)
    |--------------------------------------------------------------------------
    | An advisory weekly narrative grounded in the Person's progress + adherence read-models.
    | `default` tier — a flagship retention surface, not a throwaway line (revisit vs cost when Q5
    | pricing lands). No safety sandwich (prescribes no library entities; safety by construction
    | via the prompt). Materialised once per Person per ISO week; a same-week refresh is cache-served.
    */
    'weekly_report' => [
        'tier' => 'default',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cost meter (NFR-AI-001 / NFR-OPS-002)
    |--------------------------------------------------------------------------
    | Micro-USD per 1,000 tokens, keyed by provider model id. Feeds cost_micros
    | on every ai_interactions row. Unknown/stub models fall back to `default`
    | (0) until the real provider + pricing land with Q5.
    */
    'pricing' => [
        'default' => ['in' => 0, 'out' => 0],
    ],

    /*
    |--------------------------------------------------------------------------
    | AICredit meter (FR-SAS-004 / NFR-OPS-002)
    |--------------------------------------------------------------------------
    | The user-facing usage unit (distinct from cost_micros, which is our provider
    | spend for margin tracking). Each generation debits `credits.<feature>` from the
    | Person's wallet, falling back to `credits.default`. A wallet that can't cover the
    | cost yields a 402 (API_SPECIFICATION §4); a generation is debited once, only on
    | success (failed/rejected attempts are free to the user — INV-005's safety loop must
    | never cost credits). `free_grant` is the pre-billing starter allotment: wallets are
    | created empty and funded explicitly — E1.9 plan grants replace this stopgap.
    */
    'credits' => [
        'default' => 1,
        'program' => 1,
        'meal_plan' => 1,
        'exercise_alternatives' => 1,
        'plan_adjustment' => 1,
        'daily_recommendation' => 1,
        'coach_chat' => 1,
        'recovery' => 1,
        'weekly_report' => 1,
        'habit_nudge' => 1,
        'free_grant' => 10,
    ],

];
