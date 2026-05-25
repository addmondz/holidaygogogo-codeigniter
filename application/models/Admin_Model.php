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
						$select = 'a.AdminID, a.Name, a.Username, a.Level, a.Status, a.TeamLeadID, tl.Name as TeamLeadName';
						if($this->session->level == 10) {
							$select .= ', a.Password';
						}
						$this->db->select($select);
						$this->db->from('admin a');
						$this->db->join('admin tl', 'a.TeamLeadID = tl.AdminID', 'left');
						$this->db->where('a.AdminID !=', $this->session->admin_id);
						$this->db->where('a.AdminID !=', 8);

						//Do Not Display Owner Records If Session Is Finance
						if($this->session->level == 30) {
							$this->db->where('a.Level !=', '10');
						}

						//Filters
						if($this->input->get('name')) {
							$this->db->where('a.Name', $this->input->get('name'));
						}
						if($this->input->get('gender')) {
							$this->db->where('a.Gender', $this->input->get('gender'));
						}
						if($this->input->get('identification_number')) {
							$this->db->where('a.IdentificationNumber', $this->input->get('identification_number'));
						}
						if($this->input->get('passport_number')) {
							$this->db->where('a.PassportNumber', $this->input->get('passport_number'));
						}
						if($this->input->get('mobile')) {
							$this->db->where('a.Mobile', $this->input->get('mobile'));
						}
						if($this->input->get('email')) {
							$this->db->where('a.Email', $this->input->get('email'));
						}
						if($this->input->get('username')) {
							$this->db->where('a.Username', $this->input->get('username'));
						}
						if($this->input->get('level')) {
							$this->db->where('a.Level', $this->input->get('level'));
						}
						if($this->input->get('status')) {
							$this->db->where('a.Status', $this->input->get('status'));
						} else {
							$this->db->where('a.Status !=', 'N');
						}

						$this->db->order_by('a.Name', 'ASC');
						$admins = $this->db->get();
						if($admins->num_rows() > 0) {
							return $admins->result();
						} else {
							return false;
						}
					case 'Update':
						$this->db->select('AdminID, CountryCodeID, Name, Gender, IdentificationNumber, PassportNumber, Mobile, Email, Username, Level, AccessControl, TeamLeadID');
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

	function Read_Team_Leads()
	{
		$this->db->select('AdminID, Name');
		$this->db->where('Level', '25');
		$this->db->where('Status', 'Y');
		$this->db->order_by('Name', 'ASC');
		return $this->db->get('admin')->result();
	}

	// GHL agent list shown in the admin's "Lead Dashboard Agents" multi-select.
	// One row per UserID — ghl_users may have duplicates across LocationIDs.
	function Read_GHL_Users()
	{
		$sql = "
			SELECT UserID, MAX(Name) AS Name, MAX(Email) AS Email
			FROM ghl_users
			WHERE Deleted = 0 AND Name IS NOT NULL AND Name != ''
			GROUP BY UserID
			ORDER BY Name ASC
		";
		return $this->db->query($sql)->result();
	}

	function Read_Lead_Dashboard_Agents_For_Admin($admin_id)
	{
		$admin_id = (int) $admin_id;
		if ($admin_id <= 0) return array();
		$this->db->select('GhlUserID');
		$this->db->where('AdminID', $admin_id);
		$rows = $this->db->get('admin_lead_dashboard_agents')->result();
		return array_map(function($r) { return $r->GhlUserID; }, $rows);
	}

	function Read_Sales_Targets_For_Admin($admin_id)
	{
		$admin_id = (int) $admin_id;
		if ($admin_id <= 0) return array();
		$this->db->select('target_year, target_month, target_amount');
		$this->db->where('AdminID', $admin_id);
		$rows = $this->db->get('sales_target')->result();
		$out = array();
		foreach ($rows as $r) {
			$key = sprintf('%04d-%02d', (int)$r->target_year, (int)$r->target_month);
			$out[$key] = (float) $r->target_amount;
		}
		return $out;
	}

	private function _Sync_Sales_Targets($admin_id, array $period_amount_map)
	{
		$admin_id = (int) $admin_id;
		if ($admin_id <= 0) return;

		$this->load->model('Sales_Target_Model');
		foreach ($period_amount_map as $ym => $amount) {
			if (!is_string($ym) || !preg_match('/^(\d{4})-(\d{2})$/', $ym, $m)) {
				continue;
			}
			$year  = (int) $m[1];
			$month = (int) $m[2];
			if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) continue;
			$amt = max(0.0, (float) $amount);
			$this->Sales_Target_Model->upsert($admin_id, $year, $month, $amt);
		}
	}

	private function _Sync_Lead_Dashboard_Agents($admin_id, array $ghl_user_ids)
	{
		$admin_id = (int) $admin_id;
		if ($admin_id <= 0) return;

		$this->db->where('AdminID', $admin_id)->delete('admin_lead_dashboard_agents');

		$ghl_user_ids = array_values(array_unique(array_filter(
			array_map('strval', $ghl_user_ids),
			'strlen'
		)));
		if (empty($ghl_user_ids)) return;

		$now = date('Y-m-d H:i:s');
		$insertBy = (int) $this->session->admin_id ?: null;
		$rows = array();
		foreach ($ghl_user_ids as $uid) {
			$rows[] = array(
				'AdminID'    => $admin_id,
				'GhlUserID'  => $uid,
				'InsertBy'   => $insertBy,
				'InsertDate' => $now,
			);
		}
		$this->db->insert_batch('admin_lead_dashboard_agents', $rows);
	}

	// Active finance admins with a usable email address. Used to fan out
	// e-invoice notification emails to the finance team.
	function get_finance_admins()
	{
		$this->db->select('AdminID, Name, Email');
		$this->db->where('Level', '30');
		$this->db->where('Status', 'Y');
		$this->db->where('Email IS NOT NULL', null, false);
		$this->db->where('Email !=', '');
		$this->db->order_by('Name', 'ASC');
		return $this->db->get('admin')->result_array();
	}

	function Create()
	{
		$payload = json_decode(json_encode($this->input->post('admin')), true);
		if (empty($payload) || empty($payload[0])) {
			return false;
		}
		$row = $payload[0];

		$this->db->insert('admin', $row);
		if($this->db->affected_rows() == 1) {
			$newAdminId = (int) $this->db->insert_id();
			$this->_Sync_Lead_Dashboard_Agents(
				$newAdminId,
				(array) $this->input->post('lead_dashboard_agents')
			);
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
						$adminPayload = json_decode(json_encode($this->input->post('admin')), true);
						$adminId = (!empty($adminPayload[0]['AdminID'])) ? (int) $adminPayload[0]['AdminID'] : 0;
						$ldaDirty = ($this->input->post('lead_dashboard_agents_dirty') === '1');
						$stDirty  = ($this->input->post('sales_targets_dirty') === '1');

						// update_batch returns int (>=0) on success, FALSE on input error.
						// 0 means "row matched but values already equal" — still a success for the user.
						$updateResult = $this->db->update_batch('admin', json_decode(json_encode($this->input->post('admin'))), 'AdminID');
						$adminOk = ($updateResult !== FALSE);

						if($adminOk || $ldaDirty || $stDirty) {
							$adminLog = $this->input->post('admin_log');
							if(!empty($adminLog)) {
								$this->db->insert_batch('admin_log', json_decode(json_encode($adminLog)));
							}
							if($ldaDirty && $adminId > 0) {
								try {
									$this->_Sync_Lead_Dashboard_Agents(
										$adminId,
										(array) $this->input->post('lead_dashboard_agents')
									);
								} catch (Exception $e) {
									log_message('error', 'Lead dashboard agents sync failed for AdminID '.$adminId.': '.$e->getMessage());
								}
							}
							if($stDirty && $adminId > 0) {
								try {
									$this->_Sync_Sales_Targets(
										$adminId,
										(array) $this->input->post('sales_targets')
									);
								} catch (Exception $e) {
									log_message('error', 'Sales targets sync failed for AdminID '.$adminId.': '.$e->getMessage());
								}
							}
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

	public function find($admin_id)
    {
        return $this->db->get_where('admin', ['AdminID' => $admin_id])->row();
    }

}