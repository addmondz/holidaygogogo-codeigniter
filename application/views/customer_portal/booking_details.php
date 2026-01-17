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
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
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
            margin-bottom: 20px;
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
            box-shadow: 0 0 0 3px rgba(80, 200, 120, 0.2);
        }

        .timeline-item.completed::after {
            content: '\f00c';
            font-family: 'Line Awesome Free';
            font-weight: 900;
            position: absolute;
            left: -26px;
            top: 5px;
            width: 16px;
            height: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 10px;
            z-index: 2;
        }

        .timeline-item.pending::before {
            background: #FF9800;
            border-color: #FF9800;
            box-shadow: 0 0 0 3px rgba(255, 152, 0, 0.2);
            animation: pendingPulse 2s ease-in-out infinite;
        }

        .timeline-item.pending::after {
            content: '\f071';
            font-family: 'Line Awesome Free';
            font-weight: 900;
            position: absolute;
            left: -26px;
            top: 5px;
            width: 16px;
            height: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 9px;
            z-index: 2;
        }

        @keyframes pendingPulse {
            0%, 100% {
                box-shadow: 0 0 0 3px rgba(255, 152, 0, 0.2);
            }
            50% {
                box-shadow: 0 0 0 5px rgba(255, 152, 0, 0.3);
            }
        }

        .timeline-item.available::before {
            background: #6c757d;
            border-color: #6c757d;
        }

        .timeline-content {
            background: #ffffff;
            border-radius: 6px;
            padding: 12px;
            border-left: 3px solid #e0e0e0;
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .timeline-item.completed .timeline-content {
            border-left-color: #50C878;
            border-left-width: 3px;
            background: #f0fdf4;
        }

        .timeline-item.pending .timeline-content {
            border-left-color: #FF9800;
            border-left-width: 3px;
            background: #fffbf0;
        }

        .timeline-item.available .timeline-content {
            border-left-color: #6c757d;
            border-left-width: 3px;
            background: #f8f9fa;
        }

        /* Overdue styling - more prominent red */
        .timeline-item.pending .timeline-action-text.overdue {
            color: #d32f2f;
            font-weight: 600;
        }

        .timeline-date {
            font-size: 11px;
            color: #666;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .timeline-title {
            font-size: 13px;
            font-weight: 600;
            color: #333;
            margin-bottom: 6px;
        }

        .timeline-action {
            font-size: 11px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            width: auto;
            border-radius: 8px;
            padding: 4px 20px;
            font-weight: 500;
        }

        /* Completed status - green badge */
        .timeline-item.completed .timeline-action {
            background-color: #d4edda;
            color: #155724;
        }

        /* Pending status - yellow badge (including overdue) */
        .timeline-item.pending .timeline-action {
            background-color: #fff3cd;
            color: #856404;
        }

        /* Overdue - also yellow (same as pending) */
        .timeline-item.pending .timeline-action.overdue {
            background-color: #fff3cd;
            color: #856404;
        }

        /* Available/Default status - gray badge */
        .timeline-item.available .timeline-action {
            background-color: #e9ecef;
            color: #495057;
        }

        .timeline-action a {
            color: inherit;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .timeline-action a:hover {
            opacity: 0.8;
        }

        .timeline-action-text {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        /* Icons for different action types - inherit text color */
        .timeline-action i,
        .timeline-action-text i,
        .timeline-action a i {
            font-size: 12px;
            color: inherit !important;
        }

        /* Remove status badges - visual indicators are enough */

        /* Highlighted timeline item for review */
        .timeline-item-highlight {
            animation: highlightPulse 2s ease-in-out infinite;
        }

        .timeline-item-highlight .timeline-content {
            background: linear-gradient(135deg, #fff5e6 0%, #ffe6cc 100%);
            border-left: 4px solid #ff9800;
            box-shadow: 0 2px 8px rgba(255, 152, 0, 0.2);
        }

        .timeline-item-highlight::before {
            background: #ff9800;
            border-color: #ff9800;
            box-shadow: 0 0 10px rgba(255, 152, 0, 0.5);
        }

        @keyframes highlightPulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.9;
            }
        }

        .timeline-review-cta {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #ff9800;
            color: white !important;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            text-decoration: none !important;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(255, 152, 0, 0.3);
            cursor: pointer;
        }

        .timeline-review-cta:hover {
            background: #f57c00;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 152, 0, 0.4);
        }

        .timeline-review-cta i {
            font-size: 16px;
        }

        /* Clickable timeline item */
        .timeline-item-clickable .timeline-content {
            transition: all 0.3s ease;
        }

        .timeline-item-clickable:hover .timeline-content {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(255, 152, 0, 0.3);
        }

        .timeline-item-clickable:hover .timeline-review-cta {
            background: #f57c00;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 152, 0, 0.4);
        }

        .timeline-icon {
            display: inline-block;
            margin-right: 6px;
            font-size: 12px;
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

                <!-- Contact Actions -->
                <div class="documents-grid" style="margin-top: 20px;">
                    <?php if (!empty($booking['SalesAgentMobile'])): 
                        $formatted_mobile = format_mobile_number($booking['SalesAgentMobile']);
                        if ($formatted_mobile): ?>
                            <a href="tel:<?php echo $formatted_mobile; ?>" class="document-item">
                                <div class="document-icon">
                                    <i class="la la-phone"></i>
                                </div>
                                <div class="document-name">Call Sales Agent</div>
                                <div class="document-action">Click to Call</div>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if (get_offical_whatsapp_link() != null): ?>
                        <a href="<?php echo get_offical_whatsapp_link('Hi, I have a question about my booking. Booking no. ' . $booking['BookingNumber']); ?>" target="_blank" class="document-item">
                            <div class="document-icon">
                                <i class="la la-whatsapp"></i>
                            </div>
                            <div class="document-name">WhatsApp Us</div>
                            <div class="document-action">Click to Chat</div>
                        </a>
                    <?php endif; ?>
                </div>
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

                    // Analyze payment types to determine what payment upload events to show
                    $has_deposit_payment = false;
                    $has_full_payment = false;
                    $has_balance_payment = false;
                    $deposit_payment_date = null;
                    $full_payment_date = null;
                    $balance_payment_date = null;
                    
                    if (!empty($booking['payments'])) {
                        foreach ($booking['payments'] as $payment) {
                            if (!empty($payment['Credit']) && $payment['Credit'] > 0 && 
                                ($payment['Status'] == 'Y' || $payment['Status'] == 'P')) {
                                
                                $payment_type = !empty($payment['Type']) ? strtoupper(trim($payment['Type'])) : '';
                                $payment_date = !empty($payment['DateRaw']) ? $payment['DateRaw'] : null;
                                
                                if ($payment_type == 'DEPOSIT') {
                                    $has_deposit_payment = true;
                                    if (empty($deposit_payment_date) && $payment_date) {
                                        $deposit_payment_date = $payment_date;
                                    }
                                    // If we already have a full payment, treat it as balance payment
                                    if ($has_full_payment && !empty($full_payment_date)) {
                                        $has_balance_payment = true;
                                        if (empty($balance_payment_date)) {
                                            $balance_payment_date = $full_payment_date;
                                        }
                                    }
                                } elseif ($payment_type == 'FULL') {
                                    $has_full_payment = true;
                                    if (empty($full_payment_date) && $payment_date) {
                                        $full_payment_date = $payment_date;
                                    }
                                    // If we already have a deposit, treat FULL as balance payment
                                    if ($has_deposit_payment) {
                                        $has_balance_payment = true;
                                        if (empty($balance_payment_date) && $payment_date) {
                                            $balance_payment_date = $payment_date;
                                        }
                                    }
                                } else {
                                    // Check if this is a balance payment (not deposit, not full, but has credit)
                                    // Balance payments might be ADDITIONAL PAYMENT or other types
                                    if ($payment_type != 'DEPOSIT' && $payment_type != 'FULL') {
                                        $has_balance_payment = true;
                                        if (empty($balance_payment_date) && $payment_date) {
                                            $balance_payment_date = $payment_date;
                                        }
                                    }
                                }
                            }
                        }
                    }
                    
                    // Determine payment structure: full payment only, or deposit + balance
                    // Priority: Check actual payments first, then fall back to deadlines
                    $payment_structure = 'none';
                    
                    if ($has_full_payment && !$has_deposit_payment) {
                        // Only full payment exists in actual payments
                        $payment_structure = 'full_only';
                    } elseif ($has_deposit_payment) {
                        // Has deposit payment, might have balance too
                        $payment_structure = 'deposit_balance';
                    } else {
                        // No payments yet - determine structure from deadlines
                        $has_deposit_deadline = !empty($booking['DepositDeadlineRaw']);
                        $has_full_payment_deadline = !empty($booking['FullPaymentDeadlineRaw']);
                        
                        if ($has_deposit_deadline && $has_full_payment_deadline) {
                            // Both deadlines exist - deposit + balance structure
                            $payment_structure = 'deposit_balance';
                        } elseif ($has_full_payment_deadline && !$has_deposit_deadline) {
                            // Only full payment deadline exists - full payment only
                            $payment_structure = 'full_only';
                        } elseif ($has_deposit_deadline) {
                            // Only deposit deadline exists - deposit + balance structure (balance deadline might come later)
                            $payment_structure = 'deposit_balance';
                        }
                    }
                    
                    // Event 2: Upload payment proof based on payment structure
                    if ($payment_structure == 'full_only') {
                        // Show single full payment upload event
                        // Use payment date if payment exists, otherwise use deadline
                        $full_event_date = !empty($full_payment_date) ? $full_payment_date : 
                                          (!empty($booking['FullPaymentDeadlineRaw']) ? $booking['FullPaymentDeadlineRaw'] : 
                                          (!empty($booking['DepositDeadlineRaw']) ? $booking['DepositDeadlineRaw'] : null));
                        
                        if ($full_event_date) {
                            $full_deadline = !empty($booking['FullPaymentDeadlineRaw']) ? $booking['FullPaymentDeadlineRaw'] : 
                                            (!empty($booking['DepositDeadlineRaw']) ? $booking['DepositDeadlineRaw'] : null);
                            $full_deadline_passed = $full_deadline ? (strtotime($full_deadline) < strtotime($today)) : false;
                            $full_paid = $booking['balance_due'] <= 0 || $has_full_payment;
                            
                            $timeline_events[] = [
                                'date' => date('d/m/y', strtotime($full_event_date)),
                                'title' => 'Upload payment proof (Full Payment)',
                                'action' => $full_paid ? 'Completed' : ($full_deadline_passed ? 'Overdue' : 'Pending'),
                                'status' => $full_paid ? 'completed' : ($full_deadline_passed ? 'pending' : 'pending'),
                                'icon' => 'la la-upload'
                            ];
                        }
                    } elseif ($payment_structure == 'deposit_balance') {
                        // Show deposit upload event if deposit deadline exists
                        // Use payment date if payment exists, otherwise use deadline
                        if (!empty($booking['DepositDeadlineRaw']) || !empty($deposit_payment_date)) {
                            $deposit_event_date = !empty($deposit_payment_date) ? $deposit_payment_date : $booking['DepositDeadlineRaw'];
                            $deposit_deadline_passed = !empty($booking['DepositDeadlineRaw']) ? (strtotime($booking['DepositDeadlineRaw']) < strtotime($today)) : false;
                            
                            $timeline_events[] = [
                                'date' => date('d/m/y', strtotime($deposit_event_date)),
                                'title' => 'Upload payment proof (Deposit)',
                                'action' => $has_deposit_payment ? 'Completed' : ($deposit_deadline_passed ? 'Overdue' : 'Pending'),
                                'status' => $has_deposit_payment ? 'completed' : ($deposit_deadline_passed ? 'pending' : 'pending'),
                                'icon' => 'la la-upload'
                            ];
                        }
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

                    // Event 5: Upload payment proof (Full) - only show if deposit+balance structure, not for full payment only
                    if ($payment_structure == 'deposit_balance' && (!empty($booking['FullPaymentDeadlineRaw']) || !empty($balance_payment_date) || !empty($full_payment_date))) {
                        // Use payment date if balance payment exists (could be FULL payment after deposit, or other balance payment type)
                        // Priority: balance_payment_date > full_payment_date (when deposit exists) > deadline
                        $balance_event_date = null;
                        if (!empty($balance_payment_date)) {
                            $balance_event_date = $balance_payment_date;
                        } elseif (!empty($full_payment_date) && $has_deposit_payment) {
                            // If we have deposit and full payment, use full payment date as balance
                            $balance_event_date = $full_payment_date;
                        } elseif (!empty($booking['FullPaymentDeadlineRaw'])) {
                            $balance_event_date = $booking['FullPaymentDeadlineRaw'];
                        }
                        
                        if ($balance_event_date) {
                            $balance_deadline_passed = !empty($booking['FullPaymentDeadlineRaw']) ? (strtotime($booking['FullPaymentDeadlineRaw']) < strtotime($today)) : false;
                            $balance_paid = $booking['balance_due'] <= 0 || $has_balance_payment || ($has_full_payment && $has_deposit_payment);
                            
                            $timeline_events[] = [
                                'date' => date('d/m/y', strtotime($balance_event_date)),
                                'title' => 'Upload payment proof (Full)',
                                'action' => $balance_paid ? 'Completed' : ($balance_deadline_passed ? 'Overdue' : 'Pending'),
                                'status' => $balance_paid ? 'completed' : ($balance_deadline_passed ? 'pending' : 'pending'),
                                'icon' => 'la la-upload'
                            ];
                        }
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

                    // Event 7: Submit/View Review - only after travel date and if allowed
                    if (!empty($booking['EndDateRaw'])) {
                        $travel_ended = strtotime($booking['EndDateRaw']) < strtotime($today);
                        $allow_review = !empty($booking['AllowReview']) && $booking['AllowReview'] == 1;
                        $has_review = !empty($booking['CustomerReview']);
                        $booking_token = !empty($booking['Token']) ? $booking['Token'] : '';

                        if ($travel_ended && $allow_review) {
                            if ($has_review) {
                                // Review already submitted - show view option
                                $review_date = !empty($booking['CustomerReviewTimestamp'])
                                    ? date('d/m/y', strtotime($booking['CustomerReviewTimestamp']))
                                    : date('d/m/y', strtotime($booking['EndDateRaw']));
                                $timeline_events[] = [
                                    'date' => $review_date,
                                    'title' => 'View Review',
                                    'action' => '<a href="#" class="view-review-link" data-booking-token="' . htmlspecialchars($booking_token) . '">View Your Review</a>',
                                    'status' => 'completed',
                                    'icon' => 'la la-star'
                                ];
                            } else {
                                // No review yet - show submit option with prominent styling
                                $timeline_events[] = [
                                    'date' => date('d/m/y', strtotime($booking['EndDateRaw'])),
                                    'title' => 'Submit Review',
                                    'action' => '<span class="timeline-review-cta"><i class="la la-star" style="color: white;"></i> Submit Your Review</span>',
                                    'status' => 'pending',
                                    'icon' => 'la la-star',
                                    'highlight' => true,
                                    'clickable' => true,
                                    'booking_token' => $booking_token
                                ];
                            }
                        }
                    }

                    // Display timeline events
                    foreach ($timeline_events as $event):
                        $highlight_class = !empty($event['highlight']) ? 'timeline-item-highlight' : '';
                        $clickable_class = !empty($event['clickable']) ? 'timeline-item-clickable' : '';
                        $data_attrs = '';
                        if (!empty($event['booking_token'])) {
                            $data_attrs = 'data-booking-token="' . htmlspecialchars($event['booking_token']) . '"';
                        }
                        
                        // Determine action type and prepare display
                        $original_action = $event['action'];
                        $has_link = stripos($original_action, '<a') !== false || stripos($original_action, '<span') !== false;
                        $action_display = $original_action;
                        
                        // Only simplify if it's not a link
                        if (!$has_link) {
                            if ($event['status'] == 'completed' && stripos($original_action, 'Completed') !== false) {
                                $action_display = '';
                            } elseif ($event['status'] == 'pending') {
                                if (stripos($original_action, 'Overdue') !== false) {
                                    $action_display = 'Overdue';
                                } elseif (stripos($original_action, 'Pending') !== false) {
                                    $action_display = 'Pending';
                                }
                            }
                        }
                    ?>
                        <div class="timeline-item <?php echo $event['status']; ?> <?php echo $highlight_class; ?> <?php echo $clickable_class; ?>" <?php echo $data_attrs; ?>>
                            <div class="timeline-content">
                                <div class="timeline-date">
                                    <i class="<?php echo $event['icon']; ?> timeline-icon"></i>
                                    <?php echo $event['date']; ?>
                                </div>
                                <div class="timeline-title">
                                    <?php echo htmlspecialchars($event['title']); ?>
                                </div>
                                <div class="timeline-action<?php echo ($event['status'] == 'pending' && stripos($action_display, 'Overdue') !== false) ? ' overdue' : ''; ?>">
                                    <?php 
                                    if ($has_link) {
                                        // For links (View BC, View Receipt, etc.) - add view icon
                                        // Add icon before the link text
                                        $link_html = preg_replace('/(<a[^>]*>)(.*?)(<\/a>)/i', '$1<i class="la la-eye"></i> $2$3', $original_action);
                                        echo $link_html;
                                    } elseif ($event['status'] == 'completed') {
                                        // For completed actions - add checkmark icon
                                        $text = !empty($action_display) ? htmlspecialchars($action_display) : 'Completed';
                                        echo '<span class="timeline-action-text"><i class="la la-check"></i> ' . $text . '</span>';
                                    } elseif (stripos($action_display, 'Overdue') !== false) {
                                        // For overdue actions - add warning icon
                                        echo '<span class="timeline-action-text"><i class="la la-exclamation-triangle"></i> Overdue</span>';
                                    } elseif (stripos($action_display, 'Pending') !== false || $event['status'] == 'pending') {
                                        // For pending actions - add clock icon
                                        echo '<span class="timeline-action-text"><i class="la la-clock"></i> Pending</span>';
                                    } elseif (!empty($action_display)) {
                                        // Fallback for any other text
                                        echo '<span class="timeline-action-text">' . htmlspecialchars($action_display) . '</span>';
                                    }
                                    ?>
                                </div>
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
                                <th>Amount</th>
                                <th>Reference</th>
                                <th>Status</th>
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
                    <?php 
                    // Check if travel voucher has been sent (status PT or later)
                    $travel_voucher_sent = in_array($booking['Status'], array('PT', 'OG', 'Y', 'PR'));
                    
                    foreach ($booking['documents'] as $doc_key => $doc): 
                        // Hide custom uploads (extra documents) until travel voucher is sent
                        if (strpos($doc_key, 'cu_') === 0 && !$travel_voucher_sent) {
                            continue;
                        }
                    ?>
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
                                <?php elseif (strpos($doc_key, 'cu_') === 0): ?>
                                    <i class="la la-file-alt"></i>
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

            <!-- Customer Comments Section -->
            <div class="details-card">
                <div class="card-title">
                    Your Comments
                </div>
                <div id="customer-comments-list" class="mb-3" style="min-height: 100px;">
                    <div class="text-center text-muted py-3" style="font-size: 14px;">
                        <i class="la la-spinner la-spin"></i> Loading comments...
                    </div>
                </div>

                <?php 
                // Hide comment form after travel voucher is sent (status PT or later)
                // Status progression: P/PP -> PTV -> PT (travel voucher sent) -> OG -> Y
                $hide_comment_form = in_array($booking['Status'], array('PT', 'OG', 'Y', 'PR'));
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
                            <small>Review submitted on <?php echo date('d/m/Y h:i:s A', strtotime($booking['CustomerReviewTimestamp'])); ?></small>
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

            $(document).on('click', '.timeline-review-cta', function(e) {
                console.log('clicked');
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
                        } catch(e) {
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
                return text.replace(/[&<>"']/g, function(m) { return map[m]; });
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
                    return new Date(datetime).toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' });
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
                                    '<span class="text-muted" style="font-size: 0.75rem; color: #65676b;">' + (remark.created_at_relative || remark.created_at) + '</span>' +
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