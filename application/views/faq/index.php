<?php $is_owner = ((int)$this->session->level === 10); // only OWNER may create/edit/delete; others view only ?>

<div class="d-flex flex-column-fluid">
	<div class="container-fluid">
		<?php if($this->session->flashdata('faq_success')) { ?>
			<div class="alert alert-light-success" role="alert" style="border-left:4px solid #1bc5bd;">
				<?php echo htmlspecialchars($this->session->flashdata('faq_success')); ?>
			</div>
		<?php } ?>
		<?php if($this->session->flashdata('faq_error')) { ?>
			<div class="alert alert-light-danger" role="alert" style="border-left:4px solid #f64e60;">
				<?php echo htmlspecialchars($this->session->flashdata('faq_error')); ?>
			</div>
		<?php } ?>

		<div class="card card-custom mb-5">
			<div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
				<div class="card-title">
					<h3 class="card-label" style="color:#6082B6;">
						<strong>FAQ Records</strong>
					</h3>
				</div>
				<div class="card-toolbar">
					<a href="<?php echo base_url('Faq/Internal'); ?>" target="_blank" class="btn btn-light-primary font-weight-bold" data-toggle="tooltip" title="Open the internal FAQ page (staff login required)">
						<i class="la la-lock"></i>Internal Page
					</a>
					<?php /* temporary: external FAQ not needed for now
					<a href="<?php echo base_url('faq/external'); ?>" target="_blank" class="btn btn-light-success font-weight-bold ml-2" data-toggle="tooltip" title="Open the public external FAQ page">
						<i class="la la-external-link-alt"></i>External Page
					</a>
					*/ ?>
					<?php if($is_owner) { ?>
						<a href="<?php echo base_url('Faq/Create'); ?>" class="btn btn-primary font-weight-bold ml-2" style="width:160px;">
							<i class="la la-clipboard-list"></i>Create FAQ
						</a>
					<?php } ?>
					<?php $current_url = base_url($_SERVER['REQUEST_URI']); ?>
				</div>
			</div>
			<div class="card-body">
				<div class="accordion accordion-solid accordion-toggle-plus">
					<div class="card">
						<div class="card-header">
							<div id="faq_header" data-toggle="collapse" data-target="#faq_info" class="card-title collapsed" style="font-size:13px;">Filter By FAQ Information</div>
						</div>
						<div id="faq_info" class="collapse">
							<div class="card-body">
								<form action="<?php echo base_url('Faq') ?>" method="get" class="form">
									<div class="row">
										<div class="col-md-4">
											<div class="form-group">
												<label>Title</label>
												<div class="input-icon">
													<input type="text" name="title" value="<?php echo htmlspecialchars((string)$this->input->get('title'), ENT_QUOTES); ?>" autocomplete="off" class="form-control">
													<span><i class="la la-clipboard-list"></i></span>
												</div>
											</div>
										</div>
										<div class="col-md-4">
											<div class="form-group">
												<label>Type</label>
												<select name="type" class="form-control selectpicker">
													<option data-icon="la la-list font-size-lg bs-icon" value="">--SELECT TYPE--</option>
													<option data-icon="la la-user-shield font-size-lg bs-icon" value="internal" <?php if($this->input->get('type') === 'internal') { echo 'selected'; } ?>>Internal</option>
													<?php /* temporary: external FAQ not needed for now
													<option data-icon="la la-globe font-size-lg bs-icon" value="external" <?php if($this->input->get('type') === 'external') { echo 'selected'; } ?>>External</option>
													*/ ?>
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
				<div class="dataTables_wrapper dt-bootstrap4 no-footer" <?php if(empty($faqs)) { echo 'style="overflow-x:auto;"'; } ?>>
					<table id="kt_datatable" class="table table-bordered table-head-custom table-checkable dataTable no-footer dtr-inline">
						<thead>
							<tr>
								<th style="text-align:center;">No.</th>
								<th style="text-align:center;">Title</th>
								<?php /* temporary: external FAQ not needed for now, Type column hidden
								<th style="text-align:center;">Type</th>
								*/ ?>
								<th style="text-align:center;">Tags</th>
								<th style="text-align:center;">Destination</th>
								<th style="text-align:center;">Order</th>
								<th style="text-align:center;">Created By</th>
								<?php if($is_owner) { ?>
									<th class="action" style="text-align:center;">Action</th>
								<?php } ?>
							</tr>
						</thead>
						<tbody>
							<?php if(empty($faqs)) { ?>
								<tr><td colspan="<?php echo $is_owner ? 7 : 6; ?>" style="text-align:center; padding-top:10px; padding-bottom:10px;">FAQ Records Not Found</td></tr>
							<?php } else { $count = 1; foreach($faqs as $faq) { ?>
								<tr>
									<td style="text-align:center; padding-top:15px; padding-bottom:15px;"><?php echo $count; ?></td>
									<td style="text-align:left;"><strong><?php echo htmlspecialchars($faq->Title); ?></strong></td>
									<td style="text-align:center;">
										<?php
											$tag_names = ($faq->Tags === null || $faq->Tags === '') ? array() : explode('||', $faq->Tags);
											if(empty($tag_names)) {
												echo '<span class="text-muted">-</span>';
											} else {
												foreach($tag_names as $tag_name) {
													echo '<span class="label label-inline label-pill label-light-info font-weight-bold mr-1 mb-1">' . htmlspecialchars($tag_name) . '</span>';
												}
											}
										?>
									</td>
									<td style="text-align:center;">
										<?php
											$destination_names = ($faq->Destinations === null || $faq->Destinations === '') ? array() : explode('||', $faq->Destinations);
											if(empty($destination_names)) {
												echo '<span class="text-muted">-</span>';
											} else {
												foreach($destination_names as $destination_name) {
													echo '<span class="label label-inline label-pill label-light-primary font-weight-bold mr-1 mb-1">' . htmlspecialchars($destination_name) . '</span>';
												}
											}
										?>
									</td>
									<?php /* temporary: external FAQ not needed for now, Type column hidden
									<td style="text-align:center;">
										<?php if($faq->Type === 'external') { ?>
											<span class="label label-inline label-pill label-light-success font-weight-bold">External</span>
										<?php } else { ?>
											<span class="label label-inline label-pill label-light-primary font-weight-bold">Internal</span>
										<?php } ?>
									</td>
									*/ ?>
									<td style="text-align:center;"><?php echo (int)$faq->DisplayOrder; ?></td>
									<td style="text-align:center;"><?php echo htmlspecialchars((string)$faq->InsertByName); ?></td>
									<?php if($is_owner) { ?>
										<td style="text-align:center;">
											<div class="btn-group">
												<a href="<?php echo base_url('Faq/Update?faq_id=') . (int)$faq->FAQID; ?>" class="btn btn-icon btn-light-warning btn-sm" data-toggle="tooltip" title="Edit FAQ">
													<i class="la la-edit"></i>
												</a>
												<button onclick="Delete_Record('<?php echo base_url('assets/image/sweetalert.jpg'); ?>', '<?php echo 'FAQ : ' . str_replace('\'', '', $faq->Title); ?>', '<?php echo base_url('Faq/Delete'); ?>', 'faq_id', <?php echo (int)$faq->FAQID; ?>, 'Y', '<?php if(strpos($current_url, '?') == true) { echo base_url('Faq?') . (explode('?', $current_url))[1]; } else { echo base_url('Faq'); } ?>')" class="btn btn-icon btn-light-danger btn-sm" data-toggle="tooltip" title="Delete FAQ" style="margin-left:4px;">
													<i class="la la-trash"></i>
												</button>
											</div>
										</td>
									<?php } ?>
								</tr>
								<?php $count++; ?>
							<?php } } ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
	<?php if($this->input->get('title') !== null && $this->input->get('title') !== '' || ($this->input->get('type') !== null && $this->input->get('type') !== '')) { ?>
		$('#faq_header').click();
	<?php } ?>

	$('#reset').click(function() { Reset('<?php echo base_url('Faq'); ?>'); });
	$('[data-toggle="tooltip"]').tooltip();
</script>
