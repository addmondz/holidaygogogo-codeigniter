<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Customer-facing Quotation PDF (Dompdf). Layout follows the 9 Sep 2026 feedback
 * screenshot: a branded header (embedded logo + company block) over a key/value
 * detail table (Tour Code, Package, Name, Travel Date + note, Total Pax), a
 * per-person HOTEL pricing table (twin/triple + single supplement), a FLIGHT
 * schedule table (date/sector/flight no/timing/duration) with a per-pax price and
 * fare notes, and a highlighted block of boilerplate footer notes.
 *
 * This document shows ONLY what the customer sees — raw cost, margin %, currency
 * snapshot, profit, the internal item list, combinations and the day-by-day
 * itinerary are intentionally NOT rendered here.
 */
$this->load->helper('costing_quote');

$company    = isset($company) && is_array($company) ? $company : array();
$booking    = isset($booking) ? $booking : array();
$package    = isset($package) ? $package : array();
$quote_hotels  = isset($quote_hotels) && is_array($quote_hotels) ? $quote_hotels : array();
$quote_flights = isset($quote_flights) && is_array($quote_flights) ? $quote_flights : array();
$quote_meta    = isset($quote_meta) && is_array($quote_meta) ? $quote_meta : array();
$public_ref    = isset($public_ref) ? $public_ref : '';

$CompanyName    = isset($company['Name']) ? $company['Name'] : 'HolidayGoGoGo';
$CompanyReg     = isset($company['RegistrationNumber']) ? $company['RegistrationNumber'] : '';
$CompanyLicense = isset($company['LicenseNumber']) ? $company['LicenseNumber'] : '';
// Prefer the dated address (matches Booking Confirmation PDF); fall back to DB.
$CompanyAddress = isset($CompanyAddress) && $CompanyAddress !== ''
    ? $CompanyAddress
    : (isset($company['Address']) ? $company['Address'] : '');
$CompanyWebsite = isset($company['Website']) ? $company['Website'] : '';

$PackageName  = isset($package['name']) && $package['name'] !== '' ? $package['name'] : '-';
$TourCode     = isset($package['tour_code']) && $package['tour_code'] !== '' ? $package['tour_code'] : '-';
$CustomerName = isset($package['customer_name']) && $package['customer_name'] !== '' ? $package['customer_name'] : '-';
$TotalPax     = (int) (isset($booking['total_pax']) ? $booking['total_pax'] : 0);

// Travel Date as a start–end range (matches the wizard's start/end pair).
$TravelStart = !empty($booking['travel_date']) && strtotime($booking['travel_date'])
    ? date('d M Y', strtotime($booking['travel_date']))
    : '-';
$TravelEnd = !empty($booking['travel_date_end']) && strtotime($booking['travel_date_end'])
    ? date('d M Y', strtotime($booking['travel_date_end']))
    : '';
$TravelDate = ($TravelEnd !== '' && $TravelEnd !== $TravelStart)
    ? ($TravelStart . ' - ' . $TravelEnd)
    : $TravelStart;

// Quote-level free text.
$qm = $quote_meta;
$qv = function ($key) use ($qm) {
    return isset($qm[$key]) && $qm[$key] !== null ? trim((string) $qm[$key]) : '';
};
$PricingBasis   = $qv('quote_pricing_basis');
$TravelDateNote = $qv('quote_travel_date_note');
$HotelNote      = $qv('quote_hotel_note');
$FlightTitle    = $qv('quote_flight_title') !== '' ? $qv('quote_flight_title') : 'FLIGHT SCHEDULE';
$FlightFareNote = $qv('quote_flight_fare_note');
$FlightExpiry   = $qv('quote_flight_expiry');
$FlightPrice    = (isset($qm['quote_flight_price']) && $qm['quote_flight_price'] !== null && $qm['quote_flight_price'] !== '')
    ? (float) $qm['quote_flight_price'] : null;
$FooterLines    = costing_quote_footer_note_lines($qv('quote_footer_notes'));

$money = function ($value) {
    return 'RM ' . number_format((float) $value, 2, '.', ',');
};

// Embedded logo (same asset the Booking Confirmation uses).
$logoSrc = '';
$logoPath = FCPATH . 'assets/image/pdflogo-new.jpeg';
if (is_file($logoPath)) {
    $logoSrc = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logoPath));
}
?>
<html><head>
    <meta charset="utf-8">
    <title>Quotation <?php echo html_escape($public_ref); ?></title>
