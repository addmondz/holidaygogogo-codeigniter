<style>
.kt-datatable__pager,
.kt-datatable__pager-info,
.kt-datatable__pager-size,
.kt-datatable__pager-nav,
.kt-datatable__info,
.dataTables_paginate,
.dataTables_length,
.dataTables_info {
	display: none !important;
}
div.kt-datatable__pager-container { display: none !important; }
.campaign-description-cell {
	max-width: 360px;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}
.campaign-progress-wrap {
	min-width: 140px;
	margin-top: 4px;
}
.campaign-progress-wrap .progress {
	height: 6px;
	background: #eaeaea;
	border-radius: 3px;
}
.campaign-progress-wrap .progress-bar {
	background: #1bc5bd;
	transition: width .4s ease;
}
.campaign-progress-text {
	font-size: 11px;
	color: #5e6278;
	margin-top: 2px;
}
</style>

<div class="d-flex flex-column-fluid">
	<div class="container-fluid">
		<?php if($this->session->flashdata('campaign_success')) { ?>
			<div class="alert alert-light-success" role="alert" style="border-left:4px solid #1bc5bd;">
				<?php echo htmlspecialchars($this->session->flashdata('campaign_success')); ?>
			</div>
		<?php } ?>
		<?php if($this->session->flashdata('campaign_error')) { ?>
			<div class="alert alert-light-danger" role="alert" style="border-left:4px solid #f64e60;">
				<?php echo htmlspecialchars($this->session->flashdata('campaign_error')); ?>
			</div>
		<?php } ?>

		<div class="card card-custom mb-5">
			<div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
				<div class="card-title">
					<h3 class="card-label" style="color:#6082B6;">
						<strong>Campaigns</strong>
					</h3>
				</div>
				<div class="card-toolbar">
					<a href="<?php echo base_url('Campaign/Create'); ?>" class="btn btn-primary font-weight-bold" style="width:200px;">
						<i class="la la-bullhorn"></i>Create Campaign
					</a>
					<?php $current_url = base_url($_SERVER['REQUEST_URI']); ?>
				</div>
			</div>
			<div class="card-body">
				<div class="accordion accordion-solid accordion-toggle-plus">
					<div class="card">
						<div class="card-header">
							<div id="campaign_header" data-toggle="collapse" data-target="#campaign_info" class="card-title collapsed" style="font-size:13px;">Filter By Campaign Information</div>
						</div>
						<div id="campaign_info" class="collapse">
							<div class="card-body">
								<form action="<?php echo base_url('Campaign') ?>" method="get" class="form">
									<div class="row">
										<div class="col-md-4">
											<div class="form-group">
												<label>Campaign Name</label>
												<div class="input-icon">
													<input type="text" name="name" value="<?php echo htmlspecialchars((string)$this->input->get('name'), ENT_QUOTES); ?>" autocomplete="off" class="form-control">
													<span><i class="la la-bullhorn"></i></span>
												</div>
											</div>
										</div>
										<div class="col-md-4">
											<div class="form-group">
												<label>Campaign Date Range
													<a onclick="Reset_Date_Range()" class="btn btn-icon btn-light-warning btn-xs" data-toggle="tooltip" title="Clear date range">
														<i class="la la-undo"></i>
													</a>
												</label>
												<div id="kt_daterangepicker_campaign" class="input-icon">
													<input readonly type="text" name="date_range" value="<?php echo htmlspecialchars((string)$this->input->get('date_range'), ENT_QUOTES); ?>" autocomplete="off" class="form-control">
													<span><i class="la la-calendar"></i></span>
												</div>
											</div>
										</div>
									</div>
									<input type="submit" value="Filter" class="btn btn-light-success font-weight-bold" style="width:80px;">
									<input type="button" id="reset" value="Reset" class="btn btn-light-primary font-weight-bold" style="width:80px;">
								</form>
							</div>
						</div>
					</div>
				</div>

				<br><br>
				<div class="dataTables_wrapper dt-bootstrap4 no-footer" <?php if(empty($campaigns)) { echo 'style="overflow-x:auto;"'; } ?>>
					<table id="kt_datatable" class="table table-bordered table-head-custom table-checkable dataTable no-footer dtr-inline">
						<thead>
							<tr>
								<th style="text-align:center;">No.</th>
								<th style="text-align:center;">Name</th>
								<th style="text-align:center;">Campaign Date</th>
								<th style="text-align:center;">Description</th>
								<th style="text-align:center;">Guests</th>
								<th style="text-align:center;">Created By</th>
								<th style="text-align:center;">Created On</th>
								<th style="text-align:center;">Last GHL Sync</th>
								<th class="action" style="text-align:center;">Action</th>
							</tr>
						</thead>
						<tbody>
							<?php if(empty($campaigns)) { ?>
								<tr><td colspan="9" style="text-align:center; padding-top:10px; padding-bottom:10px;">Campaign Records Not Found</td></tr>
							<?php } else { $count = ($page - 1) * $limit + 1; foreach($campaigns as $c) {
								$last_run = isset($last_sync_runs[(int)$c->CampaignID]) ? $last_sync_runs[(int)$c->CampaignID] : null;
							?>
								<tr>
									<td style="text-align:center; padding-top:15px; padding-bottom:15px;"><?php echo $count; ?></td>
									<td style="text-align:left;"><strong><?php echo htmlspecialchars($c->Name); ?></strong></td>
									<td style="text-align:center;">
										<?php
											if(!empty($c->CampaignDate) && $c->CampaignDate !== '0000-00-00') {
												echo date('d M Y', strtotime($c->CampaignDate));
											} else {
												echo '<span class="text-muted">&mdash;</span>';
											}
										?>
									</td>
									<td class="campaign-description-cell" title="<?php echo htmlspecialchars((string)$c->Description, ENT_QUOTES); ?>">
										<?php echo htmlspecialchars((string)$c->Description); ?>
									</td>
									<td style="text-align:center;">
										<span class="label label-inline label-pill label-light-primary font-weight-bold"><?php echo (int)$c->GuestCount; ?></span>
									</td>
									<td style="text-align:center;"><?php echo htmlspecialchars((string)$c->InsertByName); ?></td>
									<td style="text-align:center;">
										<?php echo !empty($c->InsertDate) ? date('d M Y', strtotime($c->InsertDate)) : ''; ?>
									</td>
									<td style="text-align:center;">
										<?php if($last_run) {
											$when = !empty($last_run->CompletedAt) ? $last_run->CompletedAt : $last_run->InsertDate;
											// Treat legacy tag runs (pre-workflow refactor) as the enrolled count for display purposes.
											$enrolled = (int)$last_run->EnrolledCount > 0 ? (int)$last_run->EnrolledCount : (int)$last_run->TaggedCount;
											$ok       = $last_run->Status === 'completed' && (int)$last_run->FailedCount === 0;
											$pillCls  = $ok ? 'label-light-success' : ($last_run->Status === 'failed' ? 'label-light-danger' : 'label-light-warning');
											$inFlight = in_array($last_run->Status, array('pending', 'running'), true);
											$totalFor = (int)$last_run->TotalGuests > 0 ? (int)$last_run->TotalGuests : ((int)$c->GuestCount);
											$doneFor  = $inFlight ? (int)$last_run->CurrentOffset : ($enrolled + (int)$last_run->FailedCount);
											$pctFor   = ($totalFor > 0) ? min(100, (int) round(($doneFor / max(1, $totalFor)) * 100)) : 0;
										?>
											<span class="label label-inline label-pill <?php echo $pillCls; ?> font-weight-bold"
												data-toggle="tooltip"
												title="Enrolled <?php echo $enrolled; ?>, Matched <?php echo (int)$last_run->MatchedCount; ?>, Created <?php echo (int)$last_run->CreatedCount; ?>, Failed <?php echo (int)$last_run->FailedCount; ?>">
												<?php echo date('d M H:i', strtotime($when)); ?>
												·
												<?php echo $enrolled; ?>/<?php echo $enrolled + (int)$last_run->FailedCount; ?>
											</span>
											<?php if($inFlight) { ?>
												<div class="campaign-progress-wrap"
													data-campaign-id="<?php echo (int)$c->CampaignID; ?>"
													data-run-id="<?php echo htmlspecialchars((string)$last_run->RunID, ENT_QUOTES); ?>">
													<div class="progress">
														<div class="progress-bar" role="progressbar" style="width: <?php echo $pctFor; ?>%;"></div>
													</div>
													<div class="campaign-progress-text">
														<span class="campaign-progress-status"><?php echo $last_run->Status === 'pending' ? 'Queued' : 'Sending'; ?></span>
														&middot;
														<span class="campaign-progress-done"><?php echo $doneFor; ?></span>/<span class="campaign-progress-total"><?php echo $totalFor; ?></span>
													</div>
												</div>
											<?php } ?>
										<?php } else { ?>
											<span class="text-muted" style="font-size:12px;">Not synced</span>
										<?php } ?>
									</td>
									<td style="text-align:center;">
										<div class="btn-group">
											<a href="<?php echo base_url('Campaign/View?campaign_id=') . (int)$c->CampaignID; ?>" class="btn btn-icon btn-light-primary btn-sm" data-toggle="tooltip" title="View campaign">
												<i class="la la-eye"></i>
											</a>
											<a href="<?php echo base_url('Campaign/Update?campaign_id=') . (int)$c->CampaignID; ?>" class="btn btn-icon btn-light-warning btn-sm" data-toggle="tooltip" title="Edit campaign" style="margin-left:4px;">
												<i class="la la-edit"></i>
											</a>
											<?php
												$noGuests    = ((int)$c->GuestCount === 0);
												$noWorkflow  = empty($c->GhlWorkflowID);
												$inFlightRun = $last_run && in_array($last_run->Status, array('pending', 'running'), true);
												$disabledSync = $noGuests || $noWorkflow || $inFlightRun;
												$disabledReason = $noGuests
													? 'Add guests before sending'
													: ($noWorkflow
														? 'Set GHL Workflow ID first'
														: ($inFlightRun ? 'Send already in progress' : 'Send WhatsApp via GHL workflow'));
											?>
											<button
												class="btn btn-icon btn-light-success btn-sm campaign-sync-btn"
												data-campaign-id="<?php echo (int)$c->CampaignID; ?>"
												data-campaign-name="<?php echo htmlspecialchars((string)$c->Name, ENT_QUOTES); ?>"
												data-guest-count="<?php echo (int)$c->GuestCount; ?>"
												data-toggle="tooltip"
												title="<?php echo $disabledReason; ?>"
												<?php if($disabledSync) echo 'disabled'; ?>
												style="margin-left:4px;">
												<i class="la la-paper-plane"></i>
											</button>
											<button onclick="Delete_Record('<?php echo base_url('assets/image/sweetalert.jpg'); ?>', '<?php echo 'Campaign : ' . str_replace('\'', '', $c->Name); ?>', '<?php echo base_url('Campaign/Delete'); ?>', 'campaign_id', <?php echo (int)$c->CampaignID; ?>, 'Y', '<?php if(strpos($current_url, '?') == true) { echo base_url('Campaign?') . (explode('?', $current_url))[1]; } else { echo base_url('Campaign'); } ?>')" class="btn btn-icon btn-light-danger btn-sm" data-toggle="tooltip" title="Delete campaign" style="margin-left:4px;">
												<i class="la la-trash"></i>
											</button>
										</div>
									</td>
								</tr>
								<?php $count++; ?>
							<?php } } ?>
						</tbody>
					</table>
				</div>

				<div class="d-flex justify-content-between align-items-center mt-3">
					<?php if(!empty($campaigns)) {
						$start = ($page - 1) * $limit + 1;
						$end   = min($page * $limit, $total);
					?>
						<div class="text-left font-weight-bold" style="padding-left:15px;">
							Showing <?= $start ?> to <?= $end ?> of <?= $total ?> entries
						</div>
					<?php } ?>

					<div class="text-center">
						<?php
							$totalPages = ($total > 0) ? (int) ceil($total / $limit) : 0;
							$query = $_GET;
							unset($query['page']);

							if($totalPages > 1):
								$maxPagesToShow = 7;
								$half = floor($maxPagesToShow / 2);
								$startPage = max(1, $page - $half);
								$endPage   = min($totalPages, $page + $half);
								if($page <= $half) { $endPage = min($totalPages, $maxPagesToShow); }
								if($page + $half > $totalPages) { $startPage = max(1, $totalPages - $maxPagesToShow + 1); }
						?>
							<?php $query['page'] = 1; ?>
							<a href="?<?= http_build_query($query) ?>" class="btn btn-sm <?= ($page == 1 ? 'btn-primary disabled' : 'btn-light') ?>">&laquo; First</a>
							<?php $query['page'] = max(1, $page - 1); ?>
							<a href="?<?= http_build_query($query) ?>" class="btn btn-sm <?= ($page == 1 ? 'btn-primary disabled' : 'btn-light') ?>">&lsaquo; Prev</a>
							<?php if($startPage > 1): ?><span class="btn btn-sm btn-light disabled">...</span><?php endif; ?>
							<?php for($i = $startPage; $i <= $endPage; $i++): $query['page'] = $i; ?>
								<a href="?<?= http_build_query($query) ?>" class="btn btn-sm <?= ($page == $i ? 'btn-primary' : 'btn-light') ?>"><?= $i ?></a>
							<?php endfor; ?>
							<?php if($endPage < $totalPages): ?><span class="btn btn-sm btn-light disabled">...</span><?php endif; ?>
							<?php $query['page'] = min($totalPages, $page + 1); ?>
							<a href="?<?= http_build_query($query) ?>" class="btn btn-sm <?= ($page == $totalPages ? 'btn-primary disabled' : 'btn-light') ?>">Next &rsaquo;</a>
							<?php $query['page'] = $totalPages; ?>
							<a href="?<?= http_build_query($query) ?>" class="btn btn-sm <?= ($page == $totalPages ? 'btn-primary disabled' : 'btn-light') ?>">Last &raquo;</a>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
	<?php
		$expanded_keys = array('name', 'date_range');
		$expand = false;
		foreach($expanded_keys as $k) {
			if($this->input->get($k) !== null && $this->input->get($k) !== '') { $expand = true; break; }
		}
	?>
	<?php if($expand) { ?> $('#campaign_header').click(); <?php } ?>

	$('#reset').click(function() { Reset('<?php echo base_url('Campaign'); ?>'); });
	function Reset_Date_Range() { $('#kt_daterangepicker_campaign input').val(''); }

	$('#kt_daterangepicker_campaign').daterangepicker({
		buttonClasses: ' btn',
		applyClass: 'btn-primary',
		cancelClass: 'btn-secondary',
		autoApply: true
	}, function(start, end, label) {
		$('#kt_daterangepicker_campaign .form-control').val(start.format('DD/MM/YYYY') + ' - ' + end.format('DD/MM/YYYY'));
	});

	$('[data-toggle="tooltip"]').tooltip();

	(function() {
		var ENQUEUE_URL = '<?php echo base_url('Campaign/Sync_Enqueue'); ?>';
		var STATUS_URL  = '<?php echo base_url('Campaign/Sync_Status'); ?>';
		var POLL_MS     = 5000;
		var activePollers = {};

		function escapeHtml(s) {
			if(s === null || s === undefined) return '';
			return String(s).replace(/[&<>"']/g, function(c) {
				return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];
			});
		}

		// Attaches a progress widget under the "Last GHL Sync" cell. Reused
		// for both freshly-enqueued runs and runs the page found in-flight
		// during initial render (the PHP side renders the wrapper element).
		function ensureProgressWidget($row, campaignId, runId) {
			var $cell = $row.find('td').eq(7); // "Last GHL Sync" column
			var $wrap = $cell.find('.campaign-progress-wrap');
			if($wrap.length === 0) {
				$wrap = $('<div class="campaign-progress-wrap"><div class="progress"><div class="progress-bar" role="progressbar" style="width:0%;"></div></div><div class="campaign-progress-text"><span class="campaign-progress-status">Queued</span> &middot; <span class="campaign-progress-done">0</span>/<span class="campaign-progress-total">0</span></div></div>');
				$cell.append($wrap);
			}
			$wrap.attr('data-campaign-id', campaignId).attr('data-run-id', runId);
			return $wrap;
		}

		function renderProgress($wrap, state) {
			var total = state.total_guests || 0;
			var done  = state.current_offset || 0;
			var pct   = total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0;
			$wrap.find('.progress-bar').css('width', pct + '%');
			$wrap.find('.campaign-progress-done').text(done);
			$wrap.find('.campaign-progress-total').text(total);
			$wrap.find('.campaign-progress-status').text(
				state.status === 'pending'   ? 'Queued' :
				state.status === 'running'   ? 'Sending' :
				state.status === 'completed' ? 'Sent' :
				state.status === 'failed'    ? 'Failed' : state.status
			);
		}

		function showFinalSummary(state) {
			var html =
				'<table class="table table-bordered table-sm" style="margin-top:10px;">' +
				'<tbody>' +
				'<tr><td>Enrolled in workflow</td><td><b>' + (state.enrolled|0) + '</b></td></tr>' +
				'<tr><td>Matched</td><td>'    + (state.matched|0) + '</td></tr>' +
				'<tr><td>Created</td><td>'    + (state.created|0) + '</td></tr>' +
				'<tr><td>Failed</td><td>'     + (state.failed|0)  + '</td></tr>' +
				'</tbody></table>' +
				'<div class="text-muted" style="font-size:12px;">Workflow: <code>' + escapeHtml(state.workflow_id || '') + '</code></div>';
			Swal.fire({
				icon: state.status === 'completed' && (state.failed|0) === 0 ? 'success' : 'warning',
				title: state.status === 'completed' ? 'WhatsApp send complete' : 'Send finished with issues',
				html: html,
				customClass: { confirmButton: 'btn btn-light-success m-2' },
				buttonsStyling: false
			}).then(function() { window.location.reload(); });
		}

		function startPolling(campaignId, runId) {
			var pollKey = campaignId + ':' + runId;
			if(activePollers[pollKey]) return;
			activePollers[pollKey] = true;

			function tick() {
				$.ajax({
					url: STATUS_URL,
					type: 'GET',
					data: { campaign_id: campaignId, run_id: runId },
					dataType: 'json',
					timeout: 15000
				}).done(function(resp) {
					if(!resp || !resp.ok) {
						delete activePollers[pollKey];
						return;
					}
					var $wrap = $('.campaign-progress-wrap[data-run-id="' + runId + '"]');
					if($wrap.length) { renderProgress($wrap, resp); }

					if(resp.status === 'completed' || resp.status === 'failed') {
						delete activePollers[pollKey];
						showFinalSummary(resp);
						return;
					}
					setTimeout(tick, POLL_MS);
				}).fail(function() {
					// Transient failure — back off and try again.
					setTimeout(tick, POLL_MS);
				});
			}
			setTimeout(tick, POLL_MS);
		}

		// Re-attach pollers for any runs the server already showed as in-flight
		// on initial page load (e.g., user reopened the page from a different
		// tab while the cron is still draining).
		$('.campaign-progress-wrap').each(function() {
			var $wrap = $(this);
			var cid   = $wrap.attr('data-campaign-id');
			var rid   = $wrap.attr('data-run-id');
			if(cid && rid) { startPolling(cid, rid); }
		});

		$('.campaign-sync-btn').on('click', function() {
			var $btn = $(this);
			if($btn.is(':disabled')) return;

			var campaignId   = $btn.data('campaign-id');
			var campaignName = $btn.data('campaign-name');
			var guestCount   = parseInt($btn.data('guest-count'), 10) || 0;

			Swal.fire({
				icon: 'question',
				title: 'Send WhatsApp for "' + campaignName + '"?',
				html: 'This will enrol <b>' + guestCount + '</b> guest(s) into the campaign\'s ' +
				      'GHL workflow, creating any missing contacts. The workflow\'s ' +
				      'WhatsApp action then sends the message. The send runs in the background &mdash; ' +
				      'you can close this page.',
				showCancelButton: true,
				confirmButtonText: 'Send now',
				cancelButtonText: 'Cancel',
				confirmButtonColor: '#1bc5bd',
				customClass: { confirmButton: 'btn btn-light-success m-2', cancelButton: 'btn btn-secondary m-2' },
				buttonsStyling: false,
				reverseButtons: true
			}).then(function(action) {
				if(!action.isConfirmed) return;

				$btn.prop('disabled', true);
				$btn.attr('data-original-title', 'Send already in progress').tooltip('hide');

				$.ajax({
					url: ENQUEUE_URL,
					type: 'POST',
					data: { campaign_id: campaignId },
					dataType: 'json',
					timeout: 30000
				}).done(function(resp) {
					if(resp && resp.ok && resp.run_id) {
						var $row  = $btn.closest('tr');
						var $wrap = ensureProgressWidget($row, campaignId, resp.run_id);
						renderProgress($wrap, {
							status: 'pending',
							current_offset: 0,
							total_guests: resp.total,
							workflow_id: resp.workflow_id
						});
						startPolling(campaignId, resp.run_id);
					} else {
						var msg = (resp && resp.message) ? resp.message : 'Could not queue WhatsApp send.';
						$btn.prop('disabled', false);
						Swal.fire({
							icon: 'error',
							title: 'Send failed to start',
							text: msg,
							customClass: { confirmButton: 'btn btn-light-danger m-2' },
							buttonsStyling: false
						});
					}
				}).fail(function(_xhr, _status, err) {
					$btn.prop('disabled', false);
					Swal.fire({
						icon: 'error',
						title: 'Send request failed',
						text: err || 'Network or server error.',
						customClass: { confirmButton: 'btn btn-light-danger m-2' },
						buttonsStyling: false
					});
				});
			});
		});
	})();
</script>
