<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Card Visibility — owner-managed, per-(user x card) control over which Booking
 * summary cards each non-owner user may see (table `card_visibility_hidden_cards`).
 *
 * Model: cards are VISIBLE BY DEFAULT to their normal role. The owner hides a
 * specific card from a specific user by storing one (AdminID, CardSlug) row;
 * presence of a row => that user does NOT see that card. There can be several
 * Owner (Level 10) accounts, and on the booking listing an Owner sees the same
 * sales-agent card set as TC — so the TC cards are gate-able per owner-user too
 * (the managing owner toggles them). The Owner-Dashboard per-agent matrix is not
 * a registry card and is never gated.
 *
 * The card catalogue (card_visibility_registry) is the single source of truth,
 * shared by three places:
 *   - the owner settings page (Card_Visibility_Setting): lists every card with
 *     its role-eligible users;
 *   - the booking/_summary_cards partial: emits CSS that hides each hidden card's
 *     column on first paint (no flash);
 *   - Booking::ajax_summary_cards: strips each hidden card's data keys from the
 *     JSON payload so a hidden card's numbers are never sent.
 *
 * Slugs are role-prefixed (tc_/tl_/op_/fin_) so every rendered card maps to
 * exactly one slug even where two roles reuse the same DOM id (the roles never
 * co-render, so the anchor id is unique on any given page).
 *
 * The pure pieces (registry, eligibility, visible->hidden diff) are split from
 * the DB reads so they are unit-testable without CI/DB.
 */

