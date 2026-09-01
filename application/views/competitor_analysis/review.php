<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong>Review Crawled Products</strong>
                        <span class="text-muted font-weight-normal" style="font-size:12px;">&nbsp; <?php echo count($products); ?> found</span>
                    </h3>
                </div>
                <div class="card-toolbar">
                    <a href="<?php echo base_url('Competitor_Analysis'); ?>" class="btn btn-secondary font-weight-bold mr-3"><i class="la la-arrow-left"></i> Back</a>
                    <button type="button" id="analyse_selected" class="btn btn-primary font-weight-bold" <?php echo empty($has_ai) ? 'disabled title="AI analysis is currently unavailable"' : ''; ?>>
                        <i class="la la-robot"></i> Analyse Selected (<span id="sel_count">0</span>)
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($has_ai)) { ?>
                    <div class="alert alert-light-warning" style="font-size:12px;">AI analysis is currently unavailable. You can still review the crawled products below.</div>
                <?php } ?>
                <div style="overflow-x:auto;">
                    <table class="table table-bordered table-head-custom" style="font-size:13px;">
                        <thead>
                            <tr>
                                <th style="width:40px; text-align:center;"><input type="checkbox" id="sel_all"></th>
                                <th style="text-align:center;">No.</th>
                                <th>Product</th>
                                <th style="text-align:center;">AI Cost (USD)</th>
                                <th style="text-align:center;">Analysed On</th>
                                <th style="text-align:center;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)) { ?>
                                <tr><td colspan="6" style="text-align:center; padding:12px;">No products crawled</td></tr>
                            <?php } else { $n = 1; foreach ($products as $p) {
                                $analysed = ((int) $p['analysis_id'] > 0);
                            ?>
                                <tr<?php echo $analysed ? ' style="background:#f6fbf7;"' : ''; ?>>
                                    <td style="text-align:center;"><input type="checkbox" class="sel-item" value="<?php echo (int) $p['i']; ?>" <?php echo $p['chars'] > 0 ? '' : 'disabled'; ?>></td>
                                    <td style="text-align:center; padding:10px 8px;"><?php echo $n; ?></td>
                                    <td>
                                        <strong style="font-size:13px;"><?php echo htmlspecialchars($p['title']); ?></strong>
                                        <?php if ( ! empty($p['duration'])) { ?><span class="label label-light-info label-inline ml-1" style="font-size:10px;"><?php echo htmlspecialchars($p['duration']); ?></span><?php } ?>
                                        <?php echo $p['chars'] > 0 ? '' : ' <span class="label label-light-danger label-inline" style="font-size:10px;">empty</span>'; ?>
                                        <?php if ( ! empty($p['snippet'])) { ?><div class="text-muted" style="font-size:11px; margin-top:2px;"><?php echo htmlspecialchars($p['snippet']); ?></div><?php } ?>
                                        <?php if ( ! empty($p['url'])) { ?><div style="margin-top:2px;"><a href="<?php echo htmlspecialchars($p['url']); ?>" target="_blank" rel="noopener" class="text-muted" style="font-size:11px; word-break:break-all;"><i class="la la-external-link-alt mr-1"></i><?php echo htmlspecialchars($p['url']); ?></a></div><?php } ?>
                                    </td>
                                    <td style="text-align:center; font-size:12px;"><?php echo $analysed && $p['cost'] > 0 ? number_format((float) $p['cost'], 4) : '—'; ?></td>
                                    <td style="text-align:center; font-size:12px;">
                                        <?php if ($analysed && ! empty($p['analysed_at'])) { ?>
                                            <span class="label label-light-success label-inline font-weight-bold" style="font-size:11px;"><i class="la la-check mr-1"></i><?php echo htmlspecialchars(date('d M Y, g:i A', strtotime($p['analysed_at']))); ?></span>
                                        <?php } else { ?>
                                            <span class="text-muted" style="font-size:11px;">—</span>
                                        <?php } ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <?php if ($analysed) { ?>
                                            <a href="<?php echo base_url('Competitor_Analysis/View?id=') . (int) $p['analysis_id']; ?>" class="btn btn-light-success btn-sm font-weight-bold" style="font-size:11px;"><i class="la la-search mr-1"></i>View</a>
                                        <?php } else { ?>
                                            <span class="text-muted" style="font-size:11px;">—</span>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php $n++; } } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    var CA_IMG = '<?php echo base_url('assets/image/sweetalert.jpg') ?>';

    function refreshSel() {
        $('#sel_count').text($('.sel-item:checked').length);
    }
    $('#sel_all').on('change', function() {
        $('.sel-item').prop('checked', this.checked);
        refreshSel();
    });
    $(document).on('change', '.sel-item', refreshSel);
    $(document).ready(refreshSel);

    var STATE_URL = '<?php echo base_url('Competitor_Analysis/Job_State') ?>?job=';

    function pollAnalyse(analyseJob) {
        var t = setInterval(function() {
            $.getJSON(STATE_URL + encodeURIComponent(analyseJob)).done(function(res) {
                if (!res) return;
                if (res.state === 'done' || res.state === 'error' || res.state === 'unknown') {
                    clearInterval(t);
                    Swal.close();
                    // Reload the Review page — analysed products now show View + cost.
                    window.location.reload();
                } else {
                    Swal.update({ title: res.message || 'Analysing…' });
                    Swal.showLoading();
                }
            });
        }, 2500);
    }

    $('#analyse_selected').click(function() {
        var indices = $('.sel-item:checked').map(function() { return $(this).val(); }).get();
        if (!indices.length) { Display_Message(CA_IMG, 'Select at least one product', null); return; }
        var $btn = $(this), html = $btn.html();
        $btn.prop('disabled', true).html('<i class="la la-spinner la-spin"></i> Starting…');
        Swal.fire({ background: 'url(' + CA_IMG + ')', title: 'Analysing…', allowOutsideClick: false, didOpen: function() { Swal.showLoading(); } });
        $.ajax({
            url: '<?php echo base_url('Competitor_Analysis/Analyze_Selected') ?>',
            type: 'post', dataType: 'json',
            data: { job: '<?php echo $job; ?>', indices: indices },
            success: function(res) {
                if (res && res.success && res.job) {
                    pollAnalyse(res.job);
                } else {
                    Swal.close();
                    $btn.prop('disabled', false).html(html);
                    Display_Message(CA_IMG, (res && res.message) ? res.message : 'Could not start analysis', null);
                }
            },
            error: function() {
                Swal.close();
                $btn.prop('disabled', false).html(html);
                Display_Message(CA_IMG, 'Could not start analysis. Please try again', null);
            }
        });
    });
</script>
