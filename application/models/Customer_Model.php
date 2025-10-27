<?php
class Customer_Model extends CI_Model
{
	function Read_Customer()
	{
		$this->db->select('CustomerID, Name, Phone, PrimaryEmail, SecondaryEmail, Address, CurrencyCode, Bank, BankAccount, BankHolder, SwiftCode, CustomerCode,AutocountSyncAction, AutocountSyncStatus, AutocountSyncMessage');
		$this->db->where('CustomerID', $this->input->get('customer_id'));
		return $this->db->get('customer')->row_array();
	}

	function Read_Customers1()
	{
		$this->db->select('CustomerID, Name, Phone, Status, CustomerCode,AutocountSyncAction, AutocountSyncStatus, AutocountSyncMessage');;
		if(!empty($this->input->get('name'))) {
			$this->db->where('Name', $this->input->get('name'));
		}
		if(!empty($this->input->get('phone'))) {
			$this->db->where('Phone', $this->input->get('phone'));
		}
		if(!empty($this->input->get('primary_email'))) {
			$this->db->where('PrimaryEmail', $this->input->get('primary_email'));
		}
		if(!empty($this->input->get('secondary_email'))) {
			$this->db->where('SecondaryEmail', $this->input->get('secondary_email'));
		}
		if(!empty($this->input->get('address'))) {
			$this->db->where('Address', $this->input->get('address'));
		}
		if(!empty($this->input->get('currency_code'))) {
			$this->db->where('CurrencyCode', $this->input->get('currency_code'));
		}
		if(!empty($this->input->get('bank'))) {
			$this->db->where('Bank', $this->input->get('bank'));
		}
		if(!empty($this->input->get('bank_account'))) {
			$this->db->where('BankAccount', $this->input->get('bank_account'));
		}
		if(!empty($this->input->get('bank_holder'))) {
			$this->db->where('BankHolder', $this->input->get('bank_holder'));
		}
		if(!empty($this->input->get('swift_code'))) {
			$this->db->where('SwiftCode', $this->input->get('swift_code'));
		}
		$this->db->where('Status', 'Y');
		$this->db->order_by('Name', 'ASC');
		return $this->db->get('customer')->result();
	}

	function Read_Customers2()
	{
		$this->db->select('Name, Phone, PrimaryEmail, SecondaryEmail, Address, CurrencyCode, Bank, BankAccount, BankHolder, SwiftCode, CustomerCode, AutocountSyncAction, AutocountSyncStatus, AutocountSyncMessage');
		if(!empty($this->input->get('name'))) {
			$this->db->where('Name', $this->input->get('name'));
		}
		if(!empty($this->input->get('phone'))) {
			$this->db->where('Phone', $this->input->get('phone'));
		}
		if(!empty($this->input->get('primary_email'))) {
			$this->db->where('PrimaryEmail', $this->input->get('primary_email'));
		}
		if(!empty($this->input->get('secondary_email'))) {
			$this->db->where('SecondaryEmail', $this->input->get('secondary_email'));
		}
		if(!empty($this->input->get('address'))) {
			$this->db->where('Address', $this->input->get('address'));
		}
		if(!empty($this->input->get('currency_code'))) {
			$this->db->where('CurrencyCode', $this->input->get('currency_code'));
		}
		if(!empty($this->input->get('bank'))) {
			$this->db->where('Bank', $this->input->get('bank'));
		}
		if(!empty($this->input->get('bank_account'))) {
			$this->db->where('BankAccount', $this->input->get('bank_account'));
		}
		if(!empty($this->input->get('bank_holder'))) {
			$this->db->where('BankHolder', $this->input->get('bank_holder'));
		}
		if(!empty($this->input->get('swift_code'))) {
			$this->db->where('SwiftCode', $this->input->get('swift_code'));
		}
		$this->db->where('Status', 'Y');
		$this->db->order_by('Name', 'ASC');
		return $this->db->get('customer')->result();
	}
	
	function Create()
	{
		$this->db->insert_batch('customer', json_decode(json_encode($this->input->post('customer'))));
	}

	function Update()
	{
		$this->db->update_batch('customer', json_decode(json_encode($this->input->post('customer'))), 'CustomerID');

		$this->db->set('Phone', null);
		$this->db->where('CustomerID', $this->input->post('customer_id'));
		$this->db->where('Phone', '');
		$this->db->update('customer');

		$this->db->set('PrimaryEmail', null);
		$this->db->where('CustomerID', $this->input->post('customer_id'));
		$this->db->where('PrimaryEmail', '');
		$this->db->update('customer');

		$this->db->set('SecondaryEmail', null);
		$this->db->where('CustomerID', $this->input->post('customer_id'));
		$this->db->where('SecondaryEmail', '');
		$this->db->update('customer');

		$this->db->set('Address', null);
		$this->db->where('CustomerID', $this->input->post('customer_id'));
		$this->db->where('Address', '');
		$this->db->update('customer');

		$this->db->set('Bank', null);
		$this->db->where('CustomerID', $this->input->post('customer_id'));
		$this->db->where('Bank', '');
		$this->db->update('customer');

		$this->db->set('BankAccount', null);
		$this->db->where('CustomerID', $this->input->post('customer_id'));
		$this->db->where('BankAccount', '');
		$this->db->update('customer');

		$this->db->set('BankHolder', null);
		$this->db->where('CustomerID', $this->input->post('customer_id'));
		$this->db->where('BankHolder', '');
		$this->db->update('customer');
		
		$this->db->set('SwiftCode', null);
		$this->db->where('CustomerID', $this->input->post('customer_id'));
		$this->db->where('SwiftCode', '');
		$this->db->update('customer');
	}

	function Detect()
	{
		$this->db->where('Name', $this->input->post('name'));
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

		if (!empty($config['customer_cutoff_date'])) {
			$date = date('Y-m-d', strtotime($config['customer_cutoff_date']));
			$this->db->where('customer.InsertDate >', $date);
		}

		$this->db->limit($customer_qty_cront);

		return $this->db->get()->result_array();
	}


}