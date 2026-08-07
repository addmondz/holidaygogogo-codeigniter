<?php
/**
 * Owner-only Merge Duplicate Customers UI. One card per phone-duplicate group:
 * the owner picks the keeper (radio), reviews, and merges. Bookings move to the
 * keeper; the other rows are deactivated. Data comes from
 * Customer_Model::Find_Duplicate_Phone_Groups().
 */
$fmt_date = function ($d) { return $d ? date('d M Y', strtotime($d)) : '—'; };
?>
<div class="d-flex flex-column-fluid">
    <div class="container-fluid">

        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong>Merge Duplicate Customers</strong>
                    </h3>
                </div>
                <div class="card-toolbar">
                    <div class="btn-group">
                        <a href="<?php echo base_url('Merge_Duplicate_Customers?scope=same'); ?>"
                           class="btn font-weight-bold <?php echo $only_same_name ? 'btn-primary' : 'btn-light'; ?>">Same name (safe)</a>
                        <a href="<?php echo base_url('Merge_Duplicate_Customers?scope=all'); ?>"
                           class="btn font-weight-bold <?php echo $only_same_name ? 'btn-light' : 'btn-primary'; ?>">All groups</a>
                    </div>
                    <a href="<?php echo base_url('Merge_Duplicate_Customers/history'); ?>"
                       class="btn btn-light-primary font-weight-bold ml-3" data-toggle="tooltip"
                       title="View past merges and undo them">
                        <i class="la la-history"></i> Merge History
                    </a>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted" style="font-size:13px;margin-bottom:6px;">
                    Each group below shares one phone number. Pick the <strong>keeper</strong> (suggested = most bookings),
                    then <strong>Merge</strong>: all bookings move to the keeper and the other records are deactivated.
                    This cannot be auto-undone. <strong>AutoCount is not changed</strong> — deactivated records still exist there.
                </p>
                <p style="font-size:13px;margin-bottom:0;">
                    <span class="label label-light-primary label-inline"><?php echo (int) $total_groups; ?></span>
                    duplicate group(s) <?php echo $only_same_name ? '(same-name, safe to merge)' : '(all — review names carefully)'; ?>.
                </p>
            </div>
        </div>

        <?php if (empty($groups)) { ?>
            <div class="card card-custom">
                <div class="card-body text-center text-muted" style="padding:30px;">
                    No duplicate groups on this page. 🎉
                </div>
            </div>
        <?php } ?>

        <?php foreach ($groups as $gi => $g) {
            $gid  = 'grp_' . $gi . '_' . preg_replace('/[^0-9]/', '', $g['pk']);
        ?>
            <div class="card card-custom mb-4 merge-group" id="<?php echo $gid; ?>" data-pk="<?php echo htmlspecialchars($g['pk'], ENT_QUOTES); ?>">
                <div class="card-header py-3">
                    <div class="card-title">
                        <span class="card-label font-weight-bold">
                            Phone key <code>…<?php echo htmlspecialchars($g['pk'], ENT_QUOTES); ?></code>
                            &nbsp;<span class="text-muted" style="font-size:13px;"><?php echo count($g['records']); ?> records</span>
                        </span>
                        <?php if (!empty($g['names_differ'])) { ?>
                            <span class="label label-warning label-inline ml-3" style="font-size:12px;">
                                ⚠ Names differ — verify it's the same person
                            </span>
                        <?php } ?>
                    </div>
                    <div class="card-toolbar">
                        <button type="button" class="btn btn-success font-weight-bold btn-merge" data-group="<?php echo $gid; ?>">
                            <i class="la la-code-branch"></i> Merge group
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <table class="table table-bordered table-head-custom mb-0">
                        <thead>
                            <tr>
                                <th style="text-align:center;width:80px;">Keeper</th>
                                <th>Name</th>
                                <th>Code</th>
                                <th>Phone</th>
                                <th style="text-align:center;">Bookings</th>
                                <th>Created</th>
                                <th>AutoCount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($g['records'] as $r) {
                                $checked = ((int) $r->CustomerID === (int) $g['suggested_keeper']) ? 'checked' : '';
                            ?>
                                <tr>
                                    <td style="text-align:center;vertical-align:middle;">
                                        <label class="radio radio-lg justify-content-center mb-0">
                                            <input type="radio" name="keeper_<?php echo $gid; ?>"
                                                   class="keeper-radio" value="<?php echo (int) $r->CustomerID; ?>" <?php echo $checked; ?>>
                                            <span></span>
                                        </label>
                                    </td>
                                    <td class="font-weight-bold"><?php echo htmlspecialchars($r->name, ENT_QUOTES); ?></td>
                                    <td><?php echo $r->CustomerCode ? htmlspecialchars($r->CustomerCode, ENT_QUOTES) : '<span class="text-muted">—</span>'; ?></td>
                                    <td><?php echo htmlspecialchars($r->phone_number, ENT_QUOTES); ?></td>
                                    <td style="text-align:center;">
                                        <span class="label label-light-info label-inline"><?php echo (int) $r->booking_count; ?></span>
                                    </td>
                                    <td><?php echo $fmt_date($r->created_at); ?></td>
                                    <td><?php echo $r->AutocountSyncStatus ? htmlspecialchars($r->AutocountSyncStatus, ENT_QUOTES) : '<span class="text-muted">—</span>'; ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>

                    <div class="add-record-wrap mt-4">
                        <label class="font-weight-bold" style="font-size:13px;">Add another record to this group</label>
                        <div style="position:relative;max-width:460px;">
                            <input type="text" class="form-control add-record-input"
                                   placeholder="Search name / code / phone (e.g. a record with a different phone)…" autocomplete="off">
                            <div class="add-record-results"
                                 style="display:none;position:absolute;z-index:20;left:0;right:0;background:#fff;border:1px solid #E4E6EF;border-radius:.42rem;box-shadow:0 4px 12px rgba(0,0,0,.08);max-height:260px;overflow-y:auto;"></div>
                        </div>
                        <span class="text-muted" style="font-size:12px;">Use this to merge a record with a <strong>different phone number</strong> (e.g. an old number of the same person).</span>
                    </div>
                </div>
            </div>
        <?php } ?>

        <?php if ($total_pages > 1) { ?>
            <div class="d-flex justify-content-center my-4">
                <?php
                    $scope_q = 'scope=' . ($only_same_name ? 'same' : 'all');
                    for ($p = 1; $p <= $total_pages; $p++) {
                        $active = ($p === (int) $page) ? 'btn-primary' : 'btn-light';
                        echo '<a class="btn ' . $active . ' font-weight-bold mx-1" href="'
                           . base_url('Merge_Duplicate_Customers?' . $scope_q . '&page=' . $p) . '">' . $p . '</a>';
                    }
                ?>
            </div>
        <?php } ?>

    </div>
</div>

<script>
    var MERGE_URL  = '<?php echo base_url('Merge_Duplicate_Customers/ajax_merge'); ?>';
    var SEARCH_URL = '<?php echo base_url('Merge_Duplicate_Customers/ajax_search_customer'); ?>';
    var SWAL_IMG   = '<?php echo base_url('assets/image/sweetalert.jpg') ?>';

    var esc = function (s) { return $('<div>').text(s == null ? '' : String(s)).html(); };
    var phoneKey = function (s) {
        var d = (s || '').replace(/[^0-9]/g, '');
        return d.length <= 9 ? d : d.slice(-9);
    };

    // --- Add another record (search + attach) --------------------------------
    var searchTimer = null;
    $(document).on('input', '.add-record-input', function () {
        var $input = $(this);
        var $card  = $input.closest('.merge-group');
        var $box   = $card.find('.add-record-results');
        var q = $input.val().trim();

        clearTimeout(searchTimer);
        if (q.length < 2) { $box.hide().empty(); return; }

        var exclude = [];
        $card.find('.keeper-radio').each(function () { exclude.push($(this).val()); });

        searchTimer = setTimeout(function () {
            $.ajax({
                url: SEARCH_URL, type: 'get', dataType: 'json',
                data: { q: q, exclude: exclude }
            }).done(function (rows) {
                if (!rows || !rows.length) {
                    $box.html('<div class="p-3 text-muted">No matching customer.</div>').show();
                    return;
                }
                var html = '';
                rows.forEach(function (r) {
                    html += '<a href="javascript:;" class="add-record-pick d-block p-2" '
                          + 'style="border-bottom:1px solid #F3F6F9;color:#3F4254;" '
                          + 'data-id="' + esc(r.CustomerID) + '" data-name="' + esc(r.name) + '" '
                          + 'data-code="' + esc(r.CustomerCode || '') + '" data-phone="' + esc(r.phone_number || '') + '" '
                          + 'data-bookings="' + esc(r.booking_count) + '">'
                          + '<strong>' + esc(r.name) + '</strong> '
                          + '<span class="text-muted">' + esc(r.CustomerCode || '—') + ' · ' + esc(r.phone_number || 'no phone') + '</span> '
                          + '<span class="label label-light-info label-inline float-right">' + esc(r.booking_count) + ' bk</span>'
                          + '</a>';
                });
                $box.html(html).show();
            });
        }, 250);
    });

    // Hide results when clicking elsewhere.
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.add-record-wrap').length) { $('.add-record-results').hide(); }
    });

    $(document).on('click', '.add-record-pick', function () {
        var $card = $(this).closest('.merge-group');
        var gid   = $card.attr('id');
        var d     = $(this).data();

        if ($card.find('.keeper-radio[value="' + d.id + '"]').length) { return; } // already present

        var row = '<tr data-added="1" style="background:#FFF8E1;">'
            + '<td style="text-align:center;vertical-align:middle;"><label class="radio radio-lg justify-content-center mb-0">'
            + '<input type="radio" name="keeper_' + gid + '" class="keeper-radio" value="' + esc(d.id) + '"><span></span></label></td>'
            + '<td class="font-weight-bold">' + esc(d.name) + ' <span class="label label-warning label-inline ml-1">added</span></td>'
            + '<td>' + esc(d.code || '—') + '</td>'
            + '<td>' + esc(d.phone || '—') + '</td>'
            + '<td style="text-align:center;"><span class="label label-light-info label-inline">' + esc(d.bookings) + '</span></td>'
            + '<td>—</td><td>—</td>'
            + '</tr>';
        $card.find('tbody').append(row);
        $card.find('.add-record-input').val('');
        $card.find('.add-record-results').hide().empty();
    });

    // --- Merge ---------------------------------------------------------------
    $(document).on('click', '.btn-merge', function () {
        var gid   = $(this).data('group');
        var $card = $('#' + gid);
        var keeperId = $card.find('.keeper-radio:checked').val();

        if (!keeperId) {
            Display_Message(SWAL_IMG, 'Please choose which record to keep.', null);
            return;
        }

        var loserIds = [];
        $card.find('.keeper-radio').each(function () {
            if ($(this).val() !== String(keeperId)) { loserIds.push($(this).val()); }
        });
        if (!loserIds.length) { return; }

        var keeperRow  = $card.find('.keeper-radio:checked').closest('tr');
        var keeperName = keeperRow.find('td').eq(1).text().trim();
        var keeperCode = keeperRow.find('td').eq(2).text().trim();
        var keeperKey  = phoneKey(keeperRow.find('td').eq(3).text());

        // Cross-phone = any loser whose phone key differs from the keeper's.
        var crossPhone = false;
        $card.find('.keeper-radio').each(function () {
            if ($(this).val() !== String(keeperId)) {
                if (phoneKey($(this).closest('tr').find('td').eq(3).text()) !== keeperKey) { crossPhone = true; }
            }
        });

        var crossNote = crossPhone
            ? '<div class="text-danger mt-2"><strong>⚠ Different phone number(s) included</strong> — only do this if it is the same person.</div>'
            : '';

        Swal.fire({
            icon: 'warning',
            title: 'Merge ' + loserIds.length + ' record(s)?',
            html: 'Keep <strong>' + esc(keeperName) + '</strong> '
                + '(Code: <strong>' + esc(keeperCode || '—') + '</strong>) and deactivate the other '
                + loserIds.length + ' record(s). All their bookings move to the keeper.<br><br>'
                + 'This cannot be automatically undone.' + crossNote,
            showCancelButton: true,
            confirmButtonText: 'Yes, merge',
            cancelButtonText: 'Cancel',
            customClass: {
                confirmButton: 'btn btn-success m-2',
                cancelButton: 'btn btn-secondary m-2'
            },
            buttonsStyling: true
        }).then(function (res) {
            if (!res.isConfirmed) { return; }
            $.ajax({
                url: MERGE_URL,
                type: 'post',
                data: { keeper_id: keeperId, loser_ids: loserIds, allow_cross_phone: crossPhone ? 1 : 0 },
                dataType: 'json'
            }).done(function (r) {
                if (r && r.success) {
                    $card.slideUp(200, function () { $(this).remove(); });
                    Swal.fire({
                        icon: 'success',
                        title: 'Merged',
                        text: r.bookings_moved + ' booking(s) moved, ' + r.deactivated + ' record(s) deactivated.',
                        timer: 2200, showConfirmButton: false
                    });
                } else {
                    Display_Message(SWAL_IMG, (r && r.message) ? r.message : 'Merge failed.', null);
                }
            }).fail(function () {
                Display_Message(SWAL_IMG, 'Merge failed (server error).', null);
            });
        });
    });
</script>
