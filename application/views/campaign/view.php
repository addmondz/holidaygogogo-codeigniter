<div class="d-flex flex-column-fluid">
	<div class="container-fluid">
		<div class="card card-custom mb-5">
			<div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
				<div class="card-title">
					<h3 class="card-label" style="color:#6082B6;">
						<strong>Campaign Detail</strong>
					</h3>
				</div>
				<div class="card-toolbar">
					<a href="<?php echo base_url('Campaign'); ?>" class="btn btn-light font-weight-bold" style="margin-right:6px;">
						<i class="la la-arrow-left"></i>Back
					</a>
					<a href="<?php echo base_url('Campaign/Update?campaign_id=') . (int)$campaign->CampaignID; ?>" class="btn btn-warning font-weight-bold">
						<i class="la la-edit"></i>Edit
					</a>
				</div>
			</div>
			<div class="card-body">
				<div class="row mb-3">
					<div class="col-md-4">
						<div class="text-muted" style="font-size:12px;">Name</div>
						<div style="font-size:15px; font-weight:600; color:#3F4254;"><?php echo htmlspecialchars((string)$campaign->Name); ?></div>
					</div>
					<div class="col-md-3">
						<div class="text-muted" style="font-size:12px;">Campaign Date</div>
						<div style="font-size:15px; font-weight:600; color:#3F4254;">
							<?php
								if(!empty($campaign->CampaignDate) && $campaign->CampaignDate !== '0000-00-00') {
									echo date('d M Y', strtotime($campaign->CampaignDate));
								} else {
									echo '<span class="text-muted">&mdash;</span>';
								}
							?>
						</div>
					</div>
					<div class="col-md-3">
						<div class="text-muted" style="font-size:12px;">Created On</div>
						<div style="font-size:15px; font-weight:600; color:#3F4254;">
							<?php echo !empty($campaign->InsertDate) ? date('d M Y, H:i', strtotime($campaign->InsertDate)) : ''; ?>
						</div>
					</div>
					<div class="col-md-2">
						<div class="text-muted" style="font-size:12px;">Guests</div>
						<div>
							<span class="label label-inline label-pill label-light-primary font-weight-bold"><?php echo count($campaign_guests); ?></span>
						</div>
					</div>
				</div>
				<?php if(!empty($campaign->Description)) { ?>
					<div class="row mb-3">
						<div class="col-md-10">
							<div class="text-muted" style="font-size:12px;">Description</div>
							<div style="white-space:pre-wrap; color:#3F4254;"><?php echo htmlspecialchars((string)$campaign->Description); ?></div>
						</div>
					</div>
				<?php } ?>
				<div class="row">
					<div class="col-md-6">
						<div class="text-muted" style="font-size:12px;">GHL Workflow ID</div>
						<?php if(!empty($campaign->GhlWorkflowID)) { ?>
							<div style="color:#3F4254;">
								<code style="font-size:13px;"><?php echo htmlspecialchars((string)$campaign->GhlWorkflowID); ?></code>
							</div>
						<?php } else { ?>
							<div>
								<span class="label label-inline label-pill label-light-warning font-weight-bold">Not set — sync disabled</span>
							</div>
						<?php } ?>
					</div>
				</div>
			</div>
		</div>

		<div class="card card-custom mb-5">
			<div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
				<div class="card-title">
					<h3 class="card-label" style="color:#6082B6;"><strong>Guest Roster</strong></h3>
				</div>
			</div>
			<div class="card-body">
				<div class="dataTables_wrapper dt-bootstrap4 no-footer">
					<table class="table table-bordered table-head-custom">
						<thead>
							<tr>
								<th style="text-align:center;">No.</th>
								<th style="text-align:center;">Name</th>
								<th style="text-align:center;">Contact</th>
								<th style="text-align:center;">Email</th>
								<th style="text-align:center;">Type</th>
								<th style="text-align:center;">Added On</th>
							</tr>
						</thead>
						<tbody>
							<?php if(empty($campaign_guests)) { ?>
								<tr><td colspan="6" style="text-align:center; padding:14px 0; color:#7E8299;">No guests in this campaign yet.</td></tr>
							<?php } else { $i = 1; foreach($campaign_guests as $g) { ?>
								<tr>
									<td style="text-align:center;"><?php echo $i; ?></td>
									<td><?php echo htmlspecialchars((string)$g->GuestName); ?></td>
									<td style="text-align:center;"><?php echo htmlspecialchars((string)$g->ContactNum); ?></td>
									<td style="text-align:center;"><?php echo htmlspecialchars((string)$g->Email); ?></td>
									<td style="text-align:center;">
										<?php $cls = ($g->GuestType === 'GHL') ? 'label-light-warning' : 'label-light-success'; ?>
										<span class="label label-inline label-pill <?php echo $cls; ?> font-weight-bold">
											<?php echo htmlspecialchars((string)$g->GuestType); ?>
										</span>
									</td>
									<td style="text-align:center;"><?php echo !empty($g->InsertDate) ? date('d M Y', strtotime($g->InsertDate)) : ''; ?></td>
								</tr>
							<?php $i++; } } ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>
