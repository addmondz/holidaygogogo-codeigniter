<?php
	// Print-friendly PDF of every internal FAQ, rendered by Faq::Download_Pdf()
	// through dompdf. $rows is the flat Export_Rows() list (one entry per
	// sub-Q&A); they arrive grouped by FAQ in order, so we re-group on the title
	// to print each FAQ as one section. dompdf supports only a subset of CSS - no
	// flexbox/grid, no web fonts - so this stays on block layout + system fonts.
	$rows = isset($rows) ? $rows : array();

	// Re-group the flat rows into [title => [destinations, items[]]] preserving
	// order. A title-only row (blank question + answer) yields a section with no
	// items.
	$groups = array();
	foreach($rows as $row) {
		$title = $row['title'];
		if(!isset($groups[$title])) {
			$groups[$title] = array('destinations' => $row['destinations'], 'items' => array());
		}
		if(trim($row['question']) !== '' || trim($row['answer']) !== '') {
			$groups[$title]['items'][] = $row;
		}
	}
	$generated = date('j M Y');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
	* { margin: 0; padding: 0; box-sizing: border-box; }
	body {
		font-family: DejaVu Sans, sans-serif;
		color: #16202b;
		font-size: 11px;
		line-height: 1.5;
	}
	.cover { border-bottom: 2px solid #2f6fb0; padding-bottom: 12px; margin-bottom: 18px; }
	.cover .brand { font-size: 18px; font-weight: bold; color: #1d4e80; }
	.cover h1 { font-size: 22px; color: #16202b; margin-top: 4px; }
	.cover .meta { font-size: 10px; color: #5f6b78; margin-top: 6px; }

	.faq { margin-bottom: 16px; padding-bottom: 6px; }
	.faq-title {
		font-size: 14px;
		font-weight: bold;
		color: #1d4e80;
		border-left: 4px solid #2f6fb0;
		padding-left: 8px;
		margin-bottom: 4px;
	}
	.faq-dest { font-size: 9.5px; color: #5f6b78; padding-left: 12px; margin-bottom: 6px; }

	.qa { padding: 6px 0 6px 12px; border-bottom: 1px solid #e4ebf2; }
	.qa-q { font-weight: bold; color: #16202b; font-size: 11.5px; }
	.qa-a { color: #3a4753; margin-top: 3px; }
	.qa-foot { margin-top: 4px; font-size: 9px; color: #5f6b78; }
	.tag {
		display: inline-block;
		background: #e8f1fa;
		color: #1d4e80;
		font-size: 9px;
		padding: 1px 7px;
		border-radius: 8px;
		margin-right: 4px;
	}
	.empty-note { font-style: italic; color: #8a949e; padding-left: 12px; font-size: 10px; }
	.no-faqs { color: #5f6b78; font-style: italic; padding: 20px 0; }
</style>
</head>
<body>
	<div class="cover">
		<div class="brand">HolidayGoGoGo</div>
		<h1>FAQ Library</h1>
		<div class="meta">
			Every internal FAQ &middot; <?php echo count($groups); ?> FAQ<?php echo count($groups) === 1 ? '' : 's'; ?>
			&middot; Generated <?php echo htmlspecialchars($generated); ?>
		</div>
	</div>

	<?php if(empty($groups)) { ?>
		<div class="no-faqs">No FAQs yet.</div>
	<?php } else { foreach($groups as $title => $group) { ?>
		<div class="faq">
			<div class="faq-title"><?php echo htmlspecialchars($title); ?></div>
			<?php if($group['destinations'] !== '') { ?>
				<div class="faq-dest"><?php echo htmlspecialchars($group['destinations']); ?></div>
			<?php } ?>

			<?php if(empty($group['items'])) { ?>
				<div class="empty-note">No additional details for this FAQ yet.</div>
			<?php } else { foreach($group['items'] as $item) { ?>
				<div class="qa">
					<?php if(trim($item['question']) !== '') { ?>
						<div class="qa-q"><?php echo htmlspecialchars($item['question']); ?></div>
					<?php } ?>
					<?php if(trim($item['answer']) !== '') { ?>
						<div class="qa-a"><?php echo nl2br(htmlspecialchars($item['answer'])); ?></div>
					<?php } ?>
					<?php
						$foot = array();
						if($item['tags'] !== '') {
							foreach(explode(', ', $item['tags']) as $tag_name) {
								$foot[] = '<span class="tag">' . htmlspecialchars($tag_name) . '</span>';
							}
						}
						$updated = '';
						if($item['updated'] !== '') {
							$ts = strtotime($item['updated']);
							$updated = $ts ? date('j M Y', $ts) : $item['updated'];
						}
					?>
					<?php if(!empty($foot) || $updated !== '') { ?>
						<div class="qa-foot">
							<?php echo implode('', $foot); ?>
							<?php if($updated !== '') { ?>Updated <?php echo htmlspecialchars($updated); ?><?php } ?>
						</div>
					<?php } ?>
				</div>
			<?php } } ?>
		</div>
	<?php } } ?>
</body>
</html>
