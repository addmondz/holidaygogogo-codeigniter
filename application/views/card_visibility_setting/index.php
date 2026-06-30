<?php
	// Group the card registry by its UI heading, preserving registry order.
	$groups = array();
	foreach ($registry as $slug => $card) {
		$groups[$card['group']][$slug] = $card;
	}
	// Readable role label per Level for the column sub-text.
	$role_labels = array('20' => 'TC', '50' => 'TC2', '25' => 'Team Lead', '30' => 'Finance', '40' => 'OP', '45' => 'OP Lead');
?>
<div class="d-flex flex-column-fluid">
	<div class="container-fluid">
		<?php if($this->session->flashdata('card_visibility_setting_success')) { ?>
			<div class="alert alert-light-success" role="alert" style="border-left:4px solid #1bc5bd;">
				<?php echo htmlspecialchars($this->session->flashdata('card_visibility_setting_success')); ?>
			</div>
		<?php } ?>

		<div class="card card-custom mb-5">
			<div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
				<div class="card-title">
					<h3 class="card-label" style="color:#6082B6;">
						<strong>Card Visibility Settings</strong>
					</h3>
				</div>
			</div>
			<form action="<?php echo base_url('Card_Visibility_Setting/Save'); ?>" method="post">
				<div class="card-body">
					<div class="alert alert-light-primary" role="alert" style="border-left:4px solid #6082B6;">
						Each switch controls whether that user sees that dashboard card. Switches are
						<strong>ON</strong> (visible) by default &mdash; turn one <strong>OFF</strong> to hide that
						card from that user. Each card lists only the users whose role normally shows it. Tip:
						click a <strong>card name</strong> to toggle its whole row, or a <strong>user name</strong>
						to toggle that whole column.
					</div>

					<?php foreach($groups as $group_name => $cards):
						// Columns = users whose Level is eligible for any card in this group.
						$group_levels = array();
						foreach($cards as $c) { foreach($c['levels'] as $lv) { $group_levels[(int)$lv] = true; } }
						$group_users = array();
						foreach($users as $u) { if(isset($group_levels[(int)$u->Level])) { $group_users[] = $u; } }
					?>
						<div class="mb-3">
							<h5 style="color:#6082B6;"><strong><?php echo htmlspecialchars($group_name); ?></strong></h5>
							<?php if(empty($group_users)): ?>
								<div class="text-muted" style="padding:6px 0;">No active <?php echo htmlspecialchars($group_name); ?> users.</div>
							<?php else: ?>
							<div class="table-responsive" style="overflow-x:auto;">
								<table class="table table-bordered table-head-custom" style="min-width:560px;">
									<thead>
										<tr>
											<th style="text-align:left; min-width:230px; position:sticky; left:0; background:#fff; z-index:2;">Card</th>
											<?php foreach($group_users as $u): ?>
												<th class="cv-col-head" data-col="<?php echo (int)$u->AdminID; ?>" style="text-align:center; min-width:120px; cursor:pointer;" title="Toggle this user's whole column">
													<?php echo htmlspecialchars((string)$u->Name); ?>
													<div style="font-weight:normal; color:#6082B6; font-size:0.85rem;">
														<?php $lk=(string)$u->Level; echo htmlspecialchars(isset($role_labels[$lk]) ? $role_labels[$lk] : ('Level '.$lk)); ?>
													</div>
												</th>
											<?php endforeach; ?>
										</tr>
									</thead>
									<tbody>
										<?php foreach($cards as $slug => $card):
											$card_levels = array_map('intval', $card['levels']);
										?>
											<tr>
												<td class="cv-row-head" style="text-align:left; cursor:pointer; position:sticky; left:0; background:#fff; z-index:1;" title="Toggle this card's whole row">
													<strong><?php echo htmlspecialchars($card['title']); ?></strong>
												</td>
												<?php foreach($group_users as $u):
													$aid = (int)$u->AdminID;
													$eligible = in_array((int)$u->Level, $card_levels, true);
													$pair = $slug . '|' . $aid;
													$is_hidden = isset($hidden[$pair]);
												?>
													<td style="text-align:center;" data-col="<?php echo $aid; ?>">
														<?php if($eligible): ?>
															<span class="switch switch-sm switch-icon">
																<label class="mb-0">
																	<input type="checkbox" class="cv-switch" name="visible[]" value="<?php echo htmlspecialchars($pair); ?>" <?php echo $is_hidden ? '' : 'checked'; ?>>
																	<span></span>
																</label>
															</span>
														<?php else: ?>
															<span class="text-muted">&mdash;</span>
														<?php endif; ?>
													</td>
												<?php endforeach; ?>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
				<div class="card-footer text-right">
					<button type="submit" class="btn btn-primary font-weight-bold" style="min-width:170px;">
						<i class="la la-save"></i>Save Changes
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
(function() {
	// Click a card name -> toggle every switch in that row.
	document.querySelectorAll('.cv-row-head').forEach(function(cell) {
		cell.addEventListener('click', function() {
			var row = cell.closest('tr');
			var boxes = row.querySelectorAll('input.cv-switch');
			var anyOn = Array.prototype.some.call(boxes, function(b) { return b.checked; });
			boxes.forEach(function(b) { b.checked = !anyOn; });
		});
	});
	// Click a user name -> toggle every switch in that column.
	document.querySelectorAll('.cv-col-head').forEach(function(head) {
		head.addEventListener('click', function() {
			var col = head.getAttribute('data-col');
			var table = head.closest('table');
			var boxes = table.querySelectorAll('td[data-col="' + col + '"] input.cv-switch');
			var anyOn = Array.prototype.some.call(boxes, function(b) { return b.checked; });
			boxes.forEach(function(b) { b.checked = !anyOn; });
		});
	});
})();
</script>
