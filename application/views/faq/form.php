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
						<div class="col-md-4">
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
						<div class="col-md-3">
							<div class="form-group">
								<label>Tags
									<a class="btn btn-icon btn-light-primary btn-xs" data-toggle="tooltip" title="Manage the tag list under Settings &gt;&gt; FAQ Tag">
										<i class="la la-question"></i>
									</a>
								</label>
								<select name="Tags[]" class="form-control selectpicker" multiple data-actions-box="true" data-live-search="true" title="--SELECT TAGS--">
									<?php foreach($tags as $t) { ?>
										<option value="<?php echo (int)$t->FAQTagID; ?>" <?php if(in_array((int)$t->FAQTagID, $selected_tag_ids, true)) { echo 'selected'; } ?>><?php echo htmlspecialchars($t->Name); ?></option>
									<?php } ?>
								</select>
							</div>
						</div>
						<div class="col-md-3">
							<div class="form-group">
								<label>Destination</label>
								<select name="Destinations[]" class="form-control selectpicker" multiple data-actions-box="true" data-live-search="true" data-live-search-style="contains" data-live-search-normalize="true" title="--SELECT DESTINATION--">
									<?php foreach($destinations as $d) { ?>
										<option data-icon="la la-map-pin font-size-lg bs-icon" value="<?php echo (int)$d->CategoryID; ?>" <?php if(in_array((int)$d->CategoryID, $selected_destination_ids, true)) { echo 'selected'; } ?>><?php echo htmlspecialchars($d->Name); ?></option>
									<?php } ?>
								</select>
							</div>
						</div>
						<?php /* temporary: external FAQ not needed for now, so Type is always internal and the field is hidden
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
						*/ ?>
						<input type="hidden" name="Type" value="internal">
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
								<div class="d-flex justify-content-between align-items-center mb-2">
									<label class="mb-0">Sub Questions &amp; Answers</label>
									<button type="button" id="faq-add-item" class="btn btn-light-primary font-weight-bold btn-sm">
										<i class="la la-plus"></i>Add Sub Q&amp;A
									</button>
								</div>
								<div id="faq-items">
									<?php foreach($items as $index => $item) { ?>
										<div class="faq-item card mb-3" style="border:1px solid #e4e6ef;">
											<div class="card-body py-3">
												<div class="d-flex justify-content-between align-items-center mb-2">
													<span class="font-weight-bold text-muted faq-item-index"></span>
													<div class="btn-group">
														<button type="button" class="btn btn-icon btn-light-info btn-sm faq-item-copy" data-toggle="tooltip" title="Copy sub-question &amp; answer">
															<i class="la la-copy"></i>
														</button>
														<button type="button" class="btn btn-icon btn-light-danger btn-sm faq-item-remove ml-1" data-toggle="tooltip" title="Remove this sub Q&amp;A">
															<i class="la la-trash"></i>
														</button>
													</div>
												</div>
												<input type="text" name="sub_questions[]" class="form-control mb-2 faq-item-q" placeholder="Sub-question" autocomplete="off" value="<?php echo htmlspecialchars((string)$item['q'], ENT_QUOTES); ?>">
												<textarea name="sub_answers[]" rows="3" class="form-control faq-item-a" placeholder="Sub-answer"><?php echo htmlspecialchars((string)$item['a'], ENT_QUOTES); ?></textarea>
											</div>
										</div>
									<?php } ?>
								</div>
								<small class="form-text text-muted">Each sub-question and its sub-answer are required. Use the copy button to copy a pair to your clipboard.</small>
							</div>
						</div>
					</div>
				</div>
			</div>
		</form>

		<template id="faq-item-template">
			<div class="faq-item card mb-3" style="border:1px solid #e4e6ef;">
				<div class="card-body py-3">
					<div class="d-flex justify-content-between align-items-center mb-2">
						<span class="font-weight-bold text-muted faq-item-index"></span>
						<div class="btn-group">
							<button type="button" class="btn btn-icon btn-light-info btn-sm faq-item-copy" data-toggle="tooltip" title="Copy sub-question &amp; answer">
								<i class="la la-copy"></i>
							</button>
							<button type="button" class="btn btn-icon btn-light-danger btn-sm faq-item-remove ml-1" data-toggle="tooltip" title="Remove this sub Q&amp;A">
								<i class="la la-trash"></i>
							</button>
						</div>
					</div>
					<input type="text" name="sub_questions[]" class="form-control mb-2 faq-item-q" placeholder="Sub-question" autocomplete="off">
					<textarea name="sub_answers[]" rows="3" class="form-control faq-item-a" placeholder="Sub-answer"></textarea>
				</div>
			</div>
		</template>
	</div>
</div>

<script>
	(function () {
		var list     = document.getElementById('faq-items');
		var template = document.getElementById('faq-item-template');
		var addBtn   = document.getElementById('faq-add-item');

		function renumber() {
			var items = list.querySelectorAll('.faq-item');
			items.forEach(function (item, i) {
				item.querySelector('.faq-item-index').textContent = '#' + (i + 1);
			});
		}

		function bindTooltips(scope) {
			$(scope).find('[data-toggle="tooltip"]').tooltip();
		}

		function addItem() {
			var node = template.content.firstElementChild.cloneNode(true);
			list.appendChild(node);
			renumber();
			bindTooltips(node);
			node.querySelector('.faq-item-q').focus();
		}

		function copyText(text, btn) {
			var done = function () {
				var icon = btn.querySelector('i');
				if (!icon) { return; }
				var prev = icon.className;
				icon.className = 'la la-check';
				setTimeout(function () { icon.className = prev; }, 1200);
			};
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(done).catch(function () { fallbackCopy(text); done(); });
			} else {
				fallbackCopy(text);
				done();
			}
		}

		function fallbackCopy(text) {
			var ta = document.createElement('textarea');
			ta.value = text;
			ta.style.position = 'fixed';
			ta.style.opacity = '0';
			document.body.appendChild(ta);
			ta.focus();
			ta.select();
			try { document.execCommand('copy'); } catch (e) {}
			document.body.removeChild(ta);
		}

		addBtn.addEventListener('click', addItem);

		list.addEventListener('click', function (e) {
			var removeBtn = e.target.closest('.faq-item-remove');
			if (removeBtn) {
				removeBtn.closest('.faq-item').remove();
				renumber();
				return;
			}
			var copyBtn = e.target.closest('.faq-item-copy');
			if (copyBtn) {
				var item = copyBtn.closest('.faq-item');
				var q = item.querySelector('.faq-item-q').value.trim();
				var a = item.querySelector('.faq-item-a').value.trim();
				var text = [q, a].filter(Boolean).join('\n');
				copyText(text, copyBtn);
			}
		});

		// Start every fresh form with one empty row to fill in.
		if (!list.querySelector('.faq-item')) {
			addItem();
		} else {
			renumber();
		}
	})();

	$('[data-toggle="tooltip"]').tooltip();
</script>
