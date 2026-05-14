<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * E-invoice submitted notification email.
 *
 * Variables:
 *   $admin_name, $booking_id, $booking_number, $customer_name,
 *   $pax_count, $net_total (formatted), $submitted_at, $booking_url
 *   $is_admin_edit (optional bool), $edited_by_name (optional string)
 */
$is_admin_edit  = isset($is_admin_edit) ? (bool)$is_admin_edit : false;
$edited_by_name = isset($edited_by_name) ? $edited_by_name : '';
$heading = $is_admin_edit ? 'E-Invoice Request Updated by Admin' : 'E-Invoice Request Submitted';
$intro   = $is_admin_edit
    ? ('An admin' . ($edited_by_name !== '' ? ' (' . htmlspecialchars($edited_by_name) . ')' : '')
        . ' has updated a submitted e-invoice request. Latest details below:')
    : 'A customer has just submitted an e-invoice request. Details below:';
$timestamp_label = $is_admin_edit ? 'Last updated at' : 'Submitted at';
?><!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($heading) ?></title>
</head>
<body style="margin:0;padding:24px;background:#f5f6f8;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#333;">
    <table cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:6px;">
        <tr>
            <td style="padding:24px;">
                <h2 style="margin:0 0 16px 0;font-size:18px;color:#111827;"><?= htmlspecialchars($heading) ?></h2>
                <p style="margin:0 0 16px 0;">Hi <?= htmlspecialchars($admin_name) ?>,</p>
                <p style="margin:0 0 16px 0;"><?= $intro ?></p>

                <table cellpadding="6" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;margin:8px 0 20px 0;">
                    <tr>
                        <td style="border-bottom:1px solid #f0f0f0;width:140px;color:#6b7280;"><strong>Booking #</strong></td>
                        <td style="border-bottom:1px solid #f0f0f0;"><?= htmlspecialchars($booking_number) ?></td>
                    </tr>
                    <tr>
                        <td style="border-bottom:1px solid #f0f0f0;color:#6b7280;"><strong>Customer</strong></td>
                        <td style="border-bottom:1px solid #f0f0f0;"><?= htmlspecialchars($customer_name) ?></td>
                    </tr>
                    <tr>
                        <td style="border-bottom:1px solid #f0f0f0;color:#6b7280;"><strong>Pax count</strong></td>
                        <td style="border-bottom:1px solid #f0f0f0;"><?= (int)$pax_count ?></td>
                    </tr>
                    <tr>
                        <td style="border-bottom:1px solid #f0f0f0;color:#6b7280;"><strong>Net total</strong></td>
                        <td style="border-bottom:1px solid #f0f0f0;">RM <?= htmlspecialchars($net_total) ?></td>
                    </tr>
                    <tr>
                        <td style="color:#6b7280;"><strong><?= htmlspecialchars($timestamp_label) ?></strong></td>
                        <td><?= htmlspecialchars($submitted_at) ?></td>
                    </tr>
                </table>

                <p style="margin:0 0 24px 0;">
                    <a href="<?= htmlspecialchars($booking_url) ?>"
                       style="display:inline-block;background:#0d6efd;color:#ffffff;padding:10px 18px;text-decoration:none;border-radius:4px;font-weight:bold;">
                        Open booking
                    </a>
                </p>

                <p style="margin:0;color:#9ca3af;font-size:12px;">
                    This is an automated message from the HolidayGoGoGo system. Please do not reply to this email.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
