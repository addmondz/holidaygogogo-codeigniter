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
    #booking_summary_cards .summary-card-header { min-height:38px; padding:8px 14px; }
    #booking_summary_cards .summary-card-header h3 { margin:0; font-size:13px; color:#3F4254; }
    #booking_summary_cards .summary-card-body { padding:10px 14px 12px; }
    #booking_summary_cards .summary-value { font-size:24px; font-weight:700; color:#3F4254; line-height:1.1; }
    #booking_summary_cards .summary-value-sm { font-size:18px; font-weight:700; color:#3F4254; line-height:1.1; }
    #booking_summary_cards .summary-sub { font-size:11px; color:#7E8299; font-weight:500; margin-top:4px; }
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
</style>
<div id="booking_summary_cards" class="mb-4">
    <div class="panel-toggle" data-toggle="collapse" data-target="#booking_summary_cards_body" aria-expanded="true" aria-controls="booking_summary_cards_body">
        <span class="panel-title">Summary</span>
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
                        </div>
                        <div class="card-body summary-card-body">
                            <div class="summary-value" id="sc-bc-month-count">...</div>
                            <div class="summary-sub">Booking confirmations you created this month. Click to view them in the list below.</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#C4B45420;">
                        <h3>Total Sales (Month)</h3>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-value-sm" id="sc-sales-month-value">...</div>
                        <div class="summary-sub">Sum of NetTotal across BCs you created this month (excludes cancelled).</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <a class="summary-card" id="sc-cancel-rate-link" href="#">
                    <div class="card card-custom">
                        <div class="card-header border-0 summary-card-header" style="background-color:#FFFAA030;">
                            <h3>Cancellation Rate (Month)</h3>
                        </div>
                        <div class="card-body summary-card-body">
                            <div class="summary-value" id="sc-cancel-rate-value">...</div>
                            <div class="summary-sub"><span id="sc-cancel-rate-detail">—</span> of your BCs created this month were cancelled. Click to view the cancelled list.</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a class="summary-card" id="sc-payment-overdue-link" href="#">
                    <div class="card card-custom">
                        <div class="card-header border-0 summary-card-header" style="background-color:#FAA0A030;">
                            <h3>Payment Overdue</h3>
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
                        </div>
                        <div class="card-body summary-card-body">
                            <div class="summary-value" id="sc-upcoming-not-ready-count">...</div>
                            <div class="summary-sub">Your BCs starting travel within 7 days that are still upstream (Payment / Booking Op / Guest List / Travel Voucher) and not yet flagged "Pending Travel". Click to chase readiness.</div>
                        </div>
                    </div>
                </a>
            </div>
        <?php } ?>

        <?php /* ---------- TC LEAD / Owner ---------- */ ?>
        <?php if($show_tclead || $show_owner) { ?>
            <div class="col-md-3">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#A7C7E730;">
                        <h3>Leads</h3>
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
            <div class="col-md-3">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#F0FFFF;">
                        <h3>Conversion & Response (Month)</h3>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-row-3">
                            <div><div class="lbl">Conv.</div><div class="summary-value-sm" id="sc-leads-conv">...</div></div>
                            <div><div class="lbl">Resp.</div><div class="summary-value-sm" id="sc-leads-resp">...</div></div>
                            <div><div class="lbl">Avg Time</div><div class="summary-value-sm" id="sc-leads-time">...</div></div>
                        </div>
                        <div class="summary-sub">Lead-to-booking conversion %, response %, and average first-response time across this month's leads.</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <a class="summary-card" id="sc-bc-month-tl-link" href="#">
                    <div class="card card-custom">
                        <div class="card-header border-0 summary-card-header" style="background-color:#A7C7E730;">
                            <h3>BC Created</h3>
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
            <div class="col-md-3">
                <a class="summary-card" id="sc-cancel-rate-tl-link" href="#">
                    <div class="card card-custom">
                        <div class="card-header border-0 summary-card-header" style="background-color:#FFFAA030;">
                            <h3>Cancellation Rate (Month)</h3>
                        </div>
                        <div class="card-body summary-card-body">
                            <div class="summary-value" id="sc-cancel-rate-tl-value">...</div>
                            <div class="summary-sub"><span id="sc-cancel-rate-tl-detail">—</span> of team BCs created this month were cancelled. Click to view the cancelled list.</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-6">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#A7C7E730;">
                        <h3>Top Agents – Conversion (Month)</h3>
                    </div>
                    <div class="card-body summary-card-body">
                        <div class="summary-sub mb-2">Top 10 agents this month ranked by lead volume, with their conversion rate to booking.</div>
                        <table class="table table-sm summary-table">
                            <thead><tr><th>Agent</th><th class="text-right">Leads</th><th class="text-right">Converted</th><th class="text-right">Rate</th></tr></thead>
                            <tbody id="sc-agent-conversion-body"><tr><td colspan="4" class="text-center text-muted">Loading…</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php } ?>

        <?php /* ---------- OP / Owner ---------- */ ?>
        <?php if($show_op || $show_owner) { ?>
            <?php if(!$show_tclead && !$show_owner) { ?>
            <div class="col-md-3">
                <a class="summary-card" id="sc-bc-month-op-link" href="#">
                    <div class="card card-custom">
                        <div class="card-header border-0 summary-card-header" style="background-color:#A7C7E730;">
                            <h3>BC Created</h3>
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
                        </div>
                        <div class="card-body summary-card-body">
                            <div class="summary-value" id="sc-upcoming-not-ready-op-count">...</div>
                            <div class="summary-sub">All BCs starting travel within 7 days still upstream (Payment / Booking Op / Guest List / Travel Voucher) and not yet flagged "Pending Travel". Click to chase team-wide readiness.</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a class="summary-card" id="sc-gl-submitted-link" href="#">
                    <div class="card card-custom">
                        <div class="card-header border-0 summary-card-header" style="background-color:#F0FFFF;">
                            <h3>Guest List Submitted</h3>
                        </div>
                        <div class="card-body summary-card-body">
                            <div class="summary-value" id="sc-gl-submitted-count">...</div>
                            <div class="summary-sub">BCs where the guest list has been submitted but not yet locked. Click to review and lock.</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-6">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#A7C7E730;">
                        <h3>Top Destinations (Month)</h3>
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

        <?php /* ---------- Finance / Owner ---------- */ ?>
        <?php if($show_finance || $show_owner) { ?>
            <div class="col-md-6">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#C4B45420;">
                        <h3>Total Payment In</h3>
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
            <?php if(!$show_op && !$show_owner) { ?>
            <div class="col-md-6">
                <div class="card card-custom">
                    <div class="card-header border-0 summary-card-header" style="background-color:#A7C7E730;">
                        <h3>Top Destinations (Month)</h3>
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
    $.getJSON('<?php echo base_url("Booking/ajax_summary_cards"); ?>', function(resp) {
        if(!resp || resp.error) return;
        var c = resp.cards || {};
        var t = resp.tables || {};

        // TC cards
        if(c.bc_month) {
            setText('sc-bc-month-count', c.bc_month.count);
            setLink('sc-bc-month-link', c.bc_month.link);
        }
        if(c.sales_month) {
            setText('sc-sales-month-value', c.sales_month.value);
        }
        if(c.cancellation_rate) {
            setText('sc-cancel-rate-value', c.cancellation_rate.value);
            setText('sc-cancel-rate-detail', c.cancellation_rate.detail);
            setLink('sc-cancel-rate-link', c.cancellation_rate.link);
            setText('sc-cancel-rate-tl-value', c.cancellation_rate.value);
            setText('sc-cancel-rate-tl-detail', c.cancellation_rate.detail);
            setLink('sc-cancel-rate-tl-link', c.cancellation_rate.link);
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

        // OP
        if(c.gl_submitted) {
            setText('sc-gl-submitted-count', c.gl_submitted.count);
            setLink('sc-gl-submitted-link', c.gl_submitted.link);
        }

        // Finance
        if(c.payment_in_dwm) {
            setText('sc-payin-day',   c.payment_in_dwm.day);
            setText('sc-payin-week',  c.payment_in_dwm.week);
            setText('sc-payin-month', c.payment_in_dwm.month);
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
    });
})();
</script>
<?php } ?>
