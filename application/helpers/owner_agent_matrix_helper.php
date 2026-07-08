<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Owner Agent Matrix — pure fold behind the OWNER dashboard's per-agent matrix.
 *
 * The Booking controller (Booking::owner_agent_matrix) fetches seven per-agent
 * sources from the DB, then hands them here. This file folds GHL-user-keyed
 * metrics (reply, pickup, leads/conversion, follow-up, outbound) and admin-keyed
 * metrics (sales, cancellation) onto ONE row per credited AdminID, derives each
 * of the 11 metric values, grafts the composite Agent Score (agent_score_helper)
 * and sorts the result. Kept pure (no DB, no CI) so it is unit-testable in
 * isolation — see tests/helpers/OwnerAgentMatrixMergeTest.php.
 *
 * Reply and pickup are weighted by their sample size so a TC owning several GHL
 * inboxes aggregates fairly. Every rate carries an explicit denominator>0 guard
 * so a zero-leads / zero-BC agent never divides by zero.
 */

if (!function_exists('owner_agent_matrix_build')) {
    /**
     * @param array $sources keyed:
     *   leads_ungated  rows { agent_id, total_leads, converted_leads }
     *   leads_gated    rows { agent_id, converted_leads }
     *   reply          rows { agent_id, avg_response_time_seconds, total_leads }
     *   pickup         rows { agent_id, avg_seconds, n }
     *   followup       rows { owner_user_id, owned_leads, follow_up_leads, owner_name }
     *   responded      rows { admin_id, responded_leads }              (admin-keyed)
     *   outbound       rows { agent_id, outbound_count }
     *   sales          rows { admin_id, agent_name, total_sales }   (admin-keyed)
     *   cancellation   rows { admin_id, total, cancelled }          (admin-keyed)
     * @param array $map           GHL uid (string) => AdminID (int)
     * @param array $name_by_admin AdminID (int) => display name
     * @param array|null $benchmark_admins  AdminID => true for agents that may set
     *        the Agent Score 100-anchors (the Level-20 sales-agent pool). Rows not
     *        in this set are still scored/shown, just excluded from the benchmark.
     *        null => every shown agent contributes (no narrowing).
     * @param bool  $compute_score  When false, the Agent Score is NOT computed and
     *        every row's agent_score is null (rows then sort by name). Set false for
     *        periods where the Score column isn't reported (Day / Week) so no
     *        scoring work happens for a column that would only render "-".
     * @param array|null $excluded_admins  AdminID => true for agents the owner has
     *        excluded from the Agent Score. They never anchor the benchmark AND are
     *        dropped from the matrix rows entirely — matching their absence from the
     *        TC leaderboard. null => no agent is excluded.
     * @param array|null $hidden_admins  AdminID => true for agents included in the
     *        Agent Score CALCULATION but not shown — they set the 100-anchors
     *        (respecting $benchmark_admins) yet are omitted from the returned matrix
     *        rows entirely (Owner / TC Lead). null => no agent is hidden.
     * @return array list of matrix rows (see file header / plan for shape)
     */
    function owner_agent_matrix_build(array $sources, array $map, array $name_by_admin, $benchmark_admins = null, $compute_score = true, $excluded_admins = null, $hidden_admins = null)
    {
        $src = function ($key) use ($sources) {
            return isset($sources[$key]) && is_array($sources[$key]) ? $sources[$key] : array();
        };

        // When the caller supplies a 'responded' source, the displayed Served
        // column mirrors the Lead Reply Activity "Lead Responded" metric (distinct
        // leads replied-to in the period) instead of owned-leads. Display-only:
        // Follow-up % and the Agent Score keep using owned-leads (fu_owned).
        // Absent (e.g. the unit test / a legacy caller) => Served stays owned-leads.
        $has_responded_src = array_key_exists('responded', $sources) && is_array($sources['responded']);

        $u = array();
        $ensure = function (&$u, $aid) use ($name_by_admin) {
            if (!isset($u[$aid])) {
                $u[$aid] = array(
                    'name'              => isset($name_by_admin[$aid]) ? $name_by_admin[$aid] : '',
                    'reply_sum'         => 0.0, 'reply_n' => 0,
                    'pickup_sum'        => 0.0, 'pickup_n' => 0,
                    'leads'             => 0,
                    'converted_ungated' => 0,
                    'converted_gated'   => 0,
                    'sales'             => 0.0,
                    'fu_owned'          => 0, 'fu_followed' => 0,
                    'responded'         => 0,
                    'outbound'          => 0,
                    'cancel_total'      => 0, 'cancel_cancelled' => 0,
                    'has_leads'         => false, 'has_sales' => false, 'has_fu' => false,
                    'has_outbound'      => false, 'has_cancel' => false,
                );
            }
        };

        // ---- GHL-keyed: ungated leads (metrics 3 new leads, 6 conv) ----
        foreach ($src('leads_ungated') as $a) {
            $uid = (string) $a['agent_id'];
            if ($uid === '__unassigned__' || !isset($map[$uid])) { continue; }
            $aid = $map[$uid];
            $ensure($u, $aid);
            $u[$aid]['leads']             += (int) $a['total_leads'];
            $u[$aid]['converted_ungated'] += (int) $a['converted_leads'];
            $u[$aid]['has_leads']          = true;
        }

        // ---- GHL-keyed: reply time (metric 1) ----
        // Uses the Message-Log reply-pair metric (Ghl_Messages_Avg_Reply_By_Agent),
        // the SAME source as the TC "Avg Reply Time to Inbound" card and its "Best:"
        // leaderboard — so the owner matrix agrees with each agent's own card.
        // Weighted by the reply sample (total_leads = conversations replied to) so a
        // TC owning several GHL inboxes aggregates fairly.
        foreach ($src('reply') as $r) {
            $uid = (string) $r['agent_id'];
            if (!isset($map[$uid])) { continue; }
            $aid = $map[$uid];
            $ensure($u, $aid);
            $n = (int) $r['total_leads'];
            if ($r['avg_response_time_seconds'] !== null && $n > 0) {
                $u[$aid]['reply_sum'] += (float) $r['avg_response_time_seconds'] * $n;
                $u[$aid]['reply_n']   += $n;
            }
        }

        // ---- GHL-keyed: gated leads (metric 5 — converted numerator only) ----
        foreach ($src('leads_gated') as $a) {
            $uid = (string) $a['agent_id'];
            if ($uid === '__unassigned__' || !isset($map[$uid])) { continue; }
            $aid = $map[$uid];
            $ensure($u, $aid);
            $u[$aid]['converted_gated'] += (int) $a['converted_leads'];
        }

        // ---- GHL-keyed: pickup speed (metric 2) ----
        foreach ($src('pickup') as $p) {
            $uid = (string) $p['agent_id'];
            if (!isset($map[$uid])) { continue; }
            $aid = $map[$uid];
            $ensure($u, $aid);
            $n = (int) $p['n'];
            if ($n > 0) {
                $u[$aid]['pickup_sum'] += (float) $p['avg_seconds'] * $n;
                $u[$aid]['pickup_n']   += $n;
            }
        }

        // ---- GHL-keyed: ownership (metric 4 served, metric 9 follow-up) ----
        foreach ($src('followup') as $fr) {
            $uid = (string) $fr['owner_user_id'];
            if (!isset($map[$uid])) { continue; }
            $aid = $map[$uid];
            $ensure($u, $aid);
            $u[$aid]['fu_owned']    += (int) $fr['owned_leads'];
            $u[$aid]['fu_followed'] += (int) $fr['follow_up_leads'];
            $u[$aid]['has_fu']       = true;
            if ($u[$aid]['name'] === '' && !empty($fr['owner_name'])) {
                $u[$aid]['name'] = $fr['owner_name'];
            }
        }

        // ---- Admin-keyed: responded (Served display override) ----
        // Distinct leads replied-to in the period, already de-duplicated across an
        // agent's GHL inboxes by the controller, so this is admin-keyed.
        foreach ($src('responded') as $r) {
            $aid = (int) $r['admin_id'];
            if ($aid <= 0) { continue; }
            $ensure($u, $aid);
            $u[$aid]['responded'] += (int) $r['responded_leads'];
        }

        // ---- GHL-keyed: outbound (metric 7) ----
        foreach ($src('outbound') as $o) {
            $uid = (string) $o['agent_id'];
            if (!isset($map[$uid])) { continue; }
            $aid = $map[$uid];
            $ensure($u, $aid);
            $u[$aid]['outbound']    += (int) $o['outbound_count'];
            $u[$aid]['has_outbound'] = true;
        }

        // ---- Admin-keyed: sales (metric 8) ----
        foreach ($src('sales') as $s) {
            $aid = (int) $s['admin_id'];
            if ($aid <= 0) { continue; }
            $ensure($u, $aid);
            $u[$aid]['sales']    += (float) $s['total_sales'];
            $u[$aid]['has_sales'] = true;
            if ($u[$aid]['name'] === '' && !empty($s['agent_name'])) {
                $u[$aid]['name'] = $s['agent_name'];
            }
        }

        // ---- Admin-keyed: cancellation (metric 10) ----
        foreach ($src('cancellation') as $c) {
            $aid = (int) $c['admin_id'];
            if ($aid <= 0) { continue; }
            $ensure($u, $aid);
            $u[$aid]['cancel_total']     += (int) $c['total'];
            $u[$aid]['cancel_cancelled'] += (int) $c['cancelled'];
            $u[$aid]['has_cancel']        = true;
        }

        // ---- Seed the full included roster ----
        // Every AdminID named in $name_by_admin (the controller's complete scored
        // pool: Level 20/50 sales agents + Owner + TC Lead) gets a row even with no
        // activity in the window, so the owner sees the SAME agents every period.
        // Zero-activity agents fold to null metrics below and render as "—".
        // (Hidden / owner-excluded agents are seeded too but dropped from the rows.)
        foreach ($name_by_admin as $aid => $nm) {
            $ensure($u, (int) $aid);
        }

        // Eligible = any activity in the window across any of the seven sources.
        // Still gates the Agent Score: a seeded zero-activity agent is shown as a
        // row but not scored (their Score column dashes).
        $eligible = function ($row) {
            return $row['has_leads'] || $row['has_sales'] || $row['has_fu']
                || $row['has_outbound'] || $row['has_cancel'];
        };

        // ---- Metric 11: composite Agent Score over the eligible population ----
        // Skipped entirely when $compute_score is false (Day / Week), so no scoring
        // work is done for a column that would only render "-".
        $score = array('by_admin' => array());
        $score_inputs = array();
        if ($compute_score) {
        foreach ($u as $aid => $row) {
            if (!$eligible($row)) { continue; }
            $score_inputs[] = array(
                'admin_id'      => $aid,
                'name'          => $row['name'] !== '' ? $row['name'] : '#' . $aid,
                'reply_secs'    => $row['reply_n']  > 0 ? $row['reply_sum']  / $row['reply_n']  : null,
                'pickup_secs'   => $row['pickup_n'] > 0 ? $row['pickup_sum'] / $row['pickup_n'] : null,
                'conv_rate'     => $row['leads'] > 0 ? ($row['converted_ungated'] / $row['leads']) * 100 : null,
                'sales'         => $row['has_sales'] ? $row['sales'] : null,
                'followup_rate' => $row['fu_owned'] > 0 ? ($row['fu_followed'] / $row['fu_owned']) * 100 : null,
                'served_leads'  => $row['fu_owned'] > 0 ? $row['fu_owned'] : null,
                'pickup_n'      => $row['pickup_n'],
                'leads_n'       => $row['leads'],
                'owned_n'       => $row['fu_owned'],
                // Only the Level-20 sales-agent pool sets the 100-anchors, so an
                // L20 agent's matrix Score equals their own Agent Score card.
                'benchmark'     => ($benchmark_admins === null) || isset($benchmark_admins[$aid]),
                // Owner-excluded agents leave the score entirely (null score, no
                // anchor) — same exclusion the TC leaderboard applies.
                'excluded'      => ($excluded_admins !== null) && isset($excluded_admins[$aid]),
                // Owner / TC Lead anchor the benchmark but are dropped from the
                // matrix rows below — included in the calc, not displayed.
                'hidden'        => ($hidden_admins !== null) && isset($hidden_admins[$aid]),
            );
        }
        $score = agent_score_compute($score_inputs);
        }

        // ---- Build the matrix rows ----
        // NO eligibility gate here: every included agent shows a row (even with no
        // activity) so the roster is stable across periods. Hidden agents (Owner)
        // anchored the score above but never show; owner-excluded agents are dropped
        // from the rows entirely too — matching their removal from the TC leaderboard.
        // Count / rate metrics fold to null (=> the view renders "—") when their own
        // source contributed nothing for this agent; a genuine measured 0 (source
        // present, value 0) still shows as 0.
        $matrix = array();
        foreach ($u as $aid => $row) {
            if (($hidden_admins   !== null) && isset($hidden_admins[$aid]))   { continue; }
            if (($excluded_admins !== null) && isset($excluded_admins[$aid])) { continue; }
            $served_val = $has_responded_src ? $row['responded'] : $row['fu_owned'];
            $matrix[] = array(
                'admin_id'          => $aid,
                'agent_name'        => $row['name'] !== '' ? $row['name'] : '#' . $aid,
                'reply_secs'        => $row['reply_n']  > 0 ? (int) round($row['reply_sum']  / $row['reply_n'])  : null,
                'pickup_secs'       => $row['pickup_n'] > 0 ? (int) round($row['pickup_sum'] / $row['pickup_n']) : null,
                'pickup_n'          => $row['pickup_n'],
                'new_leads'         => $row['has_leads'] ? $row['leads'] : null,
                'served_leads'      => $served_val > 0 ? (int) $served_val : null,
                'conv_rate_gated'   => $row['has_leads'] ? ($row['leads'] > 0 ? round($row['converted_gated']   / $row['leads'] * 100, 1) : 0.0) : null,
                'converted_gated'   => $row['converted_gated'],
                'conv_rate_ungated' => $row['has_leads'] ? ($row['leads'] > 0 ? round($row['converted_ungated'] / $row['leads'] * 100, 1) : 0.0) : null,
                'converted_ungated' => $row['converted_ungated'],
                'outbound_count'    => $row['has_outbound'] ? $row['outbound'] : null,
                'sales_total'       => $row['has_sales'] ? $row['sales'] : null,
                'followup_rate'     => $row['has_fu'] ? ($row['fu_owned'] > 0 ? round($row['fu_followed'] / $row['fu_owned'] * 100, 1) : 0.0) : null,
                'followup_leads'    => $row['fu_followed'],
                'cancel_rate'       => $row['has_cancel'] ? ($row['cancel_total'] > 0 ? round($row['cancel_cancelled'] / $row['cancel_total'] * 100, 1) : 0.0) : null,
                'cancel_total'      => $row['cancel_total'],
                'cancel_cancelled'  => $row['cancel_cancelled'],
                'agent_score'       => isset($score['by_admin'][$aid]) ? $score['by_admin'][$aid]['composite'] : null,
            );
        }

        // Sort by Agent Score DESC (null last), then name ASC — matches the
        // leaderboard tie-break used by agent_score_compute().
        usort($matrix, function ($a, $b) {
            $as = $a['agent_score'] === null ? -1.0 : (float) $a['agent_score'];
            $bs = $b['agent_score'] === null ? -1.0 : (float) $b['agent_score'];
            if ($as !== $bs) { return ($bs <=> $as); }
            return strcmp($a['agent_name'], $b['agent_name']);
        });

        return $matrix;
    }
}
