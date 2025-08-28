<?php
class Company extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Company_Model');
	}
	
	function index()
	{
		if($this->input->is_ajax_request()) {
			$status = $this->Company_Model->Update();
			echo json_encode($status);
		} else {
			$array = $this->Company_Model->Read();
			if(!empty($array)) {
				$titles = array('tab_title' => 'HolidayGoGoGo | Company', 'breadcrumb_title' => 'Company');
				$this->load->view('layout/header', $titles);
				$this->load->view('company', $array);
				$this->load->view('layout/footer');
			} else {
				$this->load->view('errors/access_denied');
			}
		}
	}
}