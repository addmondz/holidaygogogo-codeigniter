<?php
class Profile extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Admin_Model');
	}
	
	function index()
	{
		if($this->input->is_ajax_request()) {
			$status = $this->Admin_Model->Update();
			echo json_encode($status);
		} else {
			$array = $this->Admin_Model->Read();
			if(!empty($array)) {
				$titles = array('tab_title' => 'HolidayGoGoGo | Profile', 'breadcrumb_title' => 'Profile');
				switch($array['Level']) {
					case 10:
						$array['Level'] = 'OWNER';
						break;
					case 20:
						$array['Level'] = 'SALES AGENT';
						break;
					case 30:
						$array['Level'] = 'FINANCE';
						break;
					case 60:
						$array['Level'] = 'MARKETING';
						break;
					default:
				}
				$array['country_codes'] = $this->Admin_Model->Read_Country_Codes();
				$this->load->view('layout/header', $titles);
				$this->load->view('admin/profile', $array);
				$this->load->view('layout/footer');
			} else {
				$this->load->view('errors/access_denied');
			}
		}
	}
}