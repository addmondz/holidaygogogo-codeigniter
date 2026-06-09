<div class="d-flex flex-column-fluid">
	<div class="container-fluid">
		<?php if($this->session->flashdata('faq_tag_success')) { ?>
			<div class="alert alert-light-success" role="alert" style="border-left:4px solid #1bc5bd;">
				<?php echo htmlspecialchars($this->session->flashdata('faq_tag_success')); ?>
			</div>
		<?php } ?>
		<?php if($this->session->flashdata('faq_tag_error')) { ?>
			<div class="alert alert-light-danger" role="alert" style="border-left:4px solid #f64e60;">
				<?php echo htmlspecialchars($this->session->flashdata('faq_tag_error')); ?>
			</div>
		<?php } ?>

		<div class="card card-custom mb-5">
			<div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
				<div class="card-title">
					<h3 class="card-label" style="color:#6082B6;">
						<strong>FAQ Tag Records</strong>
					</h3>
				</div>
				<div class="card-toolbar">
					<a href="<?php echo base_url('Faq_Tag/Create'); ?>" class="btn btn-primary font-weight-bold" style="width:170px;">
						<i class="la la-tag"></i>Create FAQ Tag
					</a>
					<?php $current_url = base_url($_SERVER['REQUEST_URI']); ?>
				</div>
			</div>
			<div class="card-body">
				<div class="accordion accordion-solid accordion-toggle-plus">
					<div class="card">
						<div class="card-header">
							<div id="faq_tag_header" data-toggle="collapse" data-target="#faq_tag_info" class="card-title collapsed" style="font-size:13px;">Filter By FAQ Tag Information</div>
						</div>
						<div id="faq_tag_info" class="collapse">
							<div class="card-body">
								<form action="<?php echo base_url('Faq_Tag') ?>" method="get" class="form">
									<div class="row">
										<div class="col-md-4">
											<div class="form-group">
												<label>Name</label>
												<div class="input-icon">
													<input type="text" name="name" value="<?php echo htmlspecialchars((string)$this->input->get('name'), ENT_QUOTES); ?>" autocomplete="off" class="form-control">
													<span><i class="la la-tag"></i></span>
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
				<div class="dataTables_wrapper dt-bootstrap4 no-footer" <?php if(empty($tags)) { echo 'style="overflow-x:auto;"'; } ?>>
					<table id="kt_datatable" class="table table-bordered table-head-custom table-checkable dataTable no-footer dtr-inline">
						<thead>
							<tr>
								<th style="text-align:center;">No.</th>
								<th style="text-align:center;">Name</th>
								<th style="text-align:center;">Created By</th>
								<th class="action" style="text-align:center;">Action</th>
							</tr>
						</thead>
						<tbody>
							<?php if(empty($tags)) { ?>
								<tr><td colspan="4" style="text-align:center; padding-top:10px; padding-bottom:10px;">FAQ Tag Records Not Found</td></tr>
							<?php } else { $count = 1; foreach($tags as $tag) { ?>
								<tr>
									<td style="text-align:center; padding-top:15px; padding-bottom:15px;"><?php echo $count; ?></td>
									<td style="text-align:left;"><strong><?php echo htmlspecialchars($tag->Name); ?></strong></td>
									<td style="text-align:center;"><?php echo htmlspecialchars((string)$tag->InsertByName); ?></td>
									<td style="text-align:center;">
										<div class="btn-group">
											<a href="<?php echo base_url('Faq_Tag/Update?faq_tag_id=') . (int)$tag->FAQTagID; ?>" class="btn btn-icon btn-light-warning btn-sm" data-toggle="tooltip" title="Edit FAQ Tag">
												<i class="la la-edit"></i>
											</a>
											<button onclick="Delete_Record('<?php echo base_url('assets/image/sweetalert.jpg'); ?>', '<?php echo 'FAQ Tag : ' . str_replace('\'', '', $tag->Name); ?>', '<?php echo base_url('Faq_Tag/Delete'); ?>', 'faq_tag_id', <?php echo (int)$tag->FAQTagID; ?>, 'Y', '<?php if(strpos($current_url, '?') == true) { echo base_url('Faq_Tag?') . (explode('?', $current_url))[1]; } else { echo base_url('Faq_Tag'); } ?>')" class="btn btn-icon btn-light-danger btn-sm" data-toggle="tooltip" title="Delete FAQ Tag" style="margin-left:4px;">
												<i class="la la-trash"></i>
											</button>
										</div>
									</td>
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
	<?php if($this->input->get('name') !== null && $this->input->get('name') !== '') { ?>
		$('#faq_tag_header').click();
	<?php } ?>

	$('#reset').click(function() { Reset('<?php echo base_url('Faq_Tag'); ?>'); });
	$('[data-toggle="tooltip"]').tooltip();
</script>