if (!function_exists('card_visibility_registry')) {
	/**
	 * Every configurable summary card, keyed by stable slug.
	 *
	 * Each entry:
	 *   'group'  => UI heading + role bucket ('TC' | 'OP' | 'Finance'). TC Lead
	 *               (25) shares the 'TC' bucket — it sees the TC agent card set.
	 *   'title'  => human-readable card name
	 *   'levels' => admin.Level values whose users see this card (eligibility)
	 *   'anchor' => a DOM id that exists inside the card's column (for CSS hide)
	 *   'keys'   => the $cards/$tables keys to strip from the AJAX payload
	 *
	 * @return array slug => entry
	 */
	function card_visibility_registry()
	{
		return array(
			// ---------- TC / TC2 / TC LEAD / OWNER (level 20 / 50 / 25 / 10) ----------
			// TC Lead (25) and Owner (10) both show the same sales-agent card set as
			// TC scoped to their own bookings (summary_cards_show_agent_set + $show_tc
			// on the booking listing), so every card here is toggleable for them too.
			// The old team-lead-only card set (group "TC Lead") was retired when
			// $show_tclead was hard-set to false.
			'tc_bc_created'            => array('group' => 'TC', 'title' => 'BC Created',                         'levels' => array(10, 20, 50, 25), 'anchor' => 'sc-bc-month-count',                 'keys' => array('bc_month', 'bc_year')),
			'tc_commission_month'      => array('group' => 'TC', 'title' => 'Sales Commission (Month)',          'levels' => array(50),             'anchor' => 'sc-commission-month-value',         'keys' => array('commission_month')),
			'tc_sales_month'           => array('group' => 'TC', 'title' => 'Month Sales vs Target',              'levels' => array(10, 20, 50, 25), 'anchor' => 'sc-sales-month-value',              'keys' => array('sales_month')),
			'tc_sales_year'            => array('group' => 'TC', 'title' => 'Year Sales vs Target',               'levels' => array(10, 20, 50, 25), 'anchor' => 'sc-sales-year-value',               'keys' => array('sales_year')),
			'tc_cancellation_rate'     => array('group' => 'TC', 'title' => 'Cancellation Rate (Month)',          'levels' => array(10, 20, 50, 25), 'anchor' => 'sc-cancel-rate-value',              'keys' => array('cancellation_rate')),
			'tc_conversion_rate_ytd'   => array('group' => 'TC', 'title' => 'Conversion Rate (YTD)',             'levels' => array(10, 20, 50, 25), 'anchor' => 'sc-conv-rate-value',                'keys' => array('conversion_rate_ytd')),
			'tc_new_leads'             => array('group' => 'TC', 'title' => 'New Leads',                          'levels' => array(10, 20, 50, 25), 'anchor' => 'sc-tc-leads-month',                 'keys' => array('tc_leads_dwm')),
			'tc_handle_lead'           => array('group' => 'TC', 'title' => 'Daily Handle Lead Count',            'levels' => array(10, 20, 50, 25), 'anchor' => 'sc-tc-handle-month',                'keys' => array('tc_handle_lead_today')),
			'tc_response_time'         => array('group' => 'TC', 'title' => 'Avg Reply Time to Inbound',          'levels' => array(10, 20, 50, 25), 'anchor' => 'sc-tc-resp-month',                  'keys' => array('tc_response_time_dwm')),
			'tc_pickup_speed'          => array('group' => 'TC', 'title' => 'Lead Pickup Speed (Month)',          'levels' => array(10, 20, 50, 25), 'anchor' => 'sc-tc-pickup-value',                'keys' => array('tc_pickup_speed_month')),
			'tc_outbound_msgs'         => array('group' => 'TC', 'title' => 'Outbound Messages',                  'levels' => array(10, 20, 50, 25), 'anchor' => 'sc-tc-outbound-month',              'keys' => array('outbound_msgs_dwm')),
			'tc_followup_rate'         => array('group' => 'TC', 'title' => 'Follow-up % (Month)',                'levels' => array(10, 20, 50, 25), 'anchor' => 'sc-tc-followup-value',              'keys' => array('followup_rate')),
			'tc_agent_score'           => array('group' => 'TC', 'title' => 'Agent Score (Month)',                'levels' => array(10, 20, 50, 25), 'anchor' => 'sc-agent-score-value',              'keys' => array('agent_score_month')),
			'tc_upcoming_not_ready_7'  => array('group' => 'TC', 'title' => 'Travel in 7 Days – Not Yet Ready',   'levels' => array(10, 20, 50, 25), 'anchor' => 'sc-upcoming-not-ready-op-count',    'keys' => array('upcoming_travel_not_ready_op')),
			'tc_upcoming_not_ready_14' => array('group' => 'TC', 'title' => 'Travel in 14 Days – Not Yet Ready',  'levels' => array(10, 20, 50, 25), 'anchor' => 'sc-upcoming-not-ready-op-14-count', 'keys' => array('upcoming_travel_not_ready_op_14')),
			'tc_customer_payment_due'  => array('group' => 'TC', 'title' => 'Payment From Customer Due Soon',     'levels' => array(10, 20, 50, 25), 'anchor' => 'sc-customer-payment-due-soon-body', 'keys' => array('customer_payment_due_soon')),

			// ---------- OP (level 40 / 45) ----------
			'op_pending_bc'             => array('group' => 'OP', 'title' => 'Pending BC',                                 'levels' => array(40, 45), 'anchor' => 'sc-pending-bc-op-count',                'keys' => array('pending_bc_op')),
			'op_pending_bc_confirm'     => array('group' => 'OP', 'title' => 'Pending BC Confirmation',                    'levels' => array(40, 45), 'anchor' => 'sc-pending-bc-confirmation-op-count',   'keys' => array('pending_bc_confirmation_op')),
			'op_travel_tomorrow_nr'     => array('group' => 'OP', 'title' => 'Travelling Tomorrow – Not Pending Travel',  'levels' => array(40, 45), 'anchor' => 'sc-travel-tomorrow-not-ready-op-count', 'keys' => array('travel_tomorrow_not_ready_op')),
			'op_travel_today'           => array('group' => 'OP', 'title' => 'Travelling Today',                           'levels' => array(40, 45), 'anchor' => 'sc-travel-today-op-count',              'keys' => array('travel_today_op')),
			'op_travel_tomorrow'        => array('group' => 'OP', 'title' => 'Travelling Tomorrow',                        'levels' => array(40, 45), 'anchor' => 'sc-travel-tomorrow-op-count',           'keys' => array('travel_tomorrow_op')),
			'op_upcoming_not_ready_7'   => array('group' => 'OP', 'title' => 'Travel in 7 Days – Not Yet Ready',          'levels' => array(40, 45), 'anchor' => 'sc-upcoming-not-ready-op-count',        'keys' => array('upcoming_travel_not_ready_op')),
			'op_upcoming_not_ready_14'  => array('group' => 'OP', 'title' => 'Travel in 14 Days – Not Yet Ready',         'levels' => array(40, 45), 'anchor' => 'sc-upcoming-not-ready-op-14-count',     'keys' => array('upcoming_travel_not_ready_op_14')),
			'op_pending_review'         => array('group' => 'OP', 'title' => 'Travel Completed - Pending Review',          'levels' => array(40, 45), 'anchor' => 'sc-pending-review-op-count',            'keys' => array('pending_review_op')),
			'op_gl_submitted'           => array('group' => 'OP', 'title' => 'Guest List Submitted',                      'levels' => array(40, 45), 'anchor' => 'sc-gl-submitted-count',                 'keys' => array('gl_submitted')),
			'op_insurance_pending'      => array('group' => 'OP', 'title' => 'Pending Insurance Checklist',               'levels' => array(40, 45), 'anchor' => 'sc-insurance-pending-count',            'keys' => array('insurance_pending')),
			'op_ferry_pending'          => array('group' => 'OP', 'title' => 'Pending Ferry Transfer',                    'levels' => array(40, 45), 'anchor' => 'sc-ferry-pending-count',                'keys' => array('ferry_pending')),
			'op_bc_created'             => array('group' => 'OP', 'title' => 'BC Created',                                 'levels' => array(40, 45), 'anchor' => 'sc-bc-month-op',                        'keys' => array('bc_week_month')),
			'op_conversion_time'        => array('group' => 'OP', 'title' => 'Avg Conversion Time (Month)',               'levels' => array(40, 45), 'anchor' => 'sc-conv-time-value',                    'keys' => array('conversion_time_month')),
			'op_slow_conversion'        => array('group' => 'OP', 'title' => 'Slow Conversions (> 24h)',                  'levels' => array(40, 45), 'anchor' => 'sc-slow-conv-count',                    'keys' => array('slow_conversion_month')),
			'op_customer_payment_due'   => array('group' => 'OP', 'title' => 'Payment From Customer Due Soon',            'levels' => array(40, 45), 'anchor' => 'sc-customer-payment-due-soon-body',     'keys' => array('customer_payment_due_soon')),
			'op_checklist_payout_due'   => array('group' => 'OP', 'title' => 'Supplier Pay-out Checklist Due Soon',       'levels' => array(40, 45), 'anchor' => 'sc-checklist-payout-due-soon-body',     'keys' => array('checklist_payout_due_soon')),
			'op_supplier_due'           => array('group' => 'OP', 'title' => 'Supplier Pay-out Due Soon',                 'levels' => array(40, 45), 'anchor' => 'sc-supplier-due-soon-body',             'keys' => array('supplier_due_soon')),
			'op_destination_sales'      => array('group' => 'OP', 'title' => 'Top Destinations (Month)',                  'levels' => array(40, 45), 'anchor' => 'sc-destination-sales-body',             'keys' => array('destination_sales')),
			'op_product_sales'          => array('group' => 'OP', 'title' => 'Top Products (Month)',                      'levels' => array(40, 45), 'anchor' => 'sc-product-sales-body',                 'keys' => array('product_sales')),

			// ---------- FINANCE (level 30) ----------
			'fin_payment_in'            => array('group' => 'Finance', 'title' => 'Total Payment In',                      'levels' => array(30), 'anchor' => 'sc-payin-month',              'keys' => array('payment_in_dwm')),
			'fin_destination_sales'     => array('group' => 'Finance', 'title' => 'Top Destinations (Month)',             'levels' => array(30), 'anchor' => 'sc-destination-sales-body',   'keys' => array('destination_sales')),
			'fin_product_sales'         => array('group' => 'Finance', 'title' => 'Top Products (Month)',                 'levels' => array(30), 'anchor' => 'sc-product-sales-body',       'keys' => array('product_sales')),
			'fin_sales_by_team'         => array('group' => 'Finance', 'title' => 'Sales by Team (Month)',                'levels' => array(30), 'anchor' => 'sc-sales-by-team-body',       'keys' => array('sales_by_team')),
			'fin_supplier_overdue'      => array('group' => 'Finance', 'title' => 'Supplier Overdue',                     'levels' => array(30), 'anchor' => 'sc-supplier-overdue-body',    'keys' => array('supplier_overdue')),
		);
	}
}

