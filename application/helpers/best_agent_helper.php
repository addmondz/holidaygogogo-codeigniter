<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pick the top-converting agent from rows returned by
 * Report_Model::Lead_Dashboard_By_Agent(). Filters out the unassigned bucket
 * and any agent below the minimum-sample threshold, then sorts by
 * conversion_rate DESC with agent_name ASC as tie-breaker. Returns the top
 * row or NULL when nothing qualifies.
 *
 * Used by the "Compare the Best" sub-line on the TC Conversion Rate card so
 * the comparison stays meaningful — an agent with one converted lead out of
 * one would otherwise sit at 100%.
 */
function best_conversion_rate_agent(array $rows, $min_total_leads = 3)
{
    $threshold = (int) $min_total_leads;
    $filtered = array();
    foreach ($rows as $r) {
        if (!isset($r['agent_id'], $r['total_leads'], $r['conversion_rate'])) {
            continue;
        }
        if ($r['agent_id'] === '__unassigned__') {
            continue;
        }
        if ((int) $r['total_leads'] < $threshold) {
            continue;
        }
        $filtered[] = $r;
    }
    if (empty($filtered)) {
        return null;
    }
    usort($filtered, function ($a, $b) {
        $ra = (float) $a['conversion_rate'];
        $rb = (float) $b['conversion_rate'];
        if ($ra !== $rb) {
            return ($rb <=> $ra);
        }
        return strcmp((string) $a['agent_name'], (string) $b['agent_name']);
    });
    return $filtered[0];
}
