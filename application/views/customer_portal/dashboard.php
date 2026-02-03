<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>My Bookings - <?php echo htmlspecialchars($customer['name']); ?></title>
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


        /* Main Container */
        .dashboard-container {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        /* Customer Details Section */
        .customer-details-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .customer-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }

        .customer-detail-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .customer-detail-label {
            font-size: 13px;
            color: #666;
            font-weight: 500;
        }

        .customer-detail-value {
            font-size: 15px;
            color: #333;
            font-weight: 500;
        }

        /* What You Can Do Section */
        .portal-features-divider {
            height: 1px;
            background: linear-gradient(to right, transparent, #e0e0e0, transparent);
            margin: 25px 0;
        }

        .portal-features-title {
            font-size: 14px;
            font-weight: 600;
            color: #666;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .portal-features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }

        .portal-feature-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 0;
        }

        .portal-feature-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 18px;
        }

        .portal-feature-icon.icon-view {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }

        .portal-feature-icon.icon-download {
            background: rgba(17, 153, 142, 0.1);
            color: #11998e;
        }

        .portal-feature-icon.icon-track {
            background: rgba(245, 87, 108, 0.1);
            color: #f5576c;
        }

        .portal-feature-icon.icon-review {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .portal-feature-content {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .portal-feature-label {
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }

        .portal-feature-desc {
            font-size: 13px;
            color: #777;
            line-height: 1.4;
        }

        /* Tabs Section */
        .tabs-container {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .tabs-header {
            display: flex;
            gap: 10px;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 25px;
        }

        .tab-button {
            padding: 12px 24px;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            font-size: 16px;
            font-weight: 500;
            color: #666;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
            position: relative;
            bottom: -2px;
        }

        .tab-button:hover {
            color: #667eea;
        }

        .tab-button.active {
            color: #667eea;
            border-bottom-color: #667eea;
            font-weight: 600;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Search Section */
        .search-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .search-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .search-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .search-title i {
            color: #667eea;
        }

        .search-helper {
            font-size: 13px;
            color: #999;
            margin-top: 8px;
        }

        .search-filters-wrapper {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .search-input-wrapper {
            position: relative;
            max-width: 100%;
        }

        .date-filters-wrapper {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .date-filter-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
            flex: 1;
            min-width: 150px;
        }

        .date-filter-label {
            font-size: 13px;
            color: #666;
            font-weight: 500;
        }

        .date-filter-input {
            padding: 10px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            color: #333;
            transition: border-color 0.3s;
        }

        .date-filter-input:focus {
            outline: none;
            border-color: #6082B6;
        }

        .btn-clear-filters {
            padding: 10px 20px;
            background: #f5f7fa;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            color: #666;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .btn-clear-filters:hover {
            background: #e8e8e8;
            border-color: #ccc;
            color: #333;
        }

        .search-input {
            width: 100%;
            padding: 12px 40px 12px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            transition: border-color 0.3s;
        }

        .search-input:focus {
            outline: none;
            border-color: #667eea;
        }

        .search-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 18px;
        }

        /* Pagination */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }

        .pagination-info {
            font-size: 14px;
            color: #666;
            margin: 0;
        }

        .pagination-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .pagination-button {
            padding: 8px 16px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            background: white;
            color: #666;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            transition: all 0.3s;
        }

        .pagination-button:hover:not(:disabled) {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .pagination-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination-button.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .pagination-pages {
            display: flex;
            gap: 5px;
            align-items: center;
        }

        /* Bookings Section */
        .bookings-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }

        .bookings-count {
            margin-left: 8px; 
            font-size: 14px; 
            color: #999;
        }

        .bookings-count {
            font-size: 14px;
            color: #666;
            background: #f0f0f0;
            padding: 5px 12px;
            border-radius: 20px;
        }

        /* Bookings Grid */
        .bookings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .booking-card {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            transition: all 0.3s;
            background: white;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .booking-card:hover {
            border-color: #667eea;
        }

        /* Booking Card CTA Button */
        .booking-card-cta {
            display: block;
            width: 100%;
            padding: 6px 16px;
            margin-top: 15px;
            background: #667eea;
            color: white;
            text: white;
            text-align: center;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
        }

        .booking-card-cta:hover {
            background: #5a6fd6;
            color: white;
        }

        .booking-card-cta i {
            margin-right: 6px;
            color: white;
        }

        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f0f0f0;
        }

        .booking-number {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }

        .booking-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-partial { background: #d1ecf1; color: #0c5460; }
        .status-ongoing { background: #cce5ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-overdue { background: #f8d7da; color: #721c24; }
        .status-cancelled { background: #f5c6cb; color: #721c24; }
        .status-pending-travel {background: #e7f3ff;color: #0b5ed7;}


        .booking-details {
            display: grid;
            gap: 12px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .detail-label {
            font-size: 13px;
            color: #666;
            font-weight: 500;
        }

        .detail-value {
            font-size: 14px;
            color: #333;
            font-weight: 500;
            text-align: right;
        }

        .detail-value.amount {
            font-size: 16px;
            font-weight: 600;
            color: #667eea;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 10px;
            color: #666;
        }

        .empty-state p {
            font-size: 14px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: 0;
            }

            .dashboard-container {
                padding: 15px 10px;
            }

            .dashboard-container-header {
                padding: 0px 10px;
            }


            .customer-details-section {
                padding: 20px 15px;
                margin-bottom: 20px;
            }

            .customer-details-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .customer-detail-label {
                font-size: 12px;
            }

            .customer-detail-value {
                font-size: 14px;
            }


            .tabs-container {
                padding: 20px 15px;
            }

            .tabs-header {
                flex-wrap: wrap;
                gap: 8px;
            }

            .tab-button {
                padding: 10px 16px;
                font-size: 14px;
            }

            .search-section {
                padding: 20px 15px;
                margin-bottom: 20px;
            }

            .search-title {
                font-size: 16px;
            }

            .search-helper {
                font-size: 12px;
            }

            .search-filters-wrapper {
                gap: 12px;
            }

            .search-input-wrapper {
                max-width: 100%;
            }

            .search-input {
                padding: 10px 35px 10px 12px;
                font-size: 13px;
            }

            .date-filters-wrapper {
                flex-direction: column;
                gap: 12px;
            }

            .date-filter-item {
                min-width: 100%;
            }

            .btn-clear-filters {
                width: 100%;
                justify-content: center;
            }

            .pagination-container {
                flex-direction: column;
                align-items: center;
                gap: 15px;
                margin-top: 20px;
                padding-top: 15px;
            }

            .pagination-info {
                font-size: 12px;
                margin: 0;
                text-align: center;
            }

            .pagination-controls {
                flex-wrap: wrap;
                gap: 5px;
                justify-content: center;
            }

            .pagination-button {
                padding: 6px 12px;
                font-size: 12px;
            }

            .bookings-section {
                padding: 20px 15px;
            }

            .bookings-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .booking-card {
                padding: 15px;
            }

            .booking-header {
                margin-bottom: 12px;
                padding-bottom: 12px;
            }

            .booking-number {
                font-size: 14px;
            }

            .booking-status {
                font-size: 10px;
                padding: 4px 10px;
            }

            .booking-details {
                gap: 10px;
            }

            .detail-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }

            .detail-label {
                font-size: 12px;
            }

            .detail-value {
                font-size: 13px;
                text-align: left;
            }

            .detail-value.amount {
                font-size: 15px;
            }

            .empty-state {
                padding: 40px 20px;
            }

            .empty-state i {
                font-size: 48px;
            }

            .empty-state h3 {
                font-size: 16px;
            }

            .empty-state p {
                font-size: 13px;
            }

            .dashboard-header {
                padding: 0px 10px;
            }
        }

        @media (max-width: 480px) {
            .dashboard-container {
                padding: 10px 5px;
            }

            .header-content {
                padding: 0 10px;
            }

            .header-title h1 {
                font-size: 18px;
            }

            .tabs-container {
                padding: 15px 10px;
            }

            .tab-button {
                padding: 8px 12px;
                font-size: 11px;
            }

            .bookings-count {
                font-size: 11px;
            }
            
            .bookings-section {
                padding: 15px 10px;
            }

            .section-title {
                font-size: 16px;
            }

            .booking-card {
                padding: 12px;
            }

            .booking-number {
                font-size: 13px;
            }
        }

        @media (min-width: 769px) and (max-width: 1024px) {
            .bookings-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 1025px) {
            .dashboard-container {
                padding: 40px 30px;
            }

            .bookings-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 1400px) {
            .dashboard-container {
                padding: 50px 40px;
            }

            .bookings-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        /* Loading State */
        .loading {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <?php $this->load->view('customer_portal/header'); ?>

    <!-- Main Container -->
    <div class="dashboard-container">
        <div class="dashboard-container-header">
            <h1>My Bookings</h1>
            <p>View and manage your travel bookings</p>
            <div class="search-section">
                <!-- What You Can Do Here -->
                <div class="portal-features-title">What You Can Do Here</div>
                <div class="portal-features-grid">
                    <div class="portal-feature-item">
                        <div class="portal-feature-icon icon-view">
                            <i class="la la-eye"></i>
                        </div>
                        <div class="portal-feature-content">
                            <span class="portal-feature-label">View Bookings</span>
                            <span class="portal-feature-desc">See all your upcoming and completed travel bookings</span>
                        </div>
                    </div>
                    <div class="portal-feature-item">
                        <div class="portal-feature-icon icon-download">
                            <i class="la la-download"></i>
                        </div>
                        <div class="portal-feature-content">
                            <span class="portal-feature-label">Download Documents</span>
                            <span class="portal-feature-desc">Access booking confirmations and travel vouchers</span>
                        </div>
                    </div>
                    <div class="portal-feature-item">
                        <div class="portal-feature-icon icon-track">
                            <i class="la la-map-marker"></i>
                        </div>
                        <div class="portal-feature-content">
                            <span class="portal-feature-label">Track Trip Status</span>
                            <span class="portal-feature-desc">Monitor payment and booking confirmation status</span>
                        </div>
                    </div>
                    <div class="portal-feature-item">
                        <div class="portal-feature-icon icon-review">
                            <i class="la la-star"></i>
                        </div>
                        <div class="portal-feature-content">
                            <span class="portal-feature-label">Leave Reviews</span>
                            <span class="portal-feature-desc">Share your travel experience and feedback</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Customer Details Section -->
        <div class="customer-details-section">
            <div class="card-title">
                Customer Details
            </div>
            <div class="customer-details-grid">
                <div class="customer-detail-item">
                    <span class="customer-detail-label">Name</span>
                    <span class="customer-detail-value"><?php echo htmlspecialchars($customer['name']); ?></span>
                </div>
                <?php if (!empty($customer['CustomerCode'])): ?>
                <div class="customer-detail-item">
                    <span class="customer-detail-label">Customer Code</span>
                    <span class="customer-detail-value"><?php echo htmlspecialchars($customer['CustomerCode']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($customer['email'])): ?>
                <div class="customer-detail-item">
                    <span class="customer-detail-label">Email</span>
                    <span class="customer-detail-value">
                        <a href="mailto:<?php echo htmlspecialchars($customer['email']); ?>" style="color: #667eea; text-decoration: none;">
                            <?php echo htmlspecialchars($customer['email']); ?>
                        </a>
                    </span>
                </div>
                <?php endif; ?>
                <?php if (!empty($customer['phone_number'])): ?>
                <div class="customer-detail-item">
                    <span class="customer-detail-label">WhatsApp</span>
                    <span class="customer-detail-value">
                        <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $customer['phone_number']); ?>" target="_blank" style="color: #25D366; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">
                            <i class="la la-whatsapp" style="font-size: 18px;"></i>
                            <?php echo htmlspecialchars($customer['phone_number']); ?>
                        </a>
                    </span>
                </div>
                <?php endif; ?>
                <?php if (!empty($customer['ChatLanguage'])): ?>
                <div class="customer-detail-item">
                    <span class="customer-detail-label">Chat Language</span>
                    <span class="customer-detail-value">
                        <?php echo htmlspecialchars($customer['ChatLanguage']); ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Global Search Section -->
        <div class="search-section">
            <div class="search-header">
                <div class="search-title">
                    Search & Filter Bookings
                </div>
            </div>
            <div class="search-filters-wrapper">
                <div class="search-input-wrapper">
                    <input type="text" 
                           class="search-input" 
                           id="global-search" 
                           placeholder="Search by booking number or destination...">
                    <i class="la la-search search-icon"></i>
                </div>
                <div class="date-filters-wrapper">
                    <div class="date-filter-item">
                        <label for="date-from" class="date-filter-label">From Date</label>
                        <input type="date" 
                               class="date-filter-input" 
                               id="date-from" 
                               placeholder="From date">
                    </div>
                    <div class="date-filter-item">
                        <label for="date-to" class="date-filter-label">To Date</label>
                        <input type="date" 
                               class="date-filter-input" 
                               id="date-to" 
                               placeholder="To date">
                    </div>
                    <button type="button" class="btn-clear-filters" id="clear-filters">
                        <i class="la la-times"></i> Clear
                    </button>
                </div>
            </div>
            <div class="search-helper">
                Search and date filters apply to all bookings across both tabs
            </div>
        </div>

        <!-- Tabs Section -->
        <div class="tabs-container">
            <div class="tabs-header">
                <button class="tab-button active" data-tab="upcoming">
                    Upcoming Bookings
                    <span class="bookings-count" id="count-upcoming">
                        (<?php echo count($upcoming_bookings); ?>)
                    </span>
                </button>
                <button class="tab-button" data-tab="completed">
                    Completed Bookings
                    <span class="bookings-count" id="count-completed">
                        (<?php echo count($completed_bookings); ?>)
                    </span>
                </button>
            </div>

            <!-- Upcoming Bookings Tab -->
            <div class="tab-content active" id="tab-upcoming">
                <?php if (empty($upcoming_bookings)): ?>
                    <div class="empty-state" id="empty-upcoming-default">
                        <i class="la la-calendar"></i>
                        <h3>No Upcoming Bookings</h3>
                        <p>You don't have any upcoming bookings at the moment.</p>
                    </div>
                <?php else: ?>
                    <div class="bookings-grid" id="bookings-upcoming" data-original-count="<?php echo count($upcoming_bookings); ?>">
                        <?php foreach ($upcoming_bookings as $booking): ?>
                        <?php
                        // Determine booking status based on BC stage visibility rules
                        $display_status = $booking['Status'];
                        $status_class = 'status-pending';
                        $status_text = 'Pending';
                        
                        // Check if travel date has passed
                        // Use EndDate if available, otherwise use StartDate
                        $travel_date_passed = false;
                        $travel_end_date = !empty($booking['EndDate']) ? $booking['EndDate'] : $booking['StartDate'];
                        if (!empty($travel_end_date)) {
                            // Compare dates (ignore time)
                            $travel_date = date('Y-m-d', strtotime($travel_end_date));
                            $travel_date_passed = $travel_date < date('Y-m-d');
                        }

                        if ($booking['CancelStatus'] == 'Y') {
                            $status_class = 'status-cancelled';
                            $status_text = 'Cancelled';
                        } elseif ($booking['Status'] == 'Y' && $booking['AfterSalesService'] == 'COMPLETE') {
                            // Completed: Status = 'Y' AND AfterSalesService = 'COMPLETE'
                            $status_class = 'status-completed';
                            $status_text = 'Completed';
                        } elseif ($travel_date_passed) {
                            // Travel date has passed - show as Completed
                            $status_class = 'status-completed';
                            $status_text = 'Completed';
                        } elseif (in_array($booking['Status'], ['PP', 'PTV', 'PT', 'OG'])) {
                            // Confirmed: Status IN ('PP', 'PTV', 'PT', 'OG') - after booking confirmation
                            if ($booking['Status'] == 'OG') {
                                $status_class = 'status-ongoing';
                                $status_text = 'Confirmed';
                            } elseif ($booking['Status'] == 'PP') {
                                $status_class = 'status-partial';
                                $status_text = 'Confirmed';
                            } elseif ($booking['Status'] == 'PT') {
                                $status_class = 'status-pending-travel';
                                $status_text = 'Confirmed';
                            } elseif ($booking['Status'] == 'PTV') {
                                $status_class = 'status-pending-travel';
                                $status_text = 'Confirmed';
                            }
                        } elseif ($booking['Status'] == 'P') {
                            // Pending: Status = 'P' (Pending Payment - before booking confirmation)
                            // Check if overdue
                            $deadline = !empty($booking['DepositDeadline']) ? $booking['DepositDeadline'] : $booking['FullPaymentDeadline'];
                            if (!empty($deadline) && strtotime($deadline) < strtotime('today')) {
                                $status_class = 'status-overdue';
                                $status_text = 'Pending';
                            } else {
                                $status_class = 'status-pending';
                                $status_text = 'Pending';
                            }
                        }
                        ?>
                        <div class="booking-card" data-booking-id="<?php echo htmlspecialchars($booking['BookingID'] ?? 'N/A'); ?>">
                            <div class="booking-header">
                                <div class="booking-number">
                                    <?php echo htmlspecialchars($booking['BookingNumber'] ?? 'N/A'); ?>
                                </div>
                                <span class="booking-status <?php echo $status_class; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                            </div>
                            <div class="booking-details">
                                <div class="detail-row">
                                    <span class="detail-label">Destination</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($booking['DestinationName'] ?? 'N/A'); ?></span>
                                </div>
                                <?php if (!empty($booking['StartDate'])): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Travel Date</span>
                                    <span class="detail-value">
                                        <?php echo date('M d, Y', strtotime($booking['StartDate'])); ?>
                                        <?php if (!empty($booking['EndDate'])): ?>
                                            - <?php echo date('M d, Y', strtotime($booking['EndDate'])); ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($booking['PaxInfo'])): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Travel Pax</span>
                                    <span class="detail-value">
                                        <?php echo htmlspecialchars($booking['PaxInfo']); ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($booking['NetTotal'])): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Total Amount</span>
                                    <span class="detail-value amount">
                                        <?php echo number_format($booking['NetTotal'], 2); ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($booking['DepositDeadline'])): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Deposit Deadline</span>
                                    <span class="detail-value">
                                        <?php echo date('M d, Y', strtotime($booking['DepositDeadline'])); ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($booking['FullPaymentDeadline'])): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Full Payment Deadline</span>
                                    <span class="detail-value">
                                        <?php echo date('M d, Y', strtotime($booking['FullPaymentDeadline'])); ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <a href="<?php echo base_url('customer/booking/' . urlencode($booking['Token'] ?? '')); ?>" class="booking-card-cta">
                                <i class="la la-eye"></i> View Details
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <div class="pagination-container" id="pagination-upcoming"></div>
                <?php endif; ?>
            </div>

            <!-- Completed Bookings Tab -->
            <div class="tab-content" id="tab-completed">
                <?php if (empty($completed_bookings)): ?>
                    <div class="empty-state" id="empty-completed-default">
                        <i class="la la-check-circle"></i>
                        <h3>No Completed Bookings</h3>
                        <p>Your completed bookings will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="bookings-grid" id="bookings-completed" data-original-count="<?php echo count($completed_bookings); ?>">
                        <?php foreach ($completed_bookings as $booking): ?>
                            <?php
                            // Completed bookings always show as completed
                            $status_class = 'status-completed';
                            $status_text = 'Completed';
                            ?>
                            <div class="booking-card" data-booking-id="<?php echo htmlspecialchars($booking['BookingID'] ?? 'N/A'); ?>">
                                <div class="booking-header">
                                    <div class="booking-number">
                                        <?php echo htmlspecialchars($booking['BookingNumber'] ?? 'N/A'); ?>
                                    </div>
                                    <span class="booking-status <?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </div>
                                <div class="booking-details">
                                    <div class="detail-row">
                                        <span class="detail-label">Destination</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($booking['DestinationName'] ?? 'N/A'); ?></span>
                                    </div>
                                    <?php if (!empty($booking['StartDate'])): ?>
                                    <div class="detail-row">
                                        <span class="detail-label">Travel Date</span>
                                        <span class="detail-value">
                                            <?php echo date('M d, Y', strtotime($booking['StartDate'])); ?>
                                            <?php if (!empty($booking['EndDate'])): ?>
                                                - <?php echo date('M d, Y', strtotime($booking['EndDate'])); ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($booking['PaxInfo'])): ?>
                                    <div class="detail-row">
                                        <span class="detail-label">Travel Pax</span>
                                        <span class="detail-value">
                                            <?php echo htmlspecialchars($booking['PaxInfo']); ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($booking['NetTotal'])): ?>
                                    <div class="detail-row">
                                        <span class="detail-label">Total Amount</span>
                                        <span class="detail-value amount">
                                            <?php echo number_format($booking['NetTotal'], 2); ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <a href="<?php echo base_url('customer/booking/' . urlencode($booking['Token'] ?? '')); ?>" class="booking-card-cta">
                                    <i class="la la-eye"></i> View Details
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <div class="pagination-container" id="pagination-completed"></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="<?php echo base_url('assets/js/plugins-bundle.js'); ?>"></script>
    <script>
        // Configuration
        const ITEMS_PER_PAGE = 6; // Number of bookings per page

        // Global search state
        let globalSearchTerm = '';
        let dateFrom = '';
        let dateTo = '';

        // Tab switching functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');

            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const targetTab = this.getAttribute('data-tab');

                    // Remove active class from all buttons and contents
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));

                    // Add active class to clicked button and corresponding content
                    this.classList.add('active');
                    document.getElementById('tab-' + targetTab).classList.add('active');
                    
                    // Apply current search to the newly active tab
                    applyGlobalSearch();
                });
            });

            // Initialize pagination and search for both tabs
            initializeTab('upcoming');
            initializeTab('completed');

            // Setup global search
            const globalSearchInput = document.getElementById('global-search');
            if (globalSearchInput) {
                globalSearchInput.addEventListener('input', function() {
                    globalSearchTerm = this.value.toLowerCase().trim();
                    applyGlobalSearch();
                });
            }

            // Setup date filters
            const dateFromInput = document.getElementById('date-from');
            const dateToInput = document.getElementById('date-to');
            const clearFiltersBtn = document.getElementById('clear-filters');

            // Function to validate and fix date range
            function validateDateRange() {
                if (dateFrom && dateTo) {
                    const fromDate = new Date(dateFrom);
                    const toDate = new Date(dateTo);
                    
                    if (toDate < fromDate) {
                        // Show error message
                        Swal.fire({
                            width: 550,
                            background: 'url(<?php echo base_url('assets/image/sweetalert.jpg') ?>)',
                            icon: 'error',
                            title: 'Invalid Date Range',
                            text: 'To Date cannot be before From Date. To Date has been adjusted to match From Date.',
                            showConfirmButton: false,
                            timer: 3000
                        });
                        
                        // Set To Date to From Date
                        dateTo = dateFrom;
                        if (dateToInput) {
                            dateToInput.value = dateFrom;
                        }
                    }
                }
            }

            if (dateFromInput) {
                dateFromInput.addEventListener('change', function() {
                    dateFrom = this.value;
                    validateDateRange();
                    applyGlobalSearch();
                });
            }

            if (dateToInput) {
                dateToInput.addEventListener('change', function() {
                    dateTo = this.value;
                    validateDateRange();
                    applyGlobalSearch();
                });
            }

            if (clearFiltersBtn) {
                clearFiltersBtn.addEventListener('click', function() {
                    globalSearchTerm = '';
                    dateFrom = '';
                    dateTo = '';
                    if (globalSearchInput) globalSearchInput.value = '';
                    if (dateFromInput) dateFromInput.value = '';
                    if (dateToInput) dateToInput.value = '';
                    applyGlobalSearch();
                });
            }
        });

        function initializeTab(tabName) {
            const bookingsContainer = document.getElementById('bookings-' + tabName);
            if (!bookingsContainer) return;

            // Store original bookings HTML as data attribute (before any manipulation)
            const originalHTML = bookingsContainer.innerHTML;
            bookingsContainer.dataset.originalHTML = originalHTML;
            
            // Get original bookings count
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = originalHTML;
            const originalBookings = Array.from(tempDiv.querySelectorAll('.booking-card'));
            const originalCount = originalBookings.length;
            bookingsContainer.dataset.originalCount = originalCount;

            // Initialize pagination and render first page
            renderPagination(tabName, originalCount, 1);
            renderBookings(tabName, originalBookings, 1);
        }

        function applyGlobalSearch() {
            // Get active tab
            const activeTab = document.querySelector('.tab-button.active');
            if (!activeTab) return;
            
            const activeTabName = activeTab.getAttribute('data-tab');
            
            // Apply search to both tabs but only show results for active tab
            filterAndPaginate('upcoming', activeTabName === 'upcoming');
            filterAndPaginate('completed', activeTabName === 'completed');
            
            // Update tab counts
            updateTabCounts();
        }

        function filterAndPaginate(tabName, isActiveTab = true) {
            const bookingsContainer = document.getElementById('bookings-' + tabName);
            if (!bookingsContainer) return;

            // Get original bookings from stored HTML
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = bookingsContainer.dataset.originalHTML || '';
            const allBookings = Array.from(tempDiv.querySelectorAll('.booking-card'));
            
            // Filter bookings using global search term and date filters
            let filteredBookings = allBookings;
            
            // Apply text search filter
            if (globalSearchTerm) {
                filteredBookings = filteredBookings.filter(booking => {
                    const bookingNumber = booking.querySelector('.booking-number')?.textContent.toLowerCase() || '';
                    const destination = booking.querySelector('.detail-value')?.textContent.toLowerCase() || '';
                    return bookingNumber.includes(globalSearchTerm) || destination.includes(globalSearchTerm);
                });
            }
            
            // Apply date filters
            if (dateFrom || dateTo) {
                filteredBookings = filteredBookings.filter(booking => {
                    // Find all detail rows to locate the travel date
                    const detailRows = booking.querySelectorAll('.detail-row');
                    let travelDateText = '';
                    
                    // Find the row that contains "Travel Date"
                    for (let row of detailRows) {
                        const label = row.querySelector('.detail-label');
                        if (label && label.textContent.toLowerCase().includes('travel date')) {
                            travelDateText = row.querySelector('.detail-value')?.textContent || '';
                            break;
                        }
                    }
                    
                    if (!travelDateText) return true; // If no travel date, include it
                    
                    // Parse the date (format: "Jan 15, 2024" or "Jan 15, 2024 - Jan 20, 2024")
                    // Extract the start date (first date in the range)
                    const dateMatch = travelDateText.match(/(\w{3})\s+(\d{1,2}),\s+(\d{4})/);
                    
                    if (!dateMatch) return true;
                    
                    // Parse the date (format: "Jan 15, 2024")
                    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                    const monthIndex = monthNames.indexOf(dateMatch[1]);
                    if (monthIndex === -1) return true;
                    
                    const bookingDate = new Date(parseInt(dateMatch[3]), monthIndex, parseInt(dateMatch[2]));
                    bookingDate.setHours(0, 0, 0, 0); // Reset time to midnight for accurate comparison
                    
                    const fromDate = dateFrom ? new Date(dateFrom) : null;
                    if (fromDate) fromDate.setHours(0, 0, 0, 0);
                    
                    const toDate = dateTo ? new Date(dateTo) : null;
                    if (toDate) toDate.setHours(23, 59, 59, 999); // End of day
                    
                    // Check if booking date is within range
                    if (fromDate && bookingDate < fromDate) return false;
                    if (toDate && bookingDate > toDate) return false;
                    
                    return true;
                });
            }

            // Get current page (only reset if search or date filters changed)
            let currentPage = parseInt(bookingsContainer.dataset.currentPage || '1');
            const currentFilterKey = globalSearchTerm + '|' + dateFrom + '|' + dateTo;
            if (bookingsContainer.dataset.lastFilterKey !== currentFilterKey) {
                currentPage = 1;
                bookingsContainer.dataset.currentPage = currentPage;
                bookingsContainer.dataset.lastFilterKey = currentFilterKey;
            }

            // Show/hide empty state
            const emptyStateDefault = document.getElementById('empty-' + tabName + '-default');
            if (emptyStateDefault) {
                const hasFilters = globalSearchTerm || dateFrom || dateTo;
                if (filteredBookings.length === 0 && hasFilters) {
                    emptyStateDefault.style.display = 'none';
                } else if (allBookings.length === 0) {
                    emptyStateDefault.style.display = 'block';
                } else {
                    emptyStateDefault.style.display = 'none';
                }
            }

            // Store filtered count for tab badge update (always store it)
            bookingsContainer.dataset.filteredCount = filteredBookings.length;
            
            // Only render if this is the active tab
            if (isActiveTab) {
                renderBookings(tabName, filteredBookings, currentPage);
                renderPagination(tabName, filteredBookings.length, currentPage);
            }
        }

        function updateTabCounts() {
            const upcomingContainer = document.getElementById('bookings-upcoming');
            const completedContainer = document.getElementById('bookings-completed');
            
            if (upcomingContainer) {
                const filteredCount = upcomingContainer.dataset.filteredCount || upcomingContainer.dataset.originalCount || 0;
                const countElement = document.getElementById('count-upcoming');
                if (countElement) {
                    countElement.textContent = `(${filteredCount})`;
                }
            }
            
            if (completedContainer) {
                const filteredCount = completedContainer.dataset.filteredCount || completedContainer.dataset.originalCount || 0;
                const countElement = document.getElementById('count-completed');
                if (countElement) {
                    countElement.textContent = `(${filteredCount})`;
                }
            }
        }

        function renderBookings(tabName, bookings, page) {
            const bookingsContainer = document.getElementById('bookings-' + tabName);
            if (!bookingsContainer) return;

            // Clear container
            bookingsContainer.innerHTML = '';

            // Calculate pagination
            const startIndex = (page - 1) * ITEMS_PER_PAGE;
            const endIndex = startIndex + ITEMS_PER_PAGE;
            const paginatedBookings = bookings.slice(startIndex, endIndex);

            // Render bookings
            paginatedBookings.forEach(booking => {
                bookingsContainer.appendChild(booking.cloneNode(true));
            });

            // Store current page
            bookingsContainer.dataset.currentPage = page;

            // Show empty state if no results
            if (paginatedBookings.length === 0) {
                const emptyState = document.createElement('div');
                emptyState.className = 'empty-state';
                emptyState.style.gridColumn = '1 / -1';
                if (globalSearchTerm) {
                    emptyState.innerHTML = `
                        <i class="la la-search"></i>
                        <h3>No Bookings Found</h3>
                        <p>No bookings match your search "${globalSearchTerm}". Try adjusting your search terms.</p>
                    `;
                } else {
                    emptyState.innerHTML = `
                        <i class="la la-calendar"></i>
                        <h3>No Bookings</h3>
                        <p>No bookings to display.</p>
                    `;
                }
                bookingsContainer.appendChild(emptyState);
            }
        }

        function renderPagination(tabName, totalItems, currentPage) {
            const paginationContainer = document.getElementById('pagination-' + tabName);
            if (!paginationContainer) return;

            const totalPages = Math.ceil(totalItems / ITEMS_PER_PAGE);

            // Pagination info (left side) - always show
            const startItem = totalItems === 0 ? 0 : (currentPage - 1) * ITEMS_PER_PAGE + 1;
            const endItem = Math.min(currentPage * ITEMS_PER_PAGE, totalItems);
            const infoHTML = `
                <span class="pagination-info">
                    Showing ${startItem} - ${endItem} of ${totalItems} Record(s)
                </span>
            `;

            // Pagination controls (right side) - only show if more than 1 page
            let controlsHTML = '';
            if (totalPages > 1) {
                controlsHTML = '<div class="pagination-controls">';

            // Previous button
            controlsHTML += `
                <button class="pagination-button" 
                        onclick="changePage('${tabName}', ${currentPage - 1})"
                        ${currentPage === 1 ? 'disabled' : ''}>
                    ‹ Prev
                </button>
            `;

            // Page numbers
            const maxPagesToShow = 5;
            let startPage = Math.max(1, currentPage - Math.floor(maxPagesToShow / 2));
            let endPage = Math.min(totalPages, startPage + maxPagesToShow - 1);

            if (endPage - startPage < maxPagesToShow - 1) {
                startPage = Math.max(1, endPage - maxPagesToShow + 1);
            }

            if (startPage > 1) {
                controlsHTML += `
                    <button class="pagination-button" onclick="changePage('${tabName}', 1)">1</button>
                `;
                if (startPage > 2) {
                    controlsHTML += `<span style="color: #999; padding: 0 5px;">...</span>`;
                }
            }

            for (let i = startPage; i <= endPage; i++) {
                controlsHTML += `
                    <button class="pagination-button ${i === currentPage ? 'active' : ''}" 
                            onclick="changePage('${tabName}', ${i})">
                        ${i}
                    </button>
                `;
            }

            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    controlsHTML += `<span style="color: #999; padding: 0 5px;">...</span>`;
                }
                controlsHTML += `
                    <button class="pagination-button" onclick="changePage('${tabName}', ${totalPages})">
                        ${totalPages}
                    </button>
                `;
            }

            // Next button
            controlsHTML += `
                <button class="pagination-button" 
                        onclick="changePage('${tabName}', ${currentPage + 1})"
                        ${currentPage === totalPages ? 'disabled' : ''}>
                    Next ›
                </button>
            `;

            controlsHTML += '</div>';
            }

            paginationContainer.innerHTML = infoHTML + controlsHTML;
        }

        function changePage(tabName, page) {
            const bookingsContainer = document.getElementById('bookings-' + tabName);
            if (!bookingsContainer) return;

            // Get original bookings from stored HTML
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = bookingsContainer.dataset.originalHTML || '';
            const allBookings = Array.from(tempDiv.querySelectorAll('.booking-card'));
            
            // Filter using global search term
            let filteredBookings = allBookings;
            if (globalSearchTerm) {
                filteredBookings = allBookings.filter(booking => {
                    const bookingNumber = booking.querySelector('.booking-number')?.textContent.toLowerCase() || '';
                    const destination = booking.querySelector('.detail-value')?.textContent.toLowerCase() || '';
                    return bookingNumber.includes(globalSearchTerm) || destination.includes(globalSearchTerm);
                });
            }

            // Render with new page
            renderBookings(tabName, filteredBookings, page);
            renderPagination(tabName, filteredBookings.length, page);

            // Scroll to top of bookings section
            bookingsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

    </script>
</body>
</html>

