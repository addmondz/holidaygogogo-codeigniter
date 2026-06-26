<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Agent Score — pure composite scoring + ranking for the TC "Agent Score" card.
 *
 * The card ranks sales agents by a weighted blend of four metrics, each
 * benchmarked so the period's best performer scores 100 on that metric:
 *
 *     score = reply 30% + pickup 20% + conversion 20% + sales 30%
 *
 * Two of the metrics are "lower is better" (reply time, pickup speed) and two
 * are "higher is better" (conversion rate, sales). All four are normalised to a
 * 0–100 scale against the best agent, then combined. This file is pure (no DB,
 * no CI) so it is unit-testable in isolation; the Booking controller does the
 * keyspace-joining (GHL user id <-> AdminID) and supplies the raw per-agent rows.
 *
 * Each input row (keyed however the caller likes, values read by field):
 *   [
 *     'admin_id'    => int,
 *     'name'        => string,
 *     'reply_secs'  => float|null,  // avg reply time to inbound (lower better)
 *     'pickup_secs' => float|null,  // avg first-reply pickup speed (lower better)
 *     'conv_rate'   => float|null,  // conversion % 0..100 (higher better)
 *     'sales'       => float|null,  // credited sales value (higher better)
 *     'pickup_n'    => int,         // qualifying picked-up leads (min-sample)
 *     'leads_n'     => int,         // total leads (min-sample for reply/conversion)
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
            'reply'  => 0.30,
            'pickup' => 0.20,
            'conv'   => 0.20,
            'sales'  => 0.30,
        );
    }
}

if (!function_exists('agent_score_compute')) {
    /**
     * Normalise, weight, and rank a set of agents.
     *
     * @param array $agents  list of raw per-agent rows (see file header)
     * @param array $opts    optional: pickup_min (default 2), leads_min (default 3)
     * @return array {
     *   ranked:   list sorted by composite DESC, name ASC, each with
     *             admin_id,name,composite(float),rank(int),norm{reply,pickup,conv,sales}
     *   by_admin: admin_id => ranked row
     *   total:    int (number of ranked agents)
     *   top:      ranked row or null
     * }
     */
    function agent_score_compute(array $agents, array $opts = array())
    {
        $pickup_min = isset($opts['pickup_min']) ? (int) $opts['pickup_min'] : 2;
        $leads_min  = isset($opts['leads_min'])  ? (int) $opts['leads_min']  : 3;
        $w = agent_score_weights();

        // ---- Benchmark anchors (the "best performer" = 100 reference) ----
        // Lower-better anchors use the MINIMUM positive value among agents that
        // clear the min-sample guard, so a single lucky lead can't define 100.
        // Higher-better anchors use the MAXIMUM among min-sample agents.
        $best_reply  = null; // min positive reply_secs  (leads_n >= leads_min)
        $best_pickup = null; // min positive pickup_secs (pickup_n >= pickup_min)
        $best_conv   = null; // max conv_rate            (leads_n >= leads_min)
        $best_sales  = null; // max positive sales       (no min-sample, mirrors best-sales card)

        foreach ($agents as $a) {
            $reply  = isset($a['reply_secs'])  && $a['reply_secs']  !== null ? (float) $a['reply_secs']  : null;
            $pickup = isset($a['pickup_secs']) && $a['pickup_secs'] !== null ? (float) $a['pickup_secs'] : null;
            $conv   = isset($a['conv_rate'])   && $a['conv_rate']   !== null ? (float) $a['conv_rate']   : null;
            $sales  = isset($a['sales'])       && $a['sales']       !== null ? (float) $a['sales']       : null;
            $pn     = isset($a['pickup_n']) ? (int) $a['pickup_n'] : 0;
            $ln     = isset($a['leads_n'])  ? (int) $a['leads_n']  : 0;

            if ($reply !== null && $reply > 0 && $ln >= $leads_min) {
                $best_reply = ($best_reply === null) ? $reply : min($best_reply, $reply);
            }
            if ($pickup !== null && $pickup > 0 && $pn >= $pickup_min) {
                $best_pickup = ($best_pickup === null) ? $pickup : min($best_pickup, $pickup);
            }
            if ($conv !== null && $ln >= $leads_min) {
                $best_conv = ($best_conv === null) ? $conv : max($best_conv, $conv);
            }
            if ($sales !== null && $sales > 0) {
                $best_sales = ($best_sales === null) ? $sales : max($best_sales, $sales);
            }
        }

        // ---- Normalise + weight each agent ----
        $ranked = array();
        foreach ($agents as $a) {
            $reply  = isset($a['reply_secs'])  && $a['reply_secs']  !== null ? (float) $a['reply_secs']  : null;
            $pickup = isset($a['pickup_secs']) && $a['pickup_secs'] !== null ? (float) $a['pickup_secs'] : null;
            $conv   = isset($a['conv_rate'])   && $a['conv_rate']   !== null ? (float) $a['conv_rate']   : null;
            $sales  = isset($a['sales'])       && $a['sales']       !== null ? (float) $a['sales']       : null;

            $n_reply  = agent_score_norm_lower($reply,  $best_reply);
            $n_pickup = agent_score_norm_lower($pickup, $best_pickup);
            $n_conv   = agent_score_norm_higher($conv,  $best_conv);
            $n_sales  = agent_score_norm_higher($sales, $best_sales);

            $composite = $w['reply']  * $n_reply
                       + $w['pickup'] * $n_pickup
                       + $w['conv']   * $n_conv
                       + $w['sales']  * $n_sales;

            $ranked[] = array(
                'admin_id'  => isset($a['admin_id']) ? (int) $a['admin_id'] : 0,
                'name'      => isset($a['name']) ? (string) $a['name'] : '',
                'composite' => round($composite, 1),
                'norm'      => array(
                    'reply'  => round($n_reply, 1),
                    'pickup' => round($n_pickup, 1),
                    'conv'   => round($n_conv, 1),
                    'sales'  => round($n_sales, 1),
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
