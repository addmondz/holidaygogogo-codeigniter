<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Booking Team Snapshot Helper
 *
 * Point-in-time team attribution for credited sales. A booking's TeamID is a
 * FROZEN snapshot of the team its credited agent (SalesAgent / TC1) belonged to
 * at the time of the sale — so an agent who later switches team or leaves the
 * company does not retroactively move this year's sale out of the team that
 * earned it. Without this, the year-over-year "Total Sales by Team" benchmark
 * would silently rewrite history whenever staff are reorganised.
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
