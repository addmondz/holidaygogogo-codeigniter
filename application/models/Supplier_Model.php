<?php
class Supplier_Model extends CI_Model
{
	function Read_Supplier()
	{
		$this->db->select('SupplierID, Name, Phone, PrimaryEmail, SecondaryEmail, Address, CurrencyCode, Bank, BankAccount, BankHolder, SwiftCode');
		$this->db->where('SupplierID', $this->input->get('supplier_id'));
		return $this->db->get('supplier')->row_array();
	}

	function Read_Suppliers1()
	{
		$this->db->select('SupplierID, Name, Phone, Status');
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
		return $this->db->get('supplier')->result();
	}

	function Read_Suppliers2()
	{
		$this->db->select('Name, Phone, PrimaryEmail, SecondaryEmail, Address, CurrencyCode, Bank, BankAccount, BankHolder, SwiftCode');
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
		return $this->db->get('supplier')->result();
	}
	
	function Create()
	{
		$this->db->insert_batch('supplier', json_decode(json_encode($this->input->post('supplier'))));
	}

	function Update()
	{
		$this->db->update_batch('supplier', json_decode(json_encode($this->input->post('supplier'))), 'SupplierID');

		$this->db->set('Phone', null);
		$this->db->where('SupplierID', $this->input->post('supplier_id'));
		$this->db->where('Phone', '');
		$this->db->update('supplier');

		$this->db->set('PrimaryEmail', null);
		$this->db->where('SupplierID', $this->input->post('supplier_id'));
		$this->db->where('PrimaryEmail', '');
		$this->db->update('supplier');

		$this->db->set('SecondaryEmail', null);
		$this->db->where('SupplierID', $this->input->post('supplier_id'));
		$this->db->where('SecondaryEmail', '');
		$this->db->update('supplier');

		$this->db->set('Address', null);
		$this->db->where('SupplierID', $this->input->post('supplier_id'));
		$this->db->where('Address', '');
		$this->db->update('supplier');

		$this->db->set('Bank', null);
		$this->db->where('SupplierID', $this->input->post('supplier_id'));
		$this->db->where('Bank', '');
		$this->db->update('supplier');

		$this->db->set('BankAccount', null);
		$this->db->where('SupplierID', $this->input->post('supplier_id'));
		$this->db->where('BankAccount', '');
		$this->db->update('supplier');

		$this->db->set('BankHolder', null);
		$this->db->where('SupplierID', $this->input->post('supplier_id'));
		$this->db->where('BankHolder', '');
		$this->db->update('supplier');
		
		$this->db->set('SwiftCode', null);
		$this->db->where('SupplierID', $this->input->post('supplier_id'));
		$this->db->where('SwiftCode', '');
		$this->db->update('supplier');
	}

	function Detect()
	{
		$this->db->where('Name', $this->input->post('name'));
		if($this->db->get('supplier')->row()) {
			return true;
		} else {
			return false;
		}
	}
}