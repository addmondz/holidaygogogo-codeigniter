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
	}

	function index()
	{
		$titles = array('tab_title' => 'HolidayGoGoGo | Guest List', 'breadcrumb_title' => 'Guest List');
		$page   = max(1, (int) $this->input->get('page'));
		$limit  = 30;
		$offset = ($page - 1) * $limit;

		$data['guests']         = $this->Guests_Model->Read_Guests($limit, $offset);
		$data['total']          = null;
		$data['page']           = $page;
		$data['limit']          = $limit;
		$data['admins']         = $this->Booking_Model->Read_Admins();
		$data['sources']        = $this->Booking_Model->Read_Sources();
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
