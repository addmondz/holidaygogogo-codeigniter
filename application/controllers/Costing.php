<?php

class Costing extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Costing_Model');
    }

    public function index()
    {
        $titles = array(
            'tab_title' => 'HolidayGoGoGo | Costing Packages',
            'breadcrumb_title' => 'Setting >> Costing >> Packages',
        );

        $array = $this->Costing_Model->Read_Packages_Dashboard();

        $this->load->view('layout/header', $titles);
        $this->load->view('costing/index', $array);
        $this->load->view('layout/footer');
    }

    public function Package($package_id = null)
    {
        $package_id = (int) $package_id;
        if ($package_id <= 0) {
            redirect('Costing');
            return;
        }

        $booking_id_input = $this->input->get('booking_id', true);
        $booking_id = (int) $booking_id_input;
        $active_tab = $this->Normalize_Package_Tab($this->input->get('tab', true));
        $selected_booking_id = $booking_id_input !== null && $booking_id > 0 ? $booking_id : 0;
        $snapshot_mode = $this->Normalize_Snapshot_Mode($this->input->get('mode', true), $selected_booking_id);
        $force_new_snapshot = $snapshot_mode === 'create'
            || ($active_tab === 'bookings' && $selected_booking_id <= 0)
            || $this->input->get('new_snapshot', true) === '1';
        $array = $this->Costing_Model->Read_Package_Workspace_Data($package_id, $booking_id, 'MYR', $force_new_snapshot);

        if (empty($array['package']['id'])) {
            $this->session->set_flashdata('message_error', 'Costing package not found.');
            redirect('Costing');
            return;
        }

        $array['selected_booking_id'] = $selected_booking_id;
        $array['is_selected_booking'] = $selected_booking_id > 0 && !empty($array['booking']['id']) && (int) $array['booking']['id'] === $selected_booking_id;
        $array['active_tab'] = $active_tab;
        $array['snapshot_mode'] = $active_tab === 'bookings' ? $snapshot_mode : 'list';

        $titles = array(
            'tab_title' => 'HolidayGoGoGo | Costing Package Details',
            'breadcrumb_title' => 'Setting >> Costing >> Package Details',
        );

        $this->load->view('layout/header', $titles);
        $this->load->view('costing/workspace', $array);
        $this->load->view('layout/footer');
    }

    public function Save_Package()
    {
        $return_to = $this->Normalize_Package_Return_Target($this->input->post('return_to'));
        $package_id = $this->Costing_Model->Save_Package(array(
            'id' => (int) $this->input->post('package_id'),
            'name' => $this->input->post('name'),
            'duration_days' => $this->input->post('duration_days'),
            'duration_nights' => $this->input->post('duration_nights'),
            'description' => $this->input->post('description'),
            'status' => $this->input->post('status'),
        ));

        if ($package_id > 0) {
            $this->session->set_flashdata('message_success', ((int) $this->input->post('package_id') > 0)
                ? 'Package updated successfully.'
                : 'Package created successfully.');
            redirect($return_to === 'index' ? 'Costing' : 'Costing/Package/' . $package_id);
            return;
        }

        $this->session->set_flashdata('message_error', 'Package name is required.');
        $existing_package_id = (int) $this->input->post('package_id');
        redirect($return_to === 'workspace' && $existing_package_id > 0 ? 'Costing/Package/' . $existing_package_id : 'Costing');
    }

    public function Delete_Package()
    {
        $package_id = (int) $this->input->post('package_id');
        if ($package_id > 0) {
            $success = $this->Costing_Model->Delete_Package($package_id);
            $this->session->set_flashdata($success ? 'message_success' : 'message_error', $success
                ? 'Package deleted successfully.'
                : 'Unable to delete package.');
        }

        redirect('Costing');
    }

    public function Save_Package_Item()
    {
        $package_id = (int) $this->input->post('package_id');
        $success = $this->Costing_Model->Save_Package_Item(array(
            'id' => (int) $this->input->post('item_id'),
            'package_id' => $package_id,
            'name' => $this->input->post('name'),
            'description' => $this->input->post('description'),
            'category' => $this->input->post('category'),
            'cost_type' => $this->input->post('cost_type'),
            'default_unit_price' => $this->input->post('default_unit_price'),
            'currency_id' => $this->input->post('currency_id'),
        ));

        $this->session->set_flashdata($success ? 'message_success' : 'message_error', $success
            ? (((int) $this->input->post('item_id') > 0) ? 'Template item updated successfully.' : 'Template item created successfully.')
            : 'Unable to save template item. Name, category, package, and currency are required.');

        redirect('Costing/Package/' . $package_id . '?tab=costing');
    }

    public function Delete_Package_Item()
    {
        $package_id = (int) $this->input->post('package_id');
        $item_id = (int) $this->input->post('item_id');

        if ($package_id > 0 && $item_id > 0) {
            $this->Costing_Model->Delete_Package_Item($package_id, $item_id);
            $this->session->set_flashdata('message_success', 'Template item deleted successfully.');
        }

        redirect('Costing/Package/' . $package_id . '?tab=costing');
    }

    public function Generate_Booking_Snapshot()
    {
        $package_id = (int) $this->input->post('package_id');
        $booking_id = $this->Costing_Model->Generate_Booking_Snapshot($package_id, array(
            'booking_id' => (int) $this->input->post('booking_id'),
            'travel_date' => $this->input->post('travel_date'),
            'adult_count' => $this->input->post('adult_count'),
            'child_count' => $this->input->post('child_count'),
            'status' => $this->input->post('status'),
            'selected_package_item_ids' => $this->input->post('selected_package_item_ids'),
            'rows' => $this->input->post('rows'),
            'margin_percentage' => $this->input->post('margin_percentage'),
            'commissionable_per_pax' => $this->input->post('commissionable_per_pax'),
            'ad_hoc_per_pax' => $this->input->post('ad_hoc_per_pax'),
            'selling_price_per_pax' => $this->input->post('selling_price_per_pax'),
        ));

        if ($booking_id > 0) {
            $this->session->set_flashdata('message_success', 'Booking snapshot created from the selected Cost Templates.');
            redirect('Costing/Package/' . $package_id . '?tab=bookings&booking_id=' . $booking_id);
            return;
        }

        $this->session->set_flashdata('message_error', 'Choose at least one Cost Template and make sure total pax is greater than zero.');
        redirect('Costing/Package/' . $package_id . '?tab=bookings&mode=create_snapshot');
    }

    public function Save_Booking_Snapshot()
    {
        $package_id = (int) $this->input->post('package_id');
        $success = $this->Costing_Model->Save_Booking_Snapshot($package_id, array(
            'booking_id' => (int) $this->input->post('booking_id'),
            'travel_date' => $this->input->post('travel_date'),
            'adult_count' => $this->input->post('adult_count'),
            'child_count' => $this->input->post('child_count'),
            'status' => $this->input->post('status'),
            'margin_percentage' => $this->input->post('margin_percentage'),
            'commissionable_per_pax' => $this->input->post('commissionable_per_pax'),
            'ad_hoc_per_pax' => $this->input->post('ad_hoc_per_pax'),
            'selling_price_per_pax' => $this->input->post('selling_price_per_pax'),
            'rows' => $this->input->post('rows'),
        ));

        $booking_id = (int) $this->input->post('booking_id');
        $this->session->set_flashdata($success ? 'message_success' : 'message_error', $success
            ? 'Booking snapshot saved successfully.'
            : 'Unable to save booking snapshot. Ensure the booking exists, total pax is greater than zero, and all rows have a name, category, and currency.');

        redirect('Costing/Package/' . $package_id . '?tab=bookings' . ($booking_id > 0 ? '&booking_id=' . $booking_id : ''));
    }

    public function Delete_Booking_Snapshot()
    {
        $package_id = (int) $this->input->post('package_id');
        $booking_id = (int) $this->input->post('booking_id');

        if ($package_id > 0 && $booking_id > 0) {
            $success = $this->Costing_Model->Delete_Booking_Snapshot($package_id, $booking_id);
            $this->session->set_flashdata($success ? 'message_success' : 'message_error', $success
                ? 'Booking snapshot deleted successfully.'
                : 'Unable to delete booking snapshot.');
        }

        redirect('Costing/Package/' . $package_id . '?tab=bookings');
    }

    public function Currency()
    {
        $titles = array(
            'tab_title' => 'HolidayGoGoGo | Costing Currency',
            'breadcrumb_title' => 'Setting >> Costing >> Currency',
        );

        $array = $this->Costing_Model->Read_Currency_Dashboard_Data();

        $this->load->view('layout/header', $titles);
        $this->load->view('costing/currency', $array);
        $this->load->view('layout/footer');
    }

    public function Save_Currency()
    {
        $active_tab = $this->Normalize_Currency_Tab($this->input->post('active_tab'));
        $currency_id = (int) $this->input->post('currency_id');
        $code = strtoupper(trim((string) $this->input->post('code')));
        $name = trim((string) $this->input->post('name'));
        $symbol = trim((string) $this->input->post('symbol'));

        if ($code === '' || strlen($code) !== 3 || $name === '') {
            $this->session->set_flashdata('message_error', 'Currency code must be 3 letters and name is required.');
            redirect('Costing/Currency?active_tab=' . $active_tab);
            return;
        }

        $success = $this->Costing_Model->Save_Currency(array(
            'id' => $currency_id,
            'code' => $code,
            'name' => $name,
            'symbol' => $symbol === '' ? null : $symbol,
        ));

        if ($success) {
            $this->session->set_flashdata('message_success', $currency_id > 0 ? 'Currency updated successfully.' : 'Currency created successfully.');
        } else {
            $this->session->set_flashdata('message_error', 'Unable to save currency. The code may already exist.');
        }

        redirect('Costing/Currency?active_tab=' . $active_tab);
    }

    public function Save_Exchange_Rate()
    {
        $active_tab = $this->Normalize_Currency_Tab($this->input->post('active_tab'));
        $exchange_rate_id = (int) $this->input->post('exchange_rate_id');
        $from_currency_id = (int) $this->input->post('from_currency_id');
        $to_currency_id = (int) $this->input->post('to_currency_id');
        $unit_amount = (float) $this->input->post('unit_amount');
        $converted_amount = (float) $this->input->post('converted_amount');
        $bank_charges_myr = (float) $this->input->post('bank_charges_myr');
        $valid_from = trim((string) $this->input->post('valid_from'));

        if ($from_currency_id <= 0 || $to_currency_id <= 0 || $unit_amount <= 0 || $converted_amount <= 0 || $bank_charges_myr < 0) {
            $this->session->set_flashdata('message_error', 'From currency, to currency, unit amount, converted amount, and valid bank charges are required.');
            redirect('Costing/Currency?active_tab=' . $active_tab);
            return;
        }

        if ($from_currency_id === $to_currency_id) {
            $this->session->set_flashdata('message_error', 'Same-currency exchange-rate mapping is not allowed.');
            redirect('Costing/Currency?active_tab=' . $active_tab);
            return;
        }

        if ($valid_from === '') {
            $valid_from = date('Y-m-d H:i:s');
        } elseif (strlen($valid_from) === 16) {
            $valid_from .= ':00';
        }

        $success = $this->Costing_Model->Save_Exchange_Rate(array(
            'id' => $exchange_rate_id,
            'from_currency_id' => $from_currency_id,
            'to_currency_id' => $to_currency_id,
            'unit_amount' => $unit_amount,
            'converted_amount' => $converted_amount,
            'bank_charges_myr' => $bank_charges_myr,
            'updated_by_admin_id' => (int) $this->session->userdata('admin_id'),
            'valid_from' => $valid_from,
        ));

        if ($success) {
            $this->session->set_flashdata('message_success', $exchange_rate_id > 0
                ? 'Exchange rate updated successfully.'
                : 'Exchange rate saved as the latest rate for this currency pair.');
        } else {
            $this->session->set_flashdata('message_error', 'Unable to save exchange rate.');
        }

        redirect('Costing/Currency?active_tab=' . $active_tab);
    }

    public function Delete_Currency()
    {
        $active_tab = $this->Normalize_Currency_Tab($this->input->post('active_tab'));
        $result = $this->Costing_Model->Delete_Currency((int) $this->input->post('currency_id'));

        if (!empty($result['success'])) {
            $this->session->set_flashdata('message_success', 'Currency deleted successfully.');
        } else {
            $this->session->set_flashdata('message_error', !empty($result['message']) ? $result['message'] : 'Unable to delete currency.');
        }

        redirect('Costing/Currency?active_tab=' . $active_tab);
    }

    public function Delete_Exchange_Rate()
    {
        $active_tab = $this->Normalize_Currency_Tab($this->input->post('active_tab'));
        $result = $this->Costing_Model->Delete_Exchange_Rate((int) $this->input->post('exchange_rate_id'));

        if (!empty($result['success'])) {
            $this->session->set_flashdata('message_success', 'Exchange rate deleted successfully.');
        } else {
            $this->session->set_flashdata('message_error', !empty($result['message']) ? $result['message'] : 'Unable to delete exchange rate.');
        }

        redirect('Costing/Currency?active_tab=' . $active_tab);
    }

    private function Normalize_Currency_Tab($active_tab)
    {
        $active_tab = strtolower(trim((string) $active_tab));
        return in_array($active_tab, array('currencies', 'rates'), true) ? $active_tab : 'currencies';
    }

    private function Normalize_Package_Return_Target($target)
    {
        $target = strtolower(trim((string) $target));
        return in_array($target, array('index', 'workspace'), true) ? $target : 'workspace';
    }

    private function Normalize_Package_Tab($tab)
    {
        $tab = strtolower(trim((string) $tab));
        return in_array($tab, array('details', 'costing', 'bookings'), true) ? $tab : 'details';
    }

    private function Normalize_Snapshot_Mode($mode, $booking_id)
    {
        $mode = strtolower(trim((string) $mode));
        if ($mode === 'create_snapshot') {
            return 'create';
        }

        return (int) $booking_id > 0 ? 'view' : 'list';
    }
}
