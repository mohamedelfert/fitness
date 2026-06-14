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
    | Cost meter (NFR-AI-001 / NFR-OPS-002)
    |--------------------------------------------------------------------------
    | Micro-USD per 1,000 tokens, keyed by provider model id. Feeds cost_micros
    | on every ai_interactions row. Unknown/stub models fall back to `default`
    | (0) until the real provider + pricing land with Q5.
    */
    'pricing' => [
        'default' => ['in' => 0, 'out' => 0],
    ],

];
