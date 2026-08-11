<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * TEMPORARY dev seeder for the Costing -> Quotation flow. CLI only.
 *   php index.php Costing_Seed             -> full package + snapshot + itinerary
 *   php index.php Costing_Seed/items       -> item master rows
 *   php index.php Costing_Seed/cleanup     -> remove everything this seeder made
 * Safe to delete after testing.
 */
class Costing_Seed extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!$this->input->is_cli_request()) {
            show_error('CLI only.', 403);
            return;
        }
        $this->load->model('Costing_Model');
        $this->load->model('Costing_Item_Model');
    }

    private function currency_id($code, $name)
    {
        $code = strtoupper($code);
        $row = $this->db->select('id')->where('code', $code)->get('costing_currencies')->row_array();
        if ($row) {
            return (int) $row['id'];
        }
        $this->db->insert('costing_currencies', array('code' => $code, 'name' => $name));
        return (int) $this->db->insert_id();
    }

    // Upsert a Costing Currency rate (costing_exchange_rates): 1 <from> = <rate> MYR.
    private function set_rate($from_id, $to_myr_id, $rate)
    {
        $this->db->where('from_currency_id', $from_id)->where('to_currency_id', $to_myr_id)->delete('costing_exchange_rates');
        $this->db->insert('costing_exchange_rates', array(
            'from_currency_id' => $from_id,
            'to_currency_id'   => $to_myr_id,
            'unit_amount'      => 1,
            'rate'             => $rate,
            'bank_charges_myr' => 0,
            'valid_from'       => date('Y-m-d H:i:s'),
        ));
    }

    public function items()
    {
        $myr = $this->currency_id('MYR', 'Malaysian Ringgit');
        $usd = $this->currency_id('USD', 'US Dollar');
        $sgd = $this->currency_id('SGD', 'Singapore Dollar');

        $items = array(
            array('Return Flight (Economy)', 'flight',        $usd, 320.00),
            array('Domestic Transfer Flight', 'flight',        $myr, 250.00),
            array('4-Star Hotel (per night)', 'accommodation', $sgd, 180.00),
            array('Beach Resort (per night)',  'accommodation', $usd, 150.00),
            array('Airport Transfer (van)',    'other',         $myr, 120.00),
            array('Travel Insurance',          'other',         $myr,  45.00),
            array('Tour Leader (per day)',     'tour_leader',   $myr, 400.00),
            array('Local Guide (per day)',     'tour_leader',   $usd,  80.00),
            array('SIM Card',                  'miscellaneous', $myr,  25.00),
            array('Tips & Gratuities',         'miscellaneous', $myr,  60.00),
        );
        $created = 0;
        foreach ($items as $it) {
            if ($this->Costing_Item_Model->Save_Item(array('id' => 0, 'name' => $it[0], 'category' => $it[1], 'default_currency_id' => $it[2], 'default_unit_cost' => $it[3]))) {
                $created++;
            }
        }
        echo "Seeded {$created} costing items.\n";
        echo "Listing : " . base_url('Costing_Item') . "\n";
    }

    public function index()
    {
        $myr = $this->currency_id('MYR', 'Malaysian Ringgit');
        $usd = $this->currency_id('USD', 'US Dollar');
        $sgd = $this->currency_id('SGD', 'Singapore Dollar');

        // Costing Currency rates (the snapshot now pre-fills from THESE).
        $this->set_rate($usd, $myr, 4.50); // 1 USD = 4.50 MYR
        $this->set_rate($sgd, $myr, 3.50); // 1 SGD = 3.50 MYR

        $this->items();

        $package_id = $this->Costing_Model->Save_Package(array(
            'id' => 0, 'name' => 'Bali 4D3N Getaway (DUMMY)', 'duration_days' => 4,
            'duration_nights' => 3, 'description' => 'Seeded dummy package for testing.', 'status' => 'active',
        ));

        $rows = array(
            array('include' => 1, 'name' => 'Return Flights', 'category' => 'Flight',        'pax_type' => '', 'quantity' => 2, 'unit_count' => 1, 'unit_price' => 300, 'currency_id' => $usd, 'remark' => ''),
            array('include' => 1, 'name' => 'Hotel 3 Nights', 'category' => 'Accommodation', 'pax_type' => '', 'quantity' => 3, 'unit_count' => 1, 'unit_price' => 150, 'currency_id' => $sgd, 'remark' => ''),
            array('include' => 1, 'name' => 'Tour Leader',    'category' => 'Tour Leader',   'pax_type' => '', 'quantity' => 1, 'unit_count' => 1, 'unit_price' => 400, 'currency_id' => $myr, 'remark' => ''),
            array('include' => 1, 'name' => 'Sundry',         'category' => 'Miscellaneous', 'pax_type' => '', 'quantity' => 1, 'unit_count' => 1, 'unit_price' => 120, 'currency_id' => $myr, 'remark' => ''),
        );

        $booking_id = $this->Costing_Model->Generate_Booking_Snapshot($package_id, array(
            'booking_id' => 0, 'travel_date' => date('Y-m-d', strtotime('+30 days')),
            'adult_count' => 2, 'child_count' => 0, 'status' => 'active', 'rows' => $rows,
            'margin_percentage' => 20, 'commissionable_per_pax' => 0, 'ad_hoc_per_pax' => 0,
        ));

        if ($booking_id <= 0) { echo "FAILED to generate snapshot.\n"; return; }

        $this->Costing_Model->Save_Itinerary_Days($package_id, array(
            array('day_number' => 1, 'title' => 'Arrival in Bali', 'description' => 'Airport pickup, hotel check-in, welcome dinner.'),
            array('day_number' => 2, 'title' => 'Ubud & Rice Terraces', 'description' => 'Full-day cultural tour with lunch.'),
            array('day_number' => 3, 'title' => 'Beach & Leisure', 'description' => 'Free morning, afternoon water sports.'),
            array('day_number' => 4, 'title' => 'Departure', 'description' => 'Breakfast and transfer to airport.'),
        ));

        $fin = $this->db->where('booking_id', $booking_id)->get('costing_booking_financials')->row_array();
        $snap = $this->db->where('costing_booking_id', $booking_id)->get('costing_snapshot_rates')->result_array();

        echo "=== Costing dummy seeded ===\n";
        echo "Package {$package_id} / Snapshot {$booking_id}\n";
        echo "Costing Currency rates set: 1 USD = 4.50 MYR, 1 SGD = 3.50 MYR\n";
        echo "Snapshot rates prefilled FROM Costing Currency:\n";
        foreach ($snap as $s) { echo "  - {$s['currency_code']} => {$s['rate_to_myr']} MYR\n"; }
        echo "Total cost (MYR)   : " . ($fin ? $fin['total_cost'] : '?') . "\n";
        echo "Total selling (MYR): " . ($fin ? $fin['total_revenue'] : '?') . "\n";
        echo "Total profit (MYR) : " . ($fin ? $fin['total_profit'] : '?') . "\n";
        echo "Workspace : " . base_url('Costing/Package/' . $package_id . '?tab=bookings') . "\n";
        echo "Quotation : " . base_url('Costing/Quotation/' . $package_id) . "\n";
    }

    public function cleanup()
    {
        $pkg = $this->db->select('id')->where_in('name', array('Bali 4D3N Getaway (DUMMY)', 'Single-Snapshot Test'))->get('costing_packages')->result_array();
        $pkg_removed = 0;
        foreach ($pkg as $p) { if ($this->Costing_Model->Delete_Package((int) $p['id'])) { $pkg_removed++; } }

        $item_names = array('Return Flight (Economy)', 'Domestic Transfer Flight', '4-Star Hotel (per night)', 'Beach Resort (per night)', 'Airport Transfer (van)', 'Travel Insurance', 'Tour Leader (per day)', 'Local Guide (per day)', 'SIM Card', 'Tips & Gratuities');
        $this->db->where_in('name', $item_names)->delete('costing_items');
        $items_removed = $this->db->affected_rows();

        // Remove the seeded USD/SGD -> MYR Costing Currency rates.
        $myr = $this->currency_id('MYR', 'Malaysian Ringgit');
        $usd = $this->currency_id('USD', 'US Dollar');
        $sgd = $this->currency_id('SGD', 'Singapore Dollar');
        $this->db->where('to_currency_id', $myr)->where_in('from_currency_id', array($usd, $sgd))->delete('costing_exchange_rates');
        $rates_removed = $this->db->affected_rows();

        echo "Removed packages: {$pkg_removed}\n";
        echo "Removed items   : {$items_removed}\n";
        echo "Removed rates   : {$rates_removed}\n";
        echo "Currencies kept (shared master data).\n";
    }
}
