<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Recompute every costing scenario's stored booking financials.
 *
 * Why: the customer-pricing margin was changed from a MARKUP ON COST
 * (cost x (1 + margin%)) to a GROSS MARGIN (cost / (1 - margin%)). Cost rows,
 * pax and margin are unchanged, but the stored costing_booking_financials roll-up
 * (price_per_pax / markup_amount_total / selling_price_per_pax / revenue / profit)
 * was frozen under the old formula. The wizard recomputes live client-side, but
 * the scenario listing reads the stored row, so old scenarios still show the
 * markup-on-cost numbers until re-saved. This one-time backfill re-runs
 * Costing_Model::Recalculate_Booking_Financials() for every booking so stored
 * rows match the new gross-margin formula.
 *
 * Usage:
 *   php index.php backfill_costing_financials --dry-run --verbose
 *   php index.php backfill_costing_financials --verbose
 */
class Backfill_Costing_Financials extends CI_Controller
{
    private $dry_run = false;
    private $verbose = false;

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Costing_Model');
    }

    public function _remap($method = 'index')
    {
        if (!$this->input->is_cli_request()) {
            show_error('This script can only be run from the command line.', 403);
            return;
        }

        $args = isset($_SERVER['argv']) ? array_slice($_SERVER['argv'], 2) : [];
        if (in_array('--dry-run', $args, true)) {
            $this->dry_run = true;
            echo "🔍 DRY RUN MODE - No changes will be made" . PHP_EOL;
        }
        if (in_array('--verbose', $args, true)) {
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

        echo "⚙️ Recomputing costing financials under the gross-margin formula..." . PHP_EOL;
        echo str_repeat('-', 60) . PHP_EOL;

        $bookings = $this->db
            ->select('id, package_id')
            ->order_by('id', 'ASC')
            ->get('costing_bookings')
            ->result();

        if (empty($bookings)) {
            echo "✅ No costing scenarios found." . PHP_EOL;
            return;
        }

        echo "📋 Found " . count($bookings) . " scenario(s)" . PHP_EOL;
        echo str_repeat('-', 60) . PHP_EOL;

        $scanned = 0;
        $updated = 0;
        $errors = 0;

        foreach ($bookings as $b) {
            $scanned++;

            if ($this->dry_run) {
                if ($this->verbose) {
                    echo "🔍 [DRY RUN] scenario #{$b->id} (package {$b->package_id})" . PHP_EOL;
                }
                $updated++;
                continue;
            }

            $ok = $this->Costing_Model->Recalculate_Booking_Financials((int) $b->id);
            if ($ok === false) {
                echo "❌ scenario #{$b->id}: recompute failed" . PHP_EOL;
                $errors++;
                continue;
            }

            if ($this->verbose) {
                echo "✅ scenario #{$b->id} (package {$b->package_id})" . PHP_EOL;
            }
            $updated++;
        }

        echo PHP_EOL . str_repeat('=', 60) . PHP_EOL;
        echo "📊 Summary:" . PHP_EOL;
        echo "   📋 Scanned: {$scanned}" . PHP_EOL;
        echo "   ✅ Recomputed: {$updated}" . PHP_EOL;
        if ($errors > 0) {
            echo "   ❌ Errors: {$errors}" . PHP_EOL;
        }

        if ($this->dry_run) {
            echo PHP_EOL . "💡 DRY RUN — nothing written. Re-run without --dry-run to apply." . PHP_EOL;
        } else {
            echo PHP_EOL . "🎉 Backfill completed." . PHP_EOL;
        }
    }
}
