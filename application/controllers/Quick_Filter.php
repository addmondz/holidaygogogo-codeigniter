<?php
class Quick_Filter extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Quick_Filter_Model');
		$this->load->model('Universal_Model');

		// Models needed to render the shared booking filter partial
		$this->load->model('Booking_Model');
		$this->load->model('Customer_Type_Model');
		$this->load->model('Cancellation_Reason_Model');
		$this->load->model('Package_Checklist_Model');
	}

	function index()
	{
		$titles = array('tab_title' => 'HolidayGoGoGo | Quick Filter', 'breadcrumb_title' => 'Quick Filter');
		$array['quick_filters'] = $this->Quick_Filter_Model->Read_Quick_Filters();
		$this->load->view('layout/header', $titles);
		$this->load->view('quick_filter/index', $array);
		$this->load->view('layout/footer');
	}

	function Create()
	{
		if($this->input->is_ajax_request()) {
			$this->Quick_Filter_Model->Create();
		} else {
			$titles = array('tab_title' => 'HolidayGoGoGo | Quick Filter', 'breadcrumb_title' => 'Quick Filter >> Create');
			$array = $this->Load_Filter_Form_Data([
				'QuickFilterID' => 'NA',
				'Name' => '',
				'FilterData' => '{}',
			]);
			$this->load->view('layout/header', $titles);
			$this->load->view('quick_filter/quick_filter', $array);
			$this->load->view('layout/footer');
		}
	}

	function Update()
	{
		if($this->input->is_ajax_request()) {
			$posted = $this->input->post('quick_filter');
			if(is_array($posted) && isset($posted[0]) && count($posted[0]) > 3) {
				$this->Quick_Filter_Model->Update();
			}
		} else {
			$valid = $this->Universal_Model->Validate_Id('QuickFilterID', $this->input->get('quick_filter_id'), 'quick_filter');
			if($valid) {
				$titles = array('tab_title' => 'HolidayGoGoGo | Quick Filter', 'breadcrumb_title' => 'Quick Filter >> Update');
				$row = $this->Quick_Filter_Model->Read_Quick_Filter();
				$array = $this->Load_Filter_Form_Data($row);
				$this->load->view('layout/header', $titles);
				$this->load->view('quick_filter/quick_filter', $array);
				$this->load->view('layout/footer');
			} else {
				redirect('Quick_Filter');
			}
		}
	}

	function Detect()
	{
		$redundant_name = $this->Quick_Filter_Model->Detect();
		if($redundant_name) {
			echo json_encode(true);
		} else {
			echo json_encode(false);
		}
	}

	function Apply($id = null)
	{
		if(empty($id)) {
			redirect('Booking');
			return;
		}
		$row = $this->Quick_Filter_Model->Read_Quick_Filter_By_Id($id);
		if(!$row || $row->Status === 'N') {
			redirect('Booking');
			return;
		}
		$params = json_decode($row->FilterData, true);
		if(!is_array($params)) {
			$params = [];
		}
		$params = array_filter($params, function($v) {
			return $v !== '' && $v !== null;
		});
		$qs = http_build_query($params);
		redirect('Booking' . ($qs !== '' ? ('?' . $qs) : ''));
	}

	/**
	 * Attach dropdown data + hydrated filter_values to the form render array.
	 * The form view includes booking/_filter_fields which expects these keys.
	 */
	private function Load_Filter_Form_Data(array $row)
	{
		$filter_values = json_decode($row['FilterData'] ?? '{}', true);
		if(!is_array($filter_values)) {
			$filter_values = [];
		}
		return [
			'QuickFilterID'       => $row['QuickFilterID'] ?? 'NA',
			'Name'                => $row['Name'] ?? '',
			'FilterData'          => $row['FilterData'] ?? '{}',
			'filter_values'       => $filter_values,
			'admins'              => $this->Booking_Model->Read_Admins(),
			'booking_op_admins'   => $this->Booking_Model->Read_Booking_OP_Admins(),
			'categories'          => $this->Booking_Model->Read_Categories(),
			'tags'                => $this->Booking_Model->Read_Tags(),
			'sources'             => $this->Booking_Model->Read_Sources(),
			'customer_types'      => $this->Customer_Type_Model->Read_Customer_Types(),
			'filter_checklists'   => $this->Package_Checklist_Model->Read_Booking_Filter_Checklists(),
			'cancellation_reasons'=> $this->Cancellation_Reason_Model->Read_Cancellation_Reasons(),
		];
	}
}
