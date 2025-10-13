<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$config['autocount'] = [
    // Base settings
    'base_url'       => 'https://accounting-api.autocountcloud.com',
    'AUTOCOUNT_keyId'=> 'bb560506-69fd-406d-a64b-8cc0c1be092d',
    'AUTOCOUNT_accountBookId' => '45795',
    'AUTOCOUNT_apiKey' => '45c3a528-c4c5-4306-b036-d05f2a1d84a8',
    
    'client_id'      => 'YOUR_CLIENT_ID',
    'client_secret'  => 'YOUR_CLIENT_SECRET',
    'username'       => 'YOUR_API_USERNAME',
    'password'       => 'YOUR_API_PASSWORD',

    'manual_sync_autocount_key' => '4579545c3a528c4c5a64b8cc0c1be092d',
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
    ],

    'booking_cutoff_date' => '2025-10-01',
    'payment_cutoff_date' => '2025-10-01',

    // Sync settings
    'bulkBookingSyncToAutocount' => false,
    'bulkPaymentSyncToAutocount' => false,

    'booking_sync_autocount_status' => ['P'], // allowed AutocountSyncStatus
    'booking_sync_status'           => ['BOOKING CONFIRMATION'], // allowed BookingConfirmationTitle

    'payment_sync_autocount_status' => ['P'],
    'payment_sync_status'           => ['Y'],

    // Crontab sync qty
    'booking_qty_cront' => 10,
    'payment_qty_cront' => 10,

    'payment_acc_no_1' => '300-d277',
    'payment_acc_no_2' => '400-d019',
];


