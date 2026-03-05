<?php
class Cronjob extends CI_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Cronjob_Model');
	}

	function UpdateOngoing(){
		$this->Cronjob_Model->update_ongoing();
		exit('Done');
	}

	function UpdateCompleted(){
		$this->Cronjob_Model->update_completed();
		exit('Done');
	}

	function PaymentOutSupplierReminder(){
		$this->load->model('Notification_Model');
		$this->load->model('Product_Package_Checklist_Model');

		// Look up checklist IDs by name
		$this->db->select('ID');
		$this->db->like('name', 'Payment Out To Supplier (full)');
		$full_checklist = $this->db->get('package_checklist')->row();
		$full_checklist_id = $full_checklist ? (int)$full_checklist->ID : null;

		$this->db->select('ID');
		$this->db->like('name', 'Payment Out To Supplier (deposit)');
		$deposit_checklist = $this->db->get('package_checklist')->row();
		$deposit_checklist_id = $deposit_checklist ? (int)$deposit_checklist->ID : null;

		$count = 0;

		// Process full payment reminders
		if($full_checklist_id) {
			$bookings = $this->Cronjob_Model->get_bookings_with_supplier_date('PaymentOutSupplierFull');
			foreach($bookings as $booking) {
				if($this->has_incomplete_checklist($booking, $full_checklist_id)) {
					$count += $this->Cronjob_Model->create_supplier_reminder_notifications(
						$booking->BookingID,
						$booking->BookingNumber,
						'supplier_reminder_full',
						$booking->PaymentOutSupplierFull
					);
				}
			}
		}

		// Process deposit payment reminders
		if($deposit_checklist_id) {
			$bookings = $this->Cronjob_Model->get_bookings_with_supplier_date('PaymentOutSupplierDeposit');
			foreach($bookings as $booking) {
				if($this->has_incomplete_checklist($booking, $deposit_checklist_id)) {
					$count += $this->Cronjob_Model->create_supplier_reminder_notifications(
						$booking->BookingID,
						$booking->BookingNumber,
						'supplier_reminder_deposit',
						$booking->PaymentOutSupplierDeposit
					);
				}
			}
		}

		exit("Done. Notifications created: $count");
	}

	/**
	 * Check if a booking has any product with the given checklist assigned but not completed
	 */
	private function has_incomplete_checklist($booking, $checklist_id) {
		// Get all products for this booking
		$this->db->select('ProductID');
		$this->db->where('BookingID', $booking->BookingID);
		$this->db->where('Status', 'Y');
		$products = $this->db->get('booking_product')->result();

		if(empty($products)) {
			return false;
		}

		// Get completion map for this booking
		$this->db->select('product_id, package_checklist_id');
		$this->db->where('booking_id', $booking->BookingID);
		$completions = $this->db->get('booking_checklist_completion')->result();

		$completion_map = array();
		foreach($completions as $c) {
			$completion_map[$c->product_id . '_' . $c->package_checklist_id] = true;
		}

		// Check each product
		foreach($products as $product) {
			$assigned_checklists = $this->Product_Package_Checklist_Model->Get_Checklists_For_Product($product->ProductID);

			if(in_array($checklist_id, $assigned_checklists)) {
				$key = $product->ProductID . '_' . $checklist_id;
				if(!isset($completion_map[$key])) {
					return true; // Found an incomplete checklist
				}
			}
		}

		return false;
	}

}