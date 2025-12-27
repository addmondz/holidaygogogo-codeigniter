<?php
class Booking_Checklist_Completion_Model extends CI_Model
{
	function Read_Completion_Map($booking_id)
	{
		$this->db->select('bcc.package_checklist_id, bcc.created_by, bcc.created_at, a.Name as created_by_name');
		$this->db->from('booking_checklist_completion bcc');
		$this->db->join('admin a', 'a.AdminID = bcc.created_by', 'left');
		$this->db->where('bcc.booking_id', $booking_id);
		$results = $this->db->get()->result();
		
		$completion_map = array();
		foreach($results as $result) {
			$completion_map[$result->package_checklist_id] = array(
				'created_by' => $result->created_by,
				'created_by_name' => $result->created_by_name,
				'created_at' => $result->created_at
			);
		}
		return $completion_map;
	}

	function Create($booking_id, $package_checklist_ids, $created_by)
	{
		// First, delete existing completions for this booking
		$this->db->where('booking_id', $booking_id);
		$this->db->delete('booking_checklist_completion');
		
		// Insert new completions
		if(!empty($package_checklist_ids) && is_array($package_checklist_ids)) {
			$data = array();
			foreach($package_checklist_ids as $checklist_id) {
				$data[] = array(
					'booking_id' => $booking_id,
					'package_checklist_id' => $checklist_id,
					'created_by' => $created_by,
					'created_at' => date('Y-m-d H:i:s')
				);
			}
			if(!empty($data)) {
				$this->db->insert_batch('booking_checklist_completion', $data);
			}
		}
	}

}

