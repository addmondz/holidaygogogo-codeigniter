/**
 * Notifications Module
 * Handles notification display, updates, and interactions
 */

(function() {
    'use strict';

    // Notification state
    let notificationCheckInterval = null;
    let isDropdownOpen = false;
    const NOTIFICATION_LIMIT = 20;
    let notificationOffset = 0;
    let hasMoreNotifications = false;
    let isLoadingMore = false;

    /**
     * Initialize notifications
     */
    function initNotifications() {
        // Load unread count on page load
        updateUnreadCount();

        // Load notifications when dropdown is opened
        $('#kt_notification_toggle').on('click', function() {
            if (!isDropdownOpen) {
                notificationOffset = 0;
                hasMoreNotifications = false;
                loadNotifications(false);
                isDropdownOpen = true;
            } else {
                isDropdownOpen = false;
            }
        });

        // Close dropdown when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#kt_notification_toggle, #notification-dropdown').length) {
                $('#notification-dropdown').removeClass('show');
                isDropdownOpen = false;
            }
        });

        // Close button in header
        $('#kt_notification_close').on('click', function(e) {
            e.stopPropagation();
            $('#notification-dropdown').removeClass('show');
            isDropdownOpen = false;
        });

        // "Mark all as read" click handler (pinned footer)
        $(document).on('click', '.mark-all-notifications-read', function(e) {
            e.stopPropagation();
            const $link = $(this);
            $link.css('pointer-events', 'none');
            markAllNotificationsAsRead(function() {
                $('#notification-list').find('.notification-item').each(function() {
                    const $item = $(this);
                    if ($item.attr('data-is-read') !== 'true') {
                        applyNotificationStyle($item, false);
                        $item.find('.notification-toggle-read')
                            .text('Unread')
                            .css('color', '#F5A623')
                            .attr('title', 'Mark as unread');
                    }
                });
                updateUnreadCount();
                $link.css('pointer-events', '');
            });
        });

        // "Load More" — fetch the next page and append
        $(document).on('click', '.notification-load-more', function(e) {
            e.stopPropagation();
            if (isLoadingMore || !hasMoreNotifications) return;
            loadNotifications(true);
        });

        // Delegated notification-item click → navigate to booking
        $(document).on('click', '#notification-list .notification-item', function(e) {
            if ($(e.target).closest('.notification-toggle-read').length) return;

            const $item = $(this);
            const notificationId = $item.data('notification-id');
            const bookingNumber = $item.data('booking-number');
            const isRead = $item.attr('data-is-read') === 'true';

            if (!isRead && notificationId) {
                $.ajax({
                    url: HOST_URL + 'Notification/Mark_As_Read',
                    type: 'POST',
                    dataType: 'json',
                    data: { notification_id: notificationId }
                });
            }

            if (bookingNumber) {
                window.location.href = HOST_URL + 'Booking?booking_number=' + encodeURIComponent(bookingNumber);
            } else {
                window.location.href = HOST_URL + 'Booking';
            }
        });

        // Delegated toggle read/unread
        $(document).on('click', '#notification-list .notification-toggle-read', function(e) {
            e.stopPropagation();

            const $btn = $(this);
            const $item = $btn.closest('.notification-item');
            const notificationId = $btn.data('notification-id');
            const isCurrentlyRead = $item.attr('data-is-read') === 'true';

            const url = isCurrentlyRead
                ? HOST_URL + 'Notification/Mark_As_Unread'
                : HOST_URL + 'Notification/Mark_As_Read';

            $.ajax({
                url: url,
                type: 'POST',
                dataType: 'json',
                data: { notification_id: notificationId },
                success: function(response) {
                    if (!response.success) return;
                    if (isCurrentlyRead) {
                        applyNotificationStyle($item, true);
                        $btn.text('Read').css('color', '#3699FF').attr('title', 'Mark as read');
                    } else {
                        applyNotificationStyle($item, false);
                        $btn.text('Unread').css('color', '#F5A623').attr('title', 'Mark as unread');
                    }
                    updateUnreadCount();
                }
            });
        });

        // Delegated hover styling
        $(document).on('mouseenter', '#notification-list .notification-item', function() {
            const isRead = $(this).attr('data-is-read') === 'true';
            const hoverColor = isRead ? '#F3F6F9' : '#E8F3FF';
            const currentStyle = $(this).attr('style') || '';
            const newStyle = currentStyle.replace(/background-color:[^;]*;?/gi, '') + ' background-color: ' + hoverColor + ' !important;';
            $(this).attr('style', newStyle.trim());
        });
        $(document).on('mouseleave', '#notification-list .notification-item', function() {
            const isRead = $(this).attr('data-is-read') === 'true';
            const originalColor = isRead ? '#FFFFFF' : '#F0F7FF';
            const currentStyle = $(this).attr('style') || '';
            const newStyle = currentStyle.replace(/background-color:[^;]*;?/gi, '') + ' background-color: ' + originalColor + ' !important;';
            $(this).attr('style', newStyle.trim());
        });

        // Auto-refresh unread count every 30 seconds
        notificationCheckInterval = setInterval(function() {
            if (!isDropdownOpen) {
                updateUnreadCount();
            }
        }, 30000);
    }

    /**
     * Update unread notification count
     */
    function updateUnreadCount() {
        $.ajax({
            url: HOST_URL + 'Notification/Get_Unread_Count',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const count = parseInt(response.count) || 0;
                    const badge = $('#notification-badge');
                    const $markAll = $('#mark-read-notifications');

                    if (count > 0) {
                        badge.text(count > 99 ? '99+' : count).show();
                        $markAll.removeClass('d-none');
                    } else {
                        badge.hide();
                        $markAll.addClass('d-none');
                    }
                }
            },
            error: function() {
                console.error('Failed to fetch unread notification count');
            }
        });
    }

    /**
     * Mark all notifications as read for the current user
     */
    function markAllNotificationsAsRead(callback) {
        $.ajax({
            url: HOST_URL + 'Notification/Mark_All_As_Read',
            type: 'POST',
            dataType: 'json',
            success: function() {
                if (callback) callback();
            }
        });
    }

    /**
     * Load notifications list
     * @param {boolean} append If true, fetch the next page and append. Otherwise reset and replace.
     */
    function loadNotifications(append) {
        const $notificationList = $('#notification-list');
        const offset = append ? notificationOffset : 0;

        if (!append) {
            notificationOffset = 0;
            hasMoreNotifications = false;
            $notificationList.html(
                '<div class="text-center p-10">' +
                    '<div class="spinner spinner-primary spinner-lg"></div>' +
                    '<div class="mt-3">Loading notifications...</div>' +
                '</div>'
            );
        } else {
            isLoadingMore = true;
            $notificationList.find('.notification-load-more')
                .text('Loading...')
                .css('pointer-events', 'none');
        }

        $.ajax({
            url: HOST_URL + 'Notification/Get_Notifications',
            type: 'GET',
            dataType: 'json',
            data: { limit: NOTIFICATION_LIMIT, offset: offset },
            success: function(response) {
                const items = (response && response.success && response.notifications) ? response.notifications : [];
                hasMoreNotifications = items.length === NOTIFICATION_LIMIT;

                if (append) {
                    $notificationList.find('.notification-load-more-wrapper').remove();
                    if (items.length > 0) {
                        $notificationList.append(buildNotificationsHtml(items));
                        notificationOffset += items.length;
                    }
                    if (hasMoreNotifications) {
                        $notificationList.append(loadMoreLinkHtml());
                    }
                    isLoadingMore = false;
                } else {
                    if (items.length === 0) {
                        $notificationList.html(
                            '<div class="text-center p-10">' +
                                '<i class="la la-bell-slash la-3x text-muted mb-3"></i>' +
                                '<div class="text-muted">No notifications</div>' +
                            '</div>'
                        );
                    } else {
                        let html = buildNotificationsHtml(items);
                        if (hasMoreNotifications) html += loadMoreLinkHtml();
                        $notificationList.html(html);
                        notificationOffset = items.length;
                    }
                }
            },
            error: function() {
                if (append) {
                    $notificationList.find('.notification-load-more')
                        .text('Failed to load — retry')
                        .css('pointer-events', '');
                    isLoadingMore = false;
                } else {
                    $notificationList.html(
                        '<div class="text-center p-10">' +
                            '<div class="text-danger">Failed to load notifications</div>' +
                        '</div>'
                    );
                }
            }
        });
    }

    /**
     * Build the HTML string for a list of notifications (no DOM insertion).
     */
    function buildNotificationsHtml(notifications) {
        let html = '';
        notifications.forEach(function(notification) {
            // Get initials for avatar
            const commenterName = notification.CommenterName || 'Unknown';
            const nameParts = commenterName.split(' ');
            let initials = '';
            if (nameParts.length >= 2) {
                initials = (nameParts[0].charAt(0) + nameParts[nameParts.length - 1].charAt(0)).toUpperCase();
            } else {
                initials = commenterName.substring(0, 2).toUpperCase();
            }

            const avatarColors = ['primary', 'success', 'info', 'warning', 'danger'];
            const avatarColor = avatarColors[commenterName.charCodeAt(0) % avatarColors.length];

            const isReadValue = notification.is_read;
            const isUnread = (isReadValue === false ||
                              isReadValue === 0 ||
                              isReadValue === 'false' ||
                              isReadValue === '0' ||
                              isReadValue === null ||
                              isReadValue === undefined);

            const bgColor = isUnread ? '#F0F7FF' : '#FFFFFF';
            const textColor = '#000000';
            const fontWeight = isUnread ? '700' : '400';
            const borderLeft = isUnread ? '4px solid #3699FF' : 'none';

            const styleString = 'background-color: ' + bgColor + ' !important; border-left: ' + borderLeft + ' !important; transition: background-color 0.2s; cursor: pointer;';
            const textStyleString = 'font-size: 0.95rem; line-height: 1.3; color: ' + textColor + ' !important; font-weight: ' + fontWeight + ' !important;';

            const notificationId = notification.NotificationID || '';
            const bookingId = notification.BookingID || '';
            const bookingNumber = escapeHtml(notification.BookingNumber || '');
            const customerName = escapeHtml(notification.Customer || '');
            const isReadAttr = (isUnread ? 'false' : 'true');

            const toggleDot = isUnread
                ? '<span class="notification-toggle-read" data-notification-id="' + notificationId + '" title="Mark as read" style="font-size: 11px; cursor: pointer; background: none; border: none; padding: 4px 0; flex-shrink: 0; text-decoration: none; color: #3699FF;">Read</span>'
                : '<span class="notification-toggle-read" data-notification-id="' + notificationId + '" title="Mark as unread" style="font-size: 11px; cursor: pointer; background: none; border: none; padding: 4px 0; flex-shrink: 0; text-decoration: none; color: #F5A623;">Unread</span>';

            html += '<div class="notification-item p-4 border-bottom cursor-pointer" ' +
                     'data-notification-id="' + notificationId + '" ' +
                     'data-booking-id="' + bookingId + '" ' +
                     'data-booking-number="' + bookingNumber + '" ' +
                     'data-is-read="' + isReadAttr + '" ' +
                     'style="' + styleString + '">' +
                    '<div class="d-flex align-items-start">' +
                        '<div class="flex-shrink-0 mr-3">' +
                            '<div class="symbol symbol-40 symbol-circle symbol-light-' + avatarColor + '">' +
                                '<span class="symbol-label font-weight-bold" style="font-size: 0.9rem;">' + initials + '</span>' +
                            '</div>' +
                        '</div>' +
                        '<div class="flex-grow-1" style="min-width: 0;">' +
                            '<div class="d-flex align-items-start justify-content-between mb-1">' +
                                '<div class="flex-grow-1">' +
                                    '<div class="notification-text mb-1" style="' + textStyleString + '">' +
                                        escapeHtml(notification.message) +
                                    '</div>' +
                                    (notification.BookingNumber ?
                                        '<div class="text-primary font-size-sm mb-1">' +
                                            '<i class="la la-file-text"></i> ' + escapeHtml(notification.BookingNumber) +
                                            (customerName ? ' — ' + customerName : '') +
                                        '</div>' : '') +
                                '</div>' +
                                '<div class="d-flex align-items-center ml-2" style="white-space: nowrap;">' +
                                    '<span class="text-muted font-size-xs">' + notification.time_ago + '</span>' +
                                '</div>' +
                            '</div>' +
                            '<div class="d-flex justify-content-end">' +
                                toggleDot +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>';
        });
        return html;
    }

    /**
     * HTML for the "Load More" link placed at the end of the list.
     */
    function loadMoreLinkHtml() {
        return '<div class="text-center py-3 border-top notification-load-more-wrapper">' +
                    '<a href="javascript:;" class="notification-load-more btn btn-sm btn-light-primary font-weight-bold">Load More</a>' +
               '</div>';
    }

    /**
     * Apply read/unread styling to a notification item
     * @param {jQuery} $item The notification item element
     * @param {boolean} isUnread Whether to apply unread styling
     */
    function applyNotificationStyle($item, isUnread) {
        if (isUnread) {
            $item.attr('data-is-read', 'false');
            let style = ($item.attr('style') || '')
                .replace(/background-color:[^;]*;?/gi, '')
                .replace(/border-left:[^;]*;?/gi, '');
            $item.attr('style', (style + ' background-color: #F0F7FF !important; border-left: 4px solid #3699FF !important;').trim());

            const $textDiv = $item.find('.notification-text').first();
            if ($textDiv.length) {
                let textStyle = ($textDiv.attr('style') || '')
                    .replace(/font-weight:[^;]*;?/gi, '');
                $textDiv.attr('style', (textStyle + ' font-weight: 700 !important;').trim());
            }
        } else {
            $item.attr('data-is-read', 'true');
            let style = ($item.attr('style') || '')
                .replace(/background-color:[^;]*;?/gi, '')
                .replace(/border-left:[^;]*;?/gi, '');
            $item.attr('style', (style + ' background-color: #FFFFFF !important; border-left: none !important;').trim());

            const $textDiv = $item.find('.notification-text').first();
            if ($textDiv.length) {
                let textStyle = ($textDiv.attr('style') || '')
                    .replace(/font-weight:[^;]*;?/gi, '');
                $textDiv.attr('style', (textStyle + ' font-weight: 400 !important;').trim());
            }
        }
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text ? text.replace(/[&<>"']/g, function(m) { return map[m]; }) : '';
    }

    // Initialize when document is ready
    $(document).ready(function() {
        initNotifications();
    });

    // Cleanup interval on page unload
    $(window).on('beforeunload', function() {
        if (notificationCheckInterval) {
            clearInterval(notificationCheckInterval);
        }
    });

})();
