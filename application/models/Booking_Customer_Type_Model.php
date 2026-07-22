<?php
class Booking_Customer_Type_Model extends CI_Model
{
	function Read_By_Booking($booking_id)
	{
		if (empty($booking_id) || !is_numeric($booking_id)) {
			return [];
		}
		$this->db->select('booking_customer_type.BookingCustomerTypeID, booking_customer_type.BookingID, booking_customer_type.CustomerTypeID, customer_type.Name');
		$this->db->join('customer_type', 'customer_type.CustomerTypeID = booking_customer_type.CustomerTypeID', 'left');
		$this->db->where('booking_customer_type.BookingID', $booking_id);
		$this->db->order_by('customer_type.Name', 'ASC');
		return $this->db->get('booking_customer_type')->result();
	}

	function Read_Names_By_Booking($booking_id)
	{
		$rows = $this->Read_By_Booking($booking_id);
		$names = [];
		foreach ($rows as $row) {
			if (!empty($row->Name)) {
				$names[] = $row->Name;
			}
		}
		return $names;
	}

	function Sync($booking_id, $customer_type_ids)
	{
		if (empty($booking_id) || !is_numeric($booking_id)) {
			return;
		}
		if (!is_array($customer_type_ids)) {
			$customer_type_ids = [];
		}

		$ids = [];
		foreach ($customer_type_ids as $value) {
			if (is_numeric($value) && (int)$value > 0) {
				$ids[(int)$value] = true;
			}
		}
		$ids = array_keys($ids);

		$this->db->trans_start();

		$this->db->where('BookingID', $booking_id);
		$this->db->delete('booking_customer_type');

		if (!empty($ids)) {
			$rows = [];
			$now = date('Y-m-d H:i:s');
			foreach ($ids as $customer_type_id) {
				$rows[] = [
					'BookingID'      => $booking_id,
					'CustomerTypeID' => $customer_type_id,
					'InsertDate'     => $now,
				];
			}
			$this->db->insert_batch('booking_customer_type', $rows);
		}

		$this->db->trans_complete();
	}
}
