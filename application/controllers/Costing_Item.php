<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Costing Item master — a Settings CRUD living beside the Costing module.
 * Reusable cost items in the five fixed categories, pickable when building a
 * package cost template. GET renders the list; POST saves/deletes with flashdata
 * feedback, mirroring the sibling Costing/Currency page. Access matches the
 * Costing menu gate: Owner (level 10) or the costing admin (id 28).
 */
class Costing_Item extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!((int) $this->session->level === 10 || (int) $this->session->admin_id === 28)) {
            redirect('Booking');
            return;
        }
        $this->load->model('Costing_Item_Model');
        $this->load->helper('costing_calc');
    }

    public function index()
    {
        $filters = array(
            'name'     => trim((string) $this->input->get('item_name', true)),
            'category' => trim((string) $this->input->get('item_category', true)),
        );

        $array = array(
            'items'             => $this->Costing_Item_Model->Read_Items($filters),
            'currency_options'  => $this->Costing_Item_Model->Read_Currency_Options(),
            'categories'        => costing_categories(),
            'multiplier_types'  => costing_multiplier_types(),
            'item_filters'      => $filters,
        );

        $titles = array(
            'tab_title'        => 'HolidayGoGoGo | Costing Item',
            'breadcrumb_title' => 'Setting >> Costing >> Item',
        );

        $this->load->view('layout/header', $titles);
        $this->load->view('costing_item/index', $array);
        $this->load->view('layout/footer');
    }

    public function Save_Item()
    {
        $item_id = (int) $this->input->post('item_id');
        $success = $this->Costing_Item_Model->Save_Item(array(
            'id'                  => $item_id,
            'name'                => $this->input->post('name'),
            'category'            => $this->input->post('category'),
            'multiplier_type'     => $this->input->post('multiplier_type'),
            'default_currency_id' => $this->input->post('default_currency_id'),
            'default_unit_cost'   => $this->input->post('default_unit_cost'),
        ));

        if ($success) {
            $this->session->set_flashdata('message_success', $item_id > 0 ? 'Item updated successfully.' : 'Item created successfully.');
        } else {
            $this->session->set_flashdata('message_error', 'Unable to save item. Name, category, and currency are required.');
        }

        redirect('Costing_Item');
    }

    public function Delete_Item()
    {
        $item_id = (int) $this->input->post('item_id');
        if ($item_id > 0 && $this->Costing_Item_Model->Delete_Item($item_id)) {
            $this->session->set_flashdata('message_success', 'Item deleted successfully.');
        } else {
            $this->session->set_flashdata('message_error', 'Unable to delete item.');
        }

        redirect('Costing_Item');
    }
}
