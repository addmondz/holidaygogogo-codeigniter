<?php
	$is_update  = ($mode === 'update');
	$submit_url = $is_update ? base_url('Faq/Update?faq_id=') . (int)$faq->FAQID : base_url('Faq/Create');
?>
<div class="d-flex flex-column-fluid">
	<div class="container-fluid">
		<form id="faq_form" method="post" action="<?php echo $submit_url; ?>">
			<?php if($is_update) { ?>
				<input type="hidden" name="faq_id" value="<?php echo (int)$faq->FAQID; ?>">
			<?php } ?>

			<div class="card card-custom mb-5">
				<div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
					<div class="card-title">
						<h3 class="card-label" style="color:#6082B6;">
							<strong><?php echo $is_update ? 'Update FAQ' : 'Create FAQ'; ?></strong>
						</h3>
					</div>
					<div class="card-toolbar">
						<a href="<?php echo base_url('Faq'); ?>" class="btn btn-light font-weight-bold" style="margin-right:6px;">
							<i class="la la-arrow-left"></i>Back
						</a>
						<button type="submit" class="btn btn-primary font-weight-bold">
							<i class="la la-save"></i><?php echo $is_update ? 'Save Changes' : 'Create FAQ'; ?>
						</button>
					</div>
				</div>
				<div class="card-body">
					<strong>FAQ Information :</strong>
					<br><br>
					<div class="row">
						<div class="col-md-8">
							<div class="form-group">
								<label>Title
									<span style="color:red;">*</span>
								</label>
								<div class="input-icon">
									<input type="text" name="Title" value="<?php echo htmlspecialchars((string)$faq->Title, ENT_QUOTES); ?>" autocomplete="off" class="form-control" required>
									<span><i class="la la-clipboard-list"></i></span>
								</div>
							</div>
						</div>
						<div class="col-md-2">
							<div class="form-group">
								<label>Type
									<span style="color:red;">*</span>
								</label>
								<select name="Type" class="form-control selectpicker">
									<option data-icon="la la-user-shield font-size-lg bs-icon" value="internal" <?php if($faq->Type === 'internal') { echo 'selected'; } ?>>Internal</option>
									<option data-icon="la la-globe font-size-lg bs-icon" value="external" <?php if($faq->Type === 'external') { echo 'selected'; } ?>>External</option>
								</select>
							</div>
						</div>
						<div class="col-md-2">
							<div class="form-group">
								<label>Display Order
									<a class="btn btn-icon btn-light-primary btn-xs" data-toggle="tooltip" title="Lower numbers appear first on the public page">
										<i class="la la-question"></i>
									</a>
								</label>
								<div class="input-icon">
									<input type="number" name="DisplayOrder" value="<?php echo (int)$faq->DisplayOrder; ?>" min="0" autocomplete="off" class="form-control">
									<span><i class="la la-sort-numeric-up"></i></span>
								</div>
							</div>
						</div>
						<div class="col-md-12">
							<div class="form-group">
								<label>Description</label>
								<textarea name="Description" rows="6" class="form-control" placeholder="Answer / details shown when the panel is expanded"><?php echo htmlspecialchars((string)$faq->Description, ENT_QUOTES); ?></textarea>
							</div>
						</div>
					</div>
				</div>
			</div>
		</form>
	</div>
</div>

<script>
	$('[data-toggle="tooltip"]').tooltip();
</script>
