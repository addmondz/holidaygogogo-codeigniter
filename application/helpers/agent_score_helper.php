<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Agent Score — pure composite scoring + ranking for the TC "Agent Score" card.
 *
 * The card ranks sales agents by a weighted blend of six metrics, each
 * benchmarked so the period's best performer scores 100 on that metric:
 *
 *     score = reply 15% + pickup 10% + conversion 10% + sales 45%
 *           + follow-up 10% + leads-served 10%
 *
 * Two of the metrics are "lower is better" (reply time, pickup speed) and four
 * are "higher is better" (conversion rate, sales, follow-up rate, leads served).
 * All six are normalised to a 0–100 scale against the best agent, then combined.
 * This file is pure (no DB, no CI) so it is unit-testable in isolation; the
 * Booking controller does the keyspace-joining (GHL user id <-> AdminID) and
 * supplies the raw per-agent rows.
 *
 * Each input row (keyed however the caller likes, values read by field):
 *   [
 *     'admin_id'      => int,
 *     'name'          => string,
 *     'reply_secs'    => float|null,  // avg reply time to inbound (lower better)
 *     'pickup_secs'   => float|null,  // avg first-reply pickup speed (lower better)
 *     'conv_rate'     => float|null,  // conversion % 0..100 (higher better)
 *     'sales'         => float|null,  // credited sales value (higher better)
 *     'followup_rate' => float|null,  // follow-up % 0..100 (higher better)
 *     'served_leads'  => float|null,  // qty of leads served/owned (higher better)
 *     'pickup_n'      => int,         // qualifying picked-up leads (min-sample)
 *     'leads_n'       => int,         // total leads (min-sample for reply/conversion)
 *     'owned_n'       => int,         // owned leads (min-sample for follow-up)
 *     'benchmark'     => bool,        // optional, default true; false = scored but
 *                                     //   excluded from setting the 100-anchors
 *     'hidden'        => bool,        // optional, default false; true = STILL sets
 *                                     //   the 100-anchors (respecting 'benchmark')
 *                                     //   but is omitted from the ranked output /
 *                                     //   by_admin / total / top — counts toward
 *                                     //   the bar without appearing on it (Owner /
 *                                     //   TC Lead: included in the calc, not shown)
 *     'excluded'      => bool,        // optional, default false; true = dropped
 *                                     //   ENTIRELY (no anchor, no rank, absent
 *                                     //   from the result) — owner's exclude list
 *   ]
 *
 * A null/zero metric means "no data" and normalises to 0 — the agent gets no
 * credit for a metric they never demonstrated, and the weights still sum to the
 * full 100% so cross-agent comparison stays honest.
 */

if (!function_exists('agent_score_weights')) {
    function agent_score_weights()
    {
        // Per the product owner. Must sum to 1.0.
        return array(
            'reply'    => 0.15,
            'pickup'   => 0.10,
            'conv'     => 0.10,
            'sales'    => 0.45,
            'followup' => 0.10,
            'served'   => 0.10,
        );
    }
}

