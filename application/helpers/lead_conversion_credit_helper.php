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
 * The rule is intentionally a slot-presence check on the matched booking, not
 * an identity match against the lead's GHL owner: the existing dashboard scope
 * (assigned_to_user_id IN _restrict_agent_ids) already narrows leads to the
 * viewing admin's allowed set, so a conversion is "theirs" iff their scoped
 * lead converted to a booking whose credited TC slot is set per the cutoff.
 */
function lead_conversion_credit_sql_fragment()
{
    $cutoff = LEAD_CONVERSION_TC2_CUTOFF_DATE;
    return "EXISTS (
        SELECT 1
        FROM booking b
        WHERE b.BookingID = pl.booking_id
          AND (
              (b.InsertDate <  '{$cutoff}' AND b.SalesAgent  IS NOT NULL AND b.SalesAgent  > 0)
              OR
              (b.InsertDate >= '{$cutoff}' AND b.SalesAgent2 IS NOT NULL AND b.SalesAgent2 > 0)
          )
    )";
}
