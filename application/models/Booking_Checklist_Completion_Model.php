<?php
class Booking_Checklist_Completion_Model extends CI_Model
{
	/**
	 * Read completion map nested by product_id
	 * Returns: $map[product_id][checklist_id] = info
	 */
	function Read_Completion_Map($booking_id)
	{
		$this->db->select('bcc.product_id, bcc.package_checklist_id, bcc.created_by, bcc.created_at, a.Name as created_by_name');
		$this->db->from('booking_checklist_completion bcc');
		$this->db->join('admin a', 'a.AdminID = bcc.created_by', 'left');
		$this->db->where('bcc.booking_id', $booking_id);
		$results = $this->db->get()->result();

		$completion_map = array();
		foreach($results as $result) {
			$product_id = $result->product_id;
			if(!isset($completion_map[$product_id])) {
				$completion_map[$product_id] = array();
			}
			$completion_map[$product_id][$result->package_checklist_id] = array(
				'created_by' => $result->created_by,
				'created_by_name' => $result->created_by_name,
				'created_at' => $result->created_at
			);
		}
		return $completion_map;
	}

	/**
	 * Create checklist completions
	 * $completions is an array of [product_id, checklist_id] pairs
	 */
	function Create($booking_id, $completions, $created_by)
	{
		// First, delete existing completions for this booking
		$this->db->where('booking_id', $booking_id);
		$this->db->delete('booking_checklist_completion');

		// Insert new completions
		if(!empty($completions) && is_array($completions)) {
			$data = array();
			foreach($completions as $completion) {
				$data[] = array(
					'booking_id' => $booking_id,
					'product_id' => $completion[0],
					'package_checklist_id' => $completion[1],
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
