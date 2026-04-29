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
	 * Get bookings where the given supplier date column is today or tomorrow (1 day before and on the day only)
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
		$this->db->where('booking_product.' . $date_column . ' >=', date('Y-m-d'));
		$this->db->where('booking_product.' . $date_column . ' <=', date('Y-m-d', strtotime('+1 day')));
		$this->db->where('booking_product.Status', 'Y');
		$this->db->where('booking_product.disable_checklist_payment_out', 0);
		$this->db->where_in('booking.Status', array('PBC', 'P', 'PP', 'PBO', 'PTV', 'PT', 'OG'));
		$this->db->where('booking.CancelStatus', 'N');
		return $this->db->get()->result();
	}

	/**
	 * Create supplier payment reminder notifications for the booking's TC (SalesAgent) and OP (BookingOP).
	 * Returns count of notifications created.
	 */
	function create_supplier_reminder_notifications($booking_id, $booking_number, $type, $date) {
		$label = ($type == 'supplier_reminder_full') ? 'full' : 'deposit';
		$formatted_date = date('d/m/Y', strtotime($date));
		$message = "Reminder: Payment Out To Supplier ($label) for $booking_number is due on $formatted_date - checklist not completed";

		$count = 0;
		$notified = array();

		// Notify the booking's TC (SalesAgent) and OP (BookingOP)
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
	 * Get bookings where the given supplier date column is strictly before today (overdue)
	 */
	function get_bookings_past_payout_deadline($date_column) {
		$allowed = array('PaymentOutSupplierFull', 'PaymentOutSupplierDeposit');
		if(!in_array($date_column, $allowed)) {
			return array();
		}

		$this->db->select('booking.BookingID, booking.BookingNumber, booking_product.ProductID, booking_product.' . $date_column);
		$this->db->from('booking_product');
		$this->db->join('booking', 'booking.BookingID = booking_product.BookingID');
		$this->db->where('booking_product.' . $date_column . ' IS NOT NULL');
		$this->db->where('booking_product.' . $date_column . ' <', date('Y-m-d'));
		$this->db->where('booking_product.Status', 'Y');
		$this->db->where('booking_product.disable_checklist_payment_out', 0);
		$this->db->where_in('booking.Status', array('PBC', 'P', 'PP', 'PBO', 'PTV', 'PT', 'OG'));
		$this->db->where('booking.CancelStatus', 'N');
		return $this->db->get()->result();
	}

	/**
	 * Create overdue payout notifications for the booking's TC (SalesAgent), OP (BookingOP),
	 * and all active Finance admins (Level 30). Dedupes once per day per recipient.
	 * Returns count of notifications created.
	 */
	function create_payout_overdue_notifications($booking_id, $booking_number, $type, $date) {
		$label = ($type == 'payout_overdue_full') ? 'full' : 'deposit';
		$formatted_date = date('d/m/Y', strtotime($date));
		$message = "OVERDUE: Payment Out To Supplier ($label) for $booking_number was due on $formatted_date - checklist still pending";

		$count = 0;
		$notified = array();

		// Notify the booking's TC (SalesAgent) and OP (BookingOP)
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

		// Notify all active Finance admins (Level 30)
		$this->load->model('Admin_Model');
		$finance_admins = $this->Admin_Model->get_finance_admins();
		foreach($finance_admins as $fa) {
			$fa_id = (int)$fa['AdminID'];
			if(in_array($fa_id, $notified)) {
				continue;
			}
			if($this->has_today_notification($fa_id, $type, $booking_id)) {
				$notified[] = $fa_id;
				continue;
			}

			$this->db->insert('notification', array(
				'user_id' => $fa_id,
				'type' => $type,
				'owner_type' => 'booking',
				'owner_id' => $booking_id,
				'remark_id' => null,
				'message' => $message,
				'is_read' => 0,
				'created_at' => date('Y-m-d H:i:s')
			));
			$count++;
			$notified[] = $fa_id;
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
	 * Run the "no checklist" check for a single product and alert OPs if it fails.
	 * Called on product save paths (replaces the nightly scan).
	 */
	function check_and_notify_no_checklist($product_id) {
		$product_id = (int)$product_id;
		if($product_id <= 0) return 0;

		$this->db->select('ID');
		$this->db->where('is_required', 1);
		$required_rows = $this->db->get('package_checklist')->result();
		$required_ids = array_map(function($r){ return (int)$r->ID; }, $required_rows);

		$this->load->model('Product_Package_Checklist_Model');
		$assigned = $this->Product_Package_Checklist_Model->Get_Checklists_For_Product($product_id);
		$assigned = array_map('intval', $assigned);
		$non_required = array_diff($assigned, $required_ids);

		if(!empty($non_required)) return 0;

		$this->db->select('Name');
		$this->db->where('ProductID', $product_id);
		$product = $this->db->get('product')->row();
		if(!$product) return 0;

		return $this->create_product_no_checklist_notifications($product_id, $product->Name);
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