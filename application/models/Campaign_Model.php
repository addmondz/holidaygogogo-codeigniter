<?php
class Campaign_Model extends CI_Model
{
	function Read_Campaigns($limit = null, $offset = 0)
	{
		$this->db->select("c.CampaignID, c.Name, c.CampaignDate, c.Description, c.GhlWorkflowID, c.Status, c.InsertDate, a.Name AS InsertByName,
			(SELECT COUNT(*) FROM campaign_guests cg WHERE cg.CampaignID = c.CampaignID) AS GuestCount", false);
		$this->db->from('campaign c');
		$this->db->join('admin a', 'a.AdminID = c.InsertBy', 'left');
		$this->db->where('c.Status', 'Y');

		if(!empty($this->input->get('name'))) {
			$this->db->like('c.Name', $this->input->get('name'));
		}
		if(!empty($this->input->get('date_range'))) {
			$range = explode(' - ', $this->input->get('date_range'));
			if(count($range) == 2) {
				$start = date('Y-m-d', strtotime(str_replace('/', '-', $range[0])));
				$end   = date('Y-m-d', strtotime(str_replace('/', '-', $range[1])));
				$this->db->where('c.CampaignDate >=', $start);
				$this->db->where('c.CampaignDate <=', $end);
			}
		}

		$this->db->order_by('c.CampaignDate', 'DESC');
		$this->db->order_by('c.CampaignID', 'DESC');
		if($limit !== null) {
			$this->db->limit((int)$limit, (int)$offset);
		}
		return $this->db->get()->result();
	}

	function Count_Campaigns()
	{
		$this->db->from('campaign c');
		$this->db->where('c.Status', 'Y');
		if(!empty($this->input->get('name'))) {
			$this->db->like('c.Name', $this->input->get('name'));
		}
		if(!empty($this->input->get('date_range'))) {
			$range = explode(' - ', $this->input->get('date_range'));
			if(count($range) == 2) {
				$start = date('Y-m-d', strtotime(str_replace('/', '-', $range[0])));
				$end   = date('Y-m-d', strtotime(str_replace('/', '-', $range[1])));
				$this->db->where('c.CampaignDate >=', $start);
				$this->db->where('c.CampaignDate <=', $end);
			}
		}
		return $this->db->count_all_results();
	}

	function Read_Campaign($id)
	{
		$this->db->select('CampaignID, Name, CampaignDate, Description, GhlWorkflowID, Status, InsertBy, InsertDate');
		$this->db->where('CampaignID', (int)$id);
		return $this->db->get('campaign')->row();
	}

	function Read_Campaign_Guests($id)
	{
		$this->db->select('CampaignID, DedupKey, GuestName, ContactNum, Email, GuestType, InsertDate');
		$this->db->where('CampaignID', (int)$id);
		$this->db->order_by('GuestName', 'ASC');
		$this->db->order_by('DedupKey', 'ASC');
		return $this->db->get('campaign_guests')->result();
	}

	function Count_Campaign_Guests($id)
	{
		$this->db->from('campaign_guests');
		$this->db->where('CampaignID', (int)$id);
		return (int) $this->db->count_all_results();
	}

	// Stable order so a worker that resumes from CurrentOffset processes the
	// exact same row sequence as the run that paused. DedupKey is unique per
	// (CampaignID, DedupKey) so it's a deterministic tiebreaker.
	function Read_Campaign_Guests_Chunk($id, $offset, $limit)
	{
		$this->db->select('CampaignID, DedupKey, GuestName, ContactNum, Email, GuestType, InsertDate');
		$this->db->where('CampaignID', (int)$id);
		$this->db->order_by('DedupKey', 'ASC');
		$this->db->limit((int)$limit, (int)$offset);
		return $this->db->get('campaign_guests')->result();
	}

	function Create($data, $guest_rows)
	{
		$admin_id = $this->session->userdata('admin_id');
		$now      = date('Y-m-d H:i:s');

		$campaign_row = array(
			'Name'          => $data['Name'],
			'CampaignDate'  => !empty($data['CampaignDate']) ? $data['CampaignDate'] : null,
			'Description'   => isset($data['Description']) ? $data['Description'] : null,
			'GhlWorkflowID' => isset($data['GhlWorkflowID']) && $data['GhlWorkflowID'] !== '' ? $data['GhlWorkflowID'] : null,
			'Status'        => 'Y',
			'InsertBy'      => $admin_id,
			'InsertDate'    => $now,
			'UpdateBy'      => $admin_id,
			'UpdateDate'    => $now,
		);

		$this->db->trans_start();
		$this->db->insert('campaign', $campaign_row);
		$campaign_id = (int)$this->db->insert_id();

		$pivot = $this->Build_Pivot_Rows($campaign_id, $guest_rows, $admin_id, $now);
		if(!empty($pivot)) {
			$this->db->insert_batch('campaign_guests', $pivot);
		}
		$this->db->trans_complete();

		return $this->db->trans_status() ? $campaign_id : false;
	}

	function Update($id, $data, $guest_rows)
	{
		$id       = (int)$id;
		$admin_id = $this->session->userdata('admin_id');
		$now      = date('Y-m-d H:i:s');

		$campaign_row = array(
			'Name'          => $data['Name'],
			'CampaignDate'  => !empty($data['CampaignDate']) ? $data['CampaignDate'] : null,
			'Description'   => isset($data['Description']) ? $data['Description'] : null,
			'GhlWorkflowID' => isset($data['GhlWorkflowID']) && $data['GhlWorkflowID'] !== '' ? $data['GhlWorkflowID'] : null,
			'UpdateBy'      => $admin_id,
			'UpdateDate'    => $now,
		);

		$this->db->trans_start();
		$this->db->where('CampaignID', $id);
		$this->db->update('campaign', $campaign_row);

		$this->db->where('CampaignID', $id);
		$this->db->delete('campaign_guests');

		$pivot = $this->Build_Pivot_Rows($id, $guest_rows, $admin_id, $now);
		if(!empty($pivot)) {
			$this->db->insert_batch('campaign_guests', $pivot);
		}
		$this->db->trans_complete();

		return $this->db->trans_status();
	}

	function Delete($id)
	{
		$this->db->where('CampaignID', (int)$id);
		$this->db->update('campaign', array(
			'Status'     => 'N',
			'UpdateBy'   => $this->session->userdata('admin_id'),
			'UpdateDate' => date('Y-m-d H:i:s'),
		));
	}

	public static function Build_Pivot_Rows($campaign_id, $guest_rows, $admin_id, $now)
	{
		if(empty($guest_rows) || !is_array($guest_rows)) {
			return array();
		}
		$seen   = array();
		$out    = array();
		$valid  = array('Booking Guest', 'GHL');
		foreach($guest_rows as $key => $row) {
			$dedup = isset($row['DedupKey']) ? trim((string)$row['DedupKey']) : trim((string)$key);
			if($dedup === '') { continue; }
			if(isset($seen[$dedup])) { continue; }
			$seen[$dedup] = true;

			$type = isset($row['GuestType']) ? $row['GuestType'] : null;
			if($type !== null && !in_array($type, $valid, true)) { $type = null; }

			$out[] = array(
				'CampaignID' => (int)$campaign_id,
				'DedupKey'   => $dedup,
				'GuestName'  => isset($row['GuestName'])  ? (string)$row['GuestName']  : null,
				'ContactNum' => isset($row['ContactNum']) ? (string)$row['ContactNum'] : null,
				'Email'      => isset($row['Email'])      ? (string)$row['Email']      : null,
				'GuestType'  => $type,
				'InsertBy'   => $admin_id,
				'InsertDate' => $now,
			);
		}
		return $out;
	}
}
