<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Backfill booking.TeamID for POST-cutoff bookings onto the TC2's team.
 *
 * Why: booking.TeamID is a frozen point-in-time snapshot of the CREDITED agent's
 * team, and the credited agent follows the TC1/TC2 cutoff (TC1 before
 * LEAD_CONVERSION_TC2_CUTOFF_DATE, TC2 on/after). The write path was corrected to
 * freeze on the credited agent, but bookings created on/after the cutoff BEFORE
 * that fix still carry the old TC1-based TeamID, so "Total Sales by Team"
 * misattributes them. This one-time backfill re-freezes each in-scope row onto
 * its TC2's (booking.SalesAgent2) CURRENT team — matching what the write path now
 * produces — so historical rows and future rows agree.
 *
 * Scope:
 *   - booking.InsertDate >= cutoff (post-cutoff only; pre-cutoff stays on TC1), AND
 *   - booking.Status <> 'N' (voided/hard-deleted rows excluded).
 *   Cancelled rows are included so their frozen team stays consistent; the report
 *   filters them out at read time anyway.
 *
 * New TeamID = SalesAgent2's current admin.TeamID, or NULL (Unassigned) when TC2
 * is empty or teamless. Only rows whose current TeamID differs are updated.
 *
 * Usage:
 *   php index.php backfill_team_snapshot_cutoff --dry-run --verbose
 *   php index.php backfill_team_snapshot_cutoff --verbose
 *   php index.php backfill_team_snapshot_cutoff/stats
 *
 * Options:
 *   --dry-run    Report what would change without writing to the database
 *   --verbose    Print a line per booking updated
 */
class Backfill_Team_Snapshot_Cutoff extends CI_Controller
{
    private $dry_run = false;
    private $verbose = false;

    private function cutoff()
    {
        return defined('LEAD_CONVERSION_TC2_CUTOFF_DATE')
            ? LEAD_CONVERSION_TC2_CUTOFF_DATE
            : '2026-06-01';
    }

    /**
     * In-scope, credited-team-resolved rows: post-cutoff, not voided, with the
     * TC2's current team joined in (NULL when TC2 is empty/absent or teamless).
     */
    private function fetch_candidates()
    {
        $cutoff = $this->cutoff();
        $this->db->select('booking.BookingID, booking.BookingNumber, booking.InsertDate,'
            . ' booking.SalesAgent2, booking.TeamID AS CurrentTeamID, a2.TeamID AS CreditTeamID', false);
        $this->db->from('booking');
        // Only join a real TC2 so a 0/NULL slot resolves to CreditTeamID = NULL.
        $this->db->join('admin a2', 'a2.AdminID = booking.SalesAgent2 AND booking.SalesAgent2 > 0', 'left');
        $this->db->where("CAST(booking.InsertDate AS DATE) >=", $cutoff);
        $this->db->where('booking.Status !=', 'N');
        $this->db->order_by('booking.BookingID', 'ASC');
        return $this->db->get()->result();
    }

    /** Normalise a team value to int-or-null for comparison. */
    private function norm($v)
    {
        return ($v === null || $v === '') ? null : (int) $v;
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

        $cutoff = $this->cutoff();
        echo "⚙️ Re-freezing booking.TeamID onto the TC2's team for post-cutoff bookings (>= {$cutoff})..." . PHP_EOL;
        echo str_repeat('-', 60) . PHP_EOL;

        $bookings = $this->fetch_candidates();

        if (empty($bookings)) {
            echo "✅ No in-scope bookings found." . PHP_EOL;
            return;
        }

        echo "📋 Found " . count($bookings) . " in-scope booking(s)" . PHP_EOL;
        echo str_repeat('-', 60) . PHP_EOL;

        $scanned = 0;
        $updated = 0;
        $already_correct = 0;
        $to_unassigned = 0;
        $error_count = 0;

        foreach ($bookings as $b) {
            $scanned++;
            $cur = $this->norm($b->CurrentTeamID);
            $new = $this->norm($b->CreditTeamID);

            if ($cur === $new) {
                $already_correct++;
                continue;
            }

            $from = ($cur === null) ? 'Unassigned' : "Team {$cur}";
            $to   = ($new === null) ? 'Unassigned' : "Team {$new}";
            if ($new === null) {
                $to_unassigned++;
            }

            if ($this->dry_run) {
                if ($this->verbose) {
                    echo "🔍 #{$b->BookingNumber} (ID {$b->BookingID}): [DRY RUN] {$from} -> {$to}" . PHP_EOL;
                }
                $updated++;
                continue;
            }

            $this->db->where('BookingID', $b->BookingID);
            $this->db->update('booking', array('TeamID' => $new));

            if ($this->db->affected_rows() < 0) {
                $db_error = $this->db->error();
                $error_msg = !empty($db_error['message']) ? $db_error['message'] : 'Unknown error';
                echo "❌ #{$b->BookingNumber} (ID {$b->BookingID}): update failed — {$error_msg}" . PHP_EOL;
                $error_count++;
                continue;
            }

            if ($this->verbose) {
                echo "✅ #{$b->BookingNumber} (ID {$b->BookingID}): {$from} -> {$to}" . PHP_EOL;
            }
            $updated++;
        }

        echo PHP_EOL . str_repeat('=', 60) . PHP_EOL;
        echo "📊 Summary:" . PHP_EOL;
        echo str_repeat('-', 60) . PHP_EOL;
        echo "   📋 Scanned: {$scanned} booking(s)" . PHP_EOL;
        echo "   ✅ Re-teamed: {$updated} booking(s)" . PHP_EOL;
        echo "   ↪️  Of which moved to Unassigned: {$to_unassigned}" . PHP_EOL;
        echo "   ⏭️  Already correct: {$already_correct}" . PHP_EOL;
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
     * Report how many post-cutoff bookings would be re-teamed, without writing.
     */
    public function stats()
    {
        if (!$this->input->is_cli_request()) {
            show_error('This script can only be run from the command line.', 403);
            return;
        }

        $cutoff = $this->cutoff();
        echo "📊 Backfill candidate stats (post-cutoff >= {$cutoff})" . PHP_EOL;
        echo str_repeat('=', 60) . PHP_EOL;

        $bookings = $this->fetch_candidates();
        $total = count($bookings);
        $need_update = 0;
        $to_unassigned = 0;
        $already_correct = 0;

        foreach ($bookings as $b) {
            $cur = $this->norm($b->CurrentTeamID);
            $new = $this->norm($b->CreditTeamID);
            if ($cur === $new) {
                $already_correct++;
                continue;
            }
            $need_update++;
            if ($new === null) {
                $to_unassigned++;
            }
        }

        echo "   📋 Total in-scope bookings: {$total}" . PHP_EOL;
        echo "   🎯 Would be re-teamed: {$need_update}" . PHP_EOL;
        echo "   ↪️  Of which -> Unassigned: {$to_unassigned}" . PHP_EOL;
        echo "   ⏭️  Already correct: {$already_correct}" . PHP_EOL;
    }
}
