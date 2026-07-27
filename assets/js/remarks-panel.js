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

    function updateRemarksUnreadCount() {
        $.ajax({
            url: HOST_URL + 'Notification/Get_Remarks_Unread_Count',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    var $badge = $('#remarks-badge');
                    if (response.count > 0) {
                        $badge.text(response.count > 99 ? '99+' : response.count).show();
                    } else {
                        $badge.hide();
                    }

                    // Show/hide per-tab "Mark all as read" buttons
                    if (response.internal_count > 0) {
                        $('#mark-read-internal').removeClass('d-none');
                    } else {
                        $('#mark-read-internal').addClass('d-none');
                    }
                    if (response.customer_count > 0) {
                        $('#mark-read-customer').removeClass('d-none');
                    } else {
                        $('#mark-read-customer').addClass('d-none');
                    }
                }
            }
        });
    }

    function markRemarksAsRead(type, callback) {
        $.ajax({
            url: HOST_URL + 'Notification/Mark_Remarks_As_Read',
            type: 'POST',
            dataType: 'json',
            data: { type: type },
            success: function() {
                if (callback) callback();
            }
        });
    }

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

        // Close button in header
        $('#kt_remarks_close').on('click', function(e) {
            e.stopPropagation();
            $('#remarks-dropdown').removeClass('show');
            isDropdownOpen = false;
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

        // Load more button — delegated on #remarks-dropdown because the
        // dropdown's stopPropagation handler blocks bubbling to document.
        $('#remarks-dropdown').on('click', '.load-more-remarks', function() {
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

    /**
     * Apply read/unread styling to a remark item
     * @param {jQuery} $item The remark item element
     * @param {boolean} isUnread Whether to apply unread styling
     */
    function applyRemarkStyle($item, isUnread) {
        if (isUnread) {
            $item.attr('data-is-read', 'false');
            var style = ($item.attr('style') || '')
                .replace(/background-color:[^;]*;?/gi, '')
                .replace(/border-left:[^;]*;?/gi, '');
            $item.attr('style', (style + ' background-color: #F0F7FF !important; border-left: 4px solid #3699FF !important;').trim());

            var $textDiv = $item.find('.remark-content-text').first();
            if ($textDiv.length) {
                var textStyle = ($textDiv.attr('style') || '')
                    .replace(/font-weight:[^;]*;?/gi, '');
                $textDiv.attr('style', (textStyle + ' font-weight: 700 !important;').trim());
            }
        } else {
            $item.attr('data-is-read', 'true');
            var style = ($item.attr('style') || '')
                .replace(/background-color:[^;]*;?/gi, '')
                .replace(/border-left:[^;]*;?/gi, '');
            $item.attr('style', (style + ' background-color: #FFFFFF !important; border-left: none !important;').trim());

            var $textDiv = $item.find('.remark-content-text').first();
            if ($textDiv.length) {
                var textStyle = ($textDiv.attr('style') || '')
                    .replace(/font-weight:[^;]*;?/gi, '');
                $textDiv.attr('style', (textStyle + ' font-weight: 400 !important;').trim());
            }
        }
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

            // Read/unread state
            var isReadValue = remark.is_read;
            var isUnread = (isReadValue === false || isReadValue === 0 || isReadValue === 'false' || isReadValue === '0' || isReadValue === null || isReadValue === undefined);

            var bgColor = isUnread ? '#F0F7FF' : '#FFFFFF';
            var fontWeight = isUnread ? '700' : '400';
            var borderLeft = isUnread ? '4px solid #3699FF' : 'none';
            var isReadAttr = isUnread ? 'false' : 'true';

            var styleString = 'background-color: ' + bgColor + ' !important; border-left: ' + borderLeft + ' !important; transition: background-color 0.2s; cursor: pointer;';
            var textStyleString = 'font-size: 0.8rem; line-height: 1.3; word-wrap: break-word; font-weight: ' + fontWeight + ' !important;';

            // Toggle button
            var toggleBtn = isUnread
                ? '<span class="remark-toggle-read" data-remark-id="' + escapeAttr(remark.RemarkID) + '" title="Mark as read" style="font-size: 11px; cursor: pointer; background: none; border: none; padding: 4px 0; flex-shrink: 0; text-decoration: none; color: #3699FF;">Read</span>'
                : '<span class="remark-toggle-read" data-remark-id="' + escapeAttr(remark.RemarkID) + '" title="Mark as unread" style="font-size: 11px; cursor: pointer; background: none; border: none; padding: 4px 0; flex-shrink: 0; text-decoration: none; color: #F5A623;">Unread</span>';

            var html =
                '<div class="remark-item p-4 border-bottom cursor-pointer" ' +
                    'data-remark-id="' + escapeAttr(remark.RemarkID) + '" ' +
                    'data-booking-id="' + escapeAttr(remark.BookingID) + '" ' +
                    'data-hash="' + hash + '" ' +
                    'data-is-read="' + isReadAttr + '" ' +
                    'style="' + styleString + '">' +
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
                                    '<div class="remark-content-text text-muted mb-1" style="' + textStyleString + '">' + escapeHtml(preview) + '</div>' +
                                    '<div class="text-primary font-size-sm">' +
                                        '<i class="la la-file-text" style="font-size: 0.85rem;"></i> ' + escapeHtml(remark.BookingNumber) +
                                        (remark.Customer ? ' — ' + escapeHtml(remark.Customer) : '') +
                                    '</div>' +
                                '</div>' +
                                '<span class="text-muted font-size-xs ml-2" style="white-space: nowrap;">' + escapeHtml(remark.time_ago) + '</span>' +
                            '</div>' +
                            '<div class="d-flex justify-content-end">' +
                                toggleBtn +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>';

            $list.append(html);
        });

        // Click handler for remark items (navigate to booking)
        $list.find('.remark-item').off('click').on('click', function(e) {
            // Don't navigate if clicking the toggle button
            if ($(e.target).closest('.remark-toggle-read').length) {
                return;
            }

            var $item = $(this);
            var bookingId = $item.data('booking-id');
            var remarkHash = $item.data('hash');
            var remarkId = $item.data('remark-id');
            var isRead = $item.attr('data-is-read') === 'true';

            // Mark as read before navigating (if not already read)
            if (!isRead && remarkId) {
                $.ajax({
                    url: HOST_URL + 'Notification/Mark_Remark_As_Read',
                    type: 'POST',
                    dataType: 'json',
                    data: { remark_id: remarkId }
                });
            }

            window.location.href = HOST_URL + 'Booking/Update?booking_id=' + bookingId + remarkHash;
        });

        // Click handler for toggle read/unread button
        $list.find('.remark-toggle-read').off('click').on('click', function(e) {
            e.stopPropagation();

            var $btn = $(this);
            var $item = $btn.closest('.remark-item');
            var remarkId = $btn.data('remark-id');
            var isCurrentlyRead = $item.attr('data-is-read') === 'true';

            if (isCurrentlyRead) {
                // Mark as unread
                $.ajax({
                    url: HOST_URL + 'Notification/Mark_Remark_As_Unread',
                    type: 'POST',
                    dataType: 'json',
                    data: { remark_id: remarkId },
                    success: function(response) {
                        if (response.success) {
                            applyRemarkStyle($item, true);
                            $btn.text('Read').css('color', '#3699FF').attr('title', 'Mark as read');
                            updateRemarksUnreadCount();
                        }
                    }
                });
            } else {
                // Mark as read
                $.ajax({
                    url: HOST_URL + 'Notification/Mark_Remark_As_Read',
                    type: 'POST',
                    dataType: 'json',
                    data: { remark_id: remarkId },
                    success: function(response) {
                        if (response.success) {
                            applyRemarkStyle($item, false);
                            $btn.text('Unread').css('color', '#F5A623').attr('title', 'Mark as unread');
                            updateRemarksUnreadCount();
                        }
                    }
                });
            }
        });

        // Hover effect — differentiate read vs unread
        $list.find('.remark-item').hover(
            function() {
                var isRead = $(this).attr('data-is-read') === 'true';
                var hoverColor = isRead ? '#F3F6F9' : '#E8F3FF';
                var currentStyle = $(this).attr('style') || '';
                var newStyle = currentStyle.replace(/background-color:[^;]*;?/gi, '') + ' background-color: ' + hoverColor + ' !important;';
                $(this).attr('style', newStyle.trim());
            },
            function() {
                var isRead = $(this).attr('data-is-read') === 'true';
                var originalColor = isRead ? '#FFFFFF' : '#F0F7FF';
                var currentStyle = $(this).attr('style') || '';
                var newStyle = currentStyle.replace(/background-color:[^;]*;?/gi, '') + ' background-color: ' + originalColor + ' !important;';
                $(this).attr('style', newStyle.trim());
            }
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

        // Per-tab "Mark all as read" click handler — delegated on
        // #remarks-dropdown because the dropdown's stopPropagation
        // handler blocks bubbling to document.
        $('#remarks-dropdown').on('click', '.mark-tab-remarks-read', function() {
            var $link = $(this);
            var type = parseInt($link.data('type'));
            $link.css('pointer-events', 'none');
            markRemarksAsRead(type, function() {
                // Visually update all items in the current tab
                var $list = $('.remarks-list[data-type="' + type + '"]');
                $list.find('.remark-item').each(function() {
                    var $item = $(this);
                    if ($item.attr('data-is-read') !== 'true') {
                        applyRemarkStyle($item, false);
                        $item.find('.remark-toggle-read')
                            .text('Unread')
                            .css('color', '#F5A623')
                            .attr('title', 'Mark as unread');
                    }
                });
                updateRemarksUnreadCount();
                $link.css('pointer-events', '');
            });
        });

        // Initial badge fetch and periodic refresh every 30 seconds
        updateRemarksUnreadCount();
        setInterval(updateRemarksUnreadCount, 30000);
    });

})();
