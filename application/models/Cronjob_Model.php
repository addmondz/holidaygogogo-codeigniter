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
		$this->db->where('booking_product.disable_checklist_payment_out', 0);
		$this->db->where_in('booking.Status', array('P', 'PBO', 'PTV', 'PT', 'OG'));
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

		$label = ($type == 'supplier_reminder_full') ? 'full' : 'deposit';
		$formatted_date = date('d/m/Y', strtotime($date));
		$message = "Reminder: Payment Out To Supplier ($label) for $booking_number is due on $formatted_date - checklist not completed";

		$count = 0;
		$notified = array();
		foreach($admins as $admin) {
			// Check if notification already exists for today
			if($this->has_today_notification($admin->AdminID, $type, $booking_id)) {
				$notified[] = $admin->AdminID;
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
			$notified[] = $admin->AdminID;
		}

		// Also notify the booking's TC (SalesAgent) and OP (BookingOP)
		$this->db->select('SalesAgent, BookingOP');
		$this->db->where('BookingID', $booking_id);
		$booking = $this->db->get('booking')->row();

		if(!empty($booking)) {
			$extra_ids = array();
			if(!empty($booking->SalesAgent)) $extra_ids[] = $booking->SalesAgent;
			if(!empty($booking->BookingOP)) $extra_ids[] = $booking->BookingOP;
			$extra_ids = array_values(array_unique($extra_ids));

			if(!empty($extra_ids)) {
				$this->db->select('AdminID');
				$this->db->where('Status', 'Y');
				$this->db->where_in('AdminID', $extra_ids);
				$active_extras = $this->db->get('admin')->result();

				foreach($active_extras as $admin) {
					if(in_array($admin->AdminID, $notified)) {
						continue;
					}
					if($this->has_today_notification($admin->AdminID, $type, $booking_id)) {
						$notified[] = $admin->AdminID;
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
					$notified[] = $admin->AdminID;
				}
			}
		}

		return $count;
	}

	/**
	 * Create "product has no checklist" notifications for all active Level 40 OP admins.
	 * Dedupes once per day per admin per product via has_today_notification().
	 */
	function create_product_no_checklist_notifications($product_id, $product_name) {
		$this->db->select('AdminID');
		$this->db->where('Status', 'Y');
		$this->db->where('Level', '40');
		$admins = $this->db->get('admin')->result();

		$type = 'product_no_checklist';
		$message = "Product '{$product_name}' has no checklist assigned. Please configure.";

		$count = 0;
		foreach($admins as $admin) {
			if($this->has_today_notification($admin->AdminID, $type, $product_id)) {
				continue;
			}

			$this->db->insert('notification', array(
				'user_id' => $admin->AdminID,
				'type' => $type,
				'owner_type' => 'product',
				'owner_id' => $product_id,
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