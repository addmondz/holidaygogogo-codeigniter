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

		// Human-readable summary of the guest-picker filters this campaign was
		// built from. ID-based filters resolve against the same lookups the form
		// dropdowns use, so the View page shows names not raw ids.
		$data['filter_rows'] = $this->Campaign_Model->Describe_Filters(
			isset($data['campaign']->FiltersJson) ? $data['campaign']->FiltersJson : null,
			$this->Filter_Lookups()
		);

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
		// "Pick All Matching" asks for every guest under the current filters in one
		// shot (all=1). Cap it so a filter-less request can't pull the whole guest
		// base into the browser; the frontend warns when total exceeds what we send.
		$fetch_all = (int)$this->input->get('all') === 1;
		$page   = $fetch_all ? 1 : max(1, (int)$this->input->get('page'));
		$limit  = $fetch_all ? 2000 : 15;
		$offset = ($page - 1) * $limit;

		// The picker's Type dropdown chooses the source: GHL leads only, booking
		// guests only, or (default) both. Without this the model stayed in its
		// default 'guest' mode, so picking "GHL" still returned booking guests
		// and the synthesized Team Leader fallback rows.
		//
		// "Customer" is a separate source: the customer master (one row per
		// customer, incl. those with no booking yet). It reuses the shared
		// Customer List query so the same filters (Customer Type, Source, …)
		// apply, and it emits the same dedup_key/Name/CallingCode/ContactNum/
		// Email/Type columns the row mapping below expects.
		$type = strtolower(trim((string)$this->input->get('type')));
		if($type === 'customer') {
			$rows  = $this->Guests_Model->Read_Customers_Rich($limit, $offset);
			$total = $this->Guests_Model->Count_Customers_Rich();
		} else {
			$mode = ($type === 'ghl') ? 'ghl' : (($type === 'guest') ? 'guest' : 'all');
			$this->Guests_Model->Set_Mode($mode);

			$rows  = $this->Guests_Model->Read_Guests($limit, $offset);
			$total = $this->Guests_Model->Count_Guests();
		}

		// Show the contact WITH its international calling code — the same
		// "+60 169546738" form the Guest List dashboard renders. Without this the
		// picker dumped the raw local Mobile (no code, trunk "0" inconsistent), so
		// the number shown/stored/blasted was wrong. Mirrors guests/index.php.
		$this->load->helper('guest_contact');

		$out = array();
		foreach($rows as $r) {
			$calling_code = isset($r->CallingCode) ? (string)$r->CallingCode : '';
			$out[] = array(
				'DedupKey'   => $r->dedup_key,
				'GuestName'  => $r->Name,
				'ContactNum' => guest_contact_format_display($calling_code, (string)$r->ContactNum),
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
			'races'          => $this->Guests_Model->Read_Distinct_Ghl_Races(),
			'lead_tags'      => $this->Guests_Model->Read_Ghl_Tags(),
			'destinations'   => $this->Booking_Model->Read_Categories(),
			'campaigns'      => $this->Campaign_Model->Read_Campaigns(),
		);
	}

	// id => name maps for the filters whose stored value is an id (destination,
	// source, joined_campaign). Used to render readable filter chips on View.
	private function Filter_Lookups()
	{
		$dest = array();
		foreach($this->Booking_Model->Read_Categories() as $d) { $dest[(string)$d->CategoryID] = $d->Name; }
		$src = array();
		foreach($this->Booking_Model->Read_Sources() as $s) { $src[(string)$s->SourceID] = $s->Name; }
		$camp = array();
		foreach($this->Campaign_Model->Read_Campaigns() as $c) { $camp[(string)$c->CampaignID] = $c->Name; }
		return array('destination' => $dest, 'source' => $src, 'joined_campaign' => $camp);
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

		// Snapshot of the guest-picker filters used to build the roster. Stored
		// so the edit screen can show the audience this campaign was drawn from.
		$filters = $this->Campaign_Model->Normalize_Filters($this->input->post('FiltersJson'));

		$data = array(
			'Name'          => $name,
			'CampaignDate'  => $campaign_date,
			'Description'   => $this->input->post('Description'),
			'GhlWorkflowID' => trim((string)$this->input->post('GhlWorkflowID')),
			'FiltersJson'   => $filters === null ? null : json_encode($filters, JSON_UNESCAPED_UNICODE),
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