if (!function_exists('agent_score_compute')) {
    /**
     * Normalise, weight, and rank a set of agents.
     *
     * @param array $agents  list of raw per-agent rows (see file header)
     * @param array $opts    optional: pickup_min (default 2), leads_min (default 3),
     *                        owned_min (default 3)
     * @return array {
     *   ranked:   list sorted by composite DESC, name ASC, each with
     *             admin_id,name,composite(float),rank(int),
     *             norm{reply,pickup,conv,sales,followup}
     *   by_admin: admin_id => ranked row
     *   total:    int (number of ranked agents)
     *   top:      ranked row or null
     * }
     */
    function agent_score_compute(array $agents, array $opts = array())
    {
        $pickup_min = isset($opts['pickup_min']) ? (int) $opts['pickup_min'] : 2;
        $leads_min  = isset($opts['leads_min'])  ? (int) $opts['leads_min']  : 3;
        $owned_min  = isset($opts['owned_min'])  ? (int) $opts['owned_min']  : 3;
        $w = agent_score_weights();

        // ---- Benchmark anchors (the "best performer" = 100 reference) ----
        // Lower-better anchors use the MINIMUM positive value among agents that
        // clear the min-sample guard, so a single lucky lead can't define 100.
        // Higher-better anchors use the MAXIMUM among min-sample agents.
        $best_reply    = null; // min positive reply_secs   (leads_n >= leads_min)
        $best_pickup   = null; // min positive pickup_secs  (pickup_n >= pickup_min)
        $best_conv     = null; // max conv_rate             (leads_n >= leads_min)
        $best_sales    = null; // max positive sales        (no min-sample, mirrors best-sales card)
        $best_followup = null; // max followup_rate         (owned_n >= owned_min)
        $best_served   = null; // max positive served_leads (no min-sample, a raw count)

        foreach ($agents as $a) {
            // A 'benchmark' => false agent is still scored and ranked below, but is
            // EXCLUDED from setting the 100-reference anchors. Lets the owner matrix
            // benchmark only against the Level-20 sales-agent pool while still
            // showing (and scoring) Level-50 rows. Default true -> every agent
            // contributes, so callers that omit the flag are unaffected.
            // An 'excluded' agent never anchors the benchmark and is skipped in
            // the ranking pass below — they leave the score comparison completely.
            if (isset($a['excluded']) && $a['excluded'] === true) { continue; }
            $isBench  = !isset($a['benchmark']) || $a['benchmark'] !== false;
            $reply    = isset($a['reply_secs'])    && $a['reply_secs']    !== null ? (float) $a['reply_secs']    : null;
            $pickup   = isset($a['pickup_secs'])   && $a['pickup_secs']   !== null ? (float) $a['pickup_secs']   : null;
            $conv     = isset($a['conv_rate'])     && $a['conv_rate']     !== null ? (float) $a['conv_rate']     : null;
            $sales    = isset($a['sales'])         && $a['sales']         !== null ? (float) $a['sales']         : null;
            $followup = isset($a['followup_rate']) && $a['followup_rate'] !== null ? (float) $a['followup_rate'] : null;
            $served   = isset($a['served_leads'])  && $a['served_leads']  !== null ? (float) $a['served_leads']  : null;
            $pn       = isset($a['pickup_n']) ? (int) $a['pickup_n'] : 0;
            $ln       = isset($a['leads_n'])  ? (int) $a['leads_n']  : 0;
            $on       = isset($a['owned_n'])  ? (int) $a['owned_n']  : 0;

            if ($isBench && $reply !== null && $reply > 0 && $ln >= $leads_min) {
                $best_reply = ($best_reply === null) ? $reply : min($best_reply, $reply);
            }
            if ($isBench && $pickup !== null && $pickup > 0 && $pn >= $pickup_min) {
                $best_pickup = ($best_pickup === null) ? $pickup : min($best_pickup, $pickup);
            }
            if ($isBench && $conv !== null && $ln >= $leads_min) {
                $best_conv = ($best_conv === null) ? $conv : max($best_conv, $conv);
            }
            if ($isBench && $sales !== null && $sales > 0) {
                $best_sales = ($best_sales === null) ? $sales : max($best_sales, $sales);
            }
            if ($isBench && $followup !== null && $on >= $owned_min) {
                $best_followup = ($best_followup === null) ? $followup : max($best_followup, $followup);
            }
            if ($isBench && $served !== null && $served > 0) {
                $best_served = ($best_served === null) ? $served : max($best_served, $served);
            }
        }

        // ---- Normalise + weight each agent ----
        // A 'hidden' agent still anchored the benchmark above, but is omitted here
        // so it never appears in the ranked list / by_admin / total / top — its
        // metrics count toward the 100-bar without showing on the leaderboard or
        // matrix (Owner / TC Lead: included in the calculation, not displayed).
        $ranked = array();
        foreach ($agents as $a) {
            if (isset($a['excluded']) && $a['excluded'] === true) { continue; }
            if (isset($a['hidden']) && $a['hidden'] === true) { continue; }
            $reply    = isset($a['reply_secs'])    && $a['reply_secs']    !== null ? (float) $a['reply_secs']    : null;
            $pickup   = isset($a['pickup_secs'])   && $a['pickup_secs']   !== null ? (float) $a['pickup_secs']   : null;
            $conv     = isset($a['conv_rate'])     && $a['conv_rate']     !== null ? (float) $a['conv_rate']     : null;
            $sales    = isset($a['sales'])         && $a['sales']         !== null ? (float) $a['sales']         : null;
            $followup = isset($a['followup_rate']) && $a['followup_rate'] !== null ? (float) $a['followup_rate'] : null;
            $served   = isset($a['served_leads'])  && $a['served_leads']  !== null ? (float) $a['served_leads']  : null;

            $n_reply    = agent_score_norm_lower($reply,  $best_reply);
            $n_pickup   = agent_score_norm_lower($pickup, $best_pickup);
            $n_conv     = agent_score_norm_higher($conv,  $best_conv);
            $n_sales    = agent_score_norm_higher($sales, $best_sales);
            $n_followup = agent_score_norm_higher($followup, $best_followup);
            $n_served   = agent_score_norm_higher($served,   $best_served);

            $composite = $w['reply']    * $n_reply
                       + $w['pickup']   * $n_pickup
                       + $w['conv']     * $n_conv
                       + $w['sales']    * $n_sales
                       + $w['followup'] * $n_followup
                       + $w['served']   * $n_served;

            $ranked[] = array(
                'admin_id'  => isset($a['admin_id']) ? (int) $a['admin_id'] : 0,
                'name'      => isset($a['name']) ? (string) $a['name'] : '',
                'composite' => round($composite, 1),
                'norm'      => array(
                    'reply'    => round($n_reply, 1),
                    'pickup'   => round($n_pickup, 1),
                    'conv'     => round($n_conv, 1),
                    'sales'    => round($n_sales, 1),
                    'followup' => round($n_followup, 1),
                    'served'   => round($n_served, 1),
                ),
            );
        }

        // ---- Rank: composite DESC, name ASC (matches best_*_agent tie-break) ----
        usort($ranked, function ($a, $b) {
            if ($a['composite'] !== $b['composite']) {
                return ($b['composite'] <=> $a['composite']);
            }
            return strcmp($a['name'], $b['name']);
        });

        $by_admin = array();
        foreach ($ranked as $i => &$row) {
            $row['rank'] = $i + 1;
            $by_admin[$row['admin_id']] = $row;
        }
        unset($row);

        return array(
            'ranked'   => $ranked,
            'by_admin' => $by_admin,
            'total'    => count($ranked),
            'top'      => count($ranked) ? $ranked[0] : null,
            // The benchmark anchors (the "best performer = 100" reference on each
            // metric). Exposed so callers can show HOW a 0-100 was derived — e.g.
            // the owner's Score-breakdown popover renders "yours vs best". null on a
            // metric no min-sample agent qualified for. reply/pickup are the fastest
            // (min) time; the rest are the highest (max) value.
            'anchors'  => array(
                'reply'    => $best_reply,
                'pickup'   => $best_pickup,
                'conv'     => $best_conv,
                'sales'    => $best_sales,
                'followup' => $best_followup,
                'served'   => $best_served,
            ),
        );
    }
}

if (!function_exists('agent_score_norm_lower')) {
    /**
     * Lower-is-better normalisation: best (smallest) value -> 100, slower ->
     * proportionally less, no/zero data -> 0. Capped at [0,100] so a sub-min-
     * sample agent faster than the anchor can't exceed the benchmark.
     */
    function agent_score_norm_lower($value, $best)
    {
        if ($value === null || $value <= 0 || $best === null || $best <= 0) {
            return 0.0;
        }
        $n = ($best / $value) * 100.0;
        return $n > 100.0 ? 100.0 : $n;
    }
}

if (!function_exists('agent_score_norm_higher')) {
    /**
     * Higher-is-better normalisation: best (largest) value -> 100, lower ->
     * proportionally less, no/zero data -> 0. Capped at [0,100].
     */
    function agent_score_norm_higher($value, $best)
    {
        if ($value === null || $value <= 0 || $best === null || $best <= 0) {
            return 0.0;
        }
        $n = ($value / $best) * 100.0;
        return $n > 100.0 ? 100.0 : $n;
    }
}
