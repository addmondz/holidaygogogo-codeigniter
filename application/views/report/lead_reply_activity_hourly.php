<?php
$breakdown = isset($lead_reply_hourly_breakdown) ? $lead_reply_hourly_breakdown : array('hours' => array(), 'total_inbound' => 0, 'total_outbound' => 0, 'total' => 0);
$avgReplyLabel = isset($lead_reply_hourly_avg_reply_label) ? trim((string) $lead_reply_hourly_avg_reply_label) : '';
$hours = isset($breakdown['hours']) ? $breakdown['hours'] : array();
$peak = 0;
foreach ($hours as $h) {
    if ($h['total'] > $peak) {
        $peak = $h['total'];
    }
}
?>
<style>
    .reply-hourly-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(160px, 1fr));
        gap: 12px;
    }

    .reply-hourly-metric {
        border: 1px solid #e5e9f2;
        border-radius: 6px;
        padding: 14px 16px;
        background: #fff;
    }

    .reply-hourly-metric .label-text {
        color: #7e8299;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .reply-hourly-metric .value-text {
        font-size: 26px;
        font-weight: 700;
        line-height: 1.2;
        margin-top: 4px;
        color: #263238;
    }

    .reply-hourly-metric.response .value-text { color: #ff7b39; }
    .reply-hourly-metric.inbound .value-text { color: #2f6fed; }
    .reply-hourly-metric.outbound .value-text { color: #1bc5bd; }

    .reply-hourly-bar-wrap {
        display: flex;
        align-items: center;
        gap: 4px;
        min-width: 160px;
    }

    .reply-hourly-bar {
        height: 14px;
        border-radius: 3px;
    }

    .reply-hourly-bar.inbound { background: #2f6fed; }
    .reply-hourly-bar.outbound { background: #1bc5bd; }

    .reply-hourly-legend-dot {
        display: inline-block;
        width: 12px;
        height: 12px;
        border-radius: 3px;
        vertical-align: middle;
        margin-right: 6px;
    }

    #lead-reply-hourly-table tbody tr.is-empty-hour td {
        color: #b5b5c3;
    }

    @media (max-width: 575px) {
        .reply-hourly-summary {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-4" style="background:linear-gradient(135deg, #e4edf5 0%, #f5f8fb 100%);">
                <div class="card-title">
                    <div>
                        <h3 class="card-label mb-1" style="color:#2f506f;">
                            <strong>Lead Reply Hourly</strong>
                        </h3>
                        <div class="text-muted font-size-sm">
                            Inbound and outbound messages per hour for
                            <span class="font-weight-bold text-dark"><?php echo html_escape($lead_reply_hourly_owner_name); ?></span>
                            on <span class="font-weight-bold text-dark"><?php echo html_escape($lead_reply_hourly_date_label); ?></span>.
                        </div>
                    </div>
                </div>
                <div class="card-toolbar">
                    <a href="<?php echo base_url('Report/Lead_Reply_Activity_Dashboard'); ?>" class="btn btn-light-primary font-weight-bold mr-3">
                        <i class="la la-arrow-left"></i> Back to Dashboard
                    </a>
                    <span class="label label-light-primary label-inline font-weight-bold">
                        Updated <?php echo !empty($lead_reply_hourly_updated_at) ? html_escape($lead_reply_hourly_updated_at) : 'Not available'; ?>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <div class="reply-hourly-summary">
                    <div class="reply-hourly-metric response">
                        <div class="label-text" data-toggle="tooltip" title="Average time this owner took to reply to an inbound customer message, counting in-hours (7AM-10PM) same-day replies only.">Avg Response Time</div>
                        <div class="value-text"><?php echo $avgReplyLabel !== '' ? html_escape($avgReplyLabel) : '&mdash;'; ?></div>
                    </div>
                    <div class="reply-hourly-metric">
                        <div class="label-text">Total Messages</div>
                        <div class="value-text"><?php echo number_format($breakdown['total']); ?></div>
                    </div>
                    <div class="reply-hourly-metric inbound">
                        <div class="label-text"><span class="reply-hourly-legend-dot" style="background:#2f6fed;"></span>Inbound</div>
                        <div class="value-text"><?php echo number_format($breakdown['total_inbound']); ?></div>
                    </div>
                    <div class="reply-hourly-metric outbound">
                        <div class="label-text"><span class="reply-hourly-legend-dot" style="background:#1bc5bd;"></span>Outbound</div>
                        <div class="value-text"><?php echo number_format($breakdown['total_outbound']); ?></div>
                    </div>
                </div>

                <div class="table-responsive mt-8">
                    <table class="table table-bordered table-head-custom table-checkable" id="lead-reply-hourly-table">
                        <thead>
                            <tr>
                                <th style="width:120px;">Hour</th>
                                <th style="text-align:center; width:120px;">
                                    <span class="reply-hourly-legend-dot" style="background:#2f6fed;"></span>Inbound
                                </th>
                                <th style="text-align:center; width:120px;">
                                    <span class="reply-hourly-legend-dot" style="background:#1bc5bd;"></span>Outbound
                                </th>
                                <th style="text-align:center; width:100px;">Total</th>
                                <th>Activity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($breakdown['total'] === 0) { ?>
                                <tr>
                                    <td colspan="5" class="text-center py-10">No inbound or outbound messages found for this owner on the selected day.</td>
                                </tr>
                            <?php } else { ?>
                                <?php foreach($hours as $hour) { ?>
                                    <?php
                                        $inboundWidth = $peak > 0 ? round(($hour['inbound'] / $peak) * 100) : 0;
                                        $outboundWidth = $peak > 0 ? round(($hour['outbound'] / $peak) * 100) : 0;
                                    ?>
                                    <tr class="<?php echo $hour['total'] === 0 ? 'is-empty-hour' : ''; ?>">
                                        <td class="font-weight-bold" title="<?php echo html_escape($hour['range_label']); ?>"><?php echo html_escape($hour['label']); ?></td>
                                        <td class="text-center"><?php echo number_format($hour['inbound']); ?></td>
                                        <td class="text-center"><?php echo number_format($hour['outbound']); ?></td>
                                        <td class="text-center font-weight-bold text-dark"><?php echo number_format($hour['total']); ?></td>
                                        <td>
                                            <?php if($hour['total'] > 0) { ?>
                                                <div class="reply-hourly-bar-wrap">
                                                    <div class="reply-hourly-bar inbound" style="width:<?php echo max($inboundWidth, $hour['inbound'] > 0 ? 4 : 0); ?>%;" title="Inbound: <?php echo $hour['inbound']; ?>"></div>
                                                    <div class="reply-hourly-bar outbound" style="width:<?php echo max($outboundWidth, $hour['outbound'] > 0 ? 4 : 0); ?>%;" title="Outbound: <?php echo $hour['outbound']; ?>"></div>
                                                </div>
                                            <?php } else { ?>
                                                <span class="text-muted font-size-sm">&mdash;</span>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $('[data-toggle="tooltip"]').tooltip({ container: 'body', boundary: 'viewport', trigger: 'hover' });
</script>
