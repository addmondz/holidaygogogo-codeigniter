<?php
	$is_update    = ($mode === 'update');
	$submit_url   = $is_update ? base_url('Campaign/Update?campaign_id=') . (int)$campaign->CampaignID : base_url('Campaign/Create');
	$cd_display   = '';
	if(!empty($campaign->CampaignDate) && $campaign->CampaignDate !== '0000-00-00') {
		$cd_display = date('d/m/Y', strtotime($campaign->CampaignDate));
	}

	$initial_selected = array();
	if(!empty($campaign_guests)) {
		foreach($campaign_guests as $g) {
			$initial_selected[$g->DedupKey] = array(
				'name'    => (string)$g->GuestName,
				'contact' => (string)$g->ContactNum,
				'email'   => (string)$g->Email,
				'type'    => (string)$g->GuestType,
			);
		}
	}
?>
<style>
.campaign-picker-card .picker-section-title {
	font-weight: 600;
	color: #6082B6;
	font-size: 14px;
	margin-bottom: 8px;
}
.campaign-picker-card .picker-results,
.campaign-picker-card .picker-selected {
	max-height: 420px;
	overflow-y: auto;
	border: 1px solid #E4E6EF;
	border-radius: 6px;
}
.campaign-picker-card table { margin-bottom: 0; }
.campaign-picker-card thead th {
	position: sticky;
	top: 0;
	background: #F3F6F9;
	z-index: 1;
	font-size: 12px;
}
.picker-empty {
	text-align: center;
	padding: 20px;
	color: #7E8299;
	font-size: 13px;
}
.picker-summary {
	background:#EEF6FF;
	border:1px solid #C9E0F7;
	border-radius:6px;
	padding:10px 14px;
	color:#34577C;
	font-weight:600;
	font-size:13px;
}
.btn-icon-circle {
	width: 28px; height: 28px; padding: 0;
	display:inline-flex; align-items:center; justify-content:center;
}
</style>

