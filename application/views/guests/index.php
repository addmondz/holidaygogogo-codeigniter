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
				<?php if($list_base === 'Customer') { ?>
					<div class="card-toolbar">
						<a href="<?php echo base_url('Customer/Create'); ?>" class="btn btn-primary font-weight-bold mr-1 mb-2" style="width:180px;">
							<i class="la la-user-alt"></i>Create Customer
						</a>
						<a href="<?php echo base_url('Customer/Import_Template'); ?>" class="btn btn-light-info font-weight-bold mr-1 mb-2" style="width:180px;" data-toggle="tooltip" title="Download the blank Excel template to bulk-create customers">
							<i class="la la-file-download"></i>Import Template
						</a>
						<button type="button" class="btn btn-light-success font-weight-bold mr-1 mb-2" style="width:180px;" data-toggle="modal" data-target="#customer_import_modal" title="Upload a filled template to bulk-create customers">
							<i class="la la-file-import"></i>Bulk Import
						</button>
						<?php $current_url = base_url($_SERVER['REQUEST_URI']); ?>
						<a href="<?php if(strpos($current_url, '?') == true) { echo base_url('Customer/Download?') . (explode('?', $current_url))[1]; } else { echo base_url('Customer/Download'); } ?>" class="btn btn-light-warning font-weight-bold mb-2" style="width:180px;">
							<i class="las la-arrow-circle-down"></i>Customer Records
						</a>
					</div>
				<?php } ?>
				<?php if($list_base === 'Ghl_Leads') { ?>
					<div class="card-toolbar">
						<button type="button" class="btn btn-primary font-weight-bold mb-2" style="width:180px;" data-toggle="modal" data-target="#ghl_lead_create_modal" title="Add a lead by hand (stored as a Manual lead)">
							<i class="la la-user-plus"></i>Create Lead
						</button>
					</div>
				<?php } ?>
			</div>
			<div class="card-body">
				<?php if($list_base === 'Ghl_Leads' && $this->session->flashdata('ghl_lead_success')) { ?>
					<div class="alert alert-light-success font-weight-bold" role="alert" style="border-left:4px solid #1bc5bd;">
						<?php echo htmlspecialchars($this->session->flashdata('ghl_lead_success')); ?>
					</div>
				<?php } ?>
				<?php if($list_base === 'Ghl_Leads' && $this->session->flashdata('ghl_lead_error')) { ?>
					<div class="alert alert-light-danger font-weight-bold" role="alert" style="border-left:4px solid #f64e60;">
						<?php echo htmlspecialchars($this->session->flashdata('ghl_lead_error')); ?>
					</div>
				<?php } ?>
				<?php if($list_base === 'Customer' && $this->session->flashdata('customer_import_success')) { ?>
					<div class="alert alert-light-success font-weight-bold" role="alert" style="border-left:4px solid #1bc5bd;">
						<?php echo htmlspecialchars($this->session->flashdata('customer_import_success')); ?>
					</div>
				<?php } ?>
				<?php if($list_base === 'Customer' && $this->session->flashdata('customer_import_error')) { ?>
					<div class="alert alert-light-danger font-weight-bold" role="alert" style="border-left:4px solid #f64e60;">
						<?php echo htmlspecialchars($this->session->flashdata('customer_import_error')); ?>
					</div>
				<?php } ?>
				<div class="accordion accordion-solid accordion-toggle-plus">
					<div class="card">
						<div class="card-header">
							<div id="guests_header" data-toggle="collapse" data-target="#guests_info" class="card-title collapsed" style="font-size:13px;">Filter By Guest Information</div>
						</div>
						<div id="guests_info" class="collapse">
							<div class="card-body">
								<form action="<?php echo base_url($list_base) ?>" method="get" class="form">
										<?php if($list_base !== 'Ghl_Leads') { ?>
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
										<?php if($list_base !== 'Ghl_Leads') { ?>
										<div class="col-md-3">
											<div class="form-group">
												<label>Campaign Date
													<a onclick="Reset_Campaign_Date()" class="btn btn-icon btn-light-warning btn-xs" data-toggle="tooltip" title="Clear campaign date">
														<i class="la la-undo"></i>
													</a>
												</label>
												<div id="kt_daterangepicker_guests_campaign" class="input-icon">
													<input readonly type="text" name="campaign_date" value="<?php if(!empty($this->input->get('campaign_date'))) { echo $this->input->get('campaign_date'); } ?>" autocomplete="off" class="form-control" placeholder="Remark campaign date…">
													<span><i class="la la-bullhorn"></i></span>
												</div>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label>Follow Up Date
													<a onclick="Reset_Follow_Date()" class="btn btn-icon btn-light-warning btn-xs" data-toggle="tooltip" title="Clear follow date">
														<i class="la la-undo"></i>
													</a>
												</label>
												<div id="kt_daterangepicker_guests_follow" class="input-icon">
													<input readonly type="text" name="follow_date" value="<?php if(!empty($this->input->get('follow_date'))) { echo $this->input->get('follow_date'); } ?>" autocomplete="off" class="form-control" placeholder="Remark follow-up date…">
													<span><i class="la la-bell"></i></span>
												</div>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label>Customer Code</label>
												<div class="input-icon">
													<input type="text" name="customer_code" value="<?php if(!empty($this->input->get('customer_code'))) { echo htmlspecialchars($this->input->get('customer_code'), ENT_QUOTES); } ?>" autocomplete="off" class="form-control" placeholder="e.g. HGG-A0001">
													<span><i class="la la-id-card"></i></span>
												</div>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label>Date Creation
													<a onclick="Reset_Create_Date()" class="btn btn-icon btn-light-warning btn-xs" data-toggle="tooltip" title="Clear date creation">
														<i class="la la-undo"></i>
													</a>
												</label>
												<div id="kt_daterangepicker_guests_create" class="input-icon">
													<input readonly type="text" name="create_date" value="<?php if(!empty($this->input->get('create_date'))) { echo $this->input->get('create_date'); } ?>" autocomplete="off" class="form-control" placeholder="Customer created between…">
													<span><i class="la la-calendar-plus"></i></span>
												</div>
											</div>
										</div>
										<?php } ?>
									</div>
									<?php } else { ?>
										<div class="row">
											<div class="col-md-3">
												<div class="form-group">
													<label>Search Name</label>
													<div class="input-icon">
														<input type="text" name="q" value="<?php if(!empty($this->input->get('q'))) { echo htmlspecialchars($this->input->get('q'), ENT_QUOTES); } ?>" autocomplete="off" class="form-control" placeholder="Lead name">
														<span><i class="la la-user"></i></span>
													</div>
												</div>
											</div>
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
													<label>Lead Capture Date
														<a onclick="Reset_Booking_Date()" class="btn btn-icon btn-light-warning btn-xs" data-toggle="tooltip" title="Clear lead capture date">
															<i class="la la-undo"></i>
														</a>
													</label>
													<div id="kt_daterangepicker_guests_booking" class="input-icon">
														<input readonly type="text" name="booking_date" value="<?php if(!empty($this->input->get('booking_date'))) { echo $this->input->get('booking_date'); } ?>" autocomplete="off" class="form-control">
														<span><i class="la la-calendar"></i></span>
													</div>
												</div>
											</div>
											<div class="row">
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
												<div class="col-md-3">
													<div class="form-group">
														<label>Race</label>
														<div class="input-icon">
															<input type="text" name="race" value="<?php if(!empty($this->input->get('race'))) { echo htmlspecialchars($this->input->get('race'), ENT_QUOTES); } ?>" autocomplete="off" class="form-control" placeholder="e.g. Muslim">
															<span><i class="la la-users"></i></span>
														</div>
													</div>
												</div>
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
											</div>
										</div>
										<?php } ?>
										<input type="submit" value="Filter" class="btn btn-light-success font-weight-bold" style="width:80px;">
									<input type="button" id="reset" value="Reset" class="btn btn-light-primary font-weight-bold" style="width:80px;">
								</form>
							</div>
						</div>
					</div>
				</div>

				<br><br>
				<div class="dataTables_wrapper dt-bootstrap4 no-footer" <?php if(empty($guests)) { echo 'style="overflow-x:auto;"'; } ?>>
					<table id="kt_datatable" class="table table-bordered table-head-custom table-checkable dataTable no-footer dtr-inline"<?php if($list_base === 'Customer') { echo ' data-no-datatable="1"'; } ?>>
						<thead>
							<tr>
								<th style="text-align:center;">No.</th>
								<?php if($list_base !== 'Ghl_Leads') { ?>
									<th style="text-align:center;">Alt Name</th>
								<?php } ?>
								<th style="text-align:center;">Guest First Name</th>
								<th style="text-align:center;">Contact Num</th>
								<?php if($list_base !== 'Ghl_Leads') { ?>
									<th style="text-align:center;">Email</th>
								<?php } ?>
								<?php if($list_base === 'Ghl_Leads') { ?>
									<th style="text-align:center;">Tags</th>
									<th style="text-align:center;">Type</th>
									<th style="text-align:center;">Gender</th>
									<th style="text-align:center;">Language</th>
									<th style="text-align:center;">Race</th>
									<th style="text-align:center;">Nationality</th>
									<th style="text-align:center;">Date of Birth</th>
								<?php } ?>
								<?php if($list_base !== 'Ghl_Leads') { ?>
									<th style="text-align:center;">Language</th>
									<th style="text-align:center;">Guest Type</th>
									<th style="text-align:center;">Destination</th>
									<th style="text-align:center;">Customer Code</th>
									<?php if($list_base === 'Customer') {
										// Server-side sort toggle on customer Date Creation. Preserve all
										// current filters, drop page (jump back to page 1 on re-sort).
										$cur_dir  = (isset($sort_dir) && strtoupper($sort_dir) === 'ASC') ? 'ASC' : 'DESC';
										$next_dir = ($cur_dir === 'ASC') ? 'DESC' : 'ASC';
										$q = $this->input->get();
										unset($q['page']);
										$q['dir'] = $next_dir;
										// Relative query-string only (same as _pagination.php) so the click
										// navigates the CURRENT URL and never hits a trailing-slash / index.php
										// redirect that would drop the ?dir= param.
										$sort_url  = '?' . http_build_query($q);
										$caret     = ($cur_dir === 'ASC') ? 'la-arrow-up' : 'la-arrow-down';
										$sort_hint = ($next_dir === 'ASC') ? 'Sort oldest first' : 'Sort newest first';
									?>
										<th style="text-align:center;">
											<a href="<?php echo htmlspecialchars($sort_url, ENT_QUOTES); ?>" class="js-customer-sort text-dark font-weight-bold" title="<?php echo $sort_hint; ?>" style="white-space:nowrap;">
												Date Creation <i class="la <?php echo $caret; ?>"></i>
											</a>
										</th>
									<?php } else { ?>
										<th style="text-align:center;">Date Creation</th>
									<?php } ?>
								<?php } ?>
								<?php if($list_base === 'Customer') { ?>
									<th style="text-align:center;">AutoCount Sync</th>
								<?php } ?>
								<?php if($list_base !== 'Ghl_Leads') { ?>
									<th class="action" style="text-align:center;">Action</th>
								<?php } ?>
							</tr>
						</thead>
						<tbody>
							<?php if(empty($guests)) { ?>
								<tr><td colspan="<?php echo ($list_base === 'Customer') ? 12 : (($list_base !== 'Ghl_Leads') ? 11 : 10); ?>" style="text-align:center; padding-top:10px; padding-bottom:10px;">Guest Records Not Found</td></tr>
							<?php } else { ?>
								<?php $count = 1; foreach($guests as $g) { ?>
									<?php $is_ghl_row = isset($g->Type) && ($g->Type === 'GHL' || $g->Type === 'Manual'); ?>
									<tr>
										<td style="text-align:center; padding-top:15px; padding-bottom:15px;"><?php echo $count; ?></td>
										<?php if($list_base !== 'Ghl_Leads') {
											$altname_val = isset($g->AltName) ? (string) $g->AltName : '';
											$alt_customer_id = isset($g->CustomerID) ? (int) $g->CustomerID : 0;
											// Editable only when the row maps to a real customer (GHL/leader-
											// fallback rows without a customer stay read-only).
											$altname_editable = !$is_ghl_row && $alt_customer_id > 0;
										?>
											<td class="gl-cell<?php if($altname_editable) echo ' gl-editable'; ?>" style="text-align:center;"<?php if($altname_editable) { ?> data-field="altname" data-customer-id="<?php echo $alt_customer_id; ?>" data-value="<?php echo htmlspecialchars($altname_val, ENT_QUOTES); ?>"<?php } ?>>
												<span class="gl-display">
													<span class="gl-text"><?php if($altname_val !== '') { echo htmlspecialchars($altname_val); } else { echo '<span class="text-muted">&mdash;</span>'; } ?></span>
													<?php if($altname_editable) { ?>
														<button type="button" class="btn btn-icon btn-light-primary btn-xs gl-edit-btn ml-1" data-toggle="tooltip" title="Edit alt name"><i class="la la-pencil"></i></button>
													<?php } ?>
												</span>
											</td>
										<?php } ?>
										<?php $name_val = (string) $g->Name; ?>
										<td class="gl-cell<?php if(!$is_ghl_row) echo ' gl-editable'; ?>" style="text-align:center;"<?php if(!$is_ghl_row) { ?> data-field="name" data-dedup-key="<?php echo htmlspecialchars($g->dedup_key, ENT_QUOTES); ?>" data-value="<?php echo htmlspecialchars($name_val, ENT_QUOTES); ?>"<?php } ?>>
											<span class="gl-display">
												<span class="gl-text"><?php if($name_val !== '') { echo htmlspecialchars($name_val); } else { echo '<span class="text-muted">&mdash;</span>'; } ?></span>
												<?php if(!$is_ghl_row) { ?>
													<button type="button" class="btn btn-icon btn-light-primary btn-xs gl-edit-btn ml-1" data-toggle="tooltip" title="Edit first name"><i class="la la-pencil"></i></button>
												<?php } ?>
											</span>
										</td>
										<?php
											$calling_code = isset($g->CallingCode) ? (string)$g->CallingCode : '';
											// A merged row (same Name + IC across bookings) packs every distinct
											// phone into ContactNumbers so we can show them all; pages that don't
											// merge (Customer list) just carry the single ContactNum. The first
											// entry is the representative — the one the inline editor edits
											// (data-mobile below matches $g->ContactNum).
											if(isset($g->ContactNumbers) && (string)$g->ContactNumbers !== '') {
												$phones = guest_contact_parse_multi($g->ContactNumbers);
											} else {
												$phones = array();
												if((string)$g->ContactNum !== '') {
													$phones[] = array('calling_code' => $calling_code, 'mobile' => (string)$g->ContactNum);
												}
											}
										?>
										<td class="contact-cell<?php if(!$is_ghl_row) echo ' contact-editable'; ?>" style="text-align:center; white-space:nowrap;"<?php if(!$is_ghl_row) { ?> data-dedup-key="<?php echo htmlspecialchars($g->dedup_key, ENT_QUOTES); ?>" data-mobile="<?php echo htmlspecialchars((string)$g->ContactNum, ENT_QUOTES); ?>" data-calling-code="<?php echo htmlspecialchars($calling_code, ENT_QUOTES); ?>"<?php if($list_base === 'Customer' && !empty($g->CustomerID)) { ?> data-customer-id="<?php echo (int)$g->CustomerID; ?>"<?php } ?><?php } ?>>
											<span class="contact-display">
												<?php if(empty($phones)) { ?>
													<span class="contact-num"><span class="text-muted">&mdash;</span></span>
												<?php } else { foreach($phones as $pi => $ph) {
													$contact_display = guest_contact_format_display($ph['calling_code'], $ph['mobile']);
													$wa_number       = guest_contact_wa_digits($ph['calling_code'], $ph['mobile']);
												?>
													<span class="contact-line"<?php if($pi > 0) echo ' style="display:block; margin-top:4px;"'; ?>>
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
													</span>
												<?php } } ?>
												<?php if(!$is_ghl_row) { ?>
													<button type="button" class="btn btn-icon btn-light-primary btn-xs contact-edit-btn ml-1" data-toggle="tooltip" title="<?php echo (count($phones) > 1) ? 'Edit primary contact number' : 'Edit contact number'; ?>">
														<i class="la la-pencil"></i>
													</button>
												<?php } ?>
											</span>
										</td>
										<?php if($list_base !== 'Ghl_Leads') { ?>
										<?php $email_val = (string) $g->Email; ?>
										<td class="gl-cell<?php if(!$is_ghl_row) echo ' gl-editable'; ?>" style="text-align:center;"<?php if(!$is_ghl_row) { ?> data-field="email" data-dedup-key="<?php echo htmlspecialchars($g->dedup_key, ENT_QUOTES); ?>" data-value="<?php echo htmlspecialchars($email_val, ENT_QUOTES); ?>"<?php } ?>>
											<span class="gl-display">
												<span class="gl-text"><?php if($email_val !== '') { echo htmlspecialchars($email_val); } else { echo '<span class="text-muted">&mdash;</span>'; } ?></span>
												<?php if(!$is_ghl_row) { ?>
													<button type="button" class="btn btn-icon btn-light-primary btn-xs gl-edit-btn ml-1" data-toggle="tooltip" title="Edit email"><i class="la la-pencil"></i></button>
												<?php } ?>
											</span>
										</td>
										<?php } ?>
										<?php if($list_base === 'Ghl_Leads') { ?>
											<td style="text-align:center; max-width:220px;">
												<?php
													$lead_tags = $is_ghl_row ? ghl_lead_tags_parse(isset($g->Tags) ? $g->Tags : null) : array();
													if(!empty($lead_tags)) {
														foreach($lead_tags as $tag) {
															echo '<span class="label label-inline label-light-primary font-weight-bold mr-1 mb-1" style="white-space:normal;">'
																. htmlspecialchars($tag) . '</span>';
														}
													} else {
														echo '<span class="text-muted">&mdash;</span>';
													}
												?>
											</td>
											<?php
												$lead_type = (isset($g->Type) && $g->Type === 'Manual') ? 'Manual' : 'GHL';
												$type_cls  = $lead_type === 'Manual' ? 'label-light-warning' : 'label-light-info';
											?>
											<td style="text-align:center;">
												<span class="label label-inline font-weight-bold <?php echo $type_cls; ?>"><?php echo $lead_type; ?></span>
											</td>
											<?php
												// Gender / Language / Race / Nationality / DOB are stored only for
												// manual leads; synced GHL contacts leave them blank (shown "—").
												$gl_dash = '<span class="text-muted">&mdash;</span>';
												$gender_val = isset($g->Gender)      ? trim((string) $g->Gender)      : '';
												$lang_val   = isset($g->Language)    ? trim((string) $g->Language)    : '';
												$race_val   = isset($g->Race)        ? trim((string) $g->Race)        : '';
												$nat_val    = isset($g->Nationality) ? trim((string) $g->Nationality) : '';
												$dob_val    = isset($g->DOB)         ? trim((string) $g->DOB)         : '';
												$dob_show   = ($dob_val !== '' && $dob_val !== '0000-00-00' && strtotime($dob_val) !== false)
													? date('d M Y', strtotime($dob_val)) : '';
											?>
											<td style="text-align:center;"><?php echo $gender_val !== '' ? htmlspecialchars($gender_val) : $gl_dash; ?></td>
											<td style="text-align:center;"><?php echo $lang_val   !== '' ? htmlspecialchars($lang_val)   : $gl_dash; ?></td>
											<td style="text-align:center;"><?php echo $race_val   !== '' ? htmlspecialchars($race_val)   : $gl_dash; ?></td>
											<td style="text-align:center;"><?php echo $nat_val    !== '' ? htmlspecialchars($nat_val)    : $gl_dash; ?></td>
											<td style="text-align:center;"><?php echo $dob_show   !== '' ? htmlspecialchars($dob_show)   : $gl_dash; ?></td>
										<?php } ?>
										<?php if($list_base !== 'Ghl_Leads') { ?>
										<?php $lang_val = (string) $g->Language; ?>
										<td class="gl-cell<?php if(!$is_ghl_row) echo ' gl-editable'; ?>" style="text-align:center;"<?php if(!$is_ghl_row) { ?> data-field="language" data-dedup-key="<?php echo htmlspecialchars($g->dedup_key, ENT_QUOTES); ?>" data-value="<?php echo htmlspecialchars($lang_val, ENT_QUOTES); ?>"<?php } ?>>
											<span class="gl-display">
												<span class="gl-text"><?php if($lang_val !== '') { echo htmlspecialchars($lang_val); } else { echo '<span class="text-muted">&mdash;</span>'; } ?></span>
												<?php if(!$is_ghl_row) { ?>
													<button type="button" class="btn btn-icon btn-light-primary btn-xs gl-edit-btn ml-1" data-toggle="tooltip" title="Edit language"><i class="la la-pencil"></i></button>
												<?php } ?>
											</span>
										</td>
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
										<?php } ?>
										<?php if($list_base !== 'Ghl_Leads') { ?>
											<td style="text-align:center;">
												<?php
													$dests = array();
													foreach(explode('||', isset($g->Destination) ? (string)$g->Destination : '') as $d) {
														$d = trim($d);
														if($d === '' || $d === '-') { continue; }
														if(!in_array($d, $dests, true)) { $dests[] = $d; }
													}
													if(!empty($dests)) {
														$dest_out = array();
														foreach($dests as $d) { $dest_out[] = htmlspecialchars($d); }
														echo implode('<br>', $dest_out);
													} else {
														echo '<span class="text-muted">&mdash;</span>';
													}
												?>
											</td>
											<td style="text-align:center; white-space:nowrap;">
												<?php $cust_code = isset($g->CustomerCode) ? trim((string)$g->CustomerCode) : ''; ?>
												<?php if($cust_code !== '') { echo htmlspecialchars($cust_code); } else { echo '<span class="text-muted">&mdash;</span>'; } ?>
											</td>
											<td style="text-align:center; white-space:nowrap;">
												<?php
													$created_raw = isset($g->CustomerCreatedAt) ? (string)$g->CustomerCreatedAt : '';
													$created_ts  = ($created_raw !== '' && strpos($created_raw, '0000-00-00') !== 0) ? strtotime($created_raw) : false;
													if($created_ts) { echo date('d M Y', $created_ts); } else { echo '<span class="text-muted">&mdash;</span>'; }
												?>
											</td>
										<?php } ?>
										<?php if($list_base === 'Customer') { ?>
											<td style="text-align:center; white-space:nowrap;">
												<?php
													$sync_status = isset($g->AutocountSyncStatus) ? (string)$g->AutocountSyncStatus : '';
													if($sync_status === '' && $is_ghl_row) {
														echo '<span class="text-muted">&mdash;</span>';
													} else {
														$sync_map = mapAutocountSyncStatus($sync_status !== '' ? $sync_status : null);

														// Tooltip from the stored sync message (JSON or raw), matching supplier view.
														$sync_tip_attr = '';
														$sync_msg = isset($g->AutocountSyncMessage) ? (string)$g->AutocountSyncMessage : '';
														if($sync_msg !== '') {
															$decoded = json_decode($sync_msg, true);
															if(json_last_error() === JSON_ERROR_NONE && is_array($decoded) && array_key_exists('error', $decoded)) {
																$sync_tip = ($decoded['error'] === null)
																	? 'SUCCESS'
																	: 'ERROR: ' . (is_string($decoded['error']) ? $decoded['error'] : json_encode($decoded['error']));
															} else {
																$sync_tip = $sync_msg;
															}
															$sync_tip_attr = ' data-toggle="tooltip" data-placement="top" title="' . htmlspecialchars($sync_tip, ENT_QUOTES) . '"';
														}
												?>
													<span class="font-weight-bold" style="color:<?php echo $sync_map['color']; ?>;"<?php echo $sync_tip_attr; ?>><?php echo $sync_map['text']; ?></span>
												<?php } ?>
											</td>
										<?php } ?>
										<?php if($list_base !== 'Ghl_Leads') { ?>
										<td style="text-align:center;">
											<?php if(!$is_ghl_row) { ?>
												<div class="btn-group">
													<button type="button" data-toggle="dropdown" class="btn btn-light-primary btn-sm dropdown-toggle" style="padding-left:3px;"></button>
													<div class="dropdown-menu">
														<?php if($list_base === 'Customer' && !empty($g->CustomerID)) { ?>
															<a href="<?php echo base_url('Customer/Update?customer_id=') . $g->CustomerID; ?>" class="dropdown-item" style="font-size:11px;">Update Customer</a>
																														<?php
																$this->load->helper('utils');
																$portal_hash = generate_customer_portal_slug($g->CustomerID);
																if(!empty($portal_hash) && $portal_hash !== false):
															?>
																<a href="<?php echo base_url('customer/' . urlencode($portal_hash)); ?>" target="_blank" class="dropdown-item" style="font-size:11px;">Customer Portal</a>
															<?php endif; ?>
															<div class="dropdown-divider"></div>
														<?php } ?>
														<?php $rc = isset($remark_counts[$g->dedup_key]) ? (int) $remark_counts[$g->dedup_key] : 0; ?>
														<a href="javascript:;" class="dropdown-item js-remarks" style="font-size:11px;" data-dedup-key="<?php echo htmlspecialchars($g->dedup_key, ENT_QUOTES); ?>" data-name="<?php echo htmlspecialchars($g->Name, ENT_QUOTES); ?>">Remarks<?php if($rc > 0) { echo ' (' . $rc . ')'; } ?></a>
														<?php if($list_base === 'Customer' && !empty($g->CustomerID) && (int)$this->session->userdata('level') === 10) { ?>
															<a href="#" class="dropdown-item delete-customer text-danger" data-customer-id="<?php echo $g->CustomerID; ?>" data-customer-name="<?php echo htmlspecialchars($g->Name, ENT_QUOTES); ?>" style="font-size:11px;">Delete Customer</a>
														<?php } ?>
														<?php if($list_base !== 'Customer' && !empty($g->Token)) { ?>
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
										<?php } ?>
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
						<label class="font-weight-bold" style="font-size:12px;">Campaign Date</label>
						<input type="date" id="gr_campaign_date" class="form-control">
					</div>
					<div class="col-md-4">
						<label class="font-weight-bold" style="font-size:12px;">Destination</label>
						<select id="gr_destination" class="form-control">
							<option value="">--No destination--</option>
							<?php if(!empty($destinations)) { foreach($destinations as $d) { ?>
								<option value="<?php echo (int)$d->CategoryID; ?>"><?php echo htmlspecialchars($d->Name); ?></option>
							<?php } } ?>
						</select>
					</div>
					<div class="col-md-4">
						<label class="font-weight-bold" style="font-size:12px;">Follow Up Date</label>
						<input type="date" id="gr_follow_date" class="form-control">
					</div>
				</div>
				<div class="form-group row mb-2">
					<div class="col-md-10">
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
		$expanded_keys = array('q', 'booking_number', 'contact_number', 'email', 'destination', 'role', 'pax_min', 'pax_max', 'sales_agent', 'source', 'customer_type', 'nationality', 'gender', 'guest_type', 'language', 'booking_date', 'travel_date', 'dob', 'birthday', 'campaign_date', 'follow_date', 'customer_code', 'create_date');
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
	function Reset_Campaign_Date() {
		$('#kt_daterangepicker_guests_campaign input').val('');
	}
	function Reset_Follow_Date() {
		$('#kt_daterangepicker_guests_follow input').val('');
	}
	function Reset_Create_Date() {
		$('#kt_daterangepicker_guests_create input').val('');
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

	// Campaign / Follow date are remark ranges (Guest List page only). autoUpdateInput
	// keeps the input blank until a range is picked, so an untouched picker sends nothing.
	$('#kt_daterangepicker_guests_campaign').daterangepicker({
		buttonClasses: ' btn',
		applyClass: 'btn-primary',
		cancelClass: 'btn-secondary',
		autoApply: true,
		autoUpdateInput: false
	}, function(start, end, label) {
		$('#kt_daterangepicker_guests_campaign .form-control').val(start.format('DD/MM/YYYY') + ' - ' + end.format('DD/MM/YYYY'));
	});

	$('#kt_daterangepicker_guests_follow').daterangepicker({
		buttonClasses: ' btn',
		applyClass: 'btn-primary',
		cancelClass: 'btn-secondary',
		autoApply: true,
		autoUpdateInput: false
	}, function(start, end, label) {
		$('#kt_daterangepicker_guests_follow .form-control').val(start.format('DD/MM/YYYY') + ' - ' + end.format('DD/MM/YYYY'));
	});

	// Date Creation = when the customer master row was created. autoUpdateInput:false
	// keeps the input blank until a range is picked, so an untouched picker sends nothing.
	$('#kt_daterangepicker_guests_create').daterangepicker({
		buttonClasses: ' btn',
		applyClass: 'btn-primary',
		cancelClass: 'btn-secondary',
		autoApply: true,
		autoUpdateInput: false
	}, function(start, end, label) {
		$('#kt_daterangepicker_guests_create .form-control').val(start.format('DD/MM/YYYY') + ' - ' + end.format('DD/MM/YYYY'));
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

	// Success feedback for the GHL push. Only reached on ok:true, and by then a
	// GHL failure has already aborted the save (surfaced as an inline error), so
	// we only confirm a real sync. 'skipped' (guest not in GHL / GHL off) is silent.
	function glNotifyGhlSync(status) {
		if (typeof toastr === 'undefined') { return; }
		if (status === 'updated') {
			toastr.success('Synced to GHL.');
		}
	}

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
			data: { dedup_key: $cell.attr('data-dedup-key'), customer_id: $cell.attr('data-customer-id'), mobile: mobile },
			timeout: 30000
		}).done(function(res) {
			if (res && res.ok) {
				$cell.attr('data-mobile', res.mobile);
				if (res.dedup_key) { $cell.attr('data-dedup-key', res.dedup_key); }
				renderDisplay($cell);
				glNotifyGhlSync(res.ghl_sync);
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

	var GL_TITLES = { name: 'Edit first name', email: 'Edit email', language: 'Edit language', altname: 'Edit alt name' };

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
			data: { dedup_key: $cell.attr('data-dedup-key'), customer_id: $cell.attr('data-customer-id'), field: field, value: value },
			timeout: 30000
		}).done(function(res) {
			if (res && res.ok) {
				$cell.attr('data-value', (res.value != null) ? res.value : value);
				glRenderDisplay($cell);
				glNotifyGhlSync(res.ghl_sync);
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

	// Pretty-print a "YYYY-MM-DD" date as "13 Jul 2026". Returns '' for blanks.
	function grFormatDate(raw) {
		var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(raw || '');
		if (!m) { return ''; }
		return grPad(parseInt(m[3], 10)) + ' ' + GR_MONTHS[parseInt(m[2], 10) - 1] + ' ' + m[1];
	}

	// Local "today" as the value an HTML5 date input expects.
	function grTodayLocal() {
		var d = new Date();
		return d.getFullYear() + '-' + grPad(d.getMonth() + 1) + '-' + grPad(d.getDate());
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
			var meta = '<i class="la la-bullhorn text-primary"></i> ' + grEscape(grFormatDate(r.campaign_date));
			if (r.destination_name) {
				meta += ' <span class="text-muted font-weight-normal"><i class="la la-map-marker"></i> ' + grEscape(r.destination_name) + '</span>';
			}
			if (r.follow_date) {
				meta += ' <span class="text-danger font-weight-normal"><i class="la la-bell"></i> Follow: ' + grEscape(grFormatDate(r.follow_date)) + '</span>';
			}
			if (r.created_by) {
				meta += ' <span class="text-muted font-weight-normal">— ' + grEscape(r.created_by) + '</span>';
			}
			html += '<div class="d-flex align-items-start border-bottom py-2" data-remark-id="' + r.id + '">' +
				'<div class="flex-grow-1">' +
					'<div class="font-weight-bold text-dark-75" style="font-size:12px;">' + meta + '</div>' +
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
		$('#gr_campaign_date').val(grTodayLocal());
		$('#gr_destination').val('');
		$('#gr_follow_date').val('');
		$('#gr_remark').val('');
		$('#guest_remarks_modal').modal('show');
		grLoad();
	});

	$('#gr_add').on('click', function() {
		var $btn     = $(this);
		var $error   = $('#gr_error');
		var campaign = $('#gr_campaign_date').val();
		var dest     = $('#gr_destination').val();
		var follow   = $('#gr_follow_date').val();
		var remark   = $.trim($('#gr_remark').val());

		$error.hide().text('');
		$btn.prop('disabled', true);

		$.ajax({
			url: GR_ADD_URL, method: 'POST', dataType: 'json', timeout: 30000,
			data: { dedup_key: grDedupKey, campaign_date: campaign, destination_id: dest, follow_date: follow, remark: remark }
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

	<?php if($list_base === 'Customer') { ?>
	// ----- Customer master actions (Customer List page only) -----
	// Soft-delete customer (Status='N') via the existing Customer/Delete endpoint.
	$(document).on('click', '.delete-customer', function(e) {
		e.preventDefault();
		var $link = $(this);
		var customerId = $link.data('customer-id');
		var customerName = $link.data('customer-name');
		Swal.fire({
			title: 'Delete this customer?',
			html: 'You are about to delete <strong>' + $('<div>').text(customerName).html() + '</strong>.<br>The record will be hidden from all lists.',
			icon: 'warning',
			showCancelButton: true,
			confirmButtonText: 'Yes, delete',
			cancelButtonText: 'Cancel',
			confirmButtonColor: '#d33',
			cancelButtonColor: '#3085d6'
		}).then(function(result) {
			if (!result.isConfirmed) return;
			$.ajax({
				url: '<?php echo base_url('Customer/Delete'); ?>',
				method: 'GET',
				data: { customer_id: customerId },
				success: function() {
					if (typeof toastr !== 'undefined') { toastr.success('Customer deleted.'); }
					window.location.reload();
				},
				error: function() {
					Swal.fire({ icon: 'error', title: 'Delete failed', text: 'Could not delete the customer. Please try again.' });
				}
			});
		});
	});

	<?php } ?>
</script>

<?php if($list_base === 'Customer') { ?>
<!-- Bulk-import modal: upload a filled Import Template to create many customers
     at once. Unlike the FAQ importer this is purely additive — it never edits
     or removes existing customers; duplicate name+phone rows are skipped. -->
<div class="modal fade" id="customer_import_modal" tabindex="-1" role="dialog" aria-labelledby="customer_import_label" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<form action="<?php echo base_url('Customer/Import'); ?>" method="post" enctype="multipart/form-data" id="customer_import_form">
			<div class="modal-content">
				<div class="modal-header" style="background-color:#D7E2F2;">
					<h5 class="modal-title" id="customer_import_label" style="color:#6082B6;"><strong>Bulk Create Customers from Excel</strong></h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				</div>
				<div class="modal-body">
					<div class="alert alert-light-primary" role="alert" style="border-left:4px solid #6082B6;">
						Download <strong>Import Template</strong> first, fill one customer per row, then upload it here. Each row becomes a new customer.
					</div>
					<div class="form-group">
						<label>Excel File <span class="text-danger">*</span></label>
						<div class="custom-file">
							<input type="file" name="import_file" class="custom-file-input" id="customer_import_file" accept=".xlsx,.xls" required>
							<label class="custom-file-label" for="customer_import_file" id="customer_import_file_label">Choose .xlsx / .xls file</label>
						</div>
						<span class="form-text text-muted"><strong>Alt Name</strong>, <strong>Name</strong>, <strong>Phone Number</strong> and <strong>Chat Language</strong> are required. Leave <strong>Customer Code</strong> blank to auto-generate. Rows matching an existing name + phone are skipped. The last 3 uploads are kept as backups.</span>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-light font-weight-bold" data-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-success font-weight-bold" id="customer_import_submit">
						<i class="la la-file-import"></i>Create Customers
					</button>
				</div>
			</div>
		</form>
	</div>
</div>
<script>
	$('[data-toggle="tooltip"]').tooltip();
	// Show the picked filename, and lock the button while the import runs.
	$('#customer_import_file').on('change', function() {
		var name = (this.files && this.files.length) ? this.files[0].name : 'Choose .xlsx / .xls file';
		$('#customer_import_file_label').text(name);
	});
	$('#customer_import_form').on('submit', function() {
		$('#customer_import_submit').prop('disabled', true).html('<i class="la la-spinner la-spin"></i>Creating...');
	});
</script>
<?php } ?>

<?php if($list_base === 'Ghl_Leads') { ?>
<!-- Create Lead modal: add one lead by hand. It is stored in the same table as
     the API-synced leads but flagged "Manual" (Type column) with a synthetic id
     the GHL sync never touches. Fields mirror the GHL Leads columns. -->
<style>
	/* This theme's .modal-lg/.modal-xl widths sit behind @media(min-width:1200px),
	   so below 1200px they collapse to the default 500px. Widen this one modal
	   with a viewport-independent, ID-scoped rule (same pattern as #passportModal
	   / .lra-chat-modal elsewhere in the app). Plain block .modal-dialog +
	   max-width + margin:auto = a centered wide modal at every width. */
	#ghl_lead_create_modal .modal-dialog {
		max-width: 750px !important;
		width: auto !important;
		margin: 1.75rem auto !important;
	}
</style>
<div class="modal fade" id="ghl_lead_create_modal" tabindex="-1" role="dialog" aria-labelledby="ghl_lead_create_label" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<form action="<?php echo base_url('Ghl_Leads/Create'); ?>" method="post" id="ghl_lead_create_form">
			<div class="modal-content">
				<div class="modal-header" style="background-color:#D7E2F2;">
					<h5 class="modal-title" id="ghl_lead_create_label" style="color:#6082B6;"><strong>Create Manual Lead</strong></h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				</div>
				<div class="modal-body">
						<div class="alert alert-light-danger font-weight-bold d-none" role="alert" id="ghl_lead_create_error" style="border-left:4px solid #f64e60;">
					</div>
					<div class="form-group row">
						<div class="col-md-6">
							<label>First Name <span class="text-danger">*</span></label>
							<input type="text" name="first_name" class="form-control" autocomplete="off">
						</div>
						<div class="col-md-6">
							<label>Last Name</label>
							<input type="text" name="last_name" class="form-control" autocomplete="off">
						</div>
					</div>
					<div class="form-group row">
						<div class="col-md-6">
							<label>Contact Number <span class="text-danger">*</span></label>
							<input type="text" name="phone" class="form-control" autocomplete="off" placeholder="e.g. +60 12-345 6789">
						</div>
						<div class="col-md-6">
							<label>Email <span class="text-danger">*</span></label>
							<input type="email" name="email" class="form-control" autocomplete="off">
						</div>
					</div>
					<div class="form-group row">
						<div class="col-md-6">
							<label>Gender</label>
							<select name="gender" class="form-control">
								<option value="">-- Select --</option>
								<option value="Male">Male</option>
								<option value="Female">Female</option>
								<option value="Other">Other</option>
							</select>
						</div>
						<div class="col-md-6">
							<label>Language</label>
							<input type="text" name="chat_language" class="form-control" autocomplete="off">
						</div>
					</div>
					<div class="form-group row">
						<div class="col-md-6">
							<label>Race</label>
							<input type="text" name="race" class="form-control" autocomplete="off">
						</div>
						<div class="col-md-6">
							<label>Nationality</label>
							<input type="text" name="nationality" class="form-control" autocomplete="off">
						</div>
					</div>
					<div class="form-group row">
						<div class="col-md-6">
							<label>Date of Birth</label>
							<input type="date" name="date_of_birth" class="form-control" autocomplete="off">
						</div>
						<div class="col-md-6">
							<label>Tags</label>
							<input type="text" name="tags" class="form-control" autocomplete="off" placeholder="Comma-separated, e.g. Redang, VIP">
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-light font-weight-bold" data-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary font-weight-bold" id="ghl_lead_create_submit">
						<i class="la la-user-plus"></i>Create Lead
					</button>
				</div>
			</div>
		</form>
	</div>
</div>
<script>
	$('[data-toggle="tooltip"]').tooltip();
	$('#ghl_lead_create_form').on('submit', function(e) {
		var $form = $(this);
		var val = function(name) { return $.trim($form.find('[name="' + name + '"]').val() || ''); };
		var first = val('first_name'), last = val('last_name'), phone = val('phone'), email = val('email');
		var $err = $('#ghl_lead_create_error');
		var problems = [];

		// Mirror the server rule: a lead needs at least one identifying value.
		if (first === '' && last === '' && phone === '' && email === '') {
			problems.push('Enter at least a name, contact number or email.');
		}
		// Validate email shape when provided (server double-checks too).
		if (email !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
			problems.push('Email address is not valid.');
		}

		if (problems.length) {
			e.preventDefault();
			$err.html(problems.join('<br>')).removeClass('d-none');
			return false;
		}

		$err.addClass('d-none');
		$('#ghl_lead_create_submit').prop('disabled', true).html('<i class="la la-spinner la-spin"></i>Creating...');
	});
</script>

<?php } ?>
