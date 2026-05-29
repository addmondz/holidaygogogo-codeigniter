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
</style>

<div class="d-flex flex-column-fluid">
	<div class="container-fluid">
		<div class="card card-custom mb-5">
			<div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
				<div class="card-title">
					<h3 class="card-label" style="color:#6082B6;">
						<strong>Guest List Records</strong>
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
								<form action="<?php echo base_url('Guests') ?>" method="get" class="form">
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
										<div class="col-md-3">
											<div class="form-group">
												<label>Type</label>
												<select name="type" class="form-control selectpicker">
													<option selected value="">--ALL TYPES--</option>
													<option value="guest" <?php if($this->input->get('type') === 'guest') echo 'selected'; ?>>Booking Guest</option>
													<option value="ghl"   <?php if($this->input->get('type') === 'ghl')   echo 'selected'; ?>>GHL</option>
												</select>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-3">
											<div class="form-group">
												<label>Booking Date
													<a onclick="Reset_Booking_Date()" class="btn btn-icon btn-light-warning btn-xs" data-toggle="tooltip" title="Clear booking date">
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
								<th style="text-align:center;">Name</th>
								<th style="text-align:center;">Contact Num</th>
								<th style="text-align:center;">Email</th>
								<th style="text-align:center;">Language</th>
								<th style="text-align:center;">Agent Name</th>
								<th style="text-align:center;">Source</th>
								<th style="text-align:center;">Customer Type</th>
								<th style="text-align:center;">Nationality</th>
								<th style="text-align:center;">Gender</th>
								<th style="text-align:center;">DOB</th>
								<th style="text-align:center;">Type</th>
								<th style="text-align:center;">Num of Pax</th>
								<th style="text-align:center;">Total Sales (RM)</th>
								<th class="action" style="text-align:center;">Action</th>
							</tr>
						</thead>
						<tbody>
							<?php if(empty($guests)) { ?>
								<tr><td colspan="15" style="text-align:center; padding-top:10px; padding-bottom:10px;">Guest Records Not Found</td></tr>
							<?php } else { ?>
								<?php $count = 1; foreach($guests as $g) { ?>
									<tr>
										<td style="text-align:center; padding-top:15px; padding-bottom:15px;"><?php echo $count; ?></td>
										<td style="text-align:center;"><?php echo htmlspecialchars($g->Name); ?></td>
										<td style="text-align:center;"><?php echo htmlspecialchars($g->ContactNum); ?></td>
										<td style="text-align:center;"><?php echo htmlspecialchars($g->Email); ?></td>
										<td style="text-align:center;"><?php echo htmlspecialchars($g->Language); ?></td>
										<td style="text-align:center;"><?php echo htmlspecialchars($g->AgentName); ?></td>
										<td style="text-align:center;"><?php echo htmlspecialchars($g->Source); ?></td>
										<td style="text-align:center;"><?php echo htmlspecialchars($g->CustomerType); ?></td>
										<td style="text-align:center;"><?php echo htmlspecialchars($g->Nationality); ?></td>
										<td style="text-align:center;"><?php echo htmlspecialchars($g->Gender); ?></td>
										<td style="text-align:center;">
											<?php
												if(!empty($g->DOB) && $g->DOB !== '0000-00-00') {
													echo date('d M Y', strtotime($g->DOB));
												}
											?>
										</td>
										<td style="text-align:center; white-space:nowrap;">
											<?php
												$is_ghl  = isset($g->Type) && $g->Type === 'GHL';
												$cls     = $is_ghl ? 'label-light-warning' : 'label-light-success';
												$txt     = $is_ghl ? 'GHL' : 'Booking Guest';
											?>
											<span class="label label-inline label-pill <?php echo $cls; ?> font-weight-bold" style="white-space:nowrap;">
												<?php echo $txt; ?>
											</span>
										</td>
										<td style="text-align:center;"><?php echo (int) $g->TotalPax; ?></td>
										<td style="text-align:right;"><?php echo number_format((float) $g->TotalSales, 2); ?></td>
										<td style="text-align:center;">
											<?php if(!$is_ghl) { ?>
												<a href="<?php echo base_url('Guests/View?key=') . urlencode($g->dedup_key); ?>" class="btn btn-light-primary btn-sm" data-toggle="tooltip" title="View trip history">
													<i class="la la-eye"></i>
												</a>
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
		$expanded_keys = array('q', 'sales_agent', 'source', 'customer_type', 'nationality', 'gender', 'language', 'booking_date', 'travel_date', 'type');
		$expand = false;
		foreach($expanded_keys as $k) {
			if($this->input->get($k) !== null && $this->input->get($k) !== '') { $expand = true; break; }
		}
	?>
	<?php if($expand) { ?>
		$('#guests_header').click();
	<?php } ?>

	$('#reset').click(function() {
		Reset('<?php echo base_url('Guests'); ?>');
	});

	<?php if(!empty($guests)): ?>
		$(function() {
			$.ajax({
				url: '<?php echo base_url('Guests/Count'); ?>' + (window.location.search || ''),
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
</script>
