<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong>PowerBI Report Builder</strong>
                    </h3>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($error)) { ?>
                    <div class="alert alert-custom alert-light-danger fade show m-5" role="alert">
                        <div class="alert-text"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="m-5">
                        <p class="text-muted mb-2">Configure these values in your <code>.env</code> file:</p>
                        <ul class="text-muted">
                            <li><code>POWERBI_TENANT_ID</code></li>
                            <li><code>POWERBI_CLIENT_ID</code></li>
                            <li><code>POWERBI_CLIENT_SECRET</code></li>
                            <li><code>POWERBI_WORKSPACE_ID</code></li>
                            <li><code>POWERBI_DATASET_ID</code></li>
                        </ul>
                        <p class="text-muted mb-0">Publish your Power BI Desktop file to a workspace first, then copy the dataset ID from the semantic model settings.</p>
                    </div>
                <?php } else { ?>
                    <div id="powerbi-status" class="alert alert-light-info m-5 mb-0" role="alert">Loading Power BI report builder...</div>
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
        var embedConfig = {
            type: 'report',
            tokenType: models.TokenType.Embed,
            accessToken: <?php echo json_encode($embed_token); ?>,
            embedUrl: <?php echo json_encode($embed_url); ?>,
            datasetId: <?php echo json_encode($dataset_id); ?>,
            groupId: <?php echo json_encode($workspace_id); ?>,
            permissions: models.Permissions.All,
            settings: {
                panes: {
                    filters: {
                        expanded: true,
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
        }

        try {
            var report = powerbi.createReport(reportContainer, embedConfig);

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

            report.on('saved', function(event) {
                console.log('Report saved:', event.detail);
            });

            setInterval(function() {
                fetch('<?php echo base_url('Report/Embed_Token'); ?>')
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
