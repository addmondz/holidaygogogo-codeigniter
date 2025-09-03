<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$config['autocount'] = [
    // Base settings
    'base_url'     => 'https://api.autocountcloud.com',
    'client_id'    => 'YOUR_CLIENT_ID',
    'client_secret'=> 'YOUR_CLIENT_SECRET',
    'username'     => 'YOUR_API_USERNAME',
    'password'     => 'YOUR_API_PASSWORD',

    // Endpoints (add more later if needed)
    'endpoints' => [
		'quotation' => [
			'create'        => '/quotation/create',
			'update'        => '/quotation/update',
			'update_status' => '/quotation/updatestatus',
			'delete'        => '/quotation/delete',
			'void'          => '/quotation/void',
		],
		'payment' => [
			'create'        => '/cashbook/create',
			'update'        => '/cashbook/update',
			'update_status' => '/cashbook/updatestatus',
			'delete'        => '/cashbook/delete',
			'void'          => '/cashbook/void',
		]
	]
];
