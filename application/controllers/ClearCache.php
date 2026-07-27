<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ClearCache extends CI_Controller {
    public function index() {
        $this->output->delete_cache();
        echo "All cache cleared.";
    }
}
