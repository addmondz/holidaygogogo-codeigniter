<?php
$package = isset($package) ? $package : array();
$booking = isset($booking) ? $booking : array();
$booking_items = isset($booking_items) ? $booking_items : array();
$item_master = isset($item_master) ? $item_master : array();
$currencies = isset($currencies) ? $currencies : array();
$currency_rate_map = isset($currency_rate_map) ? $currency_rate_map : array();
$financials = isset($financials) ? $financials : array();
$itinerary_days = isset($itinerary_days) ? $itinerary_days : array();
$combinations = isset($combinations) ? $combinations : array();
$statuses = isset($statuses) ? $statuses : array('active', 'inactive');
$wizard_steps = isset($wizard_steps) ? $wizard_steps : array('details' => 'Package Details', 'cost' => 'Cost Template & Margin', 'itinerary' => 'Itinerary', 'done' => 'Save & Quotation');
$active_step = isset($active_step) ? $active_step : 'details';
$has_snapshot = !empty($has_snapshot);

$package_id = isset($package['id']) ? (int) $package['id'] : 0;
$booking_id = isset($booking['id']) ? (int) $booking['id'] : 0;
$duration_days = isset($package['duration_days']) ? (int) $package['duration_days'] : 1;
$adult_count = isset($booking['adult_count']) ? (int) $booking['adult_count'] : 2;
$child_count = isset($booking['child_count']) ? (int) $booking['child_count'] : 0;
$total_pax = isset($booking['total_pax']) ? (int) $booking['total_pax'] : ($adult_count + $child_count);
$travel_date = isset($booking['travel_date']) ? $booking['travel_date'] : date('Y-m-d');
$booking_status_value = isset($booking['status_value']) ? $booking['status_value'] : 'active';
$margin_percentage = isset($financials['margin_percentage']) ? (float) $financials['margin_percentage'] : 0;

// Multiplier options for the per-row picker in the cost template. Each row copies
// its item's default multiplier, but the user may override it here (e.g. a normally
// per-day guide charged once for this package).
$multiplier_type_options = function_exists('costing_multiplier_types')
    ? costing_multiplier_types()
    : array('per_day' => 'Per Day', 'per_pax' => 'Per Pax', 'per_day_pax' => 'Per Day & Pax', 'fixed' => 'Fixed');

$step_keys = array_keys($wizard_steps);
$active_index = array_search($active_step, $step_keys, true);
if ($active_index === false) {
    $active_index = 0;
}

// Cost rows: reuse the saved snapshot when it exists. A fresh template shows
// only category headers (each with its own item picker) — items are added
// per-category on demand, not pre-seeded from the master.
$cost_rows = array();
if (!empty($booking_items)) {
    foreach ($booking_items as $item) {
        $cost_rows[] = array(
            'name'            => isset($item['name']) ? $item['name'] : '',
            'category'        => isset($item['category']) ? $item['category'] : 'miscellaneous',
            'multiplier_type' => isset($item['multiplier_type']) ? $item['multiplier_type'] : 'fixed',
            'currency_id'     => (int) (isset($item['currency_id']) ? $item['currency_id'] : 0),
            'unit_price'      => (float) (isset($item['unit_price']) ? $item['unit_price'] : 0),
            'count'           => (float) (isset($item['quantity']) ? $item['quantity'] : 1),
            'remark'          => isset($item['remark']) ? $item['remark'] : '',
            'include'         => true,
        );
    }
}
?>

