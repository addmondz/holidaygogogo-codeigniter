<style>
	/* Include = blue (ON); exclude = solid grey (OFF). Metronic colours the switch
	   TRACK via span:before, and switch-primary keeps that track blue in BOTH
	   states (only the knob moves) — so we colour the track ourselves: grey when
	   off, blue when on. The :empty rule must come before :checked so the checked
	   (on) colour wins when both match a checkbox. */
	#agent-score-settings .switch input:empty ~ span:before { background-color: #A1A5B7 !important; }
	#agent-score-settings .switch input:checked ~ span:before { background-color: #3699FF !important; }
	/* Knob (span:after) = the circle around the tick. Default ON knob is blue on a
	   blue track (invisible), so make it a crisp white circle with a blue tick;
	   keep the OFF knob a fully-opaque white circle too (not the faint 0.7). */
	#agent-score-settings .switch input:empty ~ span:after {
		opacity: 1 !important;
		box-shadow: 0 1px 2px rgba(0, 0, 0, 0.25);
	}
	#agent-score-settings .switch input:checked ~ span:after {
		background-color: #ffffff !important;
		color: #3699FF !important;
		opacity: 1 !important;
		box-shadow: 0 1px 2px rgba(0, 0, 0, 0.25);
	}
</style>
<div id="agent-score-settings" class="d-flex flex-column-fluid">
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
						A <strong style="color:#3699FF;">blue</strong> switch (<strong>ON</strong>) means the agent is
						<strong>included</strong> in the Agent Score; a <strong>grey</strong> switch (<strong>OFF</strong>)
						<strong>excludes</strong> them. Everyone is <strong>included</strong> by default.
						Excluded agents drop out of the TC <strong>Top&nbsp;5</strong> leaderboard, the score
						benchmark (so they no longer set the &ldquo;100&rdquo; on any measure), and the owner
						per-agent matrix. The <strong>Owner</strong> and <strong>TC&nbsp;Lead</strong> count toward
						the score benchmark (their figures help set the &ldquo;100&rdquo;) but the Owner is
						<strong>not shown</strong> on the leaderboard or matrix &mdash; included in the calculation,
						not displayed. Switching one <strong>OFF</strong> removes them from the calculation entirely.
					</div>

					<div class="dataTables_wrapper dt-bootstrap4 no-footer" <?php if(empty($agents)) { echo 'style="overflow-x:auto;"'; } ?>>
						<table class="table table-bordered table-head-custom table-checkable">
							<thead>
								<tr>
									<th style="text-align:center; width:70px;">No.</th>
									<th style="text-align:left;">Agent</th>
									<th style="text-align:center; width:110px;">Role</th>
									<th style="text-align:center; width:220px;">Include in Agent Score</th>
								</tr>
							</thead>
							<tbody>
								<?php if(empty($agents)) { ?>
									<tr><td colspan="4" style="text-align:center; padding-top:10px; padding-bottom:10px;">No agents found.</td></tr>
								<?php } else { $count = 1; foreach($agents as $agent) {
									$aid = (int) $agent->AdminID;
									$is_excluded = isset($excluded[$aid]);
									$role_labels = array('10' => 'OWNER', '20' => 'TC', '25' => 'TC LEAD', '50' => 'TC2');
									$role = isset($role_labels[(string)$agent->Level]) ? $role_labels[(string)$agent->Level] : 'TC';
								?>
									<tr>
										<td style="text-align:center; padding-top:15px; padding-bottom:15px;"><?php echo $count; ?></td>
										<td style="text-align:left;"><strong><?php echo htmlspecialchars((string)$agent->Name); ?></strong></td>
										<td style="text-align:center;"><?php echo $role; ?></td>
										<td style="text-align:center;">
											<span class="switch switch-sm switch-icon">
												<label class="mb-0">
													<input type="checkbox" name="included[]" value="<?php echo $aid; ?>" <?php echo $is_excluded ? '' : 'checked'; ?>>
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
