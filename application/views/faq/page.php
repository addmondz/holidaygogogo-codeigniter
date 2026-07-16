<?php
	// Per-FAQ page (/faq/<slug>). Staff-only single-record FAQ view: the FAQ
	// title, every sub-Q&A expanded, per-item tags + audit, and whole-FAQ
	// destinations. $faq is one row from Read_By_Slug(); $tag_names maps
	// FAQTagID => Name for the per-item tag chips.
	$items      = Faq_Model::Decode_Items($faq->Description);
	$dest_names = ($faq->Destinations === null || $faq->Destinations === '') ? array() : explode('||', $faq->Destinations);
	$updated_ts = isset($faq->UpdateDate) ? strtotime((string)$faq->UpdateDate) : false;

	// Union of tags actually used across this FAQ's sub-Q&As (id => name),
	// for the tag filter bar. Only tags that appear on an item are offered,
	// so the bar never lists a tag that would match nothing. Sorted by name.
	$present_tags = array();
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
		asort($present_tags, SORT_NATURAL | SORT_FLAG_CASE);
	}
?>
<!DOCTYPE html>
<html lang="en" data-faq-theme="internal">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex,nofollow">
	<title>HolidayGoGoGo &middot; <?php echo htmlspecialchars($faq->Title); ?></title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
	<style>
		:root {
			/* Internal = blue: the per-FAQ page's design tokens. */
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
			max-width: 760px;
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

		/* whole-FAQ destination chips */
		.faq-meta { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 18px; }
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

		/* ---- Body card ---- */
		.sheet {
			margin-top: clamp(26px, 5vw, 40px);
			background: var(--card);
			border: 1px solid var(--line);
			border-radius: 18px;
			padding: clamp(14px, 3vw, 22px);
			box-shadow: 0 18px 44px -30px rgba(16, 32, 43, 0.45);
		}

		/* ---- Search ---- */
		.faq-search { position: relative; margin: 4px 0 4px; }
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

		.search-count {
			font-size: 12.5px;
			color: var(--muted);
			margin: 2px 4px 4px;
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

		/* ---- Tag filter ---- */
		.tag-filter {
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			gap: 7px;
			margin: 12px 0 2px;
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

		/* ---- Accordion ---- */
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
			padding: 18px 4px;
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
			font-size: clamp(16px, 2.3vw, 19px);
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
		.a-panel-inner { padding: 0 4px 20px; }
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
		.no-results { color: var(--muted); font-weight: 300; padding: 18px 4px; display: none; }
		.no-results.show { display: block; }

		/* ---- Actions ---- */
		.actions { margin-top: 22px; display: flex; flex-wrap: wrap; gap: 10px; }
		.btn {
			display: inline-flex;
			align-items: center;
			gap: 8px;
			font-family: 'Outfit', sans-serif;
			font-size: 13.5px;
			font-weight: 500;
			padding: 9px 16px;
			border-radius: 100px;
			cursor: pointer;
			border: 1px solid var(--line);
			background: var(--card);
			color: var(--accent-deep);
			transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
		}
		.btn:hover { border-color: var(--accent); box-shadow: 0 6px 18px -12px var(--ring); }
		.btn svg { width: 15px; height: 15px; stroke: var(--accent-deep); }
		.btn.copied { background: var(--accent-soft); border-color: var(--accent); }

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
		<h1><?php echo htmlspecialchars($faq->Title); ?></h1>

		<?php if(!empty($dest_names)) { ?>
			<div class="faq-meta">
				<?php foreach($dest_names as $dest_name) { ?>
					<span class="chip chip-dest"><?php echo htmlspecialchars($dest_name); ?></span>
				<?php } ?>
			</div>
		<?php } ?>

		<section class="sheet">
			<?php if(empty($items)) { ?>
				<p class="empty-body">No additional details for this FAQ yet.</p>
			<?php } else { ?>
				<div class="faq-search" id="faqSearch">
					<svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
					<input type="text" id="faqSearchInput" placeholder="Search this FAQ&hellip;" autocomplete="off" aria-label="Search questions and answers">
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

				<div class="a-list" id="faqList">
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
					// question + answer + tag names.
					$search_blob = strtolower($q_text . ' ' . $a_text . ' ' . implode(' ', $item_tags));
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
				<p class="no-results" id="noResults">No questions match your search.</p>
			<?php } ?>
		</section>

		<div class="actions">
			<button type="button" id="copyLink" class="btn" data-url="<?php echo htmlspecialchars(base_url('faq/' . rawurlencode($faq->Slug)), ENT_QUOTES); ?>">
				<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.07 0l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.07 0l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
				<span class="label">Copy link to this FAQ</span>
			</button>
		</div>

		<footer>
			<span>&copy; <?php echo date('Y'); ?> HolidayGoGoGo</span>
			<?php if($updated_ts) { ?>
				<span>Last updated <?php echo date('j M Y', $updated_ts); ?></span>
			<?php } ?>
		</footer>
	</main>

	<script>
		// ---- Accordion + search ----
		(function () {
			var list = document.getElementById('faqList');
			if (!list) return;
			var items = Array.prototype.slice.call(list.querySelectorAll('.a-item'));

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
			if (!input) return;
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

			function runSearch() {
				var q = input.value.trim().toLowerCase();
				wrap.classList.toggle('has-value', q !== '');
				var filtering = q !== '' || activeTags.length > 0;
				var matches = 0;
				items.forEach(function (item) {
					var hit = (q === '' || item.getAttribute('data-search').indexOf(q) !== -1) && matchesTags(item);
					item.classList.toggle('is-hidden', !hit);
					// Auto-expand on a text query so answers are visible; tag-only
					// filtering keeps items collapsed. Collapse when nothing applies.
					if (hit && q !== '') openItem(item); else closeItem(item);
					// Highlight the keyword in visible hits; strip highlights otherwise.
					var fields = item.querySelectorAll('.a-q, .a-a');
					for (var f = 0; f < fields.length; f++) {
						if (hit && q !== '') highlight(fields[f], q); else clearHl(fields[f]);
					}
					if (hit) matches++;
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

			input.addEventListener('input', runSearch);
			input.addEventListener('keydown', function (e) {
				if (e.key === 'Escape') { input.value = ''; runSearch(); }
			});
			clearBtn.addEventListener('click', function () {
				input.value = '';
				runSearch();
				input.focus();
			});
		})();

		(function () {
			var btn = document.getElementById('copyLink');
			if (!btn) return;
			var label = btn.querySelector('.label');
			var original = label.textContent;
			btn.addEventListener('click', function () {
				var url = btn.getAttribute('data-url');
				var done = function () {
					btn.classList.add('copied');
					label.textContent = 'Link copied';
					setTimeout(function () {
						btn.classList.remove('copied');
						label.textContent = original;
					}, 1800);
				};
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(url).then(done).catch(done);
				} else {
					var ta = document.createElement('textarea');
					ta.value = url;
					document.body.appendChild(ta);
					ta.select();
					try { document.execCommand('copy'); } catch (e) {}
					document.body.removeChild(ta);
					done();
				}
			});
		})();
	</script>
</body>
</html>
