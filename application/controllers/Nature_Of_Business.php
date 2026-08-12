<?php
/**
 * Nature of Business presets — a Settings CRUD under the Leads/Customer tab.
 * Manual leads pick a nature by NAME from this list. Mirrors the Lead_Status
 * controller: GET renders the list/form views, AJAX POST performs the write;
 * feedback is the client-side Display_Message SweetAlert (no flashdata). Access is
 * owner-granted per-page via the Leads/Customer > Access Settings grid (module
 * 'nature_of_business'): view opens the page, edit gates create/update. Owner (10)
 * always has full access.
 */
class Nature_Of_Business extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		// Leads/Customer tab access: view gates the whole page (owner always
		// allowed; everyone else only when granted on the Access Settings grid).
		if( ! lc_can_view('nature_of_business')) {
			redirect(base_url('Booking'));
			return;
		}
		$this->load->model('Nature_Of_Business_Model');
		$this->load->model('Universal_Model');
	}

	function index()
	{
		$titles = array('tab_title' => 'HolidayGoGoGo | Nature of Business', 'breadcrumb_title' => 'Nature of Business');
		$array['nature_of_businesses'] = $this->Nature_Of_Business_Model->Read_Nature_Of_Businesses();
		$this->load->view('layout/header', $titles);
		$this->load->view('nature_of_business/index', $array);
		$this->load->view('layout/footer');
	}

	function Create()
	{
		if(lc_block_edit('nature_of_business')) { return; }
		if($this->input->is_ajax_request()) {
			$this->Nature_Of_Business_Model->Create();
		} else {
			$titles = array('tab_title' => 'HolidayGoGoGo | Nature of Business', 'breadcrumb_title' => 'Nature of Business >> Create');
			$array = array('NatureOfBusinessID' => 'NA', 'Name' => 'NA');
			$this->load->view('layout/header', $titles);
			$this->load->view('nature_of_business/nature_of_business', $array);
			$this->load->view('layout/footer');
		}
	}

	function Update()
	{
		if(lc_block_edit('nature_of_business')) { return; }
		if($this->input->is_ajax_request()) {
			if(count($this->input->post('nature_of_business')[0]) > 3) {
				$this->Nature_Of_Business_Model->Update();
			}
		} else {
			$valid_id = $this->Universal_Model->Validate_Id('NatureOfBusinessID', $this->input->get('nature_of_business_id'), 'nature_of_business');
			if($valid_id) {
				$titles = array('tab_title' => 'HolidayGoGoGo | Nature of Business', 'breadcrumb_title' => 'Nature of Business >> Update');
				$array = $this->Nature_Of_Business_Model->Read_Nature_Of_Business();
				$this->load->view('layout/header', $titles);
				$this->load->view('nature_of_business/nature_of_business', $array);
				$this->load->view('layout/footer');
			} else {
				redirect('Nature_Of_Business');
			}
		}
	}

	function Detect() {
		if(lc_block_edit('nature_of_business')) { return; }
		$redundant_name = $this->Nature_Of_Business_Model->Detect();
		if($redundant_name) {
			echo json_encode(true);
		} else {
			echo json_encode(false);
		}
	}
}
