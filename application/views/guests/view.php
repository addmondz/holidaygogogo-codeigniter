<?php
	$can_open_booking = in_array((int)$this->session->userdata('level'), array(10, 20, 30, 40, 50), true);
?>
<style>
	.guests-profile-row { display: grid; grid-template-columns: 160px 1fr; row-gap: 8px; column-gap: 16px; }
	.guests-trip-card { margin-bottom: 16px; }
	.guests-trip-card .card-header { background-color: #F4F7FB; padding: 12px 16px; }
	.guests-trip-grid { display: grid; grid-template-columns: 160px 1fr; row-gap: 6px; column-gap: 16px; }
	.guests-profile-row .gd-field-label,
	.guests-trip-grid   .gd-field-label { color: #6082B6; font-weight: 600; }
	.guests-team-list { margin: 0; padding-left: 18px; }
	.guests-team-list li { padding: 2px 0; }
</style>

<div class="d-flex flex-column-fluid">
	<div class="container-fluid">
		<div class="card card-custom mb-5">
			<div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
				<div class="card-title">
					<h3 class="card-label" style="color:#6082B6;">
						<strong>Customer Profile<?php if(!empty($profile->Name)) { echo ' &mdash; ' . htmlspecialchars($profile->Name); } ?></strong>
					</h3>
				</div>
				<div class="card-toolbar">
					<a href="<?php echo base_url('Guests'); ?>" class="btn btn-light-primary font-weight-bold" style="width:140px;">
						<i class="la la-arrow-left"></i>Back to List
					</a>
				</div>
			</div>
			<div class="card-body">

				<?php if(!empty($profile)) { ?>
					<div class="card mb-5">
						<div class="card-header" style="background-color:#F4F7FB;">
							<h5 class="card-title mb-0" style="color:#6082B6;">Profile</h5>
						</div>
						<div class="card-body">
							<div class="guests-profile-row">
								<div class="gd-field-label">Name</div>          <div><?php echo htmlspecialchars($profile->Name); ?></div>
								<div class="gd-field-label">Contact Num</div>   <div><?php echo htmlspecialchars($profile->ContactNum); ?></div>
								<div class="gd-field-label">Email</div>         <div><?php echo htmlspecialchars($profile->Email); ?></div>
								<div class="gd-field-label">Language</div>      <div><?php echo htmlspecialchars($profile->Language); ?></div>
								<div class="gd-field-label">Source</div>        <div><?php echo !empty($profile->Source) ? htmlspecialchars($profile->Source) : '<span class="text-muted">&mdash;</span>'; ?></div>
								<div class="gd-field-label">Customer Type</div> <div><?php echo !empty($profile->CustomerType) ? htmlspecialchars($profile->CustomerType) : '<span class="text-muted">&mdash;</span>'; ?></div>
								<div class="gd-field-label">Nationality</div>   <div><?php echo htmlspecialchars($profile->Nationality); ?></div>
								<div class="gd-field-label">Gender</div>        <div><?php echo htmlspecialchars($profile->Gender); ?></div>
								<div class="gd-field-label">DOB</div>
								<div>
									<?php
										if(!empty($profile->DOB) && $profile->DOB !== '0000-00-00') {
											echo date('d M Y', strtotime($profile->DOB));
										}
									?>
								</div>
								<div class="gd-field-label">Num of Pax</div>   <div><?php echo (int) $profile->TotalPax; ?></div>
								<div class="gd-field-label">Total Sales (RM)</div> <div><?php echo number_format((float) $profile->TotalSales, 2); ?></div>
							</div>
						</div>
					</div>
				<?php } ?>

				<h5 class="mb-3" style="color:#6082B6;"><strong>Trips (<?php echo count($bookings); ?>)</strong></h5>

				<?php foreach($bookings as $b) {
					$year       = !empty($b->InsertDate) ? date('Y', strtotime($b->InsertDate)) : '';
					$dest       = !empty($b->DestinationName) ? $b->DestinationName : '—';
					$role_class = $b->this_role === 'team leader' ? 'label-light-success' : 'label-light-info';
				?>
					<div class="card guests-trip-card">
						<div class="card-header d-flex align-items-center flex-wrap">
							<div class="flex-grow-1" style="font-size:14px; font-weight:600; color:#3F4254;">
								<?php echo htmlspecialchars($dest); ?>
								<?php if($year !== '') { ?> (<?php echo $year; ?>)<?php } ?>
								&nbsp;&mdash;&nbsp;
								<span class="label label-inline label-pill <?php echo $role_class; ?> font-weight-bold">
									<?php echo htmlspecialchars($b->this_role); ?>
								</span>
							</div>
						</div>
						<div class="card-body">
							<div class="guests-trip-grid">
								<div class="gd-field-label">Booking No.</div>
								<div>
									<?php if($can_open_booking) { ?>
										<a href="<?php echo base_url('Booking/Update?booking_id=') . (int)$b->BookingID; ?>" target="_blank">
											<?php echo htmlspecialchars($b->BookingNumber); ?>
										</a>
									<?php } else { ?>
										<?php echo htmlspecialchars($b->BookingNumber); ?>
									<?php } ?>
								</div>

								<div class="gd-field-label">Created</div>
								<div><?php echo !empty($b->InsertDate) ? date('d M Y', strtotime($b->InsertDate)) : ''; ?></div>

								<div class="gd-field-label">Travel</div>
								<div>
									<?php
										$has_start = !empty($b->StartDate) && $b->StartDate !== '0000-00-00';
										$has_end   = !empty($b->EndDate)   && $b->EndDate   !== '0000-00-00';
										if($has_start) { echo date('d M Y', strtotime($b->StartDate)); }
										if($has_start && $has_end) { echo ' &ndash; '; }
										if($has_end) { echo date('d M Y', strtotime($b->EndDate)); }
									?>
								</div>

								<div class="gd-field-label">Booking Name (Leader)</div>
								<div>
									<?php echo htmlspecialchars($b->LeaderName); ?>
									<?php if(!empty($b->LeaderMobile)) { ?>
										<span class="text-muted">
											(<?php if(!empty($b->LeaderCountryCode)) { echo htmlspecialchars($b->LeaderCountryCode) . ' '; } ?><?php echo htmlspecialchars($b->LeaderMobile); ?>)
										</span>
									<?php } ?>
								</div>

								<div class="gd-field-label">Team Members</div>
								<div>
									<?php if(empty($b->team_members)) { ?>
										<em class="text-muted">(none)</em>
									<?php } else { ?>
										<ul class="guests-team-list">
											<?php foreach($b->team_members as $tm) {
												$full = trim(trim($tm->Name) . ' ' . trim($tm->LastName));
											?>
												<li><?php echo htmlspecialchars($full !== '' ? $full : '(unnamed)'); ?></li>
											<?php } ?>
										</ul>
									<?php } ?>
								</div>
							</div>
						</div>
					</div>
				<?php } ?>

			</div>
		</div>
	</div>
</div>

<script>
	$('[data-toggle="tooltip"]').tooltip();
</script>
