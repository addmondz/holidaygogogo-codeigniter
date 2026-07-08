<?php
$matrix = isset($leads_by_hour_matrix) ? $leads_by_hour_matrix : array('hours' => array(), 'rows' => array(), 'hour_totals' => array(), 'grand_total' => 0);
$date_label = isset($leads_by_hour_date_label) ? $leads_by_hour_date_label : '';
$updated_at = isset($leads_by_hour_updated_at) ? $leads_by_hour_updated_at : '';

// Peak scaling: the busiest single cell sets the deepest shade so the grid
// reads like a heatmap of when leads get picked up.
$max_cell = 0;
foreach ($matrix['rows'] as $r) {
    foreach ($r['counts'] as $c) {
        if ($c > $max_cell) { $max_cell = $c; }
    }
}

// Busiest hour of day across the whole range (for the summary line).
$peak_hour_label = '';
$peak_hour_total = 0;
foreach ($matrix['hour_totals'] as $i => $t) {
    if ($t > $peak_hour_total) {
        $peak_hour_total = $t;
        $peak_hour_label = $matrix['hours'][$i]['label'];
    }
}

$export_url = base_url('Report/Leads_By_Hour_Export?lead_date=') . urlencode($date_label);
?>
<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-4" style="background:linear-gradient(135deg, #d7e2f2 0%, #eef4fb 100%);">
                <div class="card-title">
                    <div>
                        <h3 class="card-label mb-1" style="color:#355c7d;">
                            <strong>Leads By Hour</strong>
                        </h3>
                        <div class="text-muted font-size-sm">
                            What time of day new leads get picked up &mdash; each lead counted at the hour its owner was assigned.
                        </div>
                    </div>
                </div>
                <div class="card-toolbar">
                    <?php if ($peak_hour_label !== '' && $peak_hour_total > 0) { ?>
                        <span class="label label-light-warning label-inline font-weight-bold mr-2">
                            Busiest hour: <?php echo html_escape($peak_hour_label); ?> (<?php echo number_format($peak_hour_total); ?>)
                        </span>
                    <?php } ?>
                    <span class="label label-light-primary label-inline font-weight-bold">
                        <?php echo number_format($matrix['grand_total']); ?> leads
                    </span>
                </div>
            </div>
            <div class="card-body">
                <form id="leads-by-hour-form" action="<?php echo base_url('Report/Leads_By_Hour'); ?>" method="get" class="form mb-6">
                    <div class="row align-items-end">
                        <div class="col-md-4">
                            <div class="form-group mb-0">
                                <label>Date Range</label>
                                <div id="leads_by_hour_daterangepicker" class="input-icon">
                                    <input readonly type="text" name="lead_date" value="<?php echo html_escape($date_label); ?>" autocomplete="off" class="form-control">
                                    <span><i class="la la-calendar"></i></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <input type="submit" value="Filter" class="btn btn-light-success font-weight-bold" style="width:90px;">
                            <input type="button" id="leads-by-hour-reset" value="Reset" class="btn btn-light-primary font-weight-bold" style="width:90px;">
                            <a href="<?php echo $export_url; ?>" class="btn btn-light-info font-weight-bold float-right">
                                <i class="la la-download"></i> Export Excel
                            </a>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-head-custom" style="font-size:0.95rem;">
                        <thead>
                            <tr>
                                <th style="min-width:150px; position:sticky; left:0; z-index:2; background:#f3f6f9;">Date</th>
                                <?php foreach ($matrix['hours'] as $hour) { ?>
                                    <th class="text-center text-nowrap" style="padding:0.5rem 0.4rem;"><?php echo html_escape($hour['label']); ?></th>
                                <?php } ?>
                                <th class="text-center" style="min-width:70px; background:#f3f6f9;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($matrix['rows'])) { ?>
                                <tr>
                                    <td colspan="26" class="text-center py-10">No leads found for the selected range.</td>
                                </tr>
                            <?php } else { ?>
                                <?php foreach ($matrix['rows'] as $r) { ?>
                                    <tr>
                                        <td class="text-nowrap font-weight-bold" style="position:sticky; left:0; z-index:1; background:#fff;"><?php echo html_escape($r['date_label']); ?></td>
                                        <?php foreach ($r['counts'] as $count) {
                                            if ($count > 0 && $max_cell > 0) {
                                                $alpha = 0.15 + 0.65 * ($count / $max_cell);
                                                $style = 'background: rgba(53,92,125,' . round($alpha, 2) . '); color:' . ($alpha > 0.55 ? '#fff' : '#0b2a3d') . '; font-weight:600;';
                                            } else {
                                                $style = 'color:#c4c9cf;';
                                            }
                                        ?>
                                            <td class="text-center" style="padding:0.5rem 0.4rem; <?php echo $style; ?>"><?php echo $count > 0 ? number_format($count) : '&middot;'; ?></td>
                                        <?php } ?>
                                        <td class="text-center font-weight-bold" style="background:#f3f6f9;"><?php echo number_format($r['total']); ?></td>
                                    </tr>
                                <?php } ?>
                            <?php } ?>
                        </tbody>
                        <?php if (!empty($matrix['rows'])) { ?>
                            <tfoot>
                                <tr style="background:#eef4fb;">
                                    <th class="text-nowrap" style="position:sticky; left:0; z-index:1; background:#eef4fb;">TOTAL</th>
                                    <?php foreach ($matrix['hour_totals'] as $t) { ?>
                                        <th class="text-center" style="padding:0.5rem 0.4rem;"><?php echo $t > 0 ? number_format($t) : '&middot;'; ?></th>
                                    <?php } ?>
                                    <th class="text-center"><?php echo number_format($matrix['grand_total']); ?></th>
                                </tr>
                            </tfoot>
                        <?php } ?>
                    </table>
                </div>

                <?php if ($updated_at !== '') { ?>
                    <div class="text-muted font-size-sm mt-3">Generated at <?php echo html_escape($updated_at); ?>. Counted by the hour each lead was picked up (assigned owner).</div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<script>
    $('#leads_by_hour_daterangepicker').daterangepicker({
        buttonClasses: ' btn',
        applyClass: 'btn-primary',
        cancelClass: 'btn-secondary',
        autoApply: true
    }, function(start, end) {
        $('#leads_by_hour_daterangepicker .form-control').val(start.format('DD/MM/YYYY') + ' - ' + end.format('DD/MM/YYYY'));
    });

    $('#leads-by-hour-reset').on('click', function() {
        window.location.href = '<?php echo base_url('Report/Leads_By_Hour'); ?>';
    });
</script>
