<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Summary-card ROLE gating — which card SET a role sees, given the page.
 *
 * The booking listing and the Owner Dashboard both render
 * views/booking/_summary_cards.php, but the Owner must see a different card
 * set on each:
 *
 *   - Booking listing  (`$owner_as_agent = true`):  Owner (10) and Team Lead (25)
 *     see the SALES-AGENT card set scoped to their own bookings — identical to
 *     Sales Agent (20) and TC (50).
 *   - Owner Dashboard  (`$owner_as_agent = false`): Owner (10) keeps the
 *     per-agent performance matrix. Team Lead (25) gets the SAME per-agent
 *     matrix, scoped to their own team members (see
 *     summary_cards_show_team_lead_matrix / Booking::owner_agent_matrix).
 *
 * So Team Lead (25) mirrors the Owner exactly: sales-agent cards on the listing,
 * the per-agent matrix on the dashboard.
 *
 * Pure (no DB, no session) so it can be unit-tested and shared verbatim by the
 * view (display gating) and Booking::ajax_summary_cards (data gating), keeping
 * the rendered skeleton and the JSON payload in agreement.
 */

if (!function_exists('summary_cards_show_agent_set')) {
    /**
     * True when the given role sees the sales-agent card set on this page.
     *
     * @param int|string $level          admin level
     * @param bool       $owner_as_agent true on the booking listing, false on the Owner Dashboard
     */
    function summary_cards_show_agent_set($level, $owner_as_agent)
    {
        $level = (int) $level;
        // Sales Agent (20) and TC (50) always get the agent cards.
        if ($level === 20 || $level === 50) {
            return true;
        }
        // Owner (10) and Team Lead (25) get them only on the booking listing;
        // on the dashboard they see the per-agent matrix instead.
        return (($level === 10 || $level === 25) && (bool) $owner_as_agent);
    }
}

if (!function_exists('summary_cards_show_team_lead_matrix')) {
    /**
     * True when a Team Lead (25) sees the per-agent performance matrix — the same
     * matrix as the Owner, but scoped to the lead's own team members (the
     * controller restricts the rows / benchmark to admin.TeamID mates). Dashboard
     * only; on the booking listing the Team Lead keeps the sales-agent card set.
     *
     * @param int|string $level
     * @param bool       $owner_as_agent true on the booking listing, false on the dashboard
     */
    function summary_cards_show_team_lead_matrix($level, $owner_as_agent)
    {
        return ((int) $level === 25) && !((bool) $owner_as_agent);
    }
}

if (!function_exists('summary_cards_show_owner_matrix')) {
    /**
     * True when the Owner sees the per-agent performance matrix (Dashboard only).
     *
     * @param int|string $level
     * @param bool       $owner_as_agent true on the booking listing, false on the Owner Dashboard
     */
    function summary_cards_show_owner_matrix($level, $owner_as_agent)
    {
        return ((int) $level === 10) && !((bool) $owner_as_agent);
    }
}
