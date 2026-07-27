<?php
class Customer_Type_Model extends CI_Model
{
	function Read_Customer_Type()
	{
		$this->db->select('CustomerTypeID, Name');
		$this->db->where('CustomerTypeID', $this->input->get('customer_type_id'));
		return $this->db->get('customer_type')->row_array();
	}

	function Read_Customer_Types()
	{
		$this->db->select('CustomerTypeID, Name, Status');
		if(!empty($this->input->get('name'))) {
			$this->db->where('Name', $this->input->get('name'));
		}
		$this->db->where('Status', 'Y');
		$this->db->order_by('Name', 'ASC');
		return $this->db->get('customer_type')->result();
	}

	function Create()
	{
		$this->db->insert_batch('customer_type', json_decode(json_encode($this->input->post('customer_type'))));
	}

	function Update()
	{
		$posted = json_decode(json_encode($this->input->post('customer_type')), true);
		if(!is_array($posted)) {
			return;
		}

		$this->db->trans_start();

		foreach($posted as $row) {
			if(!isset($row['CustomerTypeID']) || !isset($row['Name'])) {
				continue;
			}
			$old = $this->db->select('Name')->where('CustomerTypeID', $row['CustomerTypeID'])->get('customer_type')->row_array();
			if($old && $old['Name'] !== $row['Name']) {
				$this->db->where('customer_type', $old['Name']);
				$this->db->update('customer', ['customer_type' => $row['Name']]);
			}
		}

		$this->db->update_batch('customer_type', $posted, 'CustomerTypeID');

		$this->db->trans_complete();
	}

	function Detect()
	{
		$name = $this->input->post('name');
		$this->db->where('Name', $name);
		if($this->input->post('customer_type_id')) {
			$this->db->where('CustomerTypeID !=', $this->input->post('customer_type_id'));
		}
		if($this->db->get('customer_type')->row()) {
			return true;
		} else {
			return false;
		}
	}
}
