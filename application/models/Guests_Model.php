<?php
class Guests_Model extends CI_Model
{
	// Which single source this page reads: 'guest' (Guest List, booking guests)
	// or 'ghl' (GHL Leads). Set once per request by the controller.
	private $mode = 'guest';

	/**
	 * Lock the listing to one source. Controllers call this before Read/Count so
	 * the Guest List and GHL Leads pages each read only their own branch.
	 */
	function Set_Mode($mode)
	{
		$this->mode = ($mode === 'ghl') ? 'ghl' : 'guest';
		return $this;
	}

	private function Dedup_Key_Expr()
	{
		return "gl.dedup_key";
	}

	/**
	 * Append a multi-select filter as an " AND {$column} IN (?, ?, …) " fragment
	 * to $where and push its values onto $params. No-op when nothing is selected.
	 * $column is a trusted, code-supplied SQL expression (never user input);
	 * every value is bound as a placeholder.
	 */
	private function Append_In_Clause(&$where, &$params, $column, $raw)
	{
		$values = guest_list_multi_values($raw);
		if(empty($values)) {
			return;
		}
		$placeholders = implode(',', array_fill(0, count($values), '?'));
		$where .= " AND {$column} IN ({$placeholders}) ";
		foreach($values as $v) {
			$params[] = $v;
		}
	}

	private function Ghl_Dedup_Key_Expr()
	{
		return "(COALESCE(gc.dedup_key, CONCAT('ghl:', gc.id)) COLLATE utf8mb4_unicode_ci)";
	}

	private function Build_Branches()
	{
		$dedup     = $this->Dedup_Key_Expr();
		$gc_dedup  = $this->Ghl_Dedup_Key_Expr();

		$q_raw     = trim((string)$this->input->get('q'));
		$has_q     = $q_raw !== '';
		$like      = $has_q ? '%' . $q_raw . '%' : null;

		$this->load->helper('guest_contact');

		// Each page reads ONE source (see Set_Mode); a filter that can never match
		// that source still drops it to empty via the shared suppression predicates.
		$run       = guest_list_branches_to_run($this->mode, $this->input->get());
		$run_bookings = $run['bookings'];
		$run_ghl      = $run['ghl'];

		$booking = null;
		if($run_bookings) {
			$where = " WHERE b.Status != 'N' AND b.CancelStatus = 'N'
				AND gl.dedup_key IS NOT NULL
				AND (
					NULLIF(TRIM(gl.Name), '')     IS NOT NULL
					OR NULLIF(TRIM(gl.LastName), '') IS NOT NULL
					OR NULLIF(TRIM(gl.Mobile), '')   IS NOT NULL
					OR NULLIF(TRIM(gl.Email), '')    IS NOT NULL
				) ";
			$b_params = array();

			if(in_array($this->session->userdata('level'), array(20, 50))) {
				$where     .= " AND b.SalesAgent = ? ";
				$b_params[] = $this->session->userdata('admin_id');
			}

			$booking_range = guest_list_parse_date_range($this->input->get('booking_date'));
			if($booking_range !== null) {
				$where     .= " AND b.InsertDate >= ? AND b.InsertDate < DATE_ADD(?, INTERVAL 1 DAY) ";
				$b_params[] = $booking_range[0];
				$b_params[] = $booking_range[1];
			}

			$travel_range = guest_list_parse_date_range($this->input->get('travel_date'));
			if($travel_range !== null) {
				$where     .= " AND b.StartDate <= ? AND b.EndDate >= ? ";
				$b_params[] = $travel_range[1];
				$b_params[] = $travel_range[0];
			}

			// DOB is a booking-only, born-between calendar range on the guest's
			// date of birth. DateOfBirth is a DATE column, so an inclusive
			// >= start AND <= end matches exactly (NULL / 0000-00-00 never match).
			$dob_range = guest_list_parse_date_range($this->input->get('dob'));
			if($dob_range !== null) {
				$where     .= " AND gl.DateOfBirth >= ? AND gl.DateOfBirth <= ? ";
				$b_params[] = $dob_range[0];
				$b_params[] = $dob_range[1];
			}

			// Campaign / Follow date ranges match the guest's remark log: a guest
			// appears when ANY of their active remarks (keyed by dedup_key, so it
			// spans all their bookings) falls in the picked range. EXISTS keeps it
			// a row-level predicate the GROUP BY never has to see.
			$campaign_range = guest_list_parse_date_range($this->input->get('campaign_date'));
			if($campaign_range !== null) {
				$where     .= " AND EXISTS (SELECT 1 FROM guest_remarks gr
					WHERE gr.Status = 'Y' AND gr.dedup_key = gl.dedup_key
					AND gr.CampaignDate >= ? AND gr.CampaignDate <= ?) ";
				$b_params[] = $campaign_range[0];
				$b_params[] = $campaign_range[1];
			}

			$follow_range = guest_list_parse_date_range($this->input->get('follow_date'));
			if($follow_range !== null) {
				$where     .= " AND EXISTS (SELECT 1 FROM guest_remarks gr
					WHERE gr.Status = 'Y' AND gr.dedup_key = gl.dedup_key
					AND gr.FollowDate >= ? AND gr.FollowDate <= ?) ";
				$b_params[] = $follow_range[0];
				$b_params[] = $follow_range[1];
			}

			// Birthday is a recurring month/day match on gl.DateOfBirth that
			// ignores the birth year (today / this month / a chosen month), so
			// it surfaces guests to greet regardless of how old they turn.
			$birthday = guest_list_birthday_clause($this->input->get('birthday'));
			if($birthday !== null) {
				$where    .= $birthday['sql'];
				foreach($birthday['params'] as $bp) {
					$b_params[] = $bp;
				}
			}

