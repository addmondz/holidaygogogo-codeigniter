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
						<div class="col-md-4">
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
						<div class="col-md-12">
							<div class="form-group">
								<div class="d-flex justify-content-between align-items-center mb-2">
									<label class="mb-0">Sub Questions &amp; Answers</label>
									<button type="button" id="faq-add-item" class="btn btn-light-primary font-weight-bold btn-sm">
										<i class="la la-plus"></i>Add Sub Q&amp;A
									</button>
								</div>
								<?php if(!empty($tags)) { ?>
									<div class="form-group mb-3" id="faq-item-filter-wrap">
										<label class="text-muted mb-1" style="font-size:12.5px;"><i class="la la-filter"></i> Filter sub Q&amp;A by tag</label>
										<div class="d-flex align-items-center flex-wrap">
											<!-- No name attribute: this control filters rows in the browser and never posts. -->
											<select id="faq-item-filter" class="form-control selectpicker" multiple data-actions-box="true" data-live-search="true" data-live-search-style="contains" data-live-search-normalize="true" title="--SHOW ALL--" style="max-width:420px;">
												<?php foreach($tags as $t) { ?>
													<option data-icon="la la-tag font-size-lg bs-icon" value="<?php echo (int)$t->FAQTagID; ?>"><?php echo htmlspecialchars($t->Name); ?></option>
												<?php } ?>
											</select>
											<span id="faq-item-filter-count" class="text-muted ml-3" style="font-size:12.5px;"></span>
										</div>
										<small class="form-text text-muted">A row matches if it carries any selected tag. Adding a sub Q&amp;A clears the filter.</small>
									</div>
								<?php } ?>
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
												<?php $item_tag_ids = (isset($item['tags']) && is_array($item['tags'])) ? $item['tags'] : array(); ?>
												<label class="text-muted mt-2 mb-1" style="font-size:12.5px;"><i class="la la-tag"></i> Tags for this Q&amp;A</label>
												<select name="sub_tags[<?php echo (int)$index; ?>][]" class="form-control selectpicker faq-item-tags" multiple data-actions-box="true" data-live-search="true" data-live-search-style="contains" data-live-search-normalize="true" title="--SELECT TAGS--">
													<?php foreach($tags as $t) { ?>
														<option data-icon="la la-tag font-size-lg bs-icon" value="<?php echo (int)$t->FAQTagID; ?>" <?php if(in_array((int)$t->FAQTagID, $item_tag_ids, true)) { echo 'selected'; } ?>><?php echo htmlspecialchars($t->Name); ?></option>
													<?php } ?>
												</select>
												<!-- Always-present empty value so a row with no tags still posts its
												     sub_tags[i] key, keeping the parallel arrays aligned by row. -->
												<input type="hidden" name="sub_tags[<?php echo (int)$index; ?>][]" value="" class="faq-item-tags-empty">
												<?php
													// Audit trail carried back so Build_Items can keep created-by/date
													// and bump updated-by/date only when this row's text changes. oq/oa
													// snapshot the saved text for that change detection.
													$cb = isset($item['cb']) ? (string)$item['cb'] : '';
													$cd = isset($item['cd']) ? (string)$item['cd'] : '';
													$ub = isset($item['ub']) ? (string)$item['ub'] : '';
													$ud = isset($item['ud']) ? (string)$item['ud'] : '';
												?>
												<input type="hidden" name="sub_cb[]" value="<?php echo htmlspecialchars($cb, ENT_QUOTES); ?>">
												<input type="hidden" name="sub_cd[]" value="<?php echo htmlspecialchars($cd, ENT_QUOTES); ?>">
												<input type="hidden" name="sub_ub[]" value="<?php echo htmlspecialchars($ub, ENT_QUOTES); ?>">
												<input type="hidden" name="sub_ud[]" value="<?php echo htmlspecialchars($ud, ENT_QUOTES); ?>">
												<input type="hidden" name="sub_oq[]" value="<?php echo htmlspecialchars((string)$item['q'], ENT_QUOTES); ?>">
												<input type="hidden" name="sub_oa[]" value="<?php echo htmlspecialchars((string)$item['a'], ENT_QUOTES); ?>">
												<?php if($ub !== '' && $ud !== '') { ?>
													<div class="text-muted mt-2" style="font-size:12.5px;">
														<i class="la la-history"></i> Last updated by <strong><?php echo htmlspecialchars($ub); ?></strong> &middot; <?php echo htmlspecialchars(date('j M Y, g:i A', strtotime($ud))); ?>
													</div>
												<?php } ?>
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
					<label class="text-muted mt-2 mb-1" style="font-size:12.5px;"><i class="la la-tag"></i> Tags for this Q&amp;A</label>
					<!-- name index re-stamped by renumber() once this row is in the DOM. -->
					<select name="sub_tags[][]" class="form-control selectpicker faq-item-tags" multiple data-actions-box="true" data-live-search="true" data-live-search-style="contains" data-live-search-normalize="true" title="--SELECT TAGS--">
						<?php foreach($tags as $t) { ?>
							<option data-icon="la la-tag font-size-lg bs-icon" value="<?php echo (int)$t->FAQTagID; ?>"><?php echo htmlspecialchars($t->Name); ?></option>
						<?php } ?>
					</select>
					<input type="hidden" name="sub_tags[][]" value="" class="faq-item-tags-empty">
					<!-- Empty audit fields keep new/copied rows aligned with the
					     parallel hidden arrays; blank cd marks the row as new. -->
					<input type="hidden" name="sub_cb[]" value="">
					<input type="hidden" name="sub_cd[]" value="">
					<input type="hidden" name="sub_ub[]" value="">
					<input type="hidden" name="sub_ud[]" value="">
					<input type="hidden" name="sub_oq[]" value="">
					<input type="hidden" name="sub_oa[]" value="">
				</div>
			</div>
		</template>
	</div>
</div>

<script>
	(function () {
		var list       = document.getElementById('faq-items');
		var template   = document.getElementById('faq-item-template');
		var addBtn     = document.getElementById('faq-add-item');
		var filterSel  = document.getElementById('faq-item-filter');
		var filterNote = document.getElementById('faq-item-filter-count');

		// Read selected values straight off the native <select>. selectedOptions is
		// the source of truth bootstrap-select keeps in sync, so this works whether
		// or not the picker widget has initialised — no dependence on $().val() state.
		function selectedValues(sel) {
			if (!sel) { return []; }
			return Array.prototype.map.call(sel.selectedOptions, function (o) { return o.value; });
		}

		// Show/hide rows by the tag filter. A row is shown when it carries any of
		// the selected tags; an empty filter shows everything. Pure client-side —
		// it never touches what the form posts.
		function applyFilter() {
			if (!filterSel) { return; }
			var selected = selectedValues(filterSel);
			var items    = list.querySelectorAll('.faq-item');
			if (!selected.length) {
				items.forEach(function (item) { item.style.display = ''; });
				if (filterNote) { filterNote.textContent = ''; }
				return;
			}
			var shown = 0;
			items.forEach(function (item) {
				// Scope to the real <select>: bootstrap-select copies the element's
				// classes onto its generated wrapper <div>, so a bare '.faq-item-tags'
				// would match that div first (no .selectedOptions) and throw.
				var tags  = selectedValues(item.querySelector('select.faq-item-tags'));
				var match = tags.some(function (t) { return selected.indexOf(t) !== -1; });
				item.style.display = match ? '' : 'none';
				if (match) { shown++; }
			});
			if (filterNote) {
				filterNote.textContent = 'Showing ' + shown + ' of ' + items.length;
			}
		}

		function clearFilter() {
			if (!filterSel) { return; }
			Array.prototype.forEach.call(filterSel.options, function (o) { o.selected = false; });
			if ($(filterSel).data('selectpicker')) { $(filterSel).selectpicker('refresh'); }
			applyFilter();
		}

		if (filterSel) {
			// Listen for both the selectpicker event and the native one so the
			// filter works even if the picker widget never upgrades this control.
			$(filterSel).on('changed.bs.select change', applyFilter);
			// Re-run when a row's own tags change so the view stays consistent.
			$(list).on('changed.bs.select change', 'select.faq-item-tags', applyFilter);
		}

		function renumber() {
			var items = list.querySelectorAll('.faq-item');
			items.forEach(function (item, i) {
				item.querySelector('.faq-item-index').textContent = '#' + (i + 1);
				// Re-stamp this row's tag field name so PHP receives sub_tags keyed
				// 0..n-1 in document order, aligned with the other parallel sub_*
				// arrays (the hidden empty input shares the name so a tagless row
				// still posts its key).
				item.querySelectorAll('select.faq-item-tags, .faq-item-tags-empty').forEach(function (el) {
					el.name = 'sub_tags[' + i + '][]';
				});
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
			// The cloned <select> is inert until it's in the DOM, so init its
			// searchable picker now (bootstrap-select guards against re-init).
			$(node).find('.faq-item-tags').selectpicker();
			// A new row has no tags yet, so an active filter would hide it. Clear
			// the filter so the row the user just asked for is actually visible.
			clearFilter();
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
				applyFilter();
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
