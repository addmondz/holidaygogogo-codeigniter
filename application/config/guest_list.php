<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Guest List Locking Configuration
|--------------------------------------------------------------------------
|
| Heartbeat-based soft locking system for preventing concurrent edits
|
*/

// Heartbeat interval in seconds (how often to ping server)
$config['guest_list_heartbeat_interval'] = 30;

// Lock timeout in seconds (miss 2 heartbeats = expired)
$config['guest_list_lock_timeout'] = 90;

// Lock token length for non-logged-in users
$config['guest_list_token_length'] = 64;

