<?php
$package = isset($package) ? $package : array();
$booking = isset($booking) ? $booking : array();
$bookings = isset($bookings) ? $bookings : array();
$package_items = isset($package_items) ? $package_items : array();
$booking_items = isset($booking_items) ? $booking_items : array();
$selected_package_item_ids = isset($selected_package_item_ids) ? $selected_package_item_ids : array();
$category_suggestions = isset($category_suggestions) ? $category_suggestions : array();
$financials = isset($financials) ? $financials : array();
$currencies = isset($currencies) ? $currencies : array();
$statuses = isset($statuses) ? $statuses : array('draft', 'active', 'inactive');
$cost_types = isset($cost_types) ? $cost_types : array();
$latest_exchange_rates = isset($latest_exchange_rates) ? $latest_exchange_rates : array();
$base_currency = isset($base_currency) ? $base_currency : 'MYR';
$package_id = isset($package['id']) ? (int) $package['id'] : 0;
$booking_id = isset($booking['id']) ? (int) $booking['id'] : 0;
$has_booking = !empty($has_booking) || $booking_id > 0;
$adult_count = isset($booking['adult_count']) ? (int) $booking['adult_count'] : 0;
$child_count = isset($booking['child_count']) ? (int) $booking['child_count'] : 0;
$total_pax = isset($booking['total_pax']) ? (int) $booking['total_pax'] : ($adult_count + $child_count);
$active_tab = isset($active_tab) ? $active_tab : 'details';
$snapshot_mode = isset($snapshot_mode) ? $snapshot_mode : ($has_booking ? 'view' : 'list');
$is_snapshot_create_mode = $active_tab === 'bookings' && $snapshot_mode === 'create';
$is_snapshot_view_mode = $active_tab === 'bookings' && $snapshot_mode === 'view' && $has_booking;
$is_snapshot_list_mode = $active_tab === 'bookings' && !$is_snapshot_create_mode && !$is_snapshot_view_mode;
$format_base_amount = function ($amount) use ($base_currency) {
    return html_escape($base_currency) . ' ' . number_format((float) $amount, 2);
};
$target_margin_per_pax = (float) $financials['price_per_pax'] - (float) $financials['cost_per_pax'];
$achieved_margin_per_pax = (float) $financials['selling_price_per_pax'] - (float) $financials['cost_per_pax'];
$achieved_margin_percentage = (float) $financials['selling_price_per_pax'] > 0
    ? ($achieved_margin_per_pax / (float) $financials['selling_price_per_pax']) * 100
    : 0;
?>

