<?php
class Customer_Model extends CI_Model
{
	function Read_Customer()
	{
		$this->db->select('*');
		$this->db->where('CustomerID', $this->input->get('customer_id'));
		return $this->db->get('customer')->row_array();
	}

	function Read_Customers1_back()
	{
		$this->db->select('*');
		if(!empty($this->input->get('name'))) {
			$this->db->where('name', $this->input->get('name'));
		}
		if(!empty($this->input->get('phone_number'))) {
			$this->db->where('phone_number', $this->input->get('phone_number'));
		}
	
		if (!empty($this->input->get('CustomerCode'))) {
			$this->db->where('CustomerCode', $this->input->get('CustomerCode'));
		}

		if (!empty($this->input->get('ChatLanguage'))) {
			$this->db->where('ChatLanguage', $this->input->get('ChatLanguage'));
		}

		if (!empty($this->input->get('AutocountSyncAction'))) {
			$this->db->where('AutocountSyncAction', $this->input->get('AutocountSyncAction'));
		}

		if (!empty($this->input->get('AutocountSyncStatus'))) {
			$this->db->where('AutocountSyncStatus', $this->input->get('AutocountSyncStatus'));
		}

		if (!empty($this->input->get('AutocountSyncMessage'))) {
			$this->db->like('AutocountSyncMessage', $this->input->get('AutocountSyncMessage'));
		}

		if (!empty($this->input->get('created_at'))) {
			$this->db->where('DATE(created_at)', $this->input->get('created_at'));
		}

		if (!empty($this->input->get('updated_at'))) {
			$this->db->where('DATE(updated_at)', $this->input->get('updated_at'));
		}

		$this->db->where('Status', 'Y');
		$this->db->where('name IS NOT NULL');
		$this->db->where('phone_number IS NOT NULL');
		$this->db->where(
			"created_at NOT BETWEEN '2025-12-17 22:58:00' AND '2025-12-17 22:59:59'",
			null,
			false
		);
		$this->db->order_by('name', 'ASC');
		return $this->db->get('customer')->result();
	}

	function Read_Customers1($limit, $offset)
	{
		$this->db->select('*');

		// TRIM() both sides so leading/trailing whitespace on either the
		// stored name or the user's input does not hide a row.
		customer_name_apply_trim_like($this->db, 'name', $this->input->get('name'), 'after');

		if ($this->input->get('phone_number')) {
			$this->db->like('phone_number', $this->input->get('phone_number'), 'after');
		}

		if ($this->input->get('CustomerCode')) {
			$this->db->like('CustomerCode', $this->input->get('CustomerCode'), 'after');
		}

		if ($this->input->get('ChatLanguage')) {
			$this->db->where('ChatLanguage', $this->input->get('ChatLanguage'));
		}

		if ($this->input->get('autocount_status')) {
			$this->db->where('AutocountSyncStatus', $this->input->get('autocount_status'));
		}

		if ($this->input->get('customer_type')) {
			$this->db->where('customer_type', $this->input->get('customer_type'));
		}

		if ($this->input->get('created_date')) {
			$dates = explode(' - ', $this->input->get('created_date'));
			if (count($dates) == 2) {
				$start_date = date('Y-m-d', strtotime(str_replace('/', '-', $dates[0])));
				$end_date = date('Y-m-d', strtotime(str_replace('/', '-', $dates[1])));
				$this->db->where('DATE(created_at) >=', $start_date);
				$this->db->where('DATE(created_at) <=', $end_date);
			}
		}

		$this->db->where('Status', 'Y');
		$this->db->where('name IS NOT NULL', null, false);
		$this->db->where('phone_number IS NOT NULL', null, false);

		// 🚫 Exclude bad batch (still OK for now)
		// $this->db->where(
		// 	"created_at NOT BETWEEN '2025-12-17 22:58:00' AND '2025-12-17 22:59:59'",
		// 	null,
		// 	false
		// );

		$this->db->order_by('name', 'ASC');
		$this->db->limit($limit, $offset);

		return $this->db->get('customer')->result();
	}

	function Read_Customers_For_Export()
	{
		$this->db->select('*');

		customer_name_apply_trim_like($this->db, 'name', $this->input->get('name'), 'after');

		if ($this->input->get('phone_number')) {
			$this->db->like('phone_number', $this->input->get('phone_number'), 'after');
		}

		if ($this->input->get('CustomerCode')) {
			$this->db->like('CustomerCode', $this->input->get('CustomerCode'), 'after');
		}

		if ($this->input->get('ChatLanguage')) {
			$this->db->where('ChatLanguage', $this->input->get('ChatLanguage'));
		}

		if ($this->input->get('autocount_status')) {
			$this->db->where('AutocountSyncStatus', $this->input->get('autocount_status'));
		}

		if ($this->input->get('customer_type')) {
			$this->db->where('customer_type', $this->input->get('customer_type'));
		}

		if ($this->input->get('created_date')) {
			$dates = explode(' - ', $this->input->get('created_date'));
			if (count($dates) == 2) {
				$start_date = date('Y-m-d', strtotime(str_replace('/', '-', $dates[0])));
				$end_date = date('Y-m-d', strtotime(str_replace('/', '-', $dates[1])));
				$this->db->where('DATE(created_at) >=', $start_date);
				$this->db->where('DATE(created_at) <=', $end_date);
			}
		}

		$this->db->where('Status', 'Y');
		$this->db->where('name IS NOT NULL', null, false);
		$this->db->where('phone_number IS NOT NULL', null, false);

		$this->db->order_by('name', 'ASC');

		return $this->db->get('customer')->result();
	}

	function Count_Customers()
	{
		customer_name_apply_trim_like($this->db, 'name', $this->input->get('name'), 'after');

		if ($this->input->get('phone_number')) {
			$this->db->like('phone_number', $this->input->get('phone_number'), 'after');
		}

		if ($this->input->get('CustomerCode')) {
			$this->db->like('CustomerCode', $this->input->get('CustomerCode'), 'after');
		}

		if ($this->input->get('ChatLanguage')) {
			$this->db->where('ChatLanguage', $this->input->get('ChatLanguage'));
		}

		if ($this->input->get('autocount_status')) {
			$this->db->where('AutocountSyncStatus', $this->input->get('autocount_status'));
		}

		if ($this->input->get('customer_type')) {
			$this->db->where('customer_type', $this->input->get('customer_type'));
		}

		if ($this->input->get('created_date')) {
			$dates = explode(' - ', $this->input->get('created_date'));
			if (count($dates) == 2) {
				$start_date = date('Y-m-d', strtotime(str_replace('/', '-', $dates[0])));
				$end_date = date('Y-m-d', strtotime(str_replace('/', '-', $dates[1])));
				$this->db->where('DATE(created_at) >=', $start_date);
				$this->db->where('DATE(created_at) <=', $end_date);
			}
		}

		$this->db->from('customer');
		$this->db->where('Status', 'Y');
		$this->db->where('name IS NOT NULL', null, false);
		$this->db->where('phone_number IS NOT NULL', null, false);

		return $this->db->count_all_results();
	}

	function Read_Customers2()
	{
		$this->db->select('*');
		if(!empty($this->input->get('name'))) {
			$this->db->where('name', $this->input->get('name'));
		}
		if(!empty($this->input->get('phone_number'))) {
			$this->db->where('phone_number', $this->input->get('phone_number'));
		}
	
		if (!empty($this->input->get('CustomerCode'))) {
			$this->db->where('CustomerCode', $this->input->get('CustomerCode'));
		}

		if (!empty($this->input->get('ChatLanguage'))) {
			$this->db->where('ChatLanguage', $this->input->get('ChatLanguage'));
		}

		if (!empty($this->input->get('AutocountSyncAction'))) {
			$this->db->where('AutocountSyncAction', $this->input->get('AutocountSyncAction'));
		}

		if (!empty($this->input->get('AutocountSyncStatus'))) {
			$this->db->where('AutocountSyncStatus', $this->input->get('AutocountSyncStatus'));
		}

		if (!empty($this->input->get('AutocountSyncMessage'))) {
			$this->db->like('AutocountSyncMessage', $this->input->get('AutocountSyncMessage'));
		}

		if (!empty($this->input->get('created_at'))) {
			$this->db->where('DATE(created_at)', $this->input->get('created_at'));
		}

		if (!empty($this->input->get('updated_at'))) {
			$this->db->where('DATE(updated_at)', $this->input->get('updated_at'));
		}
		
		$this->db->where('Status', 'Y');
		$this->db->order_by('name', 'ASC');
		return $this->db->get('customer')->result();
	}
	
