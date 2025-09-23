<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$config['autocount'] = [
    // Base settings
    'base_url'       => 'https://accounting-api.autocountcloud.com',
    
    'client_id'      => 'YOUR_CLIENT_ID',
    'client_secret'  => 'YOUR_CLIENT_SECRET',
    'username'       => 'YOUR_API_USERNAME',
    'password'       => 'YOUR_API_PASSWORD',

    // Endpoints
    'endpoints' => [
        'quotation' => [
            'create'        => '/quotation',
            'update'        => '/quotation',
            'update_status' => '/quotation/updatestatus',
            'delete'        => '/quotation',
            'void'          => '/quotation/void',
        ],
        'payment' => [
            'create'        => '/payment',
            'update'        => '/payment',
            'update_status' => '/payment/updatestatus',
            'delete'        => '/pyament',
            'void'          => '/payment/void',
        ]
    ]
];