			$booking_number = trim((string)$this->input->get('booking_number'));
			if($booking_number !== '') {
				$where     .= " AND b.BookingNumber LIKE ? ";
				$b_params[] = '%' . $booking_number . '%';
			}
			$contact_number = trim((string)$this->input->get('contact_number'));
			if($contact_number !== '') {
				// Match ONLY the guest's own gl.Mobile — the exact number shown in
				// the Contact Num column — so every returned row visibly contains
				// the searched digits. (A booking's leader/contact number that lives
				// only on b.Mobile / c.phone_number is intentionally NOT matched
				// here; the leader-fallback branch still surfaces such contacts when
				// the booking's guest list is empty.)
				$where     .= " AND gl.Mobile LIKE ? ";
				$b_params[] = '%' . $contact_number . '%';
			}
			$tl_clause = guest_list_team_leader_clause($this->input->get());
			if($tl_clause !== null) {
				$where     .= $tl_clause['sql'];
				$b_params[] = $tl_clause['param'];
			}
			// Clicking a Team Leader name drills into that leader's exact
			// booking(s) by BookingID, so the list shows only that booking's
			// team members — not every booking the leader has ever run.
			$this->Append_In_Clause($where, $b_params, 'b.BookingID', $this->input->get('booking_id'));
			$email = trim((string)$this->input->get('email'));
			if($email !== '') {
				$where     .= " AND gl.Email LIKE ? ";
				$b_params[] = '%' . $email . '%';
			}
			// Dropdown filters are multi-select (see views/guests/index.php): each
			// may arrive as several values, so they filter via an IN (...) list.
			$this->Append_In_Clause($where, $b_params, 'b.Destination',                    $this->input->get('destination'));
			$this->Append_In_Clause($where, $b_params, 'b.SalesAgent',                     $this->input->get('sales_agent'));
			$this->Append_In_Clause($where, $b_params, 'b.Source',                         $this->input->get('source'));
			$this->Append_In_Clause($where, $b_params, 'c.customer_type',                  $this->input->get('customer_type'));
			$this->Append_In_Clause($where, $b_params, 'cn.Country',                       $this->input->get('nationality'));
			$this->Append_In_Clause($where, $b_params, 'gl.Gender',                        $this->input->get('gender'));
			$this->Append_In_Clause($where, $b_params, 'gl.Type',                          $this->input->get('guest_type'));
			$this->Append_In_Clause($where, $b_params, 'COALESCE(c.ChatLanguage, b.ChatLanguage)', $this->input->get('language'));
			if($has_q) {
				// Search Name matches the guest's own name OR their booking's team
				// leader (b.Customer, the name on the BC form), so searching a
				// leader surfaces everyone on their team.
				$where     .= " AND ( gl.Name LIKE ? OR gl.LastName LIKE ? OR CONCAT_WS(' ', gl.Name, gl.LastName) LIKE ? OR b.Customer LIKE ? ) ";
				$b_params[] = $like;
				$b_params[] = $like;
				$b_params[] = $like;
				$b_params[] = $like;
			}

			$from_joins_where = "
	FROM booking b
	STRAIGHT_JOIN guest_list gl ON gl.BookingID = b.BookingID AND gl.Status = 'Y'
	LEFT JOIN customer     c   ON c.CustomerID    = b.CustomerID
	LEFT JOIN admin        a   ON a.AdminID       = b.SalesAgent
	LEFT JOIN source       s   ON s.SourceID      = b.Source
	LEFT JOIN country_code cn  ON cn.CountryCodeID = gl.Nationality
	LEFT JOIN country_code ccp ON ccp.CountryCodeID = gl.CountryCodeID
	LEFT JOIN category     cat ON cat.CategoryID  = b.Destination
	{$where}
			";

			// Guest Role and Num of Pax are per-guest aggregates, so they filter
			// the GROUP BY result via HAVING (not the row-level WHERE). The
			// expressions below reuse columns produced by the windowed inner
			// subquery (IsLeader, BookingPax, booking_rn) so Read and Count agree.
			$having        = array();
			$having_params = array();

			// Guest Role is multi-select. "Lead" only affects the GHL branch, so
			// here we look at the two booking roles: picking exactly one narrows
			// the result; picking both (or neither) leaves booking guests unfiltered.
			$roles       = guest_list_multi_values($this->input->get('role'));
			$want_leader = in_array('Team Leader', $roles, true);
			$want_member = in_array('Team Member', $roles, true);
			if($want_leader && !$want_member)     { $having[] = "MAX(IsLeader) = 1"; }
			elseif($want_member && !$want_leader) { $having[] = "MAX(IsLeader) = 0"; }

			$pax_expr = "COALESCE(SUM(CASE WHEN booking_rn = 1 THEN BookingPax END), 0)";
			$pax_min  = trim((string)$this->input->get('pax_min'));
			$pax_max  = trim((string)$this->input->get('pax_max'));
			if($pax_min !== '' && is_numeric($pax_min)) {
				$having[]        = "{$pax_expr} >= ?";
				$having_params[] = (int)$pax_min;
			}
			if($pax_max !== '' && is_numeric($pax_max)) {
				$having[]        = "{$pax_expr} <= ?";
				$having_params[] = (int)$pax_max;
			}

