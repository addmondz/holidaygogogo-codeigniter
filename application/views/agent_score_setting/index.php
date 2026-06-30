<div class="d-flex flex-column-fluid">
	<div class="container-fluid">
		<?php if($this->session->flashdata('agent_score_setting_success')) { ?>
			<div class="alert alert-light-success" role="alert" style="border-left:4px solid #1bc5bd;">
				<?php echo htmlspecialchars($this->session->flashdata('agent_score_setting_success')); ?>
			</div>
		<?php } ?>

		<div class="card card-custom mb-5">
			<div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
				<div class="card-title">
					<h3 class="card-label" style="color:#6082B6;">
						<strong>Agent Score Settings</strong>
					</h3>
				</div>
			</div>
			<form action="<?php echo base_url('Agent_Score_Setting/Save'); ?>" method="post">
				<div class="card-body">
					<div class="alert alert-light-primary" role="alert" style="border-left:4px solid #6082B6;">
						Turn a switch <strong>ON</strong> to <strong>exclude</strong> that agent from the Agent Score.
						Excluded agents drop out of the TC <strong>Top&nbsp;5</strong> leaderboard, the score
						benchmark (so they no longer set the &ldquo;100&rdquo; on any measure), and the Agent
						Score column on the owner matrix. Everyone is <strong>included</strong> by default.
					</div>

					<div class="dataTables_wrapper dt-bootstrap4 no-footer" <?php if(empty($agents)) { echo 'style="overflow-x:auto;"'; } ?>>
						<table class="table table-bordered table-head-custom table-checkable">
							<thead>
								<tr>
									<th style="text-align:center; width:70px;">No.</th>
									<th style="text-align:left;">Agent</th>
									<th style="text-align:center; width:110px;">Role</th>
									<th style="text-align:center; width:220px;">Exclude from Agent Score</th>
								</tr>
							</thead>
							<tbody>
								<?php if(empty($agents)) { ?>
									<tr><td colspan="4" style="text-align:center; padding-top:10px; padding-bottom:10px;">No sales agents found.</td></tr>
								<?php } else { $count = 1; foreach($agents as $agent) {
									$aid = (int) $agent->AdminID;
									$is_excluded = isset($excluded[$aid]);
									$role = ((string)$agent->Level === '50') ? 'TC2' : 'TC';
								?>
									<tr>
										<td style="text-align:center; padding-top:15px; padding-bottom:15px;"><?php echo $count; ?></td>
										<td style="text-align:left;"><strong><?php echo htmlspecialchars((string)$agent->Name); ?></strong></td>
										<td style="text-align:center;"><?php echo $role; ?></td>
										<td style="text-align:center;">
											<span class="switch switch-sm switch-icon">
												<label class="mb-0">
													<input type="checkbox" name="excluded[]" value="<?php echo $aid; ?>" <?php echo $is_excluded ? 'checked' : ''; ?>>
													<span></span>
												</label>
											</span>
										</td>
									</tr>
								<?php $count++; } } ?>
							</tbody>
						</table>
					</div>
				</div>
				<div class="card-footer text-right">
					<button type="submit" class="btn btn-primary font-weight-bold" style="min-width:170px;">
						<i class="la la-save"></i>Save Changes
					</button>
				</div>
			</form>
		</div>
	</div>
</div>