if (!function_exists('card_visibility_pair_key')) {
	/**
	 * Stable composite key for a (card, user) pair used in the settings form and
	 * the visible->hidden diff. Slugs never contain '|'.
	 */
	function card_visibility_pair_key($slug, $admin_id)
	{
		return $slug . '|' . (int) $admin_id;
	}
}

if (!function_exists('card_visibility_eligible_pairs')) {
	/**
	 * Every (card, user) pair the owner can toggle: each card crossed with the
	 * users whose Level makes them eligible for it. Pure.
	 *
	 * @param array $registry slug => entry (from card_visibility_registry)
	 * @param array $users    list of objects/arrays with AdminID + Level
	 * @return string[] list of pair keys "slug|adminid"
	 */
	function card_visibility_eligible_pairs(array $registry, array $users)
	{
		$out = array();
		foreach ($registry as $slug => $card) {
			$levels = array_map('intval', $card['levels']);
			foreach ($users as $u) {
				$lvl = (int) (is_array($u) ? $u['Level'] : $u->Level);
				$aid = (int) (is_array($u) ? $u['AdminID'] : $u->AdminID);
				if (in_array($lvl, $levels, true)) {
					$out[] = card_visibility_pair_key($slug, $aid);
				}
			}
		}
		return $out;
	}
}

if (!function_exists('card_visibility_hidden_from_visible')) {
	/**
	 * The hidden set is every eligible pair the owner did NOT leave switched on.
	 * Visible-by-default: a pair absent from $visible_keys is hidden. Pure.
	 *
	 * @param string[] $eligible_pairs list of "slug|adminid" (authoritative set)
	 * @param array    $visible_keys   map "slug|adminid" => true (posted ON switches)
	 * @return array list of ['admin' => int, 'slug' => string] to persist as hidden
	 */
	function card_visibility_hidden_from_visible(array $eligible_pairs, array $visible_keys)
	{
		$out = array();
		foreach ($eligible_pairs as $pair) {
			if (isset($visible_keys[$pair])) { continue; }
			$bar = strrpos($pair, '|');
			if ($bar === false) { continue; }
			$out[] = array(
				'slug'  => substr($pair, 0, $bar),
				'admin' => (int) substr($pair, $bar + 1),
			);
		}
		return $out;
	}
}

if (!function_exists('card_visibility_hidden_slugs_for')) {
	/**
	 * The set of card slugs hidden for one user, as slug => true. DB read.
	 *
	 * @param int $admin_id
	 * @return array
	 */
	function card_visibility_hidden_slugs_for($admin_id)
	{
		$CI =& get_instance();
		$out = array();
		$rows = $CI->db->select('CardSlug')
			->where('AdminID', (int) $admin_id)
			->get('card_visibility_hidden_cards')->result();
		foreach ($rows as $r) {
			$out[$r->CardSlug] = true;
		}
		return $out;
	}
}

if (!function_exists('card_visibility_hide_css')) {
	/**
	 * A <style> body that hides each hidden card's whole column on first paint
	 * (no flash). Uses :has() to walk from the card's anchor id up to its Bootstrap
	 * column, scoped to the summary container. Empty string when nothing is hidden.
	 *
	 * @param array $hidden_slugs map slug => true (from card_visibility_hidden_slugs_for)
	 * @param array $registry     slug => entry
	 * @return string
	 */
	function card_visibility_hide_css(array $hidden_slugs, array $registry)
	{
		$sel = array();
		foreach ($hidden_slugs as $slug => $_) {
			if (!isset($registry[$slug])) { continue; }
			$anchor = $registry[$slug]['anchor'];
			$sel[] = '#booking_summary_cards [class*="col-"]:has(#' . $anchor . ')';
		}
		if (empty($sel)) { return ''; }
		return implode(",\n", $sel) . " { display:none !important; }";
	}
}
