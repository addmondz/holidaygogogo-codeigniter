<?php
	// Grouped FAQ page (/Faq/All). Every active internal FAQ on one screen, each
	// rendered as its own section of sub-Q&As, with a single search box and tag
	// filter spanning the whole set. $faqs is the internal-only list from
	// Read_Faqs() (carries Title, Slug, Description, Destinations); $tag_names
	// maps FAQTagID => Name for the per-item tag chips. Mirrors the per-FAQ page
	// (faq/page.php) design so the two read as one product.

	// Decode every FAQ's sub-Q&As up front so we can both render them and build
	// the union of tags actually used across the whole library (id => name). The
	// tag bar only offers tags that appear on some item, so it never lists one
	// that would match nothing. Sorted by name.
	$decoded_faqs = array();
	$present_tags = array();
	foreach($faqs as $faq) {
		$items = Faq_Model::Decode_Items($faq->Description);
		$decoded_faqs[] = array('faq' => $faq, 'items' => $items);
		if(isset($tag_names)) {
			foreach($items as $it) {
				if(isset($it['tags']) && is_array($it['tags'])) {
					foreach($it['tags'] as $tid) {
						$tid = (int)$tid;
						if($tid > 0 && isset($tag_names[$tid])) {
							$present_tags[$tid] = $tag_names[$tid];
						}
					}
				}
			}
		}
	}
	asort($present_tags, SORT_NATURAL | SORT_FLAG_CASE);
?>
<!DOCTYPE html>
<html lang="en" data-faq-theme="internal">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex,nofollow">
	<title>HolidayGoGoGo &middot; FAQ Library</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
	<style>
		:root {
			/* Internal = blue: shared with the per-FAQ page's design tokens. */
			--accent:        #2f6fb0;
			--accent-deep:   #1d4e80;
			--accent-soft:   #e8f1fa;
			--accent-tint:   #f4f8fc;
			--ring:          rgba(47, 111, 176, 0.18);
			--ink:           #16202b;
			--muted:         #5f6b78;
			--line:          #e4ebf2;
			--bg:            #f6f8fb;
			--card:          #ffffff;
		}

		* { box-sizing: border-box; margin: 0; padding: 0; }

		body {
			font-family: 'Outfit', -apple-system, sans-serif;
			color: var(--ink);
			background:
				radial-gradient(1200px 600px at 100% -10%, var(--accent-soft) 0%, transparent 55%),
				radial-gradient(900px 500px at -10% 0%, var(--accent-tint) 0%, transparent 50%),
				var(--bg);
			min-height: 100vh;
			-webkit-font-smoothing: antialiased;
			line-height: 1.5;
		}

		.wrap {
			max-width: 820px;
			margin: 0 auto;
			padding: clamp(32px, 6vw, 80px) clamp(20px, 5vw, 32px) 80px;
		}

		/* ---- Header ---- */
		.brand {
			display: flex;
			align-items: center;
			gap: 10px;
			margin-bottom: 30px;
			font-weight: 600;
			letter-spacing: -0.01em;
			color: var(--ink);
		}
		.brand .mark {
			width: 30px; height: 30px;
			border-radius: 9px;
			background: linear-gradient(135deg, var(--accent), var(--accent-deep));
			display: grid; place-items: center;
			color: #fff; font-family: 'Fraunces', serif; font-weight: 700;
			box-shadow: 0 4px 12px var(--ring);
		}

		.eyebrow {
			display: inline-flex;
			align-items: center;
			gap: 8px;
			font-size: 12px;
			font-weight: 600;
			letter-spacing: 0.14em;
			text-transform: uppercase;
			color: var(--accent-deep);
			background: var(--card);
			border: 1px solid var(--line);
			padding: 7px 14px;
			border-radius: 100px;
			box-shadow: 0 1px 2px rgba(16, 32, 43, 0.04);
		}
		.eyebrow::before {
			content: "";
			width: 7px; height: 7px;
			border-radius: 50%;
			background: var(--accent);
			box-shadow: 0 0 0 4px var(--ring);
		}

		h1 {
			font-family: 'Fraunces', Georgia, serif;
			font-weight: 600;
			font-size: clamp(28px, 5vw, 44px);
			line-height: 1.1;
			letter-spacing: -0.02em;
			margin: 18px 0 0;
			color: var(--ink);
		}
		.lede { color: var(--muted); font-size: 15px; font-weight: 300; margin-top: 10px; }

		/* chips */
		.faq-meta { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 14px; }
		.chip {
			display: inline-flex;
			align-items: center;
			gap: 6px;
			font-size: 12.5px;
			font-weight: 500;
			padding: 4px 12px;
			border-radius: 100px;
			line-height: 1.4;
		}
		.chip-tag  { background: var(--accent-soft); color: var(--accent-deep); }
		.chip-dest { background: var(--card); color: var(--muted); border: 1px solid var(--line); }
		.chip-dest::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: var(--accent); }

		/* ---- Search + tag filter (whole-library controls) ---- */
		.controls {
			margin-top: clamp(26px, 5vw, 40px);
			background: var(--card);
			border: 1px solid var(--line);
			border-radius: 18px;
			padding: clamp(14px, 3vw, 22px);
			box-shadow: 0 18px 44px -30px rgba(16, 32, 43, 0.45);
			position: sticky;
			top: 12px;
			z-index: 5;
		}
		.faq-search { position: relative; }
		.faq-search .search-icon {
			position: absolute;
			left: 16px; top: 50%;
			transform: translateY(-50%);
			width: 17px; height: 17px;
			stroke: var(--muted);
			pointer-events: none;
		}
		.faq-search input {
			width: 100%;
			font-family: 'Outfit', sans-serif;
			font-size: 15px;
			color: var(--ink);
			padding: 13px 44px;
			border: 1px solid var(--line);
			border-radius: 12px;
			background: var(--accent-tint);
			outline: none;
			transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
		}
		.faq-search input::placeholder { color: var(--muted); }
		.faq-search input:focus {
			border-color: var(--accent);
			background: var(--card);
			box-shadow: 0 0 0 4px var(--ring);
		}
		.faq-search .search-clear {
			position: absolute;
			right: 10px; top: 50%;
			transform: translateY(-50%);
			width: 26px; height: 26px;
			display: none;
			align-items: center;
			justify-content: center;
			border: none;
			background: transparent;
			border-radius: 50%;
			cursor: pointer;
			color: var(--muted);
		}
		.faq-search .search-clear:hover { background: var(--accent-soft); color: var(--accent-deep); }
		.faq-search .search-clear svg { width: 15px; height: 15px; stroke: currentColor; }
		.faq-search.has-value .search-clear { display: flex; }

		.tag-filter {
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			gap: 7px;
			margin-top: 14px;
		}
		.tag-filter-label {
			display: inline-flex;
			align-items: center;
			gap: 6px;
			font-size: 12px;
			font-weight: 600;
			letter-spacing: 0.04em;
			text-transform: uppercase;
			color: var(--muted);
			margin-right: 2px;
		}
		.tag-filter-label svg { width: 14px; height: 14px; stroke: var(--muted); }
		.tag-filter-chip {
			font-family: 'Outfit', sans-serif;
			font-size: 12.5px;
			font-weight: 500;
			line-height: 1.4;
			padding: 5px 13px;
			border-radius: 100px;
			border: 1px solid var(--line);
			background: var(--card);
			color: var(--muted);
			cursor: pointer;
			transition: border-color .2s ease, background .2s ease, color .2s ease, box-shadow .2s ease;
		}
		.tag-filter-chip:hover { border-color: var(--accent); color: var(--accent-deep); }
		.tag-filter-chip:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
		.tag-filter-chip.is-active {
			background: var(--accent);
			border-color: var(--accent);
			color: #fff;
			box-shadow: 0 6px 16px -10px var(--ring);
		}
		.search-count {
			font-size: 12.5px;
			color: var(--muted);
			margin-top: 12px;
			min-height: 16px;
		}
		.search-count strong { color: var(--accent-deep); font-weight: 600; }

		/* Search keyword highlight inside questions/answers */
		mark.faq-hl {
			background: #ffe58a;
			color: inherit;
			padding: 0 1px;
			border-radius: 3px;
		}

		/* ---- FAQ section ---- */
		.faq-section {
			margin-top: clamp(20px, 4vw, 30px);
			background: var(--card);
			border: 1px solid var(--line);
			border-radius: 18px;
			padding: clamp(14px, 3vw, 22px);
			box-shadow: 0 18px 44px -30px rgba(16, 32, 43, 0.45);
		}
		.faq-section.is-hidden { display: none; }
		.faq-section-head {
			display: flex;
			align-items: flex-start;
			gap: 12px;
			padding-bottom: 6px;
		}
		.faq-section-title {
			flex: 1;
			font-family: 'Fraunces', Georgia, serif;
			font-weight: 600;
			font-size: clamp(19px, 3vw, 24px);
			line-height: 1.2;
			letter-spacing: -0.01em;
			color: var(--ink);
		}
		.faq-open-link {
			flex-shrink: 0;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 34px; height: 34px;
			border-radius: 10px;
			border: 1px solid var(--line);
			background: var(--card);
			color: var(--accent-deep);
			transition: border-color .2s ease, background .2s ease, box-shadow .2s ease;
		}
		.faq-open-link:hover { border-color: var(--accent); background: var(--accent-soft); box-shadow: 0 6px 18px -12px var(--ring); }
		.faq-open-link svg { width: 16px; height: 16px; stroke: currentColor; }

		/* ---- Accordion ---- */
		.a-list { margin-top: 6px; }
		.a-item { border-top: 1px solid var(--line); }
		.a-item:first-child { border-top: none; }
		.a-item.is-hidden { display: none; }

		.a-trigger {
			width: 100%;
			display: flex;
			align-items: center;
			gap: 14px;
			text-align: left;
			background: transparent;
			border: none;
			cursor: pointer;
			padding: 16px 4px;
		}
		.a-trigger:focus-visible {
			outline: 2px solid var(--accent);
			outline-offset: 2px;
			border-radius: 8px;
		}
		.a-q {
			flex: 1;
			font-family: 'Fraunces', Georgia, serif;
			font-weight: 600;
			font-size: clamp(15px, 2.2vw, 18px);
			line-height: 1.35;
			color: var(--ink);
			transition: color .2s ease;
		}
		.a-trigger:hover .a-q { color: var(--accent-deep); }
		.a-chevron {
			flex-shrink: 0;
			width: 20px; height: 20px;
			stroke: var(--accent);
			transition: transform .25s ease;
		}
		.a-item.is-open .a-chevron { transform: rotate(180deg); }
		.a-item.is-open .a-q { color: var(--accent-deep); }

		.a-panel {
			overflow: hidden;
			max-height: 0;
			transition: max-height .3s ease;
		}
		.a-panel-inner { padding: 0 4px 18px; }
		.a-a {
			color: var(--muted);
			font-size: 15.5px;
			font-weight: 300;
			line-height: 1.75;
		}
		.a-meta {
			display: flex;
			align-items: center;
			gap: 6px;
			margin-top: 12px;
			font-size: 12.5px;
			color: var(--accent-deep);
		}
		.a-meta svg { width: 13px; height: 13px; stroke: var(--accent-deep); flex-shrink: 0; }
		.item-tags { margin-top: 10px; }

		.empty-body { color: var(--muted); font-style: italic; font-weight: 300; padding: 6px 4px; }
		.no-results {
			margin-top: clamp(20px, 4vw, 30px);
			background: var(--card);
			border: 1px solid var(--line);
			border-radius: 18px;
			padding: 28px 22px;
			text-align: center;
			color: var(--muted);
			font-weight: 300;
			display: none;
		}
		.no-results.show { display: block; }

		footer {
			margin-top: 48px;
			padding-top: 22px;
			border-top: 1px solid var(--line);
			font-size: 13px;
			color: var(--muted);
			display: flex;
			justify-content: space-between;
			flex-wrap: wrap;
			gap: 8px;
		}
	</style>
</head>
<body>
	<main class="wrap">
		<div class="brand">
			<span class="mark">H</span>
			<span>HolidayGoGoGo</span>
		</div>

		<div>
			<span class="eyebrow">For Our Team</span>
		</div>
		<h1>FAQ Library</h1>
		<p class="lede">Every internal FAQ in one place. Search across all of them, or filter by tag.</p>

		<?php if(empty($decoded_faqs)) { ?>
			<div class="no-results show">No FAQs yet.</div>
		<?php } else { ?>
			<section class="controls">
				<div class="faq-search" id="faqSearch">
					<svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
					<input type="text" id="faqSearchInput" placeholder="Search all FAQs&hellip;" autocomplete="off" aria-label="Search all FAQs">
					<button type="button" class="search-clear" id="faqSearchClear" aria-label="Clear search" title="Clear search">
						<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
					</button>
				</div>
				<?php if(!empty($present_tags)) { ?>
					<div class="tag-filter" id="tagFilter" role="group" aria-label="Filter by tag">
						<span class="tag-filter-label">
							<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
							Tags
						</span>
						<button type="button" class="tag-filter-chip is-active" data-tag-all aria-pressed="true">All</button>
						<?php foreach($present_tags as $tid => $tname) { ?>
							<button type="button" class="tag-filter-chip" data-tag="<?php echo (int)$tid; ?>" aria-pressed="false"><?php echo htmlspecialchars($tname); ?></button>
						<?php } ?>
					</div>
				<?php } ?>
				<div class="search-count" id="searchCount" aria-live="polite"></div>
			</section>

			<div id="faqLibrary">
			<?php foreach($decoded_faqs as $entry) {
				$faq        = $entry['faq'];
				$items      = $entry['items'];
				$dest_names = ($faq->Destinations === null || $faq->Destinations === '') ? array() : explode('||', $faq->Destinations);
				// Lowercased haystack for the FAQ title + destinations, so a search
				// can surface a whole section by its title even when no sub-item
				// text matches.
				$section_blob = strtolower(trim((string)$faq->Title) . ' ' . implode(' ', $dest_names));
			?>
				<section class="faq-section" data-search="<?php echo htmlspecialchars($section_blob, ENT_QUOTES); ?>">
					<div class="faq-section-head">
						<h2 class="faq-section-title"><?php echo htmlspecialchars($faq->Title); ?></h2>
						<?php if(!empty($faq->Slug)) { ?>
							<a href="<?php echo htmlspecialchars(base_url('faq/' . rawurlencode($faq->Slug)), ENT_QUOTES); ?>" target="_blank" rel="noopener" class="faq-open-link" title="Open this FAQ on its own page" aria-label="Open this FAQ on its own page">
								<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
							</a>
						<?php } ?>
					</div>
					<?php if(!empty($dest_names)) { ?>
						<div class="faq-meta">
							<?php foreach($dest_names as $dest_name) { ?>
								<span class="chip chip-dest"><?php echo htmlspecialchars($dest_name); ?></span>
							<?php } ?>
						</div>
					<?php } ?>

					<?php if(empty($items)) { ?>
						<p class="empty-body">No additional details for this FAQ yet.</p>
					<?php } else { ?>
						<div class="a-list">
						<?php foreach($items as $item) {
							// Resolve per-item tag chips through the $tag_names map.
							// $item_tag_ids feeds the data-tags attribute the tag filter reads.
							$item_tags    = array();
							$item_tag_ids = array();
							if(isset($tag_names) && isset($item['tags']) && is_array($item['tags'])) {
								foreach($item['tags'] as $tid) {
									$tid = (int)$tid;
									if(isset($tag_names[$tid])) {
										$item_tags[]    = $tag_names[$tid];
										$item_tag_ids[] = $tid;
									}
								}
							}
							$q_text = trim((string)$item['q']);
							$a_text = trim((string)$item['a']);
							$label  = $q_text !== '' ? $q_text : 'Details';
							// Lowercased haystack the client search filters against:
							// FAQ title + sub-question + sub-answer + tag names, so a
							// match on the parent FAQ's title still surfaces its items.
							$search_blob = strtolower((string)$faq->Title . ' ' . $q_text . ' ' . $a_text . ' ' . implode(' ', $item_tags));
							$ud = isset($item['ud']) ? trim((string)$item['ud']) : '';
						?>
							<div class="a-item" data-search="<?php echo htmlspecialchars($search_blob, ENT_QUOTES); ?>" data-tags="<?php echo htmlspecialchars(implode(' ', $item_tag_ids), ENT_QUOTES); ?>">
								<button type="button" class="a-trigger" aria-expanded="false">
									<span class="a-q"><?php echo htmlspecialchars($label); ?></span>
									<svg class="a-chevron" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
								</button>
								<div class="a-panel">
									<div class="a-panel-inner">
										<?php if($a_text !== '') { ?>
											<div class="a-a"><?php echo nl2br(htmlspecialchars($a_text)); ?></div>
										<?php } ?>
										<?php if(!empty($item_tags)) { ?>
											<div class="faq-meta item-tags">
												<?php foreach($item_tags as $tag_name) { ?>
													<span class="chip chip-tag"><?php echo htmlspecialchars($tag_name); ?></span>
												<?php } ?>
											</div>
										<?php } ?>
										<?php if($ud !== '') { $ud_ts = strtotime($ud); ?>
											<span class="a-meta">
												<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><polyline points="12 7 12 12 15.5 14"></polyline></svg>
												Updated <?php echo htmlspecialchars($ud_ts ? date('j M Y', $ud_ts) : $ud); ?>
											</span>
										<?php } ?>
									</div>
								</div>
							</div>
						<?php } ?>
						</div>
					<?php } ?>
				</section>
			<?php } ?>
			</div>
			<div class="no-results" id="noResults">No FAQs match your search.</div>
		<?php } ?>

		<footer>
			<span>&copy; <?php echo date('Y'); ?> HolidayGoGoGo</span>
			<span><?php echo count($decoded_faqs); ?> FAQ<?php echo count($decoded_faqs) === 1 ? '' : 's'; ?></span>
		</footer>
	</main>

	<script>
		(function () {
			var library = document.getElementById('faqLibrary');
			if (!library) return;
			var sections = Array.prototype.slice.call(library.querySelectorAll('.faq-section'));
			var items = Array.prototype.slice.call(library.querySelectorAll('.a-item'));

			function sizePanel(item) {
				var panel = item.querySelector('.a-panel');
				panel.style.maxHeight = item.classList.contains('is-open') ? panel.scrollHeight + 'px' : '0px';
			}
			function openItem(item) {
				var t = item.querySelector('.a-trigger');
				item.classList.add('is-open');
				t.setAttribute('aria-expanded', 'true');
				sizePanel(item);
			}
			function closeItem(item) {
				var t = item.querySelector('.a-trigger');
				item.classList.remove('is-open');
				t.setAttribute('aria-expanded', 'false');
				item.querySelector('.a-panel').style.maxHeight = '0px';
			}

			items.forEach(function (item) {
				item.querySelector('.a-trigger').addEventListener('click', function () {
					if (item.classList.contains('is-open')) closeItem(item); else openItem(item);
				});
			});

			// Re-measure any open panel after a resize reflows its content.
			var resizeTimer;
			window.addEventListener('resize', function () {
				clearTimeout(resizeTimer);
				resizeTimer = setTimeout(function () {
					items.forEach(function (item) { if (item.classList.contains('is-open')) sizePanel(item); });
				}, 120);
			});

			var input = document.getElementById('faqSearchInput');
			var wrap = document.getElementById('faqSearch');
			var clearBtn = document.getElementById('faqSearchClear');
			var countEl = document.getElementById('searchCount');
			var noResults = document.getElementById('noResults');

			// Active tag ids (as strings). Empty = no tag filter. AND semantics:
			// an item shows only if it carries EVERY active tag.
			var activeTags = [];
			function matchesTags(item) {
				if (activeTags.length === 0) return true;
				var raw = (item.getAttribute('data-tags') || '').trim();
				if (raw === '') return false;
				var ids = raw.split(' ');
				for (var i = 0; i < activeTags.length; i++) {
					if (ids.indexOf(activeTags[i]) === -1) return false;
				}
				return true;
			}

			// ---- Keyword highlight ----
			// Wrap every occurrence of the query in <mark> across an element's text
			// nodes only, so we never break the answer's <br> markup. Clearing first
			// makes it idempotent as the user types.
			function escapeRx(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }
			function clearHl(el) {
				var marks = el.querySelectorAll('mark.faq-hl');
				for (var i = 0; i < marks.length; i++) {
					var m = marks[i];
					m.parentNode.replaceChild(document.createTextNode(m.textContent), m);
				}
				el.normalize();
			}
			function highlight(el, q) {
				clearHl(el);
				if (!q) return;
				var rx = new RegExp(escapeRx(q), 'gi');
				var walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, null, false);
				var nodes = [], n;
				while ((n = walker.nextNode())) nodes.push(n);
				nodes.forEach(function (node) {
					var text = node.nodeValue;
					rx.lastIndex = 0;
					if (!rx.test(text)) return;
					rx.lastIndex = 0;
					var frag = document.createDocumentFragment(), last = 0, m;
					while ((m = rx.exec(text)) !== null) {
						if (m.index > last) frag.appendChild(document.createTextNode(text.slice(last, m.index)));
						var mark = document.createElement('mark');
						mark.className = 'faq-hl';
						mark.textContent = m[0];
						frag.appendChild(mark);
						last = m.index + m[0].length;
						if (m[0].length === 0) rx.lastIndex++;
					}
					if (last < text.length) frag.appendChild(document.createTextNode(text.slice(last)));
					node.parentNode.replaceChild(frag, node);
				});
			}
			function highlightFields(root, selector, q) {
				var fields = root.querySelectorAll(selector);
				for (var f = 0; f < fields.length; f++) {
					if (q !== '') highlight(fields[f], q); else clearHl(fields[f]);
				}
			}

			function runSearch() {
				var q = input.value.trim().toLowerCase();
				wrap.classList.toggle('has-value', q !== '');
				var filtering = q !== '' || activeTags.length > 0;
				var matches = 0;

				sections.forEach(function (section) {
					var sectionItems = Array.prototype.slice.call(section.querySelectorAll('.a-item'));
					var visibleItems = 0;
					sectionItems.forEach(function (item) {
						var hit = (q === '' || item.getAttribute('data-search').indexOf(q) !== -1) && matchesTags(item);
						item.classList.toggle('is-hidden', !hit);
						// Auto-expand on a text query so answers are visible; tag-only
						// filtering keeps items collapsed. Collapse when nothing applies.
						if (hit && q !== '') openItem(item); else closeItem(item);
						// Highlight the keyword in visible hits; strip highlights otherwise.
						highlightFields(item, '.a-q, .a-a', hit && q !== '' ? q : '');
						if (hit) visibleItems++;
					});

					var sectionHit;
					if (sectionItems.length > 0) {
						sectionHit = visibleItems > 0;
					} else {
						// A detail-less FAQ has no items to match; surface it only on a
						// title/destination text match, and never under a tag filter.
						sectionHit = activeTags.length === 0 && (q === '' || section.getAttribute('data-search').indexOf(q) !== -1);
					}
					section.classList.toggle('is-hidden', !sectionHit);
					// Highlight the section title on a visible text match too.
					highlightFields(section, '.faq-section-title', sectionHit && q !== '' ? q : '');
					if (sectionHit) matches += (sectionItems.length > 0 ? visibleItems : 1);
				});

				if (!filtering) {
					countEl.textContent = '';
					noResults.classList.remove('show');
				} else {
					noResults.classList.toggle('show', matches === 0);
					countEl.innerHTML = matches === 0 ? '' :
						'<strong>' + matches + '</strong> ' + (matches === 1 ? 'match' : 'matches');
				}
			}

			// ---- Tag filter chips ----
			var tagBar = document.getElementById('tagFilter');
			if (tagBar) {
				var tagChips = Array.prototype.slice.call(tagBar.querySelectorAll('.tag-filter-chip[data-tag]'));
				var allChip = tagBar.querySelector('.tag-filter-chip[data-tag-all]');
				function syncAll() {
					if (allChip) allChip.classList.toggle('is-active', activeTags.length === 0);
				}
				tagChips.forEach(function (chip) {
					chip.addEventListener('click', function () {
						var id = chip.getAttribute('data-tag');
						var idx = activeTags.indexOf(id);
						var nowActive = idx === -1;
						if (nowActive) activeTags.push(id); else activeTags.splice(idx, 1);
						chip.classList.toggle('is-active', nowActive);
						chip.setAttribute('aria-pressed', nowActive ? 'true' : 'false');
						syncAll();
						runSearch();
					});
				});
				if (allChip) {
					allChip.addEventListener('click', function () {
						activeTags = [];
						tagChips.forEach(function (c) {
							c.classList.remove('is-active');
							c.setAttribute('aria-pressed', 'false');
						});
						syncAll();
						runSearch();
					});
				}
			}

			if (input) {
				input.addEventListener('input', runSearch);
				input.addEventListener('keydown', function (e) {
					if (e.key === 'Escape') { input.value = ''; runSearch(); }
				});
			}
			if (clearBtn) {
				clearBtn.addEventListener('click', function () {
					input.value = '';
					runSearch();
					input.focus();
				});
			}
		})();
	</script>
</body>
</html>
