<?php
	$is_external = ($type === 'external');
	$audience    = $is_external ? 'For Travellers' : 'For Our Team';
	$heading     = $is_external ? 'Frequently Asked Questions' : 'Internal FAQ';
	$subheading  = $is_external
		? 'Everything you need to know before, during and after your trip.'
		: 'Quick answers to keep the team moving.';
?>
<!DOCTYPE html>
<html lang="en" data-faq-theme="<?php echo $is_external ? 'external' : 'internal'; ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="<?php echo $is_external ? 'index,follow' : 'noindex,nofollow'; ?>">
	<title>HolidayGoGoGo &middot; <?php echo $heading; ?></title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
	<style>
		:root {
			/* Internal = blue, External = green. The two public pages share this
			   template; only these tokens flip via [data-faq-theme]. */
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
		[data-faq-theme="external"] {
			--accent:        #1c9e7a;
			--accent-deep:   #137256;
			--accent-soft:   #e4f4ee;
			--accent-tint:   #f2faf6;
			--ring:          rgba(28, 158, 122, 0.18);
			--bg:            #f5faf8;
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
			padding: clamp(40px, 7vw, 96px) clamp(20px, 5vw, 32px) 80px;
		}

		/* ---- Header ---- */
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
			font-size: clamp(34px, 6vw, 54px);
			line-height: 1.05;
			letter-spacing: -0.02em;
			margin: 22px 0 14px;
			color: var(--ink);
		}
		h1 .accent { color: var(--accent); font-style: italic; }

		.lede {
			font-size: clamp(16px, 2.4vw, 19px);
			color: var(--muted);
			max-width: 56ch;
			font-weight: 300;
		}

		.brand {
			display: flex;
			align-items: center;
			gap: 10px;
			margin-bottom: 38px;
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

		/* ---- Accordion ---- */
		.faqs { margin-top: clamp(34px, 6vw, 52px); display: flex; flex-direction: column; gap: 12px; }

		.faq {
			background: var(--card);
			border: 1px solid var(--line);
			border-radius: 16px;
			overflow: hidden;
			transition: border-color .25s ease, box-shadow .25s ease, transform .25s ease;
			opacity: 0;
			transform: translateY(14px);
			animation: rise .55s cubic-bezier(.22,.61,.36,1) forwards;
		}
		.faq:hover { border-color: var(--ring); box-shadow: 0 10px 30px -18px rgba(16, 32, 43, 0.4); }
		.faq.open {
			border-color: var(--accent);
			box-shadow: 0 18px 44px -24px var(--ring);
		}

		.q {
			display: flex;
			align-items: center;
			gap: 16px;
			width: 100%;
			text-align: left;
			background: none;
			border: 0;
			cursor: pointer;
			padding: 22px 24px;
			font-family: 'Outfit', sans-serif;
			font-size: clamp(16px, 2vw, 18px);
			font-weight: 500;
			color: var(--ink);
		}
		.q .num {
			font-family: 'Fraunces', serif;
			font-size: 14px;
			font-weight: 600;
			color: var(--accent);
			min-width: 28px;
		}
		.q .text { flex: 1; }
		.q .chev {
			width: 30px; height: 30px;
			flex-shrink: 0;
			border-radius: 50%;
			background: var(--accent-soft);
			display: grid; place-items: center;
			transition: transform .35s cubic-bezier(.22,.61,.36,1), background .25s ease;
		}
		.q .chev svg { width: 14px; height: 14px; stroke: var(--accent-deep); }
		.faq.open .q .chev { transform: rotate(180deg); background: var(--accent); }
		.faq.open .q .chev svg { stroke: #fff; }
		.q:focus-visible { outline: 2px solid var(--accent); outline-offset: -3px; border-radius: 16px; }

		.a {
			display: grid;
			grid-template-rows: 0fr;
			transition: grid-template-rows .38s cubic-bezier(.22,.61,.36,1);
		}
		.faq.open .a { grid-template-rows: 1fr; }
		.a-inner { overflow: hidden; }
		.a-body {
			padding: 0 24px 24px 68px;
			color: var(--muted);
			font-size: 15.5px;
			font-weight: 300;
			line-height: 1.7;
		}
		.a-body::before {
			content: "";
			display: block;
			height: 1px;
			background: var(--line);
			margin-bottom: 18px;
		}
		.a-item + .a-item { margin-top: 18px; padding-top: 18px; border-top: 1px solid var(--line); }
		.a-q {
			font-weight: 500;
			color: var(--ink);
			margin-bottom: 6px;
		}
		.a-a { color: var(--muted); }

		/* Tag / destination chips (internal page only) */
		.faq-meta { display: flex; flex-wrap: wrap; gap: 7px; margin-bottom: 18px; }
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
		.chip-tag {
			background: var(--accent-soft);
			color: var(--accent-deep);
		}
		.chip-dest {
			background: var(--card);
			color: var(--muted);
			border: 1px solid var(--line);
		}
		.chip-dest::before {
			content: "";
			width: 6px; height: 6px;
			border-radius: 50%;
			background: var(--accent);
		}

		.empty {
			margin-top: 40px;
			text-align: center;
			padding: 56px 24px;
			background: var(--card);
			border: 1px dashed var(--line);
			border-radius: 18px;
			color: var(--muted);
		}
		.empty .ico { font-size: 30px; }

		footer {
			margin-top: 56px;
			padding-top: 24px;
			border-top: 1px solid var(--line);
			font-size: 13px;
			color: var(--muted);
			display: flex;
			justify-content: space-between;
			flex-wrap: wrap;
			gap: 8px;
		}

		@keyframes rise { to { opacity: 1; transform: none; } }
		@media (prefers-reduced-motion: reduce) {
			.faq { animation: none; opacity: 1; transform: none; }
			.a, .q .chev { transition: none; }
		}
	</style>
</head>
<body>
	<main class="wrap">
		<div class="brand">
			<span class="mark">H</span>
			<span>HolidayGoGoGo</span>
		</div>

		<span class="eyebrow"><?php echo $audience; ?></span>
		<h1><?php echo $is_external ? 'Questions, <span class="accent">answered</span>.' : 'Internal <span class="accent">FAQ</span>.'; ?></h1>
		<p class="lede"><?php echo $subheading; ?></p>

		<?php if(empty($faqs)) { ?>
			<div class="empty">
				<div class="ico">🗒️</div>
				<p style="margin-top:10px;">No FAQs published yet. Please check back soon.</p>
			</div>
		<?php } else { ?>
			<div class="faqs">
				<?php $i = 1; foreach($faqs as $faq) {
					$items = Faq_Model::Decode_Items($faq->Description);
				?>
					<div class="faq" style="animation-delay: <?php echo min($i * 60, 480); ?>ms;">
						<button type="button" class="q" aria-expanded="false">
							<span class="num"><?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?></span>
							<span class="text"><?php echo htmlspecialchars($faq->Title); ?></span>
							<span class="chev" aria-hidden="true">
								<svg viewBox="0 0 24 24" fill="none" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
							</span>
						</button>
						<div class="a">
							<div class="a-inner">
								<div class="a-body">
									<?php if(!$is_external) {
										$tag_names  = ($faq->Tags === null || $faq->Tags === '') ? array() : explode('||', $faq->Tags);
										$dest_names = ($faq->Destinations === null || $faq->Destinations === '') ? array() : explode('||', $faq->Destinations);
										if(!empty($tag_names) || !empty($dest_names)) { ?>
											<div class="faq-meta">
												<?php foreach($dest_names as $dest_name) { ?>
													<span class="chip chip-dest"><?php echo htmlspecialchars($dest_name); ?></span>
												<?php } ?>
												<?php foreach($tag_names as $tag_name) { ?>
													<span class="chip chip-tag"><?php echo htmlspecialchars($tag_name); ?></span>
												<?php } ?>
											</div>
									<?php } } ?>
									<?php if(empty($items)) { ?>
										<em>No additional details.</em>
									<?php } else { foreach($items as $item) { ?>
										<div class="a-item">
											<?php if(trim($item['q']) !== '') { ?>
												<div class="a-q"><?php echo htmlspecialchars($item['q']); ?></div>
											<?php } ?>
											<?php if(trim($item['a']) !== '') { ?>
												<div class="a-a"><?php echo nl2br(htmlspecialchars($item['a'])); ?></div>
											<?php } ?>
										</div>
									<?php } } ?>
								</div>
							</div>
						</div>
					</div>
					<?php $i++; ?>
				<?php } ?>
			</div>
		<?php } ?>

		<footer>
			<span>&copy; <?php echo date('Y'); ?> HolidayGoGoGo</span>
			<span><?php echo count($faqs); ?> <?php echo count($faqs) === 1 ? 'question' : 'questions'; ?></span>
		</footer>
	</main>

	<script>
		(function () {
			var items = document.querySelectorAll('.faq');
			items.forEach(function (item) {
				var btn = item.querySelector('.q');
				btn.addEventListener('click', function () {
					var isOpen = item.classList.contains('open');
					// Accordion behaviour: close the others, toggle this one.
					items.forEach(function (other) {
						other.classList.remove('open');
						other.querySelector('.q').setAttribute('aria-expanded', 'false');
					});
					if (!isOpen) {
						item.classList.add('open');
						btn.setAttribute('aria-expanded', 'true');
					}
				});
			});
		})();
	</script>
</body>
</html>
