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

	/**
	 * Get bookings where the given supplier date column is within 3 days from now or already past
	 */
	function get_bookings_with_supplier_date($date_column) {
		$allowed = array('PaymentOutSupplierFull', 'PaymentOutSupplierDeposit');
		if(!in_array($date_column, $allowed)) {
			return array();
		}

		$this->db->select('booking.BookingID, booking.BookingNumber, booking_product.ProductID, booking_product.' . $date_column);
		$this->db->from('booking_product');
		$this->db->join('booking', 'booking.BookingID = booking_product.BookingID');
		$this->db->where('booking_product.' . $date_column . ' IS NOT NULL');
		$this->db->where('booking_product.' . $date_column . ' >', '1000-01-01');
		$this->db->where('booking_product.' . $date_column . ' <=', date('Y-m-d', strtotime('+3 days')));
		$this->db->where('booking_product.Status', 'Y');
		$this->db->where_in('booking.Status', array('PT', 'OG'));
		return $this->db->get()->result();
	}

	/**
	 * Create supplier payment reminder notifications for all Level 10 admins
	 * Returns count of notifications created
	 */
	function create_supplier_reminder_notifications($booking_id, $booking_number, $type, $date) {
		// Get all active Level 10 admins
		$this->db->select('AdminID');
		$this->db->where('Status', 'Y');
		$this->db->where('level', '10');
		$admins = $this->db->get('admin')->result();

		if(empty($admins)) {
			return 0;
		}

		$label = ($type == 'supplier_reminder_full') ? 'full' : 'deposit';
		$formatted_date = date('d/m/Y', strtotime($date));
		$message = "Reminder: Payment Out To Supplier ($label) for $booking_number is due on $formatted_date - checklist not completed";

		$count = 0;
		foreach($admins as $admin) {
			// Check if notification already exists for today
			if($this->has_today_notification($admin->AdminID, $type, $booking_id)) {
				continue;
			}

			$this->db->insert('notification', array(
				'user_id' => $admin->AdminID,
				'type' => $type,
				'owner_type' => 'booking',
				'owner_id' => $booking_id,
				'remark_id' => null,
				'message' => $message,
				'is_read' => 0,
				'created_at' => date('Y-m-d H:i:s')
			));
			$count++;
		}

		return $count;
	}

	/**
	 * Check if a notification of the given type already exists for today
	 */
	private function has_today_notification($user_id, $type, $owner_id) {
		$this->db->where('user_id', $user_id);
		$this->db->where('type', $type);
		$this->db->where('owner_id', $owner_id);
		$this->db->where('DATE(created_at)', date('Y-m-d'));
		return $this->db->get('notification')->num_rows() > 0;
	}
}