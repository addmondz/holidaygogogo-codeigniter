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

        /* Header */
        .dashboard-header {
            background: #162447;
            color: white;
            padding: 10px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-title h1 {
            font-size: 24px;
            font-weight: 600;
            margin: 0;
        }

        .header-title p {
            font-size: 14px;
            opacity: 0.9;
            margin: 5px 0 0 0;
        }

        .customer-info {
            text-align: right;
        }

        .customer-info strong {
            display: block;
            font-size: 16px;
            margin-bottom: 5px;
        }

        .customer-info span {
            font-size: 13px;
            opacity: 0.9;
        }

        /* Main Container */
        .dashboard-container {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        /* Filters Section */
        .filters-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .filters-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filters-title i {
            color: #667eea;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-size: 13px;
            font-weight: 500;
            color: #666;
            margin-bottom: 8px;
        }

        .filter-group select,
        .filter-group input {
            padding: 10px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            transition: border-color 0.3s;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-filter {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #666;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
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
            cursor: pointer;
        }

        .booking-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
            border-color: #667eea;
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
        .status-pending-travel { background: #e2e3e5; color: #383d41; }

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

            .dashboard-header {
                padding: 8px 0;
            }

            .header-content {
                padding: 0 15px;
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }

            .header-title h1 {
                font-size: 20px;
            }

            .header-title p {
                font-size: 12px;
            }

            .customer-info {
                text-align: center;
            }

            .customer-info strong {
                font-size: 14px;
            }

            .customer-info span {
                font-size: 12px;
            }

            .filters-section {
                padding: 20px 15px;
                margin-bottom: 20px;
            }

            .filters-title {
                font-size: 16px;
                margin-bottom: 15px;
            }

            .filters-grid {
                grid-template-columns: 1fr;
                gap: 15px;
                margin-bottom: 15px;
            }

            .filter-group label {
                font-size: 12px;
            }

            .filter-group select,
            .filter-group input {
                padding: 8px 10px;
                font-size: 13px;
            }

            .filter-actions {
                width: 100%;
                flex-direction: column;
            }

            .btn-filter {
                flex: 1;
                width: 100%;
            }

            .bookings-section {
                padding: 20px 15px;
            }

            .section-header {
                margin-bottom: 20px;
            }

            .section-title {
                font-size: 18px;
            }

            .bookings-count {
                font-size: 12px;
                padding: 4px 10px;
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

            .filters-section,
            .bookings-section {
                padding: 15px 10px;
            }

            .filters-title {
                font-size: 15px;
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
    <!-- Header -->
    <div class="dashboard-header">
        <div class="header-content">
            <div class="header-title">
                    <a>
                        <img src="<?php echo base_url('assets/image/logo.png'); ?>" class="max-h-75px">
                    </a>
            </div>
            <div class="customer-info">
                <strong><?php echo htmlspecialchars($customer['name']); ?></strong>
                <?php if (!empty($customer['CustomerCode'])): ?>
                <span>Code: <?php echo htmlspecialchars($customer['CustomerCode']); ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="dashboard-container">
        <h1>My Bookings</h1>
        <p>View and manage your travel bookings</p>
        <!-- Filters Section -->
        <div class="filters-section">
            <div class="filters-title">
                <i class="la la-filter"></i>
                Filter Bookings
            </div>
            <form method="GET" action="<?php echo base_url('customer/' . urlencode($hash)); ?>" id="filterForm">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="status">Booking Status</label>
                        <select name="status" id="status">
                            <?php foreach ($status_options as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo ($status_filter == $value) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="travel_date_from">Travel Date From</label>
                        <input type="date" name="travel_date_from" id="travel_date_from" 
                               value="<?php echo htmlspecialchars($travel_date_from ?? ''); ?>">
                    </div>
                    <div class="filter-group">
                        <label for="travel_date_to">Travel Date To</label>
                        <input type="date" name="travel_date_to" id="travel_date_to" 
                               value="<?php echo htmlspecialchars($travel_date_to ?? ''); ?>">
                    </div>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn-filter btn-primary">
                        Apply Filters
                    </button>
                    <a href="<?php echo base_url('customer/' . urlencode($hash)); ?>" class="btn-filter btn-secondary" style="text-decoration: none; display: inline-block;">
                        Clear Filters
                    </a>
                </div>
            </form>
        </div>

        <!-- Bookings Section -->
        <div class="bookings-section">
            <div class="section-header">
                <h2 class="section-title">Your Bookings</h2>
                <span class="bookings-count"><?php echo count($bookings); ?> booking(s)</span>
            </div>

            <?php if (empty($bookings)): ?>
                <div class="empty-state">
                    <i class="la la-suitcase"></i>
                    <h3>No Bookings Found</h3>
                    <p>Try adjusting your filters or check back later.</p>
                </div>
            <?php else: ?>
                <div class="bookings-grid">
                    <?php foreach ($bookings as $booking): ?>
                        <?php
                        // Determine booking status
                        $display_status = $booking['Status'];
                        $status_class = 'status-pending';
                        $status_text = 'Pending';

                        if ($booking['CancelStatus'] == 'Y') {
                            $status_class = 'status-cancelled';
                            $status_text = 'Cancelled';
                        } elseif ($booking['Status'] == 'Y') {
                            $status_class = 'status-completed';
                            $status_text = 'Completed';
                        } elseif ($booking['Status'] == 'OG') {
                            $status_class = 'status-ongoing';
                            $status_text = 'On-Going';
                        } elseif ($booking['Status'] == 'PP') {
                            $status_class = 'status-partial';
                            $status_text = 'Partial Payment';
                        } elseif ($booking['Status'] == 'PT') {
                            $status_class = 'status-pending-travel';
                            $status_text = 'Pending Travel';
                        } elseif ($booking['Status'] == 'PTV') {
                            $status_class = 'status-pending-travel';
                            $status_text = 'Pending Travel Voucher';
                        } elseif ($booking['Status'] == 'P') {
                            // Check if overdue
                            $deadline = !empty($booking['DepositDeadline']) ? $booking['DepositDeadline'] : $booking['FullPaymentDeadline'];
                            if (!empty($deadline) && strtotime($deadline) < strtotime('today')) {
                                $status_class = 'status-overdue';
                                $status_text = 'Payment Overdue';
                            } else {
                                $status_class = 'status-pending';
                                $status_text = 'Pending Payment';
                            }
                        }
                        ?>
                        <a href="<?php echo base_url('customer/booking/' . urlencode($booking['Token'] ?? '')); ?>" class="booking-card" data-booking-id="<?php echo htmlspecialchars($booking['BookingID'] ?? 'N/A'); ?>">
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
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="<?php echo base_url('assets/js/plugins-bundle.js'); ?>"></script>
</body>
</html>

