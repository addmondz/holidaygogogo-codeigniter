<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$config['autocount'] = [
    'api_base_url' => 'https://accounting-api.autocountcloud.com',
    'api_key'      => 'YOUR_API_KEY_HERE',

    'endpoints' => [
        'quotation' => [
            'listing' => [
                'method' => 'GET',
                'url'    => '/{accountBookId}/quotation/listing',
            ],
            'get' => [
                'method' => 'GET',
                'url'    => '/{accountBookId}/quotation',
            ],
            'create' => [
                'method' => 'POST',
                'url'    => '/{accountBookId}/quotation',
            ],
            'update' => [
                'method' => 'PUT',
                'url'    => '/{accountBookId}/quotation',
            ],
            'delete' => [
                'method' => 'DELETE',
                'url'    => '/{accountBookId}/quotation',
            ],
            'updateStatus' => [
                'method' => 'PUT',
                'url'    => '/{accountBookId}/quotation/updateStatus',
            ],
            'void' => [
                'method' => 'POST',
                'url'    => '/{accountBookId}/quotation/void',
            ],
        ],
    ],
];

