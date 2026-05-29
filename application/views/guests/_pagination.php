<?php
	$totalPages = ($total > 0) ? (int) ceil($total / $limit) : 0;
	$q = $query;
	unset($q['page']);

	if($totalPages > 1):
		$maxPagesToShow = 7;
		$half = floor($maxPagesToShow / 2);
		$startPage = max(1, $page - $half);
		$endPage   = min($totalPages, $page + $half);
		if($page <= $half) {
			$endPage = min($totalPages, $maxPagesToShow);
		}
		if($page + $half > $totalPages) {
			$startPage = max(1, $totalPages - $maxPagesToShow + 1);
		}
?>
	<?php $q['page'] = 1; ?>
	<a href="?<?= http_build_query($q) ?>" class="btn btn-sm <?= ($page == 1 ? 'btn-primary disabled' : 'btn-light') ?>">&laquo; First</a>

	<?php $q['page'] = max(1, $page - 1); ?>
	<a href="?<?= http_build_query($q) ?>" class="btn btn-sm <?= ($page == 1 ? 'btn-primary disabled' : 'btn-light') ?>">&lsaquo; Prev</a>

	<?php if($startPage > 1): ?><span class="btn btn-sm btn-light disabled">...</span><?php endif; ?>

	<?php for($i = $startPage; $i <= $endPage; $i++): $q['page'] = $i; ?>
		<a href="?<?= http_build_query($q) ?>" class="btn btn-sm <?= ($page == $i ? 'btn-primary' : 'btn-light') ?>"><?= $i ?></a>
	<?php endfor; ?>

	<?php if($endPage < $totalPages): ?><span class="btn btn-sm btn-light disabled">...</span><?php endif; ?>

	<?php $q['page'] = min($totalPages, $page + 1); ?>
	<a href="?<?= http_build_query($q) ?>" class="btn btn-sm <?= ($page == $totalPages ? 'btn-primary disabled' : 'btn-light') ?>">Next &rsaquo;</a>

	<?php $q['page'] = $totalPages; ?>
	<a href="?<?= http_build_query($q) ?>" class="btn btn-sm <?= ($page == $totalPages ? 'btn-primary disabled' : 'btn-light') ?>">Last &raquo;</a>
<?php endif; ?>
