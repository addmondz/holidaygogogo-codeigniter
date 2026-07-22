<?php
$matrix = isset($lead_reply_hourly_all_matrix) ? $lead_reply_hourly_all_matrix : array(
    'hours' => array(), 'agents' => array(),
    'hour_totals_inbound' => array(), 'hour_totals_outbound' => array(),
    'grand_inbound' => 0, 'grand_outbound' => 0, 'grand_total' => 0, 'max_cell' => 0,
);
$dateLabel = isset($lead_reply_hourly_all_date_label) ? (string) $lead_reply_hourly_all_date_label : '';
$updatedAt = isset($lead_reply_hourly_all_updated_at) ? (string) $lead_reply_hourly_all_updated_at : '';

$hours = isset($matrix['hours']) ? $matrix['hours'] : array();
$agents = isset($matrix['agents']) ? $matrix['agents'] : array();
$maxCell = isset($matrix['max_cell']) ? (int) $matrix['max_cell'] : 0;

// Busiest hour of day across the whole team (inbound + outbound) for the summary line.
$peakHourLabel = '';
$peakHourTotal = 0;
foreach ($hours as $i => $h) {
    $t = (isset($matrix['hour_totals_inbound'][$i]) ? $matrix['hour_totals_inbound'][$i] : 0)
       + (isset($matrix['hour_totals_outbound'][$i]) ? $matrix['hour_totals_outbound'][$i] : 0);
    if ($t > $peakHourTotal) {
        $peakHourTotal = $t;
        $peakHourLabel = $h['label'];
    }
}

// Heatmap cell shading. Inbound = blue, outbound = teal; both scale off the
// busiest single cell so the grid reads as one heatmap.
function reply_hourly_cell_style($count, $maxCell, $rgb)
{
    if ($count > 0 && $maxCell > 0) {
        $alpha = 0.15 + 0.65 * ($count / $maxCell);
        $text = $alpha > 0.55 ? '#fff' : '#0b2a3d';
        return 'background: rgba(' . $rgb . ',' . round($alpha, 2) . '); color:' . $text . '; font-weight:600;';
    }
    return 'color:#c4c9cf;';
}

$dashboardUrl = base_url('Report/Lead_Reply_Activity_Dashboard') . '?reply_date=' . urlencode($dateLabel);
?>
<style>
    #reply-hourly-all-table th, #reply-hourly-all-table td { padding: 0.45rem 0.4rem; }
    #reply-hourly-all-table .col-agent { position: sticky; left: 0; z-index: 2; background: #f3f6f9; min-width: 150px; }
    #reply-hourly-all-table tbody .col-agent { background: #fff; z-index: 1; }
    #reply-hourly-all-table .col-dir { position: sticky; left: 150px; z-index: 2; background: #f3f6f9; min-width: 74px; }
    #reply-hourly-all-table tbody .col-dir { background: #fff; z-index: 1; }
    #reply-hourly-all-table .dir-in { color: #2f6fed; font-weight: 700; }
    #reply-hourly-all-table .dir-out { color: #1bc5bd; font-weight: 700; }
    #reply-hourly-all-table .col-total { background: #f3f6f9; min-width: 66px; }
    .reply-hourly-legend-dot { display:inline-block; width:12px; height:12px; border-radius:3px; vertical-align:middle; margin-right:6px; }
</style>

