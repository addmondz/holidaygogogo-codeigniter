<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$config['booking_to_autocount_status'] = [
    'Y'   => 1, // Success
    'N'   => 2, // Lost
    'P'   => 0, // Pending
    'PP'  => 0, // Pending (Partial Payment)
    'PT'  => 1, // Success (Payment Taken)
    'OG'  => 3, // Closed
    'PTV' => 4  // Void
];