<div class="d-flex flex-column-fluid">
	<div class="container-fluid">
		<form id="campaign_form" method="post" action="<?php echo $submit_url; ?>">
			<?php if($is_update) { ?>
				<input type="hidden" name="campaign_id" value="<?php echo (int)$campaign->CampaignID; ?>">
			<?php } ?>

			<div class="card card-custom mb-5">
				<div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
					<div class="card-title">
						<h3 class="card-label" style="color:#6082B6;">
							<strong><?php echo $is_update ? 'Update Campaign' : 'Create Campaign'; ?></strong>
						</h3>
					</div>
					<div class="card-toolbar">
						<a href="<?php echo base_url('Campaign'); ?>" class="btn btn-light font-weight-bold" style="margin-right:6px;">
							<i class="la la-arrow-left"></i>Back
						</a>
						<button type="submit" class="btn btn-primary font-weight-bold">
							<i class="la la-save"></i><?php echo $is_update ? 'Save Changes' : 'Create Campaign'; ?>
						</button>
					</div>
				</div>
				<div class="card-body">
					<div class="row">
						<div class="col-md-5">
							<div class="form-group">
								<label>Name <span style="color:red;">*</span></label>
								<div class="input-icon">
									<input type="text" name="Name" required maxlength="255" value="<?php echo htmlspecialchars((string)$campaign->Name, ENT_QUOTES); ?>" autocomplete="off" class="form-control">
									<span><i class="la la-bullhorn"></i></span>
								</div>
							</div>
						</div>
						<div class="col-md-3">
							<div class="form-group">
								<label>Campaign Date
									<a onclick="Reset_Campaign_Date()" class="btn btn-icon btn-light-warning btn-xs" data-toggle="tooltip" title="Clear date">
										<i class="la la-undo"></i>
									</a>
								</label>
								<div id="kt_datepicker_campaign" class="input-icon">
									<input readonly type="text" name="CampaignDate" value="<?php echo htmlspecialchars($cd_display, ENT_QUOTES); ?>" autocomplete="off" class="form-control" placeholder="DD/MM/YYYY">
									<span><i class="la la-calendar"></i></span>
								</div>
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-md-8">
							<div class="form-group">
								<label>Description</label>
								<textarea name="Description" class="form-control" rows="4" placeholder="Optional notes about this campaign"><?php echo htmlspecialchars((string)$campaign->Description); ?></textarea>
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label>
									GHL Workflow ID
									<span data-toggle="tooltip" title="Required for the Sync to GHL button. Open the workflow in GHL → copy the id from the URL (…/workflows/&lt;id&gt;)." style="color:#6082B6; cursor:help;">
										<i class="la la-question-circle"></i>
									</span>
								</label>
								<div class="input-icon">
									<input type="text" name="GhlWorkflowID" value="<?php echo htmlspecialchars((string)(isset($campaign->GhlWorkflowID) ? $campaign->GhlWorkflowID : ''), ENT_QUOTES); ?>" autocomplete="off" maxlength="64" class="form-control" placeholder="e.g. 7QnY8h2WlsAbcXyz">
									<span><i class="la la-share-alt"></i></span>
								</div>
								<small class="text-muted" style="font-size:11px;">Each guest will be enrolled into this GHL workflow when you click Sync. The workflow's WhatsApp action sends the message.</small>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="card card-custom mb-5 campaign-picker-card">
				<div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
					<div class="card-title">
						<h3 class="card-label" style="color:#6082B6;">
							<strong>Guests</strong>
						</h3>
					</div>
					<div class="card-toolbar">
						<span class="picker-summary">Selected: <span id="picker_count">0</span></span>
					</div>
				</div>
				<div class="card-body">
					<div class="row mb-4">
						<div class="col-md-3">
							<label>Search guests (name)</label>
							<div class="input-icon">
								<input type="text" id="guest_search_q" autocomplete="off" class="form-control" placeholder="Type a name">
								<span><i class="la la-search"></i></span>
							</div>
						</div>
						<div class="col-md-3">
							<label>Type</label>
							<select id="guest_search_type" class="form-control selectpicker">
								<option value="">--ALL TYPES--</option>
								<option value="guest">Booking Guest</option>
								<option value="ghl">GHL</option>
							</select>
						</div>
						<div class="col-md-3">
							<label>Role</label>
							<select id="guest_search_role" class="form-control selectpicker">
								<option value="">--ALL ROLES--</option>
								<option value="Team Leader">Team Leader</option>
								<option value="Team Member">Team Member</option>
							</select>
						</div>
						<div class="col-md-3">
							<label>Nationality</label>
							<select id="guest_search_nationality" class="form-control selectpicker" data-live-search="true">
								<option value="">--ALL NATIONALITIES--</option>
								<?php if(!empty($nationalities)) { foreach($nationalities as $n) { ?>
									<option value="<?php echo htmlspecialchars($n->value, ENT_QUOTES); ?>"><?php echo htmlspecialchars($n->value); ?></option>
								<?php } } ?>
							</select>
						</div>
					</div>
					<!-- Row 2 — booking attributes (customer + team member) -->
					<div class="row mb-4">
						<div class="col-md-3">
							<label>Destination</label>
							<select id="guest_search_destination" class="form-control selectpicker" data-live-search="true" multiple data-actions-box="true" title="--ALL DESTINATIONS--">
								<?php if(!empty($destinations)) { foreach($destinations as $d) { ?>
									<option value="<?php echo (int)$d->CategoryID; ?>"><?php echo htmlspecialchars($d->Name); ?></option>
								<?php } } ?>
							</select>
						</div>
						<div class="col-md-3">
							<label>Source</label>
							<select id="guest_search_source" class="form-control selectpicker" data-live-search="true" multiple data-actions-box="true" title="--ALL SOURCES--">
								<?php if(!empty($sources)) { foreach($sources as $s) { ?>
									<option value="<?php echo (int)$s->SourceID; ?>"><?php echo htmlspecialchars($s->Name); ?></option>
								<?php } } ?>
							</select>
						</div>
						<div class="col-md-3">
							<label>Customer Type</label>
							<select id="guest_search_customer_type" class="form-control selectpicker" data-live-search="true" multiple data-actions-box="true" title="--ALL CUSTOMER TYPES--">
								<?php if(!empty($customer_types)) { foreach($customer_types as $ct) { ?>
									<option value="<?php echo htmlspecialchars($ct->Name, ENT_QUOTES); ?>"><?php echo htmlspecialchars($ct->Name); ?></option>
								<?php } } ?>
							</select>
						</div>
						<div class="col-md-3">
							<label>Language</label>
							<select id="guest_search_language" class="form-control selectpicker" data-live-search="true" multiple data-actions-box="true" title="--ALL LANGUAGES--">
								<?php if(!empty($languages)) { foreach($languages as $l) { ?>
									<option value="<?php echo htmlspecialchars($l->value, ENT_QUOTES); ?>"><?php echo htmlspecialchars($l->value); ?></option>
								<?php } } ?>
							</select>
						</div>
					</div>

					<!-- Row 3 — demographics (Race / Tag are GHL-lead only) -->
					<div class="row mb-4">
						<div class="col-md-3">
							<label>Gender</label>
							<select id="guest_search_gender" class="form-control selectpicker" multiple data-actions-box="true" title="--ALL GENDERS--">
								<option value="Male">Male</option>
								<option value="Female">Female</option>
								<option value="Other">Other</option>
							</select>
						</div>
						<div class="col-md-3">
							<label>Race <span class="text-muted font-size-xs">(leads only)</span></label>
							<select id="guest_search_race" class="form-control selectpicker" data-live-search="true" multiple data-actions-box="true" title="--ALL RACES--">
								<?php if(!empty($races)) { foreach($races as $r) { ?>
									<option value="<?php echo htmlspecialchars($r->value, ENT_QUOTES); ?>"><?php echo htmlspecialchars($r->value); ?></option>
								<?php } } ?>
							</select>
						</div>
						<div class="col-md-3">
							<label>Tag <span class="text-muted font-size-xs">(leads only)</span></label>
							<select id="guest_search_tags" class="form-control selectpicker" data-live-search="true" multiple data-actions-box="true" title="--ALL TAGS--">
								<?php if(!empty($lead_tags)) { foreach($lead_tags as $t) { ?>
									<option value="<?php echo htmlspecialchars($t, ENT_QUOTES); ?>"><?php echo htmlspecialchars($t); ?></option>
								<?php } } ?>
							</select>
						</div>
						<div class="col-md-3">
							<label>Birthday</label>
							<select id="guest_search_birthday" class="form-control selectpicker">
								<option value="">--ANY--</option>
								<option value="today">Today</option>
								<option value="this_month">This Month</option>
								<option value="1">January</option>
								<option value="2">February</option>
								<option value="3">March</option>
								<option value="4">April</option>
								<option value="5">May</option>
								<option value="6">June</option>
								<option value="7">July</option>
								<option value="8">August</option>
								<option value="9">September</option>
								<option value="10">October</option>
								<option value="11">November</option>
								<option value="12">December</option>
							</select>
						</div>
					</div>

					<!-- Row 4 — dates -->
					<div class="row mb-4">
						<div class="col-md-3">
							<label>Date of Bookings
								<a onclick="Reset_Guest_Booking_Date()" class="btn btn-icon btn-light-warning btn-xs" data-toggle="tooltip" title="Clear booking date">
									<i class="la la-undo"></i>
								</a>
							</label>
							<div id="guest_search_booking_date" class="input-icon">
								<input readonly type="text" autocomplete="off" class="form-control" placeholder="DD/MM/YYYY - DD/MM/YYYY">
								<span><i class="la la-calendar"></i></span>
							</div>
						</div>
						<div class="col-md-3">
							<label>Travel Date
								<a onclick="Reset_Guest_Travel_Date()" class="btn btn-icon btn-light-warning btn-xs" data-toggle="tooltip" title="Clear travel date">
									<i class="la la-undo"></i>
								</a>
							</label>
							<div id="guest_search_travel_date" class="input-icon">
								<input readonly type="text" autocomplete="off" class="form-control" placeholder="DD/MM/YYYY - DD/MM/YYYY">
								<span><i class="la la-calendar"></i></span>
							</div>
						</div>
						<div class="col-md-3">
							<label>Date of Birth
								<a onclick="Reset_Guest_Dob()" class="btn btn-icon btn-light-warning btn-xs" data-toggle="tooltip" title="Clear date of birth">
									<i class="la la-undo"></i>
								</a>
							</label>
							<div id="guest_search_dob" class="input-icon">
								<input readonly type="text" autocomplete="off" class="form-control" placeholder="DD/MM/YYYY - DD/MM/YYYY">
								<span><i class="la la-calendar"></i></span>
							</div>
						</div>
						<div class="col-md-3">
							<div class="d-flex justify-content-between align-items-center">
								<label class="mb-0">Campaign</label>
								<select id="guest_search_campaign_mode" class="form-control form-control-sm w-auto" style="height:auto;padding:2px 22px 2px 8px;">
									<option value="include">Include</option>
									<option value="exclude">Exclude</option>
								</select>
							</div>
							<select id="guest_search_joined_campaign" class="form-control selectpicker" data-live-search="true" multiple data-actions-box="true" title="--ANY CAMPAIGN--">
								<?php if(!empty($campaigns)) { foreach($campaigns as $cp) { ?>
									<option value="<?php echo (int)$cp->CampaignID; ?>"><?php echo htmlspecialchars($cp->Name); ?></option>
								<?php } } ?>
							</select>
						</div>
					</div>

					<!-- Row 5 — customer-value segments (booking guests only) -->
					<div class="separator separator-dashed my-3"></div>
					<div class="text-muted font-weight-bold mb-2">Customer-value segments <span class="font-size-xs">(booking guests only)</span></div>
					<div class="row mb-4">
						<div class="col-md-3">
							<label>Purchase Count</label>
							<select id="guest_search_min_purchases" class="form-control selectpicker">
								<option value="">--ANY--</option>
								<option value="2">2&times; and above</option>
								<option value="3">3&times; and above</option>
								<option value="5">5&times; and above</option>
							</select>
						</div>
						<div class="col-md-3">
							<label>Lifetime Booking Value</label>
							<select id="guest_search_ltv" class="form-control selectpicker">
								<option value="">--ANY--</option>
								<option value="0-10000">Below RM10k</option>
								<option value="10000-20000">RM10k &ndash; 20k</option>
								<option value="20000-30000">RM20k &ndash; 30k</option>
								<option value="30000-50000">RM30k &ndash; 50k</option>
								<option value="50000-100000">RM50k &ndash; 100k</option>
								<option value="100000+">RM100k and above</option>
							</select>
						</div>
						<div class="col-md-3">
							<label>Booking-to-Travel Lead
								<a href="javascript:;" class="text-muted" data-toggle="tooltip" title="How far ahead the booking was created before the travel start date"><i class="la la-info-circle"></i></a>
							</label>
							<select id="guest_search_booking_lead" class="form-control selectpicker">
								<option value="">--ANY--</option>
								<option value="0-1">Within 1 month</option>
								<option value="1-2">1 &ndash; 2 months</option>
								<option value="2-3">2 &ndash; 3 months</option>
								<option value="3-6">3 &ndash; 6 months</option>
								<option value="6+">6 months and above</option>
							</select>
						</div>
						<div class="col-md-3">
							<label class="d-block">More segments</label>
							<div class="checkbox-inline">
								<label class="checkbox checkbox-lg">
									<input type="checkbox" id="guest_search_family_kids"><span></span> Family with kids
								</label>
							</div>
							<div class="checkbox-inline">
								<label class="checkbox checkbox-lg">
									<input type="checkbox" id="guest_search_consecutive_years"><span></span> Purchased 2 consecutive years+
								</label>
							</div>
							<div class="checkbox-inline">
								<label class="checkbox checkbox-lg">
									<input type="checkbox" id="guest_search_cancelled"><span></span> Has cancelled BC
								</label>
							</div>
						</div>
					</div>

					<div class="row mb-4">
						<div class="col-md-12 text-right">
							<button type="button" id="guest_search_reset" class="btn btn-light font-weight-bold mr-2"><i class="la la-undo"></i>Reset Filters</button>
							<button type="button" id="guest_search_btn" class="btn btn-light-success font-weight-bold" style="min-width:160px;"><i class="la la-search"></i>Search</button>
						</div>
					</div>

					<div class="row">
						<div class="col-md-7">
							<div class="d-flex justify-content-between align-items-center mb-2">
								<div class="picker-section-title" style="margin-bottom:0;">Available Guests</div>
								<button type="button" id="picker_pick_all" class="btn btn-light-primary btn-sm font-weight-bold" data-toggle="tooltip" title="Add every guest matching the current filters (all pages)" disabled>
									<i class="la la-check-double"></i>Pick All Matching
								</button>
							</div>
							<div class="picker-results">
								<table class="table table-bordered table-head-custom">
									<thead>
										<tr>
											<th style="width:48px; text-align:center;">Pick</th>
											<th>Name</th>
											<th>Contact</th>
											<th>Email</th>
											<th>Type</th>
										</tr>
									</thead>
									<tbody id="picker_results_body">
										<tr><td colspan="5" class="picker-empty">Use the search above to find guests.</td></tr>
									</tbody>
								</table>
							</div>
							<div class="d-flex justify-content-between align-items-center mt-2">
								<div id="picker_results_info" class="text-muted" style="font-size:12px;"></div>
								<div>
									<button type="button" id="picker_prev" class="btn btn-light btn-sm">&lsaquo; Prev</button>
									<button type="button" id="picker_next" class="btn btn-light btn-sm">Next &rsaquo;</button>
								</div>
							</div>
						</div>
						<div class="col-md-5">
							<div class="d-flex justify-content-between align-items-center mb-2">
								<div class="picker-section-title" style="margin-bottom:0;">Selected Guests</div>
								<button type="button" id="picker_clear_all" class="btn btn-light-danger btn-sm font-weight-bold" data-toggle="tooltip" title="Remove all selected guests">
									<i class="la la-times-circle"></i>Clear All
								</button>
							</div>
							<div class="picker-selected">
								<table class="table table-bordered table-head-custom">
									<thead>
										<tr>
											<th>Name</th>
											<th>Contact</th>
											<th>Type</th>
											<th style="width:60px; text-align:center;">Remove</th>
										</tr>
									</thead>
									<tbody id="picker_selected_body">
										<tr><td colspan="4" class="picker-empty">No guests selected yet.</td></tr>
									</tbody>
								</table>
							</div>
						</div>
					</div>

					<div id="picker_hidden_inputs"></div>
				</div>
			</div>

			<div class="text-right mb-5">
				<a href="<?php echo base_url('Campaign'); ?>" class="btn btn-light font-weight-bold" style="width:120px; margin-right:6px;">Cancel</a>
				<button type="submit" class="btn btn-primary font-weight-bold" style="width:200px;">
					<i class="la la-save"></i><?php echo $is_update ? 'Save Changes' : 'Create Campaign'; ?>
				</button>
			</div>
		</form>
	</div>
