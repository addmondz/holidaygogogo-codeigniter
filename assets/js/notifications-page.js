/**
 * Notifications Page Module
 *
 * Drives the full-page tabbed notification view at /Notification.
 * Reuses rendering helpers from notifications.js via window.NotificationsCore.
 */

(function() {
    'use strict';

    if (typeof $ === 'undefined') return;

    var PAGE_LIMIT = 20;
    var state = {};
    var lastSeenCounts = {};

    function ready() {
        var $tabs = $('#notif-tabs');
        if (!$tabs.length) return;
        if (!window.NotificationsCore) {
            console.error('NotificationsCore is not available; notifications-page.js requires notifications.js to load first.');
            return;
        }

        var initial = window.NOTIFICATION_PAGE_INITIAL || { tab: 'all', filter: 'all' };

        $tabs.find('a.nav-link').each(function() {
            var category = $(this).data('category');
            state[category] = {
                offset: 0,
                hasMore: false,
                loading: false,
                loaded: false,
                filter: initial.filter || 'all'
            };
        });

        bindEvents();

        // Sync filter buttons (in case ?filter=… was passed)
        $('#notif-read-filter button').removeClass('active');
        $('#notif-read-filter button[data-filter="' + initial.filter + '"]').addClass('active');

        // Load the initial tab
        loadCategory(initial.tab, false);
    }

    function bindEvents() {
        // Tab switch — lazy load on first activation
        $('#notif-tabs').on('shown.bs.tab', 'a.nav-link[data-category]', function() {
            var category = $(this).data('category');
            if (!state[category].loaded) {
                loadCategory(category, false);
            }
        });

        // Read/Unread filter — refetch active tab
        $('#notif-read-filter').on('click', 'button', function() {
            var $btn = $(this);
            if ($btn.hasClass('active')) return;
            $('#notif-read-filter button').removeClass('active');
            $btn.addClass('active');
            var newFilter = $btn.data('filter');
            var category = activeCategory();
            // Reset only the active tab; other tabs' caches stay valid since this filter
            // is per-tab — but we apply it to the active tab right now and on tabs as they
            // are re-opened (we mark them as not-yet-loaded with the new filter sticky).
            state[category].filter = newFilter;
            state[category].offset = 0;
            state[category].hasMore = false;
            state[category].loaded = false;
            loadCategory(category, false);
        });

        // Per-category Mark as read
        $('.tab-content').on('click', '.mark-category-as-read', function(e) {
            e.stopPropagation();
            var $btn = $(this);
            if ($btn.attr('data-busy') === '1') return;
            var category = $btn.data('category');
            $btn.attr('data-busy', '1').css('pointer-events', 'none');
            $.ajax({
                url: HOST_URL + 'Notification/Mark_Category_As_Read',
                type: 'POST',
                dataType: 'json',
                data: { category: category }
            }).done(function(resp) {
                if (resp && resp.success) {
                    // Restyle visible items in that pane
                    $('#notif-tab-' + category).find('.notification-item').each(function() {
                        var $item = $(this);
                        if ($item.attr('data-is-read') !== 'true') {
                            window.NotificationsCore.applyNotificationStyle($item, false);
                            $item.find('.notification-toggle-read')
                                .text('Unread')
                                .css('color', '#F5A623')
                                .attr('title', 'Mark as unread');
                        }
                    });
                    invalidateOtherTabs();
                    refreshCategoryBadges();
                }
            }).always(function() {
                $btn.removeAttr('data-busy').css('pointer-events', '');
            });
        });

        // Load More per-tab
        $('.tab-content').on('click', '.notif-load-more', function(e) {
            e.stopPropagation();
            var category = $(this).data('category');
            var s = state[category];
            if (!s || s.loading || !s.hasMore) return;
            loadCategory(category, true);
        });

        // After any per-item read/unread toggle (handled in notifications.js),
        // refresh tab badges and invalidate other tabs so they refetch on next view.
        // (The clicked item is updated in-place by notifications.js, so the active
        // tab's cache stays valid; only sibling tabs may now contain stale styling.)
        $(document).on('click', '.notification-list-page .notification-toggle-read', function() {
            invalidateOtherTabs();
            setTimeout(refreshCategoryBadges, 300);
        });

        // Global "Mark all as read" — invalidate every tab's cache so reopening reflects the new state
        $(document).on('click', '.card-toolbar .mark-all-notifications-read', function() {
            Object.keys(state).forEach(function(cat) {
                state[cat].loaded = false;
                state[cat].offset = 0;
                state[cat].hasMore = false;
            });
            // Refresh the currently visible tab right now
            var current = activeCategory();
            if (current) {
                loadCategory(current, false);
            }
            setTimeout(refreshCategoryBadges, 300);
        });
    }

    function activeCategory() {
        var $active = $('#notif-tabs a.nav-link.active');
        return $active.length ? $active.data('category') : 'all';
    }

    // Mark every tab except the current one as needing a fresh fetch when next viewed.
    // The active tab is updated in-place by its own action handler.
    function invalidateOtherTabs() {
        var current = activeCategory();
        Object.keys(state).forEach(function(cat) {
            if (cat !== current) {
                state[cat].loaded  = false;
                state[cat].offset  = 0;
                state[cat].hasMore = false;
            }
        });
    }

    function loadCategory(category, append) {
        var s = state[category];
        if (!s || s.loading) return;
        s.loading = true;

        var $pane = $('#notif-tab-' + category);
        var $list = $pane.find('.notification-list-page');
        var $loadMoreWrap = $pane.find('.notif-load-more-wrapper');

        if (!append) {
            s.offset = 0;
            s.hasMore = false;
            $list.html(
                '<div class="text-center p-10">' +
                    '<div class="spinner spinner-primary spinner-lg"></div>' +
                    '<div class="mt-3">Loading...</div>' +
                '</div>'
            );
            $loadMoreWrap.addClass('d-none');
        } else {
            $list.find('.notif-load-more').text('Loading...').css('pointer-events', 'none');
        }

        var params = {
            limit: PAGE_LIMIT,
            offset: s.offset,
            read_filter: s.filter
        };
        if (category !== 'all') params.category = category;

        $.ajax({
            url: HOST_URL + 'Notification/Get_Notifications',
            type: 'GET',
            dataType: 'json',
            data: params
        }).done(function(resp) {
            if (!resp || !resp.success) {
                $list.html('<div class="text-center p-10"><div class="text-danger">Failed to load notifications</div></div>');
                return;
            }
            var items = resp.notifications || [];
            s.hasMore = items.length === PAGE_LIMIT;

            if (!append) {
                if (items.length === 0) {
                    $list.html(emptyStateHtml(s.filter));
                } else {
                    $list.html(window.NotificationsCore.buildNotificationsHtml(items));
                    s.offset = items.length;
                }
            } else {
                if (items.length > 0) {
                    var $tmp = $('<div></div>').html(window.NotificationsCore.buildNotificationsHtml(items));
                    $list.append($tmp.children());
                    s.offset += items.length;
                }
            }
            $loadMoreWrap.toggleClass('d-none', !s.hasMore);
            // Toggle per-category mark-read button
            var $catBtn = $pane.find('.mark-category-as-read');
            if ($catBtn.length) {
                var hasUnread = $list.find('.notification-item[data-is-read="false"]').length > 0;
                $catBtn.toggleClass('d-none', !hasUnread);
            }
            s.loaded = true;
        }).fail(function() {
            if (append) {
                $list.find('.notif-load-more').text('Failed — retry').css('pointer-events', '');
            } else {
                $list.html('<div class="text-center p-10"><div class="text-danger">Failed to load notifications</div></div>');
            }
        }).always(function() {
            s.loading = false;
        });
    }

    function emptyStateHtml(filter) {
        var msg = 'No notifications';
        if (filter === 'unread') msg = 'All caught up — no unread notifications';
        else if (filter === 'read') msg = 'No read notifications yet';
        return (
            '<div class="text-center p-10">' +
                '<i class="la la-bell-slash la-3x text-muted mb-3"></i>' +
                '<div class="text-muted">' + window.NotificationsCore.escapeHtml(msg) + '</div>' +
            '</div>'
        );
    }

    function refreshCategoryBadges() {
        $.ajax({
            url: HOST_URL + 'Notification/Get_Category_Counts',
            type: 'GET',
            dataType: 'json'
        }).done(function(resp) {
            if (!resp || !resp.success || !resp.counts) return;
            var counts = resp.counts;
            lastSeenCounts = counts;
            $('#notif-tabs .notif-tab-badge').each(function() {
                var $badge = $(this);
                var cat = $badge.data('category');
                var n = (cat === 'all') ? (counts.total || 0) : (counts[cat] || 0);
                $badge.text(n);
                $badge.toggle(n > 0);
            });
        });
    }

    $(document).ready(ready);
})();