<style>
    .costing-stat-box {
        border: 1px solid #e4e6ef;
        border-radius: 6px;
        padding: 16px;
        background-color: #ffffff;
        height: 100%;
    }

    .costing-stat-label {
        font-size: 11px;
        text-transform: uppercase;
        font-weight: 600;
        color: #7e8299;
        margin-bottom: 8px;
    }

    .costing-stat-value {
        font-size: 22px;
        font-weight: 700;
        color: #3f4254;
        line-height: 1.1;
    }

    .costing-inline-form {
        display: inline-block;
        margin: 0;
    }

    .costing-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        justify-content: space-between;
        align-items: center;
    }

    .costing-toolbar-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .costing-empty-cell {
        text-align: center;
        padding-top: 14px;
        padding-bottom: 14px;
        color: #7e8299;
    }

    .costing-workspace-panel {
        border: 1px solid #e4e6ef;
        border-radius: 6px;
        background-color: #ffffff;
        padding: 24px;
        margin-bottom: 20px;
    }

    .costing-workspace-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        padding-bottom: 18px;
        margin-bottom: 20px;
        border-bottom: 1px solid #e4e6ef;
    }

    .costing-workspace-title {
        font-size: 18px;
        font-weight: 700;
        color: #3f4254;
        margin-bottom: 4px;
    }

    .costing-workspace-panel .nav-tabs {
        margin-bottom: 24px;
    }

    .costing-financial-box {
        border: 1px solid #e4e6ef;
        border-radius: 6px;
        padding: 14px 16px;
        margin-bottom: 12px;
        background-color: #ffffff;
    }

    .costing-financial-box code {
        display: block;
        margin-top: 5px;
        color: #7e8299;
        white-space: pre-wrap;
    }

    .costing-output-summary {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 18px;
    }

    .costing-output-chip {
        border: 1px solid #e4e6ef;
        border-radius: 6px;
        padding: 14px 16px;
        background-color: #f9fbfd;
    }

    .costing-output-chip-label {
        font-size: 11px;
        line-height: 1.2;
        text-transform: uppercase;
        font-weight: 700;
        color: #7e8299;
        margin-bottom: 8px;
    }

    .costing-output-chip-value {
        font-size: 22px;
        line-height: 1.15;
        font-weight: 700;
        color: #3f4254;
        overflow-wrap: anywhere;
    }

    .costing-output-table th {
        white-space: nowrap;
        font-size: 12px;
        text-transform: uppercase;
        color: #7e8299;
        background-color: #f3f6f9;
    }

    .costing-output-table td {
        vertical-align: middle;
    }

    .costing-output-table .metric-name {
        font-weight: 700;
        color: #3f4254;
    }

    .costing-output-table .metric-formula {
        display: block;
        margin-top: 3px;
        font-size: 12px;
        color: #7e8299;
        white-space: normal;
    }

    .costing-output-table .metric-value {
        display: inline-block;
        min-width: 110px;
        font-size: 16px;
        font-weight: 700;
        color: #3f4254;
        text-align: right;
    }

    .costing-snapshot-table-wrapper {
        overflow-x: auto;
        overflow-y: visible;
        -webkit-overflow-scrolling: touch;
    }

    .costing-snapshot-table {
        min-width: 1480px;
    }

    @media (max-width: 767.98px) {
        .costing-toolbar {
            align-items: flex-start;
        }

        .costing-workspace-header {
            display: block;
        }

        .costing-output-summary {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <?php if ($this->session->flashdata('message_success')) { ?>
            <div class="alert alert-success"><?php echo html_escape($this->session->flashdata('message_success')); ?></div>
        <?php } ?>
        <?php if ($this->session->flashdata('message_error')) { ?>
            <div class="alert alert-danger"><?php echo html_escape($this->session->flashdata('message_error')); ?></div>
        <?php } ?>

        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong>Costing Package Details</strong>
                    </h3>
                </div>
                <div class="card-toolbar">
                    <a href="<?php echo base_url('Costing'); ?>" class="btn btn-light-primary font-weight-bold mr-2">
                        <i class="la la-arrow-left"></i>Back To Packages
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="mb-6">
                    <div class="font-size-h4 font-weight-bold text-dark mb-2"><?php echo html_escape($package['name']); ?></div>
                    <div class="text-muted mb-4"><?php echo html_escape($package['description']); ?></div>
                    <div class="row">
                        <div class="col-md-3 mb-4">
                            <div class="costing-stat-box">
                                <div class="costing-stat-label">Duration</div>
                                <div class="costing-stat-value"><?php echo (int) $package['duration_days']; ?>D / <?php echo (int) $package['duration_nights']; ?>N</div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-4">
                            <div class="costing-stat-box">
                                <div class="costing-stat-label">Status</div>
                                <div class="costing-stat-value"><?php echo html_escape($package['status']); ?></div>
                            </div>
                        </div>
                        <!-- <div class="col-md-3 mb-4">
                            <div class="costing-stat-box">
                                <div class="costing-stat-label">Cost Templates</div>
                                <div class="costing-stat-value"><?php echo count($package_items); ?></div>
                            </div>
                        </div> -->
                        <!-- <div class="col-md-3 mb-4">
                            <div class="costing-stat-box">
                                <div class="costing-stat-label">Booking Snapshots</div>
                                <div class="costing-stat-value"><?php echo count($bookings); ?></div>
                            </div>
                        </div> -->
                    </div>
                </div>
            </div>
        </div>

        <div class="costing-workspace-panel">
            <div>
                <ul class="nav nav-tabs nav-tabs-line mb-8" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab === 'details' ? 'active' : ''; ?>" href="<?php echo base_url('Costing/Package/' . $package_id . '?tab=details'); ?>" role="tab">
                            <span class="nav-text">Package Details</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab === 'costing' ? 'active' : ''; ?>" href="<?php echo base_url('Costing/Package/' . $package_id . '?tab=costing'); ?>" role="tab">
                            <span class="nav-text">Cost Templates</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab === 'bookings' ? 'active' : ''; ?>" href="<?php echo base_url('Costing/Package/' . $package_id . '?tab=bookings'); ?>" role="tab">
                            <span class="nav-text">Booking Snapshots</span>
                        </a>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade <?php echo $active_tab === 'details' ? 'show active' : ''; ?>" id="package_details_tab" role="tabpanel">
                        <form method="post" action="<?php echo base_url('Costing/Save_Package'); ?>">
                            <input type="hidden" name="package_id" value="<?php echo $package_id; ?>">
                            <input type="hidden" name="return_to" value="workspace">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Package Name</label>
                                        <input type="text" name="name" class="form-control" value="<?php echo html_escape($package['name']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Days</label>
                                        <input type="number" min="1" name="duration_days" class="form-control" value="<?php echo (int) $package['duration_days']; ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Nights</label>
                                        <input type="number" min="0" name="duration_nights" class="form-control" value="<?php echo (int) $package['duration_nights']; ?>" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Status</label>
                                        <select name="status" class="form-control">
                                            <?php foreach ($statuses as $status) { ?>
                                                <option value="<?php echo html_escape($status); ?>" <?php echo $package['status_value'] === $status ? 'selected' : ''; ?>><?php echo html_escape(ucfirst($status)); ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label>Description</label>
                                        <textarea name="description" rows="3" class="form-control"><?php echo html_escape($package['description']); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary font-weight-bold">Save Package</button>
                        </form>
                    </div>

                    <div class="tab-pane fade <?php echo $active_tab === 'costing' ? 'show active' : ''; ?>" id="costing_breakdown_tab" role="tabpanel">
                        <div class="costing-toolbar mb-5">
                            <div>
                                <h4 class="mb-1">Cost Templates</h4>
                                <div class="text-muted">Maintain reusable package cost templates here. Existing booking snapshots keep their own copied rows.</div>
                            </div>
                            <div class="costing-toolbar-actions">
                                <button type="button" class="btn btn-primary btn-sm font-weight-bold" id="add-template-item-button">
                                    Add Cost Template
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive mb-8">
                            <table class="table table-bordered table-head-custom">
                                <thead>
                                    <tr>
                                        <th style="text-align:center;">Item</th>
                                        <th style="text-align:center;">Category</th>
                                        <th style="text-align:center;">Cost Type</th>
                                        <th style="text-align:center;">Default Price</th>
                                        <th style="text-align:center;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($package_items)) { ?>
                                        <tr>
                                            <td colspan="5" class="costing-empty-cell">Cost Templates Not Found</td>
                                        </tr>
                                    <?php } else { ?>
                                        <?php foreach ($package_items as $item) { ?>
                                            <tr>
                                                <td>
                                                    <div class="font-weight-bold"><?php echo html_escape($item['name']); ?></div>
                                                    <div class="text-muted"><?php echo html_escape($item['description']); ?></div>
                                                </td>
                                                <td style="text-align:center;"><?php echo html_escape($item['category']); ?></td>
                                                <td style="text-align:center;"><?php echo html_escape(isset($cost_types[$item['cost_type']]) ? $cost_types[$item['cost_type']] : ucfirst(str_replace('_', ' ', $item['cost_type']))); ?></td>
                                                <td style="text-align:center;"><?php echo html_escape($item['currency']); ?> <?php echo number_format((float) $item['default_unit_price'], 2); ?></td>
                                                <td style="text-align:center;">
                                                    <button
                                                        type="button"
                                                        class="btn btn-icon btn-light-primary btn-sm mr-2 edit-template-item"
                                                        data-item="<?php echo html_escape(json_encode($item)); ?>"
                                                        title="Update Cost Template"
                                                    >
                                                        <i class="la la-edit"></i>
                                                    </button>
                                                    <form method="post" action="<?php echo base_url('Costing/Delete_Package_Item'); ?>" class="costing-inline-form delete-template-item-form">
                                                        <input type="hidden" name="package_id" value="<?php echo $package_id; ?>">
                                                        <input type="hidden" name="item_id" value="<?php echo (int) $item['id']; ?>">
                                                        <button type="button" class="btn btn-icon btn-light-danger btn-sm delete-template-item-button" data-name="<?php echo html_escape($item['name']); ?>" title="Delete Cost Template">
                                                            <i class="la la-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>

                    </div>

                    <div class="tab-pane fade <?php echo $active_tab === 'bookings' ? 'show active' : ''; ?>" id="booking_snapshot_tab" role="tabpanel">
                        <div class="costing-toolbar mb-5">
                            <div>
                                <?php if ($is_snapshot_create_mode) { ?>
                                    <h4 class="mb-1">Create Snapshot</h4>
                                    <div class="text-muted">Choose Cost Templates, adjust copied values, then create an independent booking snapshot.</div>
                                <?php } elseif ($is_snapshot_view_mode) { ?>
                                    <h4 class="mb-1">Snapshot Details</h4>
                                    <div class="text-muted">Edit this snapshot copy and review the calculation output. Cost Template changes will not affect it.</div>
                                <?php } else { ?>
                                    <h4 class="mb-1">Booking Snapshots</h4>
                                    <div class="text-muted">Create and manage independent booking cost snapshots from current Cost Templates.</div>
                                <?php } ?>
                            </div>
                            <div class="costing-toolbar-actions">
                                <?php if (!$is_snapshot_list_mode) { ?>
                                    <a href="<?php echo base_url('Costing/Package/' . $package_id . '?tab=bookings'); ?>" class="btn btn-light-primary font-weight-bold">Back To Snapshot List</a>
                                <?php } ?>
                                <?php if ($is_snapshot_list_mode) { ?>
                                    <a href="<?php echo base_url('Costing/Package/' . $package_id . '?tab=bookings&mode=create_snapshot'); ?>" class="btn btn-primary font-weight-bold">Create Snapshot</a>
                                <?php } ?>
                                <?php if ($is_snapshot_view_mode) { ?>
                                    <form method="post" action="<?php echo base_url('Costing/Delete_Booking_Snapshot'); ?>" class="costing-inline-form delete-booking-form">
                                        <input type="hidden" name="package_id" value="<?php echo $package_id; ?>">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
                                        <button type="button" class="btn btn-light-danger font-weight-bold delete-booking-button" data-name="<?php echo html_escape($booking['booking_number']); ?>">Delete Snapshot</button>
                                    </form>
                                <?php } ?>
                            </div>
                        </div>

                        <?php if ($is_snapshot_list_mode) { ?>
                            <div class="table-responsive mb-8">
                                <table class="table table-bordered table-head-custom">
                                    <thead>
                                        <tr>
                                            <th style="text-align:center;">Snapshot</th>
                                            <th style="text-align:center;">Travel Date</th>
                                            <th style="text-align:center;">Total Pax</th>
                                            <th style="text-align:center;">Status</th>
                                            <th style="text-align:center;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($bookings)) { ?>
                                            <tr>
                                                <td colspan="5" class="costing-empty-cell">No booking snapshots yet. Create one from the current Cost Templates.</td>
                                            </tr>
                                        <?php } else { ?>
                                            <?php foreach ($bookings as $saved_booking) { ?>
                                                <tr>
                                                    <td class="font-weight-bold" style="text-align:center;"><?php echo html_escape($saved_booking['booking_number']); ?></td>
                                                    <td style="text-align:center;"><?php echo !empty($saved_booking['travel_date']) ? html_escape(date('d M Y', strtotime($saved_booking['travel_date']))) : '-'; ?></td>
                                                    <td style="text-align:center;"><?php echo (int) $saved_booking['total_pax']; ?></td>
                                                    <td style="text-align:center;"><?php echo html_escape($saved_booking['status_label']); ?></td>
                                                    <td style="text-align:center;">
                                                        <a href="<?php echo base_url('Costing/Package/' . $package_id . '?tab=bookings&booking_id=' . (int) $saved_booking['id']); ?>" class="btn btn-icon btn-light-primary btn-sm mr-2" title="View / Edit Snapshot">
                                                            <i class="la la-edit"></i>
                                                        </a>
                                                        <form method="post" action="<?php echo base_url('Costing/Delete_Booking_Snapshot'); ?>" class="costing-inline-form delete-booking-form">
                                                            <input type="hidden" name="package_id" value="<?php echo $package_id; ?>">
                                                            <input type="hidden" name="booking_id" value="<?php echo (int) $saved_booking['id']; ?>">
                                                            <button type="button" class="btn btn-icon btn-light-danger btn-sm delete-booking-button" data-name="<?php echo html_escape($saved_booking['booking_number']); ?>" title="Delete Snapshot">
                                                                <i class="la la-trash"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php } ?>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } ?>

                        <?php if ($is_snapshot_create_mode) { ?>
                            <form method="post" action="<?php echo base_url('Costing/Generate_Booking_Snapshot'); ?>" id="generate-booking-form">
                                <input type="hidden" name="package_id" value="<?php echo $package_id; ?>">
                                <input type="hidden" name="booking_id" value="0">
                                <input type="hidden" name="commissionable_per_pax" value="<?php echo html_escape($financials['commissionable_per_pax']); ?>">
                                <input type="hidden" name="ad_hoc_per_pax" value="<?php echo html_escape($financials['ad_hoc_per_pax']); ?>">

                                <div class="card card-custom mb-5">
                                    <div class="card-header">
                                        <div class="card-title">
                                            <h3 class="card-label">Create Snapshot</h3>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label>Travel Date</label>
                                                    <input type="date" name="travel_date" class="form-control" value="<?php echo html_escape(date('Y-m-d')); ?>" required>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label>Adults</label>
                                                    <input type="number" min="0" name="adult_count" id="snapshot_adult_count" class="form-control" value="0" required>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label>Children</label>
                                                    <input type="number" min="0" name="child_count" id="snapshot_child_count" class="form-control" value="0" required>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label>Total Pax</label>
                                                    <input type="number" min="0" id="snapshot_total_pax" class="form-control" value="0" readonly>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label>Status</label>
                                                    <select name="status" class="form-control">
                                                        <?php foreach ($statuses as $status) { ?>
                                                            <option value="<?php echo html_escape($status); ?>" <?php echo $status === 'draft' ? 'selected' : ''; ?>><?php echo html_escape(ucfirst($status)); ?></option>
                                                        <?php } ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label>Margin percentage</label>
                                                    <input type="number" step="0.01" min="0" max="99.99" class="form-control" name="margin_percentage" value="<?php echo html_escape($financials['margin_percentage']); ?>">
                                                    <span class="form-text text-muted">Selling Price (Price per Pax) = Cost / (1 - Margin)</span>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label>Manual Selling Price / Pax</label>
                                                    <input type="number" step="0.01" min="0" class="form-control" name="selling_price_per_pax" value="<?php echo html_escape($financials['selling_price_per_pax']); ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card card-custom mb-5">
                                    <div class="card-header">
                                        <div class="card-title">
                                            <h3 class="card-label">Cost Templates To Copy</h3>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive costing-snapshot-table-wrapper">
                                            <table class="table table-bordered table-head-custom costing-snapshot-table">
                                                <thead>
                                                    <tr>
                                                        <th style="text-align:center; width:70px;">Use</th>
                                                        <th style="text-align:center;">Item</th>
                                                        <th style="text-align:center;">Category</th>
                                                        <th style="text-align:center;">Pax Type</th>
                                                        <th style="text-align:center;">Qty</th>
                                                        <th style="text-align:center;">Units</th>
                                                        <th style="text-align:center;">Currency</th>
                                                        <th style="text-align:center;">Unit Price</th>
                                                        <th style="text-align:center;">Remark</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (empty($package_items)) { ?>
                                                        <tr>
                                                            <td colspan="9" class="costing-empty-cell">Add Cost Templates before creating a snapshot.</td>
                                                        </tr>
                                                    <?php } else { ?>
                                                        <?php foreach ($package_items as $index => $item) { ?>
                                                            <?php
                                                            $template_pax_type = '';
                                                            if ($item['cost_type'] === 'per_adult') {
                                                                $template_pax_type = 'adult';
                                                            } elseif ($item['cost_type'] === 'per_child') {
                                                                $template_pax_type = 'child';
                                                            }
                                                            $template_quantity = in_array($item['cost_type'], array('per_adult', 'per_child', 'per_pax'), true) ? 0 : 1;
                                                            $template_remark = trim((string) $item['description']) !== '' ? $item['description'] : 'Copied from Cost Template';
                                                            ?>
                                                            <tr class="create-snapshot-row" data-cost-type="<?php echo html_escape($item['cost_type']); ?>">
                                                                <td style="text-align:center;">
                                                                    <input type="checkbox" name="rows[<?php echo $index; ?>][include]" value="1" checked>
                                                                    <input type="hidden" name="rows[<?php echo $index; ?>][package_item_id]" value="<?php echo (int) $item['id']; ?>">
                                                                </td>
                                                                <td style="min-width:220px;"><input type="text" class="form-control" name="rows[<?php echo $index; ?>][name]" value="<?php echo html_escape($item['name']); ?>" required></td>
                                                                <td style="min-width:140px;"><input type="text" class="form-control" name="rows[<?php echo $index; ?>][category]" value="<?php echo html_escape($item['category']); ?>" required></td>
                                                                <td style="min-width:120px;">
                                                                    <select class="form-control create-row-pax-type" name="rows[<?php echo $index; ?>][pax_type]">
                                                                        <option value="" <?php echo $template_pax_type === '' ? 'selected' : ''; ?>>All</option>
                                                                        <option value="adult" <?php echo $template_pax_type === 'adult' ? 'selected' : ''; ?>>Adult</option>
                                                                        <option value="child" <?php echo $template_pax_type === 'child' ? 'selected' : ''; ?>>Child</option>
                                                                    </select>
                                                                </td>
                                                                <td><input type="number" step="0.01" min="0" class="form-control create-row-quantity" name="rows[<?php echo $index; ?>][quantity]" value="<?php echo $template_quantity; ?>"></td>
                                                                <td><input type="number" step="0.01" min="0" class="form-control" name="rows[<?php echo $index; ?>][unit_count]" value="1"></td>
                                                                <td style="min-width:120px;">
                                                                    <select class="form-control" name="rows[<?php echo $index; ?>][currency_id]">
                                                                        <?php foreach ($currencies as $currency) { ?>
                                                                            <option value="<?php echo (int) $currency['id']; ?>" <?php echo (int) $item['currency_id'] === (int) $currency['id'] ? 'selected' : ''; ?>><?php echo html_escape($currency['code']); ?></option>
                                                                        <?php } ?>
                                                                    </select>
                                                                </td>
                                                                <td><input type="number" step="0.01" min="0" class="form-control" name="rows[<?php echo $index; ?>][unit_price]" value="<?php echo html_escape($item['default_unit_price']); ?>"></td>
                                                                <td style="min-width:220px;"><input type="text" class="form-control" name="rows[<?php echo $index; ?>][remark]" value="<?php echo html_escape($template_remark); ?>"></td>
                                                            </tr>
                                                        <?php } ?>
                                                    <?php } ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <button type="submit" class="btn btn-primary font-weight-bold" <?php echo empty($package_items) ? 'disabled' : ''; ?>>Create Snapshot</button>
                                    </div>
                                </div>
                            </form>
                        <?php } ?>

                        <?php if ($is_snapshot_view_mode) { ?>
                        <form method="post" action="<?php echo base_url('Costing/Save_Booking_Snapshot'); ?>" id="booking-snapshot-form">
                            <input type="hidden" name="package_id" value="<?php echo $package_id; ?>">
                            <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">

                            <div class="row">
                                <div class="col-12">
                                    <div class="card card-custom mb-5">
                                        <div class="card-header">
                                            <div class="card-title">
                                                <h3 class="card-label">Snapshot Details</h3>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="row mb-5">
                                                <div class="col-md-3">
                                                    <div class="form-group">
                                                        <label>Travel Date</label>
                                                        <input type="date" name="travel_date" class="form-control" value="<?php echo html_escape($booking['travel_date']); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <div class="form-group">
                                                        <label>Adults</label>
                                                        <input type="number" min="0" name="adult_count" class="form-control pax-input" value="<?php echo $adult_count; ?>" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <div class="form-group">
                                                        <label>Children</label>
                                                        <input type="number" min="0" name="child_count" class="form-control pax-input" value="<?php echo $child_count; ?>" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <div class="form-group">
                                                        <label>Total Pax</label>
                                                        <input type="number" min="0" id="persisted_total_pax" class="form-control" value="<?php echo $total_pax; ?>" readonly>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group">
                                                        <label>Status</label>
                                                        <select name="status" class="form-control">
                                                            <?php foreach ($statuses as $status) { ?>
                                                                <option value="<?php echo html_escape($status); ?>" <?php echo $booking['status_value'] === $status ? 'selected' : ''; ?>><?php echo html_escape(ucfirst($status)); ?></option>
                                                            <?php } ?>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <input type="hidden" name="commissionable_per_pax" id="commissionable_per_pax" value="<?php echo html_escape($financials['commissionable_per_pax']); ?>">
                                            <input type="hidden" name="ad_hoc_per_pax" id="ad_hoc_per_pax" value="<?php echo html_escape($financials['ad_hoc_per_pax']); ?>">

                                            <div class="row mb-5">
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label>Margin percentage</label>
                                                        <input type="number" step="0.01" min="0" max="99.99" class="form-control financial-input" name="margin_percentage" id="margin_percentage" value="<?php echo html_escape($financials['margin_percentage']); ?>">
                                                        <span class="form-text text-muted">Selling Price (Price per Pax) = Cost / (1 - Margin)</span>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label>Manual Selling Price / Pax (MYR)</label>
                                                        <input type="number" step="0.01" min="0" class="form-control financial-input" name="selling_price_per_pax" id="selling_price_per_pax" value="<?php echo html_escape($financials['selling_price_per_pax']); ?>">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="table-responsive costing-snapshot-table-wrapper">
                                                <table class="table table-bordered table-head-custom costing-snapshot-table" id="costing-items-table">
                                                    <thead>
                                                        <tr>
                                                            <th style="text-align:center;">Item</th>
                                                            <th style="text-align:center;">Category</th>
                                                            <th style="text-align:center;">Pax Type</th>
                                                            <th style="text-align:center;">Qty</th>
                                                            <th style="text-align:center;">Units</th>
                                                            <th style="text-align:center;">Currency</th>
                                                            <th style="text-align:center;">Rate / Bank Charges</th>
                                                            <th style="text-align:center;">Unit Price</th>
                                                            <th style="text-align:center;">Currency Total</th>
                                                            <th style="text-align:center;"><?php echo html_escape($base_currency); ?> Total</th>
                                                            <th style="text-align:center;">Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if (empty($booking_items)) { ?>
                                                            <tr class="empty-booking-state">
                                                                <td colspan="11" class="costing-empty-cell">No booking snapshot rows found. Select costing rows first, then generate the snapshot.</td>
                                                            </tr>
                                                        <?php } ?>

                                                        <?php foreach ($booking_items as $index => $item) { ?>
                                                            <tr class="costing-item-row">
                                                                <td style="min-width:220px;">
                                                                    <input type="hidden" name="rows[<?php echo $index; ?>][package_item_id]" value="<?php echo (int) $item['package_item_id']; ?>">
                                                                    <input type="text" class="form-control row-name mb-2" name="rows[<?php echo $index; ?>][name]" value="<?php echo html_escape($item['name']); ?>" required>
                                                                    <input type="text" class="form-control row-remark" name="rows[<?php echo $index; ?>][remark]" value="<?php echo html_escape($item['remark']); ?>" placeholder="Remark">
                                                                </td>
                                                                <td style="min-width:140px;">
                                                                    <input type="text" class="form-control row-category" name="rows[<?php echo $index; ?>][category]" value="<?php echo html_escape($item['category']); ?>" required>
                                                                </td>
                                                                <td style="min-width:120px;">
                                                                    <select class="form-control row-pax-type" name="rows[<?php echo $index; ?>][pax_type]">
                                                                        <option value="" <?php echo $item['pax_type'] === '' ? 'selected' : ''; ?>>All</option>
                                                                        <option value="adult" <?php echo $item['pax_type'] === 'adult' ? 'selected' : ''; ?>>Adult</option>
                                                                        <option value="child" <?php echo $item['pax_type'] === 'child' ? 'selected' : ''; ?>>Child</option>
                                                                    </select>
                                                                </td>
                                                                <td><input type="number" step="0.01" min="0" class="form-control row-quantity" name="rows[<?php echo $index; ?>][quantity]" value="<?php echo html_escape($item['quantity']); ?>"></td>
                                                                <td><input type="number" step="0.01" min="0" class="form-control row-unit-count" name="rows[<?php echo $index; ?>][unit_count]" value="<?php echo html_escape($item['unit_count']); ?>"></td>
                                                                <td style="min-width:120px;">
                                                                    <select class="form-control row-currency-id" name="rows[<?php echo $index; ?>][currency_id]">
                                                                        <?php foreach ($currencies as $currency) { ?>
                                                                            <option value="<?php echo (int) $currency['id']; ?>" data-code="<?php echo html_escape($currency['code']); ?>" <?php echo (int) $item['currency_id'] === (int) $currency['id'] ? 'selected' : ''; ?>>
                                                                                <?php echo html_escape($currency['code']); ?>
                                                                            </option>
                                                                        <?php } ?>
                                                                    </select>
                                                                </td>
                                                                <td style="text-align:center;">
                                                                    <span class="font-weight-bold row-exchange-rate"><?php echo number_format((float) $item['exchange_rate'], 4, '.', ''); ?></span>
                                                                    <div class="mt-2" style="min-width:120px;">
                                                                        <input type="number" step="0.01" min="0" class="form-control row-bank-charges-myr" name="rows[<?php echo $index; ?>][bank_charges_myr]" value="<?php echo number_format((float) (isset($item['bank_charges_myr']) ? $item['bank_charges_myr'] : 0), 2, '.', ''); ?>">
                                                                    </div>
                                                                </td>
                                                                <td><input type="number" step="0.01" min="0" class="form-control row-unit-price" name="rows[<?php echo $index; ?>][unit_price]" value="<?php echo html_escape($item['unit_price']); ?>"></td>
                                                                <td style="text-align:center;">
                                                                    <span class="row-total-currency"><?php echo html_escape($item['currency']); ?></span>
                                                                    <span class="font-weight-bold row-total-amount"><?php echo number_format((float) $item['total_amount'], 2); ?></span>
                                                                </td>
                                                                <td style="text-align:center;"><span class="font-weight-bold row-base-total"><?php echo number_format((float) $item['base_total'], 2); ?></span></td>
                                                                <td style="text-align:center;"><button type="button" class="btn btn-light-danger btn-sm remove-row">Remove</button></td>
                                                            </tr>
                                                        <?php } ?>
                                                    </tbody>
                                                </table>
                                            </div>

                                            <button type="button" class="btn btn-light-primary font-weight-bold mr-2" id="add-costing-row">Add Row</button>
                                            <button type="submit" class="btn btn-primary font-weight-bold" <?php echo $has_booking ? '' : 'disabled'; ?>>Save Snapshot</button>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="card card-custom mb-5">
                                        <div class="card-header">
                                            <div class="card-title">
                                                <h3 class="card-label">Calculation Output</h3>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="costing-output-summary">
                                                <div class="costing-output-chip">
                                                    <div class="costing-output-chip-label">Total Cost (<?php echo html_escape($base_currency); ?>)</div>
                                                    <div class="costing-output-chip-value financial-total-cost-output" id="financial-total-cost"><?php echo $format_base_amount($financials['total_cost']); ?></div>
                                                </div>
                                                <div class="costing-output-chip">
                                                    <div class="costing-output-chip-label">Total Revenue (<?php echo html_escape($base_currency); ?>)</div>
                                                    <div class="costing-output-chip-value financial-total-revenue-output" id="financial-total-revenue"><?php echo $format_base_amount($financials['total_revenue']); ?></div>
                                                </div>
                                                <div class="costing-output-chip">
                                                    <div class="costing-output-chip-label">Total Profit (<?php echo html_escape($base_currency); ?>)</div>
                                                    <div class="costing-output-chip-value financial-total-profit-output" id="summary-total-profit"><?php echo $format_base_amount($financials['total_profit']); ?></div>
                                                </div>
                                            </div>

                                            <div class="table-responsive">
                                                <table class="table table-bordered costing-output-table mb-4">
                                                    <thead>
                                                        <tr>
                                                            <th>Metric</th>
                                                            <th style="text-align:right;">Per Pax (<?php echo html_escape($base_currency); ?>)</th>
                                                            <th style="text-align:right;">Total (<?php echo html_escape($base_currency); ?>)</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td>
                                                                <span class="metric-name">Cost</span>
                                                                <span class="metric-formula">Total: Sum of all snapshot rows converted to <?php echo html_escape($base_currency); ?></span>
                                                                <span class="metric-formula">Per pax: Total cost / total pax</span>
                                                            </td>
                                                            <td style="text-align:right;"><span class="metric-value financial-cost-per-pax-output" id="financial-cost-per-pax"><?php echo $format_base_amount($financials['cost_per_pax']); ?></span></td>
                                                            <td style="text-align:right;"><span class="metric-value financial-total-cost-output"><?php echo $format_base_amount($financials['total_cost']); ?></span></td>
                                                        </tr>
                                                        <tr>
                                                            <td>
                                                                <span class="metric-name">Target margin amount <span class="financial-margin-percentage-label">(<?php echo number_format((float) $financials['margin_percentage'], 2); ?>%)</span></span>
                                                                <span class="metric-formula">Per pax: Calculated selling price per pax - cost per pax</span>
                                                                <span class="metric-formula">Total: Calculated selling total - total cost</span>
                                                            </td>
                                                            <td style="text-align:right;"><span class="metric-value financial-markup-per-pax-output"><?php echo $format_base_amount($target_margin_per_pax); ?></span></td>
                                                            <td style="text-align:right;"><span class="metric-value financial-markup-total-output" id="financial-markup-total"><?php echo $format_base_amount($financials['markup_amount_total']); ?></span></td>
                                                        </tr>
                                                        <tr>
                                                            <td>
                                                                <span class="metric-name">Calculated Selling Price</span>
                                                                <span class="metric-formula">Per pax: Cost per pax / (1 - margin percentage)</span>
                                                                <span class="metric-formula">Total: Calculated selling price per pax x total pax</span>
                                                            </td>
                                                            <td style="text-align:right;"><span class="metric-value financial-price-per-pax-output" id="financial-price-per-pax"><?php echo $format_base_amount($financials['price_per_pax']); ?></span></td>
                                                            <td style="text-align:right;"><span class="metric-value financial-calculated-selling-total-output"><?php echo $format_base_amount((float) $financials['price_per_pax'] * max(1, $total_pax)); ?></span></td>
                                                        </tr>
                                                        <tr>
                                                            <td>
                                                                <span class="metric-name">Revenue</span>
                                                                <span class="metric-formula">Per pax: Manual selling price, or calculated selling price by default</span>
                                                                <span class="metric-formula">Total: Revenue per pax x total pax</span>
                                                            </td>
                                                            <td style="text-align:right;"><span class="metric-value financial-selling-per-pax-output" id="summary-selling-per-pax"><?php echo $format_base_amount($financials['selling_price_per_pax']); ?></span></td>
                                                            <td style="text-align:right;"><span class="metric-value financial-total-revenue-output"><?php echo $format_base_amount($financials['total_revenue']); ?></span></td>
                                                        </tr>
                                                        <tr>
                                                            <td>
                                                                <span class="metric-name">Achieved margin amount <span class="financial-achieved-margin-percentage-label">(<?php echo number_format($achieved_margin_percentage, 2); ?>%)</span></span>
                                                                <span class="metric-formula">Per pax: Entered selling price per pax - cost per pax</span>
                                                                <span class="metric-formula">Total: Total revenue - total cost</span>
                                                            </td>
                                                            <td style="text-align:right;"><span class="metric-value financial-achieved-margin-per-pax-output"><?php echo $format_base_amount($achieved_margin_per_pax); ?></span></td>
                                                            <td style="text-align:right;"><span class="metric-value financial-achieved-margin-total-output"><?php echo $format_base_amount($financials['total_profit']); ?></span></td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="template_item_modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <form method="post" action="<?php echo base_url('Costing/Save_Package_Item'); ?>" id="template-item-form">
                <input type="hidden" name="package_id" value="<?php echo $package_id; ?>">
                <input type="hidden" name="item_id" id="template_item_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="template-item-form-title">Add Cost Template</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <i aria-hidden="true" class="ki ki-close"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-5">
                            <div class="form-group">
                                <label>Item Name</label>
                                <input type="text" name="name" id="template_item_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Category</label>
                                <input type="text" name="category" id="template_item_category" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Cost Type</label>
                                <select name="cost_type" id="template_item_cost_type" class="form-control">
                                    <?php foreach ($cost_types as $value => $label) { ?>
                                        <option value="<?php echo html_escape($value); ?>"><?php echo html_escape($label); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Currency</label>
                                <select name="currency_id" id="template_item_currency_id" class="form-control">
                                    <?php foreach ($currencies as $currency) { ?>
                                        <option value="<?php echo (int) $currency['id']; ?>"><?php echo html_escape($currency['code']); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Default Unit Price</label>
                                <input type="number" step="0.01" min="0" name="default_unit_price" id="template_item_default_unit_price" class="form-control" value="0" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group mb-0">
                                <label>Description</label>
                                <input type="text" name="description" id="template_item_description" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light-primary font-weight-bold" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-light-primary font-weight-bold" id="reset-template-item-form">Reset</button>
                    <button type="submit" class="btn btn-primary font-weight-bold" id="template-item-submit-button">Save Cost Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function () {
        var currencies = <?php echo json_encode($currencies); ?>;
        var latestExchangeRates = <?php echo json_encode($latest_exchange_rates); ?>;
        var baseCurrencyCode = <?php echo json_encode($base_currency); ?>;
        var adultField = document.getElementById('snapshot_adult_count');
        var childField = document.getElementById('snapshot_child_count');
        var totalField = document.getElementById('snapshot_total_pax');
        var persistedTotalField = document.getElementById('persisted_total_pax');
        var tableBody = document.querySelector('#costing-items-table tbody');
        var addButton = document.getElementById('add-costing-row');
        var marginField = document.getElementById('margin_percentage');
        var commissionableField = document.getElementById('commissionable_per_pax');
        var adHocField = document.getElementById('ad_hoc_per_pax');
        var sellingPriceField = document.getElementById('selling_price_per_pax');
        var generateForm = document.getElementById('generate-booking-form');
        var addTemplateItemButton = document.getElementById('add-template-item-button');
        var templateEditButtons = document.querySelectorAll('.edit-template-item');
        var resetTemplateItemButton = document.getElementById('reset-template-item-form');

        var baseCurrencyId = 0;
        var exchangeRateMap = {};
        var initialCalculatedPricePerPax = <?php echo json_encode((float) $financials['price_per_pax']); ?>;
        var initialSellingPricePerPax = <?php echo json_encode((float) $financials['selling_price_per_pax']); ?>;
        var sellingPriceManuallyEdited = initialSellingPricePerPax > 0 && Math.abs(initialSellingPricePerPax - initialCalculatedPricePerPax) > 0.009;

        currencies.forEach(function (currency) {
            if (currency.code === baseCurrencyCode) {
                baseCurrencyId = parseInt(currency.id, 10) || 0;
            }
        });

        latestExchangeRates.forEach(function (rateRow) {
            exchangeRateMap[rateRow.from_currency_id + ':' + rateRow.to_currency_id] = {
                rate: rateRow.rate,
                bank_charges_myr: rateRow.bank_charges_myr || 0
            };
        });

        function asNumber(value) {
            var parsed = parseFloat(value);
            return isNaN(parsed) ? 0 : parsed;
        }

        function formatAmount(value) {
            return asNumber(value).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function formatBaseAmount(value) {
            return baseCurrencyCode + ' ' + formatAmount(value);
        }

        function updateOutput(selector, value) {
            document.querySelectorAll(selector).forEach(function (element) {
                element.textContent = formatBaseAmount(value);
            });
        }

        function updatePercentOutput(selector, value) {
            document.querySelectorAll(selector).forEach(function (element) {
                element.textContent = '(' + asNumber(value).toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }) + '%)';
            });
        }

        function confirmForm(button, title, text) {
            var form = button.closest('form');
            if (!form) {
                return;
            }

            if (typeof Swal === 'undefined') {
                if (confirm(title)) {
                    form.submit();
                }
                return;
            }

            var swalWithBootstrapButtons = Swal.mixin({
                customClass: {
                    confirmButton: 'btn btn-danger',
                    cancelButton: 'btn btn-light-primary'
                },
                buttonsStyling: false
            });

            swalWithBootstrapButtons.fire({
                title: title,
                text: text,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, Delete',
                cancelButtonText: 'Cancel',
                reverseButtons: true
            }).then(function (result) {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        }

        function syncGenerateFinancials() {
            if (!generateForm) {
                return;
            }

            var marginTarget = generateForm.querySelector('input[name="margin_percentage"]');
            var commissionableTarget = generateForm.querySelector('input[name="commissionable_per_pax"]');
            var adHocTarget = generateForm.querySelector('input[name="ad_hoc_per_pax"]');
            var sellingPriceTarget = generateForm.querySelector('input[name="selling_price_per_pax"]');

            if (marginTarget && marginField) {
                marginTarget.value = marginField.value;
            }
            if (commissionableTarget && commissionableField) {
                commissionableTarget.value = commissionableField.value;
            }
            if (adHocTarget && adHocField) {
                adHocTarget.value = adHocField.value;
            }
            if (sellingPriceTarget && sellingPriceField) {
                sellingPriceTarget.value = sellingPriceField.value;
            }
        }

        function updatePaxTotals() {
            var total = asNumber(adultField ? adultField.value : 0) + asNumber(childField ? childField.value : 0);
            if (totalField) {
                totalField.value = total;
            }
            updateCreateSnapshotQuantities();
        }

        function updateCreateSnapshotQuantities() {
            var adults = asNumber(adultField ? adultField.value : 0);
            var children = asNumber(childField ? childField.value : 0);
            var total = adults + children;

            document.querySelectorAll('.create-snapshot-row').forEach(function (row) {
                var quantityField = row.querySelector('.create-row-quantity');
                if (!quantityField || quantityField.dataset.manual === '1') {
                    return;
                }

                var costType = row.getAttribute('data-cost-type') || '';
                var paxTypeField = row.querySelector('.create-row-pax-type');
                var paxType = paxTypeField ? paxTypeField.value : '';

                if (costType === 'per_adult' || paxType === 'adult') {
                    quantityField.value = adults;
                } else if (costType === 'per_child' || paxType === 'child') {
                    quantityField.value = children;
                } else if (costType === 'per_pax') {
                    quantityField.value = total;
                }
            });
        }

        function updatePersistedPaxTotals() {
            var paxInputs = document.querySelectorAll('.pax-input');
            var total = 0;
            paxInputs.forEach(function (field) {
                total += asNumber(field.value);
            });
            if (persistedTotalField) {
                persistedTotalField.value = total;
            }
            return total;
        }

        function resolveExchangeRate(row) {
            var currencyField = row.querySelector('.row-currency-id');
            var rateDisplay = row.querySelector('.row-exchange-rate');
            var bankChargesField = row.querySelector('.row-bank-charges-myr');
            if (!currencyField || !rateDisplay) {
                return { rate: 0, bankChargesMyr: 0 };
            }

            var selectedOption = currencyField.options[currencyField.selectedIndex];
            var selectedCode = selectedOption ? selectedOption.getAttribute('data-code') : '';
            var selectedCurrencyId = parseInt(currencyField.value, 10) || 0;
            var totalCurrencyLabel = row.querySelector('.row-total-currency');
            if (totalCurrencyLabel) {
                totalCurrencyLabel.textContent = selectedCode;
            }

            if (selectedCode === baseCurrencyCode) {
                rateDisplay.textContent = '1.0000';
                if (bankChargesField && currencyField.dataset.lastCurrencyId !== String(selectedCurrencyId)) {
                    bankChargesField.value = '0.00';
                }
                currencyField.dataset.lastCurrencyId = String(selectedCurrencyId);
                return { rate: 1, bankChargesMyr: asNumber(bankChargesField ? bankChargesField.value : 0) };
            }

            var mappedRate = exchangeRateMap[selectedCurrencyId + ':' + baseCurrencyId] || null;
            if (mappedRate && mappedRate.rate) {
                rateDisplay.textContent = parseFloat(mappedRate.rate).toFixed(4);
                if (bankChargesField && currencyField.dataset.lastCurrencyId !== String(selectedCurrencyId)) {
                    bankChargesField.value = asNumber(mappedRate.bank_charges_myr).toFixed(2);
                }
                currencyField.dataset.lastCurrencyId = String(selectedCurrencyId);
                return {
                    rate: asNumber(mappedRate.rate),
                    bankChargesMyr: asNumber(bankChargesField ? bankChargesField.value : mappedRate.bank_charges_myr)
                };
            }

            rateDisplay.textContent = '0.0000';
            if (bankChargesField && currencyField.dataset.lastCurrencyId !== String(selectedCurrencyId)) {
                bankChargesField.value = '0.00';
            }
            currencyField.dataset.lastCurrencyId = String(selectedCurrencyId);
            return { rate: 0, bankChargesMyr: asNumber(bankChargesField ? bankChargesField.value : 0) };
        }

        function updateRow(row) {
            var quantity = asNumber(row.querySelector('.row-quantity').value);
            var unitCount = asNumber(row.querySelector('.row-unit-count').value);
            var unitPrice = asNumber(row.querySelector('.row-unit-price').value);
            var exchangeRate = resolveExchangeRate(row);
            var totalAmount = quantity * unitCount * unitPrice;
            var baseTotal = (totalAmount * exchangeRate.rate) + exchangeRate.bankChargesMyr;

            row.querySelector('.row-total-amount').textContent = formatAmount(totalAmount);
            row.querySelector('.row-base-total').textContent = formatAmount(baseTotal);
            row.dataset.baseTotal = baseTotal.toFixed(2);
        }

        function recalculateFinancials() {
            if (!tableBody) {
                return;
            }

            var totalPax = updatePersistedPaxTotals();
            var totalCost = 0;

            tableBody.querySelectorAll('.costing-item-row').forEach(function (row) {
                updateRow(row);
                totalCost += asNumber(row.dataset.baseTotal);
            });

            var marginPercentage = marginField ? Math.min(99.99, Math.max(0, asNumber(marginField.value))) : 0;

            var costPerPax = totalPax > 0 ? totalCost / totalPax : 0;
            var marginRate = marginPercentage / 100;
            var pricePerPax = totalPax > 0 && marginRate < 1 ? costPerPax / (1 - marginRate) : 0;
            var calculatedSellingTotal = pricePerPax * totalPax;
            var markupAmountTotal = calculatedSellingTotal - totalCost;
            var markupAmountPerPax = pricePerPax - costPerPax;
            var enteredSellingPrice = sellingPriceField ? asNumber(sellingPriceField.value) : 0;
            var sellingPricePerPax = sellingPriceManuallyEdited && enteredSellingPrice > 0 ? enteredSellingPrice : pricePerPax;
            var totalRevenue = sellingPricePerPax * totalPax;
            var grossProfit = totalRevenue - totalCost;
            var profitPerPax = sellingPricePerPax - costPerPax;
            var achievedMarginPercentage = sellingPricePerPax > 0 ? (profitPerPax / sellingPricePerPax) * 100 : 0;

            if (sellingPriceField && (!sellingPriceManuallyEdited || enteredSellingPrice <= 0)) {
                sellingPriceField.value = sellingPricePerPax.toFixed(2);
            }

            updatePercentOutput('.financial-margin-percentage-label', marginPercentage);
            updatePercentOutput('.financial-achieved-margin-percentage-label', achievedMarginPercentage);
            updateOutput('.financial-selling-per-pax-output', sellingPricePerPax);
            updateOutput('.financial-total-profit-output', grossProfit);
            updateOutput('.financial-total-cost-output', totalCost);
            updateOutput('.financial-cost-per-pax-output', costPerPax);
            updateOutput('.financial-markup-per-pax-output', markupAmountPerPax);
            updateOutput('.financial-markup-total-output', markupAmountTotal);
            updateOutput('.financial-price-per-pax-output', pricePerPax);
            updateOutput('.financial-calculated-selling-total-output', calculatedSellingTotal);
            updateOutput('.financial-total-revenue-output', totalRevenue);
            updateOutput('.financial-achieved-margin-per-pax-output', profitPerPax);
            updateOutput('.financial-achieved-margin-total-output', grossProfit);
            syncGenerateFinancials();
        }

        function bindRow(row) {
            var currencyField = row.querySelector('.row-currency-id');
            if (currencyField) {
                currencyField.dataset.lastCurrencyId = currencyField.value;
            }

            row.querySelectorAll('input, select').forEach(function (field) {
                field.addEventListener('input', recalculateFinancials);
                field.addEventListener('change', recalculateFinancials);
            });

            var removeButton = row.querySelector('.remove-row');
            if (removeButton) {
                removeButton.addEventListener('click', function () {
                    row.remove();
                    recalculateFinancials();
                });
            }
        }

        function resetTemplateItemForm() {
            document.getElementById('template_item_id').value = '';
            document.getElementById('template_item_name').value = '';
            document.getElementById('template_item_category').value = '';
            document.getElementById('template_item_description').value = '';
            document.getElementById('template_item_default_unit_price').value = '0';
            document.getElementById('template_item_cost_type').selectedIndex = 0;
            document.getElementById('template_item_currency_id').value = baseCurrencyId || '';
            document.getElementById('template-item-form-title').textContent = 'Add Cost Template';
            document.getElementById('template-item-submit-button').textContent = 'Save Cost Template';
        }

        if (addTemplateItemButton) {
            addTemplateItemButton.addEventListener('click', function () {
                resetTemplateItemForm();
                $('#template_item_modal').modal('show');
            });
        }

        document.querySelectorAll('.delete-template-item-button').forEach(function (button) {
            button.addEventListener('click', function () {
                confirmForm(button, 'Delete Cost Template: ' + (button.getAttribute('data-name') || '' ) + '?', 'This reusable template row will be removed from the package. Existing snapshots will not be changed.');
            });
        });

        document.querySelectorAll('.delete-booking-button').forEach(function (button) {
            button.addEventListener('click', function () {
                confirmForm(button, 'Delete Snapshot: ' + (button.getAttribute('data-name') || '' ) + '?', 'This booking snapshot and its costing calculation will be deleted.');
            });
        });

        if (adultField) {
            adultField.addEventListener('input', updatePaxTotals);
            adultField.addEventListener('change', updatePaxTotals);
        }

        if (childField) {
            childField.addEventListener('input', updatePaxTotals);
            childField.addEventListener('change', updatePaxTotals);
        }

        document.querySelectorAll('.create-row-quantity').forEach(function (field) {
            field.addEventListener('input', function () {
                field.dataset.manual = '1';
            });
        });

        document.querySelectorAll('.create-row-pax-type').forEach(function (field) {
            field.addEventListener('change', function () {
                var row = field.closest('.create-snapshot-row');
                var quantityField = row ? row.querySelector('.create-row-quantity') : null;
                if (quantityField) {
                    quantityField.dataset.manual = '';
                }
                updateCreateSnapshotQuantities();
            });
        });

        document.querySelectorAll('.costing-item-row').forEach(bindRow);
        if (sellingPriceField) {
            sellingPriceField.addEventListener('input', function () {
                sellingPriceManuallyEdited = asNumber(sellingPriceField.value) > 0;
            });
            sellingPriceField.addEventListener('change', function () {
                sellingPriceManuallyEdited = asNumber(sellingPriceField.value) > 0;
            });
        }
        document.querySelectorAll('.financial-input, .pax-input').forEach(function (field) {
            field.addEventListener('input', recalculateFinancials);
            field.addEventListener('change', recalculateFinancials);
        });

        document.querySelectorAll('.financial-input').forEach(function (field) {
            field.addEventListener('input', syncGenerateFinancials);
            field.addEventListener('change', syncGenerateFinancials);
        });

        if (addButton) {
            addButton.addEventListener('click', function () {
                var emptyState = tableBody.querySelector('.empty-booking-state');
                if (emptyState) {
                    emptyState.remove();
                }

                var index = tableBody.querySelectorAll('.costing-item-row').length;
                var currencyOptions = currencies.map(function (currency) {
                    return '<option value="' + currency.id + '" data-code="' + currency.code + '"' + (currency.code === baseCurrencyCode ? ' selected' : '') + '>' + currency.code + '</option>';
                }).join('');

                var tr = document.createElement('tr');
                tr.className = 'costing-item-row';
                tr.innerHTML = ''
                    + '<td style="min-width:220px;">'
                    + '<input type="hidden" name="rows[' + index + '][package_item_id]" value="">'
                    + '<input type="text" class="form-control row-name mb-2" name="rows[' + index + '][name]" value="New Cost Item" required>'
                    + '<input type="text" class="form-control row-remark" name="rows[' + index + '][remark]" value="" placeholder="Remark">'
                    + '</td>'
                    + '<td style="min-width:140px;"><input type="text" class="form-control row-category" name="rows[' + index + '][category]" value="Misc" required></td>'
                    + '<td style="min-width:120px;"><select class="form-control row-pax-type" name="rows[' + index + '][pax_type]"><option value="" selected>All</option><option value="adult">Adult</option><option value="child">Child</option></select></td>'
                    + '<td><input type="number" step="0.01" min="0" class="form-control row-quantity" name="rows[' + index + '][quantity]" value="1"></td>'
                    + '<td><input type="number" step="0.01" min="0" class="form-control row-unit-count" name="rows[' + index + '][unit_count]" value="1"></td>'
                    + '<td style="min-width:120px;"><select class="form-control row-currency-id" name="rows[' + index + '][currency_id]">' + currencyOptions + '</select></td>'
                    + '<td style="text-align:center;"><span class="font-weight-bold row-exchange-rate">1.0000</span><div class="mt-2" style="min-width:120px;"><input type="number" step="0.01" min="0" class="form-control row-bank-charges-myr" name="rows[' + index + '][bank_charges_myr]" value="0.00"></div></td>'
                    + '<td><input type="number" step="0.01" min="0" class="form-control row-unit-price" name="rows[' + index + '][unit_price]" value="0"></td>'
                    + '<td style="text-align:center;"><span class="row-total-currency">' + baseCurrencyCode + '</span> <span class="font-weight-bold row-total-amount">0.00</span></td>'
                    + '<td style="text-align:center;"><span class="font-weight-bold row-base-total">0.00</span></td>'
                    + '<td style="text-align:center;"><button type="button" class="btn btn-light-danger btn-sm remove-row">Remove</button></td>';

                tableBody.appendChild(tr);
                bindRow(tr);
                recalculateFinancials();
            });
        }

        templateEditButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                var item = JSON.parse(button.getAttribute('data-item'));
                document.getElementById('template_item_id').value = item.id || '';
                document.getElementById('template_item_name').value = item.name || '';
                document.getElementById('template_item_category').value = item.category || '';
                document.getElementById('template_item_description').value = item.description || '';
                document.getElementById('template_item_default_unit_price').value = item.default_unit_price || '0';
                document.getElementById('template_item_cost_type').value = item.cost_type || 'fixed';
                document.getElementById('template_item_currency_id').value = item.currency_id || '';
                document.getElementById('template-item-form-title').textContent = 'Update Cost Template';
                document.getElementById('template-item-submit-button').textContent = 'Save Cost Template';
                $('#template_item_modal').modal('show');
            });
        });

        if (resetTemplateItemButton) {
            resetTemplateItemButton.addEventListener('click', resetTemplateItemForm);
        }

        $('#template_item_modal').on('hidden.bs.modal', function () {
            resetTemplateItemForm();
        });

        updatePaxTotals();
        syncGenerateFinancials();
        recalculateFinancials();
    })();
</script>
