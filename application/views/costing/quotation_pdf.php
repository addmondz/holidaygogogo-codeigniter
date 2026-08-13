<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Customer-facing Quotation PDF (Dompdf). Layout mirrors the Booking Confirmation
 * PDF (application/views/booking/booking_confirmation.php): embedded logo header,
 * company block, title + No. row, a details table, itemised rows (here: the per-day
 * itinerary), a per-item "Package Includes" list with each line's selling price,
 * and a totals footer. All prices are the customer-facing selling price in MYR —
 * raw cost, margin %, currency snapshot, and profit stay internal and are NOT rendered.
 */
$company    = isset($company) && is_array($company) ? $company : array();
$booking    = isset($booking) ? $booking : array();
$package    = isset($package) ? $package : array();
$itinerary  = isset($itinerary) ? $itinerary : array();
$items      = isset($items) ? $items : array();
$financials = isset($financials) ? $financials : array();
$public_ref = isset($public_ref) ? $public_ref : '';

$CompanyName    = isset($company['Name']) ? $company['Name'] : 'HolidayGoGoGo';
$CompanyReg     = isset($company['RegistrationNumber']) ? $company['RegistrationNumber'] : '';
$CompanyLicense = isset($company['LicenseNumber']) ? $company['LicenseNumber'] : '';
$CompanyAddress = isset($company['Address']) ? $company['Address'] : '';
$CompanyWebsite = isset($company['Website']) ? $company['Website'] : '';

$PackageName   = isset($package['name']) ? $package['name'] : '-';
$TourCode      = isset($package['tour_code']) && $package['tour_code'] !== '' ? $package['tour_code'] : '-';
$DurationDays  = (int) (isset($package['duration_days']) ? $package['duration_days'] : 0);
$DurationNights = (int) (isset($package['duration_nights']) ? $package['duration_nights'] : 0);
$TotalPax      = (int) (isset($booking['total_pax']) ? $booking['total_pax'] : 0);
$TotalSelling  = (float) (isset($financials['total_revenue']) ? $financials['total_revenue'] : 0);

$TravelDate = !empty($booking['travel_date']) && strtotime($booking['travel_date'])
    ? strtoupper(date('d M Y', strtotime($booking['travel_date'])))
    : '-';
$InsertDate = strtoupper(date('d M Y'));

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
    /* Reserve top/bottom margin on every page for the repeating header/footer. */
    @page { margin: 165px 40px 95px 40px; }
    #pdf-header { position: fixed; top: -145px; left: 0px; right: 0px; height: 135px; }
    #pdf-footer { position: fixed; bottom: -78px; left: 0px; right: 0px; height: 68px; }
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
            <th style="width:65%;"><h3 class="text-right">QUOTATION</h3></th>
            <th style="width:35%; font-weight:700; text-align:right;">No : <?php echo html_escape($public_ref); ?></th>
        </tr>
    </table>

    <table style="width:100%; font-size:12px;">
        <tr>
            <td style="width:14%;">Tour Package</td>
            <td style="width:1%;"> : </td>
            <td style="width:35%;"><b><?php echo html_escape($PackageName); ?></b></td>
            <td style="width:16%;"><b>Travel Date</b></td>
            <td style="width:1%;"> : </td>
            <td style="width:25%;"><?php echo html_escape($TravelDate); ?></td>
        </tr>
        <tr>
            <td>Duration</td>
            <td> : </td>
            <td><?php echo $DurationDays; ?> Days / <?php echo $DurationNights; ?> Nights</td>
            <td><b>No. Of Pax</b></td>
            <td> : </td>
            <td><?php echo $TotalPax; ?></td>
        </tr>
        <tr>
            <td>Date</td>
            <td> : </td>
            <td><?php echo html_escape($InsertDate); ?></td>
            <td><b>Tour Code</b></td>
            <td> : </td>
            <td><?php echo html_escape($TourCode); ?></td>
        </tr>
    </table>

    <?php if (!empty($items)) { ?>
        <hr style="margin-bottom:0px;">
        <table style="width:100%; font-size:13px;">
            <tr style="font-weight:700;">
                <td style="width:8%;">No.</td>
                <td style="width:62%;">Package Includes</td>
                <td style="width:30%; text-align:right;">Price (RM)</td>
            </tr>
        </table>
        <hr style="margin-top:0px; margin-bottom:5px;">
        <table style="width:100%; font-size:12px; border-spacing:0;">
            <?php $item_no = 0; ?>
            <?php foreach ($items as $line) { ?>
                <?php if (trim((string) $line['name']) === '') { continue; } ?>
                <?php $item_no++; ?>
                <tr style="vertical-align:baseline;">
                    <td style="width:8%; padding:3px 0;"><?php echo $item_no; ?></td>
                    <td style="width:62%; padding:3px 0;"><?php echo html_escape($line['name']); ?></td>
                    <td style="width:30%; text-align:right; padding:3px 0;"><?php echo number_format((float) $line['selling'], 2, '.', ','); ?></td>
                </tr>
            <?php } ?>
        </table>
    <?php } ?>

    <!-- Totals pinned to the bottom of page 1 (mirrors the Booking Confirmation footer). -->
    <div style="position: absolute; bottom: 0; left: 0; right: 0;">
        <hr style="margin-bottom:5px; margin-top:10px;">
        <table style="width:100%; margin-bottom:10px;">
            <tr style="font-weight:bold;">
                <td style="width:60%;">&nbsp;</td>
                <td style="width:28%;">Total Package Price (RM):</td>
                <td style="width:12%; text-align:right; float:left;"><label><?php echo number_format($TotalSelling, 2, '.', ','); ?></label></td>
            </tr>
            <?php if ($TotalPax > 0) { ?>
            <tr>
                <td>&nbsp;</td>
                <td style="border-bottom: 1px solid black;">Price / Pax (RM):</td>
                <td style="text-align:right; float:left; border-bottom: 1px solid black;"><label><?php echo number_format($TotalSelling / max(1, $TotalPax), 2, '.', ','); ?></label></td>
            </tr>
            <?php } ?>
        </table>
    </div>

    <!-- PAGE 2: Itinerary -->
    <div style="page-break-before: always;">
        <h3 class="text-center" style="margin-bottom:5px;"><?php echo html_escape($PackageName); ?> — Itinerary</h3>
        <p class="text-center small-font" style="margin-bottom:8px;"><?php echo $DurationDays; ?> Days / <?php echo $DurationNights; ?> Nights</p>
        <hr style="margin-bottom:0px;">
        <table style="width:100%; font-size:13px;">
            <tr style="font-weight:700;">
                <td style="width:8%;">Day</td>
                <td style="width:32%;">Itinerary</td>
                <td style="width:60%;">Details</td>
            </tr>
        </table>
        <hr style="margin-top:0px;">

        <?php if (empty($itinerary)) { ?>
            <table style="width:100%; font-size:11px; border-spacing:5px;">
                <tr><td style="text-align:center; padding:10px;">Itinerary details to be confirmed.</td></tr>
            </table>
        <?php } else { ?>
            <?php foreach ($itinerary as $day) { ?>
                <table style="width:100%; font-size:11px; border-spacing:5px;">
                    <tr style="vertical-align:baseline;">
                        <td style="width:8%;">Day <?php echo (int) $day['day_number']; ?></td>
                        <td style="width:32%;"><strong><?php echo html_escape(isset($day['title']) ? $day['title'] : ''); ?></strong></td>
                        <td style="width:60%;"><?php echo nl2br(html_escape(isset($day['description']) ? $day['description'] : '')); ?></td>
                    </tr>
                </table>
            <?php } ?>
        <?php } ?>
    </div>
</body></html>
