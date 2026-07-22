<?php
class Slow_Conversion_Reason_Model extends CI_Model
{
	function Read_Slow_Conversion_Reason()
	{
		$this->db->select('SlowConversionReasonID, Name');
		$this->db->where('SlowConversionReasonID', $this->input->get('slow_conversion_reason_id'));
		return $this->db->get('slow_conversion_reason')->row_array();
	}

	function Read_Slow_Conversion_Reasons()
	{
		$this->db->select('SlowConversionReasonID, Name, Status');
		$this->db->where('Status', 'Y');
		$this->db->order_by('Name', 'ASC');
		return $this->db->get('slow_conversion_reason')->result();
	}

	function Create()
	{
		$this->db->insert_batch('slow_conversion_reason', json_decode(json_encode($this->input->post('slow_conversion_reason'))));
	}

	function Update()
	{
		$this->db->update_batch('slow_conversion_reason', json_decode(json_encode($this->input->post('slow_conversion_reason'))), 'SlowConversionReasonID');
	}

	function Detect()
	{
		$this->db->where('Name', $this->input->post('name'));
		if($this->db->get('slow_conversion_reason')->row()) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * The reason ids currently tagged on a booking (junction rows).
	 *
	 * @param int $booking_id
	 * @return int[]
	 */
	function Read_Selected_Reason_Ids($booking_id)
	{
		$this->db->select('SlowConversionReasonID');
		$this->db->where('BookingID', (int) $booking_id);
		$rows = $this->db->get('booking_slow_conversion_reason')->result();
		$ids = array();
		foreach ($rows as $row) {
			$ids[] = (int) $row->SlowConversionReasonID;
		}
		return $ids;
	}

	/**
	 * Sync a booking's tagged reasons to exactly the submitted set, writing only
	 * the difference (diff_selected_reason_ids decides add/remove). Returns the
	 * change set actually applied.
	 *
	 * @param int   $booking_id
	 * @param array $submitted_ids reason ids from the multi-select POST
	 * @param int   $admin_id      editor, stamped on inserted rows
	 * @return array{add:int[], remove:int[]}
	 */
	function Save_Booking_Reasons($booking_id, $submitted_ids, $admin_id)
	{
		$this->load->helper('slow_conversion');
		$booking_id = (int) $booking_id;
		$current = $this->Read_Selected_Reason_Ids($booking_id);
		$diff = diff_selected_reason_ids($current, is_array($submitted_ids) ? $submitted_ids : array());

		if (!empty($diff['remove'])) {
			$this->db->where('BookingID', $booking_id);
			$this->db->where_in('SlowConversionReasonID', $diff['remove']);
			$this->db->delete('booking_slow_conversion_reason');
		}

		if (!empty($diff['add'])) {
			$now = date('Y-m-d H:i:s');
			$rows = array();
			foreach ($diff['add'] as $reason_id) {
				$rows[] = array(
					'BookingID'              => $booking_id,
					'SlowConversionReasonID' => (int) $reason_id,
					'InsertBy'               => $admin_id !== null ? (int) $admin_id : null,
					'InsertDate'             => $now,
				);
			}
			$this->db->insert_batch('booking_slow_conversion_reason', $rows);
		}

		return $diff;
	}
}
