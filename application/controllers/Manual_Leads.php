<?php
/**
 * Manual Leads page — hand-entered ("Manual") leads live here on their own page,
 * separate from GHL Leads for control + privacy. It reuses the Guest List view
 * and model (locked to mode 'manual' via Set_Mode) so it shares the GHL Leads
 * layout, but reads only lead_source='manual' rows and — for everyone except
 * Owner/Team Lead/Marketing — only the leads the viewer created (see
 * Guests_Model::Build_Branches + guest_list_ghl_lead_source_scope).
 */
class Manual_Leads extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		if (!$this->config->item('show_guest_list')) {
			redirect(base_url('Booking'));
		}
		$this->load->model('Guests_Model');
		$this->load->model('Booking_Model');
		$this->load->model('Customer_Type_Model');
		$this->load->helper('guest_contact');
		$this->load->helper('ghl_lead_tags');
	}

	function index()
	{
		$titles = array('tab_title' => 'HolidayGoGoGo | Manual Leads', 'breadcrumb_title' => 'Manual Leads');
		$page   = max(1, (int) $this->input->get('page'));
		$limit  = 30;
		$offset = ($page - 1) * $limit;

		$this->Guests_Model->Set_Mode('manual');
		$data['guests']         = $this->Guests_Model->Read_Guests($limit, $offset);
		$this->load->model('Ghl_Messages_Model');
		$data['msg_log_phones'] = $this->Ghl_Messages_Model->Phones_With_Messages_For_Guests($data['guests']);
		$data['chat_counts']    = $this->Chat_Counts_For_Guests($data['guests']);
		$data['total']          = null;
		$data['page']           = $page;
		$data['limit']          = $limit;
		$data['list_base']      = 'Manual_Leads';
		$data['page_title']     = 'Manual Leads Records';
		$data['admins']         = $this->Booking_Model->Read_Admins();
		$data['sources']        = $this->Booking_Model->Read_Sources();
		$data['destinations']   = $this->Booking_Model->Read_Categories();
		$data['customer_types'] = $this->Customer_Type_Model->Read_Customer_Types();
		$data['nationalities']  = $this->Guests_Model->Read_Distinct('Nationality');
		$data['languages']      = $this->Guests_Model->Read_Distinct('ChatLanguage');
		$this->load->view('layout/header', $titles);
		$this->load->view('guests/index', $data);
		$this->load->view('layout/footer');
	}

	/**
	 * Create a hand-entered ("Manual") lead from the Create Lead modal. Stored in
	 * ghl_contacts with a synthetic "manual:<uid>" id, lead_source = 'manual' and
	 * created_by = the current admin (drives per-creator visibility). The GHL API
	 * sync never touches it. Redirects back to the listing with a flash message.
	 */
	function Create()
	{
		$this->load->helper('ghl_manual_lead');
		$this->load->model('Ghl_Contacts_Model');

		// Unique suffix for the synthetic "manual:<uid>" contact_id (the helper
		// adds the prefix). uniqid(more_entropy) is unique enough for hand entry.
		$uid = uniqid('', true);
		$now = date('Y-m-d H:i:s');

		// Creator is taken from the session, NEVER from the POST body.
		$created_by = (int) $this->session->userdata('admin_id');

		$prepared = ghl_manual_lead_prepare($this->input->post(), $uid, $now, $created_by);

		if (!$prepared['ok']) {
			$this->session->set_flashdata('ghl_lead_error', implode(' ', $prepared['errors']));
			redirect(base_url('Manual_Leads'));
			return;
		}

		$new_id = $this->Ghl_Contacts_Model->create_manual_lead($prepared['row']);
		if ($new_id) {
			$this->session->set_flashdata('ghl_lead_success', 'Manual lead created.');
		} else {
			$this->session->set_flashdata('ghl_lead_error', 'Could not save the lead. Please try again.');
		}
		redirect(base_url('Manual_Leads'));
	}

	/**
	 * dedup_key => active chat-file count for the leads on this page (badges the
	 * Action menu with "Chat History (n)").
	 */
	private function Chat_Counts_For_Guests($guests)
	{
		$keys = array();
		foreach ((array) $guests as $g) {
			if (!empty($g->dedup_key)) {
				$keys[] = $g->dedup_key;
			}
		}
		return $this->Guests_Model->Read_Chat_History_Counts($keys);
	}

	function Count()
	{
		$page  = max(1, (int) $this->input->get('page'));
		$limit = 30;
		$this->Guests_Model->Set_Mode('manual');
		$total = (int) $this->Guests_Model->Count_Guests();

		$pagination_html = $this->load->view('guests/_pagination', array(
			'total' => $total,
			'page'  => $page,
			'limit' => $limit,
			'query' => $this->input->get(),
		), true);

		$this->output
			->set_content_type('application/json')
			->set_output(json_encode(array(
				'total'           => $total,
				'pagination_html' => $pagination_html,
			)));
	}
}
