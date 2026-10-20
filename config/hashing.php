<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hash driver
    |--------------------------------------------------------------------------
    |
    | Passwords are hashed with argon2id (SEC-010). The driver is fixed on
    | purpose; only the cost parameters come from the environment.
    |
    */

    'driver' => 'argon2id',

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => env('HASH_VERIFY', true),
        'limit' => env('BCRYPT_LIMIT', null),
    ],

    'argon' => [
        'memory' => (int) env('ARGON_MEMORY', 65536),
        'threads' => (int) env('ARGON_THREADS', 1),
        'time' => (int) env('ARGON_TIME', 4),
        'verify' => env('HASH_VERIFY', true),
    ],

    'rehash_on_login' => true,

];