<style>
    .cw-wrap { max-width: 1180px; margin: 0 auto; }
    .cw-stepper { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 26px; }
    .cw-step {
        flex: 1 1 190px; display: flex; align-items: center; gap: 12px;
        border: 1px solid #e4e6ef; border-radius: 8px; padding: 14px 16px;
        background: #ffffff; color: #3f4254; text-decoration: none;
    }
    .cw-step .cw-num {
        width: 34px; height: 34px; border-radius: 50%; flex: 0 0 34px;
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 15px; background: #eef1f7; color: #6082B6;
    }
    .cw-step .cw-label { font-weight: 600; font-size: 14px; line-height: 1.2; }
    .cw-step.is-active { border-color: #6082B6; background: #eef4ff; }
    .cw-step.is-active .cw-num { background: #6082B6; color: #fff; }
    .cw-step.is-done .cw-num { background: #1BC5BD; color: #fff; }
    .cw-step.is-upcoming { color: #9aa0b3; }
    .cw-panel {
        border: 1px solid #e4e6ef; border-radius: 8px; background: #fff;
        padding: 26px; margin-bottom: 20px;
    }
    .cw-panel-title { font-size: 19px; font-weight: 700; color: #3f4254; margin-bottom: 4px; }
    .cw-panel-sub { color: #6d7288; margin-bottom: 22px; }
    .cw-cost-table th { background: #f3f6fb; color: #3f4254; font-size: 12.5px; text-align: center; white-space: nowrap; }
    .cw-cost-table td { vertical-align: middle; }
    .cw-cost-table tr.cw-cat-row td {
        background: #eef4ff; color: #45608a; font-weight: 700; font-size: 12.5px;
        text-transform: uppercase; letter-spacing: .5px; padding: 9px 12px;
    }
    .cw-cost-table input, .cw-cost-table select { min-width: 90px; }
    .cw-myr { font-weight: 700; color: #3f4254; white-space: nowrap; }
    .cw-bank-note { display: block; font-size: 11.5px; font-weight: 600; color: #8a6d3b; margin-top: 2px; }
    .cw-total { font-weight: 700; color: #187DE4; white-space: nowrap; }
    .cw-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-top: 20px; }
    .cw-summary-box { border: 1px solid #e4e6ef; border-radius: 8px; padding: 14px 16px; background: #f9fbff; }
    .cw-summary-box .lbl { font-size: 11px; text-transform: uppercase; font-weight: 700; color: #6d7288; letter-spacing: .4px; }
    .cw-summary-box .val { font-size: 20px; font-weight: 700; color: #3f4254; margin-top: 4px; }
    .cw-actions { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-top: 8px; }
    .cw-itin-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 20px; }
    .cw-itin-field { min-width: 0; }
    @media (max-width: 767px) { .cw-itin-fields { grid-template-columns: 1fr; } }
    .cw-combo-card { border: 1px solid #e4e6ef; border-radius: 8px; padding: 16px; margin-bottom: 16px; background: #fbfdff; }
    .cw-combo-head { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
    .cw-combo-head .cw-combo-name { font-weight: 700; }
    .cw-combo-foot { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 22px; margin-top: 8px; font-weight: 700; color: #3f4254; }
    .cw-combo-foot .sell { color: #187DE4; }
    .cw-combo-empty { color: #9aa0b3; font-style: italic; }
</style>

<div class="d-flex flex-column-fluid">
    <div class="container-fluid cw-wrap">

        <div class="d-flex justify-content-between align-items-center mb-5 flex-wrap" style="gap:10px;">
            <div>
                <div class="font-size-h4 font-weight-bolder text-dark"><?php echo $package_id > 0 ? html_escape($package['name']) : 'New Costing Package'; ?></div>
                <div class="text-muted"><?php echo $package_id > 0 ? ((int) $duration_days . 'D / ' . (int) (isset($package['duration_nights']) ? $package['duration_nights'] : 0) . 'N Costing Package') : 'Start with the package details below.'; ?></div>
            </div>
            <a href="<?php echo base_url('Costing'); ?>" class="btn btn-light-primary font-weight-bold">
                <i class="la la-arrow-left"></i>Back To Packages
            </a>
        </div>

        <?php if ($this->session->flashdata('message_success')) { ?>
            <div class="alert alert-success"><?php echo html_escape($this->session->flashdata('message_success')); ?></div>
        <?php } ?>
        <?php if ($this->session->flashdata('message_error')) { ?>
            <div class="alert alert-danger"><?php echo html_escape($this->session->flashdata('message_error')); ?></div>
        <?php } ?>

        <div class="cw-stepper">
            <?php $i = 0; foreach ($wizard_steps as $key => $label) {
                $state = $i === $active_index ? 'is-active' : ($i < $active_index ? 'is-done' : 'is-upcoming');
                // Navigable only once the package exists (saved).
                $tag = $package_id > 0 ? 'a' : 'span';
                $href = $package_id > 0 ? ' href="' . base_url('Costing/Package/' . $package_id . '?step=' . $key) . '"' : '';
            ?>
                <<?php echo $tag . $href; ?> class="cw-step <?php echo $state; ?>">
                    <span class="cw-num"><?php echo ($state === 'is-done') ? '<i class="la la-check"></i>' : ($i + 1); ?></span>
                    <span class="cw-label"><?php echo html_escape($label); ?></span>
                </<?php echo $tag; ?>>
            <?php $i++; } ?>
        </div>

        <?php if ($active_step === 'details') { ?>
        <!-- STEP 1: PACKAGE DETAILS -->
        <form method="post" action="<?php echo base_url('Costing/Save_Package'); ?>">
            <input type="hidden" name="package_id" value="<?php echo $package_id; ?>">
            <input type="hidden" name="wizard_next" value="cost">
            <div class="cw-panel">
                <div class="cw-panel-title">Package Details</div>
                <div class="cw-panel-sub">Name the package and set its duration and status.</div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label>Package Name</label>
                            <input type="text" name="name" class="form-control" required value="<?php echo html_escape($package['name']); ?>">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Customer Name</label>
                            <input type="text" name="customer_name" class="form-control" value="<?php echo html_escape(isset($package['customer_name']) ? $package['customer_name'] : ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Contact Number</label>
                            <input type="text" name="customer_contact" class="form-control" value="<?php echo html_escape(isset($package['customer_contact']) ? $package['customer_contact'] : ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="customer_email" class="form-control" value="<?php echo html_escape(isset($package['customer_email']) ? $package['customer_email'] : ''); ?>">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Days</label>
                            <input type="number" min="1" name="duration_days" class="form-control" required value="<?php echo (int) $duration_days; ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Nights</label>
                            <input type="number" min="0" name="duration_nights" class="form-control" required value="<?php echo (int) (isset($package['duration_nights']) ? $package['duration_nights'] : 0); ?>">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <?php foreach ($statuses as $status_option) { ?>
                                    <option value="<?php echo html_escape($status_option); ?>" <?php echo (isset($package['status_value']) && $package['status_value'] === $status_option) ? 'selected' : ''; ?>><?php echo html_escape(ucfirst($status_option)); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" rows="3" class="form-control"><?php echo html_escape(isset($package['description']) ? $package['description'] : ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="cw-actions">
                <a href="<?php echo base_url('Costing'); ?>" class="btn btn-light font-weight-bold">Cancel</a>
                <button type="submit" class="btn btn-primary font-weight-bold">Save &amp; Continue<i class="la la-arrow-right ml-2"></i></button>
            </div>
        </form>

        <?php } elseif ($active_step === 'cost') { ?>
        <!-- STEP 2: COST TEMPLATE + MARGIN -->
        <form method="post" action="<?php echo base_url('Costing/Save_Cost_Step'); ?>" id="cw-cost-form">
            <input type="hidden" name="package_id" value="<?php echo $package_id; ?>">
            <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
            <input type="hidden" name="status" value="<?php echo html_escape($booking_status_value); ?>">
            <div class="cw-panel">
                <div class="cw-panel-title">Cost Template &amp; Margin</div>
                <div class="cw-panel-sub">Enter each cost in its currency — MYR converts automatically (bank charges included) using the Costing Currency rates, then multiplies by the day/pax count.</div>

                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="form-group mb-2">
                            <label>Travel Date</label>
                            <input type="date" name="travel_date" class="form-control" value="<?php echo html_escape(date('Y-m-d', strtotime($travel_date))); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group mb-2">
                            <label>Adults</label>
                            <input type="number" min="0" id="cw-adult" name="adult_count" class="form-control" value="<?php echo (int) $adult_count; ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group mb-2">
                            <label>Children</label>
                            <input type="number" min="0" id="cw-child" name="child_count" class="form-control" value="<?php echo (int) $child_count; ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group mb-2">
                            <label>Total Pax</label>
                            <input type="number" id="cw-total-pax" class="form-control" value="<?php echo (int) $total_pax; ?>" readonly>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered cw-cost-table mb-2" id="cw-cost-table">
                        <thead>
                            <tr>
                                <th style="width:34px;">Use</th>
                                <th style="text-align:left;">Details</th>
                                <th style="width:120px;">Currency</th>
                                <th style="width:120px;">Cost</th>
                                <th style="width:140px;">MYR (convert)</th>
                                <th style="width:120px;">No of Day / Pax</th>
                                <th style="width:140px;">Total in MYR</th>
                                <th style="width:44px;"></th>
                            </tr>
                        </thead>
                        <tbody id="cw-cost-body">
                            <?php
                            // Group the cost rows by category (canonical order first, then any
                            // stragglers) so the template reads as sections, not one long list.
                            $this->load->helper('costing_calc');
                            $this->load->model('Costing_Category_Model');
                            $cat_labels = $this->Costing_Category_Model->Read_Category_Map();
                            $grouped = array();
                            foreach ($cost_rows as $idx => $row) {
                                $cat = isset($row['category']) ? $row['category'] : 'miscellaneous';
                                if (!isset($cat_labels[$cat])) { $cat = 'miscellaneous'; }
                                $grouped[$cat][] = array('idx' => $idx, 'row' => $row);
                            }
                            // Render every dynamic category from the master so the user can add
                            // items under any of them — even categories with no master items or
                            // saved rows yet (empty pickers still let the section show).
                            foreach (array_keys($cat_labels) as $cat) {
                            ?>
                                <tr class="cw-cat-row" data-cat="<?php echo html_escape($cat); ?>">
                                    <td colspan="8">
                                        <div class="d-flex flex-wrap align-items-center justify-content-between" style="gap:8px;">
                                            <span><?php echo html_escape($cat_labels[$cat]); ?></span>
                                            <div class="d-flex align-items-center" style="gap:8px;">
                                                <select class="form-control form-control-sm cw-cat-add" data-cat="<?php echo html_escape($cat); ?>" style="min-width:240px;max-width:280px;">
                                                    <option value="">— Add <?php echo html_escape($cat_labels[$cat]); ?> item —</option>
                                                    <?php foreach ($item_master as $mi_idx => $mi) {
                                                        $mcat = isset($mi['category']) ? $mi['category'] : 'miscellaneous';
                                                        if (!isset($cat_labels[$mcat])) { $mcat = 'miscellaneous'; }
                                                        if ($mcat !== $cat) { continue; }
                                                    ?>
                                                        <option value="<?php echo (int) $mi_idx; ?>"><?php echo html_escape($mi['name']); ?> (<?php echo html_escape(isset($mi['currency_code']) ? $mi['currency_code'] : ''); ?> · <?php echo html_escape(isset($mi['multiplier_label']) ? $mi['multiplier_label'] : ''); ?>)</option>
                                                    <?php } ?>
                                                </select>
                                                <button type="button" class="btn btn-sm btn-success font-weight-bold cw-cat-add-btn" data-cat="<?php echo html_escape($cat); ?>"><i class="la la-plus"></i>Add</button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php foreach ((isset($grouped[$cat]) ? $grouped[$cat] : array()) as $entry) { $idx = $entry['idx']; $row = $entry['row']; ?>
                                <tr class="cw-row" data-cat="<?php echo html_escape($cat); ?>">
                                    <td class="text-center">
                                        <input type="hidden" name="rows[<?php echo $idx; ?>][include]" value="0" class="cw-include-hidden">
                                        <input type="checkbox" class="cw-include" <?php echo !empty($row['include']) ? 'checked' : ''; ?>>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" name="rows[<?php echo $idx; ?>][name]" value="<?php echo html_escape($row['name']); ?>" placeholder="Cost item" readonly>
                                        <select class="form-control form-control-sm cw-mult-type mt-2" name="rows[<?php echo $idx; ?>][multiplier_type]" title="How this cost scales">
                                            <?php $rmt = strtolower(trim((string) $row['multiplier_type'])); if (!isset($multiplier_type_options[$rmt])) { $rmt = 'fixed'; } foreach ($multiplier_type_options as $mkey => $mlabel) { ?>
                                                <option value="<?php echo html_escape($mkey); ?>" <?php echo ($mkey === $rmt) ? 'selected' : ''; ?>><?php echo html_escape($mlabel); ?></option>
                                            <?php } ?>
                                        </select>
                                        <textarea class="form-control form-control-sm cw-remark mt-2" name="rows[<?php echo $idx; ?>][remark]" rows="2" placeholder="Remark (optional)"><?php echo html_escape($row['remark']); ?></textarea>
                                        <input type="hidden" name="rows[<?php echo $idx; ?>][category]" value="<?php echo html_escape($cat); ?>">
                                        <input type="hidden" name="rows[<?php echo $idx; ?>][unit_count]" value="1">
                                        <input type="hidden" name="rows[<?php echo $idx; ?>][pax_type]" value="">
                                        <input type="hidden" class="cw-myr-hidden" name="rows[<?php echo $idx; ?>][myr_per_unit]" value="">
                                        <input type="hidden" class="cw-bank-hidden" name="rows[<?php echo $idx; ?>][bank_charges_myr]" value="">
                                    </td>
                                    <td>
                                        <select class="form-control cw-currency" name="rows[<?php echo $idx; ?>][currency_id]">
                                            <?php foreach ($currencies as $currency) { ?>
                                                <option value="<?php echo (int) $currency['id']; ?>" <?php echo ((int) $currency['id'] === (int) $row['currency_id']) ? 'selected' : ''; ?>><?php echo html_escape($currency['code']); ?></option>
                                            <?php } ?>
                                        </select>
                                    </td>
                                    <td><input type="number" step="0.01" min="0" class="form-control cw-cost" name="rows[<?php echo $idx; ?>][unit_price]" value="<?php echo html_escape($row['unit_price']); ?>"></td>
                                    <td class="text-right cw-myr"><span class="cw-myr-val">0.00</span><span class="cw-bank-note"></span></td>
                                    <td><input type="number" step="1" min="0" class="form-control cw-count" name="rows[<?php echo $idx; ?>][quantity]" value="<?php echo html_escape($row['count']); ?>"></td>
                                    <td class="text-right cw-total">0.00</td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-icon btn-light-danger btn-sm cw-remove" title="Remove row"><i class="la la-trash"></i></button>
                                    </td>
                                </tr>
                                <?php } ?>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <div class="row mt-3">
                    <div class="col-md-4">
                        <div class="form-group mb-0">
                            <label>Margin (Markup on Cost %)</label>
                            <input type="number" step="0.01" min="0" id="cw-margin" name="margin_percentage" class="form-control" value="<?php echo html_escape($margin_percentage); ?>">
                            <span class="form-text text-muted">Selling = Cost × (1 + margin%). No manual selling price.</span>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-center mt-3" style="gap:8px;">
                    <a href="<?php echo base_url('Costing_Item'); ?>" target="_blank" class="btn btn-light font-weight-bold" title="Manage item master">Manage Items</a>
                </div>
                <?php if (empty($item_master)) { ?>
                    <div class="text-muted mt-2">No items in the master yet — add them under <a href="<?php echo base_url('Costing_Item'); ?>" target="_blank">Costing Item</a>.</div>
                <?php } ?>

                <?php
                // Per-currency roll-up: rate to MYR + total owed in each currency
                // used, so the user sees how much of each foreign currency this
                // package needs and at what frozen rate.
                $cur_breakdown = costing_currency_breakdown($cost_rows, $currency_rate_map);
                ?>
                <div class="cw-cur-panel" id="cw-cur-panel" style="margin-top:24px;<?php echo empty($cur_breakdown) ? 'display:none;' : ''; ?>">
                    <div class="cw-panel-title" style="font-size:14px;">Currency Breakdown</div>
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0 w-100" id="cw-cur-table">
                            <thead>
                                <tr>
                                    <th style="text-align:left;">Currency</th>
                                    <th class="text-right">Rate to MYR</th>
                                    <th class="text-right">Bank Charge / unit</th>
                                    <th class="text-right">Total (currency)</th>
                                    <th class="text-right">Total in MYR</th>
                                </tr>
                            </thead>
                            <tbody id="cw-cur-body">
                                <?php foreach ($cur_breakdown as $cb) { ?>
                                <tr>
                                    <td style="text-align:left;"><?php echo html_escape($cb['code']); ?></td>
                                    <td class="text-right"><?php echo number_format($cb['rate_to_myr'], 4); ?></td>
                                    <td class="text-right"><?php echo $cb['bank_charges_myr'] > 0 ? 'RM ' . number_format($cb['bank_charges_myr'], 2) : '—'; ?></td>
                                    <td class="text-right"><?php echo html_escape($cb['code']) . ' ' . number_format($cb['total_foreign'], 2); ?></td>
                                    <td class="text-right">RM <?php echo number_format($cb['total_myr'], 2); ?></td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="cw-summary">
                    <div class="cw-summary-box"><div class="lbl">Total Cost</div><div class="val" id="cw-sum-cost">RM 0.00</div></div>
                    <div class="cw-summary-box"><div class="lbl">Cost / Pax</div><div class="val" id="cw-sum-costpax">RM 0.00</div></div>
                    <div class="cw-summary-box"><div class="lbl">Selling / Pax</div><div class="val" id="cw-sum-sellpax">RM 0.00</div></div>
                    <div class="cw-summary-box"><div class="lbl">Total Revenue</div><div class="val" id="cw-sum-revenue">RM 0.00</div></div>
                    <div class="cw-summary-box"><div class="lbl">Profit</div><div class="val" id="cw-sum-profit" style="color:#1BC5BD;">RM 0.00</div></div>
                </div>
            </div>

            <!-- COMBINATIONS: customer-facing bundles shown on the Quotation PDF -->
            <div class="cw-panel">
                <div class="cw-panel-title">Combinations</div>
                <div class="cw-panel-sub">Bundles shown on the customer Quotation PDF (the internal cost items above are hidden from it). Add items to each combination by picking from the internal cost table above — its live cost is copied in. Selling price = its cost &times; margin. All combinations add up to the total package price.</div>

                <div id="cw-combos"></div>

                <div class="d-flex flex-wrap align-items-center justify-content-between mt-2" style="gap:10px;">
                    <button type="button" class="btn btn-light-primary font-weight-bold" id="cw-combo-add"><i class="la la-plus"></i>Add Combination</button>
                    <div class="cw-summary-box" style="margin:0;min-width:240px;">
                        <div class="lbl">Combinations Total (Selling)</div>
                        <div class="val" id="cw-combo-grand" style="color:#187DE4;">RM 0.00</div>
                    </div>
                </div>
                <?php if (empty($item_master)) { ?>
                    <div class="text-muted mt-2">Add items to the master first under <a href="<?php echo base_url('Costing_Item'); ?>" target="_blank">Costing Item</a> to build combinations.</div>
                <?php } ?>
            </div>

            <div class="cw-actions">
                <a href="<?php echo base_url('Costing/Package/' . $package_id . '?step=details'); ?>" class="btn btn-light font-weight-bold"><i class="la la-arrow-left mr-2"></i>Back</a>
                <button type="submit" class="btn btn-primary font-weight-bold">Save &amp; Continue<i class="la la-arrow-right ml-2"></i></button>
            </div>
        </form>

        <?php } elseif ($active_step === 'itinerary') { ?>
        <!-- STEP 3: ITINERARY -->
        <?php
        $itin_rows = !empty($itinerary_days) ? $itinerary_days : array();
        if (empty($itin_rows)) {
            for ($d = 1; $d <= max(1, $duration_days); $d++) {
                $itin_rows[] = array('day_number' => $d);
            }
        }
        // Each day = a plain-text title + five rich-text (TinyMCE) HTML blocks.
        $itin_fields = array(
            'description'          => 'Description',
            'meal_plan'            => 'Meal Plan',
            'notes'                => 'Notes',
            'special_remark'       => 'Special Remark',
            'terms_and_conditions' => 'Terms & Conditions',
        );
        // Renders one day card. $tpl=true emits the blank JS template with an
        // "__I__" index placeholder (values blank); otherwise a saved/seed row.
        $render_itin_card = function ($i, $day, $tpl = false) use ($itin_fields) {
            $idx  = $tpl ? '__I__' : (int) $i;
            $dayn = $tpl ? '' : (int) (isset($day['day_number']) ? $day['day_number'] : ((int) $i + 1));
            ob_start(); ?>
            <div class="cw-panel cw-itin-day">
                <div class="d-flex align-items-end mb-4" style="gap:10px;">
                    <div style="width:80px;">
                        <label class="font-weight-bold mb-1">Day</label>
                        <input type="number" min="1" class="form-control" name="itinerary[<?php echo $idx; ?>][day_number]" value="<?php echo $dayn; ?>">
                    </div>
                    <div style="flex:1;">
                        <label class="font-weight-bold mb-1">Title</label>
                        <input type="text" class="form-control" name="itinerary[<?php echo $idx; ?>][title]" value="<?php echo $tpl ? '' : html_escape(isset($day['title']) ? $day['title'] : ''); ?>" placeholder="Day title e.g. Arrival &amp; City Tour">
                    </div>
                    <button type="button" class="btn btn-icon btn-light-danger cw-itin-remove" data-toggle="tooltip" title="Remove day"><i class="la la-trash"></i></button>
                </div>
                <div class="cw-itin-fields">
                    <?php foreach ($itin_fields as $key => $label) { ?>
                        <div class="cw-itin-field">
                            <label class="font-weight-bold mb-1"><?php echo $label; ?></label>
                            <textarea id="cw-ed-<?php echo $idx; ?>-<?php echo $key; ?>" class="form-control cw-itin-editor" name="itinerary[<?php echo $idx; ?>][<?php echo $key; ?>]"><?php echo $tpl ? '' : html_escape(isset($day[$key]) ? $day[$key] : ''); ?></textarea>
                        </div>
                    <?php } ?>
                </div>
            </div>
            <?php return ob_get_clean();
        };
        ?>
        <form method="post" action="<?php echo base_url('Costing/Save_Itinerary'); ?>" id="cw-itin-form">
            <input type="hidden" name="package_id" value="<?php echo $package_id; ?>">
            <div class="cw-panel">
                <div class="cw-panel-title">Itinerary</div>
                <div class="cw-panel-sub">Per-day plan shown on the customer Quotation PDF. Each block is a rich-text editor.</div>
            </div>
            <div id="cw-itin-body">
                <?php foreach ($itin_rows as $i => $day) { echo $render_itin_card($i, $day); } ?>
            </div>
            <template id="cw-itin-tpl"><?php echo $render_itin_card(0, array(), true); ?></template>
            <div class="cw-panel">
                <button type="button" class="btn btn-light-primary font-weight-bold" id="cw-itin-add"><i class="la la-plus"></i>Add Day</button>
            </div>
            <div class="cw-actions">
                <a href="<?php echo base_url('Costing/Package/' . $package_id . '?step=cost'); ?>" class="btn btn-light font-weight-bold"><i class="la la-arrow-left mr-2"></i>Back</a>
                <button type="submit" class="btn btn-primary font-weight-bold">Save &amp; Continue<i class="la la-arrow-right ml-2"></i></button>
            </div>
        </form>

        <?php } else { ?>
        <!-- STEP 4: DONE + QUOTATION PDF -->
        <div class="cw-panel">
            <?php if ($has_snapshot) { ?>
                <div class="d-flex align-items-center justify-content-between flex-wrap mb-4" style="gap:10px;">
                    <div>
                        <div class="cw-panel-title"><i class="la la-check-circle text-success"></i> Costing Saved</div>
                        <div class="cw-panel-sub mb-0">Your customer quotation is ready below.</div>
                    </div>
                    <div class="d-flex flex-wrap" style="gap:8px;">
                        <a href="<?php echo base_url('Costing/Quotation/' . $package_id); ?>" target="_blank" class="btn btn-light-success font-weight-bold"><i class="la la-external-link-alt"></i>Open in New Tab</a>
                        <a href="<?php echo base_url('Costing/Package/' . $package_id . '?step=cost'); ?>" class="btn btn-light-primary font-weight-bold"><i class="la la-edit"></i>Edit Costing</a>
                        <a href="<?php echo base_url('Costing'); ?>" class="btn btn-primary font-weight-bold">Done</a>
                    </div>
                </div>
                <iframe src="<?php echo base_url('Costing/Quotation/' . $package_id); ?>" title="Quotation PDF" style="width:100%; height:820px; border:1px solid #e4e6ef; border-radius:8px;"></iframe>
            <?php } else { ?>
                <div class="cw-panel-title">Almost There</div>
                <div class="cw-panel-sub">Add the cost template first — the quotation is generated from your saved costing.</div>
                <a href="<?php echo base_url('Costing/Package/' . $package_id . '?step=cost'); ?>" class="btn btn-primary font-weight-bold">Go to Cost Template</a>
            <?php } ?>
        </div>
        <?php } ?>

    </div>
</div>

<?php if ($active_step === 'cost') { ?>
<script>
(function () {
    var RATE_MAP = <?php echo json_encode($currency_rate_map); ?> || {};
    var CAT_LABELS = <?php echo json_encode($cat_labels); ?> || {};
    var DURATION_DAYS = <?php echo (int) $duration_days; ?>;

    var body = document.getElementById('cw-cost-body');
    var adultInput = document.getElementById('cw-adult');
    var childInput = document.getElementById('cw-child');
    var totalPaxInput = document.getElementById('cw-total-pax');
    var marginInput = document.getElementById('cw-margin');
    var rowSeq = <?php echo count($cost_rows); ?>;
    var combosWrap = document.getElementById('cw-combos');
    var comboSeq = 0; // monotonic combination index for unique field names

    // Saved combinations to re-render on load (edit mode). Each: name + its items.
    var EXISTING_COMBOS = <?php echo json_encode(array_map(function ($combo) {
        return array(
            'name' => isset($combo['name']) ? $combo['name'] : '',
            'items' => array_map(function ($it) {
                return array(
                    'name'            => isset($it['name']) ? $it['name'] : '',
                    'category'        => isset($it['category']) ? $it['category'] : 'miscellaneous',
                    'multiplier_type' => isset($it['multiplier_type']) ? $it['multiplier_type'] : 'fixed',
                    'currency_id'     => (int) (isset($it['currency_id']) ? $it['currency_id'] : 0),
                    'unit_price'      => (float) (isset($it['unit_price']) ? $it['unit_price'] : 0),
                    'count'           => (float) (isset($it['quantity']) ? $it['quantity'] : 1),
                    'remark'          => isset($it['remark']) ? $it['remark'] : '',
                );
            }, isset($combo['items']) ? $combo['items'] : array()),
        );
    }, $combinations)); ?> || [];

    function money(n) { return 'RM ' + (Math.round((Number(n) || 0) * 100) / 100).toFixed(2); }
    function totalPax() { return (parseInt(adultInput.value, 10) || 0) + (parseInt(childInput.value, 10) || 0); }

    function rowMyrPerUnit(row) {
        var currencyId = row.querySelector('.cw-currency').value;
        var info = RATE_MAP[currencyId] || { rate_to_myr: 0, bank_charges_myr: 0 };
        var cost = parseFloat(row.querySelector('.cw-cost').value) || 0;
        var perUnit = Math.round(cost * (Number(info.rate_to_myr) || 0) * 100) / 100 + (Number(info.bank_charges_myr) || 0);
        return Math.round(perUnit * 100) / 100;
    }

    function autoCount(row) {
        var type = row.querySelector('.cw-mult-type').value;
        if (type === 'per_day') { return DURATION_DAYS; }
        if (type === 'per_pax') { return totalPax(); }
        if (type === 'per_day_pax') { return DURATION_DAYS * totalPax(); }
        return null; // fixed / custom -> leave user value
    }

    // Price a single cost row (internal template OR a combination): refresh its
    // day/pax count, freeze the MYR-convert + bank charge onto its hidden inputs,
    // paint the MYR/Total cells. Returns { included, total }. Combination rows have
    // no Use checkbox, so they are always included.
    function processRow(row) {
        var includeEl = row.querySelector('.cw-include');
        var included = includeEl ? includeEl.checked : true;
        var incHidden = row.querySelector('.cw-include-hidden');
        if (incHidden) { incHidden.value = included ? '1' : '0'; }

        // Refresh day/pax-driven counts as pax changes.
        var auto = autoCount(row);
        var countInput = row.querySelector('.cw-count');
        if (auto !== null && document.activeElement !== countInput) { countInput.value = auto; }

        var perUnit = rowMyrPerUnit(row);
        var count = parseFloat(countInput.value) || 0;
        var total = Math.round(perUnit * count * 100) / 100;

        // Freeze the "MYR (convert)" figure (bank charge baked in) + the bank
        // charge itself onto hidden inputs so the saved value is exactly what
        // the user sees here, not re-pulled from the master on save.
        var info = RATE_MAP[row.querySelector('.cw-currency').value] || { bank_charges_myr: 0 };
        var myrHidden = row.querySelector('.cw-myr-hidden');
        var bankHidden = row.querySelector('.cw-bank-hidden');
        var bankVal = Number(info.bank_charges_myr) || 0;
        if (myrHidden) { myrHidden.value = perUnit; }
        if (bankHidden) { bankHidden.value = bankVal; }

        var myrCell = row.querySelector('.cw-myr');
        myrCell.querySelector('.cw-myr-val').textContent = money(perUnit);
        myrCell.querySelector('.cw-bank-note').textContent = bankVal > 0 ? ('incl. ' + money(bankVal) + ' bank') : '';
        row.querySelector('.cw-total').textContent = money(total);
        row.style.opacity = included ? '1' : '0.45';
        return { included: included, total: total };
    }

    function currentMargin() {
        var margin = parseFloat(marginInput.value) || 0;
        return margin < 0 ? 0 : margin;
    }

    function recalc() {
        totalPaxInput.value = totalPax();
        var grandCost = 0;
        body.querySelectorAll('.cw-row').forEach(function (row) {
            var res = processRow(row);
            if (res.included) { grandCost += res.total; }
        });

        var pax = totalPax() || 1;
        var margin = currentMargin();
        var costPax = grandCost / pax;
        var sellPax = Math.round(costPax * (1 + margin / 100) * 100) / 100;
        var revenue = Math.round(sellPax * pax * 100) / 100;
        var profit = Math.round((revenue - grandCost) * 100) / 100;

        document.getElementById('cw-sum-cost').textContent = money(grandCost);
        document.getElementById('cw-sum-costpax').textContent = money(costPax);
        document.getElementById('cw-sum-sellpax').textContent = money(sellPax);
        document.getElementById('cw-sum-revenue').textContent = money(revenue);
        document.getElementById('cw-sum-profit').textContent = money(profit);

        renderCurrencyBreakdown();
        recalcCombos(margin);
    }

    // Each combination's cost = sum of its rows' MYR totals; selling = cost x
    // (1 + margin%). Combinations are additive: their sellings sum into the grand
    // total shown to the customer.
    function recalcCombos(margin) {
        var grand = 0;
        combosWrap.querySelectorAll('.cw-combo-card').forEach(function (card) {
            var cost = 0;
            card.querySelectorAll('.cw-crow').forEach(function (row) { cost += processRow(row).total; });
            var selling = Math.round(cost * (1 + margin / 100) * 100) / 100;
            var costEl = card.querySelector('.cw-combo-cost');
            var sellEl = card.querySelector('.cw-combo-sell');
            if (costEl) { costEl.textContent = money(cost); }
            if (sellEl) { sellEl.textContent = money(selling); }
            grand += selling;
        });
        var g = document.getElementById('cw-combo-grand');
        if (g) { g.textContent = money(grand); }
    }

    // Roll included rows up per currency: rate to MYR + total owed in each
    // currency (mirrors the costing_currency_breakdown() PHP helper).
    function renderCurrencyBreakdown() {
        var panel = document.getElementById('cw-cur-panel');
        var tbody = document.getElementById('cw-cur-body');
        if (!panel || !tbody) { return; }

        var acc = {};
        body.querySelectorAll('.cw-row').forEach(function (row) {
            if (!row.querySelector('.cw-include').checked) { return; }
            var cid = row.querySelector('.cw-currency').value;
            var info = RATE_MAP[cid] || { code: '', rate_to_myr: 0, bank_charges_myr: 0 };
            var unit = parseFloat(row.querySelector('.cw-cost').value) || 0;
            var count = parseFloat(row.querySelector('.cw-count').value) || 0;
            var perUnit = rowMyrPerUnit(row);
            if (!acc[cid]) {
                acc[cid] = {
                    code: info.code || '',
                    rate: Number(info.rate_to_myr) || 0,
                    bank: Number(info.bank_charges_myr) || 0,
                    foreign: 0,
                    myr: 0
                };
            }
            acc[cid].foreign += unit * count;
            acc[cid].myr += Math.round(perUnit * count * 100) / 100;
        });

        var list = Object.keys(acc).map(function (k) { return acc[k]; });
        list.sort(function (a, b) { return a.code < b.code ? -1 : (a.code > b.code ? 1 : 0); });

        if (!list.length) { panel.style.display = 'none'; tbody.innerHTML = ''; return; }
        panel.style.display = '';

        function num(n, d) { return (Math.round((Number(n) || 0) * Math.pow(10, d)) / Math.pow(10, d)).toFixed(d); }
        tbody.innerHTML = list.map(function (e) {
            return '<tr>' +
                '<td style="text-align:left;">' + (e.code || '?') + '</td>' +
                '<td class="text-right">' + num(e.rate, 4) + '</td>' +
                '<td class="text-right">' + (e.bank > 0 ? 'RM ' + num(e.bank, 2) : '&mdash;') + '</td>' +
                '<td class="text-right">' + (e.code || '?') + ' ' + num(e.foreign, 2) + '</td>' +
                '<td class="text-right">RM ' + num(e.myr, 2) + '</td>' +
                '</tr>';
        }).join('');
    }

    function currencyOptions(selectedId) {
        var opts = '';
        <?php foreach ($currencies as $currency) { ?>
        opts += '<option value="<?php echo (int) $currency['id']; ?>"' + (String(selectedId) === '<?php echo (int) $currency['id']; ?>' ? ' selected' : '') + '><?php echo html_escape($currency['code']); ?></option>';
        <?php } ?>
        return opts;
    }

    // Cost items are added from the master, never free text.
    var MASTER = <?php echo json_encode(array_map(function ($mi) {
        return array(
            'name'            => isset($mi['name']) ? $mi['name'] : '',
            'category'        => isset($mi['category']) ? $mi['category'] : 'miscellaneous',
            'multiplier_type' => isset($mi['multiplier_type']) ? $mi['multiplier_type'] : 'fixed',
            'currency_id'     => (int) (isset($mi['default_currency_id']) ? $mi['default_currency_id'] : 0),
            'unit_price'      => 0,
        );
    }, $item_master)); ?> || [];

    function masterCount(type) {
        if (type === 'per_day') { return DURATION_DAYS; }
        if (type === 'per_pax') { return totalPax(); }
        if (type === 'per_day_pax') { return DURATION_DAYS * totalPax(); }
        return 1;
    }

    // Build the multiplier <select> options for a freshly added row, mirroring the
    // costing_multiplier_types() helper so it stays in one source of truth server-side.
    var MULT_TYPES = <?php echo json_encode($multiplier_type_options); ?> || {};
    function multiplierOptions(selectedType) {
        var opts = '';
        Object.keys(MULT_TYPES).forEach(function (key) {
            opts += '<option value="' + key + '"' + (key === selectedType ? ' selected' : '') + '>' + MULT_TYPES[key] + '</option>';
        });
        return opts;
    }

    // Drop a new row under its category header (creating the header if this is the
    // first row of that category) so the added row lands in the right group.
    function insertIntoCategory(tr, category) {
        if (!CAT_LABELS[category]) { category = 'miscellaneous'; }
        tr.setAttribute('data-cat', category);
        var header = body.querySelector('.cw-cat-row[data-cat="' + category + '"]');
        if (!header) {
            header = document.createElement('tr');
            header.className = 'cw-cat-row';
            header.setAttribute('data-cat', category);
            header.innerHTML = '<td colspan="8">' + CAT_LABELS[category] + '</td>';
            body.appendChild(header);
            body.appendChild(tr);
            return;
        }
        var node = header.nextSibling;
        var lastInGroup = header;
        while (node) {
            if (node.nodeType === 1 && node.classList.contains('cw-cat-row')) { break; }
            if (node.nodeType === 1 && node.classList.contains('cw-row') && node.getAttribute('data-cat') === category) { lastInGroup = node; }
            node = node.nextSibling;
        }
        if (lastInGroup.nextSibling) { body.insertBefore(tr, lastInGroup.nextSibling); }
        else { body.appendChild(tr); }
    }

    // Each category header carries its own item picker; add lands the row in that
    // category and only offers items belonging to it.
    function addRow(sel) {
        if (!sel || sel.value === '') {
            if (typeof Swal !== 'undefined') { Swal.fire({ icon: 'info', title: 'Pick an item', text: 'Select a cost item for this category first.' }); }
            else { alert('Select a cost item for this category first.'); }
            return;
        }
        var item = MASTER[parseInt(sel.value, 10)];
        if (!item) { return; }

        var i = rowSeq++;
        var tr = document.createElement('tr');
        tr.className = 'cw-row';
        tr.innerHTML =
            '<td class="text-center"><input type="hidden" name="rows[' + i + '][include]" value="0" class="cw-include-hidden"><input type="checkbox" class="cw-include" checked></td>' +
            '<td><input type="text" class="form-control" name="rows[' + i + '][name]" value="" readonly>' +
            '<select class="form-control form-control-sm cw-mult-type mt-2" name="rows[' + i + '][multiplier_type]" title="How this cost scales">' + multiplierOptions(item.multiplier_type) + '</select>' +
            '<textarea class="form-control form-control-sm cw-remark mt-2" name="rows[' + i + '][remark]" rows="2" placeholder="Remark (optional)"></textarea>' +
            '<input type="hidden" name="rows[' + i + '][category]" value="miscellaneous">' +
            '<input type="hidden" name="rows[' + i + '][unit_count]" value="1">' +
            '<input type="hidden" name="rows[' + i + '][pax_type]" value="">' +
            '<input type="hidden" class="cw-myr-hidden" name="rows[' + i + '][myr_per_unit]" value="">' +
            '<input type="hidden" class="cw-bank-hidden" name="rows[' + i + '][bank_charges_myr]" value=""></td>' +
            '<td><select class="form-control cw-currency" name="rows[' + i + '][currency_id]">' + currencyOptions(item.currency_id) + '</select></td>' +
            '<td><input type="number" step="0.01" min="0" class="form-control cw-cost" name="rows[' + i + '][unit_price]" value="0"></td>' +
            '<td class="text-right cw-myr"><span class="cw-myr-val">0.00</span><span class="cw-bank-note"></span></td>' +
            '<td><input type="number" step="1" min="0" class="form-control cw-count" name="rows[' + i + '][quantity]" value="1"></td>' +
            '<td class="text-right cw-total">0.00</td>' +
            '<td class="text-center"><button type="button" class="btn btn-icon btn-light-danger btn-sm cw-remove" title="Remove row"><i class="la la-trash"></i></button></td>';

        tr.querySelector('input[name="rows[' + i + '][name]"]').value = item.name;
        tr.querySelector('input[name="rows[' + i + '][category]"]').value = item.category;
        tr.querySelector('.cw-mult-type').value = item.multiplier_type;
        tr.querySelector('.cw-cost').value = item.unit_price;
        tr.querySelector('.cw-count').value = masterCount(item.multiplier_type);

        insertIntoCategory(tr, item.category);

        sel.value = '';
        // Keep Select2 (if active) in sync with the native reset.
        if (window.jQuery && jQuery.fn.select2) { jQuery(sel).trigger('change.select2'); }
        refreshComboPickers();
        recalc();
    }

    body.addEventListener('input', recalc);
    body.addEventListener('change', recalc);
    body.addEventListener('click', function (e) {
        var addBtn = e.target.closest('.cw-cat-add-btn');
        if (addBtn) {
            var cat = addBtn.getAttribute('data-cat');
            addRow(body.querySelector('.cw-cat-add[data-cat="' + cat + '"]'));
            return;
        }
        var btn = e.target.closest('.cw-remove');
        // Keep the (now empty) category header so all sections stay visible.
        if (btn) { var row = btn.closest('.cw-row'); if (row) { row.remove(); refreshComboPickers(); recalc(); } }
    });
    adultInput.addEventListener('input', recalc);
    childInput.addEventListener('input', recalc);
    marginInput.addEventListener('input', recalc);

    /* -------------------------------------------------------------------- *
     *  COMBINATIONS — customer bundles shown on the Quotation PDF.          *
     *  Same per-row cost math as the template above, grouped per bundle.    *
     * -------------------------------------------------------------------- */

    // Combination items are picked ONLY from the internal cost table above — not
    // the whole master. A combo bundle just re-uses rows already costed at the top.
    function topRows() { return Array.prototype.slice.call(body.querySelectorAll('.cw-row')); }

    var topUidSeq = 0;
    function topRowUid(row) {
        if (!row.dataset.uid) { row.dataset.uid = 'tr' + (topUidSeq++); }
        return row.dataset.uid;
    }
    function topRowCat(row) {
        var c = row.getAttribute('data-cat') || 'miscellaneous';
        return CAT_LABELS[c] ? c : 'miscellaneous';
    }
    function topRowName(row) {
        var el = row.querySelector('input[type="text"]');
        return el ? el.value : '';
    }

    // Copy a top cost row's LIVE values so the combo row mirrors what's costed above.
    function readTopRow(row) {
        var mult = row.querySelector('.cw-mult-type');
        var cur = row.querySelector('.cw-currency');
        var cost = row.querySelector('.cw-cost');
        var cnt = row.querySelector('.cw-count');
        var rmk = row.querySelector('.cw-remark');
        var catEl = row.querySelector('input[name*="[category]"]');
        return {
            name: topRowName(row),
            category: catEl ? catEl.value : topRowCat(row),
            multiplier_type: mult ? mult.value : 'fixed',
            currency_id: cur ? cur.value : 0,
            unit_price: cost ? cost.value : 0,
            count: cnt ? cnt.value : '',
            remark: rmk ? rmk.value : ''
        };
    }

    // Fill a combo picker <select> with the current top rows, grouped by category.
    function populateComboPicker(sel) {
        var keep = sel.value;
        sel.innerHTML = '';
        var ph = document.createElement('option');
        ph.value = ''; ph.textContent = '— Add item —';
        sel.appendChild(ph);
        var byCat = {};
        topRows().forEach(function (row) {
            (byCat[topRowCat(row)] = byCat[topRowCat(row)] || []).push(row);
        });
        Object.keys(CAT_LABELS).forEach(function (cat) {
            if (!byCat[cat]) { return; }
            var og = document.createElement('optgroup');
            og.label = CAT_LABELS[cat];
            byCat[cat].forEach(function (row) {
                var o = document.createElement('option');
                o.value = topRowUid(row); o.textContent = topRowName(row) || '(unnamed)';
                og.appendChild(o);
            });
            sel.appendChild(og);
        });
        if (keep && sel.querySelector('option[value="' + keep + '"]')) { sel.value = keep; }
    }

    // One item picker per combination, sourced from the internal cost table.
    function comboItemPicker() {
        var sel = document.createElement('select');
        sel.className = 'form-control form-control-sm cw-combo-pick';
        populateComboPicker(sel);
        return sel;
    }

    // Re-sync every combination's picker after top rows are added/removed.
    function refreshComboPickers() {
        combosWrap.querySelectorAll('.cw-combo-pick').forEach(function (sel) {
            populateComboPicker(sel);
            if (window.jQuery && jQuery.fn.select2 && jQuery(sel).data('select2')) { jQuery(sel).trigger('change.select2'); }
        });
    }

    // Append a cost row to a combination card. `item` supplies the master values.
    function addComboRow(card, item) {
        var c = card.getAttribute('data-c');
        var r = parseInt(card.dataset.rseq, 10) || 0;
        card.dataset.rseq = (r + 1);
        var base = 'combinations[' + c + '][rows][' + r + ']';
        var tr = document.createElement('tr');
        tr.className = 'cw-row cw-crow';
        tr.innerHTML =
            '<td><input type="text" class="form-control" name="' + base + '[name]" value="" readonly>' +
            '<select class="form-control form-control-sm cw-mult-type mt-2" name="' + base + '[multiplier_type]" title="How this cost scales">' + multiplierOptions(item.multiplier_type) + '</select>' +
            '<textarea class="form-control form-control-sm cw-remark mt-2" name="' + base + '[remark]" rows="2" placeholder="Remark (optional)"></textarea>' +
            '<input type="hidden" name="' + base + '[include]" value="1">' +
            '<input type="hidden" name="' + base + '[category]" value="miscellaneous">' +
            '<input type="hidden" name="' + base + '[unit_count]" value="1">' +
            '<input type="hidden" name="' + base + '[pax_type]" value="">' +
            '<input type="hidden" class="cw-myr-hidden" name="' + base + '[myr_per_unit]" value="">' +
            '<input type="hidden" class="cw-bank-hidden" name="' + base + '[bank_charges_myr]" value=""></td>' +
            '<td><select class="form-control cw-currency" name="' + base + '[currency_id]">' + currencyOptions(item.currency_id) + '</select></td>' +
            '<td><input type="number" step="0.01" min="0" class="form-control cw-cost" name="' + base + '[unit_price]" value="0"></td>' +
            '<td class="text-right cw-myr"><span class="cw-myr-val">0.00</span><span class="cw-bank-note"></span></td>' +
            '<td><input type="number" step="1" min="0" class="form-control cw-count" name="' + base + '[quantity]" value="1"></td>' +
            '<td class="text-right cw-total">0.00</td>' +
            '<td class="text-center"><button type="button" class="btn btn-icon btn-light-danger btn-sm cw-remove" title="Remove row"><i class="la la-trash"></i></button></td>';

        tr.querySelector('input[name="' + base + '[name]"]').value = item.name || '';
        tr.querySelector('input[name="' + base + '[category]"]').value = item.category || 'miscellaneous';
        tr.querySelector('.cw-mult-type').value = item.multiplier_type || 'fixed';
        tr.querySelector('.cw-cost').value = (item.unit_price !== undefined ? item.unit_price : 0);
        tr.querySelector('.cw-count').value = (item.count !== undefined && item.count !== null && item.count !== '') ? item.count : masterCount(item.multiplier_type);
        tr.querySelector('.cw-remark').value = item.remark || '';

        card.querySelector('.cw-combo-body').appendChild(tr);
        return tr;
    }

    // Build a combination card (name + its own cost table + item picker + totals).
    function addComboCard(name, items) {
        var c = comboSeq++;
        var card = document.createElement('div');
        card.className = 'cw-combo-card';
        card.setAttribute('data-c', c);
        card.dataset.rseq = '0';
        card.innerHTML =
            '<div class="cw-combo-head">' +
                '<input type="text" class="form-control cw-combo-name" name="combinations[' + c + '][name]" placeholder="Combination name (e.g. Premium)" value="">' +
                '<button type="button" class="btn btn-icon btn-light-danger cw-combo-remove" title="Remove combination"><i class="la la-trash"></i></button>' +
            '</div>' +
            '<div class="table-responsive"><table class="table table-bordered cw-cost-table mb-2">' +
                '<thead><tr>' +
                    '<th style="text-align:left;">Details</th>' +
                    '<th style="width:120px;">Currency</th>' +
                    '<th style="width:120px;">Cost</th>' +
                    '<th style="width:140px;">MYR (convert)</th>' +
                    '<th style="width:120px;">No of Day / Pax</th>' +
                    '<th style="width:140px;">Total in MYR</th>' +
                    '<th style="width:44px;"></th>' +
                '</tr></thead>' +
                '<tbody class="cw-combo-body"></tbody>' +
            '</table></div>' +
            '<div class="d-flex align-items-center" style="gap:8px;"><span class="cw-combo-pick-slot"></span>' +
                '<button type="button" class="btn btn-sm btn-success font-weight-bold cw-combo-add-item"><i class="la la-plus"></i>Add Item</button></div>' +
            '<div class="cw-combo-foot">Cost:&nbsp;<span class="cw-combo-cost">RM 0.00</span> &middot; Selling:&nbsp;<span class="sell cw-combo-sell">RM 0.00</span></div>';

        card.querySelector('.cw-combo-pick-slot').appendChild(comboItemPicker());
        combosWrap.appendChild(card);
        card.querySelector('.cw-combo-name').value = name || '';
        (items || []).forEach(function (it) { addComboRow(card, it); });

        if (window.jQuery && jQuery.fn.select2) {
            jQuery(card).find('.cw-combo-pick').select2({ placeholder: '— Add item —', allowClear: true, width: '260px' });
        }
        return card;
    }

    combosWrap.addEventListener('input', recalc);
    combosWrap.addEventListener('change', recalc);
    combosWrap.addEventListener('click', function (e) {
        var addItem = e.target.closest('.cw-combo-add-item');
        if (addItem) {
            var card = addItem.closest('.cw-combo-card');
            var pick = card.querySelector('.cw-combo-pick');
            if (!pick || pick.value === '') {
                if (typeof Swal !== 'undefined') { Swal.fire({ icon: 'info', title: 'Pick an item', text: 'Select a cost item from the table above.' }); }
                else { alert('Select a cost item from the table above.'); }
                return;
            }
            var srcRow = body.querySelector('.cw-row[data-uid="' + pick.value + '"]');
            if (srcRow) {
                addComboRow(card, readTopRow(srcRow));
                pick.value = '';
                if (window.jQuery && jQuery.fn.select2) { jQuery(pick).trigger('change.select2'); }
                recalc();
            }
            return;
        }
        var rmBtn = e.target.closest('.cw-remove');
        if (rmBtn && rmBtn.closest('.cw-crow')) { rmBtn.closest('.cw-crow').remove(); recalc(); return; }
        var rmCard = e.target.closest('.cw-combo-remove');
        if (rmCard) { var cc = rmCard.closest('.cw-combo-card'); if (cc) { cc.remove(); recalc(); } }
    });

    document.getElementById('cw-combo-add').addEventListener('click', function () { addComboCard('', []); recalc(); });

    // Render saved combinations (edit mode) before the first price pass.
    EXISTING_COMBOS.forEach(function (combo) { addComboCard(combo.name, combo.items); });

    recalc();

    // Each category picker is type-to-search (Select2 ships with the theme).
    if (window.jQuery && jQuery.fn.select2) {
        jQuery('.cw-cat-add').each(function () {
            jQuery(this).select2({
                placeholder: jQuery(this).find('option').first().text(),
                allowClear: true,
                width: '260px'
            });
        });
    }
})();
</script>
<?php } elseif ($active_step === 'itinerary') { ?>
<script>
// Runs on jQuery ready so the TinyMCE bundle (loaded later in the footer) is
// available. Each itinerary day has five rich-text editors; new days are cloned
// from a blank <template> and their editors initialised on the fly.
jQuery(function () {
    var body = document.getElementById('cw-itin-body');
    var addBtn = document.getElementById('cw-itin-add');
    var tpl = document.getElementById('cw-itin-tpl');
    var form = document.getElementById('cw-itin-form');
    if (!body || !addBtn || !tpl || !form) { return; }

    var hasTiny = (typeof window.tinymce !== 'undefined');
    var seq = body.querySelectorAll('.cw-itin-day').length;

    // Shared editor config — text formatting + lists; pasted images embed as
    // base64 (rendered inline by DomPDF). Positioning styles are dropped so
    // Word/PDF pastes don't overlap on the printed quotation.
    var editorConfig = {
        menubar: false,
        height: 70,
        branding: false,
        statusbar: false,
        toolbar: 'undo redo | bold italic underline | forecolor backcolor | bullist numlist | alignleft aligncenter alignright | removeformat',
        plugins: 'lists paste',
        paste_data_images: true,
        paste_webkit_styles: 'none',
        paste_remove_styles_if_webkit: true,
        invalid_styles: { '*': 'position top left right bottom z-index' },
        content_style: 'body{font-size:13px} img{max-width:100%;height:auto}'
    };

    function initEditors(scope) {
        if (!hasTiny) { return; }
        scope.querySelectorAll('.cw-itin-editor').forEach(function (el) {
            tinymce.init(Object.assign({}, editorConfig, { target: el }));
        });
    }

    function removeEditors(scope) {
        if (!hasTiny) { return; }
        scope.querySelectorAll('.cw-itin-editor').forEach(function (el) {
            var ed = tinymce.get(el.id);
            if (ed) { ed.remove(); }
        });
    }

    initEditors(body);

    addBtn.addEventListener('click', function () {
        var html = tpl.innerHTML.replace(/__I__/g, seq++);
        var wrap = document.createElement('div');
        wrap.innerHTML = html.trim();
        var card = wrap.firstElementChild;
        card.querySelector('input[name$="[day_number]"]').value = body.querySelectorAll('.cw-itin-day').length + 1;
        body.appendChild(card);
        initEditors(card);
        if (window.jQuery && jQuery.fn.tooltip) {
            jQuery(card).find('[data-toggle="tooltip"]').tooltip();
        }
    });

    body.addEventListener('click', function (e) {
        var btn = e.target.closest('.cw-itin-remove');
        if (!btn) { return; }
        var card = btn.closest('.cw-itin-day');
        var cards = body.querySelectorAll('.cw-itin-day');
        if (cards.length <= 1) {
            // Keep at least one day — clear it instead of removing.
            card.querySelector('input[name$="[title]"]').value = '';
            if (hasTiny) {
                card.querySelectorAll('.cw-itin-editor').forEach(function (el) {
                    var ed = tinymce.get(el.id);
                    if (ed) { ed.setContent(''); } else { el.value = ''; }
                });
            }
            return;
        }
        removeEditors(card);
        card.remove();
    });

    // Sync every editor back to its textarea before the form posts.
    form.addEventListener('submit', function () {
        if (hasTiny) { tinymce.triggerSave(); }
    });

    if (window.jQuery && jQuery.fn.tooltip) {
        jQuery('[data-toggle="tooltip"]').tooltip();
    }
});
</script>
<?php } ?>
