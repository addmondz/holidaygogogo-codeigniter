<?php
class Admin extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Admin_Model');
	}

	function index()
	{
		$titles = array('tab_title' => 'HolidayGoGoGo | Admin', 'breadcrumb_title' => 'Admin');
		$array['admins'] = $this->Admin_Model->Read();
		if(!empty($array['admins'])) {
			foreach($array['admins'] as $admin) {
				switch($admin->Level) {
					case 10:
						$admin->Level = 'OWNER';
						break;
					case 20:
						$admin->Level = 'SALES AGENT';
						break;
					case 25:
						$admin->Level = 'TEAM LEAD';
						break;
					case 30:
						$admin->Level = 'FINANCE';
						break;
					default:
				}

				switch($admin->Status) {
					case 'Y':
						$admin->StatusIcon = '<i class="la la-check-circle text-success"></i>';
						break;
					case 'D':
						$admin->StatusIcon = '<i class="la la-times-circle text-danger"></i>';
						break;
					default:
				}
			}
		}
		$this->load->view('layout/header', $titles);
		$this->load->view('admin/index', $array);
		$this->load->view('layout/footer');
	}

	function Create()
	{
		if($this->input->is_ajax_request()) {
			$status = $this->Admin_Model->Create();
			echo json_encode($status);
		} else {
			$titles = array('tab_title' => 'HolidayGoGoGo | Admin', 'breadcrumb_title' => 'Admin >> Create');
			$array = array('Action' => 'C', 'AdminID' => 0, 'Name' => 'NA', 'AccessControl' => array(), 'TeamLeadID' => '');
			$array['country_codes'] = $this->Admin_Model->Read_Country_Codes();
			$array['team_leads'] = $this->Admin_Model->Read_Team_Leads();
			$this->load->view('layout/header', $titles);
			$this->load->view('admin/admin', $array);
			$this->load->view('layout/footer');
		}
	}

	function Update()
	{
		if($this->input->is_ajax_request()) {
			$status = $this->Admin_Model->Update();
			echo json_encode($status);
		} else {
			$array = $this->Admin_Model->Read();
			if(!empty($array)) {
				$titles = array('tab_title' => 'HolidayGoGoGo | Admin', 'breadcrumb_title' => 'Admin >> Update');
				$array['AccessControl'] = explode(',', $array['AccessControl']);
				$array['Action'] = 'U';
				$array['country_codes'] = $this->Admin_Model->Read_Country_Codes();
				$array['team_leads'] = $this->Admin_Model->Read_Team_Leads();
				$this->load->view('layout/header', $titles);
				$this->load->view('admin/admin', $array);
				$this->load->view('layout/footer');
			} else {
				redirect('Admin');
			}
		}
	}

	function Update_Status_To_D_Or_Y()
	{
		if($this->input->is_ajax_request()) {
			$status = $this->Admin_Model->Update();
			echo json_encode($status);
		} else {
			$this->load->view('errors/access_denied');
		}
	}

	function Update_Status_To_N()
	{
		if($this->input->is_ajax_request()) {
			$status = $this->Admin_Model->Update();
			echo json_encode($status);
		} else {
			$this->load->view('errors/access_denied');
		}
	}
}