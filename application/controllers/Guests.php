<?php
class Guests extends MY_Controller
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
	}

	function index()
	{
		$titles = array('tab_title' => 'HolidayGoGoGo | Guest List', 'breadcrumb_title' => 'Guest List');
		$page   = max(1, (int) $this->input->get('page'));
		$limit  = 30;
		$offset = ($page - 1) * $limit;

		$this->Guests_Model->Set_Mode('guest');
		$data['guests']         = $this->Guests_Model->Read_Guests($limit, $offset);
		$data['total']          = null;
		$data['page']           = $page;
		$data['limit']          = $limit;
		$data['list_base']      = 'Guests';
		$data['page_title']     = 'Guest List Records';
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

	function Count()
	{
		$page  = max(1, (int) $this->input->get('page'));
		$limit = 30;
		$this->Guests_Model->Set_Mode('guest');
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

	function Update_Contact()
	{
		$out = function ($data) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode($data));
		};

		$dedup_key = (string) $this->input->post('dedup_key');
		$mobile    = trim((string) $this->input->post('mobile'));

		if ($dedup_key === '') {
			return $out(array('ok' => false, 'message' => 'Missing guest reference.'));
		}

		$valid = guest_contact_validate_mobile($mobile);
		if (!$valid['ok']) {
			return $out(array('ok' => false, 'message' => $valid['error']));
		}

		// Block when the new number already belongs to a different person.
		$new_key = guest_contact_normalize_key($mobile);
		if ($new_key !== '' && $new_key !== $dedup_key
			&& $this->Guests_Model->Contact_Key_Belongs_To_Other($new_key, $dedup_key)) {
			return $out(array('ok' => false, 'message' => 'This contact number already belongs to another guest.'));
		}

		// Levels 20/50 may only edit guests on their own bookings (mirrors the
		// read-side scoping in Guests_Model::Read_Guests).
		$scope_admin_id = in_array($this->session->userdata('level'), array(20, 50))
			? $this->session->userdata('admin_id')
			: null;

		$affected = $this->Guests_Model->Update_Guest_Contact(
			$dedup_key,
			$mobile,
			$this->session->userdata('admin_id'),
			$scope_admin_id
		);

		if ($affected < 1) {
			return $out(array('ok' => false, 'message' => 'Guest not found or you are not allowed to edit it.'));
		}

		return $out(array(
			'ok'        => true,
			'mobile'    => $mobile,
			'dedup_key' => $new_key !== '' ? $new_key : $dedup_key,
		));
	}

	function View()
	{
		$key = $this->input->get('key');
		if(empty($key)) { redirect('Guests'); return; }

		$detail = $this->Guests_Model->Read_Guest_Detail($key);
		if(empty($detail) || empty($detail['bookings'])) {
			redirect('Guests');
			return;
		}

		$titles = array('tab_title' => 'HolidayGoGoGo | Guest List >> View', 'breadcrumb_title' => 'Guest List >> View');
		$this->load->view('layout/header', $titles);
		$this->load->view('guests/view', $detail);
		$this->load->view('layout/footer');
	}
}
