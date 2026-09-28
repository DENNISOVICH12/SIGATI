<?php

return [
    'bootstrap' => [
        'engineer' => [
            'name' => env('SIGATI_ENGINEER_NAME', 'Ingeniero SIGATI'),
            'email' => env('SIGATI_ENGINEER_EMAIL'),
            'password' => env('SIGATI_ENGINEER_PASSWORD'),
        ],
        'technician' => [
            'name' => env('SIGATI_TECHNICIAN_NAME', 'Técnico SIGATI'),
            'email' => env('SIGATI_TECHNICIAN_EMAIL'),
            'password' => env('SIGATI_TECHNICIAN_PASSWORD'),
        ],
    ],
];
