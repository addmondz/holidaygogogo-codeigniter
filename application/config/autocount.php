<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$config['autocount'] = [
    // Base settings
    'base_url'       => 'https://accounting-api.autocountcloud.com',
    'AUTOCOUNT_keyId'=> 'bb560506-69fd-406d-a64b-8cc0c1be092d',
    'AUTOCOUNT_accountBookId' => '45795',
    'AUTOCOUNT_apiKey' => '45c3a528-c4c5-4306-b036-d05f2a1d84a8',
    
    // no use yet
    'client_id'      => 'YOUR_CLIENT_ID',
    'client_secret'  => 'YOUR_CLIENT_SECRET',
    'username'       => 'YOUR_API_USERNAME',
    'password'       => 'YOUR_API_PASSWORD',

    'manual_sync_autocount_key' => '4579545c3a528c4c5a64b8cc0c1be092d', // manual sync cron by url (?key=)
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
            'delete'        => '/payment',
            'void'          => '/payment/void',
        ],
        'debtor' =>[
            'create'        => '/debtor',
            'update'        => '/debtor',
            'delete'        => '/debtor',
        ],
        'creditor' =>[
            'create'        => '/creditor',
            'update'        => '/creditor',
            'delete'        => '/creditor',
        ],
    ],

    'booking_cutoff_date' => '2025-10-01', // after the date only sync
    'payment_cutoff_date' => '2025-10-01', // after the date only sync

    // manually Sync settings
    'bulkBookingSyncToAutocount' => false, // no open for manual trigger
    'bulkPaymentSyncToAutocount' => false, // no open for manual trigger

    'booking_sync_autocount_status' => ['P'], // allowed AutocountSyncStatus
    'booking_sync_status'           => ['BOOKING CONFIRMATION'], // allowed BookingConfirmationTitle

    'payment_sync_autocount_status' => ['P'], // allowed AutocountSyncStatus
    'payment_sync_status'           => ['Y'], // allowed Payemnt approve status 

    // Crontab sync qty
    'booking_qty_cront' => 10, // each time sync 
    'payment_qty_cront' => 10, // each time sync 

    'payment_acc_no_1' => '300-d277', // payment in OR
    'payment_acc_no_2' => '400-d019', // payment out PV

    'supplier_creditTerm' => 'C.O.D.', // supplier need creditTerm
    'customer_creditTerm' => 'C.O.D.', // customer need creditTerm
];


