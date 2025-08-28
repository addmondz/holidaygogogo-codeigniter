<?php
class Universal_Model extends CI_Model
{
	function Read_Country($country_code_id)
	{
		$this->db->select('Country');
		$this->db->where('CountryCodeID', $country_code_id);
		return $this->db->get('country_code')->row()->Country;
	}
	
	function Read_Country_Code($country_code_id)
	{
		$this->db->select('CountryCode');
		$this->db->where('CountryCodeID', $country_code_id);
		return $this->db->get('country_code')->row()->CountryCode;
	}

	function Delete($column, $value, $table)
	{
		$array = array(
			'Status' => 'N',
			'UpdateBy' => $this->session->userdata('admin_id'),
			'UpdateDate' => date('Y-m-d H:i:s')
		);
		$this->db->where($column, $value);
		$this->db->update($table, $array);
	}
	
	function Validate_Id($column, $value, $table)
	{
		$this->db->where($column, $value);
		$this->db->where('Status !=', 'N');
		if($this->db->get($table)->row()) {
			return true;
		} else {
			return false;
		}
	}
}