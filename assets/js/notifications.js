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
                // Backend automatically marks as read when Get_Notifications is called
                // But we show unread styling initially, then update to read after delay
                setTimeout(function() {
                    // Update visual styling to read (backend already marked them as read)
                    updateNotificationsToReadStyle();
                }, 2000); // 2 second delay to show unread styling first
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
                // Debug: Log the response to see what we're getting
                // console.log('Notifications response:', response);
                if (response.success && response.notifications.length > 0) {
                    // Debug: Log first notification to check is_read value
                    // console.log('First notification:', response.notifications[0]);
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
            // Backend sends is_read as boolean: false (unread) or true (read)
            // Also handle string 'false'/'true' and numbers 0/1 just in case
            const isReadValue = notification.is_read;
            
            // Simplified check: unread if is_read is false, 0, 'false', '0', null, or undefined
            // Backend sends boolean false for unread, true for read
            const isUnread = (isReadValue === false || 
                              isReadValue === 0 || 
                              isReadValue === 'false' || 
                              isReadValue === '0' ||
                              isReadValue === null ||
                              isReadValue === undefined);
            
            // Different styling for unread vs read notifications - more obvious difference
            const bgColor = isUnread ? '#F0F7FF' : '#FFFFFF'; // Very light blue for unread, white for read
            const textColor = '#000000'; // Black text for both read and unread
            const fontWeight = isUnread ? '700' : '400'; // Bold for unread, normal for read
            const borderLeft = isUnread ? '4px solid #3699FF' : 'none'; // Blue left border for unread
            
            // Build style string - ensure !important is properly applied
            const styleString = 'background-color: ' + bgColor + ' !important; border-left: ' + borderLeft + ' !important; transition: background-color 0.2s; cursor: pointer;';
            const textStyleString = 'font-size: 0.95rem; line-height: 1.3; color: ' + textColor + ' !important; font-weight: ' + fontWeight + ' !important;';
            
            // Debug: Log styling for unread items
            // if (isUnread) {
            //     console.log('Unread notification:', notification.NotificationID, 'bgColor:', bgColor, 'borderLeft:', borderLeft);
            // }
            
            // Ensure all values are defined
            const notificationId = notification.NotificationID || '';
            const bookingId = notification.BookingID || '';
            const bookingNumber = escapeHtml(notification.BookingNumber || '');
            const isReadAttr = (isUnread ? 'false' : 'true');
            
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
                        '<!-- Content on the right -->' +
                        '<div class="flex-grow-1" style="min-width: 0;">' +
                            '<div class="d-flex align-items-start justify-content-between mb-1">' +
                                '<div class="flex-grow-1">' +
                                    '<div class="mb-1" style="' + textStyleString + '">' +
                                        escapeHtml(notification.message) +
                                    '</div>' +
                                    (notification.BookingNumber ? 
                                        '<div class="text-primary font-size-sm mb-1">' +
                                            '<i class="la la-file-text"></i> ' + escapeHtml(notification.BookingNumber) +
                                        '</div>' : '') +
                                '</div>' +
                                '<span class="text-muted font-size-xs ml-2" style="white-space: nowrap;">' + notification.time_ago + '</span>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>';
        });

        notificationList.html(html);
        
        // Force apply styles to ALL notifications to ensure proper styling
        // This fixes cases where HTML might not have been generated correctly or styles are missing
        setTimeout(function() {
            $('.notification-item').each(function() {
                const $item = $(this);
                let isReadAttr = $item.attr('data-is-read');
                
                // If data-is-read is missing, check the notification data or default to checking if it has unread styling
                if (!isReadAttr || isReadAttr === '') {
                    // Try to determine from existing styles or default to unread
                    const currentBg = $item.css('background-color');
                    const isUnread = (currentBg === 'rgb(240, 247, 255)' || currentBg === '#F0F7FF' || currentBg === 'rgb(225, 240, 255)' || currentBg === '#E1F0FF' || currentBg === 'rgb(179, 217, 255)' || currentBg === '#B3D9FF' || !currentBg || currentBg === 'rgba(0, 0, 0, 0)' || currentBg === 'transparent');
                    isReadAttr = isUnread ? 'false' : 'true';
                    $item.attr('data-is-read', isReadAttr);
                }
                
                const isUnread = (isReadAttr === 'false');
                let currentStyle = $item.attr('style') || '';
                
                if (isUnread) {
                    // Unread: blue background, blue border, black bold text
                    currentStyle = currentStyle.replace(/background-color:[^;]*;?/gi, '');
                    currentStyle = currentStyle.replace(/border-left:[^;]*;?/gi, '');
                    currentStyle = currentStyle.replace(/transition:[^;]*;?/gi, '');
                    currentStyle = (currentStyle.trim() + ' background-color: #F0F7FF !important; border-left: 4px solid #3699FF !important; transition: background-color 0.2s; cursor: pointer;').trim();
                    $item.attr('style', currentStyle);
                    
                    // Ensure text is black and bold for unread
                    const $textDiv = $item.find('.mb-1').first();
                    if ($textDiv.length) {
                        let textStyle = $textDiv.attr('style') || '';
                        textStyle = textStyle.replace(/color:[^;]*;?/gi, '');
                        textStyle = textStyle.replace(/font-weight:[^;]*;?/gi, '');
                        textStyle = (textStyle.trim() + ' font-size: 0.95rem; line-height: 1.3; color: #000000 !important; font-weight: 700 !important;').trim();
                        $textDiv.attr('style', textStyle);
                        // Remove any classes that might override
                        $textDiv.removeClass('font-weight-bold text-dark');
                    }
                } else {
                    // Read: white background, no border, gray normal text
                    currentStyle = currentStyle.replace(/background-color:[^;]*;?/gi, '');
                    currentStyle = currentStyle.replace(/border-left:[^;]*;?/gi, '');
                    currentStyle = currentStyle.replace(/transition:[^;]*;?/gi, '');
                    currentStyle = (currentStyle.trim() + ' background-color: #FFFFFF !important; border-left: none !important; transition: background-color 0.2s; cursor: pointer;').trim();
                    $item.attr('style', currentStyle);
                    
                    // Ensure text is black and normal for read
                    const $textDiv = $item.find('.mb-1').first();
                    if ($textDiv.length) {
                        let textStyle = $textDiv.attr('style') || '';
                        textStyle = textStyle.replace(/color:[^;]*;?/gi, '');
                        textStyle = textStyle.replace(/font-weight:[^;]*;?/gi, '');
                        textStyle = (textStyle.trim() + ' font-size: 0.95rem; line-height: 1.3; color: #000000 !important; font-weight: 400 !important;').trim();
                        $textDiv.attr('style', textStyle);
                        // Remove any classes that might override
                        $textDiv.removeClass('font-weight-bold text-dark');
                    }
                }
            });
        }, 100);

        // Add click handlers for notification items
        $('.notification-item').on('click', function() {
            const bookingNumber = $(this).data('booking-number');
            const bookingID = $(this).data('booking-id');

            // Redirect to booking dashboard with booking number search
            if (bookingNumber) {
                window.location.href = HOST_URL + 'Booking?booking_number=' + encodeURIComponent(bookingNumber);
            } else {
                window.location.href = HOST_URL + 'Booking';
            }
        });

        // Hover effect
        $('.notification-item').hover(
            function() {
                const isRead = $(this).data('is-read') === 'true' || $(this).data('is-read') === true;
                const hoverColor = isRead ? '#F3F6F9' : '#E8F3FF'; // Slightly darker blue hover for unread
                const currentStyle = $(this).attr('style') || '';
                const newStyle = currentStyle.replace(/background-color:[^;]*;?/gi, '') + ' background-color: ' + hoverColor + ' !important;';
                $(this).attr('style', newStyle.trim());
            },
            function() {
                const isRead = $(this).data('is-read') === 'true' || $(this).data('is-read') === true;
                const originalColor = isRead ? '#FFFFFF' : '#F0F7FF'; // Restore original color
                const currentStyle = $(this).attr('style') || '';
                const newStyle = currentStyle.replace(/background-color:[^;]*;?/gi, '') + ' background-color: ' + originalColor + ' !important;';
                $(this).attr('style', newStyle.trim());
            }
        );
    }


    /**
     * Update notifications visual styling to read (backend already marked them as read)
     * This is called after a delay to show unread styling first
     */
    function updateNotificationsToReadStyle() {
        // Update all notification items to read styling (instant, no transition)
        $('.notification-item').each(function() {
            const $item = $(this);
            const isUnread = $item.data('is-read') === 'false' || $item.data('is-read') === false;
            
            if (isUnread) {
                // Remove transition temporarily for instant change
                const currentStyle = $item.attr('style') || '';
                const newStyle = currentStyle.replace(/transition:[^;]*;?/gi, '')
                    .replace(/background-color:[^;]*;?/gi, '')
                    .replace(/border-left:[^;]*;?/gi, '')
                    + ' background-color: #FFFFFF !important; border-left: none !important;';
                $item.attr('style', newStyle.trim());
                
                // Update text color and weight (black text, normal weight for read)
                const $textDiv = $item.find('.mb-1').first();
                const textStyle = $textDiv.attr('style') || '';
                const newTextStyle = textStyle.replace(/color:[^;]*;?/gi, '')
                    .replace(/font-weight:[^;]*;?/gi, '')
                    + ' color: #000000 !important; font-weight: 400 !important;';
                $textDiv.attr('style', newTextStyle.trim());
                
                $item.data('is-read', 'true');
            }
        });
        
        // Update unread count
        updateUnreadCount();
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