			$booking = array(
				'dedup'         => $dedup,
				'from'          => $from_joins_where,
				'params'        => $b_params,
				'having'        => empty($having) ? '' : ' HAVING ' . implode(' AND ', $having),
				'having_params' => $having_params,
			);
		}

		$ghl = null;
		if($run_ghl) {
			$g_params = array();
			$ghl_where = "";
			if($has_q) {
				$ghl_where .= " AND ( gc.first_name LIKE ? OR gc.last_name LIKE ? OR CONCAT_WS(' ', gc.first_name, gc.last_name) LIKE ? ) ";
				$g_params[] = $like;
				$g_params[] = $like;
				$g_params[] = $like;
			}

			// Booking-date filter matches a lead's captured date — the same
			// DATE(COALESCE(date_added, created_at)) shown in the listing.
			$ghl_range = guest_list_parse_date_range($this->input->get('booking_date'));
			if($ghl_range !== null) {
				$ghl_where .= " AND DATE(COALESCE(gc.date_added, gc.created_at)) >= ?
					AND DATE(COALESCE(gc.date_added, gc.created_at)) < DATE_ADD(?, INTERVAL 1 DAY) ";
				$g_params[] = $ghl_range[0];
				$g_params[] = $ghl_range[1];
			}

			// Contact Number / Email are not booking-only — a lead carries both,
			// so they filter the GHL branch too (gc.phone / gc.email).
			$contact_number = trim((string)$this->input->get('contact_number'));
			if($contact_number !== '') {
				$ghl_where .= " AND gc.phone LIKE ? ";
				$g_params[] = '%' . $contact_number . '%';
			}
			$email = trim((string)$this->input->get('email'));
			if($email !== '') {
				$ghl_where .= " AND gc.email LIKE ? ";
				$g_params[] = '%' . $email . '%';
			}

			// GHL Leads is its own page now, so the query reads ghl_contacts only —
			// no booking/guest_list scan. (The old merged listing anti-joined the
			// two to avoid showing one person twice; separate pages don't need it.)
			$from_joins_where = "
FROM ghl_contacts gc
WHERE 1 = 1
{$ghl_where}
			";

			$ghl = array(
				'dedup'  => $gc_dedup,
				'from'   => $from_joins_where,
				'params' => $g_params,
			);
		}

		return array($booking, $ghl);
	}

	/**
	 * The per-(guest-row) windowed subquery shared by the listing (Read_Guests)
	 * and the count (Count_Guests). It exposes IsLeader / BookingPax /
	 * booking_rn / dedup_key so an outer GROUP BY dedup_key can aggregate them
	 * into Role and Num of Pax — and the HAVING built in Build_Branches filters
	 * on exactly those, identically in both paths.
	 */
	private function Booking_Windowed_Select($dedup, $from)
	{
		return "
	SELECT
		{$dedup} AS dedup_key,
		TRIM(gl.Name) AS display_name,
		gl.Mobile        AS ContactNum,
		ccp.CountryCode  AS CallingCode,
		gl.Email,
		COALESCE(c.ChatLanguage, b.ChatLanguage) AS ChatLanguage,
		a.Name           AS SalesAgentName,
		b.Customer       AS BookingCustomer,
		b.BookingID      AS BookingID,
		s.Name           AS SourceName,
		c.customer_type  AS customer_type,
		cat.Name         AS Destination,
		cn.Country       AS Nationality,
		gl.Gender,
		gl.Type          AS GuestType,
		gl.DateOfBirth,
		b.Token          AS Token,
		b.InsertDate     AS BookingDate,
		b.StartDate      AS TravelStart,
		b.EndDate        AS TravelEnd,
		CASE WHEN gl.dedup_key = COALESCE(
			NULLIF(RIGHT(REGEXP_REPLACE(IFNULL(b.Mobile, ''),       '[^0-9]', ''), 9), ''),
			NULLIF(RIGHT(REGEXP_REPLACE(IFNULL(c.phone_number, ''), '[^0-9]', ''), 9), '')
		) THEN 1 ELSE 0 END AS IsLeader,
		(COALESCE(b.Adult, 0) + COALESCE(b.Children, 0) + COALESCE(b.Infant, 0)) AS BookingPax,
		COALESCE(b.NetTotal, 0) AS BookingNetTotal,
		ROW_NUMBER() OVER (
			PARTITION BY {$dedup}
			ORDER BY b.InsertDate DESC, b.BookingID DESC
		) AS rn,
		ROW_NUMBER() OVER (
			PARTITION BY {$dedup}, b.BookingID
			ORDER BY gl.GuestListID
		) AS booking_rn
	{$from}
		";
	}

	/**
	 * The "leader fallback" branch: a Team Leader row built straight from the
	 * booking's own contact for every active booking whose leader phone is NOT
	 * already a filled guest_list row. This surfaces the leaders of bookings whose
	 * guest list was never filled in (blank rows → NULL dedup_key → invisible to
	 * the normal branch) so they stay pickable for a campaign. The NOT EXISTS
	 * anti-join against the whole filled set guarantees a leader is never shown
	 * twice — if their phone already belongs to a real guest anywhere, that guest
	 * carries them instead. Returns null when the branch is off (wrong mode /
	 * bookings suppressed / a guest-only filter that a leader can't satisfy).
	 */
	private function Build_Leader_Fallback_Branch()
	{
		$this->load->helper('guest_contact');
		$run = guest_list_branches_to_run($this->mode, $this->input->get());
		if(!$run['bookings']) {
			return null;
		}
		if(guest_list_leader_fallback_suppressed_by_filters($this->input->get())) {
			return null;
		}

		$key = $this->Booking_Leader_Key_Expr();

		$where = " WHERE b.Status != 'N' AND b.CancelStatus = 'N'
			AND {$key} IS NOT NULL
			AND NOT EXISTS (
				SELECT 1 FROM guest_list glx
				WHERE glx.Status = 'Y' AND glx.dedup_key = {$key}
			) ";
		$params = array();

		if(in_array($this->session->userdata('level'), array(20, 50))) {
			$where     .= " AND b.SalesAgent = ? ";
			$params[]   = $this->session->userdata('admin_id');
		}

		$booking_range = guest_list_parse_date_range($this->input->get('booking_date'));
		if($booking_range !== null) {
			$where   .= " AND b.InsertDate >= ? AND b.InsertDate < DATE_ADD(?, INTERVAL 1 DAY) ";
			$params[] = $booking_range[0];
			$params[] = $booking_range[1];
		}

		$travel_range = guest_list_parse_date_range($this->input->get('travel_date'));
		if($travel_range !== null) {
			$where   .= " AND b.StartDate <= ? AND b.EndDate >= ? ";
			$params[] = $travel_range[1];
			$params[] = $travel_range[0];
		}

		$booking_number = trim((string)$this->input->get('booking_number'));
		if($booking_number !== '') {
			$where   .= " AND b.BookingNumber LIKE ? ";
			$params[] = '%' . $booking_number . '%';
		}

		// Contact Number matches the leader's own booking phone (there is no
		// guest_list row yet), so it filters the booking/customer contact.
		$contact_number = trim((string)$this->input->get('contact_number'));
		if($contact_number !== '') {
			$where   .= " AND COALESCE(b.Mobile, c.phone_number) LIKE ? ";
			$params[] = '%' . $contact_number . '%';
		}

		$tl_clause = guest_list_team_leader_clause($this->input->get());
		if($tl_clause !== null) {
			$where   .= $tl_clause['sql'];
			$params[] = $tl_clause['param'];
		}

		$this->Append_In_Clause($where, $params, 'b.BookingID',   $this->input->get('booking_id'));
		$this->Append_In_Clause($where, $params, 'b.Destination', $this->input->get('destination'));
		$this->Append_In_Clause($where, $params, 'b.SalesAgent',  $this->input->get('sales_agent'));
		$this->Append_In_Clause($where, $params, 'b.Source',      $this->input->get('source'));
		$this->Append_In_Clause($where, $params, 'c.customer_type', $this->input->get('customer_type'));
		$this->Append_In_Clause($where, $params, "COALESCE(c.ChatLanguage, b.ChatLanguage)", $this->input->get('language'));

		$q_raw = trim((string)$this->input->get('q'));
		if($q_raw !== '') {
			$where   .= " AND b.Customer LIKE ? ";
			$params[] = '%' . $q_raw . '%';
		}

		$from = "
	FROM booking b
	LEFT JOIN customer     c   ON c.CustomerID     = b.CustomerID
	LEFT JOIN admin        a   ON a.AdminID        = b.SalesAgent
	LEFT JOIN source       s   ON s.SourceID       = b.Source
	LEFT JOIN category     cat ON cat.CategoryID   = b.Destination
	LEFT JOIN country_code cc  ON cc.CountryCodeID = b.CountryCodeID
	{$where}
		";

		return array('key' => $key, 'from' => $from, 'params' => $params);
	}

	/**
	 * The merged-listing SELECT for one leader-fallback group (one row per leader
	 * phone key). Column order MUST match the booking and GHL branch SELECTs so
	 * the UNION ALL lines up. Every non-key column is aggregated because a leader
	 * may run several unfilled bookings.
	 */
	private function Leader_Fallback_Select($key, $from)
	{
		return "
	SELECT
		{$key} AS dedup_key,
		CONVERT(MAX(b.Customer) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Name,
		CONVERT(GROUP_CONCAT(DISTINCT NULLIF(TRIM(b.Customer), '') SEPARATOR '||') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS TeamLeader,
		CONVERT(GROUP_CONCAT(DISTINCT CASE WHEN NULLIF(TRIM(b.Customer), '') IS NOT NULL THEN CONCAT(b.BookingID, ':', TRIM(b.Customer)) END SEPARATOR '||') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS TeamLeaderBookings,
		CONVERT(MAX(COALESCE(b.Mobile, c.phone_number)) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS ContactNum,
		CONVERT(MAX(cc.CountryCode) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS CallingCode,
		CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS Email,
		CONVERT(MAX(COALESCE(c.ChatLanguage, b.ChatLanguage)) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Language,
		CONVERT(MAX(a.Name) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS AgentName,
		CONVERT(MAX(s.Name) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Source,
		CONVERT(MAX(c.customer_type) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS CustomerType,
		CONVERT(GROUP_CONCAT(COALESCE(cat.Name, '-') ORDER BY DATE(b.InsertDate) SEPARATOR '||') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Destination,
		CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS Nationality,
		CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS Gender,
		CONVERT('ADULT' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS GuestType,
		CAST(NULL AS DATE) AS DOB,
		COALESCE(SUM(COALESCE(b.Adult, 0) + COALESCE(b.Children, 0) + COALESCE(b.Infant, 0)), 0) AS TotalPax,
		COALESCE(SUM(COALESCE(b.NetTotal, 0)), 0) AS TotalSales,
		CAST('Booking Guest' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS Type,
		CONVERT(MAX(b.Token) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Token,
		CONVERT('Team Leader' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Role,
		CONVERT(GROUP_CONCAT(DISTINCT DATE(b.InsertDate) ORDER BY DATE(b.InsertDate) SEPARATOR ',') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS BookingDates,
		CONVERT(GROUP_CONCAT(DISTINCT CONCAT(DATE(b.StartDate), '|', IFNULL(DATE(b.EndDate), '')) ORDER BY CONCAT(DATE(b.StartDate), '|', IFNULL(DATE(b.EndDate), '')) SEPARATOR ',') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS TravelDates
	{$from}
	GROUP BY {$key}
		";
	}

	private function Build_Merged_Sql_And_Params()
	{
		list($booking, $ghl) = $this->Build_Branches();
		$fallback = $this->Build_Leader_Fallback_Branch();

		$parts  = array();
		$params = array();

		if($booking !== null) {
			$dedup = $booking['dedup'];
			$parts[] = "
SELECT
	dedup_key,
	CONVERT(MAX(CASE WHEN rn = 1 THEN display_name   END) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Name,
	CONVERT(GROUP_CONCAT(DISTINCT CASE WHEN booking_rn = 1 THEN NULLIF(TRIM(BookingCustomer), '') END SEPARATOR '||') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS TeamLeader,
	CONVERT(GROUP_CONCAT(DISTINCT CASE WHEN booking_rn = 1 AND NULLIF(TRIM(BookingCustomer), '') IS NOT NULL THEN CONCAT(BookingID, ':', TRIM(BookingCustomer)) END SEPARATOR '||') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS TeamLeaderBookings,
	CONVERT(MAX(CASE WHEN rn = 1 THEN ContactNum     END) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS ContactNum,
	CONVERT(MAX(CASE WHEN rn = 1 THEN CallingCode    END) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS CallingCode,
	CONVERT(MAX(CASE WHEN rn = 1 THEN Email          END) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Email,
	CONVERT(MAX(CASE WHEN rn = 1 THEN ChatLanguage   END) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Language,
	CONVERT(MAX(CASE WHEN rn = 1 THEN SalesAgentName END) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS AgentName,
	CONVERT(MAX(CASE WHEN rn = 1 THEN SourceName     END) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Source,
	CONVERT(MAX(CASE WHEN rn = 1 THEN customer_type  END) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS CustomerType,
	CONVERT(GROUP_CONCAT(CASE WHEN booking_rn = 1 THEN COALESCE(Destination, '-') END ORDER BY DATE(BookingDate) SEPARATOR '||') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Destination,
	CONVERT(MAX(CASE WHEN rn = 1 THEN Nationality    END) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Nationality,
	CONVERT(MAX(CASE WHEN rn = 1 THEN Gender         END) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Gender,
	CONVERT(MAX(CASE WHEN rn = 1 THEN GuestType      END) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS GuestType,
	MAX(CASE WHEN rn = 1 THEN DateOfBirth END) AS DOB,
	COALESCE(SUM(CASE WHEN booking_rn = 1 THEN BookingPax      END), 0) AS TotalPax,
	COALESCE(SUM(CASE WHEN booking_rn = 1 THEN BookingNetTotal END), 0) AS TotalSales,
	CAST('Booking Guest' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS Type,
	CONVERT(MAX(CASE WHEN rn = 1 THEN Token END) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Token,
	CONVERT(CASE WHEN MAX(IsLeader) = 1 THEN 'Team Leader' ELSE 'Team Member' END USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Role,
	CONVERT(GROUP_CONCAT(DISTINCT DATE(BookingDate) ORDER BY DATE(BookingDate) SEPARATOR ',') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS BookingDates,
	CONVERT(GROUP_CONCAT(DISTINCT CONCAT(DATE(TravelStart), '|', IFNULL(DATE(TravelEnd), '')) ORDER BY CONCAT(DATE(TravelStart), '|', IFNULL(DATE(TravelEnd), '')) SEPARATOR ',') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS TravelDates
FROM (
	{$this->Booking_Windowed_Select($dedup, $booking['from'])}
) t
GROUP BY dedup_key
{$booking['having']}
			";
			$params = array_merge($params, $booking['params'], $booking['having_params']);
		}

		if($ghl !== null) {
			$gc_dedup = $ghl['dedup'];
			$parts[] = "
SELECT
	{$gc_dedup} AS dedup_key,
	CONVERT(TRIM(gc.first_name) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Name,
	CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS TeamLeader,
	CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS TeamLeaderBookings,
	CONVERT(gc.phone USING utf8mb4) COLLATE utf8mb4_unicode_ci AS ContactNum,
	CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS CallingCode,
	CONVERT(gc.email USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Email,
	NULL AS Language,
	NULL AS AgentName,
	NULL AS Source,
	NULL AS CustomerType,
	NULL AS Destination,
	NULL AS Nationality,
	NULL AS Gender,
	NULL AS GuestType,
	NULL AS DOB,
	0    AS TotalPax,
	0    AS TotalSales,
	CAST('GHL' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS Type,
	CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS Token,
	CAST('Lead' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS Role,
	CONVERT(DATE(COALESCE(gc.date_added, gc.created_at)) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS BookingDates,
	CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS TravelDates
{$ghl['from']}
			";
			$params = array_merge($params, $ghl['params']);
		}

		if($fallback !== null) {
			$parts[] = $this->Leader_Fallback_Select($fallback['key'], $fallback['from']);
			$params  = array_merge($params, $fallback['params']);
		}

		if(empty($parts)) {
			return array(null, array());
		}

		$inner = "SELECT * FROM (" . implode(" UNION ALL ", $parts) . ") merged";
		return array($inner, $params);
	}

	function Read_Guests($limit, $offset)
	{
		list($inner, $params) = $this->Build_Merged_Sql_And_Params();
		if($inner === null) {
			return array();
		}

		$sql = $inner . " ORDER BY CASE WHEN Type = 'Booking Guest' THEN 0 ELSE 1 END, Name ASC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
		return $this->db->query($sql, $params)->result();
	}

	function Count_Guests()
	{
		list($booking, $ghl) = $this->Build_Branches();

		$total = 0;

		if($booking !== null) {
			if($booking['having'] !== '') {
				// Role / pax filter on a per-guest aggregate: count the grouped
				// rows that survive the same HAVING the listing applies.
				$inner = $this->Booking_Windowed_Select($booking['dedup'], $booking['from']);
				$sql   = "SELECT COUNT(*) AS cnt FROM (
					SELECT dedup_key FROM ({$inner}) t GROUP BY dedup_key {$booking['having']}
				) z";
				$params = array_merge($booking['params'], $booking['having_params']);
				$row    = $this->db->query($sql, $params)->row();
			} else {
				$sql = "SELECT COUNT(DISTINCT {$booking['dedup']}) AS cnt {$booking['from']}";
				$row = $this->db->query($sql, $booking['params'])->row();
			}
			if($row) { $total += (int)$row->cnt; }
		}

		if($ghl !== null) {
			$sql = "SELECT COUNT(DISTINCT {$ghl['dedup']}) AS cnt {$ghl['from']}";
			$row = $this->db->query($sql, $ghl['params'])->row();
			if($row) { $total += (int)$row->cnt; }
		}

		$fallback = $this->Build_Leader_Fallback_Branch();
		if($fallback !== null) {
			// One synthesized row per leader phone key (no HAVING on this branch).
			$sql = "SELECT COUNT(DISTINCT {$fallback['key']}) AS cnt {$fallback['from']}";
			$row = $this->db->query($sql, $fallback['params'])->row();
			if($row) { $total += (int)$row->cnt; }
		}

		return $total;
	}

	/**
	 * True when $new_key already belongs to a DIFFERENT person than
	 * $current_dedup_key — either an active booking guest or a GHL lead.
	 * Shared predicate lives in guest_contact_duplicate_key_sql() so the unit
	 * test exercises the exact same SQL. Global (not sales-agent scoped) so two
	 * distinct guests can never silently merge onto one number.
	 */
	function Contact_Key_Belongs_To_Other($new_key, $current_dedup_key)
	{
		$new_key = (string) $new_key;
		if ($new_key === '') {
			return false;
		}
		$this->load->helper('guest_contact');
		$sql = guest_contact_duplicate_key_sql();
		$row = $this->db->query($sql, array($new_key, $current_dedup_key, $new_key, $current_dedup_key))->row();
		return $row && (int) $row->dup === 1;
	}

	/**
	 * The booking "leader phone key" — the last-9 digits of the booking's own
	 * Mobile, falling back to the linked customer's phone. A guest whose
	 * dedup_key equals this is the booking's team leader (mirrors the IsLeader
	 * expression in Booking_Windowed_Select). Used to decide which bookings a
	 * dashboard edit should reflect back onto (booking.Mobile / ChatLanguage).
	 */
	private function Booking_Leader_Key_Expr()
	{
		return "COALESCE(
			NULLIF(RIGHT(REGEXP_REPLACE(IFNULL(b.Mobile, ''),       '[^0-9]', ''), 9), ''),
			NULLIF(RIGHT(REGEXP_REPLACE(IFNULL(c.phone_number, ''), '[^0-9]', ''), 9), '')
		)";
	}

	/**
	 * Overwrite a plain guest_list column (Name / Mobile / Email) on every active
	 * row that shares $current_dedup_key — this person's records across bookings —
	 * keeping them grouped under the same key. When $scope_admin_id is set (levels
	 * 20/50) the update is confined to that agent's own bookings. Returns affected
	 * rows. $column is whitelisted by the callers below, never taken from input.
	 */
	private function Update_Guest_List_Column($column, $current_dedup_key, $value, $admin_id, $scope_admin_id = null)
	{
		$sql = "UPDATE guest_list gl
			JOIN booking b ON b.BookingID = gl.BookingID
			SET gl.{$column} = ?, gl.UpdateBy = ?, gl.UpdateDate = ?
			WHERE gl.Status = 'Y' AND b.Status != 'N' AND b.CancelStatus = 'N'
				AND gl.dedup_key = ?";
		$params = array($value, $admin_id, date('Y-m-d H:i:s'), $current_dedup_key);

		if ($scope_admin_id !== null) {
			$sql      .= " AND b.SalesAgent = ?";
			$params[]  = $scope_admin_id;
		}

		$this->db->query($sql, $params);
		return $this->db->affected_rows();
	}

	/**
	 * Contact number edit. Writes Mobile onto every active guest_list row sharing
	 * $current_dedup_key, then reflects the new number back onto booking.Mobile
	 * for the bookings this person LEADS (so the BC / booking contact stays in
	 * sync). The leader match uses the OLD key, since the new Mobile would change
	 * the generated dedup_key. Returns guest_list rows affected.
	 */
	function Update_Guest_Contact($current_dedup_key, $new_mobile, $admin_id, $scope_admin_id = null)
	{
		$affected = $this->Update_Guest_List_Column('Mobile', $current_dedup_key, $new_mobile, $admin_id, $scope_admin_id);

		$key_expr = $this->Booking_Leader_Key_Expr();
		$sql = "UPDATE booking b
			LEFT JOIN customer c ON c.CustomerID = b.CustomerID
			SET b.Mobile = ?
			WHERE b.Status != 'N' AND b.CancelStatus = 'N'
				AND {$key_expr} = ?";
		$params = array($new_mobile, $current_dedup_key);
		if ($scope_admin_id !== null) {
			$sql      .= " AND b.SalesAgent = ?";
			$params[]  = $scope_admin_id;
		}
		$this->db->query($sql, $params);

		return $affected;
	}

	/**
	 * First Name edit → guest_list.Name on all of the person's active rows. The
	 * booking's own leader name (booking.Customer) is intentionally NOT touched:
	 * it holds the full BC name, so rewriting it with just the first name would
	 * drop the surname. Returns rows affected.
	 */
	function Update_Guest_Name($current_dedup_key, $name, $admin_id, $scope_admin_id = null)
	{
		return $this->Update_Guest_List_Column('Name', $current_dedup_key, $name, $admin_id, $scope_admin_id);
	}

	/**
	 * Email edit → guest_list.Email on all of the person's active rows. Booking
	 * has no dedicated guest-email column (it reads MAX(guest_list.Email)), so the
	 * guest_list write already reflects onto the booking. Returns rows affected.
	 */
	function Update_Guest_Email($current_dedup_key, $email, $admin_id, $scope_admin_id = null)
	{
		return $this->Update_Guest_List_Column('Email', $current_dedup_key, $email, $admin_id, $scope_admin_id);
	}

	/**
	 * Language edit. ChatLanguage lives on booking / customer, not guest_list, and
	 * the dashboard shows COALESCE(customer, booking) — so to be visible the value
	 * is written to BOTH on the bookings this person LEADS. Only ChatLanguage is
	 * touched (not the AutoCount sync flags), so no debtor re-sync is queued.
	 * Returns bookings affected (0 when the person leads none — a pure team member).
	 */
	function Update_Guest_Language($current_dedup_key, $language, $admin_id, $scope_admin_id = null)
	{
		$key_expr = $this->Booking_Leader_Key_Expr();
		$sql = "UPDATE booking b
			LEFT JOIN customer c ON c.CustomerID = b.CustomerID
			SET b.ChatLanguage = ?, c.ChatLanguage = ?
			WHERE b.Status != 'N' AND b.CancelStatus = 'N'
				AND {$key_expr} = ?";
		$params = array($language, $language, $current_dedup_key);
		if ($scope_admin_id !== null) {
			$sql      .= " AND b.SalesAgent = ?";
			$params[]  = $scope_admin_id;
		}
		$this->db->query($sql, $params);
		return $this->db->affected_rows();
	}

	function Read_Distinct($col)
	{
		$allowed = array('Nationality', 'ChatLanguage');
		if(!in_array($col, $allowed, true)) {
			return array();
		}

		if($col === 'ChatLanguage') {
			$sql = "
				SELECT DISTINCT value
				FROM (
					SELECT TRIM(c.ChatLanguage) AS value
					FROM customer c
					WHERE c.ChatLanguage IS NOT NULL AND TRIM(c.ChatLanguage) != ''
					UNION
					SELECT TRIM(b.ChatLanguage) AS value
					FROM booking b
					WHERE b.ChatLanguage IS NOT NULL AND TRIM(b.ChatLanguage) != ''
						AND b.Status != 'N'
				) u
				ORDER BY value ASC
			";
		} else if($col === 'Nationality') {
			$sql = "
				SELECT Country AS value
				FROM country_code
				WHERE Status = 'Y'
					AND Country IS NOT NULL AND TRIM(Country) != ''
				ORDER BY Country ASC
			";
		} else {
			$sql = "
				SELECT DISTINCT TRIM(gl.{$col}) AS value
				FROM guest_list gl
				WHERE gl.{$col} IS NOT NULL AND TRIM(gl.{$col}) != ''
					AND gl.Status = 'Y'
				ORDER BY value ASC
			";
		}

		return $this->db->query($sql)->result();
	}

	/**
	 * Add a campaign remark for a guest (keyed by dedup_key, so it follows the
	 * person across all their bookings — same key the inline edits use). The
	 * campaign date, destination id, follow date and text are already validated
	 * /normalized by the caller ($destination_id / $follow_date may be empty →
	 * stored NULL). Returns the new RemarkID.
	 */
	function Add_Guest_Remark($dedup_key, $campaign_date, $destination_id, $remark, $follow_date, $admin_id)
	{
		$this->db->insert('guest_remarks', array(
			'dedup_key'     => (string) $dedup_key,
			'CampaignDate'  => $campaign_date,
			'DestinationID' => ((int) $destination_id > 0) ? (int) $destination_id : null,
			'Remark'        => $remark,
			'FollowDate'    => ($follow_date !== '') ? $follow_date : null,
			'Status'        => 'Y',
			'CreatedBy'     => $admin_id,
			'CreatedAt'     => date('Y-m-d H:i:s'),
		));
		return (int) $this->db->insert_id();
	}

	/**
	 * All active remarks for a guest, newest Campaign Date first, with the
	 * author's name and the chosen destination's name for display. CanDelete
	 * flags the rows the current user may remove (their own — pass
	 * $viewer_admin_id).
	 */
	function Read_Guest_Remarks($dedup_key, $viewer_admin_id = null)
	{
		$sql = "SELECT gr.RemarkID, gr.CampaignDate, gr.DestinationID, gr.FollowDate,
				gr.Remark, gr.CreatedBy, gr.CreatedAt,
				cat.Name AS DestinationName, a.Name AS CreatedByName
			FROM guest_remarks gr
			LEFT JOIN admin    a   ON a.AdminID     = gr.CreatedBy
			LEFT JOIN category cat ON cat.CategoryID = gr.DestinationID
			WHERE gr.Status = 'Y' AND gr.dedup_key = ?
			ORDER BY gr.CampaignDate DESC, gr.RemarkID DESC";
		$rows = $this->db->query($sql, array((string) $dedup_key))->result();

		foreach ($rows as $r) {
			$r->CanDelete = ($viewer_admin_id !== null && (int) $r->CreatedBy === (int) $viewer_admin_id);
		}
		return $rows;
	}

	/**
	 * Active-remark counts for a set of dedup_keys, keyed by dedup_key — used to
	 * badge the listing's Action menu without a per-row query. Returns [] when
	 * no keys are given.
	 */
	function Read_Remark_Counts($dedup_keys)
	{
		$keys = array();
		foreach ((array) $dedup_keys as $k) {
			$k = (string) $k;
			if ($k !== '' && !in_array($k, $keys, true)) { $keys[] = $k; }
		}
		if (empty($keys)) {
			return array();
		}
		$placeholders = implode(',', array_fill(0, count($keys), '?'));
		$sql = "SELECT dedup_key, COUNT(*) AS cnt
			FROM guest_remarks
			WHERE Status = 'Y' AND dedup_key IN ({$placeholders})
			GROUP BY dedup_key";
		$out = array();
		foreach ($this->db->query($sql, $keys)->result() as $row) {
			$out[$row->dedup_key] = (int) $row->cnt;
		}
		return $out;
	}

	/**
	 * Soft-delete a remark. Confined to the author (CreatedBy) so one user can't
	 * remove another's note. Returns rows affected (0 when not theirs / missing).
	 */
	function Delete_Guest_Remark($remark_id, $admin_id)
	{
		$this->db->query(
			"UPDATE guest_remarks SET Status = 'N' WHERE RemarkID = ? AND CreatedBy = ? AND Status = 'Y'",
			array((int) $remark_id, $admin_id)
		);
		return $this->db->affected_rows();
	}

	function Read_Guest_Detail($dedup_key)
	{
		$dedup    = $this->Dedup_Key_Expr();
		$scope    = "";
		$params_b = array($dedup_key);

		if(in_array($this->session->userdata('level'), array(20, 50))) {
			$scope     .= " AND b.SalesAgent = ? ";
			$params_b[] = $this->session->userdata('admin_id');
		}

		$booking_sql = "
SELECT
	b.BookingID, b.BookingNumber, b.Customer, b.Mobile AS BookingMobile,
	b.CountryCodeID, b.CustomerID, b.StartDate, b.EndDate, b.InsertDate,
	b.ChatLanguage AS BookingLanguage,
	cat.Name AS DestinationName,
	cc.CountryCode AS LeaderCountryCode,
	c.name AS LeaderMasterName,
	c.phone_number AS LeaderMasterPhone,
	c.ChatLanguage AS CustomerLanguage,
	COALESCE(
		NULLIF(RIGHT(REGEXP_REPLACE(IFNULL(b.Mobile, ''),       '[^0-9]', ''), 9), ''),
		NULLIF(RIGHT(REGEXP_REPLACE(IFNULL(c.phone_number, ''), '[^0-9]', ''), 9), '')
	) AS primary_phone_key
FROM booking b
LEFT JOIN category     cat ON cat.CategoryID    = b.Destination
LEFT JOIN customer     c   ON c.CustomerID      = b.CustomerID
LEFT JOIN country_code cc  ON cc.CountryCodeID  = b.CountryCodeID
WHERE b.Status != 'N' AND b.CancelStatus = 'N'
	AND EXISTS (
		SELECT 1 FROM guest_list gl
		WHERE gl.BookingID = b.BookingID AND gl.Status = 'Y'
			AND {$dedup} = ?
	)
	{$scope}
ORDER BY b.InsertDate DESC, b.BookingID DESC
		";

		$bookings = $this->db->query($booking_sql, $params_b)->result();
		if(empty($bookings)) {
			return array('profile' => null, 'bookings' => array());
		}

		$booking_ids = array();
		foreach($bookings as $b) { $booking_ids[] = (int)$b->BookingID; }

		$placeholders = implode(',', array_fill(0, count($booking_ids), '?'));
		$gl_sql = "
SELECT gl.GuestListID, gl.BookingID, gl.Name, gl.LastName, gl.Mobile, gl.Email,
	cn.Country AS Nationality, gl.Gender, gl.DateOfBirth,
	{$dedup} AS dedup_key
FROM guest_list gl
LEFT JOIN country_code cn ON cn.CountryCodeID = gl.Nationality
WHERE gl.Status = 'Y' AND gl.BookingID IN ({$placeholders})
ORDER BY gl.BookingID ASC, gl.GuestListID ASC
		";

		$gl_rows = $this->db->query($gl_sql, $booking_ids)->result();

		$by_booking = array();
		foreach($gl_rows as $r) {
			$by_booking[(int)$r->BookingID][] = $r;
		}

		$profile_candidate = null;
		foreach($bookings as $b) {
			$rows = isset($by_booking[(int)$b->BookingID]) ? $by_booking[(int)$b->BookingID] : array();

			$leader_row = null;
			if(!empty($b->primary_phone_key)) {
				foreach($rows as $r) {
					if($r->dedup_key === $b->primary_phone_key) { $leader_row = $r; break; }
				}
			}
			if($leader_row === null && !empty($b->Customer)) {
				$booking_name_norm = strtolower(trim($b->Customer));
				foreach($rows as $r) {
					$full = strtolower(trim(trim($r->Name) . ' ' . trim($r->LastName)));
					$first_only = strtolower(trim($r->Name));
					if($full === $booking_name_norm || $first_only === $booking_name_norm) {
						$leader_row = $r;
						break;
					}
				}
			}
			if($leader_row === null && !empty($rows)) {
				$leader_row = $rows[0];
			}

			$this_role = 'team member';
			if($leader_row !== null && $leader_row->dedup_key === $dedup_key) {
				$this_role = 'team leader';
			}

			$team_members = array();
			$current_guest_row = null;
			foreach($rows as $r) {
				if($leader_row !== null && (int)$r->GuestListID === (int)$leader_row->GuestListID) { continue; }
				$team_members[] = $r;
			}

			foreach($rows as $r) {
				if($r->dedup_key === $dedup_key) {
					$current_guest_row = $r;
					if($profile_candidate === null) { $profile_candidate = array('row' => $r, 'booking' => $b); }
					break;
				}
			}

			$b->LeaderName        = $leader_row !== null ? trim(trim($leader_row->Name) . ' ' . trim($leader_row->LastName)) : (string)$b->Customer;
			$b->LeaderMobile      = $leader_row !== null && !empty($leader_row->Mobile) ? $leader_row->Mobile : (!empty($b->BookingMobile) ? $b->BookingMobile : $b->LeaderMasterPhone);
			$b->this_role         = $this_role;
			$b->team_members      = $team_members;
			$b->current_guest_row = $current_guest_row;
		}

		$profile = null;
		if($profile_candidate !== null) {
			$r = $profile_candidate['row'];
			$b = $profile_candidate['booking'];
			$profile = (object)array(
				'Name'        => trim(trim($r->Name) . ' ' . trim($r->LastName)),
				'ContactNum' => $r->Mobile,
				'Email'       => $r->Email,
				'Language'    => !empty($b->CustomerLanguage) ? $b->CustomerLanguage : $b->BookingLanguage,
				'Nationality' => $r->Nationality,
				'Gender'      => $r->Gender,
				'DOB'         => $r->DateOfBirth,
				'dedup_key'   => $r->dedup_key,
			);
		}

		return array('profile' => $profile, 'bookings' => $bookings);
	}
}
