<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Service Area
    |--------------------------------------------------------------------------
    |
    | Sejuk operates in Malang. The center is used when a customer has not
    | shared a location yet.
    |
    */

    'city' => 'Malang',

    'default_location' => [
        'latitude' => -7.9666,
        'longitude' => 112.6326,
    ],

    'search_radius_options' => [1, 3, 5, 10],

    'default_search_radius' => 5,

    'technician_radius_options' => [5, 10, 15],

    /*
    |--------------------------------------------------------------------------
    | Order Rules
    |--------------------------------------------------------------------------
    */

    'response_minutes' => 15,

    'payment_minutes' => 15,

    'auto_complete_hours' => 24,

    'backup_technicians' => 3,

    'booking_days_ahead' => 6,

    'max_order_media' => 4,

    // After this many "Masih bermasalah" responses the order goes to admin review.
    'max_rework' => 2,

    'warranty' => [
        'days' => 30,
        'fee' => 15000,
    ],

    // Charged when a customer rejects the final cost. Free for the MVP.
    'visit_fee' => 0,

    /*
    |--------------------------------------------------------------------------
    | Money
    |--------------------------------------------------------------------------
    |
    | Commission is deducted when held funds are forwarded to the technician.
    |
    */

    'commission_percent' => 10,

    /*
    |--------------------------------------------------------------------------
    | Reference Data
    |--------------------------------------------------------------------------
    */

    'brands' => ['Daikin', 'Panasonic', 'Sharp', 'LG', 'Samsung', 'Gree', 'Polytron', 'Midea', 'Aqua', 'Lainnya'],

    'hero_image' => 'https://images.unsplash.com/photo-1642749776312-aa42ce20c9f5?auto=format&fit=crop&w=1600&q=75',

    'areas' => ['Lowokwaru', 'Klojen', 'Blimbing', 'Sukun', 'Kedungkandang', 'Dinoyo', 'Sawojajar', 'Tlogomas', 'Batu', 'Singosari'],

];
