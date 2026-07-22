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

	function PaymentOutOverdueNotification(){
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

		// Process overdue full-payment notifications
		if($full_checklist_id) {
			$booking_products = $this->Cronjob_Model->get_bookings_past_payout_deadline('PaymentOutSupplierFull');
			foreach($booking_products as $bp) {
				if($this->is_checklist_incomplete_for_product($bp->BookingID, $bp->ProductID, $full_checklist_id)) {
					$count += $this->Cronjob_Model->create_payout_overdue_notifications(
						$bp->BookingID,
						$bp->BookingNumber,
						'payout_overdue_full',
						$bp->PaymentOutSupplierFull
					);
				}
			}
		}

		// Process overdue deposit-payment notifications
		if($deposit_checklist_id) {
			$booking_products = $this->Cronjob_Model->get_bookings_past_payout_deadline('PaymentOutSupplierDeposit');
			foreach($booking_products as $bp) {
				if($this->is_checklist_incomplete_for_product($bp->BookingID, $bp->ProductID, $deposit_checklist_id)) {
					$count += $this->Cronjob_Model->create_payout_overdue_notifications(
						$bp->BookingID,
						$bp->BookingNumber,
						'payout_overdue_deposit',
						$bp->PaymentOutSupplierDeposit
					);
				}
			}
		}

		exit("Done. Overdue notifications created: $count");
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

	# Crontab (every 6 months at 02:00 on 1 Jan and 1 Jul):
	# 0 2 1 1,7 * /usr/bin/php /path/to/holidaygogogo-codeigniter/index.php Cronjob CleanupOldGuestListUploads >> /var/log/holidaygogogo-cron.log 2>&1
	function CleanupOldGuestListUploads()
	{
		if (!is_cli()) {
			show_404();
			return;
		}

		$source_dir = FCPATH . 'assets/upload/passport';
		$backup_dir = FCPATH . 'backups/uploads';

		$zip_path = backup_directory_to_zip($source_dir, $backup_dir, 'passport');
		echo 'Backup: ' . ($zip_path ?: 'skipped (empty source)') . PHP_EOL;

		// Protect passport files attached to bookings that have not yet
		// been marked completed (booking.Status != 'Y'). Orphan files and
		// files belonging to completed bookings remain eligible for cleanup.
		$this->db->select('g.PassportCopy', false);
		$this->db->from('guest_list g');
		$this->db->join('booking b', 'b.BookingID = g.BookingID', 'inner');
		$this->db->where("g.PassportCopy IS NOT NULL", null, false);
		$this->db->where("g.PassportCopy <>", '');
		$this->db->where("b.Status <>", 'Y');
		$protected_rows = $this->db->get()->result();
		$protected_basenames = array_values(array_unique(array_map(
			function ($row) { return basename($row->PassportCopy); },
			$protected_rows
		)));
		echo 'Protected (active booking) passport files: ' . count($protected_basenames) . PHP_EOL;

		$deleted_basenames = delete_files_older_than($source_dir, 365, $protected_basenames);
		echo 'Deleted source files older than 1 year: ' . count($deleted_basenames) . PHP_EOL;

		if (!empty($deleted_basenames)) {
			$deleted_db_paths = array_map(
				function ($name) { return 'assets/upload/passport/' . $name; },
				$deleted_basenames
			);
			$this->db->where_in('PassportCopy', $deleted_db_paths);
			$this->db->update('guest_list', ['PassportCopy' => null]);
			echo 'Cleared guest_list.PassportCopy rows: ' . $this->db->affected_rows() . PHP_EOL;
		}

		$pruned = prune_backups_older_than($backup_dir, 730, 'passport_*.zip');
		echo 'Pruned backup zips older than 2 years: ' . $pruned . PHP_EOL;

		exit('Done');
	}

}