<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Access Settings for the "Leads/Customer" tab — OWNER only. Lets the owner grant
 * each staff member view and/or edit rights on the four pages (Customer, Guest
 * List, GHL Leads, Manual Leads). Owner itself always has full access and is not
 * listed. Enforcement lives in leads_customer_access_helper + each page's
 * controller; this page only manages the grants (table lc_module_access).
 */
class Leads_Customer_Access extends MY_Controller
{
    function __construct()
    {
        parent::__construct();
        // Owner-only. MY_Controller doesn't gate this class, so guard here.
        if ((int) $this->session->userdata('level') !== 10) {
            redirect('Dashboard');
        }
        $this->load->model('Lc_Module_Access_Model');
        $this->load->helper('leads_customer_access');
    }

    function index()
    {
        $titles = array(
            'tab_title'        => 'HolidayGoGoGo | Leads/Customer Access',
            'breadcrumb_title' => 'Leads/Customer Access',
        );
        $data['admins']  = $this->Lc_Module_Access_Model->Read_Grid();
        $data['modules'] = lc_modules();
        $this->load->view('layout/header', $titles);
        $this->load->view('leads_customer_access/index', $data);
        $this->load->view('layout/footer');
    }

    function Save()
    {
        if ( ! $this->input->is_ajax_request()) {
            redirect('Leads_Customer_Access');
            return;
        }
        $ok = $this->Lc_Module_Access_Model->Save();
        echo json_encode((bool) $ok);
    }
}
