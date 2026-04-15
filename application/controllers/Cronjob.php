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
			$booking_products = $this->Cronjob_Model->get_bookings_with_supplier_date('PaymentOutSupplierFull');
			foreach($booking_products as $bp) {
				if($this->is_checklist_incomplete_for_product($bp->BookingID, $bp->ProductID, $full_checklist_id)) {
					$count += $this->Cronjob_Model->create_supplier_reminder_notifications(
						$bp->BookingID,
						$bp->BookingNumber,
						'supplier_reminder_full',
						$bp->PaymentOutSupplierFull
					);
				}
			}
		}

		// Process deposit payment reminders
		if($deposit_checklist_id) {
			$booking_products = $this->Cronjob_Model->get_bookings_with_supplier_date('PaymentOutSupplierDeposit');
			foreach($booking_products as $bp) {
				if($this->is_checklist_incomplete_for_product($bp->BookingID, $bp->ProductID, $deposit_checklist_id)) {
					$count += $this->Cronjob_Model->create_supplier_reminder_notifications(
						$bp->BookingID,
						$bp->BookingNumber,
						'supplier_reminder_deposit',
						$bp->PaymentOutSupplierDeposit
					);
				}
			}
		}

		exit("Done. Notifications created: $count");
	}

	function ProductNoChecklistReminder(){
		$this->load->model('Product_Package_Checklist_Model');

		$this->db->select('ID');
		$this->db->where('is_required', 1);
		$required_rows = $this->db->get('package_checklist')->result();
		$required_ids = array_map(function($r){ return (int)$r->ID; }, $required_rows);

		$this->db->select('ProductID, Name');
		$this->db->where('Status', 'Y');
		$products = $this->db->get('product')->result();

		$count = 0;
		$matched = array();
		foreach($products as $product) {
			$assigned = $this->Product_Package_Checklist_Model->Get_Checklists_For_Product($product->ProductID);
			$assigned = array_map('intval', $assigned);
			$non_required = array_diff($assigned, $required_ids);
			if(empty($non_required)) {
				$matched[] = $product->ProductID . ':' . $product->Name;
				$count += $this->Cronjob_Model->create_product_no_checklist_notifications(
					$product->ProductID,
					$product->Name
				);
			}
		}

		echo "Products count: " . count($products) . "<br>";
		echo "Required checklist IDs: " . implode(',', $required_ids) . "<br>";
		echo "Matched products (" . count($matched) . "): <pre>" . implode("\n", $matched) . "</pre><br>";
		exit("Done. Notifications created: $count");
	}

	/**
	 * Check if a specific product in a booking has the given checklist assigned but not completed
	 */
	private function is_checklist_incomplete_for_product($booking_id, $product_id, $checklist_id) {
		$assigned_checklists = $this->Product_Package_Checklist_Model->Get_Checklists_For_Product($product_id);

		if(!in_array($checklist_id, $assigned_checklists)) {
			return false;
		}

		// Check if this specific product's checklist is completed
		$this->db->where('booking_id', $booking_id);
		$this->db->where('product_id', $product_id);
		$this->db->where('package_checklist_id', $checklist_id);
		$completed = $this->db->get('booking_checklist_completion')->num_rows() > 0;

		return !$completed;
	}

}