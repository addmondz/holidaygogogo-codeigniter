<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Backfill guest_list_room from guest_list for recent bookings
 *
 * Scope:
 *   - All 2026 bookings (by InsertDate year), AND
 *   - 2025 bookings that are NOT completed
 *     (NOT (booking.Status = 'Y' AND booking.AfterSalesService = 'COMPLETE'))
 *   - Excludes voided (Status = 'N') and cancelled (CancelStatus = 'Y') bookings
 *
 * For every in-scope booking that has no guest_list_room records yet, create
 * a single aggregate "ROOM A" whose adult/child/infant counts are summed from
 * the booking's active guest_list rows, then call
 * Guest_List_Model->Auto_Assign_Rooms() so each guest_list row is linked to
 * the newly-created room via guest_list_room_id.
 *
 * Bookings that already have rooms are skipped untouched.
 *
 * Usage:
 *   php index.php backfill_guest_list_rooms --dry-run --verbose
 *   php index.php backfill_guest_list_rooms --verbose
 *   php index.php backfill_guest_list_rooms/stats
 *
 * Options:
 *   --dry-run    Report what would change without writing to the database
 *   --verbose    Print a line per booking processed
 */
class Backfill_Guest_List_Rooms extends CI_Controller
{
    private $dry_run = false;
    private $verbose = false;

    /**
     * Apply the scope filter (2026 all, plus 2025 not-completed) to a query
     * builder chain. Call after you've set ->from('booking').
     */
    private function apply_scope_filter()
    {
        $this->db->where("("
            . "YEAR(booking.InsertDate) = 2026"
            . " OR (YEAR(booking.InsertDate) = 2025 AND NOT (booking.Status = 'Y' AND booking.AfterSalesService = 'COMPLETE'))"
            . ")", null, false);
        $this->db->where('booking.Status !=', 'N');
        $this->db->where('booking.CancelStatus', 'N');
    }

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
        $uri_segments = $this->uri->segment_array();

        $all_flags = array_merge(
            array_slice($args, 3),
            $uri_segments ? array_slice($uri_segments, 1) : []
        );

        if (in_array('--dry-run', $all_flags)) {
            $this->dry_run = true;
            echo "🔍 DRY RUN MODE - No changes will be made" . PHP_EOL;
        }
        if (in_array('--verbose', $all_flags)) {
            $this->verbose = true;
        }

        // Support an optional explicit method (e.g. `stats`) as the first segment
        // after the controller, ignoring flag-looking segments.
        $requested = null;
        if ($uri_segments && count($uri_segments) >= 2) {
            $candidate = $uri_segments[2];
            if (strpos($candidate, '--') !== 0) {
                $requested = $candidate;
            }
        }

        if ($requested === 'stats') {
            $this->stats();
        } else {
            $this->index();
        }
    }

    public function index()
    {
        if (!$this->input->is_cli_request()) {
            show_error('This script can only be run from the command line.', 403);
            return;
        }

        $this->load->model('Guest_List_Room_Model');
        $this->load->model('Guest_List_Model');

        echo "⚙️ Backfilling guest_list_room from guest_list (2026 all + 2025 not-completed)..." . PHP_EOL;
        echo str_repeat('-', 60) . PHP_EOL;

        $this->db->select('BookingID, BookingNumber, InsertDate, Status, AfterSalesService');
        $this->db->from('booking');
        $this->apply_scope_filter();
        $this->db->order_by('BookingID', 'ASC');
        $bookings = $this->db->get()->result_array();

        if (empty($bookings)) {
            echo "✅ No in-scope bookings found." . PHP_EOL;
            return;
        }

        echo "📋 Found " . count($bookings) . " in-scope booking(s)" . PHP_EOL;
        echo str_repeat('-', 60) . PHP_EOL;

        $scanned = 0;
        $backfilled = 0;
        $skipped_has_rooms = 0;
        $skipped_no_gl = 0;
        $error_count = 0;

        foreach ($bookings as $booking) {
            $scanned++;
            $booking_id = $booking['BookingID'];
            $booking_number = $booking['BookingNumber'];

            // Skip if rooms already exist — don't disturb manual edits.
            $existing_rooms = $this->Guest_List_Room_Model->Read_Rooms_By_Booking_ID($booking_id);
            if (!empty($existing_rooms)) {
                if ($this->verbose) {
                    echo "⏭️  #{$booking_number} (ID {$booking_id}): already has " . count($existing_rooms) . " room(s), skipping" . PHP_EOL;
                }
                $skipped_has_rooms++;
                continue;
            }

            // Count active guest_list rows by Type.
            $guests = $this->Guest_List_Model->Read_Guests_By_Booking_ID($booking_id);
            $adult_total = 0;
            $child_total = 0;
            $infant_total = 0;
            foreach ($guests as $g) {
                if ($g->Type == 'ADULT') {
                    $adult_total++;
                } elseif ($g->Type == 'CHILD') {
                    $child_total++;
                } elseif ($g->Type == 'INFANT') {
                    $infant_total++;
                }
            }
            $gl_total = $adult_total + $child_total + $infant_total;

            if ($gl_total === 0) {
                if ($this->verbose) {
                    echo "⏭️  #{$booking_number} (ID {$booking_id}): no guest_list rows, skipping" . PHP_EOL;
                }
                $skipped_no_gl++;
                continue;
            }

            $pax_label = "A:{$adult_total} C:{$child_total} I:{$infant_total}";

            if ($this->dry_run) {
                if ($this->verbose) {
                    echo "🔍 #{$booking_number} (ID {$booking_id}): [DRY RUN] Would create ROOM A ({$pax_label}) and link {$gl_total} guest_list row(s) via Auto_Assign_Rooms" . PHP_EOL;
                }
                $backfilled++;
                continue;
            }

            // Apply: insert room + auto-assign, wrapped in a per-booking transaction.
            $this->db->trans_start();

            $this->db->insert('guest_list_room', [
                'booking_id'   => $booking_id,
                'room_name'    => 'ROOM A',
                'adult_count'  => $adult_total,
                'child_count'  => $child_total,
                'infant_count' => $infant_total,
                'InsertBy'     => 0,
                'InsertDate'   => date('Y-m-d H:i:s'),
            ]);

            $this->Guest_List_Model->Auto_Assign_Rooms($booking_id);

            $this->db->trans_complete();

            if ($this->db->trans_status() === false) {
                $db_error = $this->db->error();
                $error_msg = !empty($db_error['message']) ? $db_error['message'] : 'Unknown error';
                echo "❌ #{$booking_number} (ID {$booking_id}): transaction failed — {$error_msg}" . PHP_EOL;
                $error_count++;
                continue;
            }

            if ($this->verbose) {
                echo "✅ #{$booking_number} (ID {$booking_id}): created ROOM A ({$pax_label}), linked {$gl_total} guest_list row(s)" . PHP_EOL;
            }
            $backfilled++;
        }

        echo PHP_EOL . str_repeat('=', 60) . PHP_EOL;
        echo "📊 Summary:" . PHP_EOL;
        echo str_repeat('-', 60) . PHP_EOL;
        echo "   📋 Scanned: {$scanned} booking(s)" . PHP_EOL;
        echo "   ✅ Backfilled: {$backfilled} booking(s)" . PHP_EOL;
        echo "   ⏭️  Skipped (already has rooms): {$skipped_has_rooms}" . PHP_EOL;
        echo "   ⏭️  Skipped (no guest_list rows): {$skipped_no_gl}" . PHP_EOL;
        if ($error_count > 0) {
            echo "   ❌ Errors: {$error_count} booking(s)" . PHP_EOL;
        }

        if ($this->dry_run) {
            echo PHP_EOL . "💡 This was a DRY RUN. No changes were made." . PHP_EOL;
            echo "   Run without --dry-run to apply changes." . PHP_EOL;
        } else {
            echo PHP_EOL . "🎉 Backfill completed." . PHP_EOL;
        }
    }

    /**
     * Report how many in-scope bookings are candidates for backfill.
     */
    public function stats()
    {
        if (!$this->input->is_cli_request()) {
            show_error('This script can only be run from the command line.', 403);
            return;
        }

        $this->load->model('Guest_List_Room_Model');
        $this->load->model('Guest_List_Model');

        echo "📊 Backfill candidate stats (2026 all + 2025 not-completed)" . PHP_EOL;
        echo str_repeat('=', 60) . PHP_EOL;

        $this->db->select('BookingID, InsertDate');
        $this->db->from('booking');
        $this->apply_scope_filter();
        $bookings = $this->db->get()->result_array();

        $total = count($bookings);
        $has_rooms_2025 = 0;
        $has_rooms_2026 = 0;
        $cand_2025 = 0;
        $cand_2026 = 0;
        $no_gl_2025 = 0;
        $no_gl_2026 = 0;

        foreach ($bookings as $b) {
            $year = (int)date('Y', strtotime($b['InsertDate']));
            $is_2025 = ($year === 2025);

            $rooms = $this->Guest_List_Room_Model->Read_Rooms_By_Booking_ID($b['BookingID']);
            if (!empty($rooms)) {
                if ($is_2025) { $has_rooms_2025++; } else { $has_rooms_2026++; }
                continue;
            }
            $guests = $this->Guest_List_Model->Read_Guests_By_Booking_ID($b['BookingID']);
            if (!empty($guests)) {
                if ($is_2025) { $cand_2025++; } else { $cand_2026++; }
            } else {
                if ($is_2025) { $no_gl_2025++; } else { $no_gl_2026++; }
            }
        }

        echo "   📋 Total in-scope bookings: {$total}" . PHP_EOL;
        echo "   ✅ Already has rooms  — 2026: {$has_rooms_2026}, 2025: {$has_rooms_2025}" . PHP_EOL;
        echo "   🎯 Backfill candidates — 2026: {$cand_2026}, 2025: {$cand_2025}" . PHP_EOL;
        echo "   ⏭️  No GL rows         — 2026: {$no_gl_2026}, 2025: {$no_gl_2025}" . PHP_EOL;
    }
}
