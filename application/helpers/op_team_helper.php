<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('op_team_admin_ids')) {
    /**
     * Resolve the set of admin IDs that make up the OP team the given user
     * belongs to, used to scope the OP / OP TEAM LEAD summary cards so everyone
     * under the same OP TEAM LEAD (level 45) sees each other's BCs.
     *
     * Membership is the inverse pointer admin.OpTeamLeadID (an OP member, level
     * 40, points at their level-45 lead — see 20260529_Add_Op_Team_Lead_Role.sql):
     *   - OP TEAM LEAD (45): lead is the user; team = the lead + every member
     *     whose OpTeamLeadID is the lead.
     *   - OP (40): lead is the user's OpTeamLeadID; team = that lead + every
     *     member (siblings + self) pointing at it. A member with no lead
     *     (OpTeamLeadID empty) is a team of one — just themselves.
     *
     * Always includes the user's own id, so the result is never empty (no
     * `IN ()` SQL hazard). Returns a sorted, de-duplicated list of ints.
     *
     * @param int   $admin_id logged-in admin id
     * @param int   $level    logged-in level (40 or 45)
     * @param array $admins   active OP-side admins; each row needs ->AdminID,
     *                        ->Level, ->OpTeamLeadID (CI ->result() objects)
     * @return int[]
     */
    function op_team_admin_ids($admin_id, $level, $admins)
    {
        $admin_id = (int) $admin_id;
        $level    = (int) $level;

        $norm_tl = function ($tl) {
            return ($tl === null || $tl === '' || (int) $tl <= 0) ? null : (int) $tl;
        };

        $lead_id = null;
        if ($level === 45) {
            $lead_id = $admin_id;
        } elseif ($level === 40) {
            foreach ($admins as $a) {
                if ((int) $a->AdminID === $admin_id) {
                    $lead_id = $norm_tl($a->OpTeamLeadID);
                    break;
                }
            }
        }

        $ids = array($admin_id); // self is always in scope
        if ($lead_id !== null) {
            $ids[] = $lead_id;
            foreach ($admins as $a) {
                if ($norm_tl($a->OpTeamLeadID) === $lead_id) {
                    $ids[] = (int) $a->AdminID;
                }
            }
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);
        return $ids;
    }
}
