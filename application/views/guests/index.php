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
div.kt-datatable__pager-container {
	display: none !important;
}

/* Inline contact-number edit */
.contact-edit-btn {
	opacity: 0;
	transition: opacity .15s ease;
	vertical-align: middle;
}
.contact-editable:hover .contact-edit-btn,
.contact-edit-btn:focus {
	opacity: 1;
}
.contact-editor {
	display: inline-flex;
	align-items: center;
	gap: 4px;
}
.contact-editor input.contact-input {
	width: 150px;
	height: 30px;
	padding: 2px 8px;
	font-size: 12px;
}
.contact-cell .contact-error {
	display: block;
	margin-top: 4px;
	color: #f64e60;
	font-size: 11px;
	font-weight: 600;
	white-space: normal;
}
</style>

<?php
	// Shared by the Guest List (Guests) and GHL Leads (Ghl_Leads) pages — each
	// passes the controller base and heading, defaulting to the Guest List page.
	$list_base  = isset($list_base)  ? $list_base  : 'Guests';
	$page_title = isset($page_title) ? $page_title : 'Guest List Records';
?>
<div class="d-flex flex-column-fluid">
	<div class="container-fluid">
		<div class="card card-custom mb-5">
			<div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
				<div class="card-title">
					<h3 class="card-label" style="color:#6082B6;">
						<strong><?php echo htmlspecialchars($page_title); ?></strong>
					</h3>
				</div>
			</div>
			<div class="card-body">
				<div class="accordion accordion-solid accordion-toggle-plus">
					<div class="card">
						<div class="card-header">
							<div id="guests_header" data-toggle="collapse" data-target="#guests_info" class="card-title collapsed" style="font-size:13px;">Filter By Guest Information</div>
						</div>
						<div id="guests_info" class="collapse">
							<div class="card-body">
								<form action="<?php echo base_url($list_base) ?>" method="get" class="form">
									<div class="row">
										<div class="col-md-3">
											<div class="form-group">
												<label>Search Name</label>
												<div class="input-icon">
													<input type="text" name="q" value="<?php if(!empty($this->input->get('q'))) { echo htmlspecialchars($this->input->get('q'), ENT_QUOTES); } ?>" autocomplete="off" class="form-control">
													<span><i class="la la-user"></i></span>
												</div>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label>Sales Agent</label>
												<select name="sales_agent" class="form-control selectpicker" data-live-search="true">
													<option selected data-icon="la la-user-tie font-size-lg bs-icon" value="">--SELECT SALES AGENT--</option>
													<?php if(!empty($admins)) { foreach($admins as $a) { ?>
														<option data-icon="la la-user-tie font-size-lg bs-icon" value="<?php echo $a->AdminID; ?>" <?php if($this->input->get('sales_agent') == $a->AdminID) echo 'selected'; ?>><?php echo $a->Name; ?></option>
													<?php } } ?>
												</select>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label>Source</label>
												<select name="source" class="form-control selectpicker" data-live-search="true">
													<option selected data-icon="la la-stream font-size-lg bs-icon" value="">--SELECT SOURCE--</option>
													<?php if(!empty($sources)) { foreach($sources as $s) { ?>
														<option data-icon="la la-stream font-size-lg bs-icon" value="<?php echo $s->SourceID; ?>" <?php if($this->input->get('source') == $s->SourceID) echo 'selected'; ?>><?php echo $s->Name; ?></option>
													<?php } } ?>
												</select>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label>Customer Type</label>
												<select name="customer_type" class="form-control selectpicker">
													<option selected data-icon="la la-users font-size-lg bs-icon" value="">--SELECT CUSTOMER TYPE--</option>
													<?php if(!empty($customer_types)) { foreach($customer_types as $ct) { ?>
														<option data-icon="la la-user-tag font-size-lg bs-icon" value="<?php echo $ct->Name; ?>" <?php if($this->input->get('customer_type') == $ct->Name) echo 'selected'; ?>><?php echo $ct->Name; ?></option>
													<?php } } ?>
												</select>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-3">
											<div class="form-group">
												<label>Nationality</label>
												<select name="nationality" class="form-control selectpicker" data-live-search="true">
													<option selected data-icon="la la-globe font-size-lg bs-icon" value="">--SELECT NATIONALITY--</option>
													<?php if(!empty($nationalities)) { foreach($nationalities as $n) { ?>
														<option data-icon="la la-globe font-size-lg bs-icon" value="<?php echo htmlspecialchars($n->value, ENT_QUOTES); ?>" <?php if($this->input->get('nationality') === $n->value) echo 'selected'; ?>><?php echo htmlspecialchars($n->value); ?></option>
													<?php } } ?>
												</select>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label>Gender</label>
												<select name="gender" class="form-control selectpicker">
													<option selected data-icon="la la-venus-mars font-size-lg bs-icon" value="">--SELECT GENDER--</option>
													<option data-icon="la la-mars font-size-lg bs-icon" value="Male"   <?php if($this->input->get('gender') === 'Male')   echo 'selected'; ?>>Male</option>
													<option data-icon="la la-venus font-size-lg bs-icon" value="Female" <?php if($this->input->get('gender') === 'Female') echo 'selected'; ?>>Female</option>
													<option data-icon="la la-genderless font-size-lg bs-icon" value="Other"  <?php if($this->input->get('gender') === 'Other')  echo 'selected'; ?>>Other</option>
												</select>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label>Language</label>
												<select name="language" class="form-control selectpicker" data-live-search="true">
													<option selected data-icon="la la-language font-size-lg bs-icon" value="">--SELECT LANGUAGE--</option>
													<?php if(!empty($languages)) { foreach($languages as $l) { ?>
														<option data-icon="la la-language font-size-lg bs-icon" value="<?php echo htmlspecialchars($l->value, ENT_QUOTES); ?>" <?php if($this->input->get('language') === $l->value) echo 'selected'; ?>><?php echo htmlspecialchars($l->value); ?></option>
													<?php } } ?>
												</select>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-3">
											<div class="form-group">
												<label>Contact Number</label>
												<div class="input-icon">
													<input type="text" name="contact_number" value="<?php if(!empty($this->input->get('contact_number'))) { echo htmlspecialchars($this->input->get('contact_number'), ENT_QUOTES); } ?>" autocomplete="off" class="form-control" placeholder="e.g. 0123456789">
													<span><i class="la la-phone"></i></span>
												</div>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label>Email</label>
												<div class="input-icon">
													<input type="text" name="email" value="<?php if(!empty($this->input->get('email'))) { echo htmlspecialchars($this->input->get('email'), ENT_QUOTES); } ?>" autocomplete="off" class="form-control" placeholder="e.g. name@email.com">
													<span><i class="la la-envelope"></i></span>
												</div>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label>Destination</label>
												<select name="destination" class="form-control selectpicker" data-live-search="true">
													<option selected data-icon="la la-map-marker font-size-lg bs-icon" value="">--SELECT DESTINATION--</option>
													<?php if(!empty($destinations)) { foreach($destinations as $d) { ?>
														<option data-icon="la la-map-marker font-size-lg bs-icon" value="<?php echo $d->CategoryID; ?>" <?php if($this->input->get('destination') == $d->CategoryID) echo 'selected'; ?>><?php echo htmlspecialchars($d->Name); ?></option>
													<?php } } ?>
												</select>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label>Guest Role</label>
												<select name="role" class="form-control selectpicker">
													<option selected data-icon="la la-user-friends font-size-lg bs-icon" value="">--SELECT ROLE--</option>
													<option data-icon="la la-user-friends font-size-lg bs-icon" value="Team Leader" <?php if($this->input->get('role') === 'Team Leader') echo 'selected'; ?>>Team Leader</option>
													<option data-icon="la la-user-friends font-size-lg bs-icon" value="Team Member" <?php if($this->input->get('role') === 'Team Member') echo 'selected'; ?>>Team Member</option>
													<option data-icon="la la-user-friends font-size-lg bs-icon" value="Lead"        <?php if($this->input->get('role') === 'Lead')        echo 'selected'; ?>>Lead (GHL)</option>
												</select>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-3">
											<div class="form-group">
												<label>Team Leader</label>
												<div class="input-icon">
													<input type="text" name="team_leader" value="<?php if(!empty($this->input->get('team_leader'))) { echo htmlspecialchars($this->input->get('team_leader'), ENT_QUOTES); } ?>" autocomplete="off" class="form-control" placeholder="e.g. John Tan">
													<span><i class="la la-user-friends"></i></span>
												</div>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label>Booking Number</label>
												<div class="input-icon">
													<input type="text" name="booking_number" value="<?php if(!empty($this->input->get('booking_number'))) { echo htmlspecialchars($this->input->get('booking_number'), ENT_QUOTES); } ?>" autocomplete="off" class="form-control" placeholder="e.g. 2606-001-0001">
													<span><i class="la la-hashtag"></i></span>
												</div>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label><?php echo ($list_base === 'Ghl_Leads') ? 'Lead Capture Date' : 'Booking Date'; ?>
													<a onclick="Reset_Booking_Date()" class="btn btn-icon btn-light-warning btn-xs" data-toggle="tooltip" title="<?php echo ($list_base === 'Ghl_Leads') ? 'Clear lead capture date' : 'Clear booking date'; ?>">
														<i class="la la-undo"></i>
													</a>
												</label>
												<div id="kt_daterangepicker_guests_booking" class="input-icon">
													<input readonly type="text" name="booking_date" value="<?php if(!empty($this->input->get('booking_date'))) { echo $this->input->get('booking_date'); } ?>" autocomplete="off" class="form-control">
													<span><i class="la la-calendar"></i></span>
												</div>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label>Travel Date
													<a onclick="Reset_Travel_Date()" class="btn btn-icon btn-light-warning btn-xs" data-toggle="tooltip" title="Clear travel date">
														<i class="la la-undo"></i>
													</a>
												</label>
												<div id="kt_daterangepicker_guests_travel" class="input-icon">
													<input readonly type="text" name="travel_date" value="<?php if(!empty($this->input->get('travel_date'))) { echo $this->input->get('travel_date'); } ?>" autocomplete="off" class="form-control">
													<span><i class="la la-calendar"></i></span>
												</div>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label>Num of Pax</label>
												<div class="d-flex align-items-center">
													<input type="number" min="0" name="pax_min" value="<?php if($this->input->get('pax_min') !== null && $this->input->get('pax_min') !== '') { echo (int)$this->input->get('pax_min'); } ?>" autocomplete="off" class="form-control" placeholder="Min">
													<span class="px-2 font-weight-bold">&ndash;</span>
													<input type="number" min="0" name="pax_max" value="<?php if($this->input->get('pax_max') !== null && $this->input->get('pax_max') !== '') { echo (int)$this->input->get('pax_max'); } ?>" autocomplete="off" class="form-control" placeholder="Max">
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
				<div class="dataTables_wrapper dt-bootstrap4 no-footer" <?php if(empty($guests)) { echo 'style="overflow-x:auto;"'; } ?>>
					<table id="kt_datatable" class="table table-bordered table-head-custom table-checkable dataTable no-footer dtr-inline">
						<thead>
							<tr>
								<th style="text-align:center;">No.</th>
								<th style="text-align:center;">First Name</th>
								<th style="text-align:center;">Team Leader</th>
								<th style="text-align:center;">Contact Num</th>
								<th style="text-align:center;">Email</th>
								<th style="text-align:center;">Language</th>
								<th style="text-align:center;">Agent Name</th>
								<th style="text-align:center;">Source</th>
								<th style="text-align:center;">Customer Type</th>
								<th style="text-align:center;">Destination</th>
								<th style="text-align:center;">Nationality</th>
								<th style="text-align:center;">Gender</th>
								<th style="text-align:center;">DOB</th>
								<th style="text-align:center;">Guest Role</th>
								<th style="text-align:center;">Num of Pax</th>
								<th style="text-align:center;">Total Sales (RM)</th>
								<th style="text-align:center;">Booking Date(s)</th>
								<th style="text-align:center;">Travel Date(s)</th>
								<th class="action" style="text-align:center;">Action</th>
							</tr>
						</thead>
						<tbody>
							<?php if(empty($guests)) { ?>
								<tr><td colspan="19" style="text-align:center; padding-top:10px; padding-bottom:10px;">Guest Records Not Found</td></tr>
							<?php } else { ?>
								<?php $count = 1; foreach($guests as $g) { ?>
									<tr>
										<td style="text-align:center; padding-top:15px; padding-bottom:15px;"><?php echo $count; ?></td>
										<td style="text-align:center;"><?php echo htmlspecialchars($g->Name); ?></td>
										<td style="text-align:center; white-space:nowrap;">
											<?php
												$leaders = guest_list_split_team_leaders(isset($g->TeamLeader) ? $g->TeamLeader : '');
												if(!empty($leaders)) {
													$tl_base    = base_url($list_base);
													$leader_out = array();
													foreach($leaders as $ln) {
														// Click a team leader name to reload the list filtered
														// to that exact leader — showing everyone on their team.
														$href = $tl_base . '?team_leader=' . urlencode($ln) . '&team_leader_exact=1';
														$leader_out[] = '<a href="' . htmlspecialchars($href, ENT_QUOTES) . '" '
															. 'title="Show all team members under this leader" '
															. 'style="color:#3699FF; text-decoration:none; border-bottom:1px dashed #3699FF;">'
															. htmlspecialchars($ln) . '</a>';
													}
													echo implode('<br>', $leader_out);
												} else {
													echo '<span class="text-muted">&mdash;</span>';
												}
											?>
										</td>
										<?php
											$is_ghl_row      = isset($g->Type) && $g->Type === 'GHL';
											$calling_code    = isset($g->CallingCode) ? (string)$g->CallingCode : '';
											$contact_display = guest_contact_format_display($calling_code, (string)$g->ContactNum);
											$wa_number       = guest_contact_wa_digits($calling_code, (string)$g->ContactNum);
										?>
										<td class="contact-cell<?php if(!$is_ghl_row) echo ' contact-editable'; ?>" style="text-align:center; white-space:nowrap;"<?php if(!$is_ghl_row) { ?> data-dedup-key="<?php echo htmlspecialchars($g->dedup_key, ENT_QUOTES); ?>" data-mobile="<?php echo htmlspecialchars((string)$g->ContactNum, ENT_QUOTES); ?>" data-calling-code="<?php echo htmlspecialchars($calling_code, ENT_QUOTES); ?>"<?php } ?>>
											<span class="contact-display">
												<?php if(!empty($wa_number)) { ?>
													<a class="contact-wa" href="https://wa.me/<?php echo $wa_number; ?>" target="_blank" rel="noopener" style="color:#25D366; text-decoration:none; display:inline-flex; align-items:center; gap:5px;" title="Open WhatsApp chat">
														<i class="la la-whatsapp" style="font-size:16px;"></i><span class="contact-num"><?php echo htmlspecialchars($contact_display); ?></span>
													</a>
												<?php } else { ?>
													<span class="contact-num"><?php echo htmlspecialchars($contact_display); ?></span>
												<?php } ?>
												<?php if(!$is_ghl_row) { ?>
													<button type="button" class="btn btn-icon btn-light-primary btn-xs contact-edit-btn ml-1" data-toggle="tooltip" title="Edit contact number">
														<i class="la la-pencil"></i>
													</button>
												<?php } ?>
											</span>
										</td>
										<td style="text-align:center;"><?php echo htmlspecialchars($g->Email); ?></td>
										<td style="text-align:center;"><?php echo htmlspecialchars($g->Language); ?></td>
										<td style="text-align:center;"><?php echo htmlspecialchars($g->AgentName); ?></td>
										<td style="text-align:center;"><?php echo htmlspecialchars($g->Source); ?></td>
										<td style="text-align:center;"><?php echo htmlspecialchars($g->CustomerType); ?></td>
										<td style="text-align:center; white-space:nowrap;">
											<?php
												if(!empty($g->Destination)) {
													$dest_out = array();
													foreach(explode('||', $g->Destination) as $d) {
														$d = trim($d);
														if($d !== '') { $dest_out[] = htmlspecialchars($d); }
													}
													echo implode('<br>', $dest_out);
												}
											?>
										</td>
										<td style="text-align:center;"><?php echo htmlspecialchars($g->Nationality); ?></td>
										<td style="text-align:center;"><?php echo htmlspecialchars($g->Gender); ?></td>
										<td style="text-align:center;">
											<?php
												if(!empty($g->DOB) && $g->DOB !== '0000-00-00') {
													echo date('d M Y', strtotime($g->DOB));
												}
											?>
										</td>
										<?php $is_ghl = isset($g->Type) && $g->Type === 'GHL'; ?>
										<td style="text-align:center; white-space:nowrap;">
											<?php
												$role = isset($g->Role) ? $g->Role : '';
												if($role === 'Team Leader')      { $rcls = 'label-light-primary'; }
												elseif($role === 'Team Member')  { $rcls = 'label-light-info'; }
												elseif($role === 'Lead')         { $rcls = 'label-light-warning'; }
												else                             { $rcls = 'label-light-dark'; }
											?>
											<?php if($role !== ''): ?>
												<span class="label label-inline label-pill <?php echo $rcls; ?> font-weight-bold" style="white-space:nowrap;"><?php echo htmlspecialchars($role); ?></span>
											<?php endif; ?>
										</td>
										<td style="text-align:center;"><?php echo (int) $g->TotalPax; ?></td>
										<td style="text-align:right;"><?php echo number_format((float) $g->TotalSales, 2); ?></td>
										<td style="text-align:center; white-space:nowrap;">
											<?php
												if(!empty($g->BookingDates)) {
													$bd_out = array();
													foreach(array_map('trim', explode(',', $g->BookingDates)) as $d) {
														if($d !== '' && $d !== '0000-00-00' && ($ts = strtotime($d))) {
															$bd_out[] = htmlspecialchars(date('d M Y', $ts));
														}
													}
													if(!empty($bd_out)) {
														if($is_ghl) {
															echo '<span class="text-muted font-weight-bold d-block">Lead captured</span>';
														}
														echo implode('<br>', $bd_out);
													}
												}
											?>
										</td>
										<td style="text-align:center; white-space:nowrap;">
											<?php
												if(!empty($g->TravelDates)) {
													$td_out = array();
													foreach(array_map('trim', explode(',', $g->TravelDates)) as $it) {
														$p    = explode('|', $it);
														$s    = isset($p[0]) ? trim($p[0]) : '';
														$e    = isset($p[1]) ? trim($p[1]) : '';
														$s_ts = ($s !== '' && $s !== '0000-00-00') ? strtotime($s) : false;
														$e_ts = ($e !== '' && $e !== '0000-00-00') ? strtotime($e) : false;
														if($s_ts && $e_ts)   { $td_out[] = htmlspecialchars(date('d M Y', $s_ts) . ' - ' . date('d M Y', $e_ts)); }
														elseif($s_ts)        { $td_out[] = htmlspecialchars(date('d M Y', $s_ts)); }
														elseif($e_ts)        { $td_out[] = htmlspecialchars(date('d M Y', $e_ts)); }
													}
													echo implode('<br>', $td_out);
												}
											?>
										</td>
										<td style="text-align:center;">
											<?php if(!$is_ghl) { ?>
												<div class="btn-group">
													<button type="button" data-toggle="dropdown" class="btn btn-light-primary btn-sm dropdown-toggle" style="padding-left:3px;"></button>
													<div class="dropdown-menu">
														<a href="<?php echo base_url('Guests/View?key=') . urlencode($g->dedup_key); ?>" class="dropdown-item" style="font-size:11px;">View Trip History</a>
														<?php if(!empty($g->Token)) { ?>
															<div class="dropdown-divider"></div>
															<a href="<?php echo base_url('Guest_List?gl=') . urlencode($g->Token); ?>" target="_blank" class="dropdown-item" style="font-size:11px;">Guest List</a>
															<a href="<?php echo base_url('Booking_Confirmation?token=') . urlencode($g->Token); ?>" target="_blank" class="dropdown-item" style="font-size:11px;">Booking Confirmation</a>
														<?php } ?>
													</div>
												</div>
											<?php } else { ?>
												<span class="text-muted">&mdash;</span>
											<?php } ?>
										</td>
									</tr>
									<?php $count++; ?>
								<?php } ?>
							<?php } ?>
						</tbody>
					</table>
				</div>
				<div class="d-flex justify-content-between align-items-center mt-3">
					<?php if(!empty($guests)) {
						$start = ($page - 1) * $limit + 1;
						$end   = $start + count($guests) - 1;
					?>
						<div class="text-left font-weight-bold" style="padding-left:15px;">
							Showing <?= $start ?> to <?= $end ?> of <span id="guests_total" class="text-muted">…</span> entries
						</div>
					<?php } ?>

					<div class="text-center" id="guests_pagination">
						<?php if(!empty($guests)): ?>
							<span class="text-muted small"><i class="la la-spinner la-spin"></i>&nbsp; Loading pagination…</span>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
	<?php
		$expanded_keys = array('q', 'booking_number', 'contact_number', 'email', 'destination', 'role', 'pax_min', 'pax_max', 'sales_agent', 'source', 'customer_type', 'nationality', 'gender', 'language', 'booking_date', 'travel_date', 'team_leader');
		$expand = false;
		foreach($expanded_keys as $k) {
			if($this->input->get($k) !== null && $this->input->get($k) !== '') { $expand = true; break; }
		}
	?>
	<?php if($expand) { ?>
		$('#guests_header').click();
	<?php } ?>

	$('#reset').click(function() {
		Reset('<?php echo base_url($list_base); ?>');
	});

	<?php if(!empty($guests)): ?>
		$(function() {
			$.ajax({
				url: '<?php echo base_url($list_base . '/Count'); ?>' + (window.location.search || ''),
				dataType: 'json',
				timeout: 60000
			}).done(function(data) {
				if (data && typeof data.total !== 'undefined') {
					$('#guests_total').text(Number(data.total).toLocaleString()).removeClass('text-muted');
				}
				if (data && typeof data.pagination_html === 'string') {
					$('#guests_pagination').html(data.pagination_html);
				} else {
					$('#guests_pagination').empty();
				}
			}).fail(function() {
				$('#guests_total').text('—').removeClass('text-muted');
				$('#guests_pagination').html('<span class="text-danger small">Could not load total / pagination</span>');
			});
		});
	<?php endif; ?>

	function Reset_Booking_Date() {
		$('#kt_daterangepicker_guests_booking input').val('');
	}
	function Reset_Travel_Date() {
		$('#kt_daterangepicker_guests_travel input').val('');
	}

	$('#kt_daterangepicker_guests_booking').daterangepicker({
		buttonClasses: ' btn',
		applyClass: 'btn-primary',
		cancelClass: 'btn-secondary',
		autoApply: true
	}, function(start, end, label) {
		$('#kt_daterangepicker_guests_booking .form-control').val(start.format('DD/MM/YYYY') + ' - ' + end.format('DD/MM/YYYY'));
	});

	$('#kt_daterangepicker_guests_travel').daterangepicker({
		buttonClasses: ' btn',
		applyClass: 'btn-primary',
		cancelClass: 'btn-secondary',
		autoApply: true
	}, function(start, end, label) {
		$('#kt_daterangepicker_guests_travel .form-control').val(start.format('DD/MM/YYYY') + ' - ' + end.format('DD/MM/YYYY'));
	});

	$('[data-toggle="tooltip"]').tooltip();

	// ----- Inline contact-number edit -----
	var GUEST_CONTACT_UPDATE_URL = '<?php echo base_url('Guests/Update_Contact'); ?>';

	// Mirror of guest_contact_format_display() / guest_contact_wa_digits() in
	// application/helpers/guest_contact_helper.php — keep the two in sync.
	function gcFormatDisplay(callingCode, local) {
		local       = $.trim(local || '');
		callingCode = $.trim(callingCode || '');
		if (local === '') { return ''; }
		if (callingCode === '') { return local; }
		if (local.charAt(0) === '0') { local = local.substring(1); }
		if (local === '') { return callingCode; }
		return callingCode + ' ' + local;
	}

	function gcWaDigits(callingCode, local) {
		var localDigits = (local || '').replace(/[^0-9]/g, '');
		if (localDigits === '') { return ''; }
		callingCode = $.trim(callingCode || '');
		if (callingCode === '') { return localDigits; }
		localDigits = localDigits.replace(/^0+/, '');
		return callingCode.replace(/[^0-9]/g, '') + localDigits;
	}

	function waLink(label, digits) {
		var safe = $('<span>').text(label).html();
		if (digits === '') { return '<span class="contact-num">' + safe + '</span>'; }
		return '<a class="contact-wa" href="https://wa.me/' + digits + '" target="_blank" rel="noopener" ' +
			'style="color:#25D366; text-decoration:none; display:inline-flex; align-items:center; gap:5px;" title="Open WhatsApp chat">' +
			'<i class="la la-whatsapp" style="font-size:16px;"></i><span class="contact-num">' + safe + '</span></a>';
	}

	function renderDisplay($cell) {
		$cell.find('[data-toggle="tooltip"]').tooltip('dispose');
		var num = $cell.attr('data-mobile') || '';
		var cc  = $cell.attr('data-calling-code') || '';
		var html = '<span class="contact-display">' + waLink(gcFormatDisplay(cc, num), gcWaDigits(cc, num)) +
			'<button type="button" class="btn btn-icon btn-light-primary btn-xs contact-edit-btn ml-1" data-toggle="tooltip" title="Edit contact number">' +
			'<i class="la la-pencil"></i></button></span>';
		$cell.html(html);
		$cell.find('[data-toggle="tooltip"]').tooltip();
	}

	$('#kt_datatable').on('click', '.contact-edit-btn', function() {
		var $cell = $(this).closest('.contact-cell');
		if ($cell.find('.contact-editor').length) { return; }
		var current = $cell.attr('data-mobile') || '';
		$cell.find('[data-toggle="tooltip"]').tooltip('dispose');
		$cell.html(
			'<span class="contact-editor">' +
				'<input type="text" class="form-control contact-input" value="' + $('<span>').text(current).html() + '" autocomplete="off">' +
				'<button type="button" class="btn btn-icon btn-light-success btn-xs contact-save" data-toggle="tooltip" title="Save"><i class="la la-check"></i></button>' +
				'<button type="button" class="btn btn-icon btn-light-danger btn-xs contact-cancel" data-toggle="tooltip" title="Cancel"><i class="la la-times"></i></button>' +
			'</span>' +
			'<span class="contact-error" style="display:none;"></span>'
		);
		$cell.find('[data-toggle="tooltip"]').tooltip();
		$cell.find('.contact-input').focus().select();
	});

	$('#kt_datatable').on('click', '.contact-cancel', function() {
		renderDisplay($(this).closest('.contact-cell'));
	});

	$('#kt_datatable').on('keydown', '.contact-input', function(e) {
		if (e.which === 13) { e.preventDefault(); $(this).closest('.contact-cell').find('.contact-save').click(); }
		else if (e.which === 27) { e.preventDefault(); renderDisplay($(this).closest('.contact-cell')); }
	});

	$('#kt_datatable').on('click', '.contact-save', function() {
		var $btn   = $(this);
		var $cell  = $btn.closest('.contact-cell');
		var $input = $cell.find('.contact-input');
		var $error = $cell.find('.contact-error');
		var mobile = $.trim($input.val());

		$btn.tooltip('hide');
		$error.hide().text('');
		$btn.prop('disabled', true).find('i').attr('class', 'la la-spinner la-spin');

		$.ajax({
			url: GUEST_CONTACT_UPDATE_URL,
			method: 'POST',
			dataType: 'json',
			data: { dedup_key: $cell.attr('data-dedup-key'), mobile: mobile },
			timeout: 30000
		}).done(function(res) {
			if (res && res.ok) {
				$cell.attr('data-mobile', res.mobile);
				if (res.dedup_key) { $cell.attr('data-dedup-key', res.dedup_key); }
				renderDisplay($cell);
			} else {
				$error.text((res && res.message) ? res.message : 'Could not update contact number.').show();
				$btn.prop('disabled', false).find('i').attr('class', 'la la-check');
			}
		}).fail(function() {
			$error.text('Network error. Please try again.').show();
			$btn.prop('disabled', false).find('i').attr('class', 'la la-check');
		});
	});
</script>
