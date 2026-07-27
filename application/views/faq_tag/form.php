<?php
	$is_update  = ($mode === 'update');
	$submit_url = $is_update ? base_url('Faq_Tag/Update?faq_tag_id=') . (int)$tag->FAQTagID : base_url('Faq_Tag/Create');
?>
<div class="d-flex flex-column-fluid">
	<div class="container-fluid">
		<form id="faq_tag_form" method="post" action="<?php echo $submit_url; ?>">
			<?php if($is_update) { ?>
				<input type="hidden" name="faq_tag_id" value="<?php echo (int)$tag->FAQTagID; ?>">
			<?php } ?>

			<div class="card card-custom mb-5">
				<div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
					<div class="card-title">
						<h3 class="card-label" style="color:#6082B6;">
							<strong><?php echo $is_update ? 'Update FAQ Tag' : 'Create FAQ Tag'; ?></strong>
						</h3>
					</div>
					<div class="card-toolbar">
						<a href="<?php echo base_url('Faq_Tag'); ?>" class="btn btn-light font-weight-bold" style="margin-right:6px;">
							<i class="la la-arrow-left"></i>Back
						</a>
						<button type="submit" class="btn btn-primary font-weight-bold">
							<i class="la la-save"></i><?php echo $is_update ? 'Save Changes' : 'Create FAQ Tag'; ?>
						</button>
					</div>
				</div>
				<div class="card-body">
					<strong>FAQ Tag Information :</strong>
					<br><br>
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label>Name
									<span style="color:red;">*</span>
								</label>
								<div class="input-icon">
									<input type="text" name="Name" value="<?php echo htmlspecialchars((string)$tag->Name, ENT_QUOTES); ?>" autocomplete="off" class="form-control" required>
									<span><i class="la la-tag"></i></span>
								</div>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label>Default Tag</label>
								<div class="checkbox-inline" style="margin-top:8px;">
									<label class="checkbox checkbox-lg">
										<input type="checkbox" name="IsDefault" value="Y" <?php echo (isset($tag->IsDefault) && $tag->IsDefault === 'Y') ? 'checked' : ''; ?>><span></span> Show up-front on the internal FAQ Library (<strong>/Faq/Internal</strong>)
									</label>
								</div>
								<span class="form-text" style="color:#5f6b78; font-size:12.5px;">Only default tags appear as chips on /Faq/Internal. Other tags stay reachable through the tag search bar.</span>
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
