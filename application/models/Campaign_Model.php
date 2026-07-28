<?php
class Campaign_Model extends CI_Model
{
	function Read_Campaigns($limit = null, $offset = 0)
	{
		$this->db->select("c.CampaignID, c.Name, c.CampaignDate, c.Description, c.GhlWorkflowID, c.Status, c.InsertDate, a.Name AS InsertByName,
			(SELECT COUNT(*) FROM campaign_guests cg WHERE cg.CampaignID = c.CampaignID) AS GuestCount", false);
		$this->db->from('campaign c');
		$this->db->join('admin a', 'a.AdminID = c.InsertBy', 'left');
		$this->db->where('c.Status', 'Y');

		if(!empty($this->input->get('name'))) {
			$this->db->like('c.Name', $this->input->get('name'));
		}
		if(!empty($this->input->get('date_range'))) {
			$range = explode(' - ', $this->input->get('date_range'));
			if(count($range) == 2) {
				$start = date('Y-m-d', strtotime(str_replace('/', '-', $range[0])));
				$end   = date('Y-m-d', strtotime(str_replace('/', '-', $range[1])));
				$this->db->where('c.CampaignDate >=', $start);
				$this->db->where('c.CampaignDate <=', $end);
			}
		}

		$this->db->order_by('c.CampaignDate', 'DESC');
		$this->db->order_by('c.CampaignID', 'DESC');
		if($limit !== null) {
			$this->db->limit((int)$limit, (int)$offset);
		}
		return $this->db->get()->result();
	}

	function Count_Campaigns()
	{
		$this->db->from('campaign c');
		$this->db->where('c.Status', 'Y');
		if(!empty($this->input->get('name'))) {
			$this->db->like('c.Name', $this->input->get('name'));
		}
		if(!empty($this->input->get('date_range'))) {
			$range = explode(' - ', $this->input->get('date_range'));
			if(count($range) == 2) {
				$start = date('Y-m-d', strtotime(str_replace('/', '-', $range[0])));
				$end   = date('Y-m-d', strtotime(str_replace('/', '-', $range[1])));
				$this->db->where('c.CampaignDate >=', $start);
				$this->db->where('c.CampaignDate <=', $end);
			}
		}
		return $this->db->count_all_results();
	}

	function Read_Campaign($id)
	{
		$this->db->select('CampaignID, Name, CampaignDate, Description, GhlWorkflowID, FiltersJson, Status, InsertBy, InsertDate');
		$this->db->where('CampaignID', (int)$id);
		return $this->db->get('campaign')->row();
	}

	function Read_Campaign_Guests($id)
	{
		$this->db->select('CampaignID, DedupKey, GuestName, ContactNum, Email, GuestType, InsertDate');
		$this->db->where('CampaignID', (int)$id);
		$this->db->order_by('GuestName', 'ASC');
		$this->db->order_by('DedupKey', 'ASC');
		return $this->db->get('campaign_guests')->result();
	}

	function Count_Campaign_Guests($id)
	{
		$this->db->from('campaign_guests');
		$this->db->where('CampaignID', (int)$id);
		return (int) $this->db->count_all_results();
	}

	// Stable order so a worker that resumes from CurrentOffset processes the
	// exact same row sequence as the run that paused. DedupKey is unique per
	// (CampaignID, DedupKey) so it's a deterministic tiebreaker.
	function Read_Campaign_Guests_Chunk($id, $offset, $limit)
	{
		$this->db->select('CampaignID, DedupKey, GuestName, ContactNum, Email, GuestType, InsertDate');
		$this->db->where('CampaignID', (int)$id);
		$this->db->order_by('DedupKey', 'ASC');
		$this->db->limit((int)$limit, (int)$offset);
		return $this->db->get('campaign_guests')->result();
	}

	function Create($data, $guest_rows)
	{
		$admin_id = $this->session->userdata('admin_id');
		$now      = date('Y-m-d H:i:s');

		$campaign_row = array(
			'Name'          => $data['Name'],
			'CampaignDate'  => !empty($data['CampaignDate']) ? $data['CampaignDate'] : null,
			'Description'   => isset($data['Description']) ? $data['Description'] : null,
			'GhlWorkflowID' => isset($data['GhlWorkflowID']) && $data['GhlWorkflowID'] !== '' ? $data['GhlWorkflowID'] : null,
			'FiltersJson'   => isset($data['FiltersJson']) && $data['FiltersJson'] !== '' ? $data['FiltersJson'] : null,
			'Status'        => 'Y',
			'InsertBy'      => $admin_id,
			'InsertDate'    => $now,
			'UpdateBy'      => $admin_id,
			'UpdateDate'    => $now,
		);

		$this->db->trans_start();
		$this->db->insert('campaign', $campaign_row);
		$campaign_id = (int)$this->db->insert_id();

		$pivot = $this->Build_Pivot_Rows($campaign_id, $guest_rows, $admin_id, $now);
		if(!empty($pivot)) {
			$this->db->insert_batch('campaign_guests', $pivot);
		}
		$this->db->trans_complete();

		return $this->db->trans_status() ? $campaign_id : false;
	}

	function Update($id, $data, $guest_rows)
	{
		$id       = (int)$id;
		$admin_id = $this->session->userdata('admin_id');
		$now      = date('Y-m-d H:i:s');

		$campaign_row = array(
			'Name'          => $data['Name'],
			'CampaignDate'  => !empty($data['CampaignDate']) ? $data['CampaignDate'] : null,
			'Description'   => isset($data['Description']) ? $data['Description'] : null,
			'GhlWorkflowID' => isset($data['GhlWorkflowID']) && $data['GhlWorkflowID'] !== '' ? $data['GhlWorkflowID'] : null,
			'FiltersJson'   => isset($data['FiltersJson']) && $data['FiltersJson'] !== '' ? $data['FiltersJson'] : null,
			'UpdateBy'      => $admin_id,
			'UpdateDate'    => $now,
		);

		$this->db->trans_start();
		$this->db->where('CampaignID', $id);
		$this->db->update('campaign', $campaign_row);

		$this->db->where('CampaignID', $id);
		$this->db->delete('campaign_guests');

		$pivot = $this->Build_Pivot_Rows($id, $guest_rows, $admin_id, $now);
		if(!empty($pivot)) {
			$this->db->insert_batch('campaign_guests', $pivot);
		}
		$this->db->trans_complete();

		return $this->db->trans_status();
	}

	function Delete($id)
	{
		$this->db->where('CampaignID', (int)$id);
		$this->db->update('campaign', array(
			'Status'     => 'N',
			'UpdateBy'   => $this->session->userdata('admin_id'),
			'UpdateDate' => date('Y-m-d H:i:s'),
		));
	}

	public static function Build_Pivot_Rows($campaign_id, $guest_rows, $admin_id, $now)
	{
		if(empty($guest_rows) || !is_array($guest_rows)) {
			return array();
		}
		$seen   = array();
		$out    = array();
		$valid  = array('Booking Guest', 'GHL');
		foreach($guest_rows as $key => $row) {
			$dedup = isset($row['DedupKey']) ? trim((string)$row['DedupKey']) : trim((string)$key);
			if($dedup === '') { continue; }
			if(isset($seen[$dedup])) { continue; }
			$seen[$dedup] = true;

			$type = isset($row['GuestType']) ? $row['GuestType'] : null;
			if($type !== null && !in_array($type, $valid, true)) { $type = null; }

			$out[] = array(
				'CampaignID' => (int)$campaign_id,
				'DedupKey'   => $dedup,
				'GuestName'  => isset($row['GuestName'])  ? (string)$row['GuestName']  : null,
				'ContactNum' => isset($row['ContactNum']) ? (string)$row['ContactNum'] : null,
				'Email'      => isset($row['Email'])      ? (string)$row['Email']      : null,
				'GuestType'  => $type,
				'InsertBy'   => $admin_id,
				'InsertDate' => $now,
			);
		}
		return $out;
	}

	// Sanitise the guest-picker filter snapshot posted from the form before it
	// is stored on the campaign. Keeps only whitelisted keys, coerces scalars
	// to strings and multi-selects to string arrays, and drops empties. Returns
	// null when nothing meaningful was applied (campaign_mode alone doesn't
	// count — it only rides along when a real filter is present).
	public static function Normalize_Filters($raw)
	{
		if(!is_string($raw) || trim($raw) === '') { return null; }
		$decoded = json_decode($raw, true);
		if(!is_array($decoded) || $decoded === array() || array_keys($decoded) === range(0, count($decoded) - 1)) {
			// not an associative object
			return null;
		}

		$multi = array('destination', 'source', 'customer_type', 'language',
			'gender', 'race', 'tags', 'joined_campaign');
		$scalar = array('q', 'type', 'bc_type', 'role', 'nationality', 'booking_date',
			'travel_date', 'dob', 'birthday', 'min_purchases', 'ltv',
			'booking_lead', 'family_kids', 'consecutive_years', 'cancelled',
			'has_email', 'campaign_mode');

		$out = array();
		foreach($multi as $k) {
			if(!isset($decoded[$k]) || !is_array($decoded[$k])) { continue; }
			$vals = array();
			foreach($decoded[$k] as $v) {
				if(is_array($v)) { continue; }
				$v = trim((string)$v);
				if($v !== '') { $vals[] = $v; }
			}
			if(!empty($vals)) { $out[$k] = $vals; }
		}
		foreach($scalar as $k) {
			if(!isset($decoded[$k])) { continue; }
			$v = $decoded[$k];
			if(is_array($v)) { $v = reset($v); }
			$v = trim((string)$v);
			if($v !== '') { $out[$k] = $v; }
		}

		// "Meaningful" = at least one filter other than the include/exclude toggle.
		$real = $out;
		unset($real['campaign_mode']);
		if(empty($real)) { return null; }

		return $out;
	}

	// Turn a stored filter snapshot into an ordered list of human-readable
	// {label, values[]} rows for the View page. $lookups resolves the id-based
	// filters: ['destination'=>[id=>name], 'source'=>[id=>name],
	// 'joined_campaign'=>[id=>name]]. Accepts a JSON string or a decoded array.
	public static function Describe_Filters($filters, $lookups = array())
	{
		if(is_string($filters)) {
			$filters = ($filters === '') ? array() : json_decode($filters, true);
		}
		if(!is_array($filters) || empty($filters)) { return array(); }
		if(!is_array($lookups)) { $lookups = array(); }

		$labels = array(
			'q'                 => 'Name contains',
			'type'              => 'Guest Type',
			'bc_type'           => 'Booking Type',
			'role'              => 'Role',
			'nationality'       => 'Nationality',
			'destination'       => 'Destination',
			'source'            => 'Source',
			'customer_type'     => 'Customer Type',
			'language'          => 'Language',
			'gender'            => 'Gender',
			'race'              => 'Race',
			'tags'              => 'Tag',
			'birthday'          => 'Birthday',
			'booking_date'      => 'Date of Bookings',
			'travel_date'       => 'Travel Date',
			'dob'               => 'Date of Birth',
			'min_purchases'     => 'Purchase Count',
			'ltv'               => 'Lifetime Booking Value',
			'booking_lead'      => 'Booking-to-Travel Lead',
			'family_kids'       => 'Family with kids',
			'consecutive_years' => 'Purchased 2 consecutive years+',
			'cancelled'         => 'Has cancelled BC',
			'has_email'         => 'Has email address',
			'joined_campaign'   => 'Campaign',
		);
		$id_lookups = array('destination', 'source', 'joined_campaign');
		$flags      = array('family_kids', 'consecutive_years', 'cancelled', 'has_email');
		$months     = array(1 => 'January', 'February', 'March', 'April', 'May',
			'June', 'July', 'August', 'September', 'October', 'November', 'December');
		$type_map     = array('guest' => 'Booking Guest', 'ghl' => 'GHL', 'customer' => 'Customer');
		$bc_map       = array(
			'BOOKING CONFIRMATION' => 'Booking Confirmation (BC)',
			'PROFORMA INVOICE'     => 'Proforma Invoice (PI)',
			'QUOTATION'            => 'Quotation (QU)',
		);
		$ltv_map      = array(
			'0-10000'       => 'Below RM10k',
			'10000-20000'   => "RM10k \xe2\x80\x93 20k",
			'20000-30000'   => "RM20k \xe2\x80\x93 30k",
			'30000-50000'   => "RM30k \xe2\x80\x93 50k",
			'50000-100000'  => "RM50k \xe2\x80\x93 100k",
			'100000+'       => 'RM100k and above',
		);
		$lead_map     = array(
			'0-1' => 'Within 1 month',
			'1-2' => "1 \xe2\x80\x93 2 months",
			'2-3' => "2 \xe2\x80\x93 3 months",
			'3-6' => "3 \xe2\x80\x93 6 months",
			'6+'  => '6 months and above',
		);

		$rows = array();
		foreach($labels as $key => $label) {
			if(!isset($filters[$key])) { continue; }
			$raw = $filters[$key];

			// Segment checkboxes only appear when actually ticked.
			if(in_array($key, $flags, true)) {
				if((string)$raw === '1') { $rows[] = array('label' => $label, 'values' => array('Yes')); }
				continue;
			}

			$vals = is_array($raw) ? $raw : array($raw);
			$out  = array();
			foreach($vals as $v) {
				if(is_array($v)) { continue; }
				$v = trim((string)$v);
				if($v === '') { continue; }

				if(in_array($key, $id_lookups, true)) {
					$map = isset($lookups[$key]) && is_array($lookups[$key]) ? $lookups[$key] : array();
					$out[] = isset($map[$v]) ? (string)$map[$v] : $v;
				} elseif($key === 'type') {
					$out[] = isset($type_map[$v]) ? $type_map[$v] : $v;
				} elseif($key === 'bc_type') {
					$out[] = isset($bc_map[$v]) ? $bc_map[$v] : $v;
				} elseif($key === 'min_purchases') {
					$out[] = $v . "\xc3\x97 and above";
				} elseif($key === 'ltv') {
					$out[] = isset($ltv_map[$v]) ? $ltv_map[$v] : $v;
				} elseif($key === 'booking_lead') {
					$out[] = isset($lead_map[$v]) ? $lead_map[$v] : $v;
				} elseif($key === 'birthday') {
					if($v === 'today')          { $out[] = 'Today'; }
					elseif($v === 'this_month') { $out[] = 'This Month'; }
					elseif(isset($months[(int)$v])) { $out[] = $months[(int)$v]; }
					else { $out[] = $v; }
				} else {
					$out[] = $v;
				}
			}
			if(empty($out)) { continue; }

			// The include/exclude toggle rides on the Campaign row's label.
			if($key === 'joined_campaign') {
				$mode  = isset($filters['campaign_mode']) ? strtolower((string)$filters['campaign_mode']) : 'include';
				$label = $label . ($mode === 'exclude' ? ' (Exclude)' : ' (Include)');
			}
			$rows[] = array('label' => $label, 'values' => $out);
		}
		return $rows;
	}
}
