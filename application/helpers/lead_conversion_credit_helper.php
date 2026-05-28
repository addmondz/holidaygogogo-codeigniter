<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Fallback when the helper is loaded outside the CodeIgniter bootstrap (e.g.
// from the standalone test runner). The canonical source is constants.php.
if (!defined('LEAD_CONVERSION_TC2_CUTOFF_DATE')) {
    define('LEAD_CONVERSION_TC2_CUTOFF_DATE', '2026-06-01');
}

/**
 * Returns the AdminID credited for converting a lead on a given booking, or NULL.
 *
 * Pre-cutoff bookings credit TC1 (booking.SalesAgent); on/after credit TC2
 * (booking.SalesAgent2). NULL when the relevant slot is empty or InsertDate is
 * missing, which means the conversion does not count under this rule.
 */
function lead_conversion_credited_admin_id($insert_date, $sales_agent, $sales_agent2, $cutoff_date = null)
{
    if ($insert_date === null || $insert_date === '') {
        return null;
    }

    $cutoff = ($cutoff_date === null || $cutoff_date === '')
        ? LEAD_CONVERSION_TC2_CUTOFF_DATE
        : $cutoff_date;

    $insert_day = substr((string) $insert_date, 0, 10);
    $on_or_after_cutoff = strcmp($insert_day, $cutoff) >= 0;

    $slot = $on_or_after_cutoff ? $sales_agent2 : $sales_agent;
    if ($slot === null || $slot === '' || (int) $slot <= 0) {
        return null;
    }
    return (int) $slot;
}

/**
 * SQL EXISTS-fragment that decides whether a ghl_processed_leads row counts as a
 * conversion under the TC1/TC2 cutoff rule. Expects the caller's FROM clause to
 * alias ghl_processed_leads as `pl`.
 *
 * Identity match: the credited TC slot on the booking (SalesAgent pre-cutoff,
 * SalesAgent2 on/after cutoff) must equal the AdminID resolved from
 * pl.assigned_to_user_id via the admin_lead_dashboard_agents mapping table
 * (maintained from the Admin / Update form). A lead assigned to agent X only
 * counts as X's conversion when X actually holds the credited slot for the
 * booking's InsertDate window.
 *
 * Team-inbox GHL users (e.g. "Nur TC Team") use shared Gmail addresses that
 * differ from the corporate admin.Email, so the linkage is kept in an explicit
 * mapping table rather than inferred from email.
 */
function lead_conversion_credit_sql_fragment()
{
    $cutoff = LEAD_CONVERSION_TC2_CUTOFF_DATE;
    return "EXISTS (
        SELECT 1
        FROM booking b
        INNER JOIN admin_lead_dashboard_agents alda_credit
            ON alda_credit.GhlUserID = NULLIF(pl.assigned_to_user_id, '')
        INNER JOIN admin a_credit
            ON a_credit.AdminID = alda_credit.AdminID
            AND a_credit.Status = 'Y'
        WHERE b.BookingID = pl.booking_id
          AND (
              (b.InsertDate <  '{$cutoff}' AND b.SalesAgent  = a_credit.AdminID)
              OR
              (b.InsertDate >= '{$cutoff}' AND b.SalesAgent2 = a_credit.AdminID)
          )
    )";
}

/**
 * WHERE-clause fragment for filtering `booking` rows down to the ones credited
 * to a specific admin under the TC1/TC2 cutoff rule. Returns two `?`
 * placeholders that the caller must bind to the same admin_id (in order). Use
 * unqualified column names — intended for queries with FROM booking and no
 * conflicting joins.
 *
 * Pre-cutoff (InsertDate < 2026-06-01): the admin must hold SalesAgent (TC1).
 * On/after cutoff: the admin must hold SalesAgent2 (TC2). Mirrors the
 * attribution used by lead_conversion_credit_sql_fragment() so the booking
 * summary cards agree with the Lead Dashboard.
 */
function lead_conversion_credit_booking_clause()
{
    $cutoff = LEAD_CONVERSION_TC2_CUTOFF_DATE;
    return "(
        (booking.InsertDate <  '{$cutoff}' AND booking.SalesAgent  = ?)
        OR
        (booking.InsertDate >= '{$cutoff}' AND booking.SalesAgent2 = ?)
    )";
}

/**
 * SQL expression that resolves the credited AdminID for a booking row under
 * the TC1/TC2 cutoff rule. Use this in a SELECT or GROUP BY to aggregate by
 * the credited TC across the cutoff boundary in a single query — the
 * "Compare the Best" sub-lines on the TC summary cards use this so the
 * leaderboard universe matches the agent's own credited count.
 *
 * Pre-cutoff -> SalesAgent (TC1). On/after cutoff -> SalesAgent2 (TC2).
 */
function lead_conversion_credit_agent_expr($table_alias = 'booking')
{
    $cutoff = LEAD_CONVERSION_TC2_CUTOFF_DATE;
    return "(CASE WHEN {$table_alias}.InsertDate < '{$cutoff}'"
         . " THEN {$table_alias}.SalesAgent"
         . " ELSE {$table_alias}.SalesAgent2 END)";
}
