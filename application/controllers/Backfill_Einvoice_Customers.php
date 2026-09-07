<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Backfill customers for already-SUBMITTED e-invoice pax.
 *
 * When an e-invoice is submitted, Invoice_Split_Model::Create_Customers_For_New_Pax()
 * auto-creates a customer for any pax whose name OR phone differs from the
 * booking customer (unless one already matches BOTH name and phone). That logic
 * runs only at submit time, so pax submitted before the rule was fixed
 * (2026-08-24: AND -> OR, phone-only guard -> name+phone guard) never got a
 * customer — e.g. relatives sharing one phone, or a company billing name on the
 * booker's phone.
 *
 * This command re-runs the SAME model method over every booking that has at
 * least one submitted (SubmitStatus='S') pax. It is idempotent: the name+phone
 * dedup inside the model skips any pax that already has a matching customer, so
 * re-running creates nothing new. Created rows carry CreatedFromEInvoice=1 and
 * are queued to AutoCount (AutocountSyncAction='C', Status='P') by the model.
 *
 * Usage:
 *   php index.php backfill_einvoice_customers --dry-run --verbose
 *   php index.php backfill_einvoice_customers --verbose
 *
 * Options:
 *   --dry-run    Report what WOULD be created without writing to the database
 *   --verbose    Print a line per booking / created customer
 */
class Backfill_Einvoice_Customers extends CI_Controller
{
    private $dry_run = false;
    private $verbose = false;

    /**
     * Only these pax (by name) are backfilled — the four confirmed cases from
     * the e-invoice screenshots. Matching is case/whitespace-insensitive via
     * einvoice_name_key(). Leave empty to process every submitted pax.
     */
    private $targets = [
        'WISE CARRY HAULAGE SDN BHD',
        'Muhammad faris azhad bin azrulniza',
        'Nur damia qaisara binti azrulniza',
        'Nur delisya qaireena binti azrulniza',
    ];

    /**
     * Handle CLI flags that CodeIgniter would otherwise treat as method names.
     */
    public function _remap($method = 'index')
    {
        if (!$this->input->is_cli_request()) {
            show_error('This script can only be run from the command line.', 403);
            return;
        }

        $args = isset($_SERVER['argv']) ? $_SERVER['argv'] : [];
        $flags = array_slice($args, 2);

        if (in_array('--dry-run', $flags)) {
            $this->dry_run = true;
            echo "🔍 DRY RUN MODE - No changes will be made" . PHP_EOL;
        }
        if (in_array('--verbose', $flags)) {
            $this->verbose = true;
        }

        $this->index();
    }

