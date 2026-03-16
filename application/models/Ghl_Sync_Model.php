<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ghl_Sync_Model extends CI_Model
{
    public function create_log($data)
    {
        $this->db->insert('ghl_sync_run_log', $data);
        return (int) $this->db->insert_id();
    }
}
