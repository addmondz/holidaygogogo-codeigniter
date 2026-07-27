<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('team_member_admin_ids')) {
    /**
     * Resolve the set of admin IDs that make up the Team the given user belongs
     * to, used to scope the booking / payment listings so a TEAM LEAD (25) or
     * OP TEAM LEAD (45) sees every team member's work while line staff (20 / 40)
     * see only their own.
     *
     * Membership is the shared admin.TeamID column (see the "team" settings
     * entity / 2026XXXX_Add_TeamID_To_Admin.sql): a lead and every member are
     * assigned the SAME Team, so the team = all active admins whose TeamID equals
     * the viewer's TeamID. This is intentionally separate from the checklist
     * pointer columns admin.TeamLeadID (→ level 25) and admin.OpTeamLeadID
     * (→ level 45), which continue to drive checklist permissions only.
     *
     * A viewer with no Team (TeamID NULL/empty) — or one not present in $admins —
     * is a team of one: just themselves. The viewer's own id is always included,
     * so the result is never empty (no `IN ()` SQL hazard). Returns a sorted,
     * de-duplicated list of ints.
     *
     * @param int   $admin_id logged-in admin id
     * @param array $admins   admin rows; each needs ->AdminID, ->TeamID and
     *                        (optionally) ->Status (CI ->result() objects). Rows
     *                        with a Status other than 'Y' are treated as inactive
     *                        and excluded from the team.
     * @return int[]
     */
    function team_member_admin_ids($admin_id, $admins)
    {
        $admin_id = (int) $admin_id;

        $norm_team = function ($team) {
            return ($team === null || $team === '' || (int) $team <= 0) ? null : (int) $team;
        };

        // Find the viewer's Team.
        $team_id = null;
        foreach ($admins as $a) {
            if ((int) $a->AdminID === $admin_id) {
                $team_id = $norm_team($a->TeamID);
                break;
            }
        }

        $ids = array($admin_id); // self is always in scope
        if ($team_id !== null) {
            foreach ($admins as $a) {
                $active = !isset($a->Status) || $a->Status === 'Y';
                if ($active && $norm_team($a->TeamID) === $team_id) {
                    $ids[] = (int) $a->AdminID;
                }
            }
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);
        return $ids;
    }
}
