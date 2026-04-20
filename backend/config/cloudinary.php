<?php

return [

    'notification_url' => env('CLOUDINARY_NOTIFICATION_URL'),

    'cloud_url' => env('CLOUDINARY_URL', 'cloudinary://'
        . env('CLOUDINARY_API_KEY') . ':'
        . env('CLOUDINARY_API_SECRET') . '@'
        . env('CLOUDINARY_CLOUD_NAME')),

    'upload_preset' => env('CLOUDINARY_UPLOAD_PRESET'),
    'upload_route' => env('CLOUDINARY_UPLOAD_ROUTE'),
    'upload_action' => env('CLOUDINARY_UPLOAD_ACTION'),

    'cloud' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
    ],

    'api' => [
        'key' => env('CLOUDINARY_API_KEY'),
        'secret' => env('CLOUDINARY_API_SECRET'),
    ],
];
