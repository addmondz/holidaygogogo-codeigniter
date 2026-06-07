<?php
class Guests_Model extends CI_Model
{
	private function Dedup_Key_Expr()
	{
		return "gl.dedup_key";
	}

	private function Ghl_Dedup_Key_Expr()
	{
		return "(COALESCE(gc.dedup_key, CONCAT('ghl:', gc.id)) COLLATE utf8mb4_unicode_ci)";
	}

	private function Build_Branches()
	{
		$dedup     = $this->Dedup_Key_Expr();
		$gc_dedup  = $this->Ghl_Dedup_Key_Expr();

		$type      = $this->input->get('type');
		$q_raw     = trim((string)$this->input->get('q'));
		$has_q     = $q_raw !== '';
		$like      = $has_q ? '%' . $q_raw . '%' : null;

		$this->load->helper('guest_contact');

		// Guest Role = Lead selects GHL leads only, so it drops the booking
		// branch (mirror of the GHL suppression below).
		$run_bookings = ($type !== 'ghl')
			&& !guest_list_bookings_suppressed_by_filters($this->input->get());
		// GHL leads carry no booking-only attributes (sales agent, source,
		// travel dates, destination, pax, etc.), so any such filter drops them
		// from the listing.
		$run_ghl      = ($type !== 'guest')
			&& !guest_list_ghl_suppressed_by_filters($this->input->get());

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

			$booking_number = trim((string)$this->input->get('booking_number'));
			if($booking_number !== '') {
				$where     .= " AND b.BookingNumber LIKE ? ";
				$b_params[] = '%' . $booking_number . '%';
			}
			$contact_number = trim((string)$this->input->get('contact_number'));
			if($contact_number !== '') {
				$where     .= " AND gl.Mobile LIKE ? ";
				$b_params[] = '%' . $contact_number . '%';
			}
			$email = trim((string)$this->input->get('email'));
			if($email !== '') {
				$where     .= " AND gl.Email LIKE ? ";
				$b_params[] = '%' . $email . '%';
			}
			if(!empty($this->input->get('destination'))) {
				$where     .= " AND b.Destination = ? ";
				$b_params[] = $this->input->get('destination');
			}
			if(!empty($this->input->get('sales_agent'))) {
				$where     .= " AND b.SalesAgent = ? ";
				$b_params[] = $this->input->get('sales_agent');
			}
			if(!empty($this->input->get('source'))) {
				$where     .= " AND b.Source = ? ";
				$b_params[] = $this->input->get('source');
			}
			if(!empty($this->input->get('customer_type'))) {
				$where     .= " AND c.customer_type = ? ";
				$b_params[] = $this->input->get('customer_type');
			}
			if(!empty($this->input->get('nationality'))) {
				$where     .= " AND cn.Country = ? ";
				$b_params[] = $this->input->get('nationality');
			}
			if(!empty($this->input->get('gender'))) {
				$where     .= " AND gl.Gender = ? ";
				$b_params[] = $this->input->get('gender');
			}
			if(!empty($this->input->get('language'))) {
				$where     .= " AND COALESCE(c.ChatLanguage, b.ChatLanguage) = ? ";
				$b_params[] = $this->input->get('language');
			}
			if($has_q) {
				$where     .= " AND ( gl.Name LIKE ? OR gl.LastName LIKE ? OR CONCAT_WS(' ', gl.Name, gl.LastName) LIKE ? ) ";
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
	LEFT JOIN category     cat ON cat.CategoryID  = b.Destination
	{$where}
			";

			// Guest Role and Num of Pax are per-guest aggregates, so they filter
			// the GROUP BY result via HAVING (not the row-level WHERE). The
			// expressions below reuse columns produced by the windowed inner
			// subquery (IsLeader, BookingPax, booking_rn) so Read and Count agree.
			$having        = array();
			$having_params = array();

			$role = trim((string)$this->input->get('role'));
			if($role === 'Team Leader')     { $having[] = "MAX(IsLeader) = 1"; }
			elseif($role === 'Team Member') { $having[] = "MAX(IsLeader) = 0"; }

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

			$from_joins_where = "
FROM ghl_contacts gc
LEFT JOIN (
	SELECT DISTINCT {$dedup} AS dk
	FROM booking b
	STRAIGHT_JOIN guest_list gl ON gl.BookingID = b.BookingID AND gl.Status = 'Y'
	WHERE b.Status != 'N' AND b.CancelStatus = 'N'
) bg_keys ON bg_keys.dk = {$gc_dedup}
WHERE bg_keys.dk IS NULL
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
		TRIM(CONCAT_WS(' ', NULLIF(gl.Name, ''), NULLIF(gl.LastName, ''))) AS display_name,
		gl.Mobile        AS ContactNum,
		gl.Email,
		COALESCE(c.ChatLanguage, b.ChatLanguage) AS ChatLanguage,
		a.Name           AS SalesAgentName,
		s.Name           AS SourceName,
		c.customer_type  AS customer_type,
		cat.Name         AS Destination,
		cn.Country       AS Nationality,
		gl.Gender,
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

	private function Build_Merged_Sql_And_Params()
	{
		list($booking, $ghl) = $this->Build_Branches();

		$parts  = array();
		$params = array();

		if($booking !== null) {
			$dedup = $booking['dedup'];
			$parts[] = "
SELECT
	dedup_key,
	CONVERT(MAX(CASE WHEN rn = 1 THEN display_name   END) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Name,
	CONVERT(MAX(CASE WHEN rn = 1 THEN ContactNum     END) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS ContactNum,
	CONVERT(MAX(CASE WHEN rn = 1 THEN Email          END) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Email,
	CONVERT(MAX(CASE WHEN rn = 1 THEN ChatLanguage   END) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Language,
	CONVERT(MAX(CASE WHEN rn = 1 THEN SalesAgentName END) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS AgentName,
	CONVERT(MAX(CASE WHEN rn = 1 THEN SourceName     END) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Source,
	CONVERT(MAX(CASE WHEN rn = 1 THEN customer_type  END) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS CustomerType,
	CONVERT(MAX(CASE WHEN rn = 1 THEN Destination    END) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Destination,
	CONVERT(MAX(CASE WHEN rn = 1 THEN Nationality    END) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Nationality,
	CONVERT(MAX(CASE WHEN rn = 1 THEN Gender         END) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Gender,
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
	CONVERT(TRIM(CONCAT_WS(' ', NULLIF(gc.first_name, ''), NULLIF(gc.last_name, ''))) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Name,
	CONVERT(gc.phone USING utf8mb4) COLLATE utf8mb4_unicode_ci AS ContactNum,
	CONVERT(gc.email USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Email,
	NULL AS Language,
	NULL AS AgentName,
	NULL AS Source,
	NULL AS CustomerType,
	NULL AS Destination,
	NULL AS Nationality,
	NULL AS Gender,
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
	 * Overwrite Mobile on every active guest_list row that shares
	 * $current_dedup_key (this person's records across bookings), keeping them
	 * grouped under the new key. When $scope_admin_id is set (levels 20/50) the
	 * update is confined to that agent's own bookings. Returns affected rows.
	 */
	function Update_Guest_Contact($current_dedup_key, $new_mobile, $admin_id, $scope_admin_id = null)
	{
		$sql = "UPDATE guest_list gl
			JOIN booking b ON b.BookingID = gl.BookingID
			SET gl.Mobile = ?, gl.UpdateBy = ?, gl.UpdateDate = ?
			WHERE gl.Status = 'Y' AND b.Status != 'N' AND b.CancelStatus = 'N'
				AND gl.dedup_key = ?";
		$params = array($new_mobile, $admin_id, date('Y-m-d H:i:s'), $current_dedup_key);

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