    public function index()
    {
        if (!$this->input->is_cli_request()) {
            show_error('This script can only be run from the command line.', 403);
            return;
        }

        $this->load->model('Invoice_Split_Model');
        $this->load->model('Customer_Model');
        $this->load->helper('einvoice_customer');

        echo "⚙️ Backfilling customers for submitted e-invoice pax..." . PHP_EOL;
        echo str_repeat('-', 60) . PHP_EOL;

        // Bookings with at least one submitted pax.
        $this->db->distinct();
        $this->db->select('BookingID');
        $this->db->where('SubmitStatus', 'S');
        $this->db->where('Status', 'Y');
        $this->db->order_by('BookingID', 'ASC');
        $rows = $this->db->get('invoice_split_pax')->result_array();

        if (empty($rows)) {
            echo "✅ No submitted e-invoice pax found." . PHP_EOL;
            return;
        }

        echo "📋 Found " . count($rows) . " booking(s) with submitted e-invoice pax" . PHP_EOL;
        echo str_repeat('-', 60) . PHP_EOL;

        $scanned = 0;
        $created_total = 0;
        $bookings_with_creates = 0;

        foreach ($rows as $row) {
            $scanned++;
            $booking_id = (int) $row['BookingID'];

            if ($this->dry_run) {
                $would = $this->preview_creates($booking_id);
                if (!empty($would)) {
                    $bookings_with_creates++;
                    $created_total += count($would);
                    if ($this->verbose) {
                        foreach ($would as $w) {
                            echo "🔍 booking {$booking_id}: [DRY RUN] would create \"{$w['name']}\" ({$w['phone']})" . PHP_EOL;
                        }
                    }
                }
                continue;
            }

            // Apply via the SAME model method used at submit time (fixed rule +
            // name+phone dedup + CreatedFromEInvoice flag + AutoCount queue),
            // but only for the targeted pax.
            $pax_data = $this->filter_targets($this->Invoice_Split_Model->Get_Pax_By_Booking($booking_id));
            if (empty($pax_data)) {
                continue;
            }
            $created = $this->Invoice_Split_Model->Create_Customers_For_New_Pax($booking_id, $pax_data);

            if (!empty($created)) {
                $bookings_with_creates++;
                $created_total += count($created);
                if ($this->verbose) {
                    echo "✅ booking {$booking_id}: created " . count($created)
                        . " customer(s) [IDs: " . implode(', ', $created) . "]" . PHP_EOL;
                }
            }
        }

        echo PHP_EOL . str_repeat('=', 60) . PHP_EOL;
        echo "📊 Summary:" . PHP_EOL;
        echo str_repeat('-', 60) . PHP_EOL;
        echo "   📋 Bookings scanned: {$scanned}" . PHP_EOL;
        echo "   ✅ Customers " . ($this->dry_run ? "to create" : "created") . ": {$created_total}"
            . " across {$bookings_with_creates} booking(s)" . PHP_EOL;

        if ($this->dry_run) {
            echo PHP_EOL . "💡 This was a DRY RUN. No changes were made." . PHP_EOL;
            echo "   Run without --dry-run to apply changes." . PHP_EOL;
        } else {
            echo PHP_EOL . "🎉 Backfill completed." . PHP_EOL;
        }
    }

    /**
     * Keep only the pax whose name is in $this->targets (case/whitespace-
     * insensitive). If no targets are configured, pass everything through.
     *
     * @param array $pax_data
     * @return array
     */
    private function filter_targets($pax_data)
    {
        if (empty($this->targets)) {
            return $pax_data;
        }
        $target_keys = array_map('einvoice_name_key', $this->targets);
        $out = [];
        foreach ($pax_data as $pax) {
            $name = isset($pax['PaxName']) ? $pax['PaxName'] : '';
            if (in_array(einvoice_name_key($name), $target_keys, true)) {
                $out[] = $pax;
            }
        }
        return $out;
    }

    /**
     * Read-only preview: which pax on a booking WOULD become a new customer,
     * mirroring the model's decision (OR rule + name+phone dedup). Never writes.
     *
     * @param int $booking_id
     * @return array List of ['name' => ..., 'phone' => ...].
     */
    private function preview_creates($booking_id)
    {
        $this->db->select('Customer, Mobile');
        $this->db->where('BookingID', $booking_id);
        $booking = $this->db->get('booking')->row_array();
        if (empty($booking)) {
            return [];
        }
        $booking_name  = isset($booking['Customer']) ? $booking['Customer'] : '';
        $booking_phone = isset($booking['Mobile']) ? $booking['Mobile'] : '';

        $out = [];
        $pax_data = $this->filter_targets($this->Invoice_Split_Model->Get_Pax_By_Booking($booking_id));
        foreach ($pax_data as $pax) {
            $pax_name  = isset($pax['PaxName']) ? $pax['PaxName'] : '';
            $pax_phone = isset($pax['PhoneNumber']) ? $pax['PhoneNumber'] : '';
            $same_as_booker = !empty($pax['SameAsBooker']);

            if (!einvoice_pax_needs_new_customer($pax_name, $pax_phone, $booking_name, $booking_phone, $same_as_booker)) {
                continue;
            }
            if (!empty($this->Customer_Model->find_active_by_phone($pax_phone, null, $pax_name))) {
                continue;
            }
            $out[] = ['name' => trim($pax_name), 'phone' => trim($pax_phone)];
        }
        return $out;
    }
}
