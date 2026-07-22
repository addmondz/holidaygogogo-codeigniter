<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Cancellation-rate helpers.
 *
 * The Cancellation Rate KPI must NOT penalise an agent for cancelling a
 * "BOOKING - DUPLICATED BOOKING" — those are data-entry duplicates of a real
 * booking, not a lost sale. Such rows are dropped from BOTH the numerator
 * (cancelled count) and the denominator (total population) so the rate reflects
 * only genuine cancellations.
 *
 * The reason is matched by NAME (via the cancellation_reason table) rather than
 * a hard-coded CancellationReasonID, so the rule survives differing seed IDs
 * across environments.
 */

/** The exact cancellation_reason.Name treated as a duplicate booking. */
function cancellation_rate_duplicate_reason_name()
{
	return 'BOOKING - DUPLICATED BOOKING';
}

/**
 * SQL predicate (boolean) that is TRUE for bookings that should COUNT toward
 * the cancellation-rate population. It keeps every non-cancelled booking and
 * every genuine cancellation, and excludes only rows cancelled as duplicates.
 *
 * Safe against a cancelled row with a NULL CancellationReasonID (those stay in
 * the population as a genuine cancellation).
 *
 * @param string $alias booking table alias used in the surrounding query
 * @return string
 */
function cancellation_rate_exclude_duplicate_clause($alias = 'booking')
{
	$reason = cancellation_rate_duplicate_reason_name();
	return "({$alias}.CancelStatus <> 'Y'"
		. " OR {$alias}.CancellationReasonID IS NULL"
		. " OR {$alias}.CancellationReasonID NOT IN"
		. " (SELECT CancellationReasonID FROM cancellation_reason WHERE Name = '{$reason}'))";
}