</head><style type="text/css">
    body {
        font-family: "Segoe UI", Arial, sans-serif !important;
        font-size: 13px;
        padding-left: 10px;
        padding-right: 10px;
    }
    h1 { font-size: 24px; text-align: center; margin-top: 0px; margin-bottom: 5px; }
    h3 { font-size: 16px; margin: 0px; margin-bottom: 5px; }
    p { margin-top: 0px; margin-bottom: 2px; }
    .margin-top-10 { margin-top: 10px; }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .small-font { font-size: 13px; }
    .red { color: #c0392b; }
    /* Key/value detail table + data tables share this bordered look. */
    table.kv, table.data { width: 100%; border-collapse: collapse; }
    table.kv td { border: 1px solid #444; padding: 5px 8px; font-size: 12px; vertical-align: top; }
    table.kv td.k { width: 26%; font-weight: bold; background: #f2f2f2; }
    table.data th, table.data td { border: 1px solid #444; padding: 5px 8px; font-size: 12px; }
    table.data th { background: #d9d9d9; text-align: center; font-weight: bold; }
    .section-title { font-weight: bold; text-align: center; margin: 14px 0 6px 0; }
    .note-row td { background: #f7f7f7; font-style: italic; }
    .footer-notes { margin-top: 14px; }
    .footer-notes p { background: #fff3a3; font-style: italic; font-size: 11px; padding: 2px 4px; margin-bottom: 4px; }
    /* Reserve top/bottom margin on every page for the repeating header/footer. */
    @page { margin: 165px 40px 70px 40px; }
    #pdf-header { position: fixed; top: -145px; left: 0px; right: 0px; height: 135px; }
    #pdf-footer { position: fixed; bottom: -55px; left: 0px; right: 0px; height: 45px; }
</style><body>
    <!-- Repeats on every page (fixed) -->
    <div id="pdf-header">
        <table>
            <tr>
                <?php if ($logoSrc !== '') { ?>
                    <td style="width:15%"><img src="<?php echo $logoSrc; ?>" style="width:160px;"></td>
                <?php } ?>
                <td style="width:85%; text-align: center;">
                    <h1><?php echo html_escape($CompanyName); ?></h1>
                    <small>(Co. Reg. No. - <?php echo html_escape($CompanyReg); ?> | Travel Agent License No. - <?php echo html_escape($CompanyLicense); ?>)</small>
                    <p class="text-center small-font"><small>Address : <?php echo html_escape($CompanyAddress); ?></small></p>
                    <p class="text-center small-font"><small>Website : <?php echo html_escape($CompanyWebsite); ?></small></p>
                </td>
            </tr>
        </table>
        <hr class="margin-top-10">
    </div>

    <!-- Repeats on every page (fixed) -->
    <div id="pdf-footer">
        <hr style="margin-bottom:5px;">
        <p style="font-size:12px; text-align:center;"><i>This Is A Computer Generated Quotation. No Signature Required.</i></p>
    </div>

    <table style="width:100%;">
        <tr>
            <th style="width:65%;"><h3 class="text-right">Custom Quotation</h3></th>
            <th style="width:35%; font-weight:700; text-align:right;">No : <?php echo html_escape($public_ref); ?></th>
        </tr>
    </table>

    <!-- Key / value details -->
    <table class="kv">
        <tr>
            <td class="k">TOUR CODE</td>
            <td><?php echo html_escape($TourCode); ?></td>
        </tr>
        <tr>
            <td class="k">TOUR PACKAGE</td>
            <td><?php echo html_escape($PackageName); ?></td>
        </tr>
        <tr>
            <td class="k">NAME</td>
            <td><?php echo html_escape($CustomerName); ?></td>
        </tr>
        <tr>
            <td class="k">TRAVEL DATE</td>
            <td>
                <?php echo html_escape($TravelDate); ?>
                <?php if ($TravelDateNote !== '') { ?>
                    <span class="red">Note: <?php echo html_escape($TravelDateNote); ?></span>
                <?php } ?>
            </td>
        </tr>
        <tr>
            <td class="k">TOTAL PAX</td>
            <td><?php echo $TotalPax > 0 ? ($TotalPax . 'pax') : '-'; ?></td>
        </tr>
    </table>

    <!-- Hotel pricing -->
    <div class="section-title">
        Pricing per person<?php if ($PricingBasis !== '') { ?> (Quoted based on <span class="red"><?php echo html_escape($PricingBasis); ?></span>)<?php } ?>
    </div>
    <table class="data">
        <thead>
            <tr>
                <th style="text-align:left;">Hotel</th>
                <th style="width:22%;">Twin / Triple</th>
                <th style="width:22%;">Single Supp</th>
            </tr>
        </thead>
        <tbody>
            <?php $has_hotel = false; ?>
            <?php foreach ($quote_hotels as $h) {
                $hname = trim((string) (isset($h['hotel_name']) ? $h['hotel_name'] : ''));
                $twin  = isset($h['twin_triple_price']) && $h['twin_triple_price'] !== null && $h['twin_triple_price'] !== '' ? $h['twin_triple_price'] : null;
                $single = isset($h['single_supp_price']) && $h['single_supp_price'] !== null && $h['single_supp_price'] !== '' ? $h['single_supp_price'] : null;
                if ($hname === '' && $twin === null && $single === null) { continue; }
                $has_hotel = true; ?>
                <tr>
                    <td><?php echo html_escape($hname !== '' ? $hname : '-'); ?></td>
                    <td class="text-center"><?php echo $twin !== null ? $money($twin) : '-'; ?></td>
                    <td class="text-center"><?php echo $single !== null ? $money($single) : '-'; ?></td>
                </tr>
            <?php } ?>
            <?php if (!$has_hotel) { ?>
                <tr><td colspan="3" class="text-center">Hotel pricing to be confirmed.</td></tr>
            <?php } ?>
            <?php if ($HotelNote !== '') { ?>
                <tr class="note-row"><td colspan="3" class="text-center"><?php echo html_escape($HotelNote); ?></td></tr>
            <?php } ?>
        </tbody>
    </table>

    <!-- Flight schedule -->
    <div class="section-title"><?php echo html_escape($FlightTitle); ?></div>
    <table class="data">
        <thead>
            <tr>
                <th>Travel Date</th>
                <th>Sector</th>
                <th>Flight No</th>
                <th>Timing</th>
                <th>Duration</th>
            </tr>
        </thead>
        <tbody>
            <?php $has_flight = false; ?>
            <?php foreach ($quote_flights as $f) {
                $cells = array(
                    isset($f['travel_date']) ? $f['travel_date'] : '',
                    isset($f['sector']) ? $f['sector'] : '',
                    isset($f['flight_no']) ? $f['flight_no'] : '',
                    isset($f['timing']) ? $f['timing'] : '',
                    isset($f['duration']) ? $f['duration'] : '',
                );
                $is_blank = true;
                foreach ($cells as $c) { if (trim((string) $c) !== '') { $is_blank = false; break; } }
                if ($is_blank) { continue; }
                $has_flight = true; ?>
                <tr>
                    <?php foreach ($cells as $c) { ?>
                        <td class="text-center"><?php echo html_escape(trim((string) $c) !== '' ? $c : '-'); ?></td>
                    <?php } ?>
                </tr>
            <?php } ?>
            <?php if (!$has_flight) { ?>
                <tr><td colspan="5" class="text-center">Flight schedule to be confirmed.</td></tr>
            <?php } ?>
            <?php if ($FlightPrice !== null) { ?>
                <tr>
                    <td colspan="4" style="font-weight:bold; text-align:right;">Price per person (Adult / Child)</td>
                    <td class="text-center" style="font-weight:bold;"><?php echo $money($FlightPrice); ?></td>
                </tr>
            <?php } ?>
            <?php if ($FlightFareNote !== '') { ?>
                <tr class="note-row"><td colspan="5" class="text-center">Fare quote includes <?php echo html_escape($FlightFareNote); ?></td></tr>
            <?php } ?>
            <?php if ($FlightExpiry !== '') { ?>
                <tr class="note-row"><td colspan="5" class="text-center" style="font-weight:bold; font-style:normal;"><?php echo html_escape($FlightExpiry); ?></td></tr>
            <?php } ?>
        </tbody>
    </table>

    <!-- Boilerplate footer notes (highlighted) -->
    <?php if (!empty($FooterLines)) { ?>
        <div class="footer-notes">
            <?php foreach ($FooterLines as $line) { ?>
                <p><?php echo html_escape($line); ?></p>
            <?php } ?>
        </div>
    <?php } ?>
</body></html>
