<?php $this->load->helper('notification_category'); ?>
<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong>Notifications</strong>
                    </h3>
                </div>
                <div class="card-toolbar">
                    <div class="btn-group btn-group-sm mr-3" role="group" id="notif-read-filter" aria-label="Read state filter">
                        <button type="button" class="btn btn-light-primary <?php echo $initial_filter === 'all' ? 'active' : ''; ?>" data-filter="all">All</button>
                        <button type="button" class="btn btn-light-primary <?php echo $initial_filter === 'unread' ? 'active' : ''; ?>" data-filter="unread">Unread</button>
                        <button type="button" class="btn btn-light-primary <?php echo $initial_filter === 'read' ? 'active' : ''; ?>" data-filter="read">Read</button>
                    </div>
                    <a href="javascript:;" class="btn btn-sm btn-light-primary mark-all-notifications-read">
                        <i class="la la-check-double"></i> Mark all as read
                    </a>
                </div>
            </div>

            <ul class="nav nav-tabs nav-tabs-line nav-tabs-bold px-5 pt-2 mb-0 flex-nowrap" role="tablist" id="notif-tabs" style="overflow-x:auto; flex-wrap:nowrap;">
                <?php
                    $tabs = array_merge(array('all'), $category_keys);
                    foreach ($tabs as $tab_key):
                        $is_active = ($tab_key === $initial_tab);
                        $count = isset($category_counts[$tab_key === 'all' ? 'total' : $tab_key])
                            ? (int)$category_counts[$tab_key === 'all' ? 'total' : $tab_key]
                            : 0;
                        $label = notification_category_label($tab_key);
                ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $is_active ? 'active' : ''; ?>"
                           data-toggle="tab"
                           href="#notif-tab-<?php echo $tab_key; ?>"
                           role="tab"
                           data-category="<?php echo $tab_key; ?>">
                            <?php echo $label; ?>
                            <span class="label label-rounded label-light-primary ml-2 notif-tab-badge"
                                  data-category="<?php echo $tab_key; ?>"
                                  style="<?php echo $count > 0 ? '' : 'display:none;'; ?>">
                                <?php echo $count; ?>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="card-body p-0">
                <div class="tab-content">
                    <?php foreach ($tabs as $tab_key): $is_active = ($tab_key === $initial_tab); ?>
                        <div class="tab-pane fade <?php echo $is_active ? 'show active' : ''; ?>"
                             id="notif-tab-<?php echo $tab_key; ?>"
                             role="tabpanel"
                             data-category="<?php echo $tab_key; ?>">
                            <?php if ($tab_key !== 'all'): ?>
                            <div class="d-flex justify-content-end p-3">
                                <a href="javascript:;"
                                   class="btn btn-sm btn-light-primary mark-category-as-read d-none"
                                   data-category="<?php echo $tab_key; ?>">
                                    <i class="la la-check"></i> Mark this category as read
                                </a>
                            </div>
                            <?php endif; ?>
                            <div class="notification-list-page" data-category="<?php echo $tab_key; ?>">
                                <div class="text-center p-10 notif-tab-placeholder">
                                    <div class="text-muted">Click this tab to load notifications.</div>
                                </div>
                            </div>
                            <div class="text-center py-3 border-top d-none notif-load-more-wrapper" data-category="<?php echo $tab_key; ?>">
                                <a href="javascript:;"
                                   class="btn btn-sm btn-light-primary font-weight-bold notif-load-more"
                                   data-category="<?php echo $tab_key; ?>">Load More</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    window.NOTIFICATION_PAGE_INITIAL = {
        tab:    <?php echo json_encode($initial_tab); ?>,
        filter: <?php echo json_encode($initial_filter); ?>
    };
</script>
