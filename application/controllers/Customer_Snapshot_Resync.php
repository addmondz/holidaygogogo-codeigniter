<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Rebuild the denormalised customer "self guest" snapshot columns
 * (Gender / DateOfBirth / Nationality / GuestType) from guest_list.
 *
 * Usage (CLI only):
 *   php index.php customer_snapshot_resync
 *
 * The columns are normally kept fresh in real time by Customer_Model::
 * Refresh_Snapshot_* (called from the guest_list + customer write paths). This
 * command is the safety net / initial backfill: it recomputes every customer in
 * one pass. Safe to re-run any time, and can be cron'd for belt-and-braces.
 *
 * Suggested crontab (nightly at 03:15, off-peak):
 * 15 3 * * * /usr/bin/php /path/to/holidaygogogo-codeigniter/index.php customer_snapshot_resync >> /var/log/holidaygogogo-cron.log 2>&1
 */
class Customer_Snapshot_Resync extends CI_Controller
{
    public function index()
    {
        if (!$this->input->is_cli_request()) {
            show_error('This script can only be run from the command line.', 403);
            return;
        }

        $this->load->model('Customer_Model');
        echo "Rebuilding customer guest snapshot..." . PHP_EOL;
        $affected = $this->Customer_Model->Rebuild_All_Snapshots();
        echo "Done. Customers updated: " . (int) $affected . PHP_EOL;
    }
}
