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
    <script src="https://cdn.tailwindcss.com"></script>
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
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
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

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-pending-review {
            background: #e2d9f3;
            color: #6f42c1;
        }

        .status-pending-payment {
            background: #fff3cd;
            color: #856404;
        }

        .status-partial-payment {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-pending-tv {
            background: #f8d7da;
            color: #721c24;
        }

        .status-pending-gl {
            background: #ffeaa7;
            color: #856404;
        }

        .status-pending-travel {
            background: #e2e3e5;
            color: #383d41;
        }

        .status-ongoing {
            background: #cce5ff;
            color: #004085;
        }

        .status-overdue {
            background: #f8d7da;
            color: #721c24;
        }

        .status-cancelled {
            background: #f5c6cb;
            color: #721c24;
        }

        .status-unknown {
            background: #e9ecef;
            color: #495057;
        }

        /* Review CTA Banner */
        .review-cta-banner {
            margin-top: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            animation: pulseGlow 2s ease-in-out infinite;
        }

        @keyframes pulseGlow {

            0%,
            100% {
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            }

            50% {
                box-shadow: 0 4px 25px rgba(102, 126, 234, 0.6);
            }
        }

        .review-cta-content {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .review-cta-icon {
            font-size: 48px;
            color: #ffd700;
            animation: starPulse 1.5s ease-in-out infinite;
        }

        @keyframes starPulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }
        }

        .review-cta-text {
            flex: 1;
            min-width: 200px;
        }

        .review-cta-text h3 {
            color: white;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .review-cta-text p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 14px;
            margin: 0;
        }

        .review-cta-button {
            background: white;
            color: #667eea;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .review-cta-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            background: #f8f9fa;
        }

        .review-cta-button i {
            font-size: 18px;
            color: #667eea;
        }

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
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
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
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
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
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
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

        /* Custom Upload Row Layout */
        .custom-uploads-section {
            margin-top: 15px;
        }

        .custom-upload-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 8px;
            text-decoration: none;
            color: inherit;
            transition: background 0.2s;
        }

        .custom-upload-row:hover {
            background: #e9ecef;
            text-decoration: none;
            color: inherit;
        }

        .custom-upload-row.disabled {
            opacity: 0.5;
            pointer-events: none;
        }

        .custom-upload-row-name {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .custom-upload-row-name i {
            font-size: 18px;
            color: #162447;
        }

        .custom-upload-row-btn {
            font-size: 12px;
            font-weight: 600;
            color: #162447;
            background: #e8ecf4;
            padding: 5px 15px;
            border-radius: 5px;
            white-space: nowrap;
        }

        .custom-upload-row:hover .custom-upload-row-btn {
            background: #d0d7e5;
        }

        /* Products Section */
        .products-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
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
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
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
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .back-button:hover {
            background: #f8f9fa;
            border-color: #162447;
        }

        /* Payment History Section */
        .payments-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
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

            .review-cta-banner {
                padding: 20px;
                margin-top: 15px;
            }

            .review-cta-content {
                flex-direction: column;
                text-align: center;
            }

            .review-cta-icon {
                font-size: 40px;
            }

            .review-cta-text {
                margin: 10px 0;
            }

            .review-cta-text h3 {
                font-size: 18px;
            }

            .review-cta-button {
                width: 100%;
                justify-content: center;
            }

            .content-grid {
                grid-template-columns: 1fr;
                gap: 20px;
                margin-bottom: 20px;
            }

            .details-card,
            .documents-card,
            .products-section,
            .payments-section,
            .summary-card {
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

        /* Timeline CTA Button - Simple small button */
        .timeline-cta-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.15s ease;
            white-space: nowrap;
            margin-top: 10px;
            min-width: 140px;
        }

        .timeline-cta-btn:hover {
            text-decoration: none;
        }

        .timeline-cta-btn svg {
            width: 12px;
            height: 12px;
            flex-shrink: 0;
        }

        /* Gray - Future status (matches bg-gray-300) */
        .cta-btn-gray {
            background: #d1d5db;
            color: #4b5563;
            border: none;
        }

        .cta-btn-gray:hover {
            background: #9ca3af;
            color: #1f2937;
        }

        /* Green - Completed status (matches bg-green-500) */
        .cta-btn-green {
            background: #22c55e;
            color: #ffffff;
            border: none;
        }

        .cta-btn-green:hover {
            background: #16a34a;
            color: #ffffff;
        }

        /* Yellow - Current status (matches bg-yellow-400) */
        .cta-btn-yellow {
            background: #facc15;
            color: #713f12;
            border: none;
        }

        .cta-btn-yellow:hover {
            background: #eab308;
            color: #422006;
        }

        /* Red - Overdue status (matches bg-red-500) */
        .cta-btn-red {
            background: #ef4444;
            color: #ffffff;
            border: none;
        }

        .cta-btn-red:hover {
            background: #dc2626;
            color: #ffffff;
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

        <?php
        // Show review CTA if travel has ended, review is allowed, and no review exists
        $today = date('Y-m-d');
        $travel_ended = !empty($booking['EndDateRaw']) && strtotime($booking['EndDateRaw']) < strtotime($today);
        $allow_review = !empty($booking['AllowReview']) && $booking['AllowReview'] == 1;
        $has_review = !empty($booking['CustomerReview']);
        $booking_token = !empty($booking['Token']) ? $booking['Token'] : '';

        if ($travel_ended && $allow_review && !$has_review):
        ?>
            <div class="review-cta-banner" style="margin-bottom: 20px;">
                <div class="review-cta-content">
                    <div class="review-cta-icon">
                        <i class="la la-star" style="color: white; font-size: 45px;"></i>
                    </div>
                    <div class="review-cta-text">
                        <h3>Share Your Experience!</h3>
                        <p>Your travel has ended. We'd love to hear about your experience!</p>
                    </div>
                    <button class="review-cta-button submit-review-link" data-booking-token="<?php echo htmlspecialchars($booking_token); ?>">
                        <i class="la la-star"></i> Submit Review
                    </button>
                </div>
            </div>
        <?php endif; ?>

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
                <?php if (!empty($booking['BookingNumber'])): ?>
                    <div class="detail-item">
                        <span class="detail-label">Booking Number</span>
                        <span class="detail-value"><?php echo htmlspecialchars($booking['BookingNumber']); ?></span>
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
                    <span class="detail-label">Guests</span>
                    <span class="detail-value"><?php echo htmlspecialchars($booking['PaxInfo']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Customer Mobile</span>
                    <span class="detail-value"><?php echo htmlspecialchars($booking['CustomerMobile']); ?></span>
                </div>
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

                <!-- Contact Actions -->
                <div class="documents-grid" style="margin-top: 20px;">
                    <?php if (!empty($booking['SalesAgentMobile'])):
                        $formatted_mobile = format_mobile_number($booking['SalesAgentMobile']);
                        if ($formatted_mobile): ?>
                            <a href="tel:<?php echo $formatted_mobile; ?>" class="document-item">
                                <div class="document-icon">
                                    <i class="la la-phone"></i>
                                </div>
                                <div class="document-name">Call Your Travel Consultant</div>
                                <div class="document-action">Call <?php echo htmlspecialchars($booking['SalesAgentName']); ?> Now</div>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if (get_offical_whatsapp_link() != null): ?>
                        <?php
                            $message = "Hi, I’d like to check on a question regarding my booking.\nBooking number: {$booking['BookingNumber']}";
                        ?>
                        <a href="<?php echo get_offical_whatsapp_link($message, $formatted_mobile); ?>" target="_blank" class="document-item">
                            <div class="document-icon">
                                <i class="la la-whatsapp"></i>
                            </div>
                            <div class="document-name">WhatsApp Travel Consultant</div>
                            <div class="document-action">WhatsApp <?php echo htmlspecialchars($booking['SalesAgentName']); ?></div>
                        </a>
                    <?php endif; ?>
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

            <!-- Booking Timeline Section -->
            <?php
            // ==========================================
            // TIMELINE DATA CALCULATION
            // ==========================================

            // Helper function to get relative time
            if (!function_exists('get_relative_time')) {
                function get_relative_time($date)
                {
                    if (empty($date)) return '';
                    $timestamp = strtotime($date);
                    $now = time();
                    $diff = $now - $timestamp;

                    if ($diff < 0) {
                        // Future date
                        $diff = abs($diff);
                        if ($diff < 86400) return 'today';
                        if ($diff < 172800) return 'tomorrow';
                        $days = floor($diff / 86400);
                        if ($days < 7) return 'in ' . $days . ' days';
                        if ($days < 30) return 'in ' . ceil($days / 7) . ' weeks';
                        return 'in ' . ceil($days / 30) . ' months';
                    } else {
                        // Past date
                        if ($diff < 60) return 'just now';
                        if ($diff < 3600) return floor($diff / 60) . ' mins ago';
                        if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
                        if ($diff < 172800) return 'yesterday';
                        $days = floor($diff / 86400);
                        if ($days < 7) return $days . ' days ago';
                        if ($days < 30) return ceil($days / 7) . ' weeks ago';
                        return ceil($days / 30) . ' months ago';
                    }
                }
            }

            // Get CI instance for model access
            $CI = &get_instance();
            $CI->load->helper('booking_flow');

            // Initialize timeline steps
            $timeline_steps = [];
            $today = date('Y-m-d');
            $booking_id = $booking['BookingID'];
            $booking_status = $booking['Status'];
            $lock_status = !empty($booking['LockStatus']) ? $booking['LockStatus'] : 'N';

            // Determine which step is current based on sequential checks
            // 1. BC Approved? -> if not, step 1 is current
            // 2. Payment Received? -> if not, step 2/3 is current
            // 3. Checklist Completed? -> if not, step 4 is current
            // 4. Guest List Locked? -> if not, step 5 is current
            // 5. Travel Voucher Sent? -> if not, step 6 is current
            // 6. Travel Ongoing/Completed? -> step 7 or 8

            $bc_approved = !empty($booking['bc_approved']) && $booking['bc_approved'] == 1;

            // Check payments
            $has_any_payment = false;
            $payment_date = null;
            $payment_details = [];
            if (!empty($booking['payments'])) {
                foreach ($booking['payments'] as $payment) {
                    if (
                        !empty($payment['Credit']) && $payment['Credit'] > 0 &&
                        ($payment['Status'] == 'Y' || $payment['Status'] == 'P')
                    ) {
                        $has_any_payment = true;
                        if (empty($payment_date)) {
                            $payment_date = !empty($payment['DateRaw']) ? $payment['DateRaw'] : $payment['Date'];
                        }
                        $payment_details[] = [
                            'type' => $payment['Type'] ?? 'Payment',
                            'amount' => $payment['Credit'],
                            'date' => !empty($payment['DateRaw']) ? $payment['DateRaw'] : $payment['Date'],
                            'status' => $payment['Status']
                        ];
                    }
                }
            }

            // Check if all required payment is received (deposit or full)
            $has_deposit_deadline = !empty($booking['DepositDeadlineRaw']);
            $deposit_paid = false;
            $full_paid = false;
            foreach ($payment_details as $p) {
                $type = strtoupper(trim($p['type'] ?? ''));
                if ($type == 'DEPOSIT') $deposit_paid = true;
                if ($type == 'FULL') $full_paid = true;
            }
            $total_paid = isset($booking['total_paid']) ? floatval($booking['total_paid']) : 0;
            $net_total = isset($booking['NetTotal']) ? floatval($booking['NetTotal']) : 0;
            $deposit_complete = $has_deposit_deadline ? ($has_any_payment || $deposit_paid || $full_paid) : false;
            $full_payment_complete = ($total_paid >= $net_total && $net_total > 0) || $full_paid;

            // Check checklist completion
            $checklists_completed = false;
            if (function_exists('are_all_checklists_completed')) {
                $booking_obj = (object) $booking;
                $checklists_completed = are_all_checklists_completed($booking_id, $CI);
            } else {
                // Fallback: check if status is past PBO
                $checklists_completed = in_array($booking_status, ['PTV', 'PT', 'OG', 'Y']);
            }

            // Check guest list locked
            $guest_list_locked = ($lock_status == 'Y');

            // Check travel voucher sent (status is PT or later)
            $travel_voucher_sent = in_array($booking_status, ['PT', 'OG', 'Y']);

            // Check travel dates
            $travel_start = !empty($booking['StartDateRaw']) ? $booking['StartDateRaw'] : null;
            $travel_end = !empty($booking['EndDateRaw']) ? $booking['EndDateRaw'] : (!empty($booking['StartDateRaw']) ? $booking['StartDateRaw'] : null);
            $travel_ongoing = false;
            $travel_completed = false;
            if ($travel_start && $travel_end) {
                $start_ts = strtotime(date('Y-m-d 00:00:00', strtotime($travel_start)));
                $end_ts = strtotime(date('Y-m-d 23:59:59', strtotime($travel_end)));
                $today_ts = time();
                if ($today_ts >= $start_ts && $today_ts <= $end_ts) {
                    $travel_ongoing = true;
                } elseif ($today_ts > $end_ts) {
                    $travel_completed = true;
                }
            }

            // Status change dates from logs
            $status_dates = $booking['status_change_dates'] ?? [];

            // Determine current step (first incomplete step)
            $current_step = 0;
            if ($has_deposit_deadline) {
                if (!$bc_approved) $current_step = 1;
                elseif (!$deposit_complete) $current_step = 2;
                elseif (!$checklists_completed) $current_step = 3;
                elseif (!$guest_list_locked) $current_step = 4;
                elseif (!$travel_voucher_sent) $current_step = 5;
                elseif (!$full_payment_complete) $current_step = 6;
                elseif ($travel_ongoing) $current_step = 7;
                elseif (!$travel_completed) $current_step = 7;
                else $current_step = 8;
            } else {
                if (!$bc_approved) $current_step = 1;
                elseif (!$full_payment_complete) $current_step = 2;
                elseif (!$checklists_completed) $current_step = 3;
                elseif (!$guest_list_locked) $current_step = 4;
                elseif (!$travel_voucher_sent) $current_step = 5;
                elseif ($travel_ongoing) $current_step = 6;
                elseif (!$travel_completed) $current_step = 6;
                else $current_step = 7;
            }

            // Build timeline steps
            // Prepare document URLs for CTAs
            $bc_url = base_url('Booking_Confirmation?token=' . $booking['Token']);
            $receipt_url = base_url('Receipt?token=' . $booking['Token']);
            $gl_url = base_url('Guest_List?gl=' . $booking['Token']);
            $tv_url = base_url('Travel_Voucher?token=' . $booking['Token']);
            
            // Step 1: BC Approved
            $bc_date = $booking['bc_approval_date'] ?? $booking['InsertDateRaw'] ?? null;
            $bc_step_status = $bc_approved ? 'completed' : ($current_step == 1 ? 'current' : 'future');
            $timeline_steps[] = [
                'step' => count($timeline_steps) + 1,
                'title' => 'Booking Confirmed',
                'description' => 'Your booking has been confirmed',
                'event_date' => $bc_date,
                'relative_time' => get_relative_time($bc_date),
                'expected_date' => null,
                'status' => $bc_step_status,
                'icon' => 'check-circle',
                // CTA: View Booking Confirmation (available when not future)
                'cta_text' => 'View',
                'cta_url' => $bc_url,
                'cta_icon' => 'file-text',
                'cta_enabled' => ($bc_step_status != 'future')
            ];

            // Step: Deposit / Full / Partial / Pending Payment
            $payment_deadline = $has_deposit_deadline ? $booking['DepositDeadlineRaw'] : $booking['FullPaymentDeadlineRaw'];
            $is_overdue = !empty($payment_deadline) && $today > date('Y-m-d', strtotime($payment_deadline)) && ($has_deposit_deadline ? !$deposit_complete : !$full_payment_complete);
            $payment_step_status = ($has_deposit_deadline ? $deposit_complete : $full_payment_complete)
                ? 'completed'
                : ($is_overdue ? 'overdue' : ($current_step == 2 ? 'current' : 'future'));

            // Determine payment title/description based on rules
            if ($has_deposit_deadline) {
                $payment_title = $deposit_complete ? 'Deposit Received' : 'Pending Deposit';
                $payment_description = $deposit_complete ? 'Deposit payment received' : 'Deposit payment required';
            } else {
                if ($total_paid <= 0) {
                    $payment_title = 'Pending Payment';
                    $payment_description = 'Full payment required';
                } elseif ($total_paid < $net_total) {
                    $payment_title = 'Partial Payment Received';
                    $payment_description = 'Partial payment received';
                } else {
                    $payment_title = 'Full Payment Received';
                    $payment_description = 'Full payment received';
                }
            }
            
            $timeline_steps[] = [
                'step' => count($timeline_steps) + 1,
                'title' => $payment_title,
                'description' => $payment_description,
                'event_date' => ($has_deposit_deadline ? $deposit_complete : $full_payment_complete) ? $payment_date : ($has_any_payment ? $payment_date : null),
                'relative_time' => (($has_deposit_deadline ? $deposit_complete : $full_payment_complete) || $has_any_payment) ? get_relative_time($payment_date) : '',
                'expected_date' => $payment_deadline,
                'status' => $payment_step_status,
                'icon' => 'dollar-sign',
                'payment_details' => $payment_details,
                // CTA: Download Receipt (available only when this step is completed)
                'cta_text' => 'Download',
                'cta_url' => $receipt_url,
                'cta_icon' => 'download',
                'cta_enabled' => ($has_deposit_deadline ? $deposit_complete : $full_payment_complete)
            ];

            // Step: Checklist Completed (step number is sequential)
            $checklist_date = $status_dates['PBO'] ?? null;
            $checklist_step_status = $checklists_completed ? 'completed' : ($current_step == 3 ? 'current' : 'future');
            $timeline_steps[] = [
                'step' => count($timeline_steps) + 1,
                'title' => 'Checklist Completed',
                'description' => $checklists_completed ? 'All booking requirements verified' : 'Booking requirements being processed',
                'event_date' => $checklists_completed ? $checklist_date : null,
                'relative_time' => $checklists_completed ? get_relative_time($checklist_date) : '',
                'expected_date' => null,
                'status' => $checklist_step_status,
                'icon' => 'clipboard-check',
                // No CTA for checklist step
                'cta_text' => null,
                'cta_url' => null,
                'cta_icon' => null,
                'cta_enabled' => false
            ];

            // Step: Guest List Completed & Locked
            $gl_date = $status_dates['PTV'] ?? $status_dates['PGL'] ?? null;
            // Guest list should become current right after BC is confirmed (in parallel with payment stage)
            $gl_step_status = $guest_list_locked ? 'completed' : ($bc_approved ? 'current' : 'future');
            $gl_expected_date = $bc_date ? date('Y-m-d', strtotime($bc_date . ' +2 days')) : null;
            $timeline_steps[] = [
                'step' => count($timeline_steps) + 1,
                'title' => 'Guest List Finalized',
                'description' => $guest_list_locked ? 'Guest list has been submitted and locked' : 'Please submit your guest list',
                'event_date' => $guest_list_locked ? $gl_date : null,
                'relative_time' => $guest_list_locked ? get_relative_time($gl_date) : '',
                'expected_date' => $gl_expected_date,
                'status' => $gl_step_status,
                'icon' => 'users',
                // CTA: View/Edit Guest List (available when not future - can view or edit)
                'cta_text' => $guest_list_locked ? 'View' : 'Edit',
                'cta_url' => $gl_url,
                'cta_icon' => 'users',
                'cta_enabled' => ($gl_step_status != 'future')
            ];

            // Step: Travel Voucher Sent
            $tv_date = $status_dates['PT'] ?? null;
            $tv_step_status = $travel_voucher_sent ? 'completed' : ($current_step == 5 ? 'current' : 'future');
            $tv_expected_date = null;
            if ($travel_start) {
                $travel_ts = strtotime($travel_start);
                $bc_ts = !empty($bc_date) ? strtotime($bc_date) : null;
                if (!empty($bc_ts) && ($travel_ts - $bc_ts) < 7 * 86400) {
                    $tv_expected_date = date('Y-m-d', strtotime($travel_start . ' -1 days'));
                } else {
                    $tv_expected_date = date('Y-m-d', strtotime($travel_start . ' -7 days'));
                }
            }
            $timeline_steps[] = [
                'step' => count($timeline_steps) + 1,
                'title' => 'Travel Voucher Sent',
                'description' => $travel_voucher_sent ? 'Your travel voucher is ready' : 'Travel voucher will be sent before your trip',
                'event_date' => $travel_voucher_sent ? $tv_date : null,
                'relative_time' => $travel_voucher_sent ? get_relative_time($tv_date) : '',
                'expected_date' => $tv_expected_date,
                'status' => $tv_step_status,
                'icon' => 'file-text',
                // CTA: View Travel Voucher (available when travel voucher sent AND guest list locked)
                'cta_text' => 'View',
                'cta_url' => $tv_url,
                'cta_icon' => 'plane',
                'cta_enabled' => ($travel_voucher_sent && $guest_list_locked)
            ];

            // Step: Full Payment (only for deposit-flow bookings, shown after Travel Voucher)
            if ($has_deposit_deadline) {
                $full_payment_deadline = $booking['FullPaymentDeadlineRaw'] ?? null;
                $full_payment_overdue = !empty($full_payment_deadline) && $today > date('Y-m-d', strtotime($full_payment_deadline)) && !$full_payment_complete;
                $full_payment_step_status = $full_payment_complete
                    ? 'completed'
                    : (($travel_voucher_sent && $full_payment_overdue) ? 'overdue' : ($current_step == 6 ? 'current' : 'future'));

                $full_payment_title = $full_payment_complete ? 'Full Payment Received' : 'Pending Full Payment';
                $full_payment_description = $full_payment_complete ? 'Full payment received' : 'Full payment required';

                $timeline_steps[] = [
                    'step' => count($timeline_steps) + 1,
                    'title' => $full_payment_title,
                    'description' => $full_payment_description,
                    'event_date' => $full_payment_complete ? $payment_date : null,
                    'relative_time' => $full_payment_complete ? get_relative_time($payment_date) : '',
                    'expected_date' => $full_payment_deadline,
                    'status' => $full_payment_step_status,
                    'icon' => 'credit-card',
                    'payment_details' => $payment_details,
                    // CTA: Download Receipt (available only when full payment is completed)
                    'cta_text' => 'Download',
                    'cta_url' => $receipt_url,
                    'cta_icon' => 'download',
                    'cta_enabled' => $full_payment_complete
                ];
            }

            // Step: Trip Status (expected / ongoing / completed)
            $trip_step_status = $travel_completed ? 'completed' : ($travel_ongoing ? 'current' : 'future');
            if ($travel_start && $travel_end) {
                if ($travel_completed) {
                    $trip_title = 'Trip Completed';
                    $trip_description = 'We hope you had a wonderful trip!';
                    $trip_event_date = $travel_end;
                    $trip_relative = get_relative_time($travel_end);
                    $trip_expected = null;
                } elseif ($travel_ongoing) {
                    $trip_title = 'Trip Ongoing';
                    $trip_description = 'Your trip is currently in progress';
                    $trip_event_date = $travel_start;
                    $trip_relative = 'ongoing';
                    $trip_expected = $travel_end;
                } else {
                    $trip_title = 'Trip Date';
                    $trip_description = 'Your trip is scheduled to begin soon';
                    $trip_event_date = null;
                    $trip_relative = get_relative_time($travel_start);
                    $trip_expected = $travel_start;
                }
                $timeline_steps[] = [
                    'step' => count($timeline_steps) + 1,
                    'title' => $trip_title,
                    'description' => $trip_description,
                    'event_date' => $trip_event_date,
                    'relative_time' => $trip_relative,
                    'expected_date' => $trip_expected,
                    'status' => $trip_step_status,
                    'icon' => 'flag',
                    // No CTA for trip status
                    'cta_text' => null,
                    'cta_url' => null,
                    'cta_icon' => null,
                    'cta_enabled' => false
                ];
            }
            ?>

            <div class="details-card">
                <div class="card-title">
                    Timeline
                </div>

                <div class="relative">
                    <!-- Timeline line -->
                    <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-gray-200"></div>

                    <!-- Timeline items -->
                    <div class="space-y-6">
                        <?php foreach ($timeline_steps as $index => $step): ?>
                            <?php
                            // Determine colors based on status
                            $status = $step['status'];
                            $dot_classes = '';
                            $text_classes = '';
                            $bg_classes = '';
                            $ring_classes = '';

                            switch ($status) {
                                case 'completed':
                                    $dot_classes = 'bg-green-500 text-white';
                                    $text_classes = 'text-gray-700';
                                    $bg_classes = 'bg-green-50 border-green-200';
                                    break;
                                case 'current':
                                    $dot_classes = 'bg-yellow-400 text-white';
                                    $text_classes = 'text-gray-900 font-semibold';
                                    $bg_classes = 'bg-yellow-50 border-yellow-300';
                                    $ring_classes = 'ring-4 ring-yellow-200';
                                    break;
                                case 'overdue':
                                    $dot_classes = 'bg-red-500 text-white';
                                    $text_classes = 'text-red-700 font-semibold';
                                    $bg_classes = 'bg-red-50 border-red-300';
                                    $ring_classes = 'ring-4 ring-red-200';
                                    break;
                                default: // future
                                    $dot_classes = 'bg-gray-300 text-gray-500';
                                    $text_classes = 'text-gray-400';
                                    $bg_classes = 'bg-gray-50 border-gray-200';
                            }

                            // Icon SVG based on type
                            $icon_svg = '';
                            switch ($step['icon']) {
                                case 'check-circle':
                                    $icon_svg = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>';
                                    break;
                                case 'credit-card':
                                    $icon_svg = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>';
                                    break;
                                case 'dollar-sign':
                                    $icon_svg = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>';
                                    break;
                                case 'clipboard-check':
                                    $icon_svg = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>';
                                    break;
                                case 'users':
                                    $icon_svg = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>';
                                    break;
                                case 'file-text':
                                    $icon_svg = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>';
                                    break;
                                case 'plane':
                                    $icon_svg = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>';
                                    break;
                                case 'flag':
                                    $icon_svg = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"></path>';
                                    break;
                                default:
                                    $icon_svg = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>';
                            }
                            ?>

                            <div class="relative flex items-start gap-4 md:gap-8">
                                <!-- Timeline dot -->
                                <div class="relative z-10 flex-shrink-0">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center <?php echo $dot_classes; ?> <?php echo $ring_classes; ?> transition-all duration-300">
                                        <?php if ($status == 'completed'): ?>
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        <?php elseif ($status == 'current'): ?>
                                            <span class="w-2 h-2 bg-white rounded-full animate-pulse"></span>
                                        <?php elseif ($status == 'overdue'): ?>
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                            </svg>
                                        <?php else: ?>
                                            <span class="text-xs font-medium"><?php echo $step['step']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Content card -->
                                <div class="flex-1 ml-0 md:ml-0">
                                    <div class="p-4 rounded-lg border <?php echo $bg_classes; ?> transition-all duration-300 <?php echo ($status == 'current') ? 'shadow-md' : ''; ?>">
                                        <?php 
                                        // Prepare CTA data first
                                        $has_cta = !empty($step['cta_enabled']) && !empty($step['cta_text']) && !empty($step['cta_url']);
                                        $cta_btn_class = 'timeline-cta-btn rounded-lg';
                                        $cta_icon_svg = '';
                                        
                                        if ($has_cta) {
                                            if ($status == 'completed') {
                                                $cta_btn_class .= ' cta-btn-completed';
                                            } elseif ($status == 'current' || $status == 'overdue') {
                                                $cta_btn_class .= ' cta-btn-current';
                                            } else {
                                                $cta_btn_class .= ' cta-btn-default';
                                            }
                                            
                                            switch ($step['cta_icon'] ?? 'external-link') {
                                                case 'file-text':
                                                    $cta_icon_svg = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>';
                                                    break;
                                                case 'download':
                                                    $cta_icon_svg = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>';
                                                    break;
                                                case 'users':
                                                    $cta_icon_svg = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>';
                                                    break;
                                                case 'plane':
                                                    $cta_icon_svg = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>';
                                                    break;
                                                default:
                                                    $cta_icon_svg = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>';
                                            }
                                        }
                                        ?>
                                        
                                        <div class="flex items-center gap-2 mb-1">
                                            <svg class="w-4 h-4 flex-shrink-0 <?php echo ($status == 'completed') ? 'text-green-600' : (($status == 'current') ? 'text-yellow-600' : (($status == 'overdue') ? 'text-red-600' : 'text-gray-400')); ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <?php echo $icon_svg; ?>
                                            </svg>
                                            <h3 class="font-semibold <?php echo $text_classes; ?>"><?php echo htmlspecialchars($step['title']); ?></h3>
                                        </div>

                                        <p class="text-sm <?php echo ($status == 'future') ? 'text-gray-400' : 'text-gray-600'; ?> mb-2">
                                            <?php echo htmlspecialchars($step['description']); ?>
                                        </p>

                                        <div class="flex flex-wrap items-center gap-3 text-xs">
                                            <?php if (!empty($step['event_date'])): ?>
                                                <span class="inline-flex items-center gap-1 <?php echo ($status == 'completed') ? 'text-green-600' : (($status == 'overdue') ? 'text-red-600' : 'text-gray-500'); ?>">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                    </svg>
                                                    <?php echo date('d M Y', strtotime($step['event_date'])); ?>
                                                </span>
                                                <?php if (!empty($step['relative_time'])): ?>
                                                    <span class="text-gray-400">(<?php echo $step['relative_time']; ?>)</span>
                                                <?php endif; ?>
                                            <?php elseif (!empty($step['expected_date']) && $status != 'completed'): ?>
                                                <span class="inline-flex items-center gap-1 <?php echo ($status == 'overdue') ? 'text-red-600 font-medium' : 'text-gray-400'; ?>">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                    Expected: <?php echo date('d M Y', strtotime($step['expected_date'])); ?>
                                                </span>
                                            <?php endif; ?>

                                            <?php if ($status == 'current'): ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-yellow-200 text-yellow-800 rounded-full font-medium">
                                                    <span class="w-1.5 h-1.5 bg-yellow-500 rounded-full animate-pulse"></span>
                                                    Current Step
                                                </span>
                                            <?php elseif ($status == 'overdue'): ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-red-200 text-red-800 rounded-full font-medium">
                                                    Overdue
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($has_cta): 
                                            // Determine button color based on status
                                            $btn_color_class = 'cta-btn-gray';
                                            if ($status == 'completed') {
                                                $btn_color_class = 'cta-btn-green';
                                            } elseif ($status == 'current') {
                                                $btn_color_class = 'cta-btn-yellow';
                                            } elseif ($status == 'overdue') {
                                                $btn_color_class = 'cta-btn-red';
                                            }
                                        ?>
                                            <a href="<?php echo htmlspecialchars($step['cta_url']); ?>" 
                                               target="_blank" 
                                               class="timeline-cta-btn <?php echo $btn_color_class; ?>">
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                                <span><?php echo htmlspecialchars($step['cta_text']); ?></span>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Progress summary -->
                <div class="mt-6 pt-4 border-t border-gray-100 hidden">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">Progress</span>
                        <?php
                        $completed_count = 0;
                        $total_count = count($timeline_steps);
                        foreach ($timeline_steps as $s) {
                            if ($s['status'] == 'completed') $completed_count++;
                        }
                        $progress_percent = $total_count > 0 ? round(($completed_count / $total_count) * 100) : 0;
                        ?>
                        <span class="font-medium text-gray-700"><?php echo $completed_count; ?> of <?php echo $total_count; ?> steps completed</span>
                    </div>
                    <div class="mt-2 h-2 bg-gray-200 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-green-400 to-green-500 rounded-full transition-all duration-500" style="width: <?php echo $progress_percent; ?>%"></div>
                    </div>
                </div>
            </div>

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
                                <th>Amount</th>
                                <th>Reference</th>
                                <th>Status</th>
                                <th>Receipt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($booking['payments'] as $payment): ?>
                                <tr>
                                    <td data-label="Date"><?php echo !empty($payment['Date']) ? htmlspecialchars($payment['Date']) : '-'; ?></td>
                                    <td data-label="Type"><?php echo htmlspecialchars($payment['Type'] ?? '-'); ?></td>
                                    <td data-label="Amount" class="payment-credit">
                                        <?php if (!empty($payment['Credit']) && $payment['Credit'] > 0): ?>
                                            RM <?php echo number_format($payment['Credit'], 2); ?>
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
                                    <td data-label="Receipt">
                                        <?php if (!empty($payment['Credit']) && $payment['Credit'] > 0 && $payment['Status'] == 'Y'): ?>
                                            <a href="<?php echo base_url('Receipt?token=' . $booking['Token']); ?>" target="_blank">
                                                View
                                            </a>
                                        <?php else: ?>
                                            <span style="color: #999; font-size: 12px;">-</span>
                                        <?php endif; ?>
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
                <?php
                // Check if travel voucher has been sent (status PT or later)
                $travel_voucher_sent = in_array($booking['Status'], array('PT', 'OG', 'Y', 'PR'));

                // Separate standard docs from custom uploads
                $standard_docs = array();
                $custom_uploads = array();
                foreach ($booking['documents'] as $doc_key => $doc) {
                    if (strpos($doc_key, 'cu_') === 0) {
                        if ($travel_voucher_sent) {
                            $custom_uploads[$doc_key] = $doc;
                        }
                    } else {
                        $standard_docs[$doc_key] = $doc;
                    }
                }
                ?>

                <!-- Standard Documents (Card Layout) -->
                <div class="documents-grid">
                    <?php foreach ($standard_docs as $doc_key => $doc): ?>
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

                <!-- Custom Uploaded Documents (Row Layout) -->
                <?php if (!empty($custom_uploads)): ?>
                    <div class="custom-uploads-section">
                        <?php foreach ($custom_uploads as $doc_key => $doc): ?>
                            <a href="<?php echo $doc['url']; ?>" target="_blank" class="custom-upload-row <?php echo $doc['available'] ? '' : 'disabled'; ?>">
                                <span class="custom-upload-row-name">
                                    <i class="la la-file-alt"></i>
                                    <?php echo htmlspecialchars($doc['name']); ?>
                                </span>
                                <span class="custom-upload-row-btn">View</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Customer Comments Section -->
            <div class="details-card">
                <div class="card-title">
                    Your Comments
                </div>
                <?php
                $hide_comment_form = in_array($booking['Status'], array('PT', 'OG', 'Y', 'PR'));
                if (!$hide_comment_form):
                ?>
                <div class="mb-3 p-3 rounded" style="background: #f0f7ff; border: 1px solid #cce5ff; font-size: 13px; color: #004085;">
                    <strong>How to use:</strong>
                    <ul class="mb-0 pl-3" style="line-height: 1.6;">
                        <li><strong>Ask questions</strong> or add notes about your booking — type in the box below and click <strong>Add Comment</strong>.</li>
                        <li>Our team will see your message and can reply here. You can read all comments and replies in this section.</li>
                        <li><strong>Commenting closes</strong> once your travel voucher has been sent. After that you can still read the conversation but cannot add new comments.</li>
                    </ul>
                </div>
                <?php else: ?>
                <div class="mb-3 p-3 rounded" style="background: #fff3cd; border: 1px solid #ffc107; font-size: 13px; color: #856404;">
                    <strong>Commenting is closed.</strong> You can no longer add new comments because your travel voucher has been sent. You can still read the conversation below.
                </div>
                <?php endif; ?>
                <div id="customer-comments-list" class="mb-3" style="min-height: 100px;">
                    <div class="text-center text-muted py-3" style="font-size: 14px;">
                        <i class="la la-spinner la-spin"></i> Loading comments...
                    </div>
                </div>

                <?php
                // Hide comment form after travel voucher is sent (status PT or later)
                // Status progression: P/PP -> PTV -> PT (travel voucher sent) -> OG -> Y
                if (!$hide_comment_form):
                ?>
                    <!-- Add New Comment Form -->
                    <div class="pt-3">
                        <div class="form-group mb-2">
                            <textarea id="new-customer-comment-content" class="form-control" rows="3" placeholder="Enter your comment or question here..." style="font-size: 14px; resize: vertical;"></textarea>
                        </div>
                        <button type="button" id="add-customer-comment-btn" class="btn btn-primary btn-sm" style="font-weight: 600;">
                            <i class="la la-comment"></i> Add Comment
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Review Modal -->
    <div id="reviewModal" class="review-modal">
        <div class="review-modal-overlay"></div>
        <div class="review-modal-content">
            <div class="review-modal-header">
                <h2 id="reviewModalTitle">Submit Your Review</h2>
                <button class="review-modal-close" id="reviewModalClose">&times;</button>
            </div>
            <div class="review-modal-body">
                <div id="reviewModalMessage" class="review-modal-message" style="display: none;"></div>
                <form id="reviewForm">
                    <input type="hidden" id="reviewBookingToken" name="booking_token" value="<?php echo htmlspecialchars($booking['Token']); ?>">
                    <div class="form-group">
                        <label for="reviewText">Please share your experience with us</label>
                        <textarea
                            id="reviewText"
                            name="review_text"
                            class="form-control review-textarea"
                            placeholder="Tell us about your travel experience..."
                            required
                            rows="6"><?php echo !empty($booking['CustomerReview']) ? htmlspecialchars($booking['CustomerReview']) : ''; ?></textarea>
                    </div>
                    <?php if (!empty($booking['CustomerReviewTimestamp'])): ?>
                        <div class="review-date-info">
                            <small>Review submitted on <?php echo return_timestamp_output($booking['CustomerReviewTimestamp'], true, false); ?></small>
                        </div>
                    <?php endif; ?>
                    <div class="review-modal-actions">
                        <button type="button" class="btn btn-secondary" id="reviewModalCancel">Cancel</button>
                        <button type="button" class="btn btn-info" id="reviewEditBtn" style="display: none;">Edit Review</button>
                        <button type="submit" class="btn btn-primary" id="reviewSubmitBtn">
                            <span id="reviewSubmitText"><?php echo !empty($booking['CustomerReview']) ? 'Update Review' : 'Submit Review'; ?></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="<?php echo base_url('assets/js/plugins-bundle.js'); ?>"></script>
    <script>
        $(document).ready(function() {
            var hasReview = <?php echo !empty($booking['CustomerReview']) ? 'true' : 'false'; ?>;
            var bookingToken = '<?php echo htmlspecialchars($booking['Token']); ?>';
            var reviewText = <?php echo !empty($booking['CustomerReview']) ? json_encode($booking['CustomerReview']) : 'null'; ?>;

            // Function to open submit review modal
            function openSubmitReviewModal() {
                $('#reviewModalTitle').text('Submit Your Review');
                $('#reviewText').val('').prop('disabled', false);
                $('#reviewSubmitText').text('Submit Review');
                $('#reviewSubmitBtn').show();
                $('#reviewEditBtn').hide();
                $('#reviewModalMessage').hide();
                $('.review-date-info').hide();
                $('#reviewModal').addClass('active');
            }

            // Function to open view review modal
            function openViewReviewModal() {
                $('#reviewModalTitle').text('View Your Review');
                $('#reviewText').val(reviewText).prop('disabled', true);
                $('#reviewSubmitText').text('Update Review');
                $('#reviewSubmitBtn').hide();
                $('#reviewEditBtn').show();
                $('#reviewModalMessage').hide();
                $('.review-date-info').show();
                $('#reviewModal').addClass('active');
            }

            // Handle banner button click
            $(document).on('click', '.submit-review-link', function(e) {
                e.preventDefault();
                e.stopPropagation();
                openSubmitReviewModal();
            });

            // Handle view review link click (fallback if clicked directly)
            $(document).on('click', '.view-review-link', function(e) {
                e.preventDefault();
                e.stopPropagation();
                openViewReviewModal();
            });

            // Edit review button
            $('#reviewEditBtn').on('click', function() {
                $('#reviewText').prop('disabled', false);
                $('#reviewModalTitle').text('Update Your Review');
                $('#reviewEditBtn').hide();
                $('#reviewSubmitBtn').show();
            });

            // Close modal
            function closeModal() {
                $('#reviewModal').removeClass('active');
            }

            $('#reviewModalClose, #reviewModalCancel, .review-modal-overlay').on('click', function(e) {
                e.preventDefault();
                closeModal();
            });

            // Handle form submission
            $('#reviewForm').on('submit', function(e) {
                e.preventDefault();

                var reviewText = $('#reviewText').val().trim();

                if (reviewText.length === 0) {
                    showMessage('Please enter your review.', 'error');
                    return;
                }

                // Disable submit button
                var $submitBtn = $('#reviewSubmitBtn');
                var originalText = $submitBtn.html();
                $submitBtn.prop('disabled', true).html('<i class="la la-spinner la-spin"></i> Submitting...');

                // Submit via AJAX
                $.ajax({
                    url: '<?php echo base_url('customer/booking/' . $booking['Token'] . '/review'); ?>',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        review_text: reviewText
                    },
                    success: function(response) {
                        if (response && response.success) {
                            showMessage(response.message || 'Review submitted successfully!', 'success');
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            showMessage(response.message || 'Failed to submit review. Please try again.', 'error');
                            $submitBtn.prop('disabled', false).html(originalText);
                        }
                    },
                    error: function(xhr) {
                        var errorMsg = 'Failed to submit review. Please try again.';
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response && response.message) {
                                errorMsg = response.message;
                            }
                        } catch (e) {
                            // If response is not JSON, use default message
                            if (xhr.status === 404) {
                                errorMsg = 'Review submission endpoint not found. Please contact support.';
                            } else if (xhr.status === 500) {
                                errorMsg = 'Server error. Please try again later.';
                            }
                        }
                        showMessage(errorMsg, 'error');
                        $submitBtn.prop('disabled', false).html(originalText);
                    }
                });
            });

            // Show message in modal
            function showMessage(message, type) {
                var $message = $('#reviewModalMessage');
                $message.removeClass('success error')
                    .addClass(type)
                    .text(message)
                    .show();

                // Scroll to message
                $message[0].scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest'
                });
            }

            // Close modal on Escape key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('#reviewModal').hasClass('active')) {
                    closeModal();
                }
            });

            // Customer Comments functionality
            var bookingToken = '<?php echo htmlspecialchars($booking['Token']); ?>';

            // Helper function to escape HTML
            function escapeHtml(text) {
                var map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return text.replace(/[&<>"']/g, function(m) {
                    return map[m];
                });
            }

            // Helper function to calculate time ago
            function timeAgo(datetime) {
                var timestamp = new Date(datetime).getTime();
                var diff = Date.now() - timestamp;

                if (diff < 60000) {
                    return 'just now';
                } else if (diff < 3600000) {
                    var mins = Math.floor(diff / 60000);
                    return mins + ' minute' + (mins > 1 ? 's' : '') + ' ago';
                } else if (diff < 86400000) {
                    var hours = Math.floor(diff / 3600000);
                    return hours + ' hour' + (hours > 1 ? 's' : '') + ' ago';
                } else if (diff < 604800000) {
                    var days = Math.floor(diff / 86400000);
                    return days + ' day' + (days > 1 ? 's' : '') + ' ago';
                } else {
                    return new Date(datetime).toLocaleDateString('en-GB', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric'
                    });
                }
            }

            // Load customer comments on page load
            function loadCustomerComments() {
                $.ajax({
                    url: '<?php echo base_url('customer/booking/' . $booking['Token'] . '/remarks'); ?>',
                    type: 'get',
                    dataType: 'json',
                    success: function(response) {
                        var commentsList = $('#customer-comments-list');
                        commentsList.empty();

                        if (response.success && response.remarks && response.remarks.length > 0) {
                            response.remarks.forEach(function(remark) {
                                // Get first letter for avatar color
                                var avatarColor = ['primary', 'success', 'info', 'warning', 'danger'][remark.commenter_name.charCodeAt(0) % 5];

                                var commentHtml = '<div class="comment-item d-flex mb-3 pb-3" style="border-bottom: 1px solid #e4e6eb;">' +
                                    // Avatar
                                    '<div class="flex-shrink-0 mr-3">' +
                                    '<div class="symbol symbol-40 symbol-circle symbol-light-' + avatarColor + '">' +
                                    '<span class="symbol-label font-weight-bold" style="font-size: 0.875rem;">' + (remark.commenter_initials || remark.commenter_name.substring(0, 2).toUpperCase()) + '</span>' +
                                    '</div>' +
                                    '</div>' +
                                    // Comment content
                                    '<div class="flex-grow-1" style="min-width: 0;">' +
                                    '<div class="d-flex align-items-baseline mb-1">' +
                                    '<strong class="mr-2" style="font-size: 0.875rem; color: #050505;">' + escapeHtml(remark.commenter_name) + '</strong>' +
                                    '<span class="text-muted" style="font-size: 0.75rem; color: #65676b;">' + remark.created_at + (remark.created_at_relative ? ' <span style="margin: 0 4px;">•</span> ' + remark.created_at_relative : '') + '</span>' +
                                    '</div>' +
                                    '<div class="comment-text" style="font-size: 0.875rem; color: #050505; line-height: 1.4; white-space: pre-wrap; word-wrap: break-word;">' + escapeHtml(remark.content) + '</div>' +
                                    '</div>' +
                                    '</div>';
                                commentsList.append(commentHtml);
                            });
                        } else {
                            commentsList.html('<div class="text-center text-muted py-4" style="font-size: 0.875rem; color: #65676b;">No comments yet. Be the first to add a comment!</div>');
                        }
                    },
                    error: function() {
                        $('#customer-comments-list').html('<div class="text-center text-danger py-3" style="font-size: 0.875rem;">Error loading comments. Please refresh the page.</div>');
                    }
                });
            }

            // Add new customer comment
            $('#add-customer-comment-btn').on('click', function() {
                var content = $('#new-customer-comment-content').val().trim();

                if (!content) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Please enter a comment',
                        showConfirmButton: false,
                        timer: 2000
                    });
                    return;
                }

                var $btn = $(this);
                var originalText = $btn.html();
                $btn.prop('disabled', true).html('<i class="la la-spinner la-spin"></i> Adding...');

                $.ajax({
                    url: '<?php echo base_url('customer/booking/' . $booking['Token'] . '/remark'); ?>',
                    type: 'post',
                    data: {
                        content: content
                    },
                    dataType: 'json',
                    success: function(response) {
                        $btn.prop('disabled', false).html(originalText);

                        if (response.success) {
                            $('#new-customer-comment-content').val('');
                            loadCustomerComments(); // Reload comments to show the new one
                            Swal.fire({
                                icon: 'success',
                                title: 'Comment added successfully!',
                                showConfirmButton: false,
                                timer: 2000
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: response.message || 'Failed to add comment. Please try again.',
                                showConfirmButton: false,
                                timer: 3000
                            });
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).html(originalText);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error adding comment. Please try again.',
                            showConfirmButton: false,
                            timer: 3000
                        });
                    }
                });
            });

            // Allow Enter key to submit (Ctrl+Enter or Shift+Enter)
            $('#new-customer-comment-content').on('keydown', function(e) {
                if ((e.ctrlKey || e.shiftKey) && e.key === 'Enter') {
                    e.preventDefault();
                    $('#add-customer-comment-btn').click();
                }
            });

            // Load comments on page load
            loadCustomerComments();
        });
    </script>

    <style>
        /* Review Modal Styles */
        .review-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 10000;
        }

        .review-modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .review-modal-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(2px);
        }

        .review-modal-content {
            position: relative;
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            z-index: 10001;
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .review-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-bottom: 1px solid #e0e0e0;
        }

        .review-modal-header h2 {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        .review-modal-close {
            background: none;
            border: none;
            font-size: 28px;
            color: #999;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            transition: color 0.3s;
        }

        .review-modal-close:hover {
            color: #333;
        }

        .review-modal-body {
            padding: 25px;
        }

        .review-modal-message {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .review-modal-message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .review-modal-message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .review-textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            font-family: 'Poppins', sans-serif;
            resize: vertical;
            min-height: 150px;
        }

        .review-textarea:focus {
            outline: none;
            border-color: #162447;
            box-shadow: 0 0 0 3px rgba(22, 36, 71, 0.1);
        }

        .review-textarea:disabled {
            background-color: #f8f9fa;
            cursor: not-allowed;
        }

        .form-help {
            display: block;
            color: #666;
            font-size: 13px;
            margin-top: 5px;
        }

        .review-date-info {
            margin-bottom: 20px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
        }

        .review-date-info small {
            font-size: 13px;
            color: #666;
            font-style: italic;
        }

        .review-modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #162447;
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            background: #0f1a33;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn-info:hover {
            background: #138496;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        @media (max-width: 768px) {
            .review-modal-content {
                width: 95%;
                max-height: 95vh;
            }

            .review-modal-header {
                padding: 15px 20px;
            }

            .review-modal-body {
                padding: 20px;
            }

            .review-modal-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</body>

</html>