<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-4" style="background:linear-gradient(135deg, #e4edf5 0%, #f5f8fb 100%);">
                <div class="card-title">
                    <div>
                        <h3 class="card-label mb-1" style="color:#2f506f;">
                            <strong>Lead Reply Hourly &mdash; All Agents</strong>
                        </h3>
                        <div class="text-muted font-size-sm">
                            Inbound and outbound messages per hour for every agent on
                            <span class="font-weight-bold text-dark"><?php echo html_escape($dateLabel); ?></span>.
                            Darker cells are busier hours.
                        </div>
                    </div>
                </div>
                <div class="card-toolbar">
                    <a href="<?php echo html_escape($dashboardUrl); ?>" class="btn btn-light-primary font-weight-bold mr-3">
                        <i class="la la-arrow-left"></i> Back to Dashboard
                    </a>
                    <?php if ($peakHourLabel !== '' && $peakHourTotal > 0) { ?>
                        <span class="label label-light-warning label-inline font-weight-bold mr-2">
                            Busiest hour: <?php echo html_escape($peakHourLabel); ?> (<?php echo number_format($peakHourTotal); ?>)
                        </span>
                    <?php } ?>
                    <span class="label label-light-primary label-inline font-weight-bold">
                        <?php echo number_format($matrix['grand_total']); ?> messages
                    </span>
                </div>
            </div>
            <div class="card-body">
                <form id="reply-hourly-all-form" action="<?php echo base_url('Report/Lead_Reply_Activity_Hourly_All'); ?>" method="get" class="form mb-6">
                    <div class="row align-items-end">
                        <div class="col-md-4">
                            <div class="form-group mb-0">
                                <label>Date</label>
                                <div id="reply_hourly_all_daterangepicker" class="input-icon">
                                    <input readonly type="text" name="reply_date" value="<?php echo html_escape($dateLabel); ?>" autocomplete="off" class="form-control">
                                    <span><i class="la la-calendar"></i></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <input type="submit" value="Filter" class="btn btn-light-success font-weight-bold" style="width:90px;">
                            <span class="ml-3 font-size-sm text-muted">
                                <span class="reply-hourly-legend-dot" style="background:#2f6fed;"></span>Inbound
                                <span class="reply-hourly-legend-dot ml-3" style="background:#1bc5bd;"></span>Outbound
                            </span>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-head-custom" id="reply-hourly-all-table" style="font-size:0.9rem;">
                        <thead>
                            <tr>
                                <th class="col-agent">Agent</th>
                                <th class="col-dir text-center"></th>
                                <?php foreach ($hours as $hour) { ?>
                                    <th class="text-center text-nowrap"><?php echo html_escape($hour['label']); ?></th>
                                <?php } ?>
                                <th class="col-total text-center">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($agents)) { ?>
                                <tr>
                                    <td colspan="27" class="text-center py-10">No inbound or outbound messages found for any agent on the selected day.</td>
                                </tr>
                            <?php } else { ?>
                                <?php foreach ($agents as $agent) { ?>
                                    <tr>
                                        <td class="col-agent font-weight-bold text-dark text-nowrap" rowspan="2"><?php echo html_escape($agent['owner_name']); ?></td>
                                        <td class="col-dir text-center dir-in">In</td>
                                        <?php foreach ($agent['inbound'] as $count) { ?>
                                            <td class="text-center" style="<?php echo reply_hourly_cell_style($count, $maxCell, '47,111,237'); ?>"><?php echo $count > 0 ? number_format($count) : '&middot;'; ?></td>
                                        <?php } ?>
                                        <td class="col-total text-center font-weight-bold" style="color:#2f6fed;"><?php echo number_format($agent['total_inbound']); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="col-dir text-center dir-out">Out</td>
                                        <?php foreach ($agent['outbound'] as $count) { ?>
                                            <td class="text-center" style="<?php echo reply_hourly_cell_style($count, $maxCell, '27,197,189'); ?>"><?php echo $count > 0 ? number_format($count) : '&middot;'; ?></td>
                                        <?php } ?>
                                        <td class="col-total text-center font-weight-bold" style="color:#1bc5bd;"><?php echo number_format($agent['total_outbound']); ?></td>
                                    </tr>
                                <?php } ?>
                            <?php } ?>
                        </tbody>
                        <?php if (!empty($agents)) { ?>
                            <tfoot>
                                <tr style="background:#eef2f7;">
                                    <th class="col-agent" style="background:#eef2f7;" rowspan="2">TOTAL</th>
                                    <th class="col-dir text-center dir-in" style="background:#eef2f7;">In</th>
                                    <?php foreach ($matrix['hour_totals_inbound'] as $t) { ?>
                                        <th class="text-center" style="color:#2f6fed;"><?php echo $t > 0 ? number_format($t) : '&middot;'; ?></th>
                                    <?php } ?>
                                    <th class="col-total text-center" style="color:#2f6fed;"><?php echo number_format($matrix['grand_inbound']); ?></th>
                                </tr>
                                <tr style="background:#eef2f7;">
                                    <th class="col-dir text-center dir-out" style="background:#eef2f7;">Out</th>
                                    <?php foreach ($matrix['hour_totals_outbound'] as $t) { ?>
                                        <th class="text-center" style="color:#1bc5bd;"><?php echo $t > 0 ? number_format($t) : '&middot;'; ?></th>
                                    <?php } ?>
                                    <th class="col-total text-center" style="color:#1bc5bd;"><?php echo number_format($matrix['grand_outbound']); ?></th>
                                </tr>
                            </tfoot>
                        <?php } ?>
                    </table>
                </div>

                <?php if ($updatedAt !== '') { ?>
                    <div class="text-muted font-size-sm mt-3">
                        Generated at <?php echo html_escape($updatedAt); ?>.
                        Inbound = every customer message on the agent's leads; Outbound = the agent's own replies, counted while they held the lead.
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<script>
    $('#reply_hourly_all_daterangepicker').daterangepicker({
        buttonClasses: ' btn',
        applyClass: 'btn-primary',
        cancelClass: 'btn-secondary',
        singleDatePicker: true,
        autoApply: true
    }, function(start) {
        $('#reply_hourly_all_daterangepicker .form-control').val(start.format('DD/MM/YYYY'));
    });
</script>
