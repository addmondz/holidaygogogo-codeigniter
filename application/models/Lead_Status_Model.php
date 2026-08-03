<?php
/**
 * Lead Status presets store (table: lead_status). Mirrors Customer_Type_Model.
 * Manual leads store the chosen status by NAME in ghl_contacts.lead_status, so a
 * rename cascades onto those rows to keep the stored string valid.
 */
class Lead_Status_Model extends CI_Model
{
	function Read_Lead_Status()
	{
		$this->db->select('LeadStatusID, Name');
		$this->db->where('LeadStatusID', $this->input->get('lead_status_id'));
		return $this->db->get('lead_status')->row_array();
	}

	function Read_Lead_Statuses()
	{
		$this->db->select('LeadStatusID, Name, Status');
		if(!empty($this->input->get('name'))) {
			$this->db->where('Name', $this->input->get('name'));
		}
		$this->db->where('Status', 'Y');
		$this->db->order_by('Name', 'ASC');
		return $this->db->get('lead_status')->result();
	}

	function Create()
	{
		$this->db->insert_batch('lead_status', json_decode(json_encode($this->input->post('lead_status'))));
	}

	function Update()
	{
		$posted = json_decode(json_encode($this->input->post('lead_status')), true);
		if(!is_array($posted)) {
			return;
		}

		$this->db->trans_start();

		foreach($posted as $row) {
			if(!isset($row['LeadStatusID']) || !isset($row['Name'])) {
				continue;
			}
			$old = $this->db->select('Name')->where('LeadStatusID', $row['LeadStatusID'])->get('lead_status')->row_array();
			// Manual leads reference the status by name; carry a rename onto them.
			if($old && $old['Name'] !== $row['Name']) {
				$this->db->where('lead_status', $old['Name']);
				$this->db->update('ghl_contacts', ['lead_status' => $row['Name']]);
			}
		}

		$this->db->update_batch('lead_status', $posted, 'LeadStatusID');

		$this->db->trans_complete();
	}

	function Detect()
	{
		$name = $this->input->post('name');
		$this->db->where('Name', $name);
		if($this->input->post('lead_status_id')) {
			$this->db->where('LeadStatusID !=', $this->input->post('lead_status_id'));
		}
		if($this->db->get('lead_status')->row()) {
			return true;
		} else {
			return false;
		}
	}
}
