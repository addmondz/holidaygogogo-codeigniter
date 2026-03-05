/**
 * Remarks Panel Module
 * Handles the global remarks/messages dropdown with Internal Comments and Customer Remarks tabs
 */

(function() {
    'use strict';

    var remarksState = {
        1: { offset: 0, total: 0, loaded: false },
        2: { offset: 0, total: 0, loaded: false }
    };
    var REMARKS_LIMIT = 10;
    var isDropdownOpen = false;

    function initRemarksPanel() {
        // Load remarks when dropdown is opened
        $('#kt_remarks_toggle').on('click', function() {
            if (!isDropdownOpen) {
                // Load internal comments (default tab) if not loaded yet
                if (!remarksState[1].loaded) {
                    loadRemarks(1, 0);
                }
                isDropdownOpen = true;
            } else {
                isDropdownOpen = false;
            }
        });

        // Prevent dropdown from closing when clicking inside it
        $('#remarks-dropdown').on('click', function(e) {
            e.stopPropagation();
        });

        // Sync isDropdownOpen state with Bootstrap dropdown events
        $('#kt_remarks_toggle').parent().on('hide.bs.dropdown', function() {
            isDropdownOpen = false;
        });

        // Tab switching
        $('#remarks-dropdown .nav-link').on('click', function(e) {
            e.preventDefault();
            var type = parseInt($(this).data('type'));

            // Switch active tab
            $('#remarks-dropdown .nav-link').removeClass('active');
            $(this).addClass('active');

            // Switch tab content
            $('#remarks-dropdown .tab-pane').removeClass('show active');
            $(this).attr('href') && $($(this).attr('href')).addClass('show active');

            // Load if not loaded yet
            if (!remarksState[type].loaded) {
                loadRemarks(type, 0);
            }
        });

        // Load more button
        $(document).on('click', '.load-more-remarks', function() {
            var type = parseInt($(this).data('type'));
            loadRemarks(type, remarksState[type].offset);
        });
    }

    function loadRemarks(type, offset) {
        var $list = $('.remarks-list[data-type="' + type + '"]');
        var $loadMore = $('#load-more-' + (type === 1 ? 'internal' : 'customer'));

        if (offset === 0) {
            $list.html(
                '<div class="text-center p-10">' +
                    '<div class="spinner spinner-primary spinner-lg"></div>' +
                    '<div class="mt-3">Loading...</div>' +
                '</div>'
            );
        }

        $.ajax({
            url: HOST_URL + 'Notification/Get_Remarks',
            type: 'GET',
            dataType: 'json',
            data: {
                type: type,
                limit: REMARKS_LIMIT,
                offset: offset
            },
            success: function(response) {
                if (response.success) {
                    remarksState[type].loaded = true;
                    remarksState[type].total = response.total;
                    remarksState[type].offset = offset + response.remarks.length;

                    if (offset === 0) {
                        $list.empty();
                    }

                    if (response.remarks.length === 0 && offset === 0) {
                        $list.html(
                            '<div class="text-center p-10">' +
                                '<i class="la la-comment-slash la-3x text-muted mb-3" style="display:block;"></i>' +
                                '<div class="text-muted">No ' + (type === 1 ? 'internal comments' : 'customer remarks') + ' found</div>' +
                            '</div>'
                        );
                        $loadMore.addClass('d-none');
                        return;
                    }

                    renderRemarks(response.remarks, type, $list);

                    // Show/hide load more
                    if (remarksState[type].offset < remarksState[type].total) {
                        $loadMore.removeClass('d-none');
                    } else {
                        $loadMore.addClass('d-none');
                    }
                }
            },
            error: function() {
                $list.html(
                    '<div class="text-center p-10">' +
                        '<div class="text-danger">Failed to load remarks</div>' +
                    '</div>'
                );
            }
        });
    }

    function renderRemarks(remarks, type, $list) {
        var avatarColors = ['primary', 'success', 'info', 'warning', 'danger'];
        var hash = type === 1 ? '#internal-comments' : '#customer-remarks';

        remarks.forEach(function(remark) {
            var name = remark.CommenterName || 'Unknown';
            var nameParts = name.split(' ');
            var initials = nameParts.length >= 2
                ? (nameParts[0].charAt(0) + nameParts[nameParts.length - 1].charAt(0)).toUpperCase()
                : name.substring(0, 2).toUpperCase();
            var color = avatarColors[name.charCodeAt(0) % avatarColors.length];

            // Truncate content to ~80 chars
            var content = remark.content || '';
            var preview = content.length > 80 ? content.substring(0, 80) + '...' : content;

            var html =
                '<div class="remark-item p-4 border-bottom cursor-pointer" ' +
                    'data-booking-id="' + escapeAttr(remark.BookingID) + '" ' +
                    'data-hash="' + hash + '" ' +
                    'style="cursor: pointer; transition: background-color 0.15s;">' +
                    '<div class="d-flex align-items-start">' +
                        '<div class="flex-shrink-0 mr-3">' +
                            '<div class="symbol symbol-40 symbol-circle symbol-light-' + color + '">' +
                                '<span class="symbol-label font-weight-bold" style="font-size: 0.9rem;">' + escapeHtml(initials) + '</span>' +
                            '</div>' +
                        '</div>' +
                        '<div class="flex-grow-1" style="min-width: 0;">' +
                            '<div class="d-flex align-items-start justify-content-between mb-1">' +
                                '<div class="flex-grow-1">' +
                                    '<div class="font-weight-bold" style="font-size: 0.85rem; color: #050505;">' + escapeHtml(name) + '</div>' +
                                    '<div class="text-muted mb-1" style="font-size: 0.8rem; line-height: 1.3; word-wrap: break-word;">' + escapeHtml(preview) + '</div>' +
                                    '<div class="text-primary font-size-sm">' +
                                        '<i class="la la-file-text" style="font-size: 0.85rem;"></i> ' + escapeHtml(remark.BookingNumber) +
                                    '</div>' +
                                '</div>' +
                                '<span class="text-muted font-size-xs ml-2" style="white-space: nowrap;">' + escapeHtml(remark.time_ago) + '</span>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>';

            $list.append(html);
        });

        // Click handler for remark items
        $list.find('.remark-item').off('click').on('click', function() {
            var bookingId = $(this).data('booking-id');
            var remarkHash = $(this).data('hash');
            window.location.href = HOST_URL + 'Booking/Update?booking_id=' + bookingId + remarkHash;
        });

        // Hover effect
        $list.find('.remark-item').hover(
            function() { $(this).css('background-color', '#F3F6F9'); },
            function() { $(this).css('background-color', ''); }
        );
    }

    function escapeHtml(text) {
        var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text ? String(text).replace(/[&<>"']/g, function(m) { return map[m]; }) : '';
    }

    function escapeAttr(text) {
        return text ? String(text).replace(/[&<>"']/g, function(m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
        }) : '';
    }

    $(document).ready(function() {
        initRemarksPanel();
    });

})();
