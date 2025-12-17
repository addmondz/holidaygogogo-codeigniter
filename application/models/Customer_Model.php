<?php
class Customer_Model extends CI_Model
{
	function Read_Customer()
	{
		$this->db->select('*');
		$this->db->where('CustomerID', $this->input->get('customer_id'));
		return $this->db->get('customer')->row_array();
	}

	function Read_Customers1()
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
		$this->db->order_by('name', 'ASC');
		return $this->db->get('customer')->result();
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

		// --- NEW, CLEARER QUERY START ---
		// This query uses a nested EXISTS, which is easier to read
		// and just as performant.
		$subquery = "EXISTS (
			SELECT 1 
			FROM booking b
			WHERE b.CustomerID = customer.CustomerID 
			AND EXISTS (
				SELECT 1 
				FROM payment p
				WHERE p.BookingID = b.BookingID
			)
		)";
		
		// Pass the whole string to where()
		$this->db->where($subquery, null, false); 

		// --- NEW, CLEARER QUERY END ---

		if (!empty($config['customer_cutoff_date'])) {
			$date = date('Y-m-d', strtotime($config['customer_cutoff_date']));
			$this->db->where('customer.created_at >', $date);
		}

		$this->db->limit($customer_qty_cront);

		return $this->db->get()->result_array();
	}


}