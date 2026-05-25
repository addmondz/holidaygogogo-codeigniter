<?php
    $card_level = (int) $this->session->userdata('level');
    $show_tc      = ($card_level == 20 || $card_level == 50);
    $show_tclead  = ($card_level == 25);
    $show_op      = ($card_level == 40);
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
    #booking_summary_cards .summary-row-3 { display:flex; gap:14px; }
    #booking_summary_cards .summary-row-3 > div { flex:1; }
    #booking_summary_cards .summary-row-3 .lbl { font-size:10px; color:#7E8299; text-transform:uppercase; letter-spacing:0.5px; }
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
</style>
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
            <div class="col-md-3">
                <a class="summary-card" id="sc-bc-month-link" href="#">
                    <div class="card card-custom">
                        <div class="card-header border-0 summary-card-header" style="background-color:#A7C7E730;">
                            <h3>BC Created (Month)</h3>
                            <i id="pop-bc-month" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>Formula:</strong> Total count of confirmations credited to you this month.<br><br><strong>Counted when you hold the credited sales slot for the BC:</strong><ul><li>BCs created before 1 Jun 2026: you are the primary sales person (TC1)</li><li>BCs created from 1 Jun 2026: you are the secondary sales person (TC2)</li><li>It's a booking confirmation (not a quotation)</li><li>Not cancelled, not draft</li><li>Created date is in this month</li></ul>"></i>
                        </div>
                        <div class="card-body summary-card-body">
                            <div class="summary-value" id="sc-bc-month-count">...</div>
                            <div class="summary-sub">Booking confirmations you created this month. Click to view them in the list below.</div>
                            <div class="summary-sub summary-best" id="sc-bc-month-best">Best: —</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#C4B45420;">
                        <h3>Total Sales (Month) vs Target</h3>
                        <i id="pop-sales-month" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>Numerator:</strong> Sum of NetTotal across your fully-paid BCs created this month.<br><br><strong>Fully paid</strong> = approved customer payments (Status Y, excluding agent commission) &ge; NetTotal. Partial / deposit-only / unpaid BCs are not counted.<br><br><strong>Same TC1/TC2 credit rule as BC Created (Month).</strong><br><br><strong>Target:</strong> Set per TC per month under Admin &rarr; Sales Targets. Percent = actual &divide; target &times; 100. Week pace compares this week's fully-paid sales against the expected revenue by today if you stayed on target."></i>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-value-sm" id="sc-sales-month-value">...</div>
                        <div class="summary-sub">
                            Target: <span id="sc-sales-month-target">—</span> ·
                            <span id="sc-sales-month-percent" style="color:#6082B6;font-weight:600;">—</span>
                        </div>
                        <div class="summary-sub">Week pace: <span id="sc-sales-week-pace">—</span> (<span id="sc-sales-week-value">RM 0.00</span> this week)</div>
                        <div class="summary-sub" id="sc-sales-month-empty-target" style="color:#7E8299;display:none;">
                            Ask your team lead to set a monthly target.
                        </div>
                        <div class="summary-sub summary-best" id="sc-sales-month-best">Best: —</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <a class="summary-card" id="sc-cancel-rate-link" href="#">
                    <div class="card card-custom">
                        <div class="card-header border-0 summary-card-header" style="background-color:#FFFAA030;">
                            <h3>Cancellation Rate (Month)</h3>
                            <i id="pop-cancel-rate" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>Formula:</strong> Cancelled &divide; Total &times; 100<br><br><strong>Top number:</strong> BCs created this month that were cancelled.<br><strong>Bottom number:</strong> All BCs created this month (cancelled ones included).<br><br><strong>Filters:</strong> Drafts excluded. TC view counts only your BCs; team view counts everyone's.<br><br><strong>Example:</strong> 20 BCs, 5 cancelled &rarr; 25%.<br><br><strong>Note:</strong> Based on creation date, not cancellation date. A BC cancelled this month but created last month is NOT counted here."></i>
                        </div>
                        <div class="card-body summary-card-body">
                            <div class="summary-value" id="sc-cancel-rate-value">...</div>
                            <div class="summary-sub"><span id="sc-cancel-rate-detail">—</span> of your BCs created this month were cancelled. Click to view the cancelled list.</div>
                            <div class="summary-sub summary-best" id="sc-cancel-rate-best">Best: —</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a class="summary-card" id="sc-payment-overdue-link" href="#">
                    <div class="card card-custom">
                        <div class="card-header border-0 summary-card-header" style="background-color:#FAA0A030;">
                            <h3>Payment Overdue</h3>
                            <i id="pop-payment-overdue" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>Counted when EITHER:</strong><ul><li>Full payment deadline has passed AND the BC is still waiting for full payment (deposit unpaid, or deposit paid but balance still owing)</li><li>Deposit deadline has passed AND the deposit is still unpaid</li></ul><strong>Filters:</strong> Your BCs only; not cancelled.<br><br><strong>Excludes:</strong> BCs already fully paid.<br><br><strong>No period filter</strong> — checks each BC's own deadlines against today."></i>
                        </div>
                        <div class="card-body summary-card-body">
                            <div class="summary-value" id="sc-payment-overdue-count">...</div>
                            <div class="summary-sub">Your BCs whose payment deadline has passed with balance still outstanding. Click to view and follow up.</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a class="summary-card" id="sc-upcoming-not-ready-link" href="#">
                    <div class="card card-custom">
                        <div class="card-header border-0 summary-card-header" style="background-color:#FFFAA030;">
                            <h3>Travel in 7 Days – Not Yet Ready</h3>
                            <i id="pop-upcoming-not-ready" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>&quot;Not yet ready&quot; means the BC is still waiting on:</strong><ul><li>Payment</li><li>Booking operations</li><li>Guest list submission</li><li>Travel voucher</li></ul>&quot;Ready&quot; means the BC has moved to Pending Travel (or beyond).<br><br><strong>Filters:</strong><ul><li>Travel start date between <strong>tomorrow</strong> and today + 7 days</li><li>Still at one of the upstream stages above</li><li>Not cancelled</li></ul>TC view shows your BCs only; OP/Owner view is team-wide.<br><br><strong>Why it matters:</strong> Urgent — guests travel within a week."></i>
                        </div>
                        <div class="card-body summary-card-body">
                            <div class="summary-value" id="sc-upcoming-not-ready-count">...</div>
                            <div class="summary-sub">Your BCs starting travel within 7 days that are still upstream (Payment / Booking Op / Guest List / Travel Voucher) and not yet flagged "Pending Travel". Click to chase readiness.</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#F0FFFF;">
                        <h3>Conversion Rate (Month)</h3>
                        <i id="pop-conv-rate" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>Formula:</strong> Converted &divide; Total Leads &times; 100<br><br><strong>Counted as your conversion when:</strong><ul><li>The lead is linked to a BC AND you hold the credited TC slot for that BC (TC1 before 1 Jun 2026; TC2 from 1 Jun 2026)</li></ul><strong>Scope:</strong> Leads assigned to you this month (synced from GHL).<br><br><strong>Best:</strong> Top agent across the whole team this month. Agents with fewer than 3 leads are excluded so the bar stays meaningful."></i>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-value" id="sc-conv-rate-value">...</div>
                        <div class="summary-sub"><span id="sc-conv-rate-detail">—</span> of your leads this month converted to a BC where you hold sales credit.</div>
                        <div class="summary-sub summary-best" id="sc-conv-rate-best">Best: —</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#A7C7E730;">
                        <h3>My Leads</h3>
                        <i id="pop-tc-leads" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>From:</strong> GHL leads assigned to you (matched by your admin email &rarr; GHL user).<br><br><strong>Counts by lead creation date:</strong><ul><li><strong>Today:</strong> leads created today</li><li><strong>Week:</strong> Mon &rarr; Sun of this week</li><li><strong>Month:</strong> 1st &rarr; last day of this month</li></ul><strong>Note:</strong> If your admin email isn't linked to a GHL user, this card shows zeros."></i>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-row-3">
                            <div><div class="lbl">Today</div><div class="summary-value-sm" id="sc-tc-leads-day">...</div></div>
                            <div><div class="lbl">Week</div><div class="summary-value-sm" id="sc-tc-leads-week">...</div></div>
                            <div><div class="lbl">Month</div><div class="summary-value-sm" id="sc-tc-leads-month">...</div></div>
                        </div>
                        <div class="summary-sub">New leads assigned to you, by lead creation date.</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#F0FFFF;">
                        <h3>My Response Time</h3>
                        <i id="pop-tc-resp" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>From:</strong> GHL leads assigned to you.<br><br><strong>Avg first reply</strong> = mean time across the first 5 replies on each lead, formatted as seconds / minutes / hours.<br><br><strong>Duty-hours only:</strong> Replies sent outside duty hours (Mon&ndash;Sat 09:00&ndash;18:00 MYT) are excluded from the average so after-hours replies don't skew the number.<br><br><strong>Per window (by lead creation date):</strong><ul><li><strong>Today</strong></li><li><strong>Week</strong> (Mon &rarr; Sun)</li><li><strong>Month</strong> (1st &rarr; last)</li></ul><strong>Empty (&mdash;)</strong> when there were no responded leads in that window."></i>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-row-3">
                            <div><div class="lbl">Today</div><div class="summary-value-sm" id="sc-tc-resp-day">...</div></div>
                            <div><div class="lbl">Week</div><div class="summary-value-sm" id="sc-tc-resp-week">...</div></div>
                            <div><div class="lbl">Month</div><div class="summary-value-sm" id="sc-tc-resp-month">...</div></div>
                        </div>
                        <div class="summary-sub">Avg time to first reply across your leads in each window.</div>
                    </div>
                </div>
            </div>
        <?php } ?>

        <?php /* ---------- TC LEAD / Owner ---------- */ ?>
        <?php if($show_tclead || $show_owner) { ?>
            <div class="col-md-4">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#A7C7E730;">
                        <h3>Leads</h3>
                        <i id="pop-leads-dwm" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>From:</strong> Lead conversations synced from GHL.<br><br><strong>Counts by creation date:</strong><ul><li><strong>Today:</strong> leads created today</li><li><strong>Week:</strong> leads created Monday &rarr; Sunday of this week</li><li><strong>Month:</strong> leads created 1st &rarr; last day of this month</li></ul>Each conversation counts as one lead — re-entries to the same conversation don't double-count."></i>
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
            <div class="col-md-4">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#FFFAA030;">
                        <h3>Active Leads</h3>
                        <i id="pop-active-leads" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>From:</strong> GHL leads not yet converted to a BC (<code>is_converted = 0</code>).<br><br><strong>Counts by lead creation date:</strong><ul><li><strong>Today:</strong> still-open leads that started today</li><li><strong>Week:</strong> still-open leads from Mon &rarr; Sun</li><li><strong>Month:</strong> still-open leads from 1st &rarr; end</li></ul><strong>Pair with:</strong> &quot;Leads&quot; total to see open vs converted at a glance."></i>
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
                        <i id="pop-leads-conv" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>Conversion %</strong> = Converted &divide; Total &times; 100<br>A lead is converted when it's linked to a BC AND the TC has sales credit on that BC (TC1 credit before 1 Jun 2026; TC2 credit from 1 Jun 2026 onward).<br><br><strong>Response %</strong> = Leads with at least one TC reply &divide; Total &times; 100.<br><br><strong>Avg Time</strong> = Average time-to-respond across the TC's first 5 replies on each lead. Shown as seconds / minutes / hours.<br><br><strong>Scope:</strong> All leads created this month, all agents."></i>
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
                            <i id="pop-bc-week-month-tl" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>Formula:</strong> Total count of booking confirmations across all sales agents.<br><br><strong>Filters:</strong><ul><li>Booking confirmations only (not quotations)</li><li>Not cancelled, not draft</li></ul><strong>Periods (by creation date):</strong><ul><li><strong>Week:</strong> Monday &rarr; Sunday of this week</li><li><strong>Month:</strong> 1st &rarr; last day of this month</li></ul>"></i>
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
                            <i id="pop-cancel-rate-tl" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>Formula:</strong> Cancelled &divide; Total &times; 100<br><br><strong>Top number:</strong> BCs created this month that were cancelled.<br><strong>Bottom number:</strong> All BCs created this month (cancelled ones included).<br><br><strong>Filters:</strong> Drafts excluded. TC view counts only your BCs; team view counts everyone's.<br><br><strong>Example:</strong> 20 BCs, 5 cancelled &rarr; 25%.<br><br><strong>Note:</strong> Based on creation date, not cancellation date. A BC cancelled this month but created last month is NOT counted here."></i>
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
                        <i id="pop-agent-conversion" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>Per agent:</strong><ul><li><strong>Leads:</strong> Total leads assigned this month</li><li><strong>Converted:</strong> Leads with a linked BC where the agent has sales credit (TC1 before 1 Jun 2026; TC2 from 1 Jun 2026)</li><li><strong>Rate:</strong> Converted &divide; Leads &times; 100</li></ul><strong>Sort:</strong> By total leads (highest first), then agent name. Top 10.<br><br><strong>Excludes:</strong> Unassigned leads."></i>
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

        <?php /* ---------- OP ---------- */ ?>
        <?php if($show_op) { ?>
            <?php if(!$show_tclead) { ?>
            <div class="col-md-3">
                <a class="summary-card" id="sc-bc-month-op-link" href="#">
                    <div class="card card-custom">
                        <div class="card-header border-0 summary-card-header" style="background-color:#A7C7E730;">
                            <h3>BC Created</h3>
                            <i id="pop-bc-week-month-op" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>Formula:</strong> Total count of booking confirmations across all sales agents.<br><br><strong>Filters:</strong><ul><li>Booking confirmations only (not quotations)</li><li>Not cancelled, not draft</li></ul><strong>Periods (by creation date):</strong><ul><li><strong>Week:</strong> Monday &rarr; Sunday of this week</li><li><strong>Month:</strong> 1st &rarr; last day of this month</li></ul>"></i>
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
                <a class="summary-card" id="sc-upcoming-not-ready-op-link" href="#">
                    <div class="card card-custom">
                        <div class="card-header border-0 summary-card-header" style="background-color:#FFFAA030;">
                            <h3>Travel in 7 Days – Not Yet Ready</h3>
                            <i id="pop-upcoming-not-ready-op" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>&quot;Not yet ready&quot; means the BC is still waiting on:</strong><ul><li>Payment</li><li>Booking operations</li><li>Guest list submission</li><li>Travel voucher</li></ul>&quot;Ready&quot; means the BC has moved to Pending Travel (or beyond).<br><br><strong>Filters:</strong><ul><li>Travel start date between <strong>tomorrow</strong> and today + 7 days</li><li>Still at one of the upstream stages above</li><li>Not cancelled</li></ul>TC view shows your BCs only; OP/Owner view is team-wide.<br><br><strong>Why it matters:</strong> Urgent — guests travel within a week."></i>
                        </div>
                        <div class="card-body summary-card-body">
                            <div class="summary-value" id="sc-upcoming-not-ready-op-count">...</div>
                            <div class="summary-sub">All BCs starting travel within 7 days still upstream (Payment / Booking Op / Guest List / Travel Voucher) and not yet flagged "Pending Travel". Click to chase team-wide readiness.</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#EDF4FC;">
                        <h3>Intake &rarr; BC Response Time (Month)</h3>
                        <i id="pop-intake-resp-month-op" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>Team-wide avg</strong> time between a customer hitting Submit on the intake form and the booking being advanced to <strong>Pending BC Confirmation</strong> by staff this month.<br><br><strong>Counted when:</strong><ul><li>Customer submitted this month</li><li>Booking has at least one <code>PCI &rarr; PBC</code> transition logged</li><li>Booking is not soft-deleted</li></ul><strong>Best:</strong> Fastest TC across the team. Agents with fewer than 2 intakes are excluded so a single fast booking doesn't crown someone."></i>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-value-sm" id="sc-intake-resp-month-value">...</div>
                        <div class="summary-sub"><span id="sc-intake-resp-month-count">—</span> intakes this month, team-wide.</div>
                        <div class="summary-sub summary-best" id="sc-intake-resp-month-best">Best: —</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <a class="summary-card" id="sc-gl-submitted-link" href="#">
                    <div class="card card-custom">
                        <div class="card-header border-0 summary-card-header" style="background-color:#F0FFFF;">
                            <h3>Guest List Submitted</h3>
                            <i id="pop-gl-submitted" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>Counted when:</strong><ul><li>Customer has submitted their guest list</li><li>OP has not yet locked it</li><li>It's a booking confirmation</li><li>Not cancelled, not draft</li></ul><strong>No date filter</strong> — this is a live work queue.<br><br><strong>Action:</strong> Review for completeness, then lock to finalize and stop further customer edits."></i>
                        </div>
                        <div class="card-body summary-card-body">
                            <div class="summary-value" id="sc-gl-submitted-count">...</div>
                            <div class="summary-sub">BCs where the guest list has been submitted but not yet locked. Click to review and lock.</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a class="summary-card" id="sc-insurance-pending-link" href="#">
                    <div class="card card-custom">
                        <div class="card-header border-0 summary-card-header" style="background-color:#FAA0A030;">
                            <h3>Pending Insurance Checklist</h3>
                            <i id="pop-insurance-pending" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>Counted when, for an active line item:</strong><ul><li>Product carries an Insurance package checklist</li><li>No completion record yet for that checklist on that line</li><li><code>booking_product.disable_checklist_payment_out = 0</code> (same rule the modal/filter uses)</li><li>BC, not cancelled, not draft</li></ul><strong>Live queue</strong> — no date filter. Click to filter the list to these BCs."></i>
                        </div>
                        <div class="card-body summary-card-body">
                            <div class="summary-value" id="sc-insurance-pending-count">...</div>
                            <div class="summary-sub">BCs whose insurance checklist is not yet ticked on at least one active line. Click to review and complete.</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-12">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#FFFAA030;">
                        <h3>Supplier Pay-out Due Soon</h3>
                        <i id="pop-supplier-due-soon" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>Counted when:</strong><ul><li>Payment-out (<code>Type LIKE 'SUPPLIER PAYMENT%'</code>)</li><li>Status pending (<code>Status = 'P'</code>)</li><li>Deadline within the next 3 days (tomorrow &rarr; today + 3)</li><li>Linked to a supplier</li></ul><strong>Card:</strong> headline shows upcoming payments and combined amount due. Table breaks down top 5 suppliers, earliest deadline first.<br><br><strong>Disjoint from Supplier Overdue:</strong> rows due today or earlier roll into that card.<br><strong>Excludes:</strong> Already paid (Y), deleted (N), customer payment-ins, agent-commission entries."></i>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="lbl">Upcoming Payments</div>
                                <div class="summary-value" id="sc-supplier-due-soon-count">...</div>
                                <div class="lbl mt-3">Total Amount Due</div>
                                <div class="summary-value-sm" id="sc-supplier-due-soon-total">...</div>
                                <div class="summary-sub mt-2">Supplier payouts with deadlines in the next 3 days. Settle these before they slip into Supplier Overdue.</div>
                            </div>
                            <div class="col-md-8">
                                <table class="table table-sm summary-table">
                                    <thead><tr><th>Supplier</th><th class="text-right">Upcoming</th><th class="text-right">Amount</th><th>Earliest Deadline</th></tr></thead>
                                    <tbody id="sc-supplier-due-soon-body"><tr><td colspan="4" class="text-center text-muted">Loading…</td></tr></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#A7C7E730;">
                        <h3>Top Destinations (Month)</h3>
                        <i id="pop-destination-sales-op" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>Per destination this month:</strong><ul><li><strong>BC:</strong> How many bookings</li><li><strong>Sales:</strong> Total sales</li></ul><strong>Sort (OP view):</strong> By booking count (highest first). Top 5.<br><br><strong>Filters:</strong> Booking confirmations only; not cancelled; not draft; created this month.<br><br><strong>Tip:</strong> Click a row to filter the booking list by destination."></i>
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
                        <i id="pop-product-sales" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>Per product (grouped by item code):</strong><ul><li><strong>Qty:</strong> Total quantity sold</li><li><strong>Sales:</strong> Total sales</li></ul><strong>Filters:</strong><ul><li>Booking confirmations only</li><li>Not cancelled, not draft</li><li>Active line items only</li><li>Booking created this month</li></ul><strong>Sort:</strong> By total sales (highest first). Top 5."></i>
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
                        <i id="pop-payin" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>Formula:</strong> Sum of approved incoming customer payments.<br><br><strong>Filters:</strong><ul><li>Payment is approved</li><li>Money in only (refunds and outgoing entries excluded)</li><li>Excludes agent commission received from suppliers</li></ul><strong>Periods (by payment date):</strong><ul><li><strong>Today:</strong> today only</li><li><strong>Week:</strong> Monday &rarr; Sunday of this week</li><li><strong>Month:</strong> 1st &rarr; last day of this month</li></ul>"></i>
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
                        <i id="pop-destination-sales-fin" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>Per destination this month:</strong><ul><li><strong>BC:</strong> How many bookings</li><li><strong>Sales:</strong> Total sales</li></ul><strong>Sort (Finance view):</strong> By total sales (highest first). Top 5.<br><br><strong>Filters:</strong> Booking confirmations only; not cancelled; not draft; created this month.<br><br><strong>Tip:</strong> Click a row to filter the booking list by destination."></i>
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
                        <i id="pop-product-sales" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>Per product (grouped by item code):</strong><ul><li><strong>Qty:</strong> Total quantity sold</li><li><strong>Sales:</strong> Total sales</li></ul><strong>Filters:</strong><ul><li>Booking confirmations only</li><li>Not cancelled, not draft</li><li>Active line items only</li><li>Booking created this month</li></ul><strong>Sort:</strong> By total sales (highest first). Top 5."></i>
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
                        <i id="pop-sales-by-team" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>Per team this month:</strong><ul><li><strong>BC:</strong> How many bookings credited to the team</li><li><strong>Sales:</strong> Sum of NetTotal</li></ul><strong>Grouping:</strong> SalesAgent &rarr; <code>admin.TeamLeadID</code> &rarr; team lead. Agents with no team lead collapse into a single &quot;Unassigned&quot; row so the breakdown reconciles to the team-wide total.<br><br><strong>Filters:</strong> BC only; not cancelled; not draft; created this month."></i>
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
                        <i id="pop-supplier-overdue" class="la la-info-circle summary-info-icon" data-toggle="popover" data-trigger="hover focus" data-placement="bottom" data-html="true" title="How this is calculated" data-content="<strong>Counted when:</strong><ul><li>Payment-out (<code>Type LIKE 'SUPPLIER PAYMENT%'</code>)</li><li>Status pending (<code>Status = 'P'</code>)</li><li>Deadline &lt; today</li><li>Linked to a supplier</li></ul><strong>Card:</strong> headline shows total overdue payments and combined amount due. Table breaks down top 5 suppliers by amount.<br><br><strong>Excludes:</strong> Already-paid (Y), deleted (N), customer payment-ins, agent-commission entries."></i>
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
    $('#booking_summary_cards [data-toggle="popover"]').popover({
        customClass: 'summary-popover',
        container: 'body',
        boundary: 'window'
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
    function setLink(id, href) {
        var el = document.getElementById(id);
        if(el && href) el.setAttribute('href', href);
    }
    function setBest(id, best) {
        var el = document.getElementById(id);
        if(!el) return;
        if(!best || best.name == null || best.value == null) {
            el.innerHTML = 'Best: —';
            return;
        }
        var isYou = (String(best.name) === 'You');
        el.innerHTML = 'Best: <span class="best-name' + (isYou ? ' is-you' : '') + '">'
            + escapeHtml(best.name) + '</span> · ' + escapeHtml(best.value);
    }
    // Keep clicks on the "Last synced" text from collapsing the panel.
    $('#booking_summary_cards .ghl-last-sync').on('click', function(e) {
        e.stopPropagation();
    });

    $.getJSON('<?php echo base_url("Booking/ajax_summary_cards"); ?>', function(resp) {
        if(!resp || resp.error) return;
        var c = resp.cards || {};
        var t = resp.tables || {};
        var m = resp.meta   || {};

        if(m.last_ghl_sync_display) {
            setText('ghl-last-sync', m.last_ghl_sync_display);
        }

        // TC cards
        if(c.bc_month) {
            setText('sc-bc-month-count', c.bc_month.count);
            setLink('sc-bc-month-link', c.bc_month.link);
            setBest('sc-bc-month-best', c.bc_month.best);
        }
        if(c.sales_month) {
            setText('sc-sales-month-value', c.sales_month.value);
            setText('sc-sales-month-target', c.sales_month.target);
            setText('sc-sales-month-percent', c.sales_month.percent);
            setBest('sc-sales-month-best', c.sales_month.best);
            var emptyEl = document.getElementById('sc-sales-month-empty-target');
            if(emptyEl) {
                emptyEl.style.display = c.sales_month.has_target ? 'none' : '';
            }
        }
        if(c.sales_week) {
            setText('sc-sales-week-value', c.sales_week.value);
            setText('sc-sales-week-pace',  c.sales_week.pace);
        }
        if(c.tc_leads_dwm) {
            setText('sc-tc-leads-day',   c.tc_leads_dwm.day);
            setText('sc-tc-leads-week',  c.tc_leads_dwm.week);
            setText('sc-tc-leads-month', c.tc_leads_dwm.month);
        }
        if(c.tc_response_time_dwm) {
            setText('sc-tc-resp-day',   c.tc_response_time_dwm.day);
            setText('sc-tc-resp-week',  c.tc_response_time_dwm.week);
            setText('sc-tc-resp-month', c.tc_response_time_dwm.month);
        }
        if(c.intake_response_month) {
            var ir = c.intake_response_month;
            // format_response_duration returns '-' for null; for the card we'd
            // rather render an em-dash when no intakes happened this month.
            var hasData = (ir.count > 0 && ir.value && ir.value !== '-');
            setText('sc-intake-resp-month-value', hasData ? ir.value : '—');
            setText('sc-intake-resp-month-count', ir.count);
            setBest('sc-intake-resp-month-best', ir.best);
        }
        if(c.cancellation_rate) {
            setText('sc-cancel-rate-value', c.cancellation_rate.value);
            setText('sc-cancel-rate-detail', c.cancellation_rate.detail);
            setLink('sc-cancel-rate-link', c.cancellation_rate.link);
            setBest('sc-cancel-rate-best', c.cancellation_rate.best);
            setText('sc-cancel-rate-tl-value', c.cancellation_rate.value);
            setText('sc-cancel-rate-tl-detail', c.cancellation_rate.detail);
            setLink('sc-cancel-rate-tl-link', c.cancellation_rate.link);
        }
        if(c.conversion_rate_month) {
            setText('sc-conv-rate-value',  c.conversion_rate_month.value);
            setText('sc-conv-rate-detail', c.conversion_rate_month.detail);
            setBest('sc-conv-rate-best',   c.conversion_rate_month.best);
        }
        if(c.payment_overdue) {
            setText('sc-payment-overdue-count', c.payment_overdue.count);
            setLink('sc-payment-overdue-link', c.payment_overdue.link);
        }
        if(c.upcoming_travel_not_ready) {
            setText('sc-upcoming-not-ready-count', c.upcoming_travel_not_ready.count);
            setLink('sc-upcoming-not-ready-link',  c.upcoming_travel_not_ready.link);
        }
        if(c.upcoming_travel_not_ready_op) {
            setText('sc-upcoming-not-ready-op-count', c.upcoming_travel_not_ready_op.count);
            setLink('sc-upcoming-not-ready-op-link',  c.upcoming_travel_not_ready_op.link);
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
        if(c.supplier_due_soon) {
            setText('sc-supplier-due-soon-count', c.supplier_due_soon.count);
            setText('sc-supplier-due-soon-total', c.supplier_due_soon.total_due);
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
                dueBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No supplier payouts due in the next 3 days</td></tr>';
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
                $el.popover('dispose').popover({
                    customClass: 'summary-popover',
                    container: 'body',
                    boundary: 'window'
                });
            });
        }
    });
})();
</script>
<?php } ?>
