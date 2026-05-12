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
$has_booking = !empty($has_booking);
$package_id = isset($package['id']) ? (int) $package['id'] : 0;
$booking_id = isset($booking['id']) ? (int) $booking['id'] : 0;
$adult_count = isset($booking['adult_count']) ? (int) $booking['adult_count'] : 0;
$child_count = isset($booking['child_count']) ? (int) $booking['child_count'] : 0;
$total_pax = isset($booking['total_pax']) ? (int) $booking['total_pax'] : ($adult_count + $child_count);
$active_tab = isset($active_tab) ? $active_tab : 'details';
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

        <datalist id="costing-category-suggestions">
            <?php foreach ($category_suggestions as $category_suggestion) { ?>
                <option value="<?php echo html_escape($category_suggestion); ?>"></option>
            <?php } ?>
        </datalist>

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
                    <a href="<?php echo base_url('Costing/Currency'); ?>" class="btn btn-light-primary font-weight-bold">
                        <i class="la la-coins"></i>Currency Setup
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
                        <div class="col-md-3 mb-4">
                            <div class="costing-stat-box">
                                <div class="costing-stat-label">Costing Rows</div>
                                <div class="costing-stat-value"><?php echo count($package_items); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-4">
                            <div class="costing-stat-box">
                                <div class="costing-stat-label">Booking Snapshots</div>
                                <div class="costing-stat-value"><?php echo count($bookings); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <ul class="nav nav-tabs nav-tabs-line mb-8" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab === 'details' ? 'active' : ''; ?>" data-toggle="tab" href="#package_details_tab" role="tab">
                            <span class="nav-text">Package Details</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab === 'costing' ? 'active' : ''; ?>" data-toggle="tab" href="#costing_breakdown_tab" role="tab">
                            <span class="nav-text">Costing Breakdown</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab === 'bookings' ? 'active' : ''; ?>" data-toggle="tab" href="#booking_snapshot_tab" role="tab">
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
                                <h4 class="mb-1">Costing Breakdown</h4>
                                <div class="text-muted">Maintain the package costing rows here. These rows become the source for booking snapshot generation.</div>
                            </div>
                            <div class="costing-toolbar-actions">
                                <button type="button" class="btn btn-light-primary btn-sm font-weight-bold" id="select-all-template-items">Select All</button>
                                <button type="button" class="btn btn-light-primary btn-sm font-weight-bold" id="clear-template-items">Clear</button>
                            </div>
                        </div>

                        <div class="table-responsive mb-8">
                            <table class="table table-bordered table-head-custom">
                                <thead>
                                    <tr>
                                        <th style="text-align:center;">Use</th>
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
                                            <td colspan="6" class="costing-empty-cell">Costing Rows Not Found</td>
                                        </tr>
                                    <?php } else { ?>
                                        <?php foreach ($package_items as $item) { ?>
                                            <tr>
                                                <td style="text-align:center;">
                                                    <input type="checkbox" class="snapshot-source" value="<?php echo (int) $item['id']; ?>" <?php echo in_array((int) $item['id'], $selected_package_item_ids, true) ? 'checked' : ''; ?>>
                                                </td>
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
                                                        title="Update Costing Row"
                                                    >
                                                        <i class="la la-edit"></i>
                                                    </button>
                                                    <form method="post" action="<?php echo base_url('Costing/Delete_Package_Item'); ?>" class="costing-inline-form delete-template-item-form">
                                                        <input type="hidden" name="package_id" value="<?php echo $package_id; ?>">
                                                        <input type="hidden" name="item_id" value="<?php echo (int) $item['id']; ?>">
                                                        <button type="button" class="btn btn-icon btn-light-danger btn-sm delete-template-item-button" data-name="<?php echo html_escape($item['name']); ?>" title="Delete Costing Row">
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

                        <div class="separator separator-dashed my-8"></div>

                        <div class="mb-5">
                            <h4 class="mb-1" id="template-item-form-title">Add Costing Row</h4>
                            <div class="text-muted">Create or update costing breakdown rows for this package.</div>
                        </div>

                        <form method="post" action="<?php echo base_url('Costing/Save_Package_Item'); ?>" id="template-item-form">
                            <input type="hidden" name="package_id" value="<?php echo $package_id; ?>">
                            <input type="hidden" name="item_id" id="template_item_id" value="">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Item Name</label>
                                        <input type="text" name="name" id="template_item_name" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Category</label>
                                        <input type="text" name="category" id="template_item_category" class="form-control" list="costing-category-suggestions" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Cost Type</label>
                                        <select name="cost_type" id="template_item_cost_type" class="form-control">
                                            <?php foreach ($cost_types as $value => $label) { ?>
                                                <option value="<?php echo html_escape($value); ?>"><?php echo html_escape($label); ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Currency</label>
                                        <select name="currency_id" id="template_item_currency_id" class="form-control">
                                            <?php foreach ($currencies as $currency) { ?>
                                                <option value="<?php echo (int) $currency['id']; ?>"><?php echo html_escape($currency['code']); ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label>Description</label>
                                        <input type="text" name="description" id="template_item_description" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Default Unit Price</label>
                                        <input type="number" step="0.01" min="0" name="default_unit_price" id="template_item_default_unit_price" class="form-control" value="0" required>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary font-weight-bold mr-2" id="template-item-submit-button">Save Costing Row</button>
                            <button type="button" class="btn btn-light-primary font-weight-bold" id="reset-template-item-form">Reset</button>
                        </form>
                    </div>

                    <div class="tab-pane fade <?php echo $active_tab === 'bookings' ? 'show active' : ''; ?>" id="booking_snapshot_tab" role="tabpanel">
                        <div class="costing-toolbar mb-5">
                            <div>
                                <h4 class="mb-1">Booking Snapshots</h4>
                                <div class="text-muted">Generate snapshots from selected costing rows, then edit the live booking costing and pricing calculation.</div>
                            </div>
                            <div class="costing-toolbar-actions">
                                <?php if (!empty($bookings)) { ?>
                                    <select class="form-control" id="booking-selector">
                                        <option value="">Open existing booking snapshot</option>
                                        <?php foreach ($bookings as $saved_booking) { ?>
                                            <option value="<?php echo base_url('Costing/Package/' . $package_id . '?tab=bookings&booking_id=' . (int) $saved_booking['id']); ?>" <?php echo (int) $saved_booking['id'] === $booking_id ? 'selected' : ''; ?>>
                                                <?php echo html_escape($saved_booking['booking_number'] . ' | ' . date('d M Y', strtotime($saved_booking['travel_date'])) . ' | ' . $saved_booking['total_pax'] . ' pax'); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                <?php } ?>
                                <a href="<?php echo base_url('Costing/Package/' . $package_id . '?tab=bookings&new_snapshot=1'); ?>" class="btn btn-light-primary font-weight-bold">New Snapshot</a>
                                <?php if ($has_booking) { ?>
                                    <form method="post" action="<?php echo base_url('Costing/Delete_Booking_Snapshot'); ?>" class="costing-inline-form delete-booking-form">
                                        <input type="hidden" name="package_id" value="<?php echo $package_id; ?>">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
                                        <button type="button" class="btn btn-light-danger font-weight-bold delete-booking-button" data-name="<?php echo html_escape($booking['booking_number']); ?>">Delete Snapshot</button>
                                    </form>
                                <?php } ?>
                            </div>
                        </div>

                        <div class="card card-custom card-borderless bg-light mb-8">
                            <div class="card-body">
                                <div class="font-size-h6 font-weight-bold text-dark mb-4">Generate Booking Snapshot</div>
                                <form method="post" action="<?php echo base_url('Costing/Generate_Booking_Snapshot'); ?>" id="generate-booking-form">
                                    <input type="hidden" name="package_id" value="<?php echo $package_id; ?>">
                                    <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
                                    <input type="hidden" name="margin_percentage" value="<?php echo html_escape($financials['margin_percentage']); ?>">
                                    <input type="hidden" name="commissionable_per_pax" value="<?php echo html_escape($financials['commissionable_per_pax']); ?>">
                                    <input type="hidden" name="ad_hoc_per_pax" value="<?php echo html_escape($financials['ad_hoc_per_pax']); ?>">
                                    <div id="selected-package-item-inputs"></div>

                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Travel Date</label>
                                                <input type="date" name="travel_date" class="form-control" value="<?php echo html_escape($booking['travel_date']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label>Adults</label>
                                                <input type="number" min="0" name="adult_count" id="snapshot_adult_count" class="form-control" value="<?php echo $adult_count; ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label>Children</label>
                                                <input type="number" min="0" name="child_count" id="snapshot_child_count" class="form-control" value="<?php echo $child_count; ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label>Total Pax</label>
                                                <input type="number" min="0" id="snapshot_total_pax" class="form-control" value="<?php echo $total_pax; ?>" readonly>
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

                                    <button type="submit" class="btn btn-primary font-weight-bold"><?php echo $has_booking ? 'Regenerate Snapshot' : 'Generate Snapshot'; ?></button>
                                </form>
                            </div>
                        </div>

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

                                            <div class="row mb-5">
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label>Margin Percentage</label>
                                                        <input type="number" step="0.01" min="0" class="form-control financial-input" name="margin_percentage" id="margin_percentage" value="<?php echo html_escape($financials['margin_percentage']); ?>">
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label>Commissionable / Pax</label>
                                                        <input type="number" step="0.01" min="0" class="form-control financial-input" name="commissionable_per_pax" id="commissionable_per_pax" value="<?php echo html_escape($financials['commissionable_per_pax']); ?>">
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label>Ad Hoc / Pax</label>
                                                        <input type="number" step="0.01" min="0" class="form-control financial-input" name="ad_hoc_per_pax" id="ad_hoc_per_pax" value="<?php echo html_escape($financials['ad_hoc_per_pax']); ?>">
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
                                                            <th style="text-align:center;">Rate</th>
                                                            <th style="text-align:center;">Unit Price</th>
                                                            <th style="text-align:center;">Total</th>
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
                                                                    <input type="text" class="form-control row-category" name="rows[<?php echo $index; ?>][category]" value="<?php echo html_escape($item['category']); ?>" list="costing-category-suggestions" required>
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
                                                                <td><input type="number" step="0.0001" min="0" class="form-control row-exchange-rate" value="<?php echo html_escape($item['exchange_rate']); ?>" readonly></td>
                                                                <td><input type="number" step="0.01" min="0" class="form-control row-unit-price" name="rows[<?php echo $index; ?>][unit_price]" value="<?php echo html_escape($item['unit_price']); ?>"></td>
                                                                <td style="text-align:center;"><span class="font-weight-bold row-total-amount"><?php echo number_format((float) $item['total_amount'], 2); ?></span></td>
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
                                            <div class="costing-financial-box">
                                                <strong>Total Cost</strong>
                                                <code>sum(snapshot rows converted to <?php echo html_escape($base_currency); ?>)</code>
                                                <div class="font-size-h4 font-weight-bold mt-3" id="financial-total-cost"><?php echo number_format((float) $financials['total_cost'], 2); ?></div>
                                            </div>
                                            <div class="costing-financial-box">
                                                <strong>Cost Per Pax</strong>
                                                <code>total_cost / total_pax</code>
                                                <div class="font-size-h4 font-weight-bold mt-3" id="financial-cost-per-pax"><?php echo number_format((float) $financials['cost_per_pax'], 2); ?></div>
                                            </div>
                                            <div class="costing-financial-box">
                                                <strong>Markup Amount Total</strong>
                                                <code>total_cost * (margin_percentage / 100)</code>
                                                <div class="font-size-h4 font-weight-bold mt-3" id="financial-markup-total"><?php echo number_format((float) $financials['markup_amount_total'], 2); ?></div>
                                            </div>
                                            <div class="costing-financial-box">
                                                <strong>Price Per Pax</strong>
                                                <code>(total_cost + markup_amount_total) / total_pax</code>
                                                <div class="font-size-h4 font-weight-bold mt-3" id="financial-price-per-pax"><?php echo number_format((float) $financials['price_per_pax'], 2); ?></div>
                                            </div>
                                            <div class="costing-financial-box">
                                                <strong>Total Per Pax</strong>
                                                <code>price_per_pax + commissionable_per_pax + ad_hoc_per_pax</code>
                                                <div class="font-size-h4 font-weight-bold mt-3" id="financial-total-per-pax"><?php echo number_format((float) $financials['total_per_pax'], 2); ?></div>
                                            </div>
                                            <div class="costing-financial-box">
                                                <strong>Selling Price / Pax</strong>
                                                <code>same as total_per_pax</code>
                                                <div class="font-size-h4 font-weight-bold mt-3" id="summary-selling-per-pax"><?php echo number_format((float) $financials['selling_price_per_pax'], 2); ?></div>
                                            </div>
                                            <div class="costing-financial-box">
                                                <strong>Total Revenue</strong>
                                                <code>selling_price_per_pax * total_pax</code>
                                                <div class="font-size-h4 font-weight-bold mt-3" id="financial-total-revenue"><?php echo number_format((float) $financials['total_revenue'], 2); ?></div>
                                            </div>
                                            <div class="costing-financial-box">
                                                <strong>Total Profit</strong>
                                                <code>total_revenue - total_cost</code>
                                                <div class="font-size-h4 font-weight-bold mt-3" id="summary-total-profit"><?php echo number_format((float) $financials['total_profit'], 2); ?></div>
                                            </div>
                                            <div class="alert alert-light-info mb-0">
                                                Base currency: <strong><?php echo html_escape($base_currency); ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var currencies = <?php echo json_encode($currencies); ?>;
        var latestExchangeRates = <?php echo json_encode($latest_exchange_rates); ?>;
        var baseCurrencyCode = <?php echo json_encode($base_currency); ?>;
        var selectedInputsContainer = document.getElementById('selected-package-item-inputs');
        var snapshotCheckboxes = document.querySelectorAll('.snapshot-source');
        var adultField = document.getElementById('snapshot_adult_count');
        var childField = document.getElementById('snapshot_child_count');
        var totalField = document.getElementById('snapshot_total_pax');
        var persistedTotalField = document.getElementById('persisted_total_pax');
        var tableBody = document.querySelector('#costing-items-table tbody');
        var addButton = document.getElementById('add-costing-row');
        var marginField = document.getElementById('margin_percentage');
        var commissionableField = document.getElementById('commissionable_per_pax');
        var adHocField = document.getElementById('ad_hoc_per_pax');
        var generateForm = document.getElementById('generate-booking-form');
        var templateEditButtons = document.querySelectorAll('.edit-template-item');
        var resetTemplateItemButton = document.getElementById('reset-template-item-form');
        var selectAllTemplateItemsButton = document.getElementById('select-all-template-items');
        var clearTemplateItemsButton = document.getElementById('clear-template-items');
        var bookingSelector = document.getElementById('booking-selector');

        var baseCurrencyId = 0;
        var exchangeRateMap = {};

        currencies.forEach(function (currency) {
            if (currency.code === baseCurrencyCode) {
                baseCurrencyId = parseInt(currency.id, 10) || 0;
            }
        });

        latestExchangeRates.forEach(function (rateRow) {
            exchangeRateMap[rateRow.from_currency_id + ':' + rateRow.to_currency_id] = rateRow.rate;
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
                imageUrl: <?php echo json_encode(base_url('assets/image/sweetalert.jpg')); ?>,
                imageWidth: 350,
                imageHeight: 200,
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

        function updateSelectedTemplateInputs() {
            if (!selectedInputsContainer) {
                return;
            }

            selectedInputsContainer.innerHTML = '';
            snapshotCheckboxes.forEach(function (checkbox) {
                if (checkbox.checked) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'selected_package_item_ids[]';
                    input.value = checkbox.value;
                    selectedInputsContainer.appendChild(input);
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

            if (marginTarget && marginField) {
                marginTarget.value = marginField.value;
            }
            if (commissionableTarget && commissionableField) {
                commissionableTarget.value = commissionableField.value;
            }
            if (adHocTarget && adHocField) {
                adHocTarget.value = adHocField.value;
            }
        }

        function updatePaxTotals() {
            var total = asNumber(adultField ? adultField.value : 0) + asNumber(childField ? childField.value : 0);
            if (totalField) {
                totalField.value = total;
            }
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
            var rateField = row.querySelector('.row-exchange-rate');
            if (!currencyField || !rateField) {
                return 0;
            }

            var selectedOption = currencyField.options[currencyField.selectedIndex];
            var selectedCode = selectedOption ? selectedOption.getAttribute('data-code') : '';
            var selectedCurrencyId = parseInt(currencyField.value, 10) || 0;
            if (selectedCode === baseCurrencyCode) {
                rateField.value = '1.0000';
                return 1;
            }

            var mappedRate = exchangeRateMap[selectedCurrencyId + ':' + baseCurrencyId];
            if (mappedRate) {
                rateField.value = parseFloat(mappedRate).toFixed(4);
                return asNumber(mappedRate);
            }

            rateField.value = '0.0000';
            return 0;
        }

        function updateRow(row) {
            var quantity = asNumber(row.querySelector('.row-quantity').value);
            var unitCount = asNumber(row.querySelector('.row-unit-count').value);
            var unitPrice = asNumber(row.querySelector('.row-unit-price').value);
            var exchangeRate = resolveExchangeRate(row);
            var totalAmount = quantity * unitCount * unitPrice;
            var baseTotal = totalAmount * exchangeRate;

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

            var marginPercentage = marginField ? asNumber(marginField.value) : 0;
            var commissionablePerPax = commissionableField ? asNumber(commissionableField.value) : 0;
            var adHocPerPax = adHocField ? asNumber(adHocField.value) : 0;

            var costPerPax = totalPax > 0 ? totalCost / totalPax : 0;
            var markupAmountTotal = totalCost * (marginPercentage / 100);
            var pricePerPax = totalPax > 0 ? (totalCost + markupAmountTotal) / totalPax : 0;
            var totalPerPax = pricePerPax + commissionablePerPax + adHocPerPax;
            var sellingPricePerPax = totalPerPax;
            var totalRevenue = sellingPricePerPax * totalPax;
            var grossProfit = totalRevenue - totalCost;

            document.getElementById('summary-selling-per-pax').textContent = formatAmount(sellingPricePerPax);
            document.getElementById('summary-total-profit').textContent = formatAmount(grossProfit);
            document.getElementById('financial-total-cost').textContent = formatAmount(totalCost);
            document.getElementById('financial-cost-per-pax').textContent = formatAmount(costPerPax);
            document.getElementById('financial-markup-total').textContent = formatAmount(markupAmountTotal);
            document.getElementById('financial-price-per-pax').textContent = formatAmount(pricePerPax);
            document.getElementById('financial-total-per-pax').textContent = formatAmount(totalPerPax);
            document.getElementById('financial-total-revenue').textContent = formatAmount(totalRevenue);
        }

        function bindRow(row) {
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
            document.getElementById('template_item_currency_id').selectedIndex = 0;
            document.getElementById('template-item-form-title').textContent = 'Add Costing Row';
            document.getElementById('template-item-submit-button').textContent = 'Save Costing Row';
        }

        snapshotCheckboxes.forEach(function (checkbox) {
            checkbox.addEventListener('change', updateSelectedTemplateInputs);
        });

        document.querySelectorAll('.delete-template-item-button').forEach(function (button) {
            button.addEventListener('click', function () {
                confirmForm(button, 'Delete Costing Row: ' + (button.getAttribute('data-name') || '' ) + '?', 'This costing breakdown row will be removed from the package.');
            });
        });

        document.querySelectorAll('.delete-booking-button').forEach(function (button) {
            button.addEventListener('click', function () {
                confirmForm(button, 'Delete Snapshot: ' + (button.getAttribute('data-name') || '' ) + '?', 'This booking snapshot and its costing calculation will be deleted.');
            });
        });

        if (bookingSelector) {
            bookingSelector.addEventListener('change', function () {
                if (this.value) {
                    window.location = this.value;
                }
            });
        }

        if (selectAllTemplateItemsButton) {
            selectAllTemplateItemsButton.addEventListener('click', function () {
                snapshotCheckboxes.forEach(function (checkbox) {
                    checkbox.checked = true;
                });
                updateSelectedTemplateInputs();
            });
        }

        if (clearTemplateItemsButton) {
            clearTemplateItemsButton.addEventListener('click', function () {
                snapshotCheckboxes.forEach(function (checkbox) {
                    checkbox.checked = false;
                });
                updateSelectedTemplateInputs();
            });
        }

        if (adultField) {
            adultField.addEventListener('input', updatePaxTotals);
            adultField.addEventListener('change', updatePaxTotals);
        }

        if (childField) {
            childField.addEventListener('input', updatePaxTotals);
            childField.addEventListener('change', updatePaxTotals);
        }

        document.querySelectorAll('.costing-item-row').forEach(bindRow);
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
                    + '<td style="min-width:140px;"><input type="text" class="form-control row-category" name="rows[' + index + '][category]" value="Misc" list="costing-category-suggestions" required></td>'
                    + '<td style="min-width:120px;"><select class="form-control row-pax-type" name="rows[' + index + '][pax_type]"><option value="" selected>All</option><option value="adult">Adult</option><option value="child">Child</option></select></td>'
                    + '<td><input type="number" step="0.01" min="0" class="form-control row-quantity" name="rows[' + index + '][quantity]" value="1"></td>'
                    + '<td><input type="number" step="0.01" min="0" class="form-control row-unit-count" name="rows[' + index + '][unit_count]" value="1"></td>'
                    + '<td style="min-width:120px;"><select class="form-control row-currency-id" name="rows[' + index + '][currency_id]">' + currencyOptions + '</select></td>'
                    + '<td><input type="number" step="0.0001" min="0" class="form-control row-exchange-rate" value="1.0000" readonly></td>'
                    + '<td><input type="number" step="0.01" min="0" class="form-control row-unit-price" name="rows[' + index + '][unit_price]" value="0"></td>'
                    + '<td style="text-align:center;"><span class="font-weight-bold row-total-amount">0.00</span></td>'
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
                document.getElementById('template-item-form-title').textContent = 'Update Costing Row';
                document.getElementById('template-item-submit-button').textContent = 'Save Costing Row';
                window.location.hash = 'costing_breakdown_tab';
            });
        });

        if (resetTemplateItemButton) {
            resetTemplateItemButton.addEventListener('click', resetTemplateItemForm);
        }

        updateSelectedTemplateInputs();
        updatePaxTotals();
        syncGenerateFinancials();
        recalculateFinancials();
    })();
</script>
