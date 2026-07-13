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

/* Generic inline field edit (First Name / Email / Language) */
.gl-edit-btn {
	opacity: 0;
	transition: opacity .15s ease;
	vertical-align: middle;
}
.gl-editable:hover .gl-edit-btn,
.gl-edit-btn:focus {
	opacity: 1;
}
.gl-editor {
	display: inline-flex;
	align-items: center;
	gap: 4px;
}
.gl-editor .gl-input {
	width: 150px;
	height: 30px;
	padding: 2px 8px;
	font-size: 12px;
}
.gl-editor select.gl-input {
	width: 90px;
}
.gl-cell .gl-error {
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
	// wa-digits => true for contacts that have a stored WhatsApp conversation.
	$msg_log_phones = isset($msg_log_phones) && is_array($msg_log_phones) ? $msg_log_phones : array();
	// dedup_key => active-remark count, to badge each row's Remarks action.
	$remark_counts  = isset($remark_counts) && is_array($remark_counts) ? $remark_counts : array();
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
													<input type="text" name="q" value="<?php if(!empty($this->input->get('q'))) { echo htmlspecialchars($this->input->get('q'), ENT_QUOTES); } ?>" autocomplete="off" class="form-control" placeholder="Guest or team leader name">
													<span><i class="la la-user"></i></span>
												</div>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label>Sales Agent</label>
												<?php $sel_sales_agent = guest_list_multi_values($this->input->get('sales_agent')); ?>
												<select name="sales_agent[]" class="form-control selectpicker" data-live-search="true" multiple data-actions-box="true" title="--SELECT SALES AGENT--">
													<?php if(!empty($admins)) { foreach($admins as $a) { ?>
														<option data-icon="la la-user-tie font-size-lg bs-icon" value="<?php echo $a->AdminID; ?>" <?php if(in_array((string)$a->AdminID, $sel_sales_agent, true)) echo 'selected'; ?>><?php echo $a->Name; ?></option>
													<?php } } ?>
												</select>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label>Source</label>
												<?php $sel_source = guest_list_multi_values($this->input->get('source')); ?>
												<select name="source[]" class="form-control selectpicker" data-live-search="true" multiple data-actions-box="true" title="--SELECT SOURCE--">
													<?php if(!empty($sources)) { foreach($sources as $s) { ?>
														<option data-icon="la la-stream font-size-lg bs-icon" value="<?php echo $s->SourceID; ?>" <?php if(in_array((string)$s->SourceID, $sel_source, true)) echo 'selected'; ?>><?php echo $s->Name; ?></option>
													<?php } } ?>
												</select>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label>Customer Type</label>
												<?php $sel_customer_type = guest_list_multi_values($this->input->get('customer_type')); ?>
												<select name="customer_type[]" class="form-control selectpicker" data-live-search="true" multiple data-actions-box="true" title="--SELECT CUSTOMER TYPE--">
													<?php if(!empty($customer_types)) { foreach($customer_types as $ct) { ?>
														<option data-icon="la la-user-tag font-size-lg bs-icon" value="<?php echo htmlspecialchars($ct->Name, ENT_QUOTES); ?>" <?php if(in_array((string)$ct->Name, $sel_customer_type, true)) echo 'selected'; ?>><?php echo $ct->Name; ?></option>
													<?php } } ?>
												</select>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-3">
											<div class="form-group">
												<label>Nationality</label>
												<?php $sel_nationality = guest_list_multi_values($this->input->get('nationality')); ?>
												<select name="nationality[]" class="form-control selectpicker" data-live-search="true" multiple data-actions-box="true" title="--SELECT NATIONALITY--">
													<?php if(!empty($nationalities)) { foreach($nationalities as $n) { ?>
														<option data-icon="la la-globe font-size-lg bs-icon" value="<?php echo htmlspecialchars($n->value, ENT_QUOTES); ?>" <?php if(in_array((string)$n->value, $sel_nationality, true)) echo 'selected'; ?>><?php echo htmlspecialchars($n->value); ?></option>
													<?php } } ?>
												</select>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label>Gender</label>
												<?php $sel_gender = guest_list_multi_values($this->input->get('gender')); ?>
												<select name="gender[]" class="form-control selectpicker" multiple data-actions-box="true" title="--SELECT GENDER--">
													<option data-icon="la la-mars font-size-lg bs-icon" value="Male"   <?php if(in_array('Male', $sel_gender, true))   echo 'selected'; ?>>Male</option>
													<option data-icon="la la-venus font-size-lg bs-icon" value="Female" <?php if(in_array('Female', $sel_gender, true)) echo 'selected'; ?>>Female</option>
													<option data-icon="la la-genderless font-size-lg bs-icon" value="Other"  <?php if(in_array('Other', $sel_gender, true))  echo 'selected'; ?>>Other</option>
												</select>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label>Language</label>
												<?php $sel_language = guest_list_multi_values($this->input->get('language')); ?>
												<select name="language[]" class="form-control selectpicker" data-live-search="true" multiple data-actions-box="true" title="--SELECT LANGUAGE--">
													<?php if(!empty($languages)) { foreach($languages as $l) { ?>
														<option data-icon="la la-language font-size-lg bs-icon" value="<?php echo htmlspecialchars($l->value, ENT_QUOTES); ?>" <?php if(in_array((string)$l->value, $sel_language, true)) echo 'selected'; ?>><?php echo htmlspecialchars($l->value); ?></option>
													<?php } } ?>
												</select>
											</div>
										</div>
										<?php if($list_base !== 'Ghl_Leads') { ?>
										<div class="col-md-3">
											<div class="form-group">
												<label>Guest Type</label>
												<?php $sel_guest_type = guest_list_multi_values($this->input->get('guest_type')); ?>
												<select name="guest_type[]" class="form-control selectpicker" multiple data-actions-box="true" title="--SELECT GUEST TYPE--">
													<option data-icon="la la-user font-size-lg bs-icon" value="ADULT"  <?php if(in_array('ADULT', $sel_guest_type, true))  echo 'selected'; ?>>Adult</option>
													<option data-icon="la la-child font-size-lg bs-icon" value="CHILD"  <?php if(in_array('CHILD', $sel_guest_type, true))  echo 'selected'; ?>>Child</option>
													<option data-icon="la la-baby font-size-lg bs-icon" value="INFANT" <?php if(in_array('INFANT', $sel_guest_type, true)) echo 'selected'; ?>>Infant</option>
												</select>
											</div>
										</div>
										<?php } ?>
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
												<?php $sel_destination = guest_list_multi_values($this->input->get('destination')); ?>
												<select name="destination[]" class="form-control selectpicker" data-live-search="true" multiple data-actions-box="true" title="--SELECT DESTINATION--">
													<?php if(!empty($destinations)) { foreach($destinations as $d) { ?>
														<option data-icon="la la-map-marker font-size-lg bs-icon" value="<?php echo $d->CategoryID; ?>" <?php if(in_array((string)$d->CategoryID, $sel_destination, true)) echo 'selected'; ?>><?php echo htmlspecialchars($d->Name); ?></option>
													<?php } } ?>
												</select>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label>Guest Role</label>
												<?php $sel_role = guest_list_multi_values($this->input->get('role')); ?>
												<select name="role[]" class="form-control selectpicker" multiple data-actions-box="true" title="--SELECT ROLE--">
													<option data-icon="la la-user-friends font-size-lg bs-icon" value="Team Leader" <?php if(in_array('Team Leader', $sel_role, true)) echo 'selected'; ?>>Team Leader</option>
													<option data-icon="la la-user-friends font-size-lg bs-icon" value="Team Member" <?php if(in_array('Team Member', $sel_role, true)) echo 'selected'; ?>>Team Member</option>
													<option data-icon="la la-user-friends font-size-lg bs-icon" value="Lead"        <?php if(in_array('Lead', $sel_role, true))        echo 'selected'; ?>>Lead (GHL)</option>
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
										<div class="col-md-3">
											<div class="form-group">
												<label>Date of Birth
													<a onclick="Reset_Dob()" class="btn btn-icon btn-light-warning btn-xs" data-toggle="tooltip" title="Clear date of birth">
														<i class="la la-undo"></i>
													</a>
												</label>
												<div id="kt_daterangepicker_guests_dob" class="input-icon">
													<input readonly type="text" name="dob" value="<?php if(!empty($this->input->get('dob'))) { echo $this->input->get('dob'); } ?>" autocomplete="off" class="form-control" placeholder="Born between…">
													<span><i class="la la-birthday-cake"></i></span>
												</div>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label>Birthday</label>
												<?php $birthday_sel = (string) $this->input->get('birthday'); ?>
												<select name="birthday" class="form-control">
													<option value="">Any birthday</option>
													<option value="today"      <?php if($birthday_sel === 'today')      { echo 'selected'; } ?>>🎂 Birthday today</option>
													<option value="this_month" <?php if($birthday_sel === 'this_month') { echo 'selected'; } ?>>Birthday this month</option>
													<optgroup label="Birthday in month">
														<?php foreach(array(1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December') as $mnum => $mname) { ?>
															<option value="<?php echo $mnum; ?>" <?php if($birthday_sel === (string)$mnum) { echo 'selected'; } ?>><?php echo $mname; ?></option>
														<?php } ?>
													</optgroup>
												</select>
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
								<th style="text-align:center;">Guest First Name</th>
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
								<th style="text-align:center;">Guest Type</th>
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
								<tr><td colspan="20" style="text-align:center; padding-top:10px; padding-bottom:10px;">Guest Records Not Found</td></tr>
							<?php } else { ?>
								<?php $count = 1; foreach($guests as $g) { ?>
									<?php $is_ghl_row = isset($g->Type) && $g->Type === 'GHL'; ?>
									<tr>
										<td style="text-align:center; padding-top:15px; padding-bottom:15px;"><?php echo $count; ?></td>
										<?php $name_val = (string) $g->Name; ?>
										<td class="gl-cell<?php if(!$is_ghl_row) echo ' gl-editable'; ?>" style="text-align:center;"<?php if(!$is_ghl_row) { ?> data-field="name" data-dedup-key="<?php echo htmlspecialchars($g->dedup_key, ENT_QUOTES); ?>" data-value="<?php echo htmlspecialchars($name_val, ENT_QUOTES); ?>"<?php } ?>>
											<span class="gl-display">
												<span class="gl-text"><?php if($name_val !== '') { echo htmlspecialchars($name_val); } else { echo '<span class="text-muted">&mdash;</span>'; } ?></span>
												<?php if(!$is_ghl_row) { ?>
													<button type="button" class="btn btn-icon btn-light-primary btn-xs gl-edit-btn ml-1" data-toggle="tooltip" title="Edit first name"><i class="la la-pencil"></i></button>
												<?php } ?>
											</span>
										</td>
										<td style="text-align:center; white-space:nowrap;">
											<?php
												$leaders = guest_list_team_leader_links(isset($g->TeamLeaderBookings) ? $g->TeamLeaderBookings : '');
												if(!empty($leaders)) {
													$tl_base    = base_url($list_base);
													$leader_out = array();
													foreach($leaders as $lead) {
														// Click a team leader name to reload the list filtered
														// to that leader's exact booking(s) — showing only that
														// booking's team members, not every booking they've led.
														$qs = array();
														foreach($lead['booking_ids'] as $bid) {
															$qs[] = 'booking_id[]=' . urlencode($bid);
														}
														$href = $tl_base . '?' . implode('&', $qs);
														$leader_out[] = '<a href="' . htmlspecialchars($href, ENT_QUOTES) . '" '
															. 'title="Show only this booking\'s team members" '
															. 'style="color:#3699FF; text-decoration:none; border-bottom:1px dashed #3699FF;">'
															. htmlspecialchars($lead['name']) . '</a>';
													}
													echo implode('<br>', $leader_out);
												} else {
													echo '<span class="text-muted">&mdash;</span>';
												}
											?>
										</td>
										<?php
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
												<?php if(!empty($wa_number) && !empty($msg_log_phones[$wa_number])) { ?>
													<button type="button" class="btn btn-icon btn-light-success btn-xs js-msg-log ml-1" data-toggle="tooltip" title="View message log" data-phone="<?php echo htmlspecialchars($wa_number, ENT_QUOTES); ?>" data-name="<?php echo htmlspecialchars($g->Name, ENT_QUOTES); ?>">
														<i class="la la-comments"></i>
													</button>
												<?php } ?>
												<?php if(!$is_ghl_row) { ?>
													<button type="button" class="btn btn-icon btn-light-primary btn-xs contact-edit-btn ml-1" data-toggle="tooltip" title="Edit contact number">
														<i class="la la-pencil"></i>
													</button>
												<?php } ?>
											</span>
										</td>
										<?php $email_val = (string) $g->Email; ?>
										<td class="gl-cell<?php if(!$is_ghl_row) echo ' gl-editable'; ?>" style="text-align:center;"<?php if(!$is_ghl_row) { ?> data-field="email" data-dedup-key="<?php echo htmlspecialchars($g->dedup_key, ENT_QUOTES); ?>" data-value="<?php echo htmlspecialchars($email_val, ENT_QUOTES); ?>"<?php } ?>>
											<span class="gl-display">
												<span class="gl-text"><?php if($email_val !== '') { echo htmlspecialchars($email_val); } else { echo '<span class="text-muted">&mdash;</span>'; } ?></span>
												<?php if(!$is_ghl_row) { ?>
													<button type="button" class="btn btn-icon btn-light-primary btn-xs gl-edit-btn ml-1" data-toggle="tooltip" title="Edit email"><i class="la la-pencil"></i></button>
												<?php } ?>
											</span>
										</td>
										<?php $lang_val = (string) $g->Language; ?>
										<td class="gl-cell<?php if(!$is_ghl_row) echo ' gl-editable'; ?>" style="text-align:center;"<?php if(!$is_ghl_row) { ?> data-field="language" data-dedup-key="<?php echo htmlspecialchars($g->dedup_key, ENT_QUOTES); ?>" data-value="<?php echo htmlspecialchars($lang_val, ENT_QUOTES); ?>"<?php } ?>>
											<span class="gl-display">
												<span class="gl-text"><?php if($lang_val !== '') { echo htmlspecialchars($lang_val); } else { echo '<span class="text-muted">&mdash;</span>'; } ?></span>
												<?php if(!$is_ghl_row) { ?>
													<button type="button" class="btn btn-icon btn-light-primary btn-xs gl-edit-btn ml-1" data-toggle="tooltip" title="Edit language"><i class="la la-pencil"></i></button>
												<?php } ?>
											</span>
										</td>
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
										<td style="text-align:center; white-space:nowrap;">
											<?php
												$gtype = isset($g->GuestType) ? (string) $g->GuestType : '';
												if($gtype === 'ADULT')       { $gt_cls = 'label-light-primary'; $gt_txt = 'Adult'; }
												elseif($gtype === 'CHILD')   { $gt_cls = 'label-light-info';    $gt_txt = 'Child'; }
												elseif($gtype === 'INFANT')  { $gt_cls = 'label-light-warning'; $gt_txt = 'Infant'; }
												else                         { $gt_cls = '';                    $gt_txt = ''; }
											?>
											<?php if($gt_txt !== ''): ?>
												<span class="label label-inline label-pill <?php echo $gt_cls; ?> font-weight-bold"><?php echo $gt_txt; ?></span>
											<?php else: ?>
												<span class="text-muted">&mdash;</span>
											<?php endif; ?>
										</td>
										<td style="text-align:center;">
											<?php
												if(!empty($g->DOB) && $g->DOB !== '0000-00-00') {
													echo date('d M Y', strtotime($g->DOB));
												}
											?>
										</td>
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
														if($is_ghl_row) {
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
											<?php if(!$is_ghl_row) { ?>
												<div class="btn-group">
													<button type="button" data-toggle="dropdown" class="btn btn-light-primary btn-sm dropdown-toggle" style="padding-left:3px;"></button>
													<div class="dropdown-menu">
														<a href="<?php echo base_url('Guests/View?key=') . urlencode($g->dedup_key); ?>" class="dropdown-item" style="font-size:11px;">View Trip History</a>
														<?php $rc = isset($remark_counts[$g->dedup_key]) ? (int) $remark_counts[$g->dedup_key] : 0; ?>
														<a href="javascript:;" class="dropdown-item js-remarks" style="font-size:11px;" data-dedup-key="<?php echo htmlspecialchars($g->dedup_key, ENT_QUOTES); ?>" data-name="<?php echo htmlspecialchars($g->Name, ENT_QUOTES); ?>">Remarks<?php if($rc > 0) { echo ' (' . $rc . ')'; } ?></a>
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

<?php $this->load->view('partials/message_log_modal'); ?>

<!-- Guest remarks modal: a dated remark log per guest (multiple entries). -->
<div class="modal fade" id="guest_remarks_modal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header" style="background-color:#D7E2F2;">
				<h5 class="modal-title" style="color:#6082B6;">
					<i class="la la-sticky-note"></i> Remarks &mdash; <span id="gr_guest_name" class="font-weight-bold"></span>
				</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			</div>
			<div class="modal-body">
				<!-- Add form -->
				<div class="form-group row mb-2">
					<div class="col-md-4">
						<label class="font-weight-bold" style="font-size:12px;">Date &amp; Time</label>
						<input type="datetime-local" id="gr_datetime" class="form-control">
					</div>
					<div class="col-md-6">
						<label class="font-weight-bold" style="font-size:12px;">Remark</label>
						<input type="text" id="gr_remark" class="form-control" maxlength="1000" placeholder="e.g. Called guest, will decide next week">
					</div>
					<div class="col-md-2 d-flex align-items-end">
						<button type="button" id="gr_add" class="btn btn-light-success font-weight-bold btn-block">
							<i class="la la-plus"></i> Add
						</button>
					</div>
				</div>
				<div id="gr_error" class="text-danger font-weight-bold mb-2" style="font-size:12px; display:none;"></div>
				<hr>
				<!-- Existing remarks -->
				<div id="gr_list">
					<div class="text-muted text-center py-3"><i class="la la-spinner la-spin"></i>&nbsp; Loading remarks…</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
	<?php
		$expanded_keys = array('q', 'booking_number', 'contact_number', 'email', 'destination', 'role', 'pax_min', 'pax_max', 'sales_agent', 'source', 'customer_type', 'nationality', 'gender', 'guest_type', 'language', 'booking_date', 'travel_date', 'team_leader', 'dob', 'birthday');
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
	function Reset_Dob() {
		$('#kt_daterangepicker_guests_dob input').val('');
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

	// DOB spans decades, so show month/year dropdowns and cap the range at today
	// (nobody is born in the future). The plugin floors the end-year dropdown at the
	// current start date's year, so open the picker on the full 1920..today span and
	// unlink the two calendars — that way either side's year can be picked freely.
	// autoUpdateInput:false keeps the input empty (placeholder) until a range is chosen.
	$('#kt_daterangepicker_guests_dob').daterangepicker({
		buttonClasses: ' btn',
		applyClass: 'btn-primary',
		cancelClass: 'btn-secondary',
		autoApply: true,
		showDropdowns: true,
		linkedCalendars: false,
		autoUpdateInput: false,
		minYear: 1920,
		maxYear: moment().year(),
		minDate: moment('1920-01-01'),
		maxDate: moment(),
		startDate: moment('1920-01-01'),
		endDate: moment()
	}, function(start, end, label) {
		$('#kt_daterangepicker_guests_dob .form-control').val(start.format('DD/MM/YYYY') + ' - ' + end.format('DD/MM/YYYY'));
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

	// ----- Generic inline field edit (First Name / Email / Language) -----
	var GL_FIELD_UPDATE_URL = '<?php echo base_url('Guests/Update_Field'); ?>';
	var GL_LANGUAGES = <?php echo json_encode(isset($edit_languages) ? array_values($edit_languages) : array('CN', 'EN', 'ML')); ?>;

	var GL_TITLES = { name: 'Edit first name', email: 'Edit email', language: 'Edit language' };

	function glEscape(v) { return $('<span>').text(v == null ? '' : v).html(); }

	function glRenderDisplay($cell) {
		$cell.find('[data-toggle="tooltip"]').tooltip('dispose');
		var field = $cell.attr('data-field');
		var val   = $cell.attr('data-value') || '';
		var text  = val === '' ? '<span class="text-muted">&mdash;</span>' : glEscape(val);
		$cell.html(
			'<span class="gl-display"><span class="gl-text">' + text + '</span>' +
			'<button type="button" class="btn btn-icon btn-light-primary btn-xs gl-edit-btn ml-1" data-toggle="tooltip" title="' + (GL_TITLES[field] || 'Edit') + '">' +
			'<i class="la la-pencil"></i></button></span>'
		);
		$cell.find('[data-toggle="tooltip"]').tooltip();
	}

	$('#kt_datatable').on('click', '.gl-edit-btn', function() {
		var $cell = $(this).closest('.gl-cell');
		if ($cell.find('.gl-editor').length) { return; }
		var field   = $cell.attr('data-field');
		var current = $cell.attr('data-value') || '';
		$cell.find('[data-toggle="tooltip"]').tooltip('dispose');

		var control;
		if (field === 'language') {
			var opts = '<option value=""></option>';
			for (var i = 0; i < GL_LANGUAGES.length; i++) {
				var code = GL_LANGUAGES[i];
				opts += '<option value="' + glEscape(code) + '"' + (code === current ? ' selected' : '') + '>' + glEscape(code) + '</option>';
			}
			control = '<select class="form-control gl-input">' + opts + '</select>';
		} else {
			control = '<input type="' + (field === 'email' ? 'email' : 'text') + '" class="form-control gl-input" value="' + glEscape(current) + '" autocomplete="off">';
		}

		$cell.html(
			'<span class="gl-editor">' + control +
				'<button type="button" class="btn btn-icon btn-light-success btn-xs gl-save" data-toggle="tooltip" title="Save"><i class="la la-check"></i></button>' +
				'<button type="button" class="btn btn-icon btn-light-danger btn-xs gl-cancel" data-toggle="tooltip" title="Cancel"><i class="la la-times"></i></button>' +
			'</span>' +
			'<span class="gl-error" style="display:none;"></span>'
		);
		$cell.find('[data-toggle="tooltip"]').tooltip();
		var $input = $cell.find('.gl-input').focus();
		if (field !== 'language') { $input.select(); }
	});

	$('#kt_datatable').on('click', '.gl-cancel', function() {
		glRenderDisplay($(this).closest('.gl-cell'));
	});

	$('#kt_datatable').on('keydown', '.gl-editor input.gl-input', function(e) {
		if (e.which === 13) { e.preventDefault(); $(this).closest('.gl-cell').find('.gl-save').click(); }
		else if (e.which === 27) { e.preventDefault(); glRenderDisplay($(this).closest('.gl-cell')); }
	});

	$('#kt_datatable').on('click', '.gl-save', function() {
		var $btn   = $(this);
		var $cell  = $btn.closest('.gl-cell');
		var $input = $cell.find('.gl-input');
		var $error = $cell.find('.gl-error');
		var field  = $cell.attr('data-field');
		var value  = $.trim($input.val());

		$btn.tooltip('hide');
		$error.hide().text('');
		$btn.prop('disabled', true).find('i').attr('class', 'la la-spinner la-spin');

		$.ajax({
			url: GL_FIELD_UPDATE_URL,
			method: 'POST',
			dataType: 'json',
			data: { dedup_key: $cell.attr('data-dedup-key'), field: field, value: value },
			timeout: 30000
		}).done(function(res) {
			if (res && res.ok) {
				$cell.attr('data-value', (res.value != null) ? res.value : value);
				glRenderDisplay($cell);
			} else {
				$error.text((res && res.message) ? res.message : 'Could not update.').show();
				$btn.prop('disabled', false).find('i').attr('class', 'la la-check');
			}
		}).fail(function() {
			$error.text('Network error. Please try again.').show();
			$btn.prop('disabled', false).find('i').attr('class', 'la la-check');
		});
	});

	// ----- Guest remarks (dated remark log, multiple per guest) -----
	// Endpoints always live on the Guests controller — the Remarks action only
	// renders on booking-guest rows, never on the GHL Leads page.
	var GR_LIST_URL   = '<?php echo base_url('Guests/Remarks'); ?>';
	var GR_ADD_URL    = '<?php echo base_url('Guests/Add_Remark'); ?>';
	var GR_DELETE_URL = '<?php echo base_url('Guests/Delete_Remark'); ?>';
	var GR_MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
	var grDedupKey = '';

	function grEscape(v) { return $('<span>').text(v == null ? '' : v).html(); }

	function grPad(n) { return (n < 10 ? '0' : '') + n; }

	// Pretty-print a "YYYY-MM-DD HH:MM:SS" datetime as "13 Jul 2026 15:30".
	function grFormatDateTime(raw) {
		var m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(raw || '');
		if (!m) { return grEscape(raw); }
		return grPad(parseInt(m[3], 10)) + ' ' + GR_MONTHS[parseInt(m[2], 10) - 1] + ' ' + m[1] + ' ' + m[4] + ':' + m[5];
	}

	// Local "now" as the value a datetime-local input expects.
	function grNowLocal() {
		var d = new Date();
		return d.getFullYear() + '-' + grPad(d.getMonth() + 1) + '-' + grPad(d.getDate()) +
			'T' + grPad(d.getHours()) + ':' + grPad(d.getMinutes());
	}

	function grRenderList(remarks) {
		var $list = $('#gr_list');
		if (!remarks || !remarks.length) {
			$list.html('<div class="text-muted text-center py-3">No remarks yet.</div>');
			return;
		}
		var html = '<div>';
		for (var i = 0; i < remarks.length; i++) {
			var r = remarks[i];
			html += '<div class="d-flex align-items-start border-bottom py-2" data-remark-id="' + r.id + '">' +
				'<div class="flex-grow-1">' +
					'<div class="font-weight-bold text-dark-75" style="font-size:12px;">' +
						'<i class="la la-clock text-primary"></i> ' + grFormatDateTime(r.remark_at) +
						(r.created_by ? ' <span class="text-muted font-weight-normal">— ' + grEscape(r.created_by) + '</span>' : '') +
					'</div>' +
					'<div class="text-dark-75" style="font-size:13px; white-space:pre-wrap;">' + grEscape(r.remark) + '</div>' +
				'</div>' +
				(r.can_delete ?
					'<button type="button" class="btn btn-icon btn-light-danger btn-xs gr-delete ml-2" data-id="' + r.id + '" data-toggle="tooltip" title="Delete remark"><i class="la la-trash"></i></button>' : '') +
			'</div>';
		}
		html += '</div>';
		$list.html(html);
		$list.find('[data-toggle="tooltip"]').tooltip();
	}

	function grLoad() {
		$('#gr_list').html('<div class="text-muted text-center py-3"><i class="la la-spinner la-spin"></i>&nbsp; Loading remarks…</div>');
		$.ajax({ url: GR_LIST_URL, method: 'GET', dataType: 'json', data: { dedup_key: grDedupKey }, timeout: 30000 })
			.done(function(res) {
				if (res && res.ok) { grRenderList(res.remarks); }
				else { $('#gr_list').html('<div class="text-danger text-center py-3">' + grEscape((res && res.message) || 'Could not load remarks.') + '</div>'); }
			})
			.fail(function() { $('#gr_list').html('<div class="text-danger text-center py-3">Network error. Please try again.</div>'); });
	}

	// Bump the "(n)" badge on the row's Remarks action by $delta.
	function grBumpBadge(delta) {
		var $link = $('.js-remarks[data-dedup-key="' + grDedupKey.replace(/"/g, '\\"') + '"]');
		$link.each(function() {
			var $a = $(this);
			var n = parseInt(($a.text().match(/\((\d+)\)/) || [0, 0])[1], 10) + delta;
			$a.text('Remarks' + (n > 0 ? ' (' + n + ')' : ''));
		});
	}

	// Bound on document (not #kt_datatable) because this link lives inside a
	// Bootstrap dropdown menu, which Popper can reposition out of the table.
	$(document).on('click', '.js-remarks', function() {
		grDedupKey = $(this).attr('data-dedup-key') || '';
		$('#gr_guest_name').text($(this).attr('data-name') || '');
		$('#gr_error').hide().text('');
		$('#gr_datetime').val(grNowLocal());
		$('#gr_remark').val('');
		$('#guest_remarks_modal').modal('show');
		grLoad();
	});

	$('#gr_add').on('click', function() {
		var $btn    = $(this);
		var $error  = $('#gr_error');
		var datetime = $('#gr_datetime').val();
		var remark   = $.trim($('#gr_remark').val());

		$error.hide().text('');
		$btn.prop('disabled', true);

		$.ajax({
			url: GR_ADD_URL, method: 'POST', dataType: 'json', timeout: 30000,
			data: { dedup_key: grDedupKey, remark_at: datetime, remark: remark }
		}).done(function(res) {
			$btn.prop('disabled', false);
			if (res && res.ok) {
				$('#gr_remark').val('');
				grBumpBadge(1);
				grLoad();
			} else {
				$error.text((res && res.message) || 'Could not add remark.').show();
			}
		}).fail(function() {
			$btn.prop('disabled', false);
			$error.text('Network error. Please try again.').show();
		});
	});

	// Enter in the remark box submits the add form.
	$('#gr_remark').on('keydown', function(e) {
		if (e.which === 13) { e.preventDefault(); $('#gr_add').click(); }
	});

	$('#gr_list').on('click', '.gr-delete', function() {
		var $btn = $(this);
		var id   = $btn.attr('data-id');
		$btn.tooltip('hide').prop('disabled', true).find('i').attr('class', 'la la-spinner la-spin');
		$.ajax({ url: GR_DELETE_URL, method: 'POST', dataType: 'json', data: { id: id }, timeout: 30000 })
			.done(function(res) {
				if (res && res.ok) { grBumpBadge(-1); grLoad(); }
				else {
					$('#gr_error').text((res && res.message) || 'Could not delete remark.').show();
					$btn.prop('disabled', false).find('i').attr('class', 'la la-trash');
				}
			})
			.fail(function() {
				$('#gr_error').text('Network error. Please try again.').show();
				$btn.prop('disabled', false).find('i').attr('class', 'la la-trash');
			});
	});
</script>
