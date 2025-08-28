<?php
class Country_Code_Model extends CI_Model
{
	function Read_Country_Code()
	{
		$this->db->select('CountryCodeID, Country, CountryCode, CurrencyCode');
		$this->db->where('CountryCodeID', $this->input->get('country_code_id'));
		return $this->db->get('country_code')->row_array();
	}

	function Read_Country_Codes()
	{
		$this->db->select('CountryCodeID, Country, CountryCode, CurrencyCode, Status');
		if(!empty($this->input->get('country'))) {
			$this->db->where('Country', $this->input->get('country'));
		}
		if(!empty($this->input->get('country_code'))) {
			$this->db->where('CountryCode', $this->input->get('country_code'));
		}
		if(!empty($this->input->get('currency_code'))) {
			$this->db->where('CurrencyCode', $this->input->get('currency_code'));
		}
		$this->db->where('Status', 'Y');
		$this->db->order_by('Country', 'ASC');
		return $this->db->get('country_code')->result();
	}
	
	function Create()
	{
		$this->db->insert_batch('country_code', json_decode(json_encode($this->input->post('country_code'))));
	}
	
	function Update()
	{
		$this->db->update_batch('country_code', json_decode(json_encode($this->input->post('country_code'))), 'CountryCodeID');
	}

	function Detect()
	{
		$this->db->where('Country', $this->input->post('country'));
		if($this->db->get('country_code')->row()) {
			return true;
		} else {
			return false;
		}
	}
}