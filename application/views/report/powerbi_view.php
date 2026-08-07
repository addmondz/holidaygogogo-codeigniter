<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong><?php echo htmlspecialchars(!empty($report_name) ? $report_name : 'Power BI Report', ENT_QUOTES, 'UTF-8'); ?></strong>
                        <?php if (!empty($access_level) && $access_level === 'Edit') { ?>
                            <span class="label label-inline label-light-success font-weight-bold ml-3">Edit</span>
                        <?php } else { ?>
                            <span class="label label-inline label-light-primary font-weight-bold ml-3">View</span>
                        <?php } ?>
                    </h3>
                </div>
                <div class="card-toolbar">
                    <a href="<?php echo base_url('Report/PowerBI_Reports'); ?>" class="btn btn-sm btn-light-primary font-weight-bold mr-2">
                        All Reports
                    </a>
                    <?php if (empty($error) && !empty($report_id)) { ?>
                        <?php if (!empty($access_level) && $access_level === 'Edit') { ?>
                            <a href="<?php echo base_url('Report/PowerBI_View/' . rawurlencode($report_id)); ?>" class="btn btn-sm btn-primary font-weight-bold">
                                Switch to View
                            </a>
                        <?php } else { ?>
                            <a href="<?php echo base_url('Report/PowerBI_View/' . rawurlencode($report_id) . '?mode=edit'); ?>" class="btn btn-sm btn-success font-weight-bold">
                                Edit Report
                            </a>
                        <?php } ?>
                    <?php } ?>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($error)) { ?>
                    <div class="alert alert-custom alert-light-danger fade show m-5" role="alert">
                        <div class="alert-text"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="m-5">
                        <a href="<?php echo base_url('Report/PowerBI_Reports'); ?>" class="btn btn-light-primary font-weight-bold">
                            Back to Reports
                        </a>
                    </div>
                <?php } else { ?>
                    <div id="powerbi-status" class="alert alert-light-info m-5 mb-0" role="alert">Loading Power BI report...</div>
                    <div id="powerbi-report-container" style="width:100%; height:calc(100vh - 280px); min-height:700px;"></div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<?php if (empty($error)) { ?>
<script src="https://cdn.jsdelivr.net/npm/powerbi-client@2.23.1/dist/powerbi.min.js"></script>
<script>
    (function() {
        var statusEl = document.getElementById('powerbi-status');
        var reportContainer = document.getElementById('powerbi-report-container');
        var models = window['powerbi-client'].models;
        var accessLevel = <?php echo json_encode($access_level); ?>;
        var reportId = <?php echo json_encode($report_id); ?>;
        var isEdit = accessLevel === 'Edit';
        var embedConfig = {
            type: 'report',
            id: reportId,
            tokenType: models.TokenType.Embed,
            accessToken: <?php echo json_encode($embed_token); ?>,
            embedUrl: <?php echo json_encode($embed_url); ?>,
            permissions: isEdit ? models.Permissions.All : models.Permissions.Read,
            viewMode: isEdit ? models.ViewMode.Edit : models.ViewMode.View,
            settings: {
                panes: {
                    filters: {
                        expanded: isEdit,
                        visible: true
                    },
                    pageNavigation: {
                        visible: true
                    }
                },
                background: models.BackgroundType.Default
            }
        };

        function showError(message) {
            statusEl.className = 'alert alert-light-danger m-5 mb-0';
            statusEl.textContent = message;
            statusEl.style.display = '';
        }

        try {
            var report = powerbi.embed(reportContainer, embedConfig);

            report.on('loaded', function() {
                statusEl.style.display = 'none';
            });

            report.on('rendered', function() {
                statusEl.style.display = 'none';
            });

            report.on('error', function(event) {
                var detail = event.detail || {};
                showError('Power BI failed to load: ' + (detail.message || 'Unknown error'));
                console.error('Power BI embed error:', detail);
            });

            setInterval(function() {
                var tokenUrl = '<?php echo base_url('Report/Embed_Token'); ?>'
                    + '?report_id=' + encodeURIComponent(reportId)
                    + '&mode=' + encodeURIComponent(isEdit ? 'edit' : 'view');

                fetch(tokenUrl)
                    .then(function(response) {
                        return response.json();
                    })
                    .then(function(data) {
                        if (data.embedToken) {
                            report.setAccessToken(data.embedToken);
                        } else if (data.error) {
                            showError(data.error);
                        }
                    })
                    .catch(function(error) {
                        console.error('Unable to refresh Power BI token:', error);
                    });
            }, 3300000);
        } catch (error) {
            showError('Unable to initialize Power BI: ' + error.message);
            console.error(error);
        }
    })();
</script>
<?php } ?>