	public function Create()
	{
		$customer = $this->input->post('customer')[0]; // this is an array

		// If it’s a JSON string (in some cases), decode it:
		if (is_string($customer)) {
			$customer = json_decode($customer, true);
		}

		if (empty($customer)) {
			return $this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => false,
					'message' => 'No customer data received.'
				]));
		}

		$data = [
			'CustomerCode'  => $customer['CustomerCode'] ? $customer['CustomerCode'] : null,
			'name'          => $customer['name'] ? $customer['name'] : null,
			// Alternate/secondary customer name, optional.
			'AltName'       => !empty($customer['AltName']) ? trim($customer['AltName']) : null,
			'phone_number'  => $customer['phone_number'] ? $customer['phone_number'] : null,
			'ChatLanguage'  => $customer['ChatLanguage'] ? $customer['ChatLanguage'] : null,
			// Identity fields synced to AutoCount (IC -> registerNo, TIN -> taxRegisterNo).
			'ic_passport_no' => !empty($customer['ic_passport_no']) ? strtoupper(trim($customer['ic_passport_no'])) : null,
			'tin_no'         => !empty($customer['tin_no']) ? strtoupper(trim($customer['tin_no'])) : null,
			// Billing address, synced to AutoCount debtor address.
			'Address'        => !empty($customer['Address']) ? trim($customer['Address']) : null,
			// Primary email, synced to AutoCount debtor emailAddress.
			'PrimaryEmail'   => !empty($customer['PrimaryEmail']) ? trim($customer['PrimaryEmail']) : null,
			// Flag for AutoCount sync so master-data customers (no booking) get
			// pushed by the cron. AutoCount auto-generates the code if blank.
			'AutocountSyncAction' => 'C',
			'AutocountSyncStatus' => 'P',
			'created_at'    => date('Y-m-d H:i:s'),
			'updated_at'    => date('Y-m-d H:i:s'),
		];

		// HARD BLOCK on duplicate phone: if an active customer already has this
		// phone (normalised, any format), refuse to create a second record and
		// return the existing match so the form can point the user to it. There
		// is no override — a phone identifies one customer.
		$matches = $this->find_active_by_phone($data['phone_number']);
		if (!empty($matches)) {
			return $this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success'   => false,
					'duplicate' => true,
					'matches'   => $matches,
					'message'   => 'A customer with this phone number already exists.',
				]));
		}

		$insert = $this->db->insert('customer', $data);

		if ($insert && $this->db->affected_rows() > 0) {
			// A new customer may already match existing guest rows by phone —
			// populate its snapshot from them (NULL if none).
			$this->Refresh_Snapshot_For_Customer($this->db->insert_id());
			return $this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => true,
					'CustomerID' => $this->db->insert_id(),
					'message' => 'Customer inserted successfully.'
				]));
		} else {
			return $this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => false,
					'message' => 'Failed to insert customer.'
				]));
		}
	}

	function Update()
	{
		$rows = json_decode(json_encode($this->input->post('customer')), true);

		// Server-side guard: only ERNIDA may change an already-set CustomerCode.
		// The read-only form field is front-end only, so re-check here before the
		// blind batch write in case a code slips into the payload.
		$this->load->helper('customer_code');
		$admin_id = $this->session->userdata('admin_id');
		if (is_array($rows)) {
			foreach ($rows as $i => $row) {
				if (!is_array($row) || !array_key_exists('CustomerCode', $row) || empty($row['CustomerID'])) {
					continue;
				}
				$current = $this->find($row['CustomerID']);
				$stored  = $current ? $current->CustomerCode : null;
				if (!customer_code_change_allowed($admin_id, $stored, $row['CustomerCode'])) {
					unset($rows[$i]['CustomerCode']); // drop the disallowed change, keep stored value
				}
				// Only the CustomerID left after stripping -> nothing to update.
				if (count($rows[$i]) <= 1) {
					unset($rows[$i]);
				}
			}
			$rows = array_values($rows);
		}

		if (empty($rows)) {
			return; // nothing left to write after the guard
		}

		$this->db->update_batch('customer', json_decode(json_encode($rows)), 'CustomerID');

		$this->db->set('phone_number', null);
		$this->db->where('CustomerID', $this->input->post('customer_id'));
		$this->db->where('phone_number', '');
		$this->db->update('customer');

		$this->db->set('AltName', null);
		$this->db->where('CustomerID', $this->input->post('customer_id'));
		$this->db->where('AltName', '');
		$this->db->update('customer');

		$this->db->set('ChatLanguage', null);
		$this->db->where('CustomerID', $this->input->post('customer_id'));
		$this->db->where('ChatLanguage', '');
		$this->db->update('customer');

		$this->db->set('CustomerCode', null);
		$this->db->where('CustomerID', $this->input->post('customer_id'));
		$this->db->where('CustomerCode', '');
		$this->db->update('customer');

		$this->db->set('AutocountSyncAction', null);
		$this->db->where('CustomerID', $this->input->post('customer_id'));
		$this->db->where('AutocountSyncAction', '');
		$this->db->update('customer');

		$this->db->set('AutocountSyncStatus', null);
		$this->db->where('CustomerID', $this->input->post('customer_id'));
		$this->db->where('AutocountSyncStatus', '');
		$this->db->update('customer');

		$this->db->set('AutocountSyncMessage', null);
		$this->db->where('CustomerID', $this->input->post('customer_id'));
		$this->db->where('AutocountSyncMessage', '');
		$this->db->update('customer');

		// phone_number may have changed -> recompute the guest snapshot for every
		// customer touched by this save (usually one).
		$touched = array();
		if (!empty($this->input->post('customer_id'))) {
			$touched[(int) $this->input->post('customer_id')] = true;
		}
		foreach ((array) $rows as $row) {
			if (is_array($row) && !empty($row['CustomerID'])) {
				$touched[(int) $row['CustomerID']] = true;
			}
		}
		foreach (array_keys($touched) as $cid) {
			$this->Refresh_Snapshot_For_Customer($cid);
		}
	}


	public function find($customer_id)
    {
        return $this->db->get_where('customer', ['CustomerID' => $customer_id])->row();
    }

    /**
     * Normalised last-9-digit phone key (customer-only duplicate detection).
     * Returns '' for a blank/digitless phone so those never match.
     */
    private function _phone_norm($phone)
    {
        $this->load->helper('customer_dedup');
        return customer_phone_dedup_key($phone);
    }

    /**
     * Active customers whose phone matches $phone by normalised last-9-digit key,
     * computed on the fly in SQL (no stored column). Used by the "possible
     * duplicate" check on the customer + booking create paths. Name is
     * deliberately NOT part of the match (real namesakes with different phones
     * are allowed). Looks at the customer table only.
     *
     * @param string   $phone
     * @param int|null $exclude_customer_id Skip this id (e.g. the row being edited).
     * @return array Rows: CustomerID, name, phone_number, CustomerCode.
     */
    public function find_active_by_phone($phone, $exclude_customer_id = null)
    {
        $key = $this->_phone_norm($phone);
        if ($key === '') {
            return array();
        }
        // Same last-9-digit normalisation as customer_phone_dedup_key(), applied
        // to the stored phone_number so any format ("0122983045" / "122983045" /
        // "+60 122983045") collapses to the same key.
        $this->db->select('CustomerID, name, phone_number, CustomerCode');
        $this->db->where('Status', 'Y');
        $this->db->where(
            "RIGHT(REGEXP_REPLACE(IFNULL(phone_number, ''), '[^0-9]', ''), 9) = " . $this->db->escape($key),
            null,
            false
        );
        if (!empty($exclude_customer_id)) {
            $this->db->where('CustomerID !=', (int) $exclude_customer_id);
        }
        $this->db->limit(5);
        return $this->db->get('customer')->result();
    }

    /**
     * On-the-fly customer creation for the booking flow with a HARD duplicate-
     * phone block: if an active customer already has this phone (normalised),
     * reuse that customer (refreshing its details from $data) instead of
     * inserting a duplicate; otherwise create a new customer with a generated
     * code. Returns the CustomerID (existing or new), or null on failure.
     *
     * @param array $data Customer fields (expects at least phone_number / name).
     * @return int|null
     */
    public function create_or_reuse_by_phone(array $data)
    {
        $existing = $this->find_active_by_phone(isset($data['phone_number']) ? $data['phone_number'] : null);
        if (!empty($existing)) {
            $id = (int) $existing[0]->CustomerID;
            // Refresh the reused customer with the latest booking-form details,
            // but never stamp create-time fields onto an existing row. Re-queue
            // it for AutoCount so the refreshed details sync.
            $update = $data;
            unset($update['created_at'], $update['AutocountSyncAction']);
            $update['AutocountSyncStatus'] = 'P';
            $this->update_by_id($id, $update);
            $this->Refresh_Snapshot_For_Customer($id);
            return $id;
        }
        return $this->create_with_generated_code($data);
    }

    /**
     * Last-9-digit phone key SQL expression on the customer table, shared by the
     * duplicate-group queries below (same normalisation as customer_phone_dedup_key).
     */
    private function _phone_key_sql($col = 'phone_number')
    {
        return "RIGHT(REGEXP_REPLACE(IFNULL($col, ''), '[^0-9]', ''), 9)";
    }

    /**
     * How many active phone-duplicate groups exist (COUNT>1 sharing a phone key).
     *
     * @param bool $only_same_name Restrict to groups whose rows all share one name.
     * @return int
     */
    public function Count_Duplicate_Phone_Groups($only_same_name = false)
    {
        $k = $this->_phone_key_sql();
        $having = $only_same_name ? 'HAVING c > 1 AND names = 1' : 'HAVING c > 1';
        $sql = "SELECT COUNT(*) AS grp_count FROM (
                    SELECT $k AS pk, COUNT(*) AS c, COUNT(DISTINCT UPPER(TRIM(name))) AS names
                    FROM customer
                    WHERE Status = 'Y' AND $k <> ''
                    GROUP BY pk $having
                ) g";
        $row = $this->db->query($sql)->row();
        return $row ? (int) $row->grp_count : 0;
    }

    /**
     * One page of phone-duplicate groups, each with its member records (name,
     * code, phone, created_at, AutoCount status, booking_count), a names_differ
     * flag and a suggested keeper. Owner-only merge tool consumes this.
     *
     * @param int  $limit
     * @param int  $offset
     * @param bool $only_same_name
     * @return array List of groups: ['pk','records','names_differ','suggested_keeper'].
     */
    public function Find_Duplicate_Phone_Groups($limit, $offset, $only_same_name = false)
    {
        $this->load->helper('customer_dedup');
        $k = $this->_phone_key_sql();

        // 1. The phone keys on this page (most-duplicated first).
        $having = $only_same_name ? 'HAVING c > 1 AND names = 1' : 'HAVING c > 1';
        $keys = $this->db->query(
            "SELECT $k AS pk, COUNT(*) AS c, COUNT(DISTINCT UPPER(TRIM(name))) AS names
             FROM customer
             WHERE Status = 'Y' AND $k <> ''
             GROUP BY pk $having
             ORDER BY c DESC, pk ASC
             LIMIT ? OFFSET ?",
            array((int) $limit, (int) $offset)
        )->result();

        if (empty($keys)) {
            return array();
        }

        // 2. All active records for those keys, with a booking count.
        $pks  = array_map(function ($r) { return $r->pk; }, $keys);
        $ph   = implode(',', array_fill(0, count($pks), '?'));
        $kc   = $this->_phone_key_sql('c.phone_number');
        $rows = $this->db->query(
            "SELECT c.CustomerID, c.name, c.CustomerCode, c.phone_number, c.created_at,
                    c.AutocountSyncStatus, $kc AS pk,
                    (SELECT COUNT(*) FROM booking b
                       WHERE b.CustomerID = c.CustomerID OR b.CustomerID2 = c.CustomerID) AS booking_count
             FROM customer c
             WHERE c.Status = 'Y' AND $kc IN ($ph)
             ORDER BY booking_count DESC, c.CustomerID ASC",
            $pks
        )->result();

        // 3. Bucket records by key, preserving the key page order.
        $by_key = array();
        foreach ($rows as $r) {
            $by_key[$r->pk][] = $r;
        }

        $groups = array();
        foreach ($keys as $key) {
            $recs = isset($by_key[$key->pk]) ? $by_key[$key->pk] : array();
            if (count($recs) < 2) {
                continue; // guard against races
            }
            $groups[] = array(
                'pk'               => $key->pk,
                'records'          => $recs,
                'names_differ'     => customer_group_names_differ($recs),
                'suggested_keeper' => customer_default_keeper_id($recs),
            );
        }
        return $groups;
    }

    /**
     * Merge duplicate customers into one keeper: re-point every booking from the
     * losers to the keeper (CustomerID, CustomerID2, denormalised CustomerCode),
     * then deactivate the losers (Status='N'). Transactional. SAFETY: every loser
     * must be active AND share the keeper's normalised phone key, so a bad POST
     * can never fuse unrelated customers. Local only — AutoCount is not touched.
     *
     * @param int   $keeper_id
     * @param array $loser_ids
     * @return array ['success'=>bool, 'message'?, 'bookings_moved'?, 'deactivated'?]
     */
    public function Merge_Customers($keeper_id, array $loser_ids, $allow_cross_phone = false)
    {
        $keeper_id = (int) $keeper_id;
        $loser_ids = array_values(array_diff(
            array_unique(array_map('intval', $loser_ids)),
            array($keeper_id, 0)
        ));
        if (!$keeper_id || empty($loser_ids)) {
            return array('success' => false, 'message' => 'Nothing to merge.');
        }

        $keeper = $this->db->get_where('customer', array('CustomerID' => $keeper_id, 'Status' => 'Y'))->row();
        if (!$keeper) {
            return array('success' => false, 'message' => 'Keeper not found or inactive.');
        }
        $key = $this->_phone_norm($keeper->phone_number);
        if ($key === '') {
            return array('success' => false, 'message' => 'Keeper has no valid phone number.');
        }

        $loser_codes = array();
        foreach ($loser_ids as $lid) {
            $l = $this->db->get_where('customer', array('CustomerID' => $lid, 'Status' => 'Y'))->row();
            if (!$l) {
                return array('success' => false, 'message' => "Customer #{$lid} not found or already inactive.");
            }
            // Same-phone safety, unless the owner explicitly opted into a manual
            // cross-phone merge (a record pulled in via "Add another record").
            if (!$allow_cross_phone && $this->_phone_norm($l->phone_number) !== $key) {
                return array('success' => false, 'message' => "Customer #{$lid} has a different phone — refusing to merge.");
            }
            if (!empty($l->CustomerCode)) {
                $loser_codes[] = $l->CustomerCode;
            }
        }

        // Capture the BEFORE-state for the undo log (inside the txn, pre-update).
        $undo = array(
            'losers'   => array_values($loser_ids),
            'cust_id'  => array(),
            'cust_id2' => array(),
            'codes'    => array(),
        );
        foreach ($this->db->select('BookingID, CustomerID')->where_in('CustomerID', $loser_ids)
                     ->get('booking')->result() as $b) {
            $undo['cust_id'][] = array('BookingID' => (int) $b->BookingID, 'old' => (int) $b->CustomerID);
        }
        foreach ($this->db->select('BookingID, CustomerID2')->where_in('CustomerID2', $loser_ids)
                     ->get('booking')->result() as $b) {
            $undo['cust_id2'][] = array('BookingID' => (int) $b->BookingID, 'old' => (int) $b->CustomerID2);
        }
        if (!empty($loser_codes) && !empty($keeper->CustomerCode)) {
            foreach ($this->db->select('BookingID, CustomerCode')->where_in('CustomerCode', $loser_codes)
                         ->get('booking')->result() as $b) {
                $undo['codes'][] = array('BookingID' => (int) $b->BookingID, 'old' => $b->CustomerCode);
            }
        }

        $this->db->trans_start();

        $this->db->where_in('CustomerID', $loser_ids)->update('booking', array('CustomerID' => $keeper_id));
        $moved = (int) $this->db->affected_rows();

        $this->db->where_in('CustomerID2', $loser_ids)->update('booking', array('CustomerID2' => $keeper_id));
        $moved += (int) $this->db->affected_rows();

        // Denormalised booking.CustomerCode: repoint any that held a loser's code.
        if (!empty($loser_codes) && !empty($keeper->CustomerCode)) {
            $this->db->where_in('CustomerCode', $loser_codes)
                ->update('booking', array('CustomerCode' => $keeper->CustomerCode));
        }

        $this->db->where_in('CustomerID', $loser_ids)
            ->update('customer', array('Status' => 'N', 'updated_at' => date('Y-m-d H:i:s')));

        // Undo log — lets the owner revert this merge (guarded). Written in-txn.
        $this->db->insert('customer_merge_log', array(
            'keeper_id'    => $keeper_id,
            'admin_id'     => (int) $this->session->userdata('admin_id') ?: null,
            'undo_payload' => json_encode($undo),
            'status'       => 'MERGED',
            'created_at'   => date('Y-m-d H:i:s'),
        ));

        $this->db->trans_complete();
        if ($this->db->trans_status() === false) {
            return array('success' => false, 'message' => 'Database error — merge rolled back.');
        }

        $this->Refresh_Snapshot_For_Customer($keeper_id);

        return array(
            'success'        => true,
            'keeper'         => $keeper_id,
            'deactivated'    => count($loser_ids),
            'bookings_moved' => $moved,
        );
    }

    /**
     * Guarded revert of a past merge (customer_merge_log row). Re-points each
     * moved booking back to its original customer ONLY IF it still points to the
     * keeper (edits made after the merge are left alone and reported as skipped),
     * reactivates the deactivated losers, and restores overwritten booking codes.
     * Transactional. Local only — AutoCount is not touched.
     *
     * @param int $merge_id
     * @return array ['success'=>bool, 'message'?, 'reverted'?, 'skipped'?, 'reactivated'?]
     */
    public function Revert_Merge($merge_id)
    {
        $merge_id = (int) $merge_id;
        $log = $this->db->get_where('customer_merge_log', array('MergeID' => $merge_id))->row();
        if (!$log) {
            return array('success' => false, 'message' => 'Merge record not found.');
        }
        if ($log->status === 'REVERTED') {
            return array('success' => false, 'message' => 'This merge has already been reverted.');
        }

        $undo   = json_decode($log->undo_payload, true);
        $keeper = (int) $log->keeper_id;
        if (!is_array($undo)) {
            return array('success' => false, 'message' => 'Undo data is unreadable.');
        }

        $reverted = 0;
        $skipped  = 0;

        $this->db->trans_start();

        // booking.CustomerID — only if it still points to the keeper.
        foreach ((isset($undo['cust_id']) ? $undo['cust_id'] : array()) as $it) {
            $this->db->where('BookingID', (int) $it['BookingID'])->where('CustomerID', $keeper)
                ->update('booking', array('CustomerID' => (int) $it['old']));
            if ($this->db->affected_rows() > 0) { $reverted++; } else { $skipped++; }
        }
        // booking.CustomerID2 — only if it still points to the keeper.
        foreach ((isset($undo['cust_id2']) ? $undo['cust_id2'] : array()) as $it) {
            $this->db->where('BookingID', (int) $it['BookingID'])->where('CustomerID2', $keeper)
                ->update('booking', array('CustomerID2' => (int) $it['old']));
            if ($this->db->affected_rows() > 0) { $reverted++; } else { $skipped++; }
        }
        // booking.CustomerCode — restore only rows still holding the keeper's code.
        $keeper_row = $this->db->get_where('customer', array('CustomerID' => $keeper))->row();
        $keeper_code = $keeper_row ? $keeper_row->CustomerCode : null;
        foreach ((isset($undo['codes']) ? $undo['codes'] : array()) as $it) {
            if ($keeper_code === null) { break; }
            $this->db->where('BookingID', (int) $it['BookingID'])->where('CustomerCode', $keeper_code)
                ->update('booking', array('CustomerCode' => $it['old']));
        }

        // Reactivate the losers that are still deactivated.
        $reactivated = 0;
        $losers = isset($undo['losers']) ? array_map('intval', $undo['losers']) : array();
        if (!empty($losers)) {
            $this->db->where_in('CustomerID', $losers)->where('Status', 'N')
                ->update('customer', array('Status' => 'Y', 'updated_at' => date('Y-m-d H:i:s')));
            $reactivated = (int) $this->db->affected_rows();
        }

        $this->db->where('MergeID', $merge_id)->update('customer_merge_log', array(
            'status'      => 'REVERTED',
            'reverted_at' => date('Y-m-d H:i:s'),
        ));

        $this->db->trans_complete();
        if ($this->db->trans_status() === false) {
            return array('success' => false, 'message' => 'Database error — revert rolled back.');
        }

        // Refresh snapshots for the keeper and every reactivated loser.
        $this->Refresh_Snapshot_For_Customer($keeper);
        foreach ($losers as $lid) {
            $this->Refresh_Snapshot_For_Customer($lid);
        }

        return array(
            'success'     => true,
            'reverted'    => $reverted,
            'skipped'     => $skipped,
            'reactivated' => $reactivated,
        );
    }

    /**
     * Recent merges for the owner's Merge History panel, newest first, each with
     * the keeper's name/code and how many records it folded in.
     *
     * @param int $limit
     * @return array
     */
    public function Recent_Merges($limit = 30)
    {
        $rows = $this->db->select('l.MergeID, l.keeper_id, l.admin_id, l.undo_payload, l.status,
                                   l.created_at, l.reverted_at,
                                   c.name AS keeper_name, c.CustomerCode AS keeper_code,
                                   a.Name AS admin_name')
            ->from('customer_merge_log l')
            ->join('customer c', 'c.CustomerID = l.keeper_id', 'left')
            ->join('admin a', 'a.AdminID = l.admin_id', 'left')
            ->order_by('l.MergeID', 'DESC')
            ->limit((int) $limit)
            ->get()->result();

        foreach ($rows as $r) {
            $p = json_decode($r->undo_payload, true);
            $r->loser_count = (is_array($p) && isset($p['losers'])) ? count($p['losers']) : 0;
            unset($r->undo_payload);
        }
        return $rows;
    }

    /**
     * Search active customers by name / CustomerCode / phone for the merge tool's
     * "Add another record" box (lets the owner pull an extra record — even one
     * with a different phone — into a merge group). Excludes given ids.
     *
     * @param string $q
     * @param array  $exclude_ids
     * @param int    $limit
     * @return array Rows: CustomerID, name, CustomerCode, phone_number, booking_count.
     */
    public function Search_Active_Customers($q, array $exclude_ids = array(), $limit = 15)
    {
        $q = trim((string) $q);
        if ($q === '') {
            return array();
        }

        $this->db->select('c.CustomerID, c.name, c.CustomerCode, c.phone_number,
            (SELECT COUNT(*) FROM booking b WHERE b.CustomerID = c.CustomerID OR b.CustomerID2 = c.CustomerID) AS booking_count', false);
        $this->db->from('customer c');
        $this->db->where('c.Status', 'Y');
        $this->db->group_start()
            ->like('c.name', $q)
            ->or_like('c.CustomerCode', $q)
            ->or_like('c.phone_number', $q)
            ->group_end();
        $exclude_ids = array_filter(array_map('intval', $exclude_ids));
        if (!empty($exclude_ids)) {
            $this->db->where_not_in('c.CustomerID', $exclude_ids);
        }
        $this->db->order_by('c.name', 'ASC');
        $this->db->limit((int) $limit);
        return $this->db->get()->result();
    }
	
    public function update_by_id($customer_id, $data = [])
    {
        if (empty($data)) return false;

        return $this->db
            ->where('CustomerID', $customer_id)
            ->update('customer', $data);
    }

    // ------------------------------------------------------------------
    // "Self guest" snapshot sync (customer.Gender/DateOfBirth/Nationality/
    // GuestType). These columns are a denormalised copy of the customer's own
    // guest_list record so the Customer List can filter/sort/enrich without
    // touching the multi-million-row guest_list table. Kept fresh in real time
    // by calling the methods below from every guest_list / customer.phone write
    // path; a full rebuild is available via the backfill migration or the
    // customer_snapshot_resync CLI command. See Guests_Model::Read_Customers_Rich.
    // ------------------------------------------------------------------

    // The customer's phone key = last 9 digits of phone_number, matching the
    // guest_list dedup_key generated column. Interpolated (no user input).
    const SNAPSHOT_KEY_EXPR = "NULLIF(RIGHT(REGEXP_REPLACE(IFNULL(c.phone_number, ''), '[^0-9]', ''), 9), '')";

    /**
     * Recompute the snapshot for EVERY customer whose phone key is one of $keys,
     * reading each key's self record (active guest_list row, lowest GuestListID)
     * in a single statement. A key with no active guest row clears that
     * customer's snapshot to NULL (LEFT JOIN), so this self-heals deletes too.
     *
     * @param string[] $keys dedup_keys (last-9-digit phone keys) to refresh.
     */
    public function Refresh_Snapshot_By_Keys(array $keys)
    {
        // Normalise: strings, no blanks, deduped.
        $keys = array_values(array_unique(array_filter(
            array_map('strval', $keys),
            function ($k) { return $k !== ''; }
        )));
        if (empty($keys)) {
            return;
        }

        $ph  = implode(',', array_fill(0, count($keys), '?'));
        $key = self::SNAPSHOT_KEY_EXPR;
        $sql = "UPDATE customer c
            LEFT JOIN (
                SELECT dedup_key, Gender, DateOfBirth, Nationality, Type FROM (
                    SELECT gl.dedup_key, gl.Gender, gl.DateOfBirth, gl.Nationality, gl.Type,
                        ROW_NUMBER() OVER (PARTITION BY gl.dedup_key ORDER BY gl.GuestListID ASC) AS rn
                    FROM guest_list gl
                    WHERE gl.Status = 'Y' AND gl.dedup_key IN ({$ph})
                ) z WHERE z.rn = 1
            ) g ON g.dedup_key = {$key}
            SET c.Gender = g.Gender, c.DateOfBirth = g.DateOfBirth,
                c.Nationality = g.Nationality, c.GuestType = g.Type
            WHERE {$key} IN ({$ph})";
        // Keys appear twice: the derived-table filter and the customer WHERE.
        $this->db->query($sql, array_merge($keys, $keys));
    }

    /**
     * Refresh the snapshot for all customers tied to a booking's guests — used
     * after a guest_list write (create/update/delete) on that booking. Reads the
     * distinct dedup_keys of the booking's guest rows (incl. just-deleted ones,
     * so their key is re-evaluated and cleared if it lost its self record).
     */
    public function Refresh_Snapshot_By_Booking($booking_id)
    {
        $booking_id = (int) $booking_id;
        if ($booking_id < 1) {
            return;
        }
        $rows = $this->db->distinct()->select('dedup_key')
            ->from('guest_list')
            ->where('BookingID', $booking_id)
            ->where('dedup_key IS NOT NULL', null, false)
            ->get()->result();
        $keys = array();
        foreach ($rows as $r) {
            if ($r->dedup_key !== null && $r->dedup_key !== '') {
                $keys[] = $r->dedup_key;
            }
        }
        $this->Refresh_Snapshot_By_Keys($keys);
    }

    /**
     * Refresh one customer's snapshot from its OWN phone — used after the
     * customer's phone_number is created/changed (which changes which guest_list
     * row is its self record). A blank/no-digit phone clears the snapshot.
     */
    /**
     * Rebuild the snapshot for ALL customers from guest_list in one statement —
     * the same logic as the backfill migration. Safety net for the real-time
     * hooks: run via the customer_snapshot_resync CLI command (optionally
     * cron'd) to self-heal any drift. Returns rows affected.
     */
    public function Rebuild_All_Snapshots()
    {
        $key = self::SNAPSHOT_KEY_EXPR;
        $sql = "UPDATE customer c
            LEFT JOIN (
                SELECT dedup_key, Gender, DateOfBirth, Nationality, Type FROM (
                    SELECT gl.dedup_key, gl.Gender, gl.DateOfBirth, gl.Nationality, gl.Type,
                        ROW_NUMBER() OVER (PARTITION BY gl.dedup_key ORDER BY gl.GuestListID ASC) AS rn
                    FROM guest_list gl
                    WHERE gl.Status = 'Y' AND gl.dedup_key IS NOT NULL
                ) z WHERE z.rn = 1
            ) g ON g.dedup_key = {$key}
            SET c.Gender = g.Gender, c.DateOfBirth = g.DateOfBirth,
                c.Nationality = g.Nationality, c.GuestType = g.Type
            WHERE {$key} IS NOT NULL";
        $this->db->query($sql);
        return $this->db->affected_rows();
    }

    public function Refresh_Snapshot_For_Customer($customer_id)
    {
        $customer_id = (int) $customer_id;
        if ($customer_id < 1) {
            return;
        }
        // Reuse SNAPSHOT_KEY_EXPR (aliased customer as c) to read this row's key.
        $row = $this->db
            ->select(self::SNAPSHOT_KEY_EXPR . ' AS k', false)
            ->from('customer c')
            ->where('c.CustomerID', $customer_id)
            ->get()->row();

        if ($row && $row->k !== null && $row->k !== '') {
            // Refresh by key — also (idempotently) refreshes any other customer
            // sharing the new key, which is correct.
            $this->Refresh_Snapshot_By_Keys(array($row->k));
        } else {
            // No usable phone -> nothing to snapshot; clear any stale values.
            $this->db->where('CustomerID', $customer_id)->update('customer', array(
                'Gender' => null, 'DateOfBirth' => null,
                'Nationality' => null, 'GuestType' => null,
            ));
        }
    }

    public function generate_customer_code($customer_name)
    {
        if (empty($customer_name)) {
            return null;
        }

        $this->load->helper('customer_code');

        // The first letter selects the debtor series. Pull EVERY code in that
        // series (local rows + codes AutoCount handed back via docNo) so we never
        // re-issue one that already exists — re-issuing is what produced the
        // `AccNo "303-T126" exists in Chart of Account` sync failures.
        $first_char = substr(trim($customer_name), 0, 1);
        if (ctype_alpha($first_char)) {
            $first_char = strtoupper($first_char);
        }

        $rows = $this->db
            ->select('CustomerCode')
            ->from('customer')
            ->where('CustomerCode IS NOT NULL', null, false)
            ->like('CustomerCode', '-' . $first_char) // matches "<prefix>-<letter>..."
            ->get()
            ->result_array();

        return next_customer_code($customer_name, array_column($rows, 'CustomerCode'));
    }

    /**
     * Whether a CustomerCode is already used by any customer row.
     */
    public function code_exists($code)
    {
        if (empty($code)) {
            return false;
        }

        return $this->db
            ->where('CustomerCode', $code)
            ->count_all_results('customer') > 0;
    }

    /**
     * Insert a customer with a freshly generated, collision-free CustomerCode.
     *
     * The uniqueness is enforced in code (no DB UNIQUE constraint): a MySQL
     * advisory lock serialises generation+insert so two concurrent requests
     * can't pick the same code, and an explicit code_exists() check is the
     * final guard before insert. generate_customer_code() already skips codes
     * it can see (local rows + AutoCount-issued); the lock closes the
     * read-then-insert window between two simultaneous callers.
     *
     * @param array $data         Customer row (CustomerCode is set/overwritten here).
     * @param int   $max_attempts Regeneration attempts before giving up.
     * @return int|null New CustomerID, or null on failure.
     */
    public function create_with_generated_code(array $data, $max_attempts = 5)
    {
        $name   = isset($data['name']) ? $data['name'] : '';
        $locked = $this->_lock_customer_code();

        try {
            for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
                $data['CustomerCode'] = $this->generate_customer_code($name);

                // Final code-level guard: skip a code that appeared since we read
                // the series (covers the unlocked fallback path too).
                if (!empty($data['CustomerCode']) && $this->code_exists($data['CustomerCode'])) {
                    continue;
                }

                if ($this->db->insert('customer', $data) && $this->db->affected_rows() > 0) {
                    return (int) $this->db->insert_id();
                }
            }

            log_message('error', 'create_with_generated_code: exhausted retries for customer "' . $name . '"');
            return null;
        } finally {
            if ($locked) {
                $this->_unlock_customer_code();
            }
        }
    }

    /**
     * Issue the NEXT free CustomerCode to an existing customer and persist it.
     *
     * Used by the AutoCount sync to self-heal a `... exists in Chart of Account`
     * rejection: the code we generated locally collides with an orphaned debtor
     * that lives in AutoCount but not in our DB. Because the failing customer
     * row already holds the colliding code, generate_customer_code() naturally
     * steps to the next number; persisting it before the retry makes each bump
     * advance further (126 -> 127 -> 128 ...) until AutoCount accepts.
     *
     * @param int    $customer_id
     * @param string $name
     * @return string|null New code, or null if the series is exhausted or the
     *                     next code would not differ from the current one.
     */
    public function bump_customer_code($customer_id, $name)
    {
        $locked = $this->_lock_customer_code();

        try {
            $current = $this->find($customer_id);
            $current_code = $current ? $current->CustomerCode : null;

            for ($attempt = 1; $attempt <= 5; $attempt++) {
                $code = $this->generate_customer_code($name);

                // Exhausted, or generation can't move past the failing code.
                if (empty($code) || $code === $current_code) {
                    return null;
                }

                // Never step onto a code another local row already holds.
                $clash = $this->db
                    ->where('CustomerCode', $code)
                    ->where('CustomerID !=', $customer_id)
                    ->count_all_results('customer');
                if ($clash > 0) {
                    // Park the customer on this code so the next generate_*()
                    // call steps past it, then try again.
                    $this->update_by_id($customer_id, ['CustomerCode' => $code]);
                    $current_code = $code;
                    continue;
                }

                $this->update_by_id($customer_id, ['CustomerCode' => $code]);
                return $code;
            }

            return null;
        } finally {
            if ($locked) {
                $this->_unlock_customer_code();
            }
        }
    }

    /**
     * Serialise CustomerCode generation across requests via a MySQL advisory
     * lock. Returns true if the lock was taken (false = proceed anyway; the
     * code_exists() check still guards correctness for the common case).
     */
    private function _lock_customer_code($timeout = 10)
    {
        try {
            $row = $this->db->query('SELECT GET_LOCK(?, ?) AS got', ['customer_code_gen', $timeout])->row();
            return $row && (int) $row->got === 1;
        } catch (Exception $e) {
            // Non-MySQL driver or no lock support — fall back to the bare check.
            return false;
        }
    }

    private function _unlock_customer_code()
    {
        try {
            $this->db->query('SELECT RELEASE_LOCK(?)', ['customer_code_gen']);
        } catch (Exception $e) {
            // best-effort release
        }
    }

	public function get_pending_sycn_customers()
	{
		$this->load->helper('autocount');
		$config = get_autocount_config();

		$customer_qty_cront = !empty($config['customer_qty_cront'])
			? (int)$config['customer_qty_cront']
			: 10;
		
		$statuses = !empty($config['customer_sync_autocount_status']) 
			? (array)$config['customer_sync_autocount_status'] 
			: ['P'];

		$this->db->from('customer');
		$this->db->where_in('AutocountSyncStatus', $statuses);
		$this->db->where('AutocountSyncAction IS NOT NULL', null, false);
		$this->db->where('name IS NOT NULL', null, false);

		// No booking requirement: master-data customers (created directly,
		// without any booking) are synced too. They are flagged on insert in
		// Customer_Model::Create(), so the status/action gates above are enough.

		if (!empty($config['customer_cutoff_date'])) {
			$date = date('Y-m-d', strtotime($config['customer_cutoff_date']));
			$this->db->where('customer.created_at >', $date);
		}

		$this->db->limit($customer_qty_cront);

		return $this->db->get()->result_array();
	}

	// ------------------------------------------------------------------
	// Bulk create via Excel (download template -> fill rows -> re-upload)
	// ------------------------------------------------------------------

	// Column order of the import/template sheet. The header labels below must
	// match Customer::Import_Template() exactly, and the 0-based index is how
	// PhpSpreadsheet's toArray(null,true,true,false) hands each row back.
	// Order mirrors the Customer dashboard columns (Alt Name, Name, Contact,
	// Email, Language, Customer Code), then the AutoCount identity fields that
	// aren't shown on the dashboard. The first four (ALT NAME, NAME, PHONE
	// NUMBER, CHAT LANGUAGE) are MANDATORY — see IMPORT_REQUIRED / Parse_Import_Rows.
	const IMPORT_COLUMNS = array(
		0 => 'ALT NAME',
		1 => 'NAME',
		2 => 'PHONE NUMBER',
		3 => 'EMAIL',
		4 => 'CHAT LANGUAGE',
		5 => 'CUSTOMER CODE',
		6 => 'IC / PASSPORT NO',
		7 => 'TIN',
		8 => 'BILLING ADDRESS',
	);

	// Columns a row must supply (non-blank + valid) or it's reported as failed.
	// Labels here are what the user sees in the "Missing/invalid: ..." message.
	const IMPORT_REQUIRED = array('Alt Name', 'Name', 'Phone Number', 'Chat Language');

	/**
	 * Turn raw uploaded sheet rows into normalized customer entries, applying the
	 * same casing rules as the single-create form (name/altname/IC/TIN uppercased;
	 * email and billing address kept as typed). Pure + DB-free so it can be
	 * unit-tested and so the controller only handles I/O.
	 *
	 * Each returned entry is:
	 *   array('line' => <1-based sheet row>, 'error' => null|string,
	 *         'CustomerCode','name','AltName','phone_number','ChatLanguage',
	 *         'ic_passport_no','tin_no','PrimaryEmail','Address')
	 * The header row and fully-blank rows are dropped. A row missing any mandatory
	 * field (Alt Name, Name, Phone Number, a valid Chat Language) is kept with
	 * error set, so the caller can report the exact line + which fields are bad.
	 *
	 * @param array $rows      0-indexed row arrays (PhpSpreadsheet toArray()).
	 * @param array $languages Allowed ChatLanguage codes (e.g. CN/EN/ML).
	 * @return array
	 */
	public static function Parse_Import_Rows($rows, $languages = array('CN', 'EN', 'ML'))
	{
		if (!is_array($rows)) {
			return array();
		}
		$allowed_lang = array();
		foreach ($languages as $l) {
			$allowed_lang[strtoupper(trim((string) $l))] = true;
		}

		$out  = array();
		$line = 0;
		foreach ($rows as $row) {
			$line++;
			if (!is_array($row)) {
				continue;
			}
			$cell = function ($i) use ($row) {
				return isset($row[$i]) ? trim((string) $row[$i]) : '';
			};

			$altname = $cell(0);
			$name    = $cell(1);
			$phone   = $cell(2);
			$email   = $cell(3);
			$lang    = strtoupper($cell(4));
			$code    = $cell(5);
			$ic      = $cell(6);
			$tin     = $cell(7);
			$addr    = $cell(8);

			// Drop the header row wherever it sits (matches the template labels).
			if (strtoupper($altname) === 'ALT NAME' && strtoupper($name) === 'NAME') {
				continue;
			}
			// Skip a fully-blank row (trailing empty rows Excel leaves behind).
			if ($altname === '' && $name === '' && $phone === '' && $email === ''
				&& $lang === '' && $code === '' && $ic === '' && $tin === '' && $addr === '') {
				continue;
			}

			// Chat Language is mandatory + must be a known code; an unknown code
			// clears the stored value AND fails the row via the missing-field check.
			$lang_ok = ($lang !== '' && isset($allowed_lang[$lang]));

			// Report every mandatory field that is blank/invalid, so the user can
			// fix them all in one pass rather than one row at a time.
			$missing = array();
			if ($altname === '') { $missing[] = 'Alt Name'; }
			if ($name === '')    { $missing[] = 'Name'; }
			if ($phone === '')   { $missing[] = 'Phone Number'; }
			if (!$lang_ok)       { $missing[] = 'Chat Language'; }

			$out[] = array(
				'line'          => $line,
				'error'         => empty($missing) ? null : ('Missing/invalid: ' . implode(', ', $missing)),
				'CustomerCode'  => $code !== '' ? strtoupper($code) : null,
				'name'          => $name !== '' ? strtoupper($name) : null,
				'AltName'       => $altname !== '' ? strtoupper($altname) : null,
				'phone_number'  => $phone !== '' ? $phone : null,
				'ChatLanguage'  => $lang_ok ? $lang : null,
				'ic_passport_no'=> $ic !== '' ? strtoupper($ic) : null,
				'tin_no'        => $tin !== '' ? strtoupper($tin) : null,
				// Email + billing address are case-sensitive; keep as typed.
				'PrimaryEmail'  => $email !== '' ? $email : null,
				'Address'       => $addr !== '' ? $addr : null,
			);
		}
		return $out;
	}

	/**
	 * Decide which uploaded import files to delete so only the newest $keep are
	 * kept as backups. Mirrors Faq_Model::Prune_Backups: recency is read from the
	 * unix stamp in the filename (customer_import_<unix>.xlsx). Pure so it can be
	 * unit tested; the controller does the unlink. Returns names to DELETE.
	 */
	public static function Prune_Import_Backups($filenames, $keep = 3)
	{
		if (!is_array($filenames)) {
			return array();
		}
		$keep = max(0, (int) $keep);
		$stamped = array();
		foreach ($filenames as $name) {
			$ts = 0;
			if (preg_match('/customer_import_(\d+)\./', (string) $name, $m)) {
				$ts = (int) $m[1];
			}
			$stamped[] = array('name' => (string) $name, 'ts' => $ts);
		}
		usort($stamped, function ($a, $b) {
			if ($a['ts'] === $b['ts']) { return 0; }
			return ($a['ts'] < $b['ts']) ? 1 : -1; // newest first
		});
		$prune = array_reverse(array_slice($stamped, $keep)); // oldest first
		$out = array();
		foreach ($prune as $entry) {
			$out[] = $entry['name'];
		}
		return $out;
	}

	/**
	 * Whether an active customer with this phone already exists, so a re-import
	 * doesn't create a duplicate master record. Detection is by PHONE only
	 * (normalised last-9-digit key), matching the single-create + booking paths:
	 * the same person typed "0122983045" / "122983045" is one customer, while a
	 * real namesake with a different phone is allowed. A blank/digitless phone
	 * never counts as a duplicate.
	 */
	public function exists_active_by_phone($phone)
	{
		return !empty($this->find_active_by_phone($phone));
	}

	/**
	 * Create one customer per parsed entry. Blank CustomerCode -> auto-generated
	 * collision-free code (create_with_generated_code); a supplied code is used
	 * as-is but skipped if already taken. Existing name+phone rows are skipped.
	 * Every created row is flagged for AutoCount sync (action C / status P), the
	 * same as the single-create path.
	 *
	 * @param array $parsed Entries from Parse_Import_Rows().
	 * @return array Summary: created, skipped_duplicate, failed (each with line + reason).
	 */
	public function Bulk_Import(array $parsed)
	{
		$summary = array(
			'created'          => array(), // ['line'=>, 'name'=>, 'code'=>]
			'skipped_duplicate'=> array(), // ['line'=>, 'name'=>]
			'failed'           => array(), // ['line'=>, 'name'=>, 'reason'=>]
		);

		foreach ($parsed as $entry) {
			$line = isset($entry['line']) ? (int) $entry['line'] : 0;
			$name = isset($entry['name']) ? $entry['name'] : null;

			if (!empty($entry['error'])) {
				$summary['failed'][] = array('line' => $line, 'name' => $name, 'reason' => $entry['error']);
				continue;
			}
			if ($this->exists_active_by_phone(isset($entry['phone_number']) ? $entry['phone_number'] : null)) {
				$summary['skipped_duplicate'][] = array('line' => $line, 'name' => $name);
				continue;
			}

			$now  = date('Y-m-d H:i:s');
			$data = array(
				'name'           => $name,
				'AltName'        => isset($entry['AltName']) ? $entry['AltName'] : null,
				'phone_number'   => isset($entry['phone_number']) ? $entry['phone_number'] : null,
				'ChatLanguage'   => isset($entry['ChatLanguage']) ? $entry['ChatLanguage'] : null,
				'ic_passport_no' => isset($entry['ic_passport_no']) ? $entry['ic_passport_no'] : null,
				'tin_no'         => isset($entry['tin_no']) ? $entry['tin_no'] : null,
				'Address'        => isset($entry['Address']) ? $entry['Address'] : null,
				'PrimaryEmail'   => isset($entry['PrimaryEmail']) ? $entry['PrimaryEmail'] : null,
				'AutocountSyncAction' => 'C',
				'AutocountSyncStatus' => 'P',
				'created_at'     => $now,
				'updated_at'     => $now,
			);

			$supplied_code = isset($entry['CustomerCode']) ? $entry['CustomerCode'] : null;
			if (!empty($supplied_code)) {
				if ($this->code_exists($supplied_code)) {
					$summary['failed'][] = array('line' => $line, 'name' => $name,
						'reason' => 'Customer code ' . $supplied_code . ' already in use');
					continue;
				}
				$data['CustomerCode'] = $supplied_code;
				$ok = $this->db->insert('customer', $data) && $this->db->affected_rows() > 0;
				if ($ok) {
					$this->Refresh_Snapshot_For_Customer($this->db->insert_id());
					$summary['created'][] = array('line' => $line, 'name' => $name, 'code' => $supplied_code);
				} else {
					$summary['failed'][] = array('line' => $line, 'name' => $name, 'reason' => 'Insert failed');
				}
			} else {
				$new_id = $this->create_with_generated_code($data);
				if ($new_id) {
					$this->Refresh_Snapshot_For_Customer($new_id);
					$row = $this->find($new_id);
					$summary['created'][] = array('line' => $line, 'name' => $name,
						'code' => $row ? $row->CustomerCode : null);
				} else {
					$summary['failed'][] = array('line' => $line, 'name' => $name, 'reason' => 'Could not generate code');
				}
			}
		}

		return $summary;
	}


}