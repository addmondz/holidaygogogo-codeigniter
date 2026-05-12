<?php

class Costing_Model extends CI_Model
{
    public function Read_Packages_Dashboard()
    {
        $packages = $this->db->query("
            SELECT
                cp.id,
                cp.name,
                cp.duration_days,
                cp.duration_nights,
                cp.description,
                cp.status,
                cp.updated_at,
                (
                    SELECT COUNT(*)
                    FROM costing_package_items cpi
                    WHERE cpi.package_id = cp.id
                ) AS template_item_count,
                (
                    SELECT COUNT(*)
                    FROM costing_bookings cb_count
                    WHERE cb_count.package_id = cp.id
                ) AS booking_count,
                cb.id AS latest_booking_id,
                cb.travel_date AS latest_travel_date,
                cb.total_pax AS latest_total_pax,
                cb.status AS latest_booking_status
            FROM costing_packages cp
            LEFT JOIN costing_bookings cb ON cb.id = (
                SELECT cb_latest.id
                FROM costing_bookings cb_latest
                WHERE cb_latest.package_id = cp.id
                ORDER BY cb_latest.id DESC
                LIMIT 1
            )
            ORDER BY cp.updated_at DESC, cp.id DESC
        ")->result_array();

        $package_ids = array();
        foreach ($packages as $package_row) {
            $package_ids[] = (int) $package_row['id'];
        }

        $items_by_package = $this->Read_Dashboard_Package_Items($package_ids);
        $bookings_by_package = $this->Read_Dashboard_Package_Bookings($package_ids);
        $cost_types = $this->Read_Cost_Types();
        $total_template_rows = 0;
        $total_booking_snapshots = 0;

        foreach ($packages as &$package) {
            $package['duration_days'] = (int) $package['duration_days'];
            $package['duration_nights'] = (int) $package['duration_nights'];
            $package['template_item_count'] = (int) $package['template_item_count'];
            $package['booking_count'] = (int) $package['booking_count'];
            $package['latest_total_pax'] = isset($package['latest_total_pax']) ? (int) $package['latest_total_pax'] : 0;
            $package['status_label'] = ucfirst((string) $package['status']);
            $package['latest_booking_label'] = !empty($package['latest_booking_id'])
                ? $this->Build_Booking_Number(array(
                    'id' => $package['latest_booking_id'],
                    'travel_date' => $package['latest_travel_date'],
                ))
                : 'No booking yet';
            $package['items'] = isset($items_by_package[(int) $package['id']]) ? $items_by_package[(int) $package['id']] : array();
            $package['bookings'] = isset($bookings_by_package[(int) $package['id']]) ? $bookings_by_package[(int) $package['id']] : array();
            $package['category_count'] = count(array_unique(array_filter(array_map(function ($item) {
                return isset($item['category']) ? trim((string) $item['category']) : '';
            }, $package['items']))));
            $package['cost_type_summary'] = array();
            foreach ($package['items'] as $item) {
                $cost_type = isset($item['cost_type']) ? (string) $item['cost_type'] : '';
                $label = isset($cost_types[$cost_type]) ? $cost_types[$cost_type] : ucfirst(str_replace('_', ' ', $cost_type));
                $package['cost_type_summary'][$label] = true;
            }
            $package['cost_type_summary'] = array_keys($package['cost_type_summary']);
            $total_template_rows += $package['template_item_count'];
            $total_booking_snapshots += $package['booking_count'];
        }
        unset($package);

        return array(
            'packages' => $packages,
            'total_packages' => count($packages),
            'total_template_rows' => $total_template_rows,
            'total_booking_snapshots' => $total_booking_snapshots,
            'total_currencies' => (int) $this->db->count_all('costing_currencies'),
            'latest_rate_count' => count($this->Read_Latest_Exchange_Rates()),
        );
    }

    public function Read_Package_Workspace_Data($package_id, $booking_id = null, $base_currency_code = 'MYR', $force_new_snapshot = false)
    {
        $base_currency = $this->Read_Base_Currency($base_currency_code);
        if (!$base_currency) {
            $base_currency = array('id' => 0, 'code' => $base_currency_code);
        }

        $package = $this->Read_Package($package_id);
        if (!$package) {
            return $this->Empty_Workspace_Data($base_currency['code']);
        }

        $booking = $force_new_snapshot
            ? null
            : ($booking_id > 0
                ? $this->Read_Booking($booking_id, $package_id)
                : $this->Read_Latest_Booking_For_Package($package_id));

        $bookings = $this->Read_Bookings_For_Package($package_id);
        $package_items = $this->Read_Package_Items($package_id);
        $booking_items = array();
        $financials = $this->Empty_Financials();

        if ($booking) {
            $booking_items = $this->Read_Booking_Items($booking['id'], (int) $base_currency['id'], $booking['travel_date']);
            $financials = $this->Calculate_Financials_From_Booking($booking, $booking_items, (int) $base_currency['id']);
        }

        return array(
            'package' => array(
                'id' => (int) $package['id'],
                'name' => $package['name'],
                'duration_days' => (int) $package['duration_days'],
                'duration_nights' => (int) $package['duration_nights'],
                'description' => $package['description'],
                'status' => ucfirst($package['status']),
                'status_value' => (string) $package['status'],
            ),
            'booking' => $booking ? array(
                'id' => (int) $booking['id'],
                'booking_number' => $this->Build_Booking_Number($booking),
                'travel_date' => $booking['travel_date'],
                'adult_count' => (int) $booking['adult_count'],
                'child_count' => (int) $booking['child_count'],
                'total_pax' => (int) $booking['total_pax'],
                'status' => ucfirst($booking['booking_status']),
                'status_value' => (string) $booking['booking_status'],
            ) : $this->Empty_Booking_Data(),
            'bookings' => $bookings,
            'package_items' => $package_items,
            'booking_items' => $booking_items,
            'selected_package_item_ids' => $this->Extract_Selected_Package_Item_Ids($booking_items),
            'category_suggestions' => $this->Build_Category_Suggestions($package_items, $booking_items),
            'financials' => $financials,
            'base_currency' => $base_currency['code'],
            'has_booking' => !empty($booking),
            'has_bookings' => !empty($bookings),
            'currencies' => $this->Read_Currencies(),
            'statuses' => array('draft', 'active', 'inactive'),
            'cost_types' => $this->Read_Cost_Types(),
            'latest_exchange_rates' => $this->Read_Latest_Exchange_Rates(),
        );
    }

    public function Read_Currency_Dashboard_Data()
    {
        $active_tab = (string) $this->input->get('active_tab');
        $active_tab = in_array($active_tab, array('currencies', 'rates'), true) ? $active_tab : 'currencies';

        $currency_filters = array(
            'code' => trim((string) $this->input->get('currency_code')),
            'name' => trim((string) $this->input->get('currency_name')),
            'symbol' => trim((string) $this->input->get('currency_symbol')),
        );

        $rate_filters = array(
            'from_currency_id' => (int) $this->input->get('rate_from_currency_id'),
            'to_currency_id' => (int) $this->input->get('rate_to_currency_id'),
            'valid_from' => trim((string) $this->input->get('rate_valid_from')),
        );

        return array(
            'active_tab' => $active_tab,
            'currency_filters' => $currency_filters,
            'rate_filters' => $rate_filters,
            'currencies' => $this->Read_Currencies($currency_filters),
            'exchange_rates' => $this->Read_Latest_Exchange_Rates($rate_filters),
            'currency_options' => $this->Read_Currencies(),
        );
    }

    public function Save_Package($package)
    {
        $data = array(
            'name' => trim((string) $package['name']),
            'duration_days' => max(1, (int) $package['duration_days']),
            'duration_nights' => max(0, (int) $package['duration_nights']),
            'description' => trim((string) $package['description']),
            'status' => $this->Normalize_Status($package['status']),
        );

        if ($data['name'] === '') {
            return 0;
        }

        if (!empty($package['id'])) {
            $this->db->where('id', (int) $package['id'])->update('costing_packages', $data);
            return (int) $package['id'];
        }

        $this->db->insert('costing_packages', $data);
        return (int) $this->db->insert_id();
    }

    public function Delete_Package($package_id)
    {
        return $this->db->where('id', (int) $package_id)->delete('costing_packages');
    }

    public function Save_Package_Item($item)
    {
        $data = array(
            'package_id' => (int) $item['package_id'],
            'name' => trim((string) $item['name']),
            'description' => trim((string) $item['description']),
            'category' => trim((string) $item['category']),
            'cost_type' => $this->Normalize_Cost_Type($item['cost_type']),
            'default_unit_price' => round((float) $item['default_unit_price'], 2),
            'currency_id' => (int) $item['currency_id'],
        );

        if ($data['package_id'] <= 0 || $data['currency_id'] <= 0 || $data['name'] === '' || $data['category'] === '') {
            return false;
        }

        if (!empty($item['id'])) {
            return $this->db
                ->where('id', (int) $item['id'])
                ->where('package_id', $data['package_id'])
                ->update('costing_package_items', $data);
        }

        return $this->db->insert('costing_package_items', $data);
    }

    public function Delete_Package_Item($package_id, $item_id)
    {
        return $this->db
            ->where('id', (int) $item_id)
            ->where('package_id', (int) $package_id)
            ->delete('costing_package_items');
    }

    public function Generate_Booking_Snapshot($package_id, $payload)
    {
        $package_id = (int) $package_id;
        $selected_package_item_ids = array_values(array_unique(array_filter(array_map('intval', isset($payload['selected_package_item_ids']) ? (array) $payload['selected_package_item_ids'] : array()))));
        $adult_count = max(0, (int) $payload['adult_count']);
        $child_count = max(0, (int) $payload['child_count']);
        $total_pax = $adult_count + $child_count;
        $travel_date = !empty($payload['travel_date']) ? $payload['travel_date'] : date('Y-m-d');
        $booking_status = $this->Normalize_Status($payload['status']);
        $booking_id = (int) $payload['booking_id'];

        if ($package_id <= 0 || $total_pax <= 0 || empty($selected_package_item_ids)) {
            return 0;
        }

        $package_items = $this->Read_Package_Items_By_Ids($package_id, $selected_package_item_ids);
        if (empty($package_items)) {
            return 0;
        }

        $this->db->trans_start();

        $booking_data = array(
            'package_id' => $package_id,
            'travel_date' => $travel_date,
            'adult_count' => $adult_count,
            'child_count' => $child_count,
            'total_pax' => $total_pax,
            'status' => $booking_status,
        );

        if ($booking_id > 0 && $this->Read_Booking($booking_id, $package_id)) {
            $this->db->where('id', $booking_id)->update('costing_bookings', $booking_data);
        } else {
            $this->db->insert('costing_bookings', $booking_data);
            $booking_id = (int) $this->db->insert_id();
        }

        $this->db->where('booking_id', $booking_id)->delete('costing_booking_items');

        foreach ($package_items as $item) {
            $snapshot = $this->Build_Snapshot_Row_From_Template($item, $adult_count, $child_count, $total_pax);
            $snapshot['booking_id'] = $booking_id;
            $this->db->insert('costing_booking_items', $snapshot);
        }

        $this->Upsert_Booking_Financials($booking_id, array(
            'margin_percentage' => isset($payload['margin_percentage']) ? $payload['margin_percentage'] : 0,
            'commissionable_per_pax' => isset($payload['commissionable_per_pax']) ? $payload['commissionable_per_pax'] : 0,
            'ad_hoc_per_pax' => isset($payload['ad_hoc_per_pax']) ? $payload['ad_hoc_per_pax'] : 0,
        ));

        $this->db->trans_complete();

        if (!$this->db->trans_status()) {
            return 0;
        }

        $this->Recalculate_Booking_Financials($booking_id);

        return $booking_id;
    }

    public function Save_Booking_Snapshot($package_id, $payload)
    {
        $package_id = (int) $package_id;
        $booking_id = (int) $payload['booking_id'];
        $adult_count = max(0, (int) $payload['adult_count']);
        $child_count = max(0, (int) $payload['child_count']);
        $total_pax = $adult_count + $child_count;
        $travel_date = !empty($payload['travel_date']) ? $payload['travel_date'] : date('Y-m-d');
        $booking_status = $this->Normalize_Status($payload['status']);

        if ($package_id <= 0 || $booking_id <= 0 || $total_pax <= 0 || !$this->Read_Booking($booking_id, $package_id)) {
            return false;
        }

        $rows = $this->Normalize_Booking_Rows(isset($payload['rows']) ? $payload['rows'] : array());

        $this->db->trans_start();

        $this->db->where('id', $booking_id)->update('costing_bookings', array(
            'travel_date' => $travel_date,
            'adult_count' => $adult_count,
            'child_count' => $child_count,
            'total_pax' => $total_pax,
            'status' => $booking_status,
        ));

        $this->db->where('booking_id', $booking_id)->delete('costing_booking_items');

        foreach ($rows as $row) {
            $row['booking_id'] = $booking_id;
            $row['total_amount'] = round($row['quantity'] * $row['unit_count'] * $row['unit_price'], 2);
            $this->db->insert('costing_booking_items', $row);
        }

        $this->Upsert_Booking_Financials($booking_id, array(
            'margin_percentage' => $payload['margin_percentage'],
            'commissionable_per_pax' => $payload['commissionable_per_pax'],
            'ad_hoc_per_pax' => $payload['ad_hoc_per_pax'],
        ));

        $this->db->trans_complete();

        if (!$this->db->trans_status()) {
            return false;
        }

        $this->Recalculate_Booking_Financials($booking_id);

        return true;
    }

    public function Delete_Booking_Snapshot($package_id, $booking_id)
    {
        $package_id = (int) $package_id;
        $booking_id = (int) $booking_id;

        if ($package_id <= 0 || $booking_id <= 0 || !$this->Read_Booking($booking_id, $package_id)) {
            return false;
        }

        return (bool) $this->db
            ->where('id', $booking_id)
            ->where('package_id', $package_id)
            ->delete('costing_bookings');
    }

    public function Save_Currency($currency)
    {
        $data = array(
            'code' => strtoupper(trim((string) $currency['code'])),
            'name' => trim((string) $currency['name']),
            'symbol' => $currency['symbol'],
        );

        if (!empty($currency['id'])) {
            return $this->db
                ->where('id', (int) $currency['id'])
                ->update('costing_currencies', $data);
        }

        return $this->db->insert('costing_currencies', $data);
    }

    public function Delete_Currency($currency_id)
    {
        $currency_id = (int) $currency_id;
        if ($currency_id <= 0) {
            return array('success' => false, 'message' => 'Invalid currency selected.');
        }

        $usage_count = 0;
        $usage_count += (int) $this->db->where('currency_id', $currency_id)->count_all_results('costing_package_items');
        $usage_count += (int) $this->db->where('currency_id', $currency_id)->count_all_results('costing_booking_items');
        $usage_count += (int) $this->db->group_start()
            ->where('from_currency_id', $currency_id)
            ->or_where('to_currency_id', $currency_id)
            ->group_end()
            ->count_all_results('costing_exchange_rates');

        if ($usage_count > 0) {
            return array('success' => false, 'message' => 'This currency is already used in costing records and cannot be deleted.');
        }

        return $this->Run_Safe_Delete('costing_currencies', 'id', $currency_id, 'Unable to delete currency.');
    }

    public function Save_Exchange_Rate($exchange_rate)
    {
        if ((int) $exchange_rate['from_currency_id'] === (int) $exchange_rate['to_currency_id']) {
            return false;
        }

        $unit_amount = round((float) $exchange_rate['unit_amount'], 8);
        $converted_amount = round((float) $exchange_rate['converted_amount'], 8);
        if ($unit_amount <= 0 || $converted_amount <= 0) {
            return false;
        }

        $data = array(
            'from_currency_id' => (int) $exchange_rate['from_currency_id'],
            'to_currency_id' => (int) $exchange_rate['to_currency_id'],
            'unit_amount' => $unit_amount,
            'rate' => round($converted_amount / $unit_amount, 8),
            'valid_from' => $exchange_rate['valid_from'],
        );

        if (!empty($exchange_rate['id'])) {
            return $this->db
                ->where('id', (int) $exchange_rate['id'])
                ->update('costing_exchange_rates', $data);
        }

        return $this->db->insert('costing_exchange_rates', $data);
    }

    public function Delete_Exchange_Rate($exchange_rate_id)
    {
        $exchange_rate_id = (int) $exchange_rate_id;
        if ($exchange_rate_id <= 0) {
            return array('success' => false, 'message' => 'Invalid exchange rate selected.');
        }

        return $this->Run_Safe_Delete('costing_exchange_rates', 'id', $exchange_rate_id, 'Unable to delete exchange rate.');
    }

    public function Recalculate_Booking_Financials($booking_id, $base_currency_code = 'MYR')
    {
        $booking = $this->Read_Booking((int) $booking_id);
        if (!$booking) {
            return false;
        }

        $base_currency = $this->Read_Base_Currency($base_currency_code);
        $base_currency_id = $base_currency ? (int) $base_currency['id'] : 0;
        $booking_items = $this->Read_Booking_Items((int) $booking_id, $base_currency_id, $booking['travel_date']);
        $financials = $this->Calculate_Financials_From_Booking($booking, $booking_items, $base_currency_id);

        return $this->db
            ->where('booking_id', (int) $booking_id)
            ->update('costing_booking_financials', array(
                'total_cost' => $financials['total_cost'],
                'cost_per_pax' => $financials['cost_per_pax'],
                'markup_amount_total' => $financials['markup_amount_total'],
                'price_per_pax' => $financials['price_per_pax'],
                'total_per_pax' => $financials['total_per_pax'],
                'selling_price_per_pax' => $financials['selling_price_per_pax'],
                'total_revenue' => $financials['total_revenue'],
                'gross_profit' => $financials['gross_profit'],
                'total_profit' => $financials['total_profit'],
            ));
    }

    private function Read_Base_Currency($base_currency_code)
    {
        $this->db->select('id, code');
        $this->db->where('code', strtoupper($base_currency_code));
        $currency = $this->db->get('costing_currencies')->row_array();

        if ($currency) {
            return $currency;
        }

        return $this->db->select('id, code')->order_by('id', 'ASC')->get('costing_currencies')->row_array();
    }

    private function Read_Dashboard_Package_Items($package_ids)
    {
        if (empty($package_ids)) {
            return array();
        }

        $rows = $this->db
            ->select('
                costing_package_items.id,
                costing_package_items.package_id,
                costing_package_items.name,
                costing_package_items.description,
                costing_package_items.category,
                costing_package_items.cost_type,
                costing_package_items.default_unit_price,
                costing_currencies.code AS currency
            ')
            ->join('costing_currencies', 'costing_currencies.id = costing_package_items.currency_id')
            ->where_in('costing_package_items.package_id', $package_ids)
            ->order_by('costing_package_items.package_id', 'ASC')
            ->order_by('costing_package_items.id', 'ASC')
            ->get('costing_package_items')
            ->result_array();

        $grouped = array();
        foreach ($rows as $row) {
            $package_id = (int) $row['package_id'];
            if (!isset($grouped[$package_id])) {
                $grouped[$package_id] = array();
            }
            $grouped[$package_id][] = $row;
        }

        return $grouped;
    }

    private function Read_Dashboard_Package_Bookings($package_ids)
    {
        if (empty($package_ids)) {
            return array();
        }

        $rows = $this->db
            ->select('
                costing_bookings.id,
                costing_bookings.package_id,
                costing_bookings.travel_date,
                costing_bookings.adult_count,
                costing_bookings.child_count,
                costing_bookings.total_pax,
                costing_bookings.status,
                costing_booking_financials.margin_percentage,
                costing_booking_financials.selling_price_per_pax,
                costing_booking_financials.total_revenue,
                costing_booking_financials.total_cost,
                costing_booking_financials.total_profit
            ')
            ->join('costing_booking_financials', 'costing_booking_financials.booking_id = costing_bookings.id', 'left')
            ->where_in('costing_bookings.package_id', $package_ids)
            ->order_by('costing_bookings.package_id', 'ASC')
            ->order_by('costing_bookings.id', 'DESC')
            ->get('costing_bookings')
            ->result_array();

        $grouped = array();
        foreach ($rows as $row) {
            $package_id = (int) $row['package_id'];
            $row['booking_number'] = $this->Build_Booking_Number($row);
            $row['status_label'] = ucfirst((string) $row['status']);
            $row['adult_count'] = (int) $row['adult_count'];
            $row['child_count'] = (int) $row['child_count'];
            $row['total_pax'] = (int) $row['total_pax'];
            $row['margin_percentage'] = round((float) $row['margin_percentage'], 2);
            $row['selling_price_per_pax'] = round((float) $row['selling_price_per_pax'], 2);
            $row['total_revenue'] = round((float) $row['total_revenue'], 2);
            $row['total_cost'] = round((float) $row['total_cost'], 2);
            $row['total_profit'] = round((float) $row['total_profit'], 2);

            if (!isset($grouped[$package_id])) {
                $grouped[$package_id] = array();
            }
            $grouped[$package_id][] = $row;
        }

        return $grouped;
    }

    private function Read_Package($package_id)
    {
        return $this->db->where('id', (int) $package_id)->get('costing_packages')->row_array();
    }

    private function Read_Booking($booking_id, $package_id = null)
    {
        $this->db->select('
            costing_bookings.id,
            costing_bookings.package_id,
            costing_bookings.travel_date,
            costing_bookings.adult_count,
            costing_bookings.child_count,
            costing_bookings.total_pax,
            costing_bookings.status AS booking_status
        ');
        $this->db->where('costing_bookings.id', (int) $booking_id);

        if ($package_id !== null) {
            $this->db->where('costing_bookings.package_id', (int) $package_id);
        }

        return $this->db->get('costing_bookings')->row_array();
    }

    private function Read_Bookings_For_Package($package_id)
    {
        $rows = $this->db
            ->select('id, travel_date, total_pax, status')
            ->where('package_id', (int) $package_id)
            ->order_by('id', 'DESC')
            ->get('costing_bookings')
            ->result_array();

        foreach ($rows as &$row) {
            $row['booking_number'] = $this->Build_Booking_Number($row);
            $row['status_label'] = ucfirst((string) $row['status']);
        }
        unset($row);

        return $rows;
    }

    private function Read_Latest_Booking_For_Package($package_id)
    {
        $this->db->select('
            costing_bookings.id,
            costing_bookings.package_id,
            costing_bookings.travel_date,
            costing_bookings.adult_count,
            costing_bookings.child_count,
            costing_bookings.total_pax,
            costing_bookings.status AS booking_status
        ');
        $this->db->where('costing_bookings.package_id', (int) $package_id);
        $this->db->order_by('costing_bookings.id', 'DESC');

        return $this->db->get('costing_bookings')->row_array();
    }

    private function Read_Package_Items($package_id)
    {
        return $this->db
            ->select('
                costing_package_items.id,
                costing_package_items.package_id,
                costing_package_items.name,
                costing_package_items.description,
                costing_package_items.category,
                costing_package_items.cost_type,
                costing_package_items.default_unit_price,
                costing_package_items.currency_id,
                costing_currencies.code AS currency
            ')
            ->join('costing_currencies', 'costing_currencies.id = costing_package_items.currency_id')
            ->where('costing_package_items.package_id', (int) $package_id)
            ->order_by('costing_package_items.id', 'ASC')
            ->get('costing_package_items')
            ->result_array();
    }

    private function Read_Package_Items_By_Ids($package_id, $item_ids)
    {
        if (empty($item_ids)) {
            return array();
        }

        return $this->db
            ->select('id, package_id, name, description, category, cost_type, default_unit_price, currency_id')
            ->where('package_id', (int) $package_id)
            ->where_in('id', $item_ids)
            ->order_by('id', 'ASC')
            ->get('costing_package_items')
            ->result_array();
    }

    private function Read_Booking_Items($booking_id, $base_currency_id, $travel_date = null)
    {
        $items = $this->db
            ->select('
                costing_booking_items.id,
                costing_booking_items.booking_id,
                costing_booking_items.package_item_id,
                costing_booking_items.name,
                costing_booking_items.category,
                COALESCE(costing_booking_items.pax_type, "") AS pax_type,
                costing_booking_items.quantity,
                costing_booking_items.unit_count,
                costing_booking_items.unit_price,
                costing_booking_items.currency_id,
                costing_booking_items.total_amount,
                COALESCE(costing_booking_items.remark, "") AS remark,
                costing_currencies.code AS currency
            ')
            ->join('costing_currencies', 'costing_currencies.id = costing_booking_items.currency_id')
            ->where('costing_booking_items.booking_id', (int) $booking_id)
            ->order_by('costing_booking_items.id', 'ASC')
            ->get('costing_booking_items')
            ->result_array();

        foreach ($items as &$item) {
            $item['exchange_rate'] = $this->Resolve_Exchange_Rate((int) $item['currency_id'], $base_currency_id, $travel_date);
            $item['base_total'] = round((float) $item['total_amount'] * (float) $item['exchange_rate'], 2);
        }
        unset($item);

        return $items;
    }

    private function Read_Currencies($filters = array())
    {
        $this->db->select('id, code, name, symbol');

        if (!empty($filters['code'])) {
            $this->db->like('code', $filters['code']);
        }

        if (!empty($filters['name'])) {
            $this->db->like('name', $filters['name']);
        }

        if ($filters && array_key_exists('symbol', $filters) && $filters['symbol'] !== '') {
            $this->db->like('symbol', $filters['symbol']);
        }

        return $this->db
            ->order_by('code', 'ASC')
            ->get('costing_currencies')
            ->result_array();
    }

    private function Read_Latest_Exchange_Rates($filters = array())
    {
        $where = "
            WHERE cer.from_currency_id <> cer.to_currency_id
                AND NOT EXISTS (
                    SELECT 1
                    FROM costing_exchange_rates newer
                    WHERE newer.from_currency_id = cer.from_currency_id
                        AND newer.to_currency_id = cer.to_currency_id
                        AND (
                            newer.valid_from > cer.valid_from
                            OR (newer.valid_from = cer.valid_from AND newer.id > cer.id)
                        )
                )
        ";

        $params = array();

        if (!empty($filters['from_currency_id'])) {
            $where .= " AND cer.from_currency_id = ? ";
            $params[] = (int) $filters['from_currency_id'];
        }

        if (!empty($filters['to_currency_id'])) {
            $where .= " AND cer.to_currency_id = ? ";
            $params[] = (int) $filters['to_currency_id'];
        }

        if (!empty($filters['valid_from'])) {
            $where .= " AND DATE(cer.valid_from) = ? ";
            $params[] = $filters['valid_from'];
        }

        return $this->db->query("
            SELECT
                cer.id,
                cer.from_currency_id,
                cer.to_currency_id,
                cer.unit_amount,
                cer.rate,
                ROUND(cer.unit_amount * cer.rate, 8) AS converted_amount,
                cer.valid_from,
                from_currency.code AS from_currency_code,
                to_currency.code AS to_currency_code
            FROM costing_exchange_rates cer
            INNER JOIN costing_currencies from_currency ON from_currency.id = cer.from_currency_id
            INNER JOIN costing_currencies to_currency ON to_currency.id = cer.to_currency_id
            " . $where . "
            ORDER BY from_currency.code ASC, to_currency.code ASC
        ", $params)->result_array();
    }

    private function Run_Safe_Delete($table, $key, $value, $fallback_message)
    {
        $original_db_debug = $this->db->db_debug;
        $this->db->db_debug = false;

        try {
            $success = $this->db->where($key, $value)->delete($table);
        } catch (Exception $exception) {
            $this->db->db_debug = $original_db_debug;
            return array('success' => false, 'message' => $fallback_message);
        }

        $this->db->db_debug = $original_db_debug;

        if ($success) {
            return array('success' => true, 'message' => null);
        }

        return array('success' => false, 'message' => $fallback_message);
    }

    private function Resolve_Exchange_Rate($from_currency_id, $to_currency_id, $travel_date = null)
    {
        $from_currency_id = (int) $from_currency_id;
        $to_currency_id = (int) $to_currency_id;

        if ($from_currency_id <= 0 || $to_currency_id <= 0) {
            return 0;
        }

        if ($from_currency_id === $to_currency_id) {
            return 1;
        }

        $this->db->select('rate');
        $this->db->where('from_currency_id', $from_currency_id);
        $this->db->where('to_currency_id', $to_currency_id);

        if (!empty($travel_date)) {
            $this->db->where('valid_from <=', $travel_date . ' 23:59:59');
        }

        $this->db->order_by('valid_from', 'DESC');
        $this->db->order_by('id', 'DESC');
        $row = $this->db->get('costing_exchange_rates')->row_array();

        if ($row) {
            return (float) $row['rate'];
        }

        $fallback = $this->db
            ->select('rate')
            ->where('from_currency_id', $from_currency_id)
            ->where('to_currency_id', $to_currency_id)
            ->order_by('valid_from', 'DESC')
            ->order_by('id', 'DESC')
            ->get('costing_exchange_rates')
            ->row_array();

        return $fallback ? (float) $fallback['rate'] : 0;
    }

    private function Build_Snapshot_Row_From_Template($item, $adult_count, $child_count, $total_pax)
    {
        $quantity = 1;
        $unit_count = 1;
        $pax_type = null;
        $remark = 'Generated from package template';

        switch ($item['cost_type']) {
            case 'per_adult':
                $quantity = $adult_count;
                $pax_type = 'adult';
                break;
            case 'per_child':
                $quantity = $child_count;
                $pax_type = 'child';
                break;
            case 'per_pax':
                $quantity = $total_pax;
                break;
            case 'per_unit':
                $quantity = 1;
                $unit_count = 1;
                $remark = 'Generated from package template. Update units if needed.';
                break;
            case 'fixed':
            default:
                $quantity = 1;
                break;
        }

        $total_amount = round($quantity * $unit_count * (float) $item['default_unit_price'], 2);

        return array(
            'package_item_id' => (int) $item['id'],
            'name' => $item['name'],
            'category' => $item['category'],
            'pax_type' => $pax_type,
            'quantity' => $quantity,
            'unit_count' => $unit_count,
            'unit_price' => round((float) $item['default_unit_price'], 2),
            'currency_id' => (int) $item['currency_id'],
            'total_amount' => $total_amount,
            'remark' => $remark,
        );
    }

    private function Normalize_Booking_Rows($rows)
    {
        $normalized = array();

        foreach ((array) $rows as $row) {
            $name = trim((string) (isset($row['name']) ? $row['name'] : ''));
            $category = trim((string) (isset($row['category']) ? $row['category'] : ''));
            $currency_id = (int) (isset($row['currency_id']) ? $row['currency_id'] : 0);

            if ($name === '' || $category === '' || $currency_id <= 0) {
                continue;
            }

            $normalized[] = array(
                'package_item_id' => !empty($row['package_item_id']) ? (int) $row['package_item_id'] : null,
                'name' => $name,
                'category' => $category,
                'pax_type' => in_array((string) $row['pax_type'], array('', 'adult', 'child'), true) ? (string) $row['pax_type'] : '',
                'quantity' => round(max(0, (float) $row['quantity']), 2),
                'unit_count' => round(max(0, (float) $row['unit_count']), 2),
                'unit_price' => round(max(0, (float) $row['unit_price']), 2),
                'currency_id' => $currency_id,
                'remark' => trim((string) (isset($row['remark']) ? $row['remark'] : '')),
            );
        }

        return $normalized;
    }

    private function Upsert_Booking_Financials($booking_id, $data)
    {
        $payload = array(
            'booking_id' => (int) $booking_id,
            'margin_percentage' => round(max(0, (float) $data['margin_percentage']), 2),
            'commissionable_per_pax' => round(max(0, (float) $data['commissionable_per_pax']), 2),
            'ad_hoc_per_pax' => round(max(0, (float) $data['ad_hoc_per_pax']), 2),
        );

        $exists = $this->db
            ->select('id')
            ->where('booking_id', (int) $booking_id)
            ->get('costing_booking_financials')
            ->row_array();

        if ($exists) {
            $this->db->where('booking_id', (int) $booking_id)->update('costing_booking_financials', $payload);
        } else {
            $payload = array_merge($payload, $this->Empty_Financials());
            $this->db->insert('costing_booking_financials', $payload);
        }
    }

    private function Calculate_Financials_From_Booking($booking, $booking_items, $base_currency_id)
    {
        $existing = $this->db
            ->where('booking_id', (int) $booking['id'])
            ->get('costing_booking_financials')
            ->row_array();

        $margin_percentage = $existing ? (float) $existing['margin_percentage'] : 0;
        $commissionable_per_pax = $existing ? (float) $existing['commissionable_per_pax'] : 0;
        $ad_hoc_per_pax = $existing ? (float) $existing['ad_hoc_per_pax'] : 0;
        $total_pax = max(1, (int) $booking['total_pax']);

        $total_cost = 0;
        foreach ($booking_items as $item) {
            $total_cost += (float) $item['base_total'];
        }

        $total_cost = round($total_cost, 2);
        $cost_per_pax = round($total_cost / $total_pax, 2);
        $markup_amount_total = round($total_cost * ($margin_percentage / 100), 2);
        $price_per_pax = round(($total_cost + $markup_amount_total) / $total_pax, 2);
        $total_per_pax = round($price_per_pax + $commissionable_per_pax + $ad_hoc_per_pax, 2);
        $selling_price_per_pax = round($total_per_pax, 2);
        $total_revenue = round($selling_price_per_pax * $total_pax, 2);
        $gross_profit = round($total_revenue - $total_cost, 2);

        return array(
            'margin_percentage' => $margin_percentage,
            'commissionable_per_pax' => $commissionable_per_pax,
            'ad_hoc_per_pax' => $ad_hoc_per_pax,
            'total_cost' => $total_cost,
            'cost_per_pax' => $cost_per_pax,
            'markup_amount_total' => $markup_amount_total,
            'price_per_pax' => $price_per_pax,
            'total_per_pax' => $total_per_pax,
            'selling_price_per_pax' => $selling_price_per_pax,
            'total_revenue' => $total_revenue,
            'gross_profit' => $gross_profit,
            'total_profit' => $gross_profit,
            'base_currency_id' => $base_currency_id,
        );
    }

    private function Read_Cost_Types()
    {
        return array(
            'per_pax' => 'Per Pax',
            'per_adult' => 'Per Adult',
            'per_child' => 'Per Child',
            'per_unit' => 'Per Unit',
            'fixed' => 'Fixed',
        );
    }

    private function Normalize_Status($status)
    {
        $status = strtolower(trim((string) $status));
        return in_array($status, array('draft', 'active', 'inactive'), true) ? $status : 'draft';
    }

    private function Normalize_Cost_Type($cost_type)
    {
        $cost_type = strtolower(trim((string) $cost_type));
        return array_key_exists($cost_type, $this->Read_Cost_Types()) ? $cost_type : 'fixed';
    }

    private function Build_Booking_Number($booking)
    {
        if (empty($booking['id']) || empty($booking['travel_date'])) {
            return 'N/A';
        }

        $year = date('Y', strtotime($booking['travel_date']));
        return 'CT-' . $year . '-' . str_pad($booking['id'], 4, '0', STR_PAD_LEFT);
    }

    private function Extract_Selected_Package_Item_Ids($booking_items)
    {
        $ids = array();

        foreach ($booking_items as $item) {
            if (!empty($item['package_item_id'])) {
                $ids[] = (int) $item['package_item_id'];
            }
        }

        return array_values(array_unique($ids));
    }

    private function Build_Category_Suggestions($package_items, $booking_items)
    {
        $categories = array();

        foreach (array_merge($package_items, $booking_items) as $row) {
            $category = trim((string) (isset($row['category']) ? $row['category'] : ''));
            if ($category !== '') {
                $categories[$category] = $category;
            }
        }

        ksort($categories);
        return array_values($categories);
    }

    private function Empty_Workspace_Data($base_currency_code)
    {
        return array(
            'package' => array(
                'id' => 0,
                'name' => '',
                'duration_days' => 1,
                'duration_nights' => 0,
                'description' => '',
                'status' => 'Draft',
                'status_value' => 'draft',
            ),
            'booking' => $this->Empty_Booking_Data(),
            'bookings' => array(),
            'package_items' => array(),
            'booking_items' => array(),
            'selected_package_item_ids' => array(),
            'category_suggestions' => array(),
            'financials' => $this->Empty_Financials(),
            'base_currency' => $base_currency_code,
            'has_booking' => false,
            'has_bookings' => false,
            'currencies' => $this->Read_Currencies(),
            'statuses' => array('draft', 'active', 'inactive'),
            'cost_types' => $this->Read_Cost_Types(),
            'latest_exchange_rates' => $this->Read_Latest_Exchange_Rates(),
        );
    }

    private function Empty_Booking_Data()
    {
        return array(
            'id' => 0,
            'booking_number' => 'No booking yet',
            'travel_date' => date('Y-m-d'),
            'adult_count' => 2,
            'child_count' => 0,
            'total_pax' => 2,
            'status' => 'Draft',
            'status_value' => 'draft',
        );
    }

    private function Empty_Financials()
    {
        return array(
            'margin_percentage' => 0,
            'commissionable_per_pax' => 0,
            'ad_hoc_per_pax' => 0,
            'total_cost' => 0,
            'cost_per_pax' => 0,
            'markup_amount_total' => 0,
            'price_per_pax' => 0,
            'total_per_pax' => 0,
            'selling_price_per_pax' => 0,
            'total_revenue' => 0,
            'gross_profit' => 0,
            'total_profit' => 0,
        );
    }
}
