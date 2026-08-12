<?php
/**
 * Nature of Business presets store (table: nature_of_business). Mirrors
 * Lead_Status_Model. Manual leads store the chosen nature by NAME in
 * ghl_contacts.nature_of_business, so a rename cascades onto those rows to keep
 * the stored string valid.
 */
class Nature_Of_Business_Model extends CI_Model
{
	function Read_Nature_Of_Business()
	{
		$this->db->select('NatureOfBusinessID, Name');
		$this->db->where('NatureOfBusinessID', $this->input->get('nature_of_business_id'));
		return $this->db->get('nature_of_business')->row_array();
	}

	function Read_Nature_Of_Businesses()
	{
		$this->db->select('NatureOfBusinessID, Name, Status');
		if(!empty($this->input->get('name'))) {
			$this->db->where('Name', $this->input->get('name'));
		}
		$this->db->where('Status', 'Y');
		$this->db->order_by('Name', 'ASC');
		return $this->db->get('nature_of_business')->result();
	}

	function Create()
	{
		$this->db->insert_batch('nature_of_business', json_decode(json_encode($this->input->post('nature_of_business'))));
	}

	function Update()
	{
		$posted = json_decode(json_encode($this->input->post('nature_of_business')), true);
		if(!is_array($posted)) {
			return;
		}

		$this->db->trans_start();

		foreach($posted as $row) {
			if(!isset($row['NatureOfBusinessID']) || !isset($row['Name'])) {
				continue;
			}
			$old = $this->db->select('Name')->where('NatureOfBusinessID', $row['NatureOfBusinessID'])->get('nature_of_business')->row_array();
			// Manual leads reference the nature by name; carry a rename onto them.
			if($old && $old['Name'] !== $row['Name']) {
				$this->db->where('nature_of_business', $old['Name']);
				$this->db->update('ghl_contacts', ['nature_of_business' => $row['Name']]);
			}
		}

		$this->db->update_batch('nature_of_business', $posted, 'NatureOfBusinessID');

		$this->db->trans_complete();
	}

	function Detect()
	{
		$name = $this->input->post('name');
		$this->db->where('Name', $name);
		if($this->input->post('nature_of_business_id')) {
			$this->db->where('NatureOfBusinessID !=', $this->input->post('nature_of_business_id'));
		}
		if($this->db->get('nature_of_business')->row()) {
			return true;
		} else {
			return false;
		}
	}
}
