<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Master_Product — an Owner-only (level 10) overview that lists EVERY analysed
 * record from both product tools (Competitor Product + Our Product) in one table.
 * Read-only: each row links View / Download PDF to its owning tool (by the row's
 * `feature`), and Delete removes it. The analyses themselves are produced by those
 * two tools; this page only aggregates and manages them.
 */
class Master_Product extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		// Owner-only feature.
		if ((int) $this->session->level !== 10) {
			redirect(base_url('Booking'));
			return;
		}
		$this->load->model('Master_Product_Model');
	}

	function index()
	{
		$titles = array(
			'tab_title'        => 'HolidayGoGoGo | Master Product',
			'breadcrumb_title' => 'Master Product',
		);
		$this->load->view('layout/header', $titles);
		$this->load->view('master_product/index', array(
			'records'    => $this->Master_Product_Model->Read_All(),
			'total_cost' => $this->Master_Product_Model->Read_Total_Cost(),
		));
		$this->load->view('layout/footer');
	}

	function Delete()
	{
		if ( ! $this->input->is_ajax_request()) {
			redirect(base_url('Master_Product'));
			return;
		}
		$this->output->set_content_type('application/json');
		$ok = $this->Master_Product_Model->Delete((int) $this->input->post('id'));
		echo json_encode(array('success' => $ok));
	}
}
