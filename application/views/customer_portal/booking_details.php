<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Booking Details - <?php echo htmlspecialchars($booking['BookingNumber']); ?></title>
    <link href="<?php echo base_url('assets/image/favicon.png'); ?>" rel="icon">
    <link href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700" rel="stylesheet">
    <link href="<?php echo base_url('assets/css/plugins-bundle.css'); ?>" type="text/css" rel="stylesheet">
    <link href="<?php echo base_url('assets/css/style-bundle.css'); ?>" type="text/css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }



        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 50px 20px;
        }

        /* Page Header */
        .page-header {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .booking-title h1 {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .booking-title .booking-number {
            font-size: 16px;
            color: #666;
            font-weight: 500;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
        }

        .status-completed { background: #d4edda; color: #155724; }
        .status-pending-review { background: #e2d9f3; color: #6f42c1; }
        .status-pending-payment { background: #fff3cd; color: #856404; }
        .status-partial-payment { background: #d1ecf1; color: #0c5460; }
        .status-pending-tv { background: #f8d7da; color: #721c24; }
        .status-pending-gl { background: #ffeaa7; color: #856404; }
        .status-pending-travel { background: #e2e3e5; color: #383d41; }
        .status-ongoing { background: #cce5ff; color: #004085; }
        .status-overdue { background: #f8d7da; color: #721c24; }
        .status-cancelled { background: #f5c6cb; color: #721c24; }
        .status-unknown { background: #e9ecef; color: #495057; }

        /* Main Content */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        /* Booking Details Card */
        .details-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: #666;
            font-size: 14px;
        }

        .detail-value {
            color: #333;
            font-size: 15px;
            text-align: right;
            flex: 1;
            margin-left: 20px;
        }

        /* Documents Card */
        .documents-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .documents-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        @media (max-width: 768px) {
            .documents-grid {
                grid-template-columns: 1fr;
            }
        }

        .document-item {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: #333;
            display: block;
        }

        .document-item:hover {
            background: #e9ecef;
            border-color: #162447;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .document-item.disabled {
            background: #e9ecef;
            color: #999;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .document-item.disabled:hover {
            background: #e9ecef;
            border-color: #e0e0e0;
            box-shadow: none;
        }

        .document-icon {
            font-size: 32px;
            margin-bottom: 10px;
            color: #162447;
        }
        
        .document-icon i {
            font-size: 64px;
        }

        .document-name {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .document-action {
            font-size: 12px;
            color: #666;
        }

        /* Products Section */
        .products-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .product-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 3px solid #162447;
        }

        .product-item:last-child {
            margin-bottom: 0;
        }

        .product-name {
            font-weight: 600;
            color: #162447;
            margin-bottom: 8px;
            font-size: 16px;
        }

        .product-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            font-size: 14px;
            color: #666;
        }

        .product-detail {
            display: flex;
            /* justify-content: space-between; */
        }

        .product-detail .detail-label {
            margin-right: 10px;
        }

        /* Summary Card */
        .summary-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .summary-item:last-child {
            border-bottom: none;
            font-size: 18px;
            font-weight: 600;
            margin-top: 10px;
            padding-top: 15px;
            border-top: 2px solid #e0e0e0;
        }

        .summary-label {
            font-weight: 500;
            color: #666;
        }

        .summary-value {
            font-weight: 600;
            color: #333;
        }

        /* Back Button */
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: white;
            color: #333;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .back-button:hover {
            background: #f8f9fa;
            border-color: #162447;
        }

        /* Timeline and Payment History Layout */
        .timeline-payment-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        /* Timeline Section */
        .timeline-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .timeline {
            position: relative;
            padding-left: 27px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e0e0e0;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 30px;
        }

        .timeline-item:last-child {
            margin-bottom: 0;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -26px;
            top: 5px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: white;
            border: 3px solid #162447;
            z-index: 1;
        }

        .timeline-item.completed::before {
            background: #50C878;
            border-color: #50C878;
        }

        .timeline-item.pending::before {
            background: #FFBF00;
            border-color: #FFBF00;
        }

        .timeline-item.available::before {
            background: #162447;
            border-color: #162447;
        }

        .timeline-content {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            border-left: 3px solid #162447;
        }

        .timeline-item.completed .timeline-content {
            border-left-color: #50C878;
        }

        .timeline-item.pending .timeline-content {
            border-left-color: #FFBF00;
        }

        .timeline-date {
            font-size: 12px;
            color: #666;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .timeline-title {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .timeline-action {
            font-size: 12px;
            color: #666;
        }

        .timeline-action a {
            color: #162447;
            text-decoration: none;
            font-weight: 500;
        }

        .timeline-action a:hover {
            text-decoration: underline;
        }

        .timeline-icon {
            display: inline-block;
            margin-right: 8px;
            font-size: 14px;
        }

        /* Payment History Section */
        .payments-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .payments-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .payments-table thead {
            background: #f8f9fa;
        }

        .payments-table th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            color: #666;
            border-bottom: 2px solid #e0e0e0;
        }

        .payments-table td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
            color: #333;
        }

        .payments-table tbody tr:hover {
            background: #f8f9fa;
        }

        .payments-table tbody tr:last-child td {
            border-bottom: none;
        }

        .payment-credit {
            color: #155724;
            font-weight: 600;
        }

        .payment-debit {
            color: #721c24;
            font-weight: 600;
        }

        .payment-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .payment-summary {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
        }

        .payment-summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
        }

        .payment-summary-row.total {
            font-size: 16px;
            font-weight: 600;
            padding-top: 12px;
            margin-top: 8px;
            border-top: 1px solid #e0e0e0;
        }

        .payment-summary-label {
            color: #666;
        }

        .payment-summary-value {
            color: #333;
            font-weight: 600;
        }

        .payment-summary-value.positive {
            color: #155724;
        }

        .payment-summary-value.negative {
            color: #721c24;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        /* Responsive Design */
        @media (max-width: 768px) {

            .container {
                padding: 15px 10px;
            }

            .dashboard-header {
                padding: 8px 0;
            }


            .back-button {
                padding: 8px 16px;
                font-size: 14px;
                margin-bottom: 15px;
            }

            .page-header {
                padding: 20px 15px;
                margin-bottom: 20px;
            }

            .header-top {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .booking-title h1 {
                font-size: 20px;
            }

            .booking-title .booking-number {
                font-size: 14px;
            }

            .status-badge {
                font-size: 10px;
                padding: 4px 10px;
            }

            .content-grid {
                grid-template-columns: 1fr;
                gap: 20px;
                margin-bottom: 20px;
            }

            .timeline-payment-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .details-card,
            .documents-card,
            .products-section,
            .payments-section,
            .summary-card,
            .timeline-section {
                padding: 20px 15px;
                margin-bottom: 20px;
            }

            .card-title {
                font-size: 16px;
                margin-bottom: 15px;
                padding-bottom: 12px;
            }

            .detail-item {
                padding: 12px 0;
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }

            .detail-label {
                font-size: 12px;
            }

            .detail-value {
                font-size: 14px;
                text-align: left;
                margin-left: 0;
            }

            .documents-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .document-item {
                padding: 15px;
            }

            .document-icon {
                font-size: 28px;
                margin-bottom: 8px;
            }

            .document-name {
                font-size: 13px;
            }

            .document-action {
                font-size: 11px;
            }

            .product-item {
                padding: 12px;
            }

            .product-name {
                font-size: 14px;
            }

            .product-details {
                grid-template-columns: 1fr;
                gap: 8px;
                font-size: 13px;
            }

            .payments-table {
                font-size: 12px;
                display: block;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .payments-table thead {
                display: none;
            }

            .payments-table tbody {
                display: block;
            }

            .payments-table tr {
                display: block;
                margin-bottom: 15px;
                border: 1px solid #e0e0e0;
                border-radius: 8px;
                padding: 12px;
                background: #f8f9fa;
            }

            .payments-table td {
                display: block;
                padding: 8px 0;
                border-bottom: 1px solid #e0e0e0;
                text-align: left !important;
            }

            .payments-table td:last-child {
                border-bottom: none;
            }

            .payments-table td:before {
                content: attr(data-label);
                font-weight: 600;
                color: #666;
                display: block;
                margin-bottom: 4px;
                font-size: 11px;
                text-transform: uppercase;
            }

            .payment-summary {
                margin-top: 15px;
                padding-top: 15px;
            }

            .payment-summary-row {
                font-size: 13px;
                padding: 6px 0;
            }

            .payment-summary-row.total {
                font-size: 15px;
                padding-top: 10px;
                margin-top: 6px;
            }

            .summary-item {
                padding: 10px 0;
                flex-wrap: wrap;
            }

            .summary-item:last-child {
                font-size: 16px;
                padding-top: 12px;
            }

            .summary-label,
            .summary-value {
                font-size: 14px;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 10px 5px;
            }

            .page-header {
                padding: 15px 10px;
            }

            .booking-title h1 {
                font-size: 18px;
            }

            .details-card,
            .documents-card,
            .products-section,
            .payments-section,
            .summary-card {
                padding: 15px 10px;
            }

            .card-title {
                font-size: 15px;
            }
        }

        @media (min-width: 769px) and (max-width: 1024px) {
            .content-grid {
                gap: 20px;
            }

            .documents-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <?php $this->load->view('customer_portal/header'); ?>

    <div class="container">
        <!-- Back Button -->
        <?php if (!empty($customer_hash)): ?>
        <a href="<?php echo base_url('customer/' . urlencode($customer_hash)); ?>" class="back-button">
            <i class="la la-arrow-left"></i> Back to Bookings
        </a>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div class="header-top">
                <div class="booking-title">
                    <h1>Booking Details</h1>
                    <div class="booking-number"><?php echo htmlspecialchars($booking['BookingNumber']); ?></div>
                </div>
                <div>
                    <span class="status-badge <?php echo $booking['status_display']['class']; ?>">
                        <?php echo $booking['status_display']['text']; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="content-grid">
            <!-- Booking Details -->
            <div class="details-card">
                <div class="card-title">
                    Booking Information
                </div>
                <div class="detail-item">
                    <span class="detail-label">Customer</span>
                    <span class="detail-value"><?php echo htmlspecialchars($booking['Customer']); ?></span>
                </div>
                <?php if (!empty($booking['ReservationNumber'])): ?>
                <div class="detail-item">
                    <span class="detail-label">Reservation Number</span>
                    <span class="detail-value"><?php echo htmlspecialchars($booking['ReservationNumber']); ?></span>
                </div>
                <?php endif; ?>
                <div class="detail-item">
                    <span class="detail-label">Destination</span>
                    <span class="detail-value"><?php echo htmlspecialchars($booking['DestinationName'] ?? 'N/A'); ?></span>
                </div>
                <?php if (!empty($booking['StartDate']) && !empty($booking['EndDate'])): ?>
                <div class="detail-item">
                    <span class="detail-label">Travel Date</span>
                    <span class="detail-value"><?php echo $booking['StartDate']; ?> - <?php echo $booking['EndDate']; ?></span>
                </div>
                <?php endif; ?>
                <div class="detail-item">
                    <span class="detail-label">Passengers</span>
                    <span class="detail-value"><?php echo htmlspecialchars($booking['PaxInfo']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Mobile</span>
                    <span class="detail-value"><?php echo htmlspecialchars($booking['CustomerMobile']); ?></span>
                </div>
                <?php if (!empty($booking['SalesAgentName'])): ?>
                <div class="detail-item">
                    <span class="detail-label">Sales Agent</span>
                    <span class="detail-value"><?php echo htmlspecialchars($booking['SalesAgentName']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($booking['DepositDeadline'])): ?>
                <div class="detail-item">
                    <span class="detail-label">Deposit Deadline</span>
                    <span class="detail-value"><?php echo $booking['DepositDeadline']; ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($booking['FullPaymentDeadline'])): ?>
                <div class="detail-item">
                    <span class="detail-label">Full Payment Deadline</span>
                    <span class="detail-value"><?php echo $booking['FullPaymentDeadline']; ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($booking['InsertDate'])): ?>
                <div class="detail-item">
                    <span class="detail-label">Booking Date</span>
                    <span class="detail-value"><?php echo $booking['InsertDate']; ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($booking['BookingRemark'])): ?>
                <div class="detail-item">
                    <span class="detail-label">Remarks</span>
                    <span class="detail-value"><?php echo nl2br(htmlspecialchars($booking['BookingRemark'])); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($booking['ChatLanguage'])): ?>
                <div class="detail-item">
                    <span class="detail-label">Booking Chat Language</span>
                    <span class="detail-value"><?php echo htmlspecialchars($booking['ChatLanguage']); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Timeline -->
            <div class="timeline-section">
                <div class="card-title">
                    Timeline
                </div>
                <div class="timeline">
                    <?php
                    $today = date('Y-m-d');
                    $timeline_events = [];
                    
                    // Event 1: View Booking Confirmation (always available)
                    $timeline_events[] = [
                        'date' => !empty($booking['InsertDateRaw']) ? date('d/m/y', strtotime($booking['InsertDateRaw'])) : 'N/A',
                        'title' => 'View Booking Confirmation',
                        'action' => '<a href="' . $booking['documents']['bc']['url'] . '" target="_blank">View BC</a>',
                        'status' => 'available',
                        'icon' => 'la la-file-contract'
                    ];
                    
                    // Event 2: Upload payment proof (deposit) - if DepositDeadline exists
                    if (!empty($booking['DepositDeadlineRaw'])) {
                        $deposit_deadline_passed = strtotime($booking['DepositDeadlineRaw']) < strtotime($today);
                        $has_deposit_payment = false;
                        if (!empty($booking['payments'])) {
                            foreach ($booking['payments'] as $payment) {
                                // Check if payment has credit and is approved
                                if (!empty($payment['Credit']) && $payment['Credit'] > 0 && 
                                    ($payment['Status'] == 'Y' || $payment['Status'] == 'P')) {
                                    // Use raw date for comparison
                                    $payment_date = !empty($payment['DateRaw']) ? $payment['DateRaw'] : null;
                                    if ($payment_date && strtotime($payment_date) <= strtotime($booking['DepositDeadlineRaw'])) {
                                        $has_deposit_payment = true;
                                        break;
                                    }
                                }
                            }
                        }
                        $timeline_events[] = [
                            'date' => date('d/m/y', strtotime($booking['DepositDeadlineRaw'])),
                            'title' => 'Upload payment proof (deposit)',
                            'action' => $has_deposit_payment ? 'Completed' : ($deposit_deadline_passed ? 'Overdue' : 'Pending'),
                            'status' => $has_deposit_payment ? 'completed' : ($deposit_deadline_passed ? 'pending' : 'pending'),
                            'icon' => 'la la-upload'
                        ];
                    }
                    
                    // Event 3: Submit namelist - if guest list is available
                    if (!empty($booking['has_guest_list'])) {
                        $timeline_events[] = [
                            'date' => !empty($booking['InsertDateRaw']) ? date('d/m/y', strtotime($booking['InsertDateRaw'])) : 'N/A',
                            'title' => 'Submit namelist',
                            'action' => '<a href="' . $booking['documents']['gl']['url'] . '" target="_blank">View Guest List</a>',
                            'status' => 'completed',
                            'icon' => 'la la-users'
                        ];
                    }
                    
                    // Event 4: Download payment receipt - if payments exist
                    if (!empty($booking['payments'])) {
                        $has_approved_payment = false;
                        $first_payment_date = null;
                        foreach ($booking['payments'] as $payment) {
                            if ($payment['Status'] == 'Y' && !empty($payment['Credit']) && $payment['Credit'] > 0) {
                                $has_approved_payment = true;
                                if (empty($first_payment_date) && !empty($payment['DateRaw'])) {
                                    $first_payment_date = $payment['DateRaw'];
                                }
                            }
                        }
                        if ($has_approved_payment) {
                            $receipt_date = 'N/A';
                            if ($first_payment_date) {
                                $receipt_date = date('d/m/y', strtotime($first_payment_date));
                            }
                            $timeline_events[] = [
                                'date' => $receipt_date,
                                'title' => 'Download payment receipt',
                                'action' => '<a href="' . $booking['documents']['or']['url'] . '" target="_blank">View Receipt</a>',
                                'status' => 'available',
                                'icon' => 'la la-download'
                            ];
                        }
                    }
                    
                    // Event 5: Upload payment proof (balance) - if FullPaymentDeadline exists
                    if (!empty($booking['FullPaymentDeadlineRaw'])) {
                        $balance_deadline_passed = strtotime($booking['FullPaymentDeadlineRaw']) < strtotime($today);
                        $balance_paid = $booking['balance_due'] <= 0;
                        $timeline_events[] = [
                            'date' => date('d/m/y', strtotime($booking['FullPaymentDeadlineRaw'])),
                            'title' => 'Upload payment proof (balance)',
                            'action' => $balance_paid ? 'Completed' : ($balance_deadline_passed ? 'Overdue' : 'Pending'),
                            'status' => $balance_paid ? 'completed' : ($balance_deadline_passed ? 'pending' : 'pending'),
                            'icon' => 'la la-upload'
                        ];
                    }
                    
                    // Event 6: Download Travel Voucher - if TV is available (usually after full payment)
                    if ($booking['balance_due'] <= 0 || !empty($booking['documents']['tv']['available'])) {
                        $timeline_events[] = [
                            'date' => !empty($booking['FullPaymentDeadlineRaw']) ? date('d/m/y', strtotime($booking['FullPaymentDeadlineRaw'])) : 'N/A',
                            'title' => 'Download Travel Voucher',
                            'action' => '<a href="' . $booking['documents']['tv']['url'] . '" target="_blank">View TV</a>',
                            'status' => ($booking['balance_due'] <= 0) ? 'available' : 'pending',
                            'icon' => 'la la-plane'
                        ];
                    }
                    
                    // Event 7: Submit Review - only after travel date and if not blocked
                    if (!empty($booking['EndDateRaw'])) {
                        $travel_ended = strtotime($booking['EndDateRaw']) < strtotime($today);
                        if ($travel_ended) {
                            $timeline_events[] = [
                                'date' => date('d/m/y', strtotime($booking['EndDateRaw'])),
                                'title' => 'Submit Review',
                                'action' => 'Available after travel',
                                'status' => 'pending',
                                'icon' => 'la la-star'
                            ];
                        }
                    }
                    
                    // Display timeline events
                    foreach ($timeline_events as $event):
                    ?>
                    <div class="timeline-item <?php echo $event['status']; ?>">
                        <div class="timeline-content">
                            <div class="timeline-date">
                                <i class="<?php echo $event['icon']; ?> timeline-icon"></i>
                                <?php echo $event['date']; ?>
                            </div>
                            <div class="timeline-title"><?php echo htmlspecialchars($event['title']); ?></div>
                            <div class="timeline-action"><?php echo $event['action']; ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Products Section -->
        <?php if (!empty($booking['products'])): ?>
        <div class="products-section" style="display: none !important;">
            <div class="card-title">
                Products & Services
            </div>
            <?php foreach ($booking['products'] as $product): ?>
            <div class="product-item">
                <div class="product-name"><?php echo htmlspecialchars($product['Name'] ?? $product['Description'] ?? 'N/A'); ?></div>
                <div class="product-details">
                    <?php if (!empty($product['ProductCode'])): ?>
                    <div class="product-detail">
                        <span class="detail-label">Code:</span>
                        <span><?php echo htmlspecialchars($product['ProductCode']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($product['Quantity'])): ?>
                    <div class="product-detail">
                        <span class="detail-label">Quantity:</span>
                        <span><?php echo htmlspecialchars($product['Quantity']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($product['Price'])): ?>
                    <div class="product-detail">
                        <span class="detail-label">Price:</span>
                        <span>RM <?php echo number_format($product['Price'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($product['Total'])): ?>
                    <div class="product-detail">
                        <span class="detail-label">Total:</span>
                        <span>RM <?php echo number_format($product['Total'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Timeline and Payment History -->
        <div class="timeline-payment-grid">

            <!-- Payment History Section -->
            <div class="payments-section">
                <div class="card-title">
                    Payment History
                </div>
                <?php if (empty($booking['payments'])): ?>
                    <div class="empty-state">
                        <i class="la la-wallet"></i>
                        <p>No payment records found</p>
                    </div>
                <?php else: ?>
                    <table class="payments-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Payment In</th>
                                <th>Payment Out</th>
                                <th>Reference</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($booking['payments'] as $payment): ?>
                            <tr>
                                <td data-label="Date"><?php echo !empty($payment['Date']) ? htmlspecialchars($payment['Date']) : '-'; ?></td>
                                <td data-label="Type"><?php echo htmlspecialchars($payment['Type'] ?? '-'); ?></td>
                                <td data-label="Payment In" class="payment-credit">
                                    <?php if (!empty($payment['Credit']) && $payment['Credit'] > 0): ?>
                                        RM <?php echo number_format($payment['Credit'], 2); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td data-label="Payment Out" class="payment-debit">
                                    <?php if (!empty($payment['Debit']) && $payment['Debit'] > 0): ?>
                                        RM <?php echo number_format($payment['Debit'], 2); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td data-label="Reference"><?php echo !empty($payment['ReferenceNumber']) ? htmlspecialchars($payment['ReferenceNumber']) : '-'; ?></td>
                                <td data-label="Status">
                                    <span class="payment-status <?php echo $payment['Status'] == 'Y' ? 'status-approved' : 'status-pending'; ?>">
                                        <?php echo $payment['Status'] == 'Y' ? 'Approved' : 'Pending'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="payment-summary">
                        <div class="payment-summary-row">
                            <span class="payment-summary-label">Total Paid:</span>
                            <span class="payment-summary-value positive">RM <?php echo number_format($booking['total_paid'], 2); ?></span>
                        </div>
                        <?php if ($booking['total_debit'] > 0): ?>
                        <div class="payment-summary-row">
                            <span class="payment-summary-label">Total Refunds:</span>
                            <span class="payment-summary-value negative">RM <?php echo number_format($booking['total_debit'], 2); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="payment-summary-row">
                            <span class="payment-summary-label">Booking Total:</span>
                            <span class="payment-summary-value">RM <?php echo number_format($booking['NetTotal'], 2); ?></span>
                        </div>
                        <div class="payment-summary-row total">
                            <span class="payment-summary-label">Balance Due:</span>
                            <span class="payment-summary-value <?php echo $booking['balance_due'] > 0 ? 'negative' : ($booking['balance_due'] < 0 ? 'positive' : ''); ?>">
                                RM <?php echo number_format($booking['balance_due'], 2); ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

             <!-- Documents -->
            <div class="documents-card">
                <div class="card-title">
                    Documents
                </div>
                <div class="documents-grid">
                    <?php foreach ($booking['documents'] as $doc_key => $doc): ?>
                    <a href="<?php echo $doc['url']; ?>" target="_blank" class="document-item <?php echo $doc['available'] ? '' : 'disabled'; ?>">
                        <div class="document-icon">
                            <?php if ($doc_key == 'bc'): ?>
                                <i class="la la-file-contract"></i>
                            <?php elseif ($doc_key == 'tv'): ?>
                                <i class="la la-plane"></i>
                            <?php elseif ($doc_key == 'or'): ?>
                                <i class="la la-receipt"></i>
                            <?php elseif ($doc_key == 'gl'): ?>
                                <i class="la la-users"></i>
                            <?php else: ?>
                                <i class="la la-file"></i>
                            <?php endif; ?>
                        </div>
                        <div class="document-name"><?php echo htmlspecialchars($doc['name']); ?></div>
                        <div class="document-action">Click to View</div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

