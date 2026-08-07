<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Merge Duplicate Customers — OWNER only. Lists active customers that share a
 * phone number (normalised to the last 9 digits) in duplicate groups, and lets
 * the owner review each group, pick the keeper, and merge: every booking is
 * re-pointed to the keeper and the other records are deactivated (Status='N').
 *
 * The create-time block (Customer_Model::find_active_by_phone) stops NEW
 * duplicates; this tool cleans up the ones already in the table. Local only —
 * AutoCount debtors are not touched.
 */
class Merge_Duplicate_Customers extends MY_Controller
{
    function __construct()
    {
        parent::__construct();
        // Owner-only. MY_Controller doesn't gate this class, so guard here.
        if ((int) $this->session->userdata('level') !== 10) {
            redirect('Dashboard');
        }
        $this->load->model('Customer_Model');
    }

    function index()
    {
        $page   = max(1, (int) $this->input->get('page'));
        $limit  = 20; // groups per page
        $offset = ($page - 1) * $limit;
        $only_same_name = ($this->input->get('scope') !== 'all'); // default: safe same-name groups

        $total = $this->Customer_Model->Count_Duplicate_Phone_Groups($only_same_name);

        $data['groups']         = $this->Customer_Model->Find_Duplicate_Phone_Groups($limit, $offset, $only_same_name);
        $data['only_same_name'] = $only_same_name;
        $data['page']           = $page;
        $data['limit']          = $limit;
        $data['total_groups']   = $total;
        $data['total_pages']    = (int) ceil($total / $limit);

        $titles = array(
            'tab_title'        => 'HolidayGoGoGo | Merge Duplicate Customers',
            'breadcrumb_title' => 'Leads/Customer >> Merge Duplicates',
        );
        $this->load->view('layout/header', $titles);
        $this->load->view('customer/merge_duplicates', $data);
        $this->load->view('layout/footer');
    }

    /**
     * Merge History — past merges with a guarded Undo per row.
     */
    function history()
    {
        $data['recent_merges'] = $this->Customer_Model->Recent_Merges(100);

        $titles = array(
            'tab_title'        => 'HolidayGoGoGo | Merge History',
            'breadcrumb_title' => 'Leads/Customer >> Merge History',
        );
        $this->load->view('layout/header', $titles);
        $this->load->view('customer/merge_history', $data);
        $this->load->view('layout/footer');
    }

    /**
     * Merge a duplicate group. POST: keeper_id, loser_ids[]. Returns JSON.
     */
    function ajax_merge()
    {
        if ( ! $this->input->is_ajax_request()) {
            redirect('Merge_Duplicate_Customers');
            return;
        }

        $keeper_id = (int) $this->input->post('keeper_id');
        $loser_ids = (array) $this->input->post('loser_ids');
        $allow_cross_phone = (bool) $this->input->post('allow_cross_phone');

        $result = $this->Customer_Model->Merge_Customers($keeper_id, $loser_ids, $allow_cross_phone);

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    /**
     * Customer search for "Add another record" (name / code / phone). GET: q, exclude[].
     */
    function ajax_search_customer()
    {
        $q       = (string) $this->input->get('q');
        $exclude = (array) $this->input->get('exclude');
        $rows    = $this->Customer_Model->Search_Active_Customers($q, $exclude, 15);

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($rows));
    }

    /**
     * Guarded revert of a past merge. POST: merge_id. Returns JSON.
     */
    function ajax_revert()
    {
        if ( ! $this->input->is_ajax_request()) {
            redirect('Merge_Duplicate_Customers');
            return;
        }

        $result = $this->Customer_Model->Revert_Merge((int) $this->input->post('merge_id'));

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }
}
