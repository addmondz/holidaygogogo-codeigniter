<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Booking Team Snapshot Helper
 *
 * Point-in-time team attribution for credited sales. A booking's TeamID is a
 * FROZEN snapshot of the team its credited agent belonged to at the time of the
 * sale — so an agent who later switches team or leaves the company does not
 * retroactively move this year's sale out of the team that earned it. Without
 * this, the year-over-year "Total Sales by Team" benchmark would silently
 * rewrite history whenever staff are reorganised.
 *
 * The credited agent follows the same TC1/TC2 cutoff as the rest of the app:
 * TC1 (booking.SalesAgent) for bookings before LEAD_CONVERSION_TC2_CUTOFF_DATE,
 * TC2 (booking.SalesAgent2) on/after it (see lead_conversion_credit_helper.php).
 * booking_team_snapshot() is the generic freeze primitive (works on any credited
 * agent id); booking_team_snapshot_by_credit() layers the cutoff on top.
 */

if (!function_exists('booking_team_snapshot')) {
    /**
     * Decide the TeamID to persist on a booking row when it is written.
     *
     * Freeze rule: the snapshot is (re)taken ONLY when the credited agent is
     * first assigned or actually changes. An update that leaves SalesAgent
     * unchanged must NOT re-snapshot — otherwise an unrelated edit made after
     * the agent moved team would corrupt the historical attribution.
     *
     * @param int|string      $new_sales_agent  SalesAgent on the incoming row (0 = unassigned)
     * @param int|string|null $prev_sales_agent SalesAgent currently stored; null on create
     * @param int|string|null $agent_team_id    the (new) agent's CURRENT admin.TeamID; null if none
     * @return array  array('write' => bool, 'team_id' => int|null)
     *                write=false -> leave booking.TeamID untouched (frozen)
     */
    function booking_team_snapshot($new_sales_agent, $prev_sales_agent, $agent_team_id)
    {
        $new = (int) $new_sales_agent;

        // Update with an unchanged credited agent -> freeze the existing snapshot.
        if ($prev_sales_agent !== null && (int) $prev_sales_agent === $new) {
            return array('write' => false, 'team_id' => null);
        }

        // Create, or the credited agent changed -> take a fresh snapshot of the
        // agent's current team. No agent (0) or a teamless agent -> unassigned.
        $team = ($new > 0 && $agent_team_id !== null && $agent_team_id !== '')
            ? (int) $agent_team_id
            : null;

        return array('write' => true, 'team_id' => $team);
    }
}

if (!function_exists('booking_team_snapshot_by_credit')) {
    /**
     * Write-time team-snapshot decision that resolves the credited agent under
     * the TC1/TC2 Jun-1 cutoff first, then applies the freeze rule.
     *
     * The team is frozen to the CREDITED agent: TC1 (SalesAgent) for pre-cutoff
     * bookings, TC2 (SalesAgent2) on/after. The snapshot is (re)taken only when
     * that credited agent is first assigned or actually changes — so on a
     * post-cutoff booking, editing TC1 (or any unrelated field) leaves the team
     * frozen because TC2 is what carries the credit.
     *
     * @param string|null $insert_date    effective InsertDate of the row (write or stored)
     * @param int|string  $new_sa         effective SalesAgent (TC1); 0 = none
     * @param int|string  $new_sa2        effective SalesAgent2 (TC2); 0 = none
     * @param array|null  $prev           stored row before the write, or null on create:
     *                                    array('InsertDate','SalesAgent','SalesAgent2')
     * @param int|null    $credit_team_id the NEWLY credited agent's CURRENT admin.TeamID
     * @return array  array('write' => bool, 'team_id' => int|null, 'credited' => int|null)
     */
    function booking_team_snapshot_by_credit($insert_date, $new_sa, $new_sa2, $prev, $credit_team_id)
    {
        $new_credit = lead_conversion_credited_admin_id($insert_date, $new_sa, $new_sa2);

        $prev_credit = ($prev === null)
            ? null
            : lead_conversion_credited_admin_id(
                isset($prev['InsertDate'])  ? $prev['InsertDate']  : null,
                isset($prev['SalesAgent'])  ? $prev['SalesAgent']  : null,
                isset($prev['SalesAgent2']) ? $prev['SalesAgent2'] : null
            );

        $decision = booking_team_snapshot($new_credit, $prev_credit, $credit_team_id);
        $decision['credited'] = $new_credit;
        return $decision;
    }
}