</div>

<script>
	var CAMPAIGN_INITIAL_SELECTED = <?php echo json_encode($initial_selected, JSON_UNESCAPED_UNICODE); ?>;
	var SEARCH_URL = '<?php echo base_url('Campaign/Search_Guests'); ?>';

	$('#kt_datepicker_campaign').daterangepicker({
		singleDatePicker: true,
		autoUpdateInput: false,
		locale: { format: 'DD/MM/YYYY', cancelLabel: 'Clear' },
		buttonClasses: ' btn',
		applyClass: 'btn-primary',
		cancelClass: 'btn-secondary'
	});
	$('#kt_datepicker_campaign').on('apply.daterangepicker', function(ev, picker) {
		$(this).find('input').val(picker.startDate.format('DD/MM/YYYY'));
	});
	$('#kt_datepicker_campaign').on('cancel.daterangepicker', function(ev, picker) {
		$(this).find('input').val('');
	});
	function Reset_Campaign_Date() { $('#kt_datepicker_campaign input').val(''); }

	// Guest-picker "Date of Bookings" range. autoUpdateInput:false so the box
	// stays empty (= no filter) until a range is applied; Clear empties it again.
	$('#guest_search_booking_date').daterangepicker({
		autoUpdateInput: false,
		locale: { format: 'DD/MM/YYYY', cancelLabel: 'Clear' },
		buttonClasses: ' btn',
		applyClass: 'btn-primary',
		cancelClass: 'btn-secondary'
	});
	$('#guest_search_booking_date').on('apply.daterangepicker', function(ev, picker) {
		$(this).find('input').val(picker.startDate.format('DD/MM/YYYY') + ' - ' + picker.endDate.format('DD/MM/YYYY'));
	});
	$('#guest_search_booking_date').on('cancel.daterangepicker', function(ev, picker) {
		$(this).find('input').val('');
	});
	function Reset_Guest_Booking_Date() { $('#guest_search_booking_date input').val(''); }

	// Travel Date + Date of Birth ranges — same behaviour as Date of Bookings.
	function setupGuestRange(sel) {
		$(sel).daterangepicker({
			autoUpdateInput: false,
			locale: { format: 'DD/MM/YYYY', cancelLabel: 'Clear' },
			buttonClasses: ' btn',
			applyClass: 'btn-primary',
			cancelClass: 'btn-secondary'
		});
		$(sel).on('apply.daterangepicker', function(ev, picker) {
			$(this).find('input').val(picker.startDate.format('DD/MM/YYYY') + ' - ' + picker.endDate.format('DD/MM/YYYY'));
		});
		$(sel).on('cancel.daterangepicker', function(ev, picker) {
			$(this).find('input').val('');
		});
	}
	setupGuestRange('#guest_search_travel_date');
	setupGuestRange('#guest_search_dob');
	function Reset_Guest_Travel_Date() { $('#guest_search_travel_date input').val(''); }
	function Reset_Guest_Dob() { $('#guest_search_dob input').val(''); }

	$('[data-toggle="tooltip"]').tooltip();

	(function() {
		var selected = {};
		Object.keys(CAMPAIGN_INITIAL_SELECTED || {}).forEach(function(k) {
			selected[k] = CAMPAIGN_INITIAL_SELECTED[k];
		});

		var currentPage   = 1;
		var totalPages    = 0;
		var currentRows   = [];
		var lastTotal     = 0;
		var searched      = false;

		function escapeHtml(s) {
			if(s === null || s === undefined) return '';
			return String(s).replace(/[&<>"']/g, function(c) {
				return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];
			});
		}

		function typeLabel(type) {
			if(type === 'GHL') {
				return '<span class="label label-inline label-pill label-light-warning font-weight-bold">GHL</span>';
			}
			return '<span class="label label-inline label-pill label-light-success font-weight-bold">Booking Guest</span>';
		}

		function renderSelected() {
			var $body = $('#picker_selected_body');
			$body.empty();
			var keys = Object.keys(selected);
			$('#picker_count').text(keys.length);
			if(keys.length === 0) {
				$body.html('<tr><td colspan="4" class="picker-empty">No guests selected yet.</td></tr>');
			} else {
				keys.sort(function(a, b) {
					var an = (selected[a].name || '').toLowerCase();
					var bn = (selected[b].name || '').toLowerCase();
					return an < bn ? -1 : (an > bn ? 1 : 0);
				});
				keys.forEach(function(k) {
					var g = selected[k];
					var tr = $(
						'<tr>' +
							'<td>' + escapeHtml(g.name) + '</td>' +
							'<td>' + escapeHtml(g.contact) + '</td>' +
							'<td>' + typeLabel(g.type) + '</td>' +
							'<td style="text-align:center;">' +
								'<button type="button" class="btn btn-icon btn-light-danger btn-sm btn-icon-circle picker-remove-btn" data-toggle="tooltip" title="Remove from campaign" data-key="' + escapeHtml(k) + '">' +
									'<i class="la la-times"></i>' +
								'</button>' +
							'</td>' +
						'</tr>'
					);
					$body.append(tr);
				});
			}
			rebuildHiddenInputs();
			refreshResultCheckboxState();
			$('[data-toggle="tooltip"]').tooltip();
		}

		function rebuildHiddenInputs() {
			var $h = $('#picker_hidden_inputs');
			$h.empty();
			Object.keys(selected).forEach(function(k) {
				var g = selected[k];
				$h.append('<input type="hidden" name="guests[' + k + '][name]" value="' + escapeHtml(g.name) + '">');
				$h.append('<input type="hidden" name="guests[' + k + '][contact]" value="' + escapeHtml(g.contact) + '">');
				$h.append('<input type="hidden" name="guests[' + k + '][email]" value="' + escapeHtml(g.email) + '">');
				$h.append('<input type="hidden" name="guests[' + k + '][type]" value="' + escapeHtml(g.type) + '">');
			});
		}

		function refreshResultCheckboxState() {
			$('#picker_results_body input.picker-pick').each(function() {
				var k = $(this).data('key');
				$(this).prop('checked', !!selected[k]);
			});
		}

		function renderResults(rows) {
			currentRows = rows;
			var $body = $('#picker_results_body');
			$body.empty();
			if(!rows || rows.length === 0) {
				$body.html('<tr><td colspan="5" class="picker-empty">No matching guests.</td></tr>');
				return;
			}
			rows.forEach(function(r) {
				var checked = selected[r.DedupKey] ? 'checked' : '';
				var tr = $(
					'<tr>' +
						'<td style="text-align:center;">' +
							'<input type="checkbox" class="picker-pick" data-key="' + escapeHtml(r.DedupKey) + '" ' + checked + '>' +
						'</td>' +
						'<td>' + escapeHtml(r.GuestName) + '</td>' +
						'<td>' + escapeHtml(r.ContactNum) + '</td>' +
						'<td>' + escapeHtml(r.Email) + '</td>' +
						'<td>' + typeLabel(r.GuestType) + '</td>' +
					'</tr>'
				);
				tr.data('rowdata', r);
				$body.append(tr);
			});
		}

		function currentFilterData() {
			return {
				q: $('#guest_search_q').val(),
				type: $('#guest_search_type').val(),
				role: $('#guest_search_role').val(),
				nationality: $('#guest_search_nationality').val(),
				booking_date: $('#guest_search_booking_date input').val(),
				travel_date: $('#guest_search_travel_date input').val(),
				dob: $('#guest_search_dob input').val(),
				destination: $('#guest_search_destination').val() || [],
				source: $('#guest_search_source').val() || [],
				customer_type: $('#guest_search_customer_type').val() || [],
				language: $('#guest_search_language').val() || [],
				gender: $('#guest_search_gender').val() || [],
				race: $('#guest_search_race').val() || [],
				tags: $('#guest_search_tags').val() || [],
				birthday: $('#guest_search_birthday').val(),
				min_purchases: $('#guest_search_min_purchases').val(),
				ltv: $('#guest_search_ltv').val(),
				booking_lead: $('#guest_search_booking_lead').val(),
				family_kids: $('#guest_search_family_kids').is(':checked') ? '1' : '',
				consecutive_years: $('#guest_search_consecutive_years').is(':checked') ? '1' : '',
				cancelled: $('#guest_search_cancelled').is(':checked') ? '1' : '',
				joined_campaign: $('#guest_search_joined_campaign').val() || [],
				campaign_mode: $('#guest_search_campaign_mode').val()
			};
		}

		function loadResults(page) {
			currentPage = page || 1;
			$('#picker_results_body').html('<tr><td colspan="5" class="picker-empty">Loading...</td></tr>');
			$.ajax({
				url: SEARCH_URL,
				type: 'get',
				data: $.extend({ page: currentPage }, currentFilterData()),
				dataType: 'json',
				success: function(resp) {
					totalPages = resp.total_pages;
					lastTotal  = resp.total;
					searched   = true;
					renderResults(resp.rows);
					$('#picker_results_info').text(
						'Found ' + resp.total + ' (page ' + resp.page + ' of ' + (resp.total_pages || 1) + ')'
					);
					$('#picker_prev').prop('disabled', currentPage <= 1);
					$('#picker_next').prop('disabled', currentPage >= totalPages);
					$('#picker_pick_all').prop('disabled', resp.total <= 0);
				},
				error: function() {
					$('#picker_results_body').html('<tr><td colspan="5" class="picker-empty" style="color:#f64e60;">Failed to load guests.</td></tr>');
				}
			});
		}

		function addRowsToSelected(rows) {
			var added = 0;
			(rows || []).forEach(function(r) {
				if(!r.DedupKey || selected[r.DedupKey]) { return; }
				selected[r.DedupKey] = {
					name:    r.GuestName  || '',
					contact: r.ContactNum || '',
					email:   r.Email      || '',
					type:    r.GuestType  || ''
				};
				added++;
			});
			return added;
		}

		function pickAllMatching() {
			if(!searched || lastTotal <= 0) { return; }
			var $btn = $('#picker_pick_all');
			Swal.fire({
				title: 'Add all matching guests?',
				text: 'This adds every guest matching the current filters (' + lastTotal + ') to the campaign.',
				icon: 'question',
				showCancelButton: true,
				confirmButtonText: 'Yes, add all',
				cancelButtonText: 'Cancel'
			}).then(function(r) {
				if(!r.isConfirmed) { return; }
				$btn.prop('disabled', true).html('<i class="la la-spinner la-spin"></i>Adding...');
				$.ajax({
					url: SEARCH_URL,
					type: 'get',
					data: $.extend({ all: 1 }, currentFilterData()),
					dataType: 'json',
					success: function(resp) {
						var added   = addRowsToSelected(resp.rows);
						var fetched = (resp.rows || []).length;
						renderSelected();
						var msg = 'Added ' + added + ' guest' + (added === 1 ? '' : 's') + '.';
						if(resp.total > fetched) {
							msg += ' Only the first ' + fetched + ' of ' + resp.total +
								' were added — narrow the filters to reach the rest.';
						}
						Swal.fire({ icon: 'success', title: 'Done', text: msg, timer: 2600, showConfirmButton: false });
					},
					error: function() {
						Swal.fire({ icon: 'error', title: 'Failed to add guests', timer: 2000, showConfirmButton: false });
					},
					complete: function() {
						$btn.prop('disabled', false).html('<i class="la la-check-double"></i>Pick All Matching');
						$('[data-toggle="tooltip"]').tooltip();
					}
				});
			});
		}

		$('#guest_search_btn').on('click', function() { loadResults(1); });
		$('#picker_pick_all').on('click', pickAllMatching);
		$('#guest_search_q').on('keydown', function(e) { if(e.which === 13) { e.preventDefault(); loadResults(1); } });

		// Reset every picker filter back to its empty state (does NOT touch the
		// already-selected guests). Clears text, ranges, checkboxes and repaints
		// the bootstrap-select dropdowns.
		$('#guest_search_reset').on('click', function() {
			$('#guest_search_q').val('');
			$('#guest_search_booking_date input, #guest_search_travel_date input, #guest_search_dob input').val('');
			$('#guest_search_family_kids, #guest_search_consecutive_years, #guest_search_cancelled').prop('checked', false);
			$('#guest_search_type, #guest_search_role, #guest_search_nationality, #guest_search_birthday, ' +
				'#guest_search_min_purchases, #guest_search_ltv, #guest_search_booking_lead, ' +
				'#guest_search_destination, #guest_search_source, #guest_search_customer_type, #guest_search_language, ' +
				'#guest_search_gender, #guest_search_race, #guest_search_tags, #guest_search_joined_campaign').val('');
			$('#guest_search_campaign_mode').val('include');
			$('.selectpicker').selectpicker('refresh');
		});
		$('#picker_prev').on('click', function() { if(currentPage > 1) { loadResults(currentPage - 1); } });
		$('#picker_next').on('click', function() { if(currentPage < totalPages) { loadResults(currentPage + 1); } });

		$('#picker_results_body').on('change', 'input.picker-pick', function() {
			var $tr   = $(this).closest('tr');
			var data  = $tr.data('rowdata');
			var key   = $(this).data('key');
			if(this.checked) {
				selected[key] = {
					name:    data.GuestName    || '',
					contact: data.ContactNum   || '',
					email:   data.Email        || '',
					type:    data.GuestType    || ''
				};
			} else {
				delete selected[key];
			}
			renderSelected();
		});

		$('#picker_selected_body').on('click', '.picker-remove-btn', function() {
			var k = $(this).data('key');
			delete selected[k];
			renderSelected();
		});

		$('#picker_clear_all').on('click', function() {
			if(Object.keys(selected).length === 0) return;
			Swal.fire({
				title: 'Clear all selected guests?',
				icon: 'warning',
				showCancelButton: true,
				confirmButtonText: 'Yes, clear',
				cancelButtonText: 'Cancel'
			}).then(function(r) {
				if(r.isConfirmed) {
					selected = {};
					renderSelected();
				}
			});
		});

		$('#campaign_form').on('submit', function(e) {
			var name = $.trim($('input[name="Name"]').val());
			if(name === '') {
				e.preventDefault();
				Swal.fire({ icon: 'error', title: 'Name is required', timer: 1800, showConfirmButton: false });
				return false;
			}
		});

		renderSelected();
	})();
</script>
