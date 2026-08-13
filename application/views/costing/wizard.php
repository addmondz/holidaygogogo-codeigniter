<?php
$package = isset($package) ? $package : array();
$booking = isset($booking) ? $booking : array();
$booking_items = isset($booking_items) ? $booking_items : array();
$item_master = isset($item_master) ? $item_master : array();
$currencies = isset($currencies) ? $currencies : array();
$currency_rate_map = isset($currency_rate_map) ? $currency_rate_map : array();
$financials = isset($financials) ? $financials : array();
$itinerary_days = isset($itinerary_days) ? $itinerary_days : array();
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
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Package Name</label>
                            <input type="text" name="name" class="form-control" required value="<?php echo html_escape($package['name']); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Tour Code</label>
                            <input type="text" name="tour_code" class="form-control" value="<?php echo html_escape(isset($package['tour_code']) ? $package['tour_code'] : ''); ?>">
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
                            $cat_labels = costing_categories();
                            $grouped = array();
                            foreach ($cost_rows as $idx => $row) {
                                $cat = isset($row['category']) ? $row['category'] : 'miscellaneous';
                                if (!isset($cat_labels[$cat])) { $cat = 'miscellaneous'; }
                                $grouped[$cat][] = array('idx' => $idx, 'row' => $row);
                            }
                            // Categories that have at least one master item to offer.
                            $master_cats = array();
                            foreach ($item_master as $mi) {
                                $mc = isset($mi['category']) ? $mi['category'] : 'miscellaneous';
                                if (!isset($cat_labels[$mc])) { $mc = 'miscellaneous'; }
                                $master_cats[$mc] = true;
                            }
                            // Only render a category when it has master items to add or
                            // existing rows to show — hide otherwise-empty sections.
                            foreach (array_keys($cat_labels) as $cat) {
                                if (empty($master_cats[$cat]) && empty($grouped[$cat])) { continue; }
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
                                        <input type="hidden" name="rows[<?php echo $idx; ?>][category]" value="<?php echo html_escape($cat); ?>">
                                        <input type="hidden" name="rows[<?php echo $idx; ?>][unit_count]" value="1">
                                        <input type="hidden" name="rows[<?php echo $idx; ?>][pax_type]" value="">
                                        <input type="hidden" class="cw-mult-type" name="rows[<?php echo $idx; ?>][multiplier_type]" value="<?php echo html_escape($row['multiplier_type']); ?>">
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
                <div class="d-flex flex-wrap align-items-center" style="gap:8px;">
                    <a href="<?php echo base_url('Costing_Item'); ?>" target="_blank" class="btn btn-light font-weight-bold" title="Manage item master">Manage Items</a>
                </div>
                <?php if (empty($item_master)) { ?>
                    <div class="text-muted mt-2">No items in the master yet — add them under <a href="<?php echo base_url('Costing_Item'); ?>" target="_blank">Costing Item</a>.</div>
                <?php } ?>

                <div class="row mt-5">
                    <div class="col-md-4">
                        <div class="form-group mb-0">
                            <label>Margin (Markup on Cost %)</label>
                            <input type="number" step="0.01" min="0" id="cw-margin" name="margin_percentage" class="form-control" value="<?php echo html_escape($margin_percentage); ?>">
                            <span class="form-text text-muted">Selling = Cost × (1 + margin%). No manual selling price.</span>
                        </div>
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
                $itin_rows[] = array('day_number' => $d, 'title' => '', 'description' => '');
            }
        }
        ?>
        <form method="post" action="<?php echo base_url('Costing/Save_Itinerary'); ?>">
            <input type="hidden" name="package_id" value="<?php echo $package_id; ?>">
            <div class="cw-panel">
                <div class="cw-panel-title">Itinerary</div>
                <div class="cw-panel-sub">Per-day plan shown on the customer Quotation PDF.</div>
                <div class="table-responsive">
                    <table class="table table-bordered" id="cw-itin-table">
                        <thead>
                            <tr>
                                <th style="width:90px; text-align:center;">Day</th>
                                <th style="width:26%;">Title</th>
                                <th>Description</th>
                                <th style="width:44px;"></th>
                            </tr>
                        </thead>
                        <tbody id="cw-itin-body">
                            <?php foreach ($itin_rows as $i => $day) { ?>
                                <tr>
                                    <td><input type="number" min="1" class="form-control" name="itinerary[<?php echo $i; ?>][day_number]" value="<?php echo (int) (isset($day['day_number']) ? $day['day_number'] : $i + 1); ?>"></td>
                                    <td><input type="text" class="form-control" name="itinerary[<?php echo $i; ?>][title]" value="<?php echo html_escape(isset($day['title']) ? $day['title'] : ''); ?>" placeholder="e.g. Arrival &amp; City Tour"></td>
                                    <td><textarea rows="2" class="form-control" name="itinerary[<?php echo $i; ?>][description]" placeholder="What happens on this day"><?php echo html_escape(isset($day['description']) ? $day['description'] : ''); ?></textarea></td>
                                    <td class="text-center"><button type="button" class="btn btn-icon btn-light-danger btn-sm cw-itin-remove" title="Remove day"><i class="la la-trash"></i></button></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
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
        return null; // fixed / custom -> leave user value
    }

    function recalc() {
        totalPaxInput.value = totalPax();
        var grandCost = 0;
        body.querySelectorAll('.cw-row').forEach(function (row) {
            var included = row.querySelector('.cw-include').checked;
            row.querySelector('.cw-include-hidden').value = included ? '1' : '0';

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
            if (included) { grandCost += total; }
        });

        var pax = totalPax() || 1;
        var margin = parseFloat(marginInput.value) || 0;
        if (margin < 0) { margin = 0; }
        var costPax = grandCost / pax;
        var sellPax = Math.round(costPax * (1 + margin / 100) * 100) / 100;
        var revenue = Math.round(sellPax * pax * 100) / 100;
        var profit = Math.round((revenue - grandCost) * 100) / 100;

        document.getElementById('cw-sum-cost').textContent = money(grandCost);
        document.getElementById('cw-sum-costpax').textContent = money(costPax);
        document.getElementById('cw-sum-sellpax').textContent = money(sellPax);
        document.getElementById('cw-sum-revenue').textContent = money(revenue);
        document.getElementById('cw-sum-profit').textContent = money(profit);
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
        return 1;
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
            '<input type="hidden" name="rows[' + i + '][category]" value="miscellaneous">' +
            '<input type="hidden" name="rows[' + i + '][unit_count]" value="1">' +
            '<input type="hidden" name="rows[' + i + '][pax_type]" value="">' +
            '<input type="hidden" class="cw-mult-type" name="rows[' + i + '][multiplier_type]" value="fixed">' +
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
        if (btn) { var row = btn.closest('.cw-row'); if (row) { row.remove(); recalc(); } }
    });
    adultInput.addEventListener('input', recalc);
    childInput.addEventListener('input', recalc);
    marginInput.addEventListener('input', recalc);

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
(function () {
    var body = document.getElementById('cw-itin-body');
    var addBtn = document.getElementById('cw-itin-add');
    var seq = body.querySelectorAll('tr').length;

    addBtn.addEventListener('click', function () {
        var i = seq++;
        var day = body.querySelectorAll('tr').length + 1;
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td><input type="number" min="1" class="form-control" name="itinerary[' + i + '][day_number]" value="' + day + '"></td>' +
            '<td><input type="text" class="form-control" name="itinerary[' + i + '][title]" placeholder="Day title"></td>' +
            '<td><textarea rows="2" class="form-control" name="itinerary[' + i + '][description]" placeholder="What happens on this day"></textarea></td>' +
            '<td class="text-center"><button type="button" class="btn btn-icon btn-light-danger btn-sm cw-itin-remove" title="Remove day"><i class="la la-trash"></i></button></td>';
        body.appendChild(tr);
    });

    body.addEventListener('click', function (e) {
        var btn = e.target.closest('.cw-itin-remove');
        if (!btn) { return; }
        var rows = body.querySelectorAll('tr');
        if (rows.length <= 1) {
            var only = btn.closest('tr');
            only.querySelectorAll('input[type=text], textarea').forEach(function (el) { el.value = ''; });
            return;
        }
        btn.closest('tr').remove();
    });
})();
</script>
<?php } ?>
