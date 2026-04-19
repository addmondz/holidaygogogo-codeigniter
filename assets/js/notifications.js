/**
 * Notifications Module
 * Handles notification display, updates, and interactions
 */

(function() {
    'use strict';

    // Notification state
    let notificationCheckInterval = null;
    let isDropdownOpen = false;

    /**
     * Initialize notifications
     */
    function initNotifications() {
        // Load unread count on page load
        updateUnreadCount();

        // Load notifications when dropdown is opened
        $('#kt_notification_toggle').on('click', function() {
            if (!isDropdownOpen) {
                loadNotifications();
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

                    if (count > 0) {
                        badge.text(count > 99 ? '99+' : count).show();
                    } else {
                        badge.hide();
                    }
                }
            },
            error: function() {
                console.error('Failed to fetch unread notification count');
            }
        });
    }

    /**
     * Load notifications list
     */
    function loadNotifications() {
        const notificationList = $('#notification-list');

        // Show loading state
        notificationList.html(`
            <div class="text-center p-10">
                <div class="spinner spinner-primary spinner-lg"></div>
                <div class="mt-3">Loading notifications...</div>
            </div>
        `);

        $.ajax({
            url: HOST_URL + 'Notification/Get_Notifications',
            type: 'GET',
            dataType: 'json',
            data: {
                limit: 20,
                offset: 0
            },
            success: function(response) {
                if (response.success && response.notifications.length > 0) {
                    renderNotifications(response.notifications);
                } else {
                    notificationList.html(`
                        <div class="text-center p-10">
                            <i class="la la-bell-slash la-3x text-muted mb-3"></i>
                            <div class="text-muted">No notifications</div>
                        </div>
                    `);
                }
            },
            error: function() {
                notificationList.html(`
                    <div class="text-center p-10">
                        <div class="text-danger">Failed to load notifications</div>
                    </div>
                `);
            }
        });
    }

    /**
     * Render notifications list (Facebook-style)
     */
    function renderNotifications(notifications) {
        const notificationList = $('#notification-list');
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

            // Avatar color based on first letter
            const avatarColors = ['primary', 'success', 'info', 'warning', 'danger'];
            const avatarColor = avatarColors[commenterName.charCodeAt(0) % avatarColors.length];

            // Check if notification is unread
            const isReadValue = notification.is_read;
            const isUnread = (isReadValue === false ||
                              isReadValue === 0 ||
                              isReadValue === 'false' ||
                              isReadValue === '0' ||
                              isReadValue === null ||
                              isReadValue === undefined);

            // Different styling for unread vs read notifications
            const bgColor = isUnread ? '#F0F7FF' : '#FFFFFF';
            const textColor = '#000000';
            const fontWeight = isUnread ? '700' : '400';
            const borderLeft = isUnread ? '4px solid #3699FF' : 'none';

            const styleString = 'background-color: ' + bgColor + ' !important; border-left: ' + borderLeft + ' !important; transition: background-color 0.2s; cursor: pointer;';
            const textStyleString = 'font-size: 0.95rem; line-height: 1.3; color: ' + textColor + ' !important; font-weight: ' + fontWeight + ' !important;';

            const notificationId = notification.NotificationID || '';
            const bookingId = notification.BookingID || '';
            const bookingNumber = escapeHtml(notification.BookingNumber || '');
            const isReadAttr = (isUnread ? 'false' : 'true');

            // Toggle button: text link for read/unread
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
                        '<!-- Avatar on the left -->' +
                        '<div class="flex-shrink-0 mr-3">' +
                            '<div class="symbol symbol-40 symbol-circle symbol-light-' + avatarColor + '">' +
                                '<span class="symbol-label font-weight-bold" style="font-size: 0.9rem;">' + initials + '</span>' +
                            '</div>' +
                        '</div>' +
                        '<!-- Content in the middle -->' +
                        '<div class="flex-grow-1" style="min-width: 0;">' +
                            '<div class="d-flex align-items-start justify-content-between mb-1">' +
                                '<div class="flex-grow-1">' +
                                    '<div class="notification-text mb-1" style="' + textStyleString + '">' +
                                        escapeHtml(notification.message) +
                                    '</div>' +
                                    (notification.BookingNumber ?
                                        '<div class="text-primary font-size-sm mb-1">' +
                                            '<i class="la la-file-text"></i> ' + escapeHtml(notification.BookingNumber) +
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

        notificationList.html(html);

        // Click handler for notification item (navigate to booking)
        notificationList.find('.notification-item').on('click', function(e) {
            // Don't navigate if clicking the toggle button
            if ($(e.target).closest('.notification-toggle-read').length) {
                return;
            }

            const $item = $(this);
            const notificationId = $item.data('notification-id');
            const bookingNumber = $item.data('booking-number');
            const isRead = $item.attr('data-is-read') === 'true';

            // Mark as read before navigating (if not already read)
            if (!isRead && notificationId) {
                $.ajax({
                    url: HOST_URL + 'Notification/Mark_As_Read',
                    type: 'POST',
                    dataType: 'json',
                    data: { notification_id: notificationId }
                });
            }

            // Navigate to booking
            if (bookingNumber) {
                window.location.href = HOST_URL + 'Booking?booking_number=' + encodeURIComponent(bookingNumber);
            } else {
                window.location.href = HOST_URL + 'Booking';
            }
        });

        // Click handler for toggle read/unread button
        notificationList.find('.notification-toggle-read').on('click', function(e) {
            e.stopPropagation();

            const $btn = $(this);
            const $item = $btn.closest('.notification-item');
            const notificationId = $btn.data('notification-id');
            const isCurrentlyRead = $item.attr('data-is-read') === 'true';

            if (isCurrentlyRead) {
                // Mark as unread
                $.ajax({
                    url: HOST_URL + 'Notification/Mark_As_Unread',
                    type: 'POST',
                    dataType: 'json',
                    data: { notification_id: notificationId },
                    success: function(response) {
                        if (response.success) {
                            applyNotificationStyle($item, true);
                            $btn.text('Read').css('color', '#3699FF').attr('title', 'Mark as read');
                            updateUnreadCount();
                        }
                    }
                });
            } else {
                // Mark as read
                $.ajax({
                    url: HOST_URL + 'Notification/Mark_As_Read',
                    type: 'POST',
                    dataType: 'json',
                    data: { notification_id: notificationId },
                    success: function(response) {
                        if (response.success) {
                            applyNotificationStyle($item, false);
                            $btn.text('Unread').css('color', '#F5A623').attr('title', 'Mark as unread');
                            updateUnreadCount();
                        }
                    }
                });
            }
        });

        // Hover effect
        notificationList.find('.notification-item').hover(
            function() {
                const isRead = $(this).attr('data-is-read') === 'true';
                const hoverColor = isRead ? '#F3F6F9' : '#E8F3FF';
                const currentStyle = $(this).attr('style') || '';
                const newStyle = currentStyle.replace(/background-color:[^;]*;?/gi, '') + ' background-color: ' + hoverColor + ' !important;';
                $(this).attr('style', newStyle.trim());
            },
            function() {
                const isRead = $(this).attr('data-is-read') === 'true';
                const originalColor = isRead ? '#FFFFFF' : '#F0F7FF';
                const currentStyle = $(this).attr('style') || '';
                const newStyle = currentStyle.replace(/background-color:[^;]*;?/gi, '') + ' background-color: ' + originalColor + ' !important;';
                $(this).attr('style', newStyle.trim());
            }
        );
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
