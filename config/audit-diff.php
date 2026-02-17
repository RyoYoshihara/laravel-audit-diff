<?php

return [
    'enabled' => true,

    // v0.1: updated only
    'events' => ['updated'],

    // diff calculation
    'null_equals_empty_string' => true,
    'skip_if_only_timestamps_changed' => true,

    // storage strategy
    // false: store only changed keys (default)
    // true : store full snapshot (future-proof option)
    'store_full_snapshot' => false,

    // security
    'mask_keys' => [
        'password',
        'token',
        'secret',
        'api_key',
        'authorization',
    ],

    // actor resolution (optional)
    // Closure: fn () => ['id' => '...', 'type' => '...']
    'actor_resolver' => null,
];