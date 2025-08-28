<?php
class Company_Model extends CI_Model
{
	function Read()
	{
		$this->db->select('CompanyID, Name, RegistrationNumber, LicenseNumber, Address, Website');
		$this->db->where('Status', 'Y');
		$this->db->limit(1);
		$company = $this->db->get('company');
		if($company->num_rows() == 1) {
			return $company->row_array();
		} else {
			return false;
		}
	}
	
	function Update()
	{
		$this->db->update_batch('company', json_decode(json_encode($this->input->post('company'))), 'CompanyID');
		$this->db->limit(1);
		if($this->db->affected_rows() == 1) {
			$this->db->insert_batch('company_log', json_decode(json_encode($this->input->post('company_log'))));
			return true;
		} else {
			return false;
		}
	}
}