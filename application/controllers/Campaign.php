<?php
class Campaign extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		if(!$this->config->item('show_guest_list')) {
			redirect(base_url('Booking'));
			return;
		}
		if($this->session->userdata('level') != 10) {
			redirect(base_url('Booking'));
			return;
		}
		$this->load->model('Campaign_Model');
		$this->load->model('Campaign_Ghl_Sync_Model');
		$this->load->model('Guests_Model');
		$this->load->model('Booking_Model');
		$this->load->model('Customer_Type_Model');
		$this->load->model('Universal_Model');
	}

	function index()
	{
		$titles = array('tab_title' => 'HolidayGoGoGo | Campaign', 'breadcrumb_title' => 'Campaign');
		$page   = max(1, (int)$this->input->get('page'));
		$limit  = 30;
		$offset = ($page - 1) * $limit;

		$data['campaigns'] = $this->Campaign_Model->Read_Campaigns($limit, $offset);
		$data['total']     = $this->Campaign_Model->Count_Campaigns();
		$data['page']      = $page;
		$data['limit']     = $limit;

		$campaign_ids = array();
		foreach ($data['campaigns'] as $c) { $campaign_ids[] = (int) $c->CampaignID; }
		$data['last_sync_runs'] = $this->Campaign_Ghl_Sync_Model->get_latest_run_summaries_for_campaigns($campaign_ids);

		$this->load->view('layout/header', $titles);
		$this->load->view('campaign/index', $data);
		$this->load->view('layout/footer');
	}

	function Create()
	{
		if($this->input->post()) {
			$saved = $this->Save_From_Post(null);
			if($saved === false) {
				$this->session->set_flashdata('campaign_error', 'Failed to create campaign. Name is required.');
				redirect(base_url('Campaign/Create'));
				return;
			}
			$this->session->set_flashdata('campaign_success', 'Campaign created.');
			redirect(base_url('Campaign'));
			return;
		}

		$titles = array('tab_title' => 'HolidayGoGoGo | Campaign', 'breadcrumb_title' => 'Campaign >> Create');
		$data = $this->Form_Filter_Data();
		$data['mode']            = 'create';
		$data['campaign']        = (object) array('CampaignID' => 0, 'Name' => '', 'CampaignDate' => '', 'Description' => '', 'GhlWorkflowID' => '');
		$data['campaign_guests'] = array();

		$this->load->view('layout/header', $titles);
		$this->load->view('campaign/form', $data);
		$this->load->view('layout/footer');
	}

	function Update()
	{
		$id = (int)$this->input->get('campaign_id');
		if($this->input->post()) {
			$post_id = (int)$this->input->post('campaign_id');
			if(!$this->Universal_Model->Validate_Id('CampaignID', $post_id, 'campaign')) {
				redirect(base_url('Campaign'));
				return;
			}
			$saved = $this->Save_From_Post($post_id);
			if($saved === false) {
				$this->session->set_flashdata('campaign_error', 'Failed to update campaign. Name is required.');
				redirect(base_url('Campaign/Update?campaign_id=') . $post_id);
				return;
			}
			$this->session->set_flashdata('campaign_success', 'Campaign updated.');
			redirect(base_url('Campaign'));
			return;
		}

		if(!$this->Universal_Model->Validate_Id('CampaignID', $id, 'campaign')) {
			redirect(base_url('Campaign'));
			return;
		}

		$titles = array('tab_title' => 'HolidayGoGoGo | Campaign', 'breadcrumb_title' => 'Campaign >> Update');
		$data = $this->Form_Filter_Data();
		$data['mode']            = 'update';
		$data['campaign']        = $this->Campaign_Model->Read_Campaign($id);
		$data['campaign_guests'] = $this->Campaign_Model->Read_Campaign_Guests($id);

		$this->load->view('layout/header', $titles);
		$this->load->view('campaign/form', $data);
		$this->load->view('layout/footer');
	}

	function View()
	{
		$id = (int)$this->input->get('campaign_id');
		if(!$this->Universal_Model->Validate_Id('CampaignID', $id, 'campaign')) {
			redirect(base_url('Campaign'));
			return;
		}
		$titles = array('tab_title' => 'HolidayGoGoGo | Campaign', 'breadcrumb_title' => 'Campaign >> View');
		$data['campaign']        = $this->Campaign_Model->Read_Campaign($id);
		$data['campaign_guests'] = $this->Campaign_Model->Read_Campaign_Guests($id);

		$this->load->view('layout/header', $titles);
		$this->load->view('campaign/view', $data);
		$this->load->view('layout/footer');
	}

	function Delete()
	{
		$this->Universal_Model->Delete('CampaignID', $this->input->get('campaign_id'), 'campaign');
	}

	// Queues a campaign sync run and returns immediately. The actual GHL
	// HTTP work is performed by the Cron syncGhlCampaigns worker so the user
	// can close the browser tab without losing the run. The frontend polls
	// Sync_Status to render progress.
	function Sync_Enqueue()
	{
		header('Content-Type: application/json');
		$id = (int) $this->input->post('campaign_id');
		if ($id <= 0) {
			$id = (int) $this->input->get('campaign_id');
		}
		if (!$this->Universal_Model->Validate_Id('CampaignID', $id, 'campaign')) {
			echo json_encode(array('ok' => false, 'message' => 'Campaign not found.'));
			return;
		}

		$this->load->library('GhlCampaignSyncService');
		$result = $this->ghlcampaignsyncservice->enqueue_run($id, $this->session->userdata('admin_id'));
		echo json_encode($result);
	}

	// Polled by the campaign list while a run is in flight. Returns the
	// current run_summary counters + cursor so the page can update its
	// progress bar without holding open a long-running request.
	function Sync_Status()
	{
		header('Content-Type: application/json');
		$id     = (int) $this->input->get('campaign_id');
		$run_id = trim((string) $this->input->get('run_id'));
		if ($id <= 0 || $run_id === '') {
			$id     = (int) $this->input->post('campaign_id');
			$run_id = trim((string) $this->input->post('run_id'));
		}
		if (!$this->Universal_Model->Validate_Id('CampaignID', $id, 'campaign') || $run_id === '') {
			echo json_encode(array('ok' => false, 'message' => 'Campaign or run not found.'));
			return;
		}

		$state = $this->Campaign_Ghl_Sync_Model->get_run_state($run_id);
		if (!$state || (int) $state->CampaignID !== $id) {
			echo json_encode(array('ok' => false, 'message' => 'Run not found.'));
			return;
		}

		echo json_encode(array(
			'ok'             => true,
			'run_id'         => $run_id,
			'status'         => (string) $state->Status,
			'current_offset' => (int) $state->CurrentOffset,
			'total_guests'   => (int) $state->TotalGuests,
			'matched'        => (int) $state->MatchedCount,
			'created'        => (int) $state->CreatedCount,
			'enrolled'       => (int) $state->EnrolledCount,
			'failed'         => (int) $state->FailedCount,
			'workflow_id'    => (string) $state->WorkflowID,
			'completed_at'   => $state->CompletedAt,
		));
	}

	function Search_Guests()
	{
		$page   = max(1, (int)$this->input->get('page'));
		$limit  = 15;
		$offset = ($page - 1) * $limit;

		$rows  = $this->Guests_Model->Read_Guests($limit, $offset);
		$total = $this->Guests_Model->Count_Guests();

		$out = array();
		foreach($rows as $r) {
			$out[] = array(
				'DedupKey'   => $r->dedup_key,
				'GuestName'  => $r->Name,
				'ContactNum' => $r->ContactNum,
				'Email'      => $r->Email,
				'GuestType'  => $r->Type,
			);
		}
		header('Content-Type: application/json');
		echo json_encode(array(
			'rows'        => $out,
			'total'       => $total,
			'page'        => $page,
			'limit'       => $limit,
			'total_pages' => $total > 0 ? (int)ceil($total / $limit) : 0,
		));
	}

	private function Form_Filter_Data()
	{
		return array(
			'admins'         => $this->Booking_Model->Read_Admins(),
			'sources'        => $this->Booking_Model->Read_Sources(),
			'customer_types' => $this->Customer_Type_Model->Read_Customer_Types(),
			'nationalities'  => $this->Guests_Model->Read_Distinct('Nationality'),
			'languages'      => $this->Guests_Model->Read_Distinct('ChatLanguage'),
		);
	}

	private function Save_From_Post($id)
	{
		$name = trim((string)$this->input->post('Name'));
		if($name === '') {
			return false;
		}

		$raw_date = trim((string)$this->input->post('CampaignDate'));
		$campaign_date = null;
		if($raw_date !== '') {
			$ts = strtotime(str_replace('/', '-', $raw_date));
			if($ts !== false) {
				$campaign_date = date('Y-m-d', $ts);
			}
		}

		$data = array(
			'Name'          => $name,
			'CampaignDate'  => $campaign_date,
			'Description'   => $this->input->post('Description'),
			'GhlWorkflowID' => trim((string)$this->input->post('GhlWorkflowID')),
		);

		$posted_guests = $this->input->post('guests');
		$guest_rows = array();
		if(is_array($posted_guests)) {
			foreach($posted_guests as $dedup => $row) {
				if(!is_array($row)) { continue; }
				$guest_rows[] = array(
					'DedupKey'   => $dedup,
					'GuestName'  => isset($row['name'])    ? $row['name']    : null,
					'ContactNum' => isset($row['contact']) ? $row['contact'] : null,
					'Email'      => isset($row['email'])   ? $row['email']   : null,
					'GuestType'  => isset($row['type'])    ? $row['type']    : null,
				);
			}
		}

		if($id === null) {
			return $this->Campaign_Model->Create($data, $guest_rows);
		}
		return $this->Campaign_Model->Update($id, $data, $guest_rows);
	}
}
