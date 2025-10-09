<?php
class Category_Model extends CI_Model
{
	function Read_Category()
	{
		$this->db->select('CategoryID, CategoryCodeID, Name, City, State, Country, IsDestination');
		$this->db->where('CategoryID', $this->input->get('category_id'));
		return $this->db->get('category')->row_array();
	}

	function Read_Categories()
	{
		$this->db->select('CategoryID, Name, City, State, Status');
		if(!empty($this->input->get('category_code'))) {
			$this->db->where('CategoryCodeID', $this->input->get('category_code'));
		}
		if(!empty($this->input->get('name'))) {
			$this->db->where('Name', $this->input->get('name'));
		}
		if(!empty($this->input->get('city'))) {
			$this->db->where('City', $this->input->get('city'));
		}
		if(!empty($this->input->get('state'))) {
			$this->db->where('State', $this->input->get('state'));
		}
		if(!empty($this->input->get('country'))) {
			$this->db->where('Country', $this->input->get('country'));
		}
		if(!empty($this->input->get('is_destination'))) {
			$this->db->where('IsDestination', $this->input->get('is_destination'));
		}
		$this->db->where('Status', 'Y');
		$this->db->order_by('Name', 'ASC');
		return $this->db->get('category')->result();
	}

	function Read_Category_Codes()
	{
		$this->db->select('CategoryCodeID, Name');
		$this->db->where('Status', 'Y');
		$this->db->order_by('Name', 'ASC');
		return $this->db->get('category_code')->result();
	}

	function Read_Country_Codes()
	{
		$this->db->select('CountryCodeID, Country');
		$this->db->where('Status', 'Y');
		$this->db->order_by('Country', 'ASC');
		return $this->db->get('country_code')->result();
	}

	function Create()
	{
		$this->db->insert_batch('category', json_decode(json_encode($this->input->post('category'))));
	}
	
	function Update()
	{
		$this->db->update_batch('category', json_decode(json_encode($this->input->post('category'))), 'CategoryID');
	}
	
	function Detect()
	{
		$this->db->where('Name', $this->input->post('name'));
		if($this->db->get('category')->row()) {
			return true;
		} else {
			return false;
		}
	}
	function find($category_id)
    {
        return $this->db->get_where('category', ['CategoryID' => $category_id])->row();
    }
}