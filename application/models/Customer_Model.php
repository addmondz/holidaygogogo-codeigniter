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
		$this->db->update_batch('customer', json_decode(json_encode($this->input->post('customer'))), 'CustomerID');

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


	function Detect()
	{
		$this->db->where('name', $this->input->post('name'));
		if($this->db->get('customer')->row()) {
			return true;
		} else {
			return false;
		}
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

        // Get first character, uppercase if alphabetic
        $first_char = substr(trim($customer_name), 0, 1);
        if (ctype_alpha($first_char)) {
            $first_char = strtoupper($first_char);
        }

        // Start from prefix 303, increment if sequence exceeds 999
        $prefix_num = 303;
        $max_prefix = 399;

        while ($prefix_num <= $max_prefix) {
            $prefix = $prefix_num . '-' . $first_char;

            $this->db->select("MAX(CAST(SUBSTRING(CustomerCode, 6) AS UNSIGNED)) as max_seq");
            $this->db->from('customer');
            $this->db->like('CustomerCode', $prefix, 'after');
            $result = $this->db->get()->row();

            $max_seq = ($result && $result->max_seq !== null) ? (int)$result->max_seq : 0;

            if ($max_seq < 999) {
                return $prefix . sprintf('%03d', $max_seq + 1);
            }

            $prefix_num++;
        }

        return null;
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