<?php
class Admin_Model extends CI_Model
{
	function Read()
	{
		switch($this->router->class) {
			case 'Profile':
				$this->db->select('AdminID, CountryCodeID, Name, Gender, IdentificationNumber, PassportNumber, Mobile, Email, Username, Level');
				$this->db->where('AdminID', $this->session->admin_id);
				$this->db->limit(1);
				$admin = $this->db->get('admin');
				if($admin->num_rows() == 1) {
					return $admin->row_array();
				} else {
					return false;
				}
			case 'Admin':
				switch($this->router->method) {
					case 'index':
						$this->db->select('AdminID, Name, Username, Level, Status');
						$this->db->where('AdminID !=', $this->session->admin_id);
						$this->db->where('AdminID !=', 8);

						//Do Not Display Owner Records If Session Is Finance
						if($this->session->level == 30) {
							$this->db->where('Level !=', '10');
						}

						//Filters
						if($this->input->get('name')) {
							$this->db->where('Name', $this->input->get('name'));
						}
						if($this->input->get('gender')) {
							$this->db->where('Gender', $this->input->get('gender'));
						}
						if($this->input->get('identification_number')) {
							$this->db->where('IdentificationNumber', $this->input->get('identification_number'));
						}
						if($this->input->get('passport_number')) {
							$this->db->where('PassportNumber', $this->input->get('passport_number'));
						}
						if($this->input->get('mobile')) {
							$this->db->where('Mobile', $this->input->get('mobile'));
						}
						if($this->input->get('email')) {
							$this->db->where('Email', $this->input->get('email'));
						}
						if($this->input->get('username')) {
							$this->db->where('Username', $this->input->get('username'));
						}
						if($this->input->get('level')) {
							$this->db->where('Level', $this->input->get('level'));
						}
						if($this->input->get('status')) {
							$this->db->where('Status', $this->input->get('status'));
						} else {
							$this->db->where('Status !=', 'N');
						}
						
						$this->db->order_by('Name', 'ASC');
						$admins = $this->db->get('admin');
						if($admins->num_rows() > 0) {
							return $admins->result();
						} else {
							return false;
						}
					case 'Update':
						$this->db->select('AdminID, CountryCodeID, Name, Gender, IdentificationNumber, PassportNumber, Mobile, Email, Username, Level, AccessControl');
						$this->db->where('AdminID', $this->input->get('admin_id'));
						$this->db->limit(1);
						$admin = $this->db->get('admin');
						if($admin->num_rows() == 1) {
							return $admin->row_array();
						} else {
							return false;
						}
					default:
				}
			default:
		}
	}

	function Read_Country_Codes()
	{
		$this->db->select('CountryCodeID, Country, CountryCode');
		$this->db->where('Status', 'Y');
		$this->db->order_by('Country', 'ASC');
		return $this->db->get('country_code')->result();
	}
	
	function Create()
	{
		$this->db->insert_batch('admin', json_decode(json_encode($this->input->post('admin'))));
		$this->db->limit(1);
		if($this->db->affected_rows() == 1) {
			return true;
		} else {
			return false;
		}
	}
	
	function Update()
	{
		switch($this->router->class) { 
			case 'Profile':
				$this->db->update_batch('admin', json_decode(json_encode($this->input->post('admin'))), 'AdminID');
				$this->db->limit(1);

				if($this->db->affected_rows() == 1) {
					$this->db->insert_batch('admin_log', json_decode(json_encode($this->input->post('admin_log'))));
					return true;
				} else {
					return false;
				}
				break;
			case 'Admin':
				switch($this->router->method) {
					case 'Update':
						$this->db->update_batch('admin', json_decode(json_encode($this->input->post('admin'))), 'AdminID');
						$this->db->limit(1);

						if($this->db->affected_rows() == 1) {
							$this->db->insert_batch('admin_log', json_decode(json_encode($this->input->post('admin_log'))));
							return true;
						} else {
							return false;
						}
						break;
					case 'Update_Status_To_D_Or_Y':
						$array = array(
							'Status' => $this->input->post('new_status'),
							'UpdateBy' => $this->session->admin_id,
							'UpdateDate' => date('Y-m-d H:i:s')
						);
						$this->db->where('AdminID', $this->input->post('admin_id'));
						$this->db->update('admin', $array);
						$this->db->limit(1);

						if($this->db->affected_rows() == 1) {
							$array = array(
								'AdminID' => $this->input->post('admin_id'),
								'Column' => 'Status',
								'CurrentData' => $this->input->post('current_status'),
								'NewData' => $this->input->post('new_status'),
								'InsertBy' => $this->session->admin_id,
								'InsertDate' => date('Y-m-d H:i:s')
							);
							$this->db->insert('admin_log', $array);
							return true;
						} else {
							return false;
						}
						break;
					case 'Update_Status_To_N':
						$array = array(
							'Status' => 'N',
							'UpdateBy' => $this->session->admin_id,
							'UpdateDate' => date('Y-m-d H:i:s')
						);
						$this->db->where('AdminID', $this->input->get('admin_id'));
						$this->db->update('admin', $array);
						$this->db->limit(1);

						if($this->db->affected_rows() == 1) {
							$array = array(
								'AdminID' => $this->input->get('admin_id'),
								'Column' => 'Status',
								'CurrentData' => $this->input->get('status'),
								'NewData' => 'N',
								'InsertBy' => $this->session->admin_id,
								'InsertDate' => date('Y-m-d H:i:s')
							);
							$this->db->insert('admin_log', $array);
							return true;
						} else {
							return false;
						}
						break;
					default:
				}
			default:
		}
	}
}