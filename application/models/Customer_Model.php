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

		$insert = $this->db->insert('customer', $data);

		if ($insert && $this->db->affected_rows() > 0) {
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
	}


	public function find($customer_id)
    {
        return $this->db->get_where('customer', ['CustomerID' => $customer_id])->row();
    }
	
    public function update_by_id($customer_id, $data = [])
    {
        if (empty($data)) return false;

        return $this->db
            ->where('CustomerID', $customer_id)
            ->update('customer', $data);
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


}