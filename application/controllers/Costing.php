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

        $filters = array(
            'search' => trim((string) $this->input->get('search')),
            'status' => trim((string) $this->input->get('status')),
        );

        $array = $this->Costing_Model->Read_Packages_Dashboard($filters);
        $array['filters'] = $filters;

        $this->load->view('layout/header', $titles);
        $this->load->view('costing/index', $array);
        $this->load->view('layout/footer');
    }

    public function Create()
    {
        // Brand-new package: open the wizard directly on step 1 with a blank form.
        // Saving step 1 creates the package and moves on to the cost step.
        $array = $this->Costing_Model->Read_Package_Workspace_Data(0, 0, 'MYR', true);
        $array['active_step'] = 'details';
        $array['has_snapshot'] = false;
        $array['wizard_steps'] = $this->Wizard_Steps();

        $titles = array(
            'tab_title' => 'HolidayGoGoGo | New Costing Package',
            'breadcrumb_title' => 'Setting >> Costing >> New Package',
        );

        $this->load->view('layout/header', $titles);
        $this->load->view('costing/wizard', $array);
        $this->load->view('layout/footer');
    }

    public function Package($package_id = null)
    {
        $package_id = (int) $package_id;
        if ($package_id <= 0) {
            redirect('Costing');
            return;
        }

        $step = $this->Normalize_Wizard_Step($this->input->get('step', true));

        // One costing package holds at most ONE snapshot. When it exists we load
        // it so the cost step is pre-filled; otherwise the cost step starts from
        // the item master.
        $existing_booking_id = $this->Costing_Model->Existing_Booking_Id($package_id);
        $force_new_snapshot = $existing_booking_id <= 0;

        $array = $this->Costing_Model->Read_Package_Workspace_Data($package_id, $existing_booking_id, 'MYR', $force_new_snapshot);

        if (empty($array['package']['id'])) {
            $this->session->set_flashdata('message_error', 'Costing package not found.');
            redirect('Costing');
            return;
        }

        $array['active_step'] = $step;
        $array['has_snapshot'] = $existing_booking_id > 0;
        $array['wizard_steps'] = $this->Wizard_Steps();

        $titles = array(
            'tab_title' => 'HolidayGoGoGo | Costing Package',
            'breadcrumb_title' => 'Setting >> Costing >> Package',
        );

        $this->load->view('layout/header', $titles);
        $this->load->view('costing/wizard', $array);
        $this->load->view('layout/footer');
    }

    public function Save_Package()
    {
        $package_id = $this->Costing_Model->Save_Package(array(
            'id' => (int) $this->input->post('package_id'),
            'name' => $this->input->post('name'),
            'tour_code' => $this->input->post('tour_code'),
            'duration_days' => $this->input->post('duration_days'),
            'duration_nights' => $this->input->post('duration_nights'),
            'description' => $this->input->post('description'),
            'status' => $this->input->post('status'),
        ));

        if ($package_id > 0) {
            $this->session->set_flashdata('message_success', ((int) $this->input->post('package_id') > 0)
                ? 'Package details saved.'
                : 'Package created. Continue with the costing.');
            $next = $this->Normalize_Wizard_Step($this->input->post('wizard_next'));
            redirect('Costing/Package/' . $package_id . '?step=' . $next);
            return;
        }

        $this->session->set_flashdata('message_error', 'Package name is required.');
        $existing_package_id = (int) $this->input->post('package_id');
        redirect($existing_package_id > 0 ? 'Costing/Package/' . $existing_package_id . '?step=details' : 'Costing');
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

    /**
     * Wizard step 2. Build (or update) the package's single snapshot from the
     * posted cost rows + pax + margin, then advance to the itinerary step. Reuses
     * the model's Generate_Booking_Snapshot, which creates-or-updates the one
     * snapshot and freezes the currency rates.
     */
    public function Save_Cost_Step()
    {
        $package_id = (int) $this->input->post('package_id');
        $booking_id = $this->Costing_Model->Generate_Booking_Snapshot($package_id, array(
            'booking_id' => (int) $this->input->post('booking_id'),
            'travel_date' => $this->input->post('travel_date'),
            'adult_count' => $this->input->post('adult_count'),
            'child_count' => $this->input->post('child_count'),
            'status' => $this->input->post('status'),
            'rows' => $this->input->post('rows'),
            'margin_percentage' => $this->input->post('margin_percentage'),
            'commissionable_per_pax' => $this->input->post('commissionable_per_pax'),
            'ad_hoc_per_pax' => $this->input->post('ad_hoc_per_pax'),
        ));

        if ($booking_id > 0) {
            $this->session->set_flashdata('message_success', 'Cost template saved.');
            redirect('Costing/Package/' . $package_id . '?step=itinerary');
            return;
        }

        $this->session->set_flashdata('message_error', 'Add at least one cost item and make sure total pax is greater than zero.');
        redirect('Costing/Package/' . $package_id . '?step=cost');
    }

    /**
     * Wizard step 3. Replace-all save of the itinerary, then advance to the final
     * Save & Quotation step.
     */
    public function Save_Itinerary()
    {
        $package_id = (int) $this->input->post('package_id');
        if ($package_id > 0 && $this->Costing_Model->Save_Itinerary_Days($package_id, (array) $this->input->post('itinerary'))) {
            $this->session->set_flashdata('message_success', 'Itinerary saved.');
        } else {
            $this->session->set_flashdata('message_error', 'Unable to save itinerary.');
        }

        redirect('Costing/Package/' . $package_id . '?step=done');
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

    private function Wizard_Steps()
    {
        return array(
            'details'   => 'Package Details',
            'cost'      => 'Cost Template & Margin',
            'itinerary' => 'Itinerary',
            'done'      => 'Save & Quotation',
        );
    }

    private function Normalize_Wizard_Step($step)
    {
        $step = strtolower(trim((string) $step));
        return array_key_exists($step, $this->Wizard_Steps()) ? $step : 'details';
    }
}
