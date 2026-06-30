<?php
    $card_level = (int) $this->session->userdata('level');
    $show_tc      = ($card_level == 20 || $card_level == 50);
    $show_tclead  = ($card_level == 25);
    $show_op      = ($card_level == 40 || $card_level == 45); // OP and OP TEAM LEAD share the OP cards
    $show_finance = ($card_level == 30);
    $show_owner   = ($card_level == 10);
    $any_cards    = $show_tc || $show_tclead || $show_op || $show_finance || $show_owner;
?>
<?php if($any_cards) { ?>
<style>
    #booking_summary_cards .summary-card { display:block; color:inherit; text-decoration:none; }
    #booking_summary_cards .summary-card:hover { text-decoration:none; }
    #booking_summary_cards a.summary-card .card { transition: box-shadow 0.15s ease, transform 0.15s ease; cursor:pointer; }
    #booking_summary_cards a.summary-card:hover .card { box-shadow: 0 4px 10px rgba(96,130,182,0.18); transform: translateY(-1px); }
    #booking_summary_cards .card-custom { margin-bottom: 12px; }
    /* Red card — used for the urgent "Travelling Tomorrow – Not Pending Travel"
       OP card so it stands out from the neutral queue cards. Kept clearly red
       (not a faint tint) per the project styling rules. */
    #booking_summary_cards .summary-card-red .card { border:1px solid #E8919199; }
    #booking_summary_cards .summary-card-red .summary-card-header { background-color:#F1AEB5 !important; }
    #booking_summary_cards .summary-card-red .summary-card-header h3 { color:#842029; }
    #booking_summary_cards .summary-card-red .summary-info-icon { color:#9C4A4A; }
    #booking_summary_cards .summary-card-red .summary-info-icon:hover { color:#842029; }
    #booking_summary_cards .summary-card-red .summary-value { color:#C0392B; }
    #booking_summary_cards .summary-card-header { min-height:38px; padding:8px 14px; display:flex; justify-content:space-between; align-items:center; }
    #booking_summary_cards .summary-card-header h3 { margin:0; font-size:13px; color:#3F4254; }
    #booking_summary_cards .summary-info-icon { color:#8B95A7; font-size:14px; cursor:pointer; padding:2px 4px; line-height:1; }
    #booking_summary_cards .summary-info-icon:hover { color:#6082B6; }
    .summary-popover { max-width:320px; font-size:12px; }
    .summary-popover .popover-body { font-size:12px; line-height:1.5; color:#3F4254; }
    .summary-popover .popover-body strong { color:#6082B6; }
    .summary-popover .popover-body ul { padding-left:18px; margin:4px 0; }
    #booking_summary_cards .summary-card-body { padding:10px 14px 12px; }
    #booking_summary_cards .summary-value { font-size:24px; font-weight:700; color:#3F4254; line-height:1.1; }
    #booking_summary_cards .summary-value-sm { font-size:18px; font-weight:700; color:#3F4254; line-height:1.1; }
    #booking_summary_cards .summary-sub { font-size:11px; color:#7E8299; font-weight:500; margin-top:4px; }
    #booking_summary_cards .summary-best { color:#5C6473; font-weight:600; margin-top:6px; padding-top:6px; border-top:1px dashed #EBEDF3; }
    #booking_summary_cards .summary-best .best-name { color:#3F4254; }
    #booking_summary_cards .summary-best .best-name.is-you { color:#6082B6; }
    #booking_summary_cards .summary-best .best-fig { color:#2F6F4F; font-weight:700; background:#E5F3EC; padding:1px 7px; border-radius:4px; }
    /* Agent Score card: score/rank on the left, Top-5 leaderboard on the right. */
    #booking_summary_cards .agent-score-split { display:flex; gap:16px; align-items:flex-start; }
    #booking_summary_cards .agent-score-main { flex:1 1 0; min-width:0; }
    #booking_summary_cards .agent-score-board { flex:1 1 0; min-width:0; border-left:1px solid #EBEDF3; padding-left:16px; }
    #booking_summary_cards .agent-score-board-title { font-size:11px; color:#7E8299; text-transform:uppercase; letter-spacing:0.5px; font-weight:600; margin-bottom:6px; }
    #booking_summary_cards .agent-score-board-list { list-style:none; margin:0; padding:0; }
    #booking_summary_cards .agent-score-board-list li { display:flex; align-items:center; gap:8px; padding:4px 6px; border-bottom:1px solid #F3F6F9; font-size:13px; border-radius:4px; }
    #booking_summary_cards .agent-score-board-list li:last-child { border-bottom:none; }
    #booking_summary_cards .agent-score-board-list li.is-you { background:#EEF3FB; }
    #booking_summary_cards .agent-score-board-list .asb-empty { color:#7E8299; justify-content:center; }
    #booking_summary_cards .agent-score-board-list .asb-rank { width:20px; color:#7E8299; font-weight:700; flex-shrink:0; text-align:center; }
    #booking_summary_cards .agent-score-board-list .asb-name { flex:1; min-width:0; color:#3F4254; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    #booking_summary_cards .agent-score-board-list .asb-name.is-you { color:#6082B6; }
    #booking_summary_cards .agent-score-board-list .asb-val { color:#2F6F4F; font-weight:700; flex-shrink:0; }
    /* Agent Score hero (full-width row-1 card): gradient header, big score badge
       on the left, Top-5 leaderboard on the right. */
    #booking_summary_cards .agent-score-card .summary-card-header { background:linear-gradient(90deg,#5B79AB 0%,#6082B6 55%,#C4A23F 100%) !important; }
    #booking_summary_cards .agent-score-card .summary-card-header h3 { color:#fff; font-size:14px; letter-spacing:0.3px; }
    #booking_summary_cards .agent-score-card .summary-info-icon { color:#EAF0FA; }
    #booking_summary_cards .agent-score-card .summary-info-icon:hover { color:#fff; }
    #booking_summary_cards .agent-score-hero { display:flex; flex-direction:column; align-items:center; text-align:center; padding:4px 0 2px; }
    #booking_summary_cards .agent-score-ring { width:120px; height:120px; border-radius:50%; display:flex; flex-direction:column; align-items:center; justify-content:center; background:linear-gradient(135deg,#6082B6 0%,#7E9BC9 45%,#C4A23F 100%); color:#fff; box-shadow:0 6px 16px rgba(96,130,182,0.35); margin-bottom:10px; }
    #booking_summary_cards .agent-score-ring-val { font-size:40px; font-weight:800; line-height:1; }
    #booking_summary_cards .agent-score-ring-max { font-size:12px; font-weight:600; opacity:0.85; margin-top:2px; }
    #booking_summary_cards .agent-score-rankline { font-size:14px; color:#3F4254; font-weight:600; }
    #booking_summary_cards .agent-score-rankline strong { color:#6082B6; font-size:17px; }
    #booking_summary_cards .agent-score-top { margin-top:6px; padding-top:0; border-top:none; }
    #booking_summary_cards .agent-score-blurb { margin-top:12px; text-align:center; }
    /* Medal colours for the Top-3 leaderboard ranks. */
    #booking_summary_cards .agent-score-board-list li:nth-child(1) .asb-rank { color:#C4A23F; }
    #booking_summary_cards .agent-score-board-list li:nth-child(2) .asb-rank { color:#9AA3AF; }
    #booking_summary_cards .agent-score-board-list li:nth-child(3) .asb-rank { color:#B97A56; }
    /* Owner per-agent matrix: global Day/Week/Month/Year toggle + dense table. */
    #booking_summary_cards .sc-owner-toggle { display:inline-flex; gap:0; margin-left:auto; border:1px solid #B8C7E0; border-radius:5px; overflow:hidden; }
    #booking_summary_cards .sc-owner-tab { border:none; background:#EEF3FB; color:#5C6473; font-size:12px; font-weight:600; padding:4px 14px; cursor:pointer; line-height:1.4; }
    #booking_summary_cards .sc-owner-tab + .sc-owner-tab { border-left:1px solid #B8C7E0; }
    #booking_summary_cards .sc-owner-tab.is-active { background:#6082B6; color:#fff; }
    #booking_summary_cards .sc-owner-tab:not(.is-active):hover { background:#DCE6F5; }
    #booking_summary_cards .sc-owner-matrix th, #booking_summary_cards .sc-owner-matrix td { white-space:nowrap; vertical-align:middle; }
    #booking_summary_cards .sc-owner-matrix tbody tr:first-child td { font-weight:600; }
    #booking_summary_cards .sc-month-filter { display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:14px; padding:10px 14px; background:#EEF3FB; border-radius:6px; }
    #booking_summary_cards .sc-month-filter label { margin:0; font-weight:600; color:#3F4254; font-size:13px; }
    #booking_summary_cards .sc-month-filter input[type=month] { width:auto; max-width:190px; height:auto; padding:6px 10px; font-size:13px; }
    #booking_summary_cards .sc-month-filter-hint { font-size:12px; color:#7E8299; }
    #booking_summary_cards .sc-bars { margin-top:10px; }
    #booking_summary_cards .sc-bar-row { display:flex; align-items:center; gap:8px; margin-bottom:6px; }
    #booking_summary_cards .sc-bar-label { font-size:11px; color:#5C6473; width:48px; flex-shrink:0; font-weight:600; }
    #booking_summary_cards .sc-bar-track { flex:1; height:14px; background:#E6EAF1; border-radius:7px; overflow:hidden; }
    #booking_summary_cards .sc-bar-fill { display:block; height:100%; width:0; border-radius:7px; transition:width .35s ease; }
    #booking_summary_cards .sc-bar-actual { background:#6082B6; }
    #booking_summary_cards .sc-bar-target { background:#C4A23F; }
    #booking_summary_cards .sc-bar-amt { font-size:11px; color:#3F4254; font-weight:600; min-width:70px; text-align:right; flex-shrink:0; }
    #booking_summary_cards .summary-row-3 { display:flex; gap:14px; }
    #booking_summary_cards .summary-row-3 > div, #booking_summary_cards .summary-row-3 > a { flex:1; }
    #booking_summary_cards .summary-row-3 .lbl { font-size:10px; color:#7E8299; text-transform:uppercase; letter-spacing:0.5px; }
    #booking_summary_cards .due-bucket { display:block; padding:8px 10px; border-radius:6px; background:#F7F8FA; }
    #booking_summary_cards a.due-bucket { color:inherit; text-decoration:none; transition:box-shadow .15s, transform .15s; }
    #booking_summary_cards a.due-bucket:hover { text-decoration:none; box-shadow:0 4px 10px rgba(96,130,182,0.18); transform:translateY(-1px); }
    #booking_summary_cards .due-bucket.is-overdue { background:#FAA0A025; }
    #booking_summary_cards .due-bucket.is-today { background:#FFFAA035; }
    #booking_summary_cards .due-bucket .summary-value-sm.amt-overdue { color:#D9342B; }
    #booking_summary_cards .due-bucket .summary-value-sm.amt-today { color:#B8860B; }
    #booking_summary_cards .due-bucket .due-amt { font-size:13px; font-weight:600; color:#3F4254; margin-top:3px; }
    #booking_summary_cards .summary-table { font-size:12px; margin-bottom:0; }
    #booking_summary_cards .summary-table th { font-size:11px; color:#7E8299; text-transform:uppercase; border-top:none; border-bottom:1px solid #EBEDF3; padding:6px 8px; font-weight:600; }
    #booking_summary_cards .summary-table td { padding:6px 8px; border-top:1px solid #F3F6F9; vertical-align:middle; }
    #booking_summary_cards .summary-table a { color:#6082B6; }
    #booking_summary_cards .panel-title { color:#6082B6; font-weight:700; font-size:14px; margin:0; padding:0; }
    #booking_summary_cards .panel-toggle { display:flex; align-items:center; justify-content:space-between; cursor:pointer; padding:10px 14px; background-color:#D7E2F2; border-radius:6px; user-select:none; }
    #booking_summary_cards .panel-toggle .panel-caret { transition: transform 0.2s ease; color:#6082B6; font-size:18px; }
    #booking_summary_cards .panel-toggle.collapsed .panel-caret { transform: rotate(-90deg); }
    #booking_summary_cards .panel-body { padding-top:12px; }
    #booking_summary_cards .panel-toggle .panel-title { flex-shrink:0; }
    #booking_summary_cards .ghl-last-sync { margin-left:auto; margin-right:14px; font-size:12px; color:#3F4254; white-space:nowrap; flex-shrink:0; }
    /* TC summary card order. .row is a flexbox so `order` reorders cards
       visually without moving the source blocks; full-width breaks (orders 11 &
       14) snap the conceptual rows apart. The full-width month filter keeps
       default order 0 and stays on top. Layout:
         row 1  Agent Score (full width)                              -> 1
         row 2  Month Sales / Year Sales / Conversion / Cancellation -> 2-5
         row 3  New Leads / Daily Handle / Avg Reply / Pickup / Out   -> 6-10
         row 4  Follow-up % / BC Created                              -> 12-13
         bottom operational chase cards                               -> 15 */
    #booking_summary_cards .sc-pos-1  { order: 1; }
    #booking_summary_cards .sc-pos-2  { order: 2; }
    #booking_summary_cards .sc-pos-3  { order: 3; }
    #booking_summary_cards .sc-pos-4  { order: 4; }
    #booking_summary_cards .sc-pos-5  { order: 5; }
    #booking_summary_cards .sc-pos-6  { order: 6; }
    #booking_summary_cards .sc-pos-7  { order: 7; }
    #booking_summary_cards .sc-pos-8  { order: 8; }
    #booking_summary_cards .sc-pos-9  { order: 9; }
    #booking_summary_cards .sc-pos-10 { order: 10; }
    #booking_summary_cards .sc-pos-11 { order: 11; }
    #booking_summary_cards .sc-pos-12 { order: 12; }
    #booking_summary_cards .sc-pos-13 { order: 13; }
    #booking_summary_cards .sc-pos-14 { order: 14; }
    #booking_summary_cards .sc-pos-bottom { order: 15; }
    /* Full-width zero-height spacer that forces the following cards onto a new
       flex line, so the 5-up row 3 and the 2-up row 4 never merge. */
    #booking_summary_cards .sc-row-break { flex: 0 0 100%; width: 100%; height: 0; margin: 0; padding: 0; }
    /* ---------- Mobile (< md / 768px) ---------- */
    @media (max-width: 767.98px) {
        /* Wide data tables (e.g. Sales by Agent's 7 columns) scroll sideways
           inside their card instead of squishing or forcing page-wide scroll.
           white-space:nowrap stops columns collapsing so overflow-x kicks in. */
        #booking_summary_cards .summary-card-body { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        #booking_summary_cards .summary-table th,
        #booking_summary_cards .summary-table td { white-space: nowrap; }
        /* "Last synced from GHL" (Owner) drops to its own full-width line so it
           no longer overflows the Summary header bar on narrow screens. */
        #booking_summary_cards .panel-toggle { flex-wrap: wrap; }
        #booking_summary_cards .ghl-last-sync { order: 3; flex-basis: 100%; margin: 6px 0 0; white-space: normal; }
        /* Tighten the 3-up KPI strips so the figures don't crowd on small phones. */
        #booking_summary_cards .summary-row-3 { gap: 8px; }
        #booking_summary_cards .summary-value-sm { font-size: 16px; }
        /* Agent Score: stack score above the Top-5 list on narrow screens. */
        #booking_summary_cards .agent-score-split { flex-direction: column; gap: 10px; }
        #booking_summary_cards .agent-score-board { border-left: none; padding-left: 0; border-top: 1px dashed #EBEDF3; padding-top: 10px; }
    }
</style>
<?php
	// Owner-managed per-card visibility: hide each card the owner has turned OFF
	// for this user, on first paint (no flash). Cards are visible by default.
	$this->load->helper('card_visibility');
	$cv_hidden_css = card_visibility_hide_css(
		card_visibility_hidden_slugs_for($this->session->userdata('admin_id')),
		card_visibility_registry()
	);
	if($cv_hidden_css !== '') { echo '<style>' . $cv_hidden_css . '</style>'; }
?>
<div id="booking_summary_cards" class="mb-4">
    <div class="panel-toggle" data-toggle="collapse" data-target="#booking_summary_cards_body" aria-expanded="true" aria-controls="booking_summary_cards_body">
        <span class="panel-title">Summary</span>
        <?php if($show_owner) { ?>
        <span class="ghl-last-sync">Last synced from GHL: <span id="ghl-last-sync">…</span></span>
        <?php } ?>
        <i class="la la-angle-down panel-caret"></i>
    </div>
    <div id="booking_summary_cards_body" class="collapse show panel-body">
    <div class="row">

        <?php /* ---------- TC (level 20 / 50) ---------- */ ?>
        <?php if($show_tc) { ?>
            <div class="col-md-12">
                <div class="sc-month-filter">
                    <label for="sc-month-picker">Viewing month</label>
                    <input type="month" id="sc-month-picker" class="form-control">
                    <span class="sc-month-filter-hint">Re-scopes every &ldquo;(Month)&rdquo; card below; the Year card follows the selected year.</span>
                </div>
            </div>
            <div class="col-md-3 sc-pos-13">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#F5E6CD;">
                        <h3>BC Created</h3>
                        <i id="pop-bc-month" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> The number of booking confirmations credited to you, this month and this year.<br><br><strong>A booking is counted when:</strong><ul><li>It is a confirmed booking, not a quotation or proforma invoice or draft</li><li>It has not been cancelled</li><li>It was created in the period</li><li>You are the agent who gets credit for the sale</li></ul><strong>Who gets the credit:</strong> for bookings created before 1 Jun 2026 it is the main sales person; from 1 Jun 2026 onward it is the second sales agent on the booking."></i>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-row-3">
                            <div><div class="lbl">This Month</div><a class="summary-value-sm" id="sc-bc-month-link" href="#" style="display:block;color:inherit;text-decoration:none;"><span id="sc-bc-month-count">...</span></a></div>
                            <div><div class="lbl">This Year</div><a class="summary-value-sm" id="sc-bc-year-link" href="#" style="display:block;color:inherit;text-decoration:none;"><span id="sc-bc-year-count">...</span></a></div>
                        </div>
                        <div class="summary-sub">Booking confirmations credited to you (excludes quotation / proforma / cancelled). Click a figure to view that period's list.</div>
                        <div class="summary-sub summary-best" id="sc-bc-month-best">Best (month): —</div>
                        <div class="summary-sub summary-best" id="sc-bc-year-best">Best (year): —</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 sc-pos-2">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#F5E6CD;">
                        <h3>Month Sales vs Target</h3>
                        <i id="pop-sales-month" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> Your total sales this month compared against your monthly target.<br><br><strong>Sales total:</strong> adds up the value of all your Booking Confirmations created this month, <strong>regardless of payment status</strong>. Quotations, proforma invoices and cancelled bookings are not included.<br><br><strong>Credit:</strong> same rule as &ldquo;BC Created&rdquo; (main sales person before 1 Jun 2026, second sales agent after).<br><br><strong>Target:</strong> set for each agent under Admin &rarr; Sales Targets. The percentage is your sales divided by your target."></i>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-value-sm" id="sc-sales-month-value">...</div>
                        <div class="summary-sub">
                            Target: <span id="sc-sales-month-target">—</span> ·
                            <span id="sc-sales-month-percent" style="color:#6082B6;font-weight:600;">—</span>
                        </div>
                        <div class="sc-bars">
                            <div class="sc-bar-row">
                                <span class="sc-bar-label">Actual</span>
                                <span class="sc-bar-track"><span class="sc-bar-fill sc-bar-actual" id="sc-sales-month-bar-actual"></span></span>
                                <span class="sc-bar-amt" id="sc-sales-month-value-2">—</span>
                            </div>
                            <div class="sc-bar-row">
                                <span class="sc-bar-label">Target</span>
                                <span class="sc-bar-track"><span class="sc-bar-fill sc-bar-target" id="sc-sales-month-bar-target"></span></span>
                                <span class="sc-bar-amt" id="sc-sales-month-target-2">—</span>
                            </div>
                        </div>
                        <div class="summary-sub" id="sc-sales-month-empty-target" style="color:#7E8299;display:none;">
                            Ask your team lead to set a monthly target.
                        </div>
                        <div class="summary-sub summary-best" id="sc-sales-month-best">Best: —</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 sc-pos-3">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#F5E6CD;">
                        <h3>Year Sales vs Target</h3>
                        <i id="pop-sales-year" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> Your total sales for the selected year compared against your yearly target.<br><br><strong>Sales total:</strong> adds up the value of all your Booking Confirmations created this year, <strong>regardless of payment status</strong>. Quotations, proforma invoices and cancelled bookings are not included. Same rule as the Month card, just over the full year.<br><br><strong>Target:</strong> set for each agent under Admin &rarr; Yearly Target. The percentage is your sales divided by your target."></i>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-value-sm" id="sc-sales-year-value">...</div>
                        <div class="summary-sub">
                            Target: <span id="sc-sales-year-target">—</span> ·
                            <span id="sc-sales-year-percent" style="color:#6082B6;font-weight:600;">—</span>
                        </div>
                        <div class="sc-bars">
                            <div class="sc-bar-row">
                                <span class="sc-bar-label">Actual</span>
                                <span class="sc-bar-track"><span class="sc-bar-fill sc-bar-actual" id="sc-sales-year-bar-actual"></span></span>
                                <span class="sc-bar-amt" id="sc-sales-year-value-2">—</span>
                            </div>
                            <div class="sc-bar-row">
                                <span class="sc-bar-label">Target</span>
                                <span class="sc-bar-track"><span class="sc-bar-fill sc-bar-target" id="sc-sales-year-bar-target"></span></span>
                                <span class="sc-bar-amt" id="sc-sales-year-target-2">—</span>
                            </div>
                        </div>
                        <div class="summary-sub" id="sc-sales-year-empty-target" style="color:#7E8299;display:none;">
                            Ask your team lead to set a yearly target.
                        </div>
                        <div class="summary-sub summary-best" id="sc-sales-year-best">Best: —</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 sc-pos-5">
                <a class="summary-card" id="sc-cancel-rate-link" href="#">
                    <div class="card card-custom">
                        <div class="card-header border-0 summary-card-header" style="background-color:#D7E2F2;">
                            <h3>Cancellation Rate (Month)</h3>
                            <i id="pop-cancel-rate" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> The share of this month&rsquo;s bookings that ended up cancelled.<br><br><strong>How it&rsquo;s worked out:</strong> cancelled bookings divided by all bookings created this month.<ul><li><strong>Top number:</strong> bookings created this month that were later cancelled</li><li><strong>Bottom number:</strong> all bookings created this month (including the cancelled ones)</li></ul><strong>Example:</strong> 20 bookings, 5 cancelled = 25%.<br><br><strong>Scope:</strong> drafts are not counted, and bookings cancelled as a <strong>duplicate</strong> (reason &ldquo;Booking - Duplicated Booking&rdquo;) are left out of both numbers &mdash; they are data-entry copies, not lost sales. Your own view shows only your bookings; the team view shows everyone&rsquo;s.<br><br><strong>Note:</strong> based on when the booking was <strong>created</strong>, not when it was cancelled. A booking created last month but cancelled this month is not counted here."></i>
                        </div>
                        <div class="card-body summary-card-body">
                            <div class="summary-value" id="sc-cancel-rate-value">...</div>
                            <div class="summary-sub"><span id="sc-cancel-rate-detail">—</span> of your BCs created this month were cancelled. Click to view the cancelled list.</div>
                            <div class="summary-sub summary-best" id="sc-cancel-rate-best">Lowest cancellation rate: —</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-3 sc-pos-4">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#D7E2F2;">
                        <h3>Conversion Rate (YTD)</h3>
                        <i id="pop-conv-rate" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> The share of your leads this year that turned into a booking.<br><br><strong>How it&rsquo;s worked out:</strong> your converted leads divided by your total leads.<br><br><strong>A lead counts as converted when:</strong><ul><li>It is linked to a booking</li></ul>This is the same rule as the Lead Ownership dashboard&rsquo;s &ldquo;Converted&rdquo; column &mdash; it does not matter which sales agent is credited for the booking.<br><br><strong>Scope:</strong> leads assigned to you from 1 Jan to today (from GHL). This card ignores the month filter.<br><br><strong>Best:</strong> the top agent across the whole team this year. Agents with fewer than 3 leads are left out so the comparison stays fair."></i>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-value" id="sc-conv-rate-value">...</div>
                        <div class="summary-sub"><span id="sc-conv-rate-detail">—</span> of your leads year-to-date converted to a BC (counted the same way as the Lead Ownership dashboard&rsquo;s Converted column).</div>
                        <div class="summary-sub summary-best" id="sc-conv-rate-best">Best: —</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md sc-pos-6">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#D7E2F2;">
                        <h3>New Leads</h3>
                        <i id="pop-tc-leads" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> New leads assigned to you (from GHL), counted by the date the lead came in.<ul><li><strong>Today:</strong> leads that came in today</li><li><strong>Week:</strong> Monday to Sunday of this week</li><li><strong>Month:</strong> 1st to last day of this month</li></ul>Your leads are matched to you by your account email. <strong>Note:</strong> if your email isn&rsquo;t linked to a GHL user, this card will show zeros."></i>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-row-3">
                            <div><div class="lbl">Today</div><div class="summary-value-sm" id="sc-tc-leads-day">...</div></div>
                            <div><div class="lbl">Week</div><div class="summary-value-sm" id="sc-tc-leads-week">...</div></div>
                            <div><div class="lbl">Month</div><div class="summary-value-sm" id="sc-tc-leads-month">...</div></div>
                        </div>
                        <div class="summary-sub">New leads assigned to you, by lead creation date.</div>
                        <div class="summary-sub summary-best" id="sc-tc-leads-best">Best: —</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md sc-pos-7">
                <a class="summary-card" id="sc-tc-handle-link" href="#">
                    <div class="card card-custom">
                        <div class="card-header border-0 summary-card-header" style="background-color:#D7E2F2;">
                            <h3>Daily Handle Lead Count</h3>
                            <i id="pop-tc-handle" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> The number of leads you handled &mdash; the &ldquo;Lead Responded&rdquo; column from the Lead Reply Activity dashboard, scoped to you.<ul><li><strong>Today:</strong> leads you replied to today</li><li><strong>Week:</strong> Monday to Sunday of this week</li><li><strong>Month:</strong> 1st to last day of this month</li></ul><strong>How it&rsquo;s counted:</strong> distinct leads you replied to, with your reply landing between 7:00am and 10:00pm. Replying many times to the same lead still counts that lead once (even across different days in the week/month total).<br><br><strong>Note:</strong> if your account isn&rsquo;t linked to a GHL user, this card shows zero."></i>
                        </div>
                        <div class="card-body summary-card-body">
                            <div class="summary-row-3">
                                <div><div class="lbl">Today</div><div class="summary-value-sm" id="sc-tc-handle-day">...</div></div>
                                <div><div class="lbl">Week</div><div class="summary-value-sm" id="sc-tc-handle-week">...</div></div>
                                <div><div class="lbl">Month</div><div class="summary-value-sm" id="sc-tc-handle-month">...</div></div>
                            </div>
                            <div class="summary-sub">Distinct leads you replied to (Lead Responded), counted 7am&ndash;10pm. Click to view today&rsquo;s Lead Reply Hourly report.</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-12 col-md sc-pos-8">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#D7E2F2;">
                        <h3>Avg Reply Time to Inbound</h3>
                        <i id="pop-tc-resp" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> On average, how quickly you reply to an inbound message from your leads (from GHL).<br><br><strong>How it&rsquo;s measured:</strong> every inbound customer message answered by your next reply in the same chat is timed, and all those reply times are averaged &mdash; the same as the Message Log&rsquo;s &lsquo;Avg time taken&rsquo;. Shown in seconds, minutes, or hours.<br><br><strong>Working hours only:</strong> only time during working hours (everyday, 7:00am&ndash;10:00pm Malaysia time) is counted, so replies left overnight don&rsquo;t make the number look worse.<br><br><strong>By period</strong> (based on when the reply was sent): Today, Week (Mon&ndash;Sun), and Month.<br><br><strong>Best:</strong> the fastest agent across the team this month (minimum 3 chats replied to).<br>A dash (&mdash;) means you sent no qualifying replies in that period."></i>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-row-3">
                            <div><div class="lbl">Today</div><div class="summary-value-sm" id="sc-tc-resp-day">...</div></div>
                            <div><div class="lbl">Week</div><div class="summary-value-sm" id="sc-tc-resp-week">...</div></div>
                            <div><div class="lbl">Month</div><div class="summary-value-sm" id="sc-tc-resp-month">...</div></div>
                        </div>
                        <div class="summary-sub">Avg reply time to inbound messages (every reply counted), by when the reply was sent. Matches the Message Log.</div>
                        <div class="summary-sub summary-best" id="sc-tc-resp-best">Fastest: —</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md sc-pos-9">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#D7E2F2;">
                        <h3>Lead Pickup Speed (Month)</h3>
                        <i id="pop-tc-pickup" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> On average, how long your leads waited this month before you first replied.<br><br><strong>How it&rsquo;s measured:</strong> the time from a lead starting a brand-new conversation to your <strong>first reply</strong>, averaged across this month&rsquo;s leads.<br><br>Unlike &ldquo;My Response Time&rdquo;, this counts the real time the customer waited, <strong>including after hours</strong> &mdash; it&rsquo;s how long they actually waited to be picked up.<br><br><strong>Best:</strong> the fastest agent across the team this month (minimum 2 leads).<br>A dash (&mdash;) means none of your leads were picked up this month."></i>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-value" id="sc-tc-pickup-value">...</div>
                        <div class="summary-sub"><span id="sc-tc-pickup-count">—</span> of your leads this month were picked up. Average time to first reply.</div>
                        <div class="summary-sub summary-best" id="sc-tc-pickup-best">Best: —</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md sc-pos-10">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#D7E2F2;">
                        <h3>Outbound Messages</h3>
                        <i id="pop-tc-outbound" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> The number of messages you sent to leads (from GHL), counted by when they were sent.<ul><li><strong>Today:</strong> messages you sent today</li><li><strong>Week:</strong> Monday to Sunday of this week</li><li><strong>Month:</strong> 1st to last day of this month</li></ul>Only your outgoing (agent) messages are counted &mdash; incoming customer messages are not.<br><br><strong>Best:</strong> the top-sending agent across the team this month.<br><strong>Note:</strong> if your account isn&rsquo;t linked to a GHL user, this card shows zeros."></i>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-row-3">
                            <div><div class="lbl">Today</div><div class="summary-value-sm" id="sc-tc-outbound-day">...</div></div>
                            <div><div class="lbl">Week</div><div class="summary-value-sm" id="sc-tc-outbound-week">...</div></div>
                            <div><div class="lbl">Month</div><div class="summary-value-sm" id="sc-tc-outbound-month">...</div></div>
                        </div>
                        <div class="summary-sub">Outbound messages you sent, by send date.</div>
                        <div class="summary-sub summary-best" id="sc-tc-outbound-best">Best: —</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 sc-pos-12">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#D7E2F2;">
                        <h3>Follow-up % (Month)</h3>
                        <i id="pop-tc-followup" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> The share of your leads this month that you followed up on.<br><br><strong>How it&rsquo;s worked out:</strong> followed-up leads divided by all leads you own.<ul><li><strong>Top number:</strong> your leads marked as followed up (follow-up sent or completed)</li><li><strong>Bottom number:</strong> all leads you own this month</li></ul>This uses the same &ldquo;Follow Up&rdquo; definition as the Lead Ownership dashboard.<br><br><strong>Best:</strong> the highest follow-up rate across the team this month (minimum 3 owned leads)."></i>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-value" id="sc-tc-followup-value">...</div>
                        <div class="summary-sub"><span id="sc-tc-followup-detail">—</span> of your leads this month were followed up.</div>
                        <div class="summary-sub summary-best" id="sc-tc-followup-best">Highest follow-up: —</div>
                    </div>
                </div>
            </div>
            <div class="col-md-12 sc-pos-1">
                <div class="card card-custom agent-score-card">
                    <div class="card-header border-0 summary-card-header">
                        <h3>Agent Score (Month)</h3>
                        <i id="pop-agent-score" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> Your overall performance score this month, ranked against everyone else.<br><br><strong>It blends six things, each benchmarked so the month&rsquo;s best performer scores 100 on that measure:</strong><ul><li>Avg reply time to inbound</li><li>1st-response / pickup speed</li><li>Conversion rate</li><li>Sales value</li><li>Follow-up rate</li><li>Qty of leads served</li></ul>For the two speed measures, faster is better; for conversion, sales, follow-up and leads served, higher is better. A measure with no data scores 0.<br><br><strong>Score = 100</strong> would mean being the best on every single measure.<br><br>The <strong>Top 5</strong> list ranks the whole team by this score; <strong>You</strong> are highlighted."></i>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="agent-score-split">
                            <div class="agent-score-main">
                                <div class="agent-score-hero">
                                    <div class="agent-score-ring">
                                        <span class="agent-score-ring-val" id="sc-agent-score-value">...</span>
                                        <span class="agent-score-ring-max">/ 100</span>
                                    </div>
                                    <div class="agent-score-rankline">Rank <strong id="sc-agent-score-rank">—</strong> of <span id="sc-agent-score-total">—</span> agents</div>
                                    <div class="summary-sub summary-best agent-score-top" id="sc-agent-score-best">Top: —</div>
                                </div>
                                <div class="summary-sub agent-score-blurb">Weighted blend of reply speed, pickup speed, conversion, sales, follow-up &amp; leads served, benchmarked against the best performer.</div>
                            </div>
                            <div class="agent-score-board">
                                <div class="agent-score-board-title">Top 5 Agents</div>
                                <ol class="agent-score-board-list" id="sc-agent-score-leaderboard">
                                    <li class="asb-empty">…</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php /* Full-width spacers: snap row 3 (5-up) and row 4 (2-up) apart,
                     and keep the bottom chase cards from riding up next to row 4. */ ?>
            <div class="sc-row-break sc-pos-11"></div>
            <div class="sc-row-break sc-pos-14"></div>
            <?php /* Operational "chase" cards, scoped to this agent's own bookings.
                     Same DOM ids + card keys as the OP versions (TC and OP levels
                     never render together), so the existing JS populates them. */ ?>
            <div class="col-md-3 sc-pos-bottom">
                <a class="summary-card" id="sc-upcoming-not-ready-op-link" href="#">
                    <div class="card card-custom">
                        <div class="card-header border-0 summary-card-header" style="background-color:#FFFAA030;">
                            <h3>Travel in 7 Days – Not Yet Ready</h3>
                            <i id="pop-upcoming-not-ready-op" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> Your bookings that start travel within the next 7 days but aren&rsquo;t ready yet.<br><br><strong>&ldquo;Not yet ready&rdquo;</strong> means the booking is still waiting on one of these steps:<ul><li>Payment</li><li>Booking operations</li><li>Guest list submission</li><li>Travel voucher</li></ul>A booking becomes &ldquo;ready&rdquo; once it reaches the <strong>Pending Travel</strong> stage.<br><br><strong>Counted when:</strong><ul><li>Travel starts between tomorrow and 7 days from today</li><li>It is still stuck at one of the steps above</li><li>It is not cancelled</li><li>You are the credited sales agent</li></ul><strong>Why it matters:</strong> your guests travel within a week &mdash; act now."></i>
                        </div>
                        <div class="card-body summary-card-body">
                            <div class="summary-value" id="sc-upcoming-not-ready-op-count">...</div>
                            <div class="summary-sub">Your BCs starting travel within 7 days still upstream (Payment / Booking Op / Guest List / Travel Voucher) and not yet flagged "Pending Travel". Click to chase your readiness.</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-3 sc-pos-bottom">
                <a class="summary-card" id="sc-upcoming-not-ready-op-14-link" href="#">
                    <div class="card card-custom">
                        <div class="card-header border-0 summary-card-header" style="background-color:#FFFAA030;">
                            <h3>Travel in 14 Days – Not Yet Ready</h3>
                            <i id="pop-upcoming-not-ready-op-14" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> Your bookings that start travel within the next 14 days but aren&rsquo;t ready yet.<br><br><strong>&ldquo;Not yet ready&rdquo;</strong> means the booking is still waiting on one of these steps:<ul><li>Payment</li><li>Booking operations</li><li>Guest list submission</li><li>Travel voucher</li></ul>A booking becomes &ldquo;ready&rdquo; once it reaches the <strong>Pending Travel</strong> stage.<br><br><strong>Counted when:</strong><ul><li>Travel starts between tomorrow and 14 days from today</li><li>It is still stuck at one of the steps above</li><li>It is not cancelled</li><li>You are the credited sales agent</li></ul>This window also includes the bookings shown in &ldquo;Travel in 7 Days&rdquo;.<br><br><strong>Why it matters:</strong> a two-week heads-up to get everything ready."></i>
                        </div>
                        <div class="card-body summary-card-body">
                            <div class="summary-value" id="sc-upcoming-not-ready-op-14-count">...</div>
                            <div class="summary-sub">Your BCs starting travel within 14 days still upstream (Payment / Booking Op / Guest List / Travel Voucher) and not yet flagged "Pending Travel". Click to chase your readiness.</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-12 sc-pos-bottom">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#B7E4C730;">
                        <h3>Payment From Customer Due Soon</h3>
                        <i id="pop-customer-payment-due-soon" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> Your confirmed bookings that still owe a customer payment, with a deadline coming up soon.<br><br><strong>Counted when:</strong><ul><li>The booking still has a scheduled payment to collect</li><li>There is still a balance owing (booking amount minus approved customer payments)</li><li>The next deadline falls between 1 March this year and tomorrow</li><li>It is not cancelled</li><li>You are the sales agent on the booking</li></ul><strong>Next deadline:</strong> the deposit deadline when nothing is paid yet, otherwise the full-payment deadline once a deposit is in.<br><br><strong>Grouped by deadline:</strong> Overdue (1 March up to before today), Today, and Tomorrow &mdash; each showing the number of bookings and the amount still owing.<br><br><strong>Table:</strong> the 5 most urgent bookings, earliest deadline first.<br><strong>Left out:</strong> fully paid, cancelled, and drafts/quotations."></i>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-row-3 mb-3">
                            <a href="#" class="due-bucket is-overdue" id="sc-customer-payment-due-soon-overdue-link" title="View your bookings with customer payments overdue (since 1 March)">
                                <div class="lbl">Overdue</div>
                                <div class="summary-value-sm amt-overdue" id="sc-customer-payment-due-soon-overdue-count">...</div>
                                <div class="due-amt" id="sc-customer-payment-due-soon-overdue-total">...</div>
                            </a>
                            <a href="#" class="due-bucket is-today" id="sc-customer-payment-due-soon-today-link" title="View your bookings with customer payments due today">
                                <div class="lbl">Today</div>
                                <div class="summary-value-sm amt-today" id="sc-customer-payment-due-soon-today-count">...</div>
                                <div class="due-amt" id="sc-customer-payment-due-soon-today-total">...</div>
                            </a>
                            <a href="#" class="due-bucket" id="sc-customer-payment-due-soon-tomorrow-link" title="View your bookings with customer payments due tomorrow">
                                <div class="lbl">Tomorrow</div>
                                <div class="summary-value-sm" id="sc-customer-payment-due-soon-tomorrow-count">...</div>
                                <div class="due-amt" id="sc-customer-payment-due-soon-tomorrow-total">...</div>
                            </a>
                        </div>
                        <div class="summary-sub mb-2">Your booking confirmations that still owe a scheduled customer payment with a deadline from 1 March up to tomorrow, bucketed by urgency. Each bucket shows the BC count and outstanding amount. Clear overdue and today first; the table lists the most urgent BCs, earliest deadline first.</div>
                        <table class="table table-sm summary-table">
                            <thead><tr><th>Booking</th><th>Customer</th><th class="text-right">Outstanding</th><th>Deadline</th></tr></thead>
                            <tbody id="sc-customer-payment-due-soon-body"><tr><td colspan="4" class="text-center text-muted">Loading…</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php } ?>

        <?php /* ---------- TC LEAD / Owner ---------- */ ?>
        <?php /* ---------- TC LEAD (level 25) ---------- */ ?>
        <?php /* Owner (level 10) no longer shares this block — it renders the
                 per-agent performance matrix below instead. */ ?>
        <?php if($show_tclead) { ?>
            <?php if($show_tclead) { ?>
            <div class="col-md-4">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#A7C7E730;">
                        <h3>Leads</h3>
                        <i id="pop-leads-dwm" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> New leads (from GHL), counted by the date they came in.<ul><li><strong>Today:</strong> leads that came in today</li><li><strong>Week:</strong> Monday to Sunday of this week</li><li><strong>Month:</strong> 1st to last day of this month</li></ul>Each conversation counts as one lead &mdash; the same customer messaging again doesn&rsquo;t count twice."></i>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-row-3">
                            <div><div class="lbl">Today</div><div class="summary-value-sm" id="sc-leads-day">...</div></div>
                            <div><div class="lbl">Week</div><div class="summary-value-sm" id="sc-leads-week">...</div></div>
                            <div><div class="lbl">Month</div><div class="summary-value-sm" id="sc-leads-month">...</div></div>
                        </div>
                        <div class="summary-sub">New leads synced from GHL by day, week, and month-to-date.</div>
                    </div>
                </div>
            </div>
            <?php } ?>
            <div class="col-md-4">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#FFFAA030;">
                        <h3>Active Leads</h3>
                        <i id="pop-active-leads" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> Leads that have not yet turned into a booking, counted by the date they came in.<ul><li><strong>Today:</strong> still-open leads from today</li><li><strong>Week:</strong> still-open leads from Monday to Sunday</li><li><strong>Month:</strong> still-open leads from the 1st to the end of the month</li></ul>Compare with the &ldquo;Leads&rdquo; total to see open versus converted at a glance."></i>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-row-3">
                            <div><div class="lbl">Today</div><div class="summary-value-sm" id="sc-active-leads-day">...</div></div>
                            <div><div class="lbl">Week</div><div class="summary-value-sm" id="sc-active-leads-week">...</div></div>
                            <div><div class="lbl">Month</div><div class="summary-value-sm" id="sc-active-leads-month">...</div></div>
                        </div>
                        <div class="summary-sub">Leads still in GHL inboxes (not yet converted to a BC), windowed by lead start date.</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#F0FFFF;">
                        <h3>Conversion &amp; Response (<?php echo $show_owner ? 'Quarter' : 'Month'; ?>)</h3>
                        <i id="pop-leads-conv" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> Three figures across all of this period&rsquo;s leads (every agent).<ul><li><strong>Conversion %:</strong> the share of leads that turned into a booking. A lead is converted when it&rsquo;s linked to a booking and the agent is credited for that sale (main sales person before 1 Jun 2026, second sales agent after).</li><li><strong>Response %:</strong> the share of leads that got at least one reply.</li><li><strong>Avg Time:</strong> the average time to reply, across the first 5 replies on each lead (shown in seconds, minutes, or hours).</li></ul>"></i>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-row-3">
                            <div><div class="lbl">Conversion</div><div class="summary-value-sm" id="sc-leads-conv">...</div></div>
                            <div><div class="lbl">Response</div><div class="summary-value-sm" id="sc-leads-resp">...</div></div>
                            <div><div class="lbl">Avg Time</div><div class="summary-value-sm" id="sc-leads-time">...</div></div>
                        </div>
                        <div class="summary-sub">Lead-to-booking conversion %, response %, and average first-response time across <?php echo $show_owner ? "this quarter's" : "this month's"; ?> leads.</div>
                    </div>
                </div>
            </div>
            <?php if(!$show_owner) { ?>
            <div class="col-md-6">
                <a class="summary-card" id="sc-bc-month-tl-link" href="#">
                    <div class="card card-custom">
                        <div class="card-header border-0 summary-card-header" style="background-color:#A7C7E730;">
                            <h3>BC Created</h3>
                            <i id="pop-bc-week-month-tl" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> The total number of confirmed bookings across all sales agents.<br><br><strong>Counted when:</strong><ul><li>It is a confirmed booking (not a quotation or draft)</li><li>It is not cancelled</li></ul><strong>Periods</strong> (by the date the booking was created):<ul><li><strong>Week:</strong> Monday to Sunday of this week</li><li><strong>Month:</strong> 1st to last day of this month</li></ul>"></i>
                        </div>
                        <div class="card-body summary-card-body">
                            <div class="summary-row-3">
                                <div><div class="lbl">Week</div><div class="summary-value-sm" id="sc-bc-week-tl">...</div></div>
                                <div><div class="lbl">Month</div><div class="summary-value-sm" id="sc-bc-month-tl">...</div></div>
                            </div>
                            <div class="summary-sub">Team-wide booking confirmations created this week and month. Click to view this month's BCs.</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-6">
                <a class="summary-card" id="sc-cancel-rate-tl-link" href="#">
                    <div class="card card-custom">
                        <div class="card-header border-0 summary-card-header" style="background-color:#FFFAA030;">
                            <h3>Cancellation Rate (Month)</h3>
                            <i id="pop-cancel-rate-tl" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> The share of this month&rsquo;s bookings that ended up cancelled.<br><br><strong>How it&rsquo;s worked out:</strong> cancelled bookings divided by all bookings created this month.<ul><li><strong>Top number:</strong> bookings created this month that were later cancelled</li><li><strong>Bottom number:</strong> all bookings created this month (including the cancelled ones)</li></ul><strong>Example:</strong> 20 bookings, 5 cancelled = 25%.<br><br><strong>Scope:</strong> drafts are not counted, and bookings cancelled as a <strong>duplicate</strong> (reason &ldquo;Booking - Duplicated Booking&rdquo;) are left out of both numbers &mdash; they are data-entry copies, not lost sales. Your own view shows only your bookings; the team view shows everyone&rsquo;s.<br><br><strong>Note:</strong> based on when the booking was <strong>created</strong>, not when it was cancelled. A booking created last month but cancelled this month is not counted here."></i>
                        </div>
                        <div class="card-body summary-card-body">
                            <div class="summary-value" id="sc-cancel-rate-tl-value">...</div>
                            <div class="summary-sub"><span id="sc-cancel-rate-tl-detail">—</span> of team BCs created this month were cancelled. Click to view the cancelled list.</div>
                        </div>
                    </div>
                </a>
            </div>
            <?php } ?>
            <div class="col-md-6">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#A7C7E730;">
                        <h3>Top Agents &ndash; Conversion (<?php echo $show_owner ? 'Quarter' : 'Month'; ?>)</h3>
                        <i id="pop-agent-conversion" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> For each agent this period:<ul><li><strong>Leads:</strong> total leads assigned to them</li><li><strong>Converted:</strong> leads assigned to them that became a booking (same rule as the Lead Ownership dashboard&rsquo;s Converted column)</li><li><strong>Rate:</strong> converted divided by leads</li></ul><strong>Order:</strong> most leads first, then by name. Top 10 agents.<br><br>Leads with no agent assigned are not shown."></i>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-sub mb-2">Top 10 agents <?php echo $show_owner ? 'this quarter' : 'this month'; ?> ranked by lead volume, with their conversion rate to booking.</div>
                        <table class="table table-sm summary-table">
                            <thead><tr><th>Agent</th><th class="text-right">Leads</th><th class="text-right">Converted</th><th class="text-right">Rate</th></tr></thead>
                            <tbody id="sc-agent-conversion-body"><tr><td colspan="4" class="text-center text-muted">Loading…</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#A7C7E730;">
                        <h3>Top Destinations &ndash; Closed Sales (Month)</h3>
                        <i id="pop-destination-closed-sales" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="Loading…"></i>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-sub mb-2">Top 5 destinations this month ranked by revenue from fully-paid BCs (sum of approved customer payments &ge; NetTotal).</div>
                        <table class="table table-sm summary-table">
                            <thead><tr><th>Destination</th><th class="text-right">BC</th><th class="text-right">Sales</th></tr></thead>
                            <tbody id="sc-destination-closed-sales-body"><tr><td colspan="3" class="text-center text-muted">Loading…</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-12">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#A7C7E730;">
                        <h3>Active Leads by Tag</h3>
                        <i id="pop-active-leads-by-tag" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="Loading…"></i>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-sub mb-2">Unconverted leads currently in agents&rsquo; GHL inboxes, bucketed by GHL tag. Top 10 per dimension. The three columns are disjoint views over the same lead set.</div>
                        <table class="table table-sm summary-table">
                            <thead><tr><th>Destination</th><th>Language</th><th>Race</th></tr></thead>
                            <tbody id="sc-active-leads-by-tag-body"><tr><td colspan="3" class="text-center text-muted">Loading…</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#C4B45420;">
                        <h3>Self Gen vs Company (Month)</h3>
                        <i id="pop-lead-source-split" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="Loading…"></i>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-row-3">
                            <div>
                                <div class="lbl">Self Gen</div>
                                <div class="summary-value-sm" id="sc-source-selfgen-count">...</div>
                                <div class="summary-sub" id="sc-source-selfgen-total">&mdash;</div>
                            </div>
                            <div>
                                <div class="lbl">Company</div>
                                <div class="summary-value-sm" id="sc-source-company-count">...</div>
                                <div class="summary-sub" id="sc-source-company-total">&mdash;</div>
                            </div>
                        </div>
                        <div class="summary-sub">BCs this month split by whether the booking source is &ldquo;SELF GEN&rdquo; (agent&rsquo;s own lead) or any other source.</div>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#C4B45420;">
                        <h3>Sales by Agent &ndash; Self Gen vs Company (Month)</h3>
                        <i id="pop-agent-source-split" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="Loading…"></i>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-sub mb-2">Per-agent BC split for this month, grouped by team lead.</div>
                        <table class="table table-sm summary-table">
                            <thead>
                                <tr>
                                    <th>Team</th>
                                    <th>Agent</th>
                                    <th class="text-right">Self Gen</th>
                                    <th class="text-right">Self Gen RM</th>
                                    <th class="text-right">Company</th>
                                    <th class="text-right">Company RM</th>
                                    <th class="text-right">% Self Gen</th>
                                </tr>
                            </thead>
                            <tbody id="sc-agent-source-split-body"><tr><td colspan="7" class="text-center text-muted">Loading&hellip;</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php } ?>

        <?php /* ---------- OWNER (level 10) per-agent performance matrix ---------- */ ?>
        <?php if($show_owner) { ?>
            <div class="col-md-12">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#A7C7E730; display:flex; align-items:center;">
                        <h3 style="margin:0;">Agent Performance &mdash; <span id="sc-owner-period-label">This month</span></h3>
                        <i id="pop-owner-matrix" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="What each column means" data-content="<strong>Each row is one sales agent</strong>, for the selected period (use the Day / Week / Month / Year toggle).<ul><li><strong>Reply Time:</strong> average time to reply to an inbound message.</li><li><strong>1st Reply:</strong> average time to send the first reply to a new lead.</li><li><strong>New Leads:</strong> leads assigned to them in the period.</li><li><strong>Served:</strong> leads they owned (assigned or replied to).</li><li><strong>Conv % (credited):</strong> their leads that became a booking where they hold the credited sales slot, over their leads.</li><li><strong>Conv % (all):</strong> their leads that became a booking, whoever is credited, over their leads.</li><li><strong>Outbound:</strong> outbound messages they sent.</li><li><strong>Sales:</strong> value of booking confirmations credited to them (no payment gate).</li><li><strong>Follow-up %:</strong> owned leads that received a follow-up.</li><li><strong>Cancel %:</strong> credited booking confirmations later cancelled (duplicate cancellations excluded).</li><li><strong>Score:</strong> weighted 0&ndash;100 composite of reply time, 1st reply, conversion, sales, follow-up and leads served; the period&rsquo;s best on each metric scores 100.</li></ul>Speed and score need a minimum sample to rank fairly. &ldquo;&mdash;&rdquo; means no data for that agent."></i>
                        <span class="sc-owner-toggle" role="group" aria-label="Performance period">
                            <button type="button" class="sc-owner-tab" data-owner-period="day">Day</button>
                            <button type="button" class="sc-owner-tab" data-owner-period="week">Week</button>
                            <button type="button" class="sc-owner-tab is-active" data-owner-period="month">Month</button>
                            <button type="button" class="sc-owner-tab" data-owner-period="year">Year</button>
                        </span>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-sub mb-2">One row per sales agent across all performance metrics for the selected period, sorted by Agent Score. The Day / Week / Month / Year toggle re-scopes every column at once. Some columns are only reported for certain periods &mdash; a &ldquo;&mdash;&rdquo; means that column doesn&rsquo;t apply to the selected period (e.g. Conversion &amp; Cancellation show only on <strong>Year</strong>; Follow-up &amp; Agent Score only on <strong>Month</strong>; Reply Time / New Leads / Served / Outbound on Day / Week / Month; Sales on Month / Year; 1st Reply on all periods).</div>
                        <div class="table-responsive">
                            <table class="table table-sm summary-table sc-owner-matrix">
                                <thead>
                                    <tr>
                                        <th>Agent</th>
                                        <th class="text-right">Reply Time</th>
                                        <th class="text-right">1st Reply</th>
                                        <th class="text-right">New Leads</th>
                                        <th class="text-right">Served</th>
                                        <th class="text-right">Conv % (credited)</th>
                                        <th class="text-right">Conv % (all)</th>
                                        <th class="text-right">Outbound</th>
                                        <th class="text-right">Sales</th>
                                        <th class="text-right">Follow-up %</th>
                                        <th class="text-right">Cancel %</th>
                                        <th class="text-right">Score</th>
                                    </tr>
                                </thead>
                                <tbody id="sc-owner-matrix-body"><tr><td colspan="12" class="text-center text-muted">Loading&hellip;</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php } ?>

        <?php /* ---------- OP ---------- */ ?>
        <?php if($show_op) { ?>
            <?php /* Row 1: live BC backlog + tomorrow's departures (urgent red first) */ ?>
            <div class="col-md-3">
                <a class="summary-card" id="sc-pending-bc-op-link" href="#">
                    <div class="card card-custom">
                        <div class="card-header border-0 summary-card-header" style="background-color:#A7C7E730;">
                            <h3>Pending BC</h3>
                            <i id="pop-pending-bc-op" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> All bookings sitting at the <strong>Pending BC</strong> stage, waiting to be confirmed.<br><br><strong>Counted when:</strong><ul><li>The booking is at the &ldquo;Pending BC&rdquo; stage</li><li>It is not cancelled</li></ul>A team-wide live list with no date limit. Click to view and move them along."></i>
                        </div>
                        <div class="card-body summary-card-body">
                            <div class="summary-value" id="sc-pending-bc-op-count">...</div>
                            <div class="summary-sub">All bookings sitting at &ldquo;Pending BC&rdquo;, waiting to be confirmed. Click to view the list.</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a class="summary-card" id="sc-pending-bc-confirmation-op-link" href="#">
                    <div class="card card-custom">
                        <div class="card-header border-0 summary-card-header" style="background-color:#A7C7E730;">
                            <h3>Pending BC Confirmation</h3>
                            <i id="pop-pending-bc-confirmation-op" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> All bookings sitting at the <strong>Pending BC Confirmation</strong> stage, waiting to be approved.<br><br><strong>Counted when:</strong><ul><li>The booking is at the &ldquo;Pending BC Confirmation&rdquo; stage</li><li>It is not cancelled</li></ul>A team-wide live list with no date limit. Click to view and approve the booking confirmation."></i>
                        </div>
                        <div class="card-body summary-card-body">
                            <div class="summary-value" id="sc-pending-bc-confirmation-op-count">...</div>
                            <div class="summary-sub">All bookings sitting at &ldquo;Pending BC Confirmation&rdquo;, waiting to be approved. Click to view the list.</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a class="summary-card summary-card-red" id="sc-travel-tomorrow-not-ready-op-link" href="#">
                    <div class="card card-custom">
                        <div class="card-header border-0 summary-card-header" style="background-color:#F1AEB5;">
                            <h3>Travelling Tomorrow &ndash; Not Pending Travel</h3>
                            <i id="pop-travel-tomorrow-not-ready-op" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> Bookings whose travel starts tomorrow but that haven&rsquo;t reached the &ldquo;Pending Travel&rdquo; stage yet.<br><br><strong>Counted when:</strong><ul><li>Travel starts tomorrow</li><li>The booking is <strong>not</strong> yet at &ldquo;Pending Travel&rdquo;</li><li>It is a confirmed booking, not cancelled or draft</li></ul><strong>Why it matters:</strong> guests travel tomorrow but the booking isn&rsquo;t ready &mdash; chase these first. Click to view them."></i>
                        </div>
                        <div class="card-body summary-card-body">
                            <div class="summary-value" id="sc-travel-tomorrow-not-ready-op-count">...</div>
                            <div class="summary-sub">BCs travelling tomorrow that are not yet flagged &ldquo;Pending Travel&rdquo;. Urgent &mdash; click to chase readiness.</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a class="summary-card" id="sc-travel-tomorrow-op-link" href="#">
                    <div class="card card-custom">
                        <div class="card-header border-0 summary-card-header" style="background-color:#A0D8EF30;">
                            <h3>Travelling Tomorrow</h3>
                            <i id="pop-travel-tomorrow-op" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> All confirmed bookings whose travel starts tomorrow, whatever stage they&rsquo;re at.<br><br><strong>Counted when:</strong><ul><li>Travel starts tomorrow</li><li>It is a confirmed booking, not cancelled or draft</li></ul>Based on the departure date (trips that merely pass through tomorrow are not included). Click to view them."></i>
                        </div>
                        <div class="card-body summary-card-body">
                            <div class="summary-value" id="sc-travel-tomorrow-op-count">...</div>
                            <div class="summary-sub">All BCs whose travel starts tomorrow, regardless of status. Click to view the list.</div>
                        </div>
                    </div>
                </a>
            </div>
            <?php /* Row 2: upcoming readiness + after-travel queues */ ?>
            <div class="col-md-3">
                <a class="summary-card" id="sc-upcoming-not-ready-op-link" href="#">
                    <div class="card card-custom">
                        <div class="card-header border-0 summary-card-header" style="background-color:#FFFAA030;">
                            <h3>Travel in 7 Days – Not Yet Ready</h3>
                            <i id="pop-upcoming-not-ready-op" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> Bookings that start travel within the next 7 days but aren&rsquo;t ready yet.<br><br><strong>&ldquo;Not yet ready&rdquo;</strong> means the booking is still waiting on one of these steps:<ul><li>Payment</li><li>Booking operations</li><li>Guest list submission</li><li>Travel voucher</li></ul>A booking becomes &ldquo;ready&rdquo; once it reaches the <strong>Pending Travel</strong> stage.<br><br><strong>Counted when:</strong><ul><li>Travel starts between tomorrow and 7 days from today</li><li>It is still stuck at one of the steps above</li><li>It is not cancelled</li></ul>Your own view shows your bookings; the OP/Owner view shows the whole team.<br><br><strong>Why it matters:</strong> guests travel within a week &mdash; act now."></i>
                        </div>
                        <div class="card-body summary-card-body">
                            <div class="summary-value" id="sc-upcoming-not-ready-op-count">...</div>
                            <div class="summary-sub">All BCs starting travel within 7 days still upstream (Payment / Booking Op / Guest List / Travel Voucher) and not yet flagged "Pending Travel". Click to chase team-wide readiness.</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a class="summary-card" id="sc-upcoming-not-ready-op-14-link" href="#">
                    <div class="card card-custom">
                        <div class="card-header border-0 summary-card-header" style="background-color:#FFFAA030;">
                            <h3>Travel in 14 Days – Not Yet Ready</h3>
                            <i id="pop-upcoming-not-ready-op-14" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> Bookings that start travel within the next 14 days but aren&rsquo;t ready yet.<br><br><strong>&ldquo;Not yet ready&rdquo;</strong> means the booking is still waiting on one of these steps:<ul><li>Payment</li><li>Booking operations</li><li>Guest list submission</li><li>Travel voucher</li></ul>A booking becomes &ldquo;ready&rdquo; once it reaches the <strong>Pending Travel</strong> stage.<br><br><strong>Counted when:</strong><ul><li>Travel starts between tomorrow and 14 days from today</li><li>It is still stuck at one of the steps above</li><li>It is not cancelled</li></ul>This window also includes the bookings shown in &ldquo;Travel in 7 Days&rdquo;.<br><br><strong>Why it matters:</strong> a two-week heads-up to get everything ready."></i>
                        </div>
                        <div class="card-body summary-card-body">
                            <div class="summary-value" id="sc-upcoming-not-ready-op-14-count">...</div>
                            <div class="summary-sub">All BCs starting travel within 14 days still upstream (Payment / Booking Op / Guest List / Travel Voucher) and not yet flagged "Pending Travel". Click to chase team-wide readiness.</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a class="summary-card" id="sc-pending-review-op-link" href="#">
                    <div class="card card-custom">
                        <div class="card-header border-0 summary-card-header" style="background-color:#F0FFFF;">
                            <h3>Travel Completed - Pending Review</h3>
                            <i id="pop-pending-review-op" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> Bookings where travel has finished but the after-sales review is still outstanding.<br><br><strong>Counted when:</strong><ul><li>Travel has been completed</li><li>The after-sales review is still pending</li><li>It is a confirmed booking, not cancelled</li></ul>A team-wide live list. Click to follow up and close the loop."></i>
                        </div>
                        <div class="card-body summary-card-body">
                            <div class="summary-value" id="sc-pending-review-op-count">...</div>
                            <div class="summary-sub">All BCs whose travel has ended and after-sales review is still pending. Click to follow up.</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a class="summary-card" id="sc-gl-submitted-link" href="#">
                    <div class="card card-custom">
                        <div class="card-header border-0 summary-card-header" style="background-color:#F0FFFF;">
                            <h3>Guest List Submitted</h3>
                            <i id="pop-gl-submitted" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> Bookings where the customer has submitted their guest list but OP hasn&rsquo;t locked it yet.<br><br><strong>Counted when:</strong><ul><li>The customer has submitted their guest list</li><li>OP hasn&rsquo;t locked it yet</li><li>It is a confirmed booking, not cancelled or draft</li></ul>A live work list with no date limit.<br><br><strong>What to do:</strong> check the list is complete, then lock it to finalise and stop further customer edits."></i>
                        </div>
                        <div class="card-body summary-card-body">
                            <div class="summary-value" id="sc-gl-submitted-count">...</div>
                            <div class="summary-sub">BCs where the guest list has been submitted but not yet locked. Click to review and lock.</div>
                        </div>
                    </div>
                </a>
            </div>
            <?php /* Row 3: checklist work queues */ ?>
            <div class="col-md-3">
                <a class="summary-card" id="sc-insurance-pending-link" href="#">
                    <div class="card card-custom">
                        <div class="card-header border-0 summary-card-header" style="background-color:#FAA0A030;">
                            <h3>Pending Insurance Checklist</h3>
                            <i id="pop-insurance-pending" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> Bookings with an insurance checklist that hasn&rsquo;t been ticked off yet on at least one product line.<br><br><strong>Counted when, for an active product line:</strong><ul><li>The product has an insurance checklist</li><li>That checklist hasn&rsquo;t been completed yet</li><li>The line isn&rsquo;t excluded from checklist pay-outs (same rule the checklist screen uses)</li><li>It is a confirmed booking, not cancelled or draft</li><li>Travel is from 1 March this year onwards</li><li>Bookings already completed are left out</li></ul>A live list (travel from 1 March onwards). Click to filter the list to these bookings."></i>
                        </div>
                        <div class="card-body summary-card-body">
                            <div class="summary-value" id="sc-insurance-pending-count">...</div>
                            <div class="summary-sub">BCs whose insurance checklist is not yet ticked on at least one active line. Click to review and complete.</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a class="summary-card" id="sc-ferry-pending-link" href="#">
                    <div class="card card-custom">
                        <div class="card-header border-0 summary-card-header" style="background-color:#A0D8EF30;">
                            <h3>Pending Ferry Transfer (This &amp; Next Month)</h3>
                            <i id="pop-ferry-pending" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> Bookings with a &ldquo;Book Ferry Transfer&rdquo; checklist that hasn&rsquo;t been ticked off yet on at least one product line.<br><br><strong>Counted when, for an active product line:</strong><ul><li>The product has a &ldquo;Book Ferry Transfer&rdquo; checklist</li><li>That checklist hasn&rsquo;t been completed yet</li><li>The line isn&rsquo;t excluded from checklist pay-outs (same rule the checklist screen uses)</li><li>It is a confirmed booking, not cancelled or draft</li></ul><strong>Travel window:</strong> trips that fall in this month or next month only. Click to filter the list to these bookings."></i>
                        </div>
                        <div class="card-body summary-card-body">
                            <div class="summary-value" id="sc-ferry-pending-count">...</div>
                            <div class="summary-sub">BCs travelling this month or next whose &ldquo;Book Ferry Transfer&rdquo; checklist is not yet ticked on at least one active line. Click to review and complete.</div>
                        </div>
                    </div>
                </a>
            </div>
            <?php /* TBC — empty slots reserved so Row 4 starts on its own line */ ?>
            <div class="col-md-3"></div>
            <div class="col-md-3"></div>
            <?php /* Row 4: throughput / conversion metrics */ ?>
            <?php if(!$show_tclead) { ?>
            <div class="col-md-3">
                <a class="summary-card" id="sc-bc-month-op-link" href="#">
                    <div class="card card-custom">
                        <div class="card-header border-0 summary-card-header" style="background-color:#A7C7E730;">
                            <h3>BC Created</h3>
                            <i id="pop-bc-week-month-op" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> The total number of confirmed bookings across all sales agents.<br><br><strong>Counted when:</strong><ul><li>It is a confirmed booking (not a quotation or draft)</li><li>It is not cancelled</li></ul><strong>Periods</strong> (by the date the booking was created):<ul><li><strong>Week:</strong> Monday to Sunday of this week</li><li><strong>Month:</strong> 1st to last day of this month</li></ul>"></i>
                        </div>
                        <div class="card-body summary-card-body">
                            <div class="summary-row-3">
                                <div><div class="lbl">Week</div><div class="summary-value-sm" id="sc-bc-week-op">...</div></div>
                                <div><div class="lbl">Month</div><div class="summary-value-sm" id="sc-bc-month-op">...</div></div>
                            </div>
                            <div class="summary-sub">All booking confirmations created this week and month. Click to view this month's BCs.</div>
                        </div>
                    </div>
                </a>
            </div>
            <?php } ?>
            <div class="col-md-3">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#A7C7E730;">
                        <h3>Avg Conversion Time (Month)</h3>
                        <i id="pop-conv-time" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> On average, how long bookings take to go from being <strong>saved as a draft</strong> to reaching <strong>Pending Payment</strong>.<br><br><strong>Scope:</strong> all bookings from every sales agent, counted by the date they were saved as a draft this month (up to today). Cancelled bookings are left out, and only drafts that have since reached payment are counted.<br><br>Shown as hours and minutes. A dash (&mdash;) means no drafts reached payment this month."></i>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-value" id="sc-conv-time-value">...</div>
                        <div class="summary-sub"><span id="sc-conv-time-count">—</span> drafts reached payment this month. Average saved-as-draft &rarr; pending payment time, all agents.</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <a class="summary-card" id="sc-slow-conv-link" href="#">
                    <div class="card card-custom">
                        <div class="card-header border-0 summary-card-header" style="background-color:#F3D9D9;">
                            <h3>Slow Conversions (&gt; 24h)</h3>
                            <i id="pop-slow-conv" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> Drafts saved this month that took <strong>longer than 24 hours</strong> to go from being saved as a draft to reaching Pending Payment.<br><br><strong>Scope:</strong> all bookings from every sales agent, counted by the date they were saved as a draft this month (up to today). Cancelled bookings are left out.<br><br>Click to open these bookings below and see why they took so long."></i>
                        </div>
                        <div class="card-body summary-card-body">
                            <div class="summary-value" id="sc-slow-conv-count">...</div>
                            <div class="summary-sub">Drafts that took more than a day to reach payment this month. Click to review them below.</div>
                        </div>
                    </div>
                </a>
            </div>
            <?php /* TBC — empty slot reserved to complete Row 4 */ ?>
            <div class="col-md-3"></div>
            <div class="col-md-12">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#B7E4C730;">
                        <h3>Payment From Customer Due Soon</h3>
                        <i id="pop-customer-payment-due-soon" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> Confirmed bookings that still owe a customer payment, with a deadline coming up soon.<br><br><strong>Counted when:</strong><ul><li>The booking still has a scheduled payment to collect</li><li>There is still a balance owing (booking amount minus approved customer payments)</li><li>The next deadline falls between 1 March this year and tomorrow</li><li>It is not cancelled</li></ul><strong>Next deadline:</strong> the deposit deadline when nothing is paid yet, otherwise the full-payment deadline once a deposit is in.<br><br><strong>Grouped by deadline:</strong> Overdue (1 March up to before today), Today, and Tomorrow &mdash; each showing the number of bookings and the amount still owing.<br><br><strong>Table:</strong> the 5 most urgent bookings, earliest deadline first.<br><strong>Left out:</strong> fully paid, cancelled, and drafts/quotations."></i>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-row-3 mb-3">
                            <a href="#" class="due-bucket is-overdue" id="sc-customer-payment-due-soon-overdue-link" title="View bookings with customer payments overdue (since 1 March)">
                                <div class="lbl">Overdue</div>
                                <div class="summary-value-sm amt-overdue" id="sc-customer-payment-due-soon-overdue-count">...</div>
                                <div class="due-amt" id="sc-customer-payment-due-soon-overdue-total">...</div>
                            </a>
                            <a href="#" class="due-bucket is-today" id="sc-customer-payment-due-soon-today-link" title="View bookings with customer payments due today">
                                <div class="lbl">Today</div>
                                <div class="summary-value-sm amt-today" id="sc-customer-payment-due-soon-today-count">...</div>
                                <div class="due-amt" id="sc-customer-payment-due-soon-today-total">...</div>
                            </a>
                            <a href="#" class="due-bucket" id="sc-customer-payment-due-soon-tomorrow-link" title="View bookings with customer payments due tomorrow">
                                <div class="lbl">Tomorrow</div>
                                <div class="summary-value-sm" id="sc-customer-payment-due-soon-tomorrow-count">...</div>
                                <div class="due-amt" id="sc-customer-payment-due-soon-tomorrow-total">...</div>
                            </a>
                        </div>
                        <div class="summary-sub mb-2">Booking confirmations that still owe a scheduled customer payment with a deadline from 1 March up to tomorrow, bucketed by urgency. Each bucket shows the BC count and outstanding amount. Clear overdue and today first; the table lists the most urgent BCs, earliest deadline first.</div>
                        <table class="table table-sm summary-table">
                            <thead><tr><th>Booking</th><th>Customer</th><th class="text-right">Outstanding</th><th>Deadline</th></tr></thead>
                            <tbody id="sc-customer-payment-due-soon-body"><tr><td colspan="4" class="text-center text-muted">Loading…</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-12">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#FFE4B530;">
                        <h3>Supplier Pay-out Checklist Due Soon</h3>
                        <i id="pop-checklist-payout-due-soon" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> Bookings whose &ldquo;Payment Out To Supplier&rdquo; checklist hasn&rsquo;t been ticked off yet, with a deadline coming up soon.<br><br><strong>Counted when, for an active product line:</strong><ul><li>The product has a supplier pay-out checklist (full or deposit)</li><li>That checklist hasn&rsquo;t been ticked yet</li><li>The line isn&rsquo;t excluded from checklist pay-outs (same rule the checklist screen uses)</li><li>The pay-out deadline falls between 1 March this year and tomorrow</li><li>It is a confirmed booking, not cancelled or draft</li></ul><strong>Grouped by deadline:</strong> Overdue (1 March up to before today), Today, and Tomorrow &mdash; each showing the number of bookings.<br><br><strong>How this differs from &ldquo;Supplier Pay-out Due Soon&rdquo;:</strong> that card looks at pay-out records already created; this one flags pay-outs whose checklist still hasn&rsquo;t been actioned, so no RM amount is shown.<br><br><strong>Table:</strong> the 5 most urgent suppliers, earliest deadline first."></i>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-row-3 mb-3">
                            <a href="#" class="due-bucket is-overdue" id="sc-checklist-payout-due-soon-overdue-link" title="View bookings whose pay-out checklist is overdue (since 1 March)">
                                <div class="lbl">Overdue</div>
                                <div class="summary-value-sm amt-overdue" id="sc-checklist-payout-due-soon-overdue-count">...</div>
                            </a>
                            <a href="#" class="due-bucket is-today" id="sc-checklist-payout-due-soon-today-link" title="View bookings whose pay-out checklist is due today">
                                <div class="lbl">Today</div>
                                <div class="summary-value-sm amt-today" id="sc-checklist-payout-due-soon-today-count">...</div>
                            </a>
                            <a href="#" class="due-bucket" id="sc-checklist-payout-due-soon-tomorrow-link" title="View bookings whose pay-out checklist is due tomorrow">
                                <div class="lbl">Tomorrow</div>
                                <div class="summary-value-sm" id="sc-checklist-payout-due-soon-tomorrow-count">...</div>
                            </a>
                        </div>
                        <div class="summary-sub mb-2">BCs whose &ldquo;Payment Out To Supplier&rdquo; checklist is not ticked yet, bucketed by the pay-out deadline on the line (full or deposit) from 1 March up to tomorrow. Clear overdue and today first; the table lists the most urgent suppliers, earliest deadline first.</div>
                        <table class="table table-sm summary-table">
                            <thead><tr><th>Supplier</th><th class="text-right">Pay-outs</th><th>Earliest Deadline</th></tr></thead>
                            <tbody id="sc-checklist-payout-due-soon-body"><tr><td colspan="3" class="text-center text-muted">Loading…</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-12">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#FFFAA030;">
                        <h3>Supplier Pay-out Due Soon</h3>
                        <i id="pop-supplier-due-soon" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> Supplier pay-outs that are still unpaid, with a deadline coming up soon.<br><br><strong>Counted when:</strong><ul><li>It is a payment going out to a supplier</li><li>It hasn&rsquo;t been paid yet</li><li>The deadline falls between 1 March this year and tomorrow</li><li>It is linked to a supplier</li></ul><strong>Grouped by deadline:</strong> Overdue (1 March up to before today), Today, and Tomorrow &mdash; each showing the number of pay-outs and the total amount.<br><br><strong>Table:</strong> the 5 most urgent suppliers, earliest deadline first.<br><strong>Left out:</strong> already paid, deleted, customer payments coming in, and agent-commission entries."></i>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-row-3 mb-3">
                            <a href="#" class="due-bucket is-overdue" id="sc-supplier-due-soon-overdue-link" title="View bookings with supplier payouts overdue (since 1 March)">
                                <div class="lbl">Overdue</div>
                                <div class="summary-value-sm amt-overdue" id="sc-supplier-due-soon-overdue-count">...</div>
                                <div class="due-amt" id="sc-supplier-due-soon-overdue-total">...</div>
                            </a>
                            <a href="#" class="due-bucket is-today" id="sc-supplier-due-soon-today-link" title="View bookings with supplier payouts due today">
                                <div class="lbl">Today</div>
                                <div class="summary-value-sm amt-today" id="sc-supplier-due-soon-today-count">...</div>
                                <div class="due-amt" id="sc-supplier-due-soon-today-total">...</div>
                            </a>
                            <a href="#" class="due-bucket" id="sc-supplier-due-soon-tomorrow-link" title="View bookings with supplier payouts due tomorrow">
                                <div class="lbl">Tomorrow</div>
                                <div class="summary-value-sm" id="sc-supplier-due-soon-tomorrow-count">...</div>
                                <div class="due-amt" id="sc-supplier-due-soon-tomorrow-total">...</div>
                            </a>
                        </div>
                        <div class="summary-sub mb-2">Pending supplier payouts with deadlines from 1 March up to tomorrow, bucketed by urgency. Each bucket shows the payout count and total amount. Clear overdue and today first; the table lists the most urgent suppliers, earliest deadline first.</div>
                        <table class="table table-sm summary-table">
                            <thead><tr><th>Supplier</th><th class="text-right">Payouts</th><th class="text-right">Amount</th><th>Earliest Deadline</th></tr></thead>
                            <tbody id="sc-supplier-due-soon-body"><tr><td colspan="4" class="text-center text-muted">Loading…</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#A7C7E730;">
                        <h3>Top Destinations (Month)</h3>
                        <i id="pop-destination-sales-op" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> For each destination this month:<ul><li><strong>BC:</strong> how many bookings</li><li><strong>Sales:</strong> total sales</li></ul><strong>Order:</strong> most bookings first. Top 5.<br><br><strong>Counted:</strong> confirmed bookings only, not cancelled or draft, created this month.<br><br><strong>Tip:</strong> click a row to filter the booking list by that destination."></i>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-sub mb-2">Top 5 destinations this month by booking volume and total sales. Click a row to filter the list by that destination.</div>
                        <table class="table table-sm summary-table">
                            <thead><tr><th>Destination</th><th class="text-right">BC</th><th class="text-right">Sales</th></tr></thead>
                            <tbody id="sc-destination-sales-body"><tr><td colspan="3" class="text-center text-muted">Loading…</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#A7C7E730;">
                        <h3>Top Products (Month)</h3>
                        <i id="pop-product-sales" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> For each product this month (grouped by product code):<ul><li><strong>Qty:</strong> total quantity sold</li><li><strong>Sales:</strong> total sales</li></ul><strong>Counted:</strong> confirmed bookings only, not cancelled or draft, active product lines, created this month.<br><br><strong>Order:</strong> highest sales first. Top 5."></i>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-sub mb-2">Top 5 products this month by total sales, grouped by product item code.</div>
                        <table class="table table-sm summary-table">
                            <thead><tr><th>Code</th><th>Product</th><th class="text-right">Qty</th><th class="text-right">Sales</th></tr></thead>
                            <tbody id="sc-product-sales-body"><tr><td colspan="4" class="text-center text-muted">Loading…</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php } ?>

        <?php /* ---------- Finance ---------- */ ?>
        <?php if($show_finance) { ?>
            <div class="col-md-6">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#C4B45420;">
                        <h3>Total Payment In</h3>
                        <i id="pop-payin" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> The total customer money received (approved payments coming in).<br><br><strong>Counted when:</strong><ul><li>The payment is approved</li><li>It is money coming in (refunds and outgoing payments are left out)</li><li>Agent commission received from suppliers is left out</li></ul><strong>Periods</strong> (by payment date):<ul><li><strong>Today:</strong> today only</li><li><strong>Week:</strong> Monday to Sunday of this week</li><li><strong>Month:</strong> 1st to last day of this month</li></ul>"></i>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-row-3">
                            <div><div class="lbl">Today</div><div class="summary-value-sm" id="sc-payin-day">...</div></div>
                            <div><div class="lbl">Week</div><div class="summary-value-sm" id="sc-payin-week">...</div></div>
                            <div><div class="lbl">Month</div><div class="summary-value-sm" id="sc-payin-month">...</div></div>
                        </div>
                        <div class="summary-sub">Customer payments approved today, this week, and month-to-date. Excludes agent commission from suppliers.</div>
                    </div>
                </div>
            </div>
            <?php if(!$show_op) { ?>
            <div class="col-md-6">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#A7C7E730;">
                        <h3>Top Destinations (Month)</h3>
                        <i id="pop-destination-sales-fin" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> For each destination this month:<ul><li><strong>BC:</strong> how many bookings</li><li><strong>Sales:</strong> total sales</li></ul><strong>Order:</strong> highest sales first. Top 5.<br><br><strong>Counted:</strong> confirmed bookings only, not cancelled or draft, created this month.<br><br><strong>Tip:</strong> click a row to filter the booking list by that destination."></i>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-sub mb-2">Top 5 destinations this month by booking volume and total sales. Click a row to filter the list by that destination.</div>
                        <table class="table table-sm summary-table">
                            <thead><tr><th>Destination</th><th class="text-right">BC</th><th class="text-right">Sales</th></tr></thead>
                            <tbody id="sc-destination-sales-body"><tr><td colspan="3" class="text-center text-muted">Loading…</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php } ?>
            <div class="col-md-6">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#A7C7E730;">
                        <h3>Top Products (Month)</h3>
                        <i id="pop-product-sales" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> For each product this month (grouped by product code):<ul><li><strong>Qty:</strong> total quantity sold</li><li><strong>Sales:</strong> total sales</li></ul><strong>Counted:</strong> confirmed bookings only, not cancelled or draft, active product lines, created this month.<br><br><strong>Order:</strong> highest sales first. Top 5."></i>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-sub mb-2">Top 5 products this month by total sales, grouped by product item code.</div>
                        <table class="table table-sm summary-table">
                            <thead><tr><th>Code</th><th>Product</th><th class="text-right">Qty</th><th class="text-right">Sales</th></tr></thead>
                            <tbody id="sc-product-sales-body"><tr><td colspan="4" class="text-center text-muted">Loading…</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#A7C7E730;">
                        <h3>Sales by Team (Month)</h3>
                        <i id="pop-sales-by-team" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> For each team this month:<ul><li><strong>BC:</strong> how many bookings credited to the team</li><li><strong>Sales:</strong> total sales</li></ul><strong>How teams are grouped:</strong> each booking&rsquo;s sales agent rolls up to their team lead. Agents with no team lead are grouped into a single &ldquo;Unassigned&rdquo; row, so the rows add up to the company total.<br><br><strong>Counted:</strong> confirmed bookings only, not cancelled or draft, created this month."></i>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-sub mb-2">Sales credited to each team this month, grouped by the agent's team lead.</div>
                        <table class="table table-sm summary-table">
                            <thead><tr><th>Team Lead</th><th class="text-right">BC</th><th class="text-right">Sales</th></tr></thead>
                            <tbody id="sc-sales-by-team-body"><tr><td colspan="3" class="text-center text-muted">Loading…</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-12">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#FAA0A030;">
                        <h3>Supplier Overdue</h3>
                        <i id="pop-supplier-overdue" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>What it shows:</strong> Supplier pay-outs whose deadline has already passed but are still unpaid.<br><br><strong>Counted when:</strong><ul><li>It is a payment going out to a supplier</li><li>It hasn&rsquo;t been paid yet</li><li>The deadline is before today</li><li>It is linked to a supplier</li></ul><strong>Card:</strong> the headline shows the number of overdue pay-outs and the combined amount due; the table breaks down the top 5 suppliers by amount.<br><br><strong>Left out:</strong> already paid, deleted, customer payments coming in, and agent-commission entries."></i>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="lbl">Overdue Payments</div>
                                <div class="summary-value" id="sc-supplier-overdue-count">...</div>
                                <div class="lbl mt-3">Total Amount Due</div>
                                <div class="summary-value-sm" id="sc-supplier-overdue-total">...</div>
                                <div class="summary-sub mt-2">Past-deadline supplier payouts that have not yet been recorded as paid.</div>
                            </div>
                            <div class="col-md-8">
                                <table class="table table-sm summary-table">
                                    <thead><tr><th>Supplier</th><th class="text-right">Overdue</th><th class="text-right">Amount</th><th>Earliest Deadline</th></tr></thead>
                                    <tbody id="sc-supplier-overdue-body"><tr><td colspan="4" class="text-center text-muted">Loading…</td></tr></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php } ?>

    </div>
    </div>
</div>
<script>
(function() {
    var KEY = 'booking_summary_cards_collapsed';
    try {
        if(localStorage.getItem(KEY) === '1') {
            var body = document.getElementById('booking_summary_cards_body');
            var toggle = document.querySelector('#booking_summary_cards .panel-toggle');
            if(body) body.classList.remove('show');
            if(toggle) {
                toggle.classList.add('collapsed');
                toggle.setAttribute('aria-expanded', 'false');
            }
        }
    } catch(e) {}
})();
</script>
<script>
$(function() {
    var KEY = 'booking_summary_cards_collapsed';
    var $body = $('#booking_summary_cards_body');
    $body.on('shown.bs.collapse', function() {
        try { localStorage.setItem(KEY, '0'); } catch(e) {}
    });
    $body.on('hidden.bs.collapse', function() {
        try { localStorage.setItem(KEY, '1'); } catch(e) {}
    });
    // Anti-flicker hover popover. The stock `trigger:'hover focus'` hides the
    // popover the instant the cursor leaves the icon, so moving the mouse the
    // few pixels down into the popover body fires mouseleave -> hide, and the
    // re-entry fires mouseenter -> show again: a rapid show/hide flicker loop.
    // Here we drive show/hide manually and keep the popover open while the
    // cursor is over EITHER the icon or the popover tip, with a short grace
    // delay so the gap between them no longer breaks the hover.
    window.initSummaryPopover = function(el) {
        var $el = $(el);
        $el.popover('dispose').popover({
            customClass: 'summary-popover',
            container: 'body',
            boundary: 'window',
            html: true,
            trigger: 'manual'
        });
        var hideTimer = null;
        function cancelHide() { if(hideTimer) { clearTimeout(hideTimer); hideTimer = null; } }
        function scheduleHide() {
            cancelHide();
            hideTimer = setTimeout(function() { $el.popover('hide'); }, 150);
        }
        $el.off('.scpop')
            .on('mouseenter.scpop focus.scpop', function() { cancelHide(); $el.popover('show'); })
            .on('mouseleave.scpop blur.scpop', scheduleHide)
            .on('shown.bs.popover.scpop', function() {
                var inst = $el.data('bs.popover');
                var tip = inst && inst.tip;
                if(tip) {
                    $(tip).off('.scpop')
                        .on('mouseenter.scpop', cancelHide)
                        .on('mouseleave.scpop', scheduleHide);
                }
            });
    };
    $('#booking_summary_cards [data-toggle="popover"]').each(function() {
        window.initSummaryPopover(this);
    });
});
(function() {
    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function setText(id, val) {
        var el = document.getElementById(id);
        if(el) el.textContent = (val == null ? '-' : val);
    }
    // Owner matrix formatters. Seconds -> s/m/h (matches the TC response-time
    // cards); raw RM value -> "RM 1,234.00". Null/undefined -> em-dash.
    function fmtOwnerSecs(s) {
        if(s === null || s === undefined) return '—';
        s = Number(s);
        if(s >= 3600) return (s / 3600).toFixed(1) + 'h';
        if(s >= 60)   return (s / 60).toFixed(1) + 'm';
        return Math.round(s) + 's';
    }
    function fmtOwnerMoney(v) {
        v = Number(v) || 0;
        return 'RM ' + v.toLocaleString('en-MY', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
    function setLink(id, href) {
        var el = document.getElementById(id);
        if(el && href) el.setAttribute('href', href);
    }
    function setBest(id, best, label) {
        var el = document.getElementById(id);
        if(!el) return;
        label = label || 'Best';
        if(!best || best.name == null || best.value == null) {
            el.innerHTML = label + ': —';
            return;
        }
        var isYou = (String(best.name) === 'You');
        el.innerHTML = label + ': <span class="best-name' + (isYou ? ' is-you' : '') + '">'
            + escapeHtml(best.name) + '</span> · ' + escapeHtml(best.value);
    }
    // Actual vs Target bars: both scaled to max(actual, target) so the longer
    // bar fills the track and the other is proportional. Over-achievement caps
    // the actual bar at 100% visually while the % label keeps the true figure.
    function setBars(actualId, targetId, raw, rawTarget) {
        var a = parseFloat(raw) || 0;
        var tg = parseFloat(rawTarget) || 0;
        var scale = Math.max(a, tg, 1);
        var af = document.getElementById(actualId);
        var tf = document.getElementById(targetId);
        if(af) af.style.width = Math.max(0, Math.min(100, (a / scale) * 100)) + '%';
        if(tf) tf.style.width = (tg > 0 ? Math.max(0, Math.min(100, (tg / scale) * 100)) : 0) + '%';
    }
    // Best line: agent name + standout figure. Falls back to figure-only when
    // no name is resolved.
    function setBestFigure(id, best, label) {
        var el = document.getElementById(id);
        if(!el) return;
        label = label || 'Best';
        if(!best || best.value == null) { el.innerHTML = label + ': —'; return; }
        var html = label + ': ';
        if(best.name != null) {
            var isYou = (String(best.name) === 'You');
            html += '<span class="best-name' + (isYou ? ' is-you' : '') + '">'
                + escapeHtml(best.name) + '</span> · ';
        }
        html += '<span class="best-fig">' + escapeHtml(best.value) + '</span>';
        el.innerHTML = html;
    }
    // Agent Score card: render the stashed (month) payload. Empty payload ->
    // em-dash, leaderboard "Top:" stays.
    function renderAgentScore() {
        var d = window._agentScore;
        if(!d) { return; }
        var has = (d.raw != null && d.value && d.value !== '-');
        setText('sc-agent-score-value', has ? d.value : '—');
        setText('sc-agent-score-rank',  d.rank == null ? '—' : d.rank);
        setText('sc-agent-score-total', d.total == null ? '—' : d.total);
        setBest('sc-agent-score-best', d.best, 'Top');
        // Top-5 leaderboard on the right; the logged-in agent's row is highlighted.
        var lb = document.getElementById('sc-agent-score-leaderboard');
        if(lb) {
            var rows = (d.leaderboard && d.leaderboard.length) ? d.leaderboard : null;
            if(!rows) {
                lb.innerHTML = '<li class="asb-empty">No ranked agents yet.</li>';
            } else {
                lb.innerHTML = rows.map(function(r) {
                    var you = r.is_you ? ' is-you' : '';
                    return '<li class="' + (r.is_you ? 'is-you' : '') + '">'
                        + '<span class="asb-rank">' + escapeHtml(String(r.rank)) + '</span>'
                        + '<span class="asb-name' + you + '">' + escapeHtml(r.name) + '</span>'
                        + '<span class="asb-val">' + escapeHtml(r.value) + '</span>'
                        + '</li>';
                }).join('');
            }
        }
    }
    // Keep clicks on the "Last synced" text from collapsing the panel.
    $('#booking_summary_cards .ghl-last-sync').on('click', function(e) {
        e.stopPropagation();
    });
    // Owner matrix: global Day/Week/Month/Year toggle. Re-requests the whole
    // matrix scoped to the chosen period (remembered in window._ownerPeriod).
    $('#booking_summary_cards').on('click', '.sc-owner-tab', function() {
        var p = $(this).data('owner-period');
        window._ownerPeriod = p;
        $('#booking_summary_cards .sc-owner-tab').removeClass('is-active');
        $(this).addClass('is-active');
        var mp = document.getElementById('sc-month-picker');
        loadSummaryCards(mp ? mp.value : null, p);
    });

    function loadSummaryCards(month, ownerPeriod) {
        var url = '<?php echo base_url("Booking/ajax_summary_cards"); ?>';
        var params = [];
        if(month) { params.push('month=' + encodeURIComponent(month)); }
        var op = ownerPeriod || window._ownerPeriod;
        if(op) { params.push('owner_period=' + encodeURIComponent(op)); }
        if(params.length) { url += '?' + params.join('&'); }
        $.getJSON(url, function(resp) {
        if(!resp || resp.error) return;
        var c = resp.cards || {};
        var t = resp.tables || {};
        var m = resp.meta   || {};

        if(m.last_ghl_sync_display) {
            setText('ghl-last-sync', m.last_ghl_sync_display);
        }
        // Reflect the server-resolved period back into the picker (covers the
        // first load and any fallback when a bad month was requested).
        var scPick = document.getElementById('sc-month-picker');
        if(scPick && m.selected_month && scPick.value !== m.selected_month) {
            scPick.value = m.selected_month;
        }

        // TC cards
        if(c.bc_month) {
            setText('sc-bc-month-count', c.bc_month.count);
            setLink('sc-bc-month-link', c.bc_month.link);
            setBestFigure('sc-bc-month-best', c.bc_month.best, 'Best (month)');
        }
        if(c.bc_year) {
            setText('sc-bc-year-count', c.bc_year.count);
            setLink('sc-bc-year-link', c.bc_year.link);
            setBestFigure('sc-bc-year-best', c.bc_year.best, 'Best (year)');
        }
        if(c.sales_month) {
            setText('sc-sales-month-value', c.sales_month.value);
            setText('sc-sales-month-target', c.sales_month.target);
            setText('sc-sales-month-percent', c.sales_month.percent);
            setText('sc-sales-month-value-2', c.sales_month.value);
            setText('sc-sales-month-target-2', c.sales_month.has_target ? c.sales_month.target : '—');
            setBars('sc-sales-month-bar-actual', 'sc-sales-month-bar-target', c.sales_month.raw, c.sales_month.raw_target);
            setBestFigure('sc-sales-month-best', c.sales_month.best);
            var emptyEl = document.getElementById('sc-sales-month-empty-target');
            if(emptyEl) {
                emptyEl.style.display = c.sales_month.has_target ? 'none' : '';
            }
        }
        if(c.sales_year) {
            setText('sc-sales-year-value', c.sales_year.value);
            setText('sc-sales-year-target', c.sales_year.target);
            setText('sc-sales-year-percent', c.sales_year.percent);
            setText('sc-sales-year-value-2', c.sales_year.value);
            setText('sc-sales-year-target-2', c.sales_year.has_target ? c.sales_year.target : '—');
            setBars('sc-sales-year-bar-actual', 'sc-sales-year-bar-target', c.sales_year.raw, c.sales_year.raw_target);
            setBestFigure('sc-sales-year-best', c.sales_year.best);
            var emptyElY = document.getElementById('sc-sales-year-empty-target');
            if(emptyElY) {
                emptyElY.style.display = c.sales_year.has_target ? 'none' : '';
            }
        }
        if(c.tc_leads_dwm) {
            setText('sc-tc-leads-day',   c.tc_leads_dwm.day);
            setText('sc-tc-leads-week',  c.tc_leads_dwm.week);
            setText('sc-tc-leads-month', c.tc_leads_dwm.month);
            setBestFigure('sc-tc-leads-best', c.tc_leads_dwm.best);
        }
        if(c.tc_handle_lead_today) {
            setText('sc-tc-handle-day',   c.tc_handle_lead_today.day);
            setText('sc-tc-handle-week',  c.tc_handle_lead_today.week);
            setText('sc-tc-handle-month', c.tc_handle_lead_today.month);
            setLink('sc-tc-handle-link',  c.tc_handle_lead_today.link);
        }
        if(c.tc_response_time_dwm) {
            setText('sc-tc-resp-day',   c.tc_response_time_dwm.day);
            setText('sc-tc-resp-week',  c.tc_response_time_dwm.week);
            setText('sc-tc-resp-month', c.tc_response_time_dwm.month);
            setBest('sc-tc-resp-best',  c.tc_response_time_dwm.best, 'Fastest');
        }
        if(c.outbound_msgs_dwm) {
            setText('sc-tc-outbound-day',   c.outbound_msgs_dwm.day);
            setText('sc-tc-outbound-week',  c.outbound_msgs_dwm.week);
            setText('sc-tc-outbound-month', c.outbound_msgs_dwm.month);
            setBestFigure('sc-tc-outbound-best', c.outbound_msgs_dwm.best);
        }
        if(c.followup_rate) {
            setText('sc-tc-followup-value',  c.followup_rate.value);
            setText('sc-tc-followup-detail', c.followup_rate.detail);
            setBest('sc-tc-followup-best',   c.followup_rate.best, 'Highest follow-up');
        }
        if(c.agent_score_month) {
            window._agentScore = c.agent_score_month;
            renderAgentScore();
        }
        if(c.tc_pickup_speed_month) {
            var pk = c.tc_pickup_speed_month;
            // format_response_duration returns '-' for null; render an em-dash
            // when none of this month's leads were picked up.
            var pkHas = (pk.count > 0 && pk.value && pk.value !== '-');
            setText('sc-tc-pickup-value', pkHas ? pk.value : '—');
            setText('sc-tc-pickup-count', pk.count);
            setBest('sc-tc-pickup-best',  pk.best, 'Fastest');
        }
        if(c.conversion_time_month) {
            var ct = c.conversion_time_month;
            // format_response_duration returns '-' for null; render an em-dash
            // when none of this month's BCs converted.
            var ctHas = (ct.count > 0 && ct.value && ct.value !== '-');
            setText('sc-conv-time-value', ctHas ? ct.value : '—');
            setText('sc-conv-time-count', ct.count);
        }
        if(c.slow_conversion_month) {
            setText('sc-slow-conv-count', c.slow_conversion_month.count);
            setLink('sc-slow-conv-link',  c.slow_conversion_month.link);
        }
        if(c.cancellation_rate) {
            setText('sc-cancel-rate-value', c.cancellation_rate.value);
            setText('sc-cancel-rate-detail', c.cancellation_rate.detail);
            setLink('sc-cancel-rate-link', c.cancellation_rate.link);
            setBest('sc-cancel-rate-best', c.cancellation_rate.best, 'Lowest cancellation rate');
            setText('sc-cancel-rate-tl-value', c.cancellation_rate.value);
            setText('sc-cancel-rate-tl-detail', c.cancellation_rate.detail);
            setLink('sc-cancel-rate-tl-link', c.cancellation_rate.link);
        }
        if(c.conversion_rate_ytd) {
            setText('sc-conv-rate-value',  c.conversion_rate_ytd.value);
            setText('sc-conv-rate-detail', c.conversion_rate_ytd.detail);
            setBestFigure('sc-conv-rate-best',   c.conversion_rate_ytd.best);
        }
        if(c.upcoming_travel_not_ready_op) {
            setText('sc-upcoming-not-ready-op-count', c.upcoming_travel_not_ready_op.count);
            setLink('sc-upcoming-not-ready-op-link',  c.upcoming_travel_not_ready_op.link);
        }
        if(c.upcoming_travel_not_ready_op_14) {
            setText('sc-upcoming-not-ready-op-14-count', c.upcoming_travel_not_ready_op_14.count);
            setLink('sc-upcoming-not-ready-op-14-link',  c.upcoming_travel_not_ready_op_14.link);
        }
        // OP operational queue cards.
        if(c.pending_bc_op) {
            setText('sc-pending-bc-op-count', c.pending_bc_op.count);
            setLink('sc-pending-bc-op-link',  c.pending_bc_op.link);
        }
        if(c.pending_bc_confirmation_op) {
            setText('sc-pending-bc-confirmation-op-count', c.pending_bc_confirmation_op.count);
            setLink('sc-pending-bc-confirmation-op-link',  c.pending_bc_confirmation_op.link);
        }
        if(c.travel_tomorrow_op) {
            setText('sc-travel-tomorrow-op-count', c.travel_tomorrow_op.count);
            setLink('sc-travel-tomorrow-op-link',  c.travel_tomorrow_op.link);
        }
        if(c.travel_tomorrow_not_ready_op) {
            setText('sc-travel-tomorrow-not-ready-op-count', c.travel_tomorrow_not_ready_op.count);
            setLink('sc-travel-tomorrow-not-ready-op-link',  c.travel_tomorrow_not_ready_op.link);
        }
        if(c.pending_review_op) {
            setText('sc-pending-review-op-count', c.pending_review_op.count);
            setLink('sc-pending-review-op-link',  c.pending_review_op.link);
        }

        // TC LEAD / OP / Owner BC week+month
        if(c.bc_week_month) {
            setText('sc-bc-week-tl', c.bc_week_month.week);
            setText('sc-bc-month-tl', c.bc_week_month.month);
            setLink('sc-bc-month-tl-link', c.bc_week_month.link_month);
            setText('sc-bc-week-op', c.bc_week_month.week);
            setText('sc-bc-month-op', c.bc_week_month.month);
            setLink('sc-bc-month-op-link', c.bc_week_month.link_month);
        }

        // Lead metrics
        if(c.leads_dwm) {
            setText('sc-leads-day',   c.leads_dwm.day);
            setText('sc-leads-week',  c.leads_dwm.week);
            setText('sc-leads-month', c.leads_dwm.month);
            setText('sc-leads-conv',  c.leads_dwm.conversion_rate);
            setText('sc-leads-resp',  c.leads_dwm.response_rate);
            setText('sc-leads-time',  c.leads_dwm.avg_response_time);
        }
        if(c.active_leads_dwm) {
            setText('sc-active-leads-day',   c.active_leads_dwm.day);
            setText('sc-active-leads-week',  c.active_leads_dwm.week);
            setText('sc-active-leads-month', c.active_leads_dwm.month);
        }

        // OP
        if(c.gl_submitted) {
            setText('sc-gl-submitted-count', c.gl_submitted.count);
            setLink('sc-gl-submitted-link', c.gl_submitted.link);
        }
        if(c.insurance_pending) {
            setText('sc-insurance-pending-count', c.insurance_pending.count);
            setLink('sc-insurance-pending-link',  c.insurance_pending.link);
        }
        if(c.ferry_pending) {
            setText('sc-ferry-pending-count', c.ferry_pending.count);
            setLink('sc-ferry-pending-link',  c.ferry_pending.link);
        }
        if(c.supplier_due_soon) {
            var ds = c.supplier_due_soon;
            ['overdue', 'today', 'tomorrow'].forEach(function(b) {
                if(!ds[b]) return;
                setText('sc-supplier-due-soon-' + b + '-count', ds[b].count);
                setText('sc-supplier-due-soon-' + b + '-total', ds[b].total_due);
                setLink('sc-supplier-due-soon-' + b + '-link', ds[b].link);
            });
        }
        if(c.customer_payment_due_soon) {
            var cds = c.customer_payment_due_soon;
            ['overdue', 'today', 'tomorrow'].forEach(function(b) {
                if(!cds[b]) return;
                setText('sc-customer-payment-due-soon-' + b + '-count', cds[b].count);
                setText('sc-customer-payment-due-soon-' + b + '-total', cds[b].total_due);
                setLink('sc-customer-payment-due-soon-' + b + '-link', cds[b].link);
            });
        }
        if(c.checklist_payout_due_soon) {
            var cp = c.checklist_payout_due_soon;
            ['overdue', 'today', 'tomorrow'].forEach(function(b) {
                if(!cp[b]) return;
                setText('sc-checklist-payout-due-soon-' + b + '-count', cp[b].count);
                setLink('sc-checklist-payout-due-soon-' + b + '-link', cp[b].link);
            });
        }

        // Finance
        if(c.payment_in_dwm) {
            setText('sc-payin-day',   c.payment_in_dwm.day);
            setText('sc-payin-week',  c.payment_in_dwm.week);
            setText('sc-payin-month', c.payment_in_dwm.month);
        }
        if(c.supplier_overdue) {
            setText('sc-supplier-overdue-count', c.supplier_overdue.count);
            setText('sc-supplier-overdue-total', c.supplier_overdue.total_due);
        }

        // Tables
        var dest = t.destination_sales;
        var destBody = document.getElementById('sc-destination-sales-body');
        if(destBody) {
            if(dest && dest.length) {
                destBody.innerHTML = dest.map(function(r) {
                    return '<tr>' +
                        '<td><a href="' + escapeHtml(r.link) + '">' + escapeHtml(r.destination || '—') + '</a></td>' +
                        '<td class="text-right">' + escapeHtml(r.count) + '</td>' +
                        '<td class="text-right">' + escapeHtml(r.total) + '</td>' +
                    '</tr>';
                }).join('');
            } else {
                destBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No data this month</td></tr>';
            }
        }

        var destClosed = t.destination_closed_sales;
        var destClosedBody = document.getElementById('sc-destination-closed-sales-body');
        if(destClosedBody) {
            if(destClosed && destClosed.length) {
                destClosedBody.innerHTML = destClosed.map(function(r) {
                    return '<tr>' +
                        '<td><a href="' + escapeHtml(r.link) + '">' + escapeHtml(r.destination || '—') + '</a></td>' +
                        '<td class="text-right">' + escapeHtml(r.count) + '</td>' +
                        '<td class="text-right">' + escapeHtml(r.total) + '</td>' +
                    '</tr>';
                }).join('');
            } else {
                destClosedBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No data this month</td></tr>';
            }
        }

        var leadsByTag = t.active_leads_by_tag;
        var leadsByTagBody = document.getElementById('sc-active-leads-by-tag-body');
        if(leadsByTagBody) {
            var destRows = (leadsByTag && leadsByTag.destination) || [];
            var langRows = (leadsByTag && leadsByTag.language)    || [];
            var raceRows = (leadsByTag && leadsByTag.race)        || [];
            var maxLen   = Math.max(destRows.length, langRows.length, raceRows.length);
            if(maxLen === 0) {
                leadsByTagBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No active leads with allowlisted tags</td></tr>';
            } else {
                var cell = function(r) {
                    if(!r) return '<td class="text-muted">&mdash;</td>';
                    return '<td>' + escapeHtml(r.tag) + ' <span class="text-muted">(' + escapeHtml(r.count) + ')</span></td>';
                };
                var html = '';
                for(var i = 0; i < maxLen; i++) {
                    html += '<tr>' + cell(destRows[i]) + cell(langRows[i]) + cell(raceRows[i]) + '</tr>';
                }
                leadsByTagBody.innerHTML = html;
            }
        }

        var prod = t.product_sales;
        var prodBody = document.getElementById('sc-product-sales-body');
        if(prodBody) {
            if(prod && prod.length) {
                prodBody.innerHTML = prod.map(function(r) {
                    return '<tr>' +
                        '<td>' + escapeHtml(r.code || '—') + '</td>' +
                        '<td>' + escapeHtml(r.name || '—') + '</td>' +
                        '<td class="text-right">' + escapeHtml(r.qty) + '</td>' +
                        '<td class="text-right">' + escapeHtml(r.total) + '</td>' +
                    '</tr>';
                }).join('');
            } else {
                prodBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No data this month</td></tr>';
            }
        }

        var agents = t.agent_conversion;
        var agentBody = document.getElementById('sc-agent-conversion-body');
        if(agentBody) {
            if(agents && agents.length) {
                agentBody.innerHTML = agents.map(function(r) {
                    return '<tr>' +
                        '<td>' + escapeHtml(r.agent_name || '—') + '</td>' +
                        '<td class="text-right">' + escapeHtml(r.total_leads) + '</td>' +
                        '<td class="text-right">' + escapeHtml(r.converted_leads) + '</td>' +
                        '<td class="text-right">' + escapeHtml(r.conversion_rate) + '</td>' +
                    '</tr>';
                }).join('');
            } else {
                agentBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No data this month</td></tr>';
            }
        }

        // Owner per-agent performance matrix — one row per sales agent across
        // all 11 metrics for the toggle-selected period. Each column only applies
        // to certain periods; for any other period the cell shows an em-dash.
        var ownerMatrix = t.owner_agent_matrix;
        var ownerMatrixBody = document.getElementById('sc-owner-matrix-body');
        if(ownerMatrixBody) {
            // Periods each column is defined for (everything else renders "—").
            var ownerColPeriods = {
                reply:    ['day','week','month'],
                pickup:   ['day','week','month','year'],
                newleads: ['day','week','month'],
                served:   ['day','week','month'],
                convc:    ['year'],
                conva:    ['year'],
                outbound: ['day','week','month'],
                sales:    ['month','year'],
                followup: ['month'],
                cancel:   ['year'],
                score:    ['month']
            };
            var ownerP = m.owner_period || window._ownerPeriod || 'month';
            var inP = function(col) { return ownerColPeriods[col].indexOf(ownerP) !== -1; };
            // cell(applies, rendered-html) -> the html, or a right-aligned em-dash.
            var oCell = function(col, html) {
                return '<td class="text-right">' + (inP(col) ? html : '—') + '</td>';
            };
            if(ownerMatrix && ownerMatrix.length) {
                ownerMatrixBody.innerHTML = ownerMatrix.map(function(r) {
                    var score = (r.agent_score === null || r.agent_score === undefined)
                        ? '—' : escapeHtml(r.agent_score);
                    return '<tr>' +
                        '<td>' + escapeHtml(r.agent_name || '—') + '</td>' +
                        oCell('reply',    fmtOwnerSecs(r.reply_secs)) +
                        oCell('pickup',   fmtOwnerSecs(r.pickup_secs)) +
                        oCell('newleads', escapeHtml(r.new_leads)) +
                        oCell('served',   escapeHtml(r.served_leads)) +
                        oCell('convc',    escapeHtml(r.conv_rate_gated) + '%') +
                        oCell('conva',    escapeHtml(r.conv_rate_ungated) + '%') +
                        oCell('outbound', escapeHtml(r.outbound_count)) +
                        oCell('sales',    fmtOwnerMoney(r.sales_total)) +
                        oCell('followup', escapeHtml(r.followup_rate) + '%') +
                        oCell('cancel',   escapeHtml(r.cancel_rate) + '%') +
                        oCell('score',    score) +
                    '</tr>';
                }).join('');
            } else {
                ownerMatrixBody.innerHTML = '<tr><td colspan="12" class="text-center text-muted">No data for this period</td></tr>';
            }
        }
        // Reflect the server-resolved owner period onto the active toggle tab and
        // header label (covers the default and the bad-input fallback).
        if(m.owner_period) {
            $('#booking_summary_cards .sc-owner-tab').removeClass('is-active');
            $('#booking_summary_cards .sc-owner-tab[data-owner-period="' + m.owner_period + '"]').addClass('is-active');
            window._ownerPeriod = m.owner_period;
        }
        if(m.owner_period_label) { setText('sc-owner-period-label', m.owner_period_label); }

        var teams = t.sales_by_team;
        var teamBody = document.getElementById('sc-sales-by-team-body');
        if(teamBody) {
            if(teams && teams.length) {
                teamBody.innerHTML = teams.map(function(r) {
                    return '<tr>' +
                        '<td>' + escapeHtml(r.team_lead_name || '—') + '</td>' +
                        '<td class="text-right">' + escapeHtml(r.count) + '</td>' +
                        '<td class="text-right">' + escapeHtml(r.total) + '</td>' +
                    '</tr>';
                }).join('');
            } else {
                teamBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No data this month</td></tr>';
            }
        }

        // Self Gen vs Company headline card.
        if(c.lead_source_split) {
            setText('sc-source-selfgen-count', c.lead_source_split.self_gen_count);
            setText('sc-source-selfgen-total', c.lead_source_split.self_gen_total);
            setText('sc-source-company-count', c.lead_source_split.company_count);
            setText('sc-source-company-total', c.lead_source_split.company_total);
        }

        // Per-agent Self Gen vs Company table. Team Lead cell uses rowspan so a
        // run of agents under the same team lead is visually grouped (the rows
        // arrive already sorted by team lead from the server).
        var agentSrc = t.agent_source_split;
        var agentSrcBody = document.getElementById('sc-agent-source-split-body');
        if(agentSrcBody) {
            if(agentSrc && agentSrc.length) {
                var groups = {};
                var order  = [];
                agentSrc.forEach(function(r) {
                    var key = r.team_lead_name || '—';
                    if(!groups[key]) {
                        groups[key] = [];
                        order.push(key);
                    }
                    groups[key].push(r);
                });
                var html = '';
                order.forEach(function(team) {
                    var rows = groups[team];
                    rows.forEach(function(r, idx) {
                        html += '<tr>';
                        if(idx === 0) {
                            html += '<td rowspan="' + rows.length + '" class="align-middle"><strong>' + escapeHtml(team) + '</strong></td>';
                        }
                        html += '<td>' + escapeHtml(r.agent_name || '—') + '</td>' +
                                '<td class="text-right">' + escapeHtml(r.self_gen_count) + '</td>' +
                                '<td class="text-right">' + escapeHtml(r.self_gen_total) + '</td>' +
                                '<td class="text-right">' + escapeHtml(r.company_count) + '</td>' +
                                '<td class="text-right">' + escapeHtml(r.company_total) + '</td>' +
                                '<td class="text-right">' + escapeHtml(r.self_gen_pct) + '</td>' +
                                '</tr>';
                    });
                });
                agentSrcBody.innerHTML = html;
            } else {
                agentSrcBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No agent BCs this month</td></tr>';
            }
        }

        var sup = t.supplier_overdue;
        var supBody = document.getElementById('sc-supplier-overdue-body');
        if(supBody) {
            if(sup && sup.length) {
                supBody.innerHTML = sup.map(function(r) {
                    return '<tr>' +
                        '<td>' + escapeHtml(r.name || '—') + '</td>' +
                        '<td class="text-right">' + escapeHtml(r.count) + '</td>' +
                        '<td class="text-right">' + escapeHtml(r.total_due) + '</td>' +
                        '<td>' + escapeHtml(r.earliest_deadline) + '</td>' +
                    '</tr>';
                }).join('');
            } else {
                supBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No overdue supplier payments</td></tr>';
            }
        }

        var due = t.supplier_due_soon;
        var dueBody = document.getElementById('sc-supplier-due-soon-body');
        if(dueBody) {
            if(due && due.length) {
                dueBody.innerHTML = due.map(function(r) {
                    return '<tr>' +
                        '<td>' + escapeHtml(r.name || '—') + '</td>' +
                        '<td class="text-right">' + escapeHtml(r.count) + '</td>' +
                        '<td class="text-right">' + escapeHtml(r.total_due) + '</td>' +
                        '<td>' + escapeHtml(r.earliest_deadline) + '</td>' +
                    '</tr>';
                }).join('');
            } else {
                dueBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No supplier payouts due between 1 March and tomorrow</td></tr>';
            }
        }

        var custDue = t.customer_payment_due_soon;
        var custDueBody = document.getElementById('sc-customer-payment-due-soon-body');
        if(custDueBody) {
            if(custDue && custDue.length) {
                custDueBody.innerHTML = custDue.map(function(r) {
                    return '<tr>' +
                        '<td>' + escapeHtml(r.booking_number || '—') + '</td>' +
                        '<td>' + escapeHtml(r.customer || '—') + '</td>' +
                        '<td class="text-right">' + escapeHtml(r.total_due) + '</td>' +
                        '<td>' + escapeHtml(r.earliest_deadline) + '</td>' +
                    '</tr>';
                }).join('');
            } else {
                custDueBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No customer payments due between 1 March and tomorrow</td></tr>';
            }
        }

        var cpay = t.checklist_payout_due_soon;
        var cpayBody = document.getElementById('sc-checklist-payout-due-soon-body');
        if(cpayBody) {
            if(cpay && cpay.length) {
                cpayBody.innerHTML = cpay.map(function(r) {
                    return '<tr>' +
                        '<td>' + escapeHtml(r.name || '—') + '</td>' +
                        '<td class="text-right">' + escapeHtml(r.count) + '</td>' +
                        '<td>' + escapeHtml(r.earliest_deadline) + '</td>' +
                    '</tr>';
                }).join('');
            } else {
                cpayBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No unticked pay-out checklists due between 1 March and tomorrow</td></tr>';
            }
        }

        // Inject value-rich popover HTML from the server. The view ships
        // static fallback copy in each icon's data-content so popovers still
        // make sense before this AJAX returns; here we overwrite with the
        // live numbers + concrete dates and re-init the popover.
        if(resp.popovers) {
            Object.keys(resp.popovers).forEach(function(id) {
                var $el = $('#' + id);
                if(!$el.length) return;
                $el.attr('data-content', resp.popovers[id]);
                if(window.initSummaryPopover) {
                    window.initSummaryPopover($el.get(0));
                } else {
                    $el.popover('dispose').popover({
                        customClass: 'summary-popover',
                        container: 'body',
                        boundary: 'window'
                    });
                }
            });
        }
        });
    }

    // Month filter: re-fetch all cards scoped to the chosen month. The Year
    // card follows the selected year. Only present for TC (level 20/50).
    var scMonthPicker = document.getElementById('sc-month-picker');
    if(scMonthPicker) {
        scMonthPicker.addEventListener('change', function() {
            loadSummaryCards(scMonthPicker.value);
        });
    }
    loadSummaryCards();
})();
</script>
<?php } ?>
