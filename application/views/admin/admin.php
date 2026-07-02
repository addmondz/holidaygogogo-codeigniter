<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong><?php if($Action == 'C') { echo 'New Admin Record'; } else { echo 'Admin Record : ' . $Name; } ?></strong>
                    </h3>
                </div>
            </div>
            <div class="card-body">
                <form id="form">
                    <strong>Admin Information :</strong>
                    <br><br>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Name
                                    <span style="color:red;">*</span>
                                </label>
                                <div class="input-icon">
                                    <input type="text" id="Name" value="<?php if($Action == 'U') { echo $Name; } else { echo ''; } ?>" autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-user"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Gender
                                    <?php if($Action == 'C') { ?><span style="color:red;">*</span><?php } ?>
                                </label>
                                <select id="Gender" class="form-control selectpicker">
                                    <option selected disabled data-icon="la la-mercury font-size-lg bs-icon" value="">--SELECT GENDER--</option>
                                    <?php foreach(unserialize(GENDER) as $key => $value) { ?>
                                        <option <?php if($Action == 'U' && $key == $Gender) { echo 'selected'; } ?> data-icon="<?php if($key == 'F') { echo 'la la-female'; } else { echo 'la la-male'; } ?> font-size-lg bs-icon" value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Identification Number</label>
                                <div class="input-icon">
                                    <input type="text" id="IdentificationNumber" value="<?php if($Action == 'U') { echo $IdentificationNumber; } else { echo ''; } ?>" autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-id-card"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Passport Number</label>
                                <div class="input-icon">
                                    <input type="text" id="PassportNumber" value="<?php if($Action == 'U') { echo $PassportNumber; } else { echo ''; } ?>" autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-passport"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Country Code
                                    <span style="color:red;">*</span>
                                </label>
                                <select id="CountryCodeID" <?php if(!empty($country_codes)) { echo 'data-live-search="true"'; } ?> class="form-control selectpicker">
                                    <option selected disabled data-icon="la la-phone font-size-lg bs-icon" value="">--SELECT COUNTRY CODE--</option>
                                    <?php if(!empty($country_codes)) {
                                        foreach($country_codes as $country_code) { ?>
                                            <option <?php if($Action == 'U' && $country_code->CountryCodeID == $CountryCodeID) { echo 'selected'; } ?> data-icon="la la-phone font-size-lg bs-icon" value="<?php echo $country_code->CountryCodeID; ?>"><?php echo $country_code->Country . ' ' . $country_code->CountryCode; ?></option>
                                        <?php }
                                    } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Mobile
                                    <span style="color:red;">*</span>
                                </label>
                                <div class="input-icon">
                                    <input type="text" id="Mobile" value="<?php if($Action == 'U') { echo $Mobile; } else { echo ''; } ?>" autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-mobile"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Email
                                    <span style="color:red;">*</span>
                                </label>
                                <div class="input-icon">
                                    <input type="email" id="Email" value="<?php if($Action == 'U') { echo $Email; } else { echo ''; } ?>" autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-envelope"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between border-top pt-5"></div>
                    <strong>Account Setting :</strong>
                    <br><br>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Username
                                    <?php if($Action == 'C') { ?><span style="color:red;">*</span><?php } ?>
                                </label>
                                <div class="input-icon">
                                    <input <?php if($Action == 'U') { echo 'disabled'; } ?> type="text" id="Username" value="<?php if($Action == 'U') { echo $Username; } else { echo ''; } ?>" autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-user"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Password
                                    <?php if($Action == 'C') { ?><span style="color:red;">*</span><?php } ?>
                                </label>
                                <div class="input-icon">
                                    <input type="password" id="Password" autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-key"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Level
                                    <?php if($Action == 'C') { ?><span style="color:red;">*</span><?php } ?>
                                </label>
                                <select id="Level" class="form-control selectpicker">
                                    <option selected disabled data-icon="la la-key font-size-lg bs-icon" value="">--SELECT LEVEL--</option>
                                    <?php foreach(unserialize(LEVEL) as $key => $value) { ?>
                                        <?php if($this->session->level == 30 && $key == 10) { continue; } ?>
                                        <?php if($key == 50) { continue; } ?>
                                        <option <?php if($Action == 'U' && $key == $Level) { echo 'selected'; } ?> data-icon="<?php if($key == 20) { echo 'la la-user-alt'; } else if($key == 30) { echo 'la la-hand-holding-usd'; } else { echo 'la la-user-tie'; } ?> font-size-lg bs-icon" value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Access Control
                                    <span style="color:red;">*</span>
                                    <a onclick="Reset_Access_Control()" class="btn btn-icon btn-light-warning btn-xs">
                                        <i class="la la-undo"></i>
                                    </a>
                                </label>
                                <select title="--SELECT ACCESS CONTROL--" id="AccessControl" multiple class="form-control selectpicker">
                                    <?php foreach(unserialize(ACCESS_CONTROL) as $key => $value) { ?>
                                        <?php if($key == 'GB') { ?><optgroup label="BOOKING MODULE"><?php } ?>
                                        <?php if($key == 'GP') { ?><optgroup label="PAYMENT MODULE"><?php } ?>
                                        <?php if($key == 'VR') { ?><optgroup label="REPORT MODULE"><?php } ?>
                                        <?php if($key == 'FV') { ?><optgroup label="FAQ MODULE"><?php } ?>
                                        <?php if($key == 'VPR') { ?><optgroup label="PERMISSION"><?php } ?>
                                        <option <?php if($Action == 'U' && in_array($key, $AccessControl)) { echo 'selected'; } ?> data-icon="la la-check-circle font-size-lg bs-icon" value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Team Lead</label>
                                <select id="TeamLeadID" class="form-control selectpicker">
                                    <option selected data-icon="la la-users font-size-lg bs-icon" value="">--SELECT TEAM LEAD--</option>
                                    <?php if(!empty($team_leads)) {
                                        foreach($team_leads as $team_lead) { ?>
                                            <option <?php if($Action == 'U' && $TeamLeadID == $team_lead->AdminID) { echo 'selected'; } ?> data-icon="la la-user-tie font-size-lg bs-icon" value="<?php echo $team_lead->AdminID; ?>"><?php echo $team_lead->Name; ?></option>
                                        <?php }
                                    } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>OP Team Lead
                                    <small class="text-muted d-block">The OP TEAM LEAD who may tick this OP's booking checklists. Used for OP-side staff.</small>
                                </label>
                                <select id="OpTeamLeadID" class="form-control selectpicker">
                                    <option selected data-icon="la la-users font-size-lg bs-icon" value="">--SELECT OP TEAM LEAD--</option>
                                    <?php if(!empty($op_team_leads)) {
                                        foreach($op_team_leads as $op_team_lead) { ?>
                                            <option <?php if($Action == 'U' && $OpTeamLeadID == $op_team_lead->AdminID) { echo 'selected'; } ?> data-icon="la la-user-tie font-size-lg bs-icon" value="<?php echo $op_team_lead->AdminID; ?>"><?php echo $op_team_lead->Name; ?></option>
                                        <?php }
                                    } ?>
                                </select>
                            </div>
                        </div>
<?php /* Team function DISABLED — Team assignment field hidden
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Team
                                    <small class="text-muted d-block">The Team this admin belongs to. A TEAM LEAD / OP TEAM LEAD sees all their team members' bookings &amp; payments; Sales Agent / OP see only their own.</small>
                                </label>
                                <select id="TeamID" class="form-control selectpicker">
                                    <option selected data-icon="la la-users font-size-lg bs-icon" value="">--SELECT TEAM--</option>
                                    <?php if(!empty($teams)) {
                                        foreach($teams as $team) { ?>
                                            <option <?php if($Action == 'U' && $TeamID == $team->TeamID) { echo 'selected'; } ?> data-icon="la la-users font-size-lg bs-icon" value="<?php echo $team->TeamID; ?>"><?php echo $team->Name; ?></option>
                                        <?php }
                                    } ?>
                                </select>
                            </div>
                        </div>
*/ ?>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Lead Dashboard Agents
                                    <small class="text-muted d-block">Restricts which GHL agents this user can see on Lead Dashboard / Lead Data. Leave empty to block all leads (OWNER bypasses).</small>
                                </label>
                                <select id="LeadDashboardAgents" multiple data-live-search="true" data-actions-box="true" title="--SELECT GHL AGENTS--" class="form-control selectpicker">
                                    <?php if(!empty($ghl_users)) {
                                        foreach($ghl_users as $ghl_user) { ?>
                                            <option <?php if(in_array($ghl_user->UserID, $lead_dashboard_agents)) { echo 'selected'; } ?> data-icon="la la-headset font-size-lg bs-icon" value="<?php echo html_escape($ghl_user->UserID); ?>"><?php echo html_escape($ghl_user->Name . (!empty($ghl_user->Email) ? ' (' . $ghl_user->Email . ')' : '')); ?></option>
                                        <?php }
                                    } ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <?php
                        $st_current_year  = (int) date('Y');
                        $st_current_month = (int) date('n');
                        $st_year_options  = range($st_current_year - 1, $st_current_year + 2);
                        $st_month_names   = array(1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec');
                        $st_show_on_load  = ($Action == 'U' && in_array((int)$Level, array(20, 50), true));
                    ?>
                    <div id="sales_target_section" style="<?php echo $st_show_on_load ? '' : 'display:none;'; ?>">
                        <div class="d-flex justify-content-between border-top pt-5"></div>
                        <strong>Sales Target :</strong>
                        <br>
                        <small class="text-muted d-block mb-3">
                            Monthly sales target for this TC. Used by the TC's Booking dashboard
                            "Month Sales vs Target" card. The dashboard reads whichever month
                            the TC selects in its month filter, so future months are live once
                            that month is chosen.
                        </small>
                        <?php if($Action == 'C') { ?>
                            <div class="alert alert-light-warning" style="font-size:13px;">
                                <i class="la la-info-circle"></i>
                                Create the admin first, then re-open this record to set targets.
                            </div>
                        <?php } else { ?>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Year</label>
                                        <select id="StYear" class="form-control selectpicker">
                                            <?php foreach($st_year_options as $y) { ?>
                                                <option <?php if($y === $st_current_year) echo 'selected'; ?> value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Month</label>
                                        <select id="StMonth" class="form-control selectpicker">
                                            <?php foreach($st_month_names as $m_num => $m_name) { ?>
                                                <option <?php if($m_num === $st_current_month) echo 'selected'; ?> value="<?php echo $m_num; ?>"><?php echo $m_name; ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Target Amount (RM)</label>
                                        <div class="input-icon">
                                            <input type="number" min="0" step="0.01" id="StAmount" class="form-control" value="0.00" autocomplete="off">
                                            <span><i class="la la-bullseye"></i></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <small class="text-muted">
                                <span id="StPeriodLabel"></span>
                                <span id="StSavedHint" style="color:#6082B6;font-weight:600;display:none;">&nbsp;&middot;&nbsp;Will save with Update Admin</span>
                            </small>
                            <div class="row mt-4 pt-4" style="border-top:1px dashed #EBEDF3;">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Yearly Target (RM) <span id="StYearLabel" style="font-weight:400;color:#7E8299;"></span></label>
                                        <div class="input-icon">
                                            <input type="number" min="0" step="0.01" id="StYearAmount" class="form-control" value="0.00" autocomplete="off">
                                            <span><i class="la la-calendar-check-o"></i></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 d-flex align-items-center">
                                    <small class="text-muted">
                                        Annual target for this TC, following the Year selector above.
                                        Read by the Booking dashboard "Year Sales vs Target" card.
                                        <span id="StYearSavedHint" style="color:#6082B6;font-weight:600;display:none;">&nbsp;&middot;&nbsp;Will save with Update Admin</span>
                                    </small>
                                </div>
                            </div>
                            <div class="row mt-4">
                                <div class="col-md-12">
                                    <label style="font-weight:600;color:#3F4254;">Currently saved targets</label>
                                    <div class="table-responsive">
                                        <table class="table table-sm" id="StSavedTable" style="margin-bottom:0;font-size:13px;">
                                            <thead>
                                                <tr style="background:#F3F6F9;">
                                                    <th>Period</th>
                                                    <th class="text-right">Amount</th>
                                                    <th style="width:60px;"></th>
                                                </tr>
                                            </thead>
                                            <tbody id="StSavedTableBody">
                                                <tr><td colspan="3" class="text-muted text-center" style="padding:12px;">Loading&hellip;</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <small class="text-muted">
                                        Rows with <span style="color:#6082B6;font-weight:600;">*</span> are unsaved &mdash; they'll persist when you click <strong>Update Admin</strong>.
                                        Click a row to jump back to that period.
                                    </small>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                    <div class="d-flex justify-content-between border-top pt-5">
                        <input type="button" value="<?php if($Action == 'C') { echo 'Create Admin'; } else { echo 'Update Admin'; } ?>" class="btn btn-success font-weight-bold px-9 py-4" style="width:180px; margin-left:auto;">
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    var background = '<?php echo base_url('assets/image/sweetalert.jpg') ?>';
    var action = '<?php echo $Action ?>';
    var session_id = <?php echo $this->session->admin_id ?>;
    var current_datetime = '<?php echo date('Y-m-d H:i:s') ?>';
    var success_message = action == 'C' ? 'New Admin Record Successfully Created' : '<?php echo 'Admin Record : ' . $Name . ' Successfully Updated'; ?>';
    var error_message = action == 'C' ? 'New Admin Record Could Not Be Created' : '<?php echo 'Update Admin Record : ' . $Name . ' Could Not Be Updated'; ?>';

    function Reset_Access_Control() {
        $('#AccessControl').val('').change();
    }

    // ---- Sales Target editing (Update only, level 20/50 only) ----
    // Server pre-loaded as { "YYYY-MM": amount } (see Admin_Model->Read_Sales_Targets_For_Admin).
    var stInitial = <?php echo json_encode(!empty($sales_targets) ? $sales_targets : new stdClass()); ?>;
    var stTargets = $.extend({}, stInitial);

    // ---- Yearly target editing — server pre-loaded as { "YYYY": amount }
    // (see Admin_Model->Read_Year_Sales_Targets_For_Admin). Follows #StYear.
    var styInitial = <?php echo json_encode(!empty($year_sales_targets) ? $year_sales_targets : new stdClass()); ?>;
    var styTargets = $.extend({}, styInitial);

    function sty_current_year() {
        return String($('#StYear').val());
    }
    function sty_load_into_input() {
        var y = sty_current_year();
        var amt = (y in styTargets) ? styTargets[y] : 0;
        $('#StYearAmount').val(parseFloat(amt).toFixed(2));
        $('#StYearLabel').text('· ' + y);
    }
    function sty_capture_input_into_dict() {
        var y = sty_current_year();
        var v = parseFloat($('#StYearAmount').val());
        styTargets[y] = isFinite(v) && v >= 0 ? v : 0;
    }
    function sty_is_dirty() {
        var seen = {};
        for(var k in styTargets) {
            seen[k] = true;
            var initial = (k in styInitial) ? parseFloat(styInitial[k]) : 0;
            if(parseFloat(styTargets[k]) !== initial) return true;
        }
        for(var k2 in styInitial) {
            if(!seen[k2] && parseFloat(styInitial[k2]) !== 0) return true;
        }
        return false;
    }
    function sty_update_hint() {
        $('#StYearSavedHint').toggle(sty_is_dirty());
    }

    function st_current_ym() {
        var y = $('#StYear').val();
        var m = parseInt($('#StMonth').val(), 10);
        return y + '-' + (m < 10 ? '0' + m : '' + m);
    }
    function st_load_period_into_input() {
        var ym = st_current_ym();
        var amt = (ym in stTargets) ? stTargets[ym] : 0;
        $('#StAmount').val(parseFloat(amt).toFixed(2));
        var d = new Date(parseInt(ym.slice(0,4),10), parseInt(ym.slice(5,7),10)-1, 1);
        var label = d.toLocaleString('en-US', { month: 'long', year: 'numeric' });
        $('#StPeriodLabel').text('Showing target for: ' + label);
    }
    function st_capture_input_into_dict() {
        var ym = st_current_ym();
        var v = parseFloat($('#StAmount').val());
        stTargets[ym] = isFinite(v) && v >= 0 ? v : 0;
    }
    function st_is_dirty() {
        // Compare key-by-key — any change (incl. value mutation, key add) flips dirty.
        var seen = {};
        for(var k in stTargets) {
            seen[k] = true;
            var initial = (k in stInitial) ? parseFloat(stInitial[k]) : 0;
            if(parseFloat(stTargets[k]) !== initial) return true;
        }
        for(var k2 in stInitial) {
            if(!seen[k2] && parseFloat(stInitial[k2]) !== 0) return true;
        }
        return false;
    }
    function st_visibility_check() {
        var lvl = parseInt($('#Level').val(), 10);
        var show = (action == 'U') && (lvl === 20 || lvl === 50);
        $('#sales_target_section').toggle(show);
    }
    function st_update_hint() {
        $('#StSavedHint').toggle(st_is_dirty());
    }
    var ST_MONTH_NAMES = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    function st_format_period(ym) {
        var y = ym.slice(0,4);
        var m = parseInt(ym.slice(5,7), 10);
        return ST_MONTH_NAMES[m-1] + ' ' + y;
    }
    function st_format_money(amt) {
        var n = parseFloat(amt);
        if(!isFinite(n)) return 'RM 0.00';
        return 'RM ' + n.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
    }
    function st_render_saved_panel() {
        var $body = $('#StSavedTableBody');
        if(!$body.length) return;
        var keys = [];
        for(var k in stTargets) {
            if(parseFloat(stTargets[k]) > 0) keys.push(k);
        }
        keys.sort();
        if(keys.length === 0) {
            $body.html('<tr><td colspan="3" class="text-muted text-center" style="padding:12px;">No targets set yet. Pick a month above and enter an amount.</td></tr>');
            return;
        }
        var current_ym = st_current_ym();
        var html = '';
        for(var i = 0; i < keys.length; i++) {
            var ym = keys[i];
            var amt = parseFloat(stTargets[ym]);
            var initial = (ym in stInitial) ? parseFloat(stInitial[ym]) : 0;
            var dirty = (amt !== initial);
            var highlight = (ym === current_ym) ? ' style="background:#EEF3FB;"' : '';
            html += '<tr class="st-saved-row" data-ym="' + ym + '"' + highlight + ' role="button">' +
                '<td>' + st_format_period(ym) + '</td>' +
                '<td class="text-right">' + st_format_money(amt) +
                    (dirty ? ' <span style="color:#6082B6;font-weight:600;" title="Unsaved">*</span>' : '') +
                '</td>' +
                '<td><i class="la la-edit text-muted"></i></td>' +
                '</tr>';
        }
        $body.html(html);
    }

    $(function() {
        if($('#StYear').length) {
            st_load_period_into_input();
            st_render_saved_panel();
            sty_load_into_input();
            $('#StYear, #StMonth').on('change', function() {
                st_capture_input_into_dict();
                st_load_period_into_input();
                st_update_hint();
                st_render_saved_panel();
            });
            // Yearly amount follows the Year selector only. Captured on input
            // (below) so switching years never loses the current edit.
            $('#StYear').on('change', sty_load_into_input);
            $('#StAmount').on('input change', function() {
                st_capture_input_into_dict();
                st_update_hint();
                st_render_saved_panel();
            });
            $('#StYearAmount').on('input change', function() {
                sty_capture_input_into_dict();
                sty_update_hint();
            });
            $('#StSavedTableBody').on('click', '.st-saved-row', function() {
                var ym = $(this).data('ym');
                if(!ym) return;
                var y = String(ym).slice(0,4);
                var m = String(parseInt(String(ym).slice(5,7), 10));
                st_capture_input_into_dict();
                $('#StYear').val(y);
                $('#StMonth').val(m);
                // selectpicker needs a refresh to show the new value
                if($('#StYear').hasClass('selectpicker')) { $('#StYear').selectpicker('refresh'); }
                if($('#StMonth').hasClass('selectpicker')) { $('#StMonth').selectpicker('refresh'); }
                st_load_period_into_input();
                st_render_saved_panel();
            });
        }
        st_visibility_check();
        $('#Level').on('change', st_visibility_check);
    });

    $('input[type="button"]').click(function() {
        const swalWithBootstrapButtons = Swal.mixin({
            customClass: {
                confirmButton: 'btn btn-light-success m-2',
                cancelButton: 'btn btn-danger m-2'
            },
            buttonsStyling: true
        });
        swalWithBootstrapButtons.fire({
            width: 550,
            background: `url(${background})`,
            icon: 'warning',
            title: action == 'C' ? 'Create New Admin Record ?' : '<?php echo 'Update Admin Record : ' . $Name . ' ?'; ?>',
            confirmButtonText: 'Confirm',
            cancelButtonText: 'Cancel',
            showCancelButton: true
        }).then((response) => {
            if(response.isConfirmed) {
                var country_code = $('#CountryCodeID').val();
                var name = ($('#Name').val()).toUpperCase();
                var gender = $('#Gender').val();
                var identification_number = $('#IdentificationNumber').val();
                var passport_number = ($('#PassportNumber').val()).toUpperCase();
                var mobile = $('#Mobile').val();
                var email = ($('#Email').val()).toUpperCase();
                var username = ($('#Username').val()).toUpperCase();
                var password = ($('#Password').val()).toUpperCase();
                var level = $('#Level').val();
                var access_control = ($('#AccessControl').val()).toString();
                var team_lead_id = $('#TeamLeadID').val();
                var op_team_lead_id = $('#OpTeamLeadID').val();
                var team_id = $('#TeamID').val();
                var lead_dashboard_agents = $('#LeadDashboardAgents').val() || [];
                var lda_initial_str = '<?php echo implode(",", $lead_dashboard_agents); ?>'.split(',').filter(Boolean).sort().join(',');
                var lda_current_str = lead_dashboard_agents.slice().sort().join(',');
                var lda_dirty = (lda_current_str !== lda_initial_str);
                // Capture the currently-edited input into the dict first so the most
                // recent edit isn't lost when diffing.
                if($('#StAmount').length) { st_capture_input_into_dict(); }
                var st_dirty = $('#StAmount').length && st_is_dirty();
                if($('#StYearAmount').length) { sty_capture_input_into_dict(); }
                var sty_dirty = $('#StYearAmount').length && sty_is_dirty();
                if(country_code == null || name == '' || (action == 'C' && gender == null) || mobile == '' || email == '' || (action == 'C' && username == '') || (action == 'C' && password == '') || (action == 'C' && !level) || access_control == '') {
                    Display_Message(background, 'Please Insert All Required Admin Information', null);
                } else {
                    if(action == 'C') {
                        var admin = [];
                        var url = '<?php echo base_url('Admin/Create') ?>';
                        admin.push({CountryCodeID:country_code, Name:name, Gender:gender, IdentificationNumber:identification_number, PassportNumber:passport_number, Mobile:mobile, Email:email, Username:username, Password:password, Level:level, AccessControl:access_control, TeamLeadID:team_lead_id ? team_lead_id : null, OpTeamLeadID:op_team_lead_id ? op_team_lead_id : null, TeamID:team_id ? team_id : null, InsertBy:session_id, InsertDate:current_datetime});
                        Submit_Admin(url, admin, null, lead_dashboard_agents, true, {}, false, {}, false);
                    } else {
                        var dirty_fields = $('#form').dirty('showDirtyFields');
                        var admin_id = <?php echo $AdminID ?>;
                        var admin = [{AdminID:admin_id, UpdateBy:session_id, UpdateDate:current_datetime}];
                        var admin_log = [];
                        var url = '<?php echo base_url('Admin/Update') ?>';
                        for(var i = 0; i < dirty_fields.length; i++) {
                            var key = dirty_fields[i].id;
                            // Skip plugin-injected inputs without an id (e.g. bootstrap-select's live-search box)
                            // — they're not admin columns and would generate invalid SQL like `SET 0 = '...'`.
                            // Also skip non-column UI inputs that live alongside the form
                            // (LeadDashboardAgents posts via its own array; Sales Target inputs
                            // post via the sales_targets dict).
                            if(!key || key == 'LeadDashboardAgents'
                                    || key == 'StAmount' || key == 'StYear' || key == 'StMonth'
                                    || key == 'StYearAmount') {
                                continue;
                            }
                            if(key != 'AccessControl') {
                                var value = key == 'Name' || key == 'PassportNumber' || key == 'Email' || key == 'Password' ? (dirty_fields[i].value).toUpperCase() : dirty_fields[i].value;

                                //Handle TeamLeadID / OpTeamLeadID / TeamID empty value as null
                                if((key == 'TeamLeadID' || key == 'OpTeamLeadID' || key == 'TeamID') && value == '') {
                                    value = null;
                                }

                                //Update Admin
                                admin[0][key] = value;

                                //Insert Admin Log
                                // Read the field's pre-edit value straight from the jquery-dirty
                                // plugin's own stored "dirtyInitialValue" (the same key it sets in
                                // assets/js/jquery-dirty.js). The previous Object.values(...)[N] index
                                // math was brittle: the index drifted whenever the form gained a field
                                // or a <select> gained options (new Level roles, OpTeamLeadID, lead
                                // dashboard / sales-target selects), yielding a wrong/oversized
                                // CurrentData that made the admin_log insert fail — which, with
                                // db_debug on, corrupted the JSON response and showed a false
                                // "Could Not Be Updated" even though the admin row was already saved.
                                var default_value = $(dirty_fields[i]).data('dirtyInitialValue');
                                if(default_value === undefined) { default_value = null; }
                                admin_log.push({AdminID:admin_id, Column:key, CurrentData:default_value, NewData:value, InsertBy:session_id, InsertDate:current_datetime});
                            }
                        }

                        // Action : Update Access Control
                        if(access_control != '<?php echo implode(',', $AccessControl); ?>') {
                            admin[0]['AccessControl'] = access_control;
                            admin_log.push({AdminID:admin_id, Column:'AccessControl', CurrentData:'<?php echo implode(',', $AccessControl); ?>', NewData:access_control, InsertBy:session_id, InsertDate:current_datetime});
                        }

                        var count = 0;
                        $.each(admin[0], function() {
                            count++;
                        });
                        if(count == 3 && !lda_dirty && !st_dirty && !sty_dirty) {
                            var url = '<?php echo base_url('Admin') ?>';
                            Display_Message(background, '<?php echo 'No Changes Detected In Admin Record : ' . $Name; ?>', url);
                        } else {
                            Submit_Admin(url, admin, admin_log, lead_dashboard_agents, lda_dirty, stTargets, st_dirty, styTargets, sty_dirty);
                        }
                    }
                }
            }
        });
    });

    function Submit_Admin(url, admin, admin_log, lead_dashboard_agents, lda_dirty, sales_targets, st_dirty, year_sales_targets, sty_dirty)
    {
        $.ajax({
            url: url,
            type: 'post',
            data: {
                admin: admin,
                admin_log: admin_log,
                lead_dashboard_agents: lead_dashboard_agents || [],
                lead_dashboard_agents_dirty: lda_dirty ? '1' : '0',
                sales_targets: sales_targets || {},
                sales_targets_dirty: st_dirty ? '1' : '0',
                year_sales_targets: year_sales_targets || {},
                year_sales_targets_dirty: sty_dirty ? '1' : '0'
            },
            dataType: 'json',
            success: function(status) {
                if(status == true) {
                    var url = '<?php echo base_url('Admin') ?>';
                    Display_Message(background, success_message, url);
                } else {
                    Display_Message(background, error_message, null);
                }
            },
            error: function() {
                Display_Message(background, error_message, null);
            }
        });
    }

    $('#form').dirty('isClean');
</script>