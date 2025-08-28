<?php
class Cronjob_Model extends CI_Model
{
	function update_ongoing(){
		$this->db->set('Status', 'OG');
		$this->db->where('Status', 'PT');
		$this->db->where('StartDate <=', date('Y-m-d'));
		$this->db->where('EndDate >=', date('Y-m-d'));
		return $this->db->update('booking');
	}

	function update_completed(){
		$this->db->set('Status', 'Y');
		$this->db->where_in('Status', 'OG');
		$this->db->where('EndDate <', date('Y-m-d'));
		return $this->db->update('booking');
	}
}