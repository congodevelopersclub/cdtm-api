<?php

return [

    // Separate from CACHE_STORE, which holds the OAuth one-time codes.
    'store' => env('CDTM_CACHE_STORE', 'database'),

    'ttl' => (int) env('CDTM_CACHE_TTL_PROFILES', 300),
    'jitter' => (float) env('CDTM_CACHE_JITTER', 0.2),

    'lock_ttl' => (int) env('CDTM_CACHE_LOCK_TTL', 10),
    'lock_wait' => (int) env('CDTM_CACHE_LOCK_WAIT', 3),

];
