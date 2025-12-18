<?php
function renderPagination($totalPages, $currentPage, $query, $maxPagesToShow = 7) {
    if ($totalPages <= 1) return;

    $half = floor($maxPagesToShow / 2);
    $start = max(1, $currentPage - $half);
    $end = min($totalPages, $currentPage + $half);

    if ($currentPage <= $half) {
        $end = min($totalPages, $maxPagesToShow);
    }
    if ($currentPage + $half > $totalPages) {
        $start = max(1, $totalPages - $maxPagesToShow + 1);
    }

    $query['page'] = 1;
    echo '<a href="?' . http_build_query($query) . '" class="btn btn-sm ' . ($currentPage == 1 ? 'btn-primary disabled' : 'btn-light') . '">&laquo; First</a> ';

    $prevPage = max(1, $currentPage - 1);
    $query['page'] = $prevPage;
    echo '<a href="?' . http_build_query($query) . '" class="btn btn-sm ' . ($currentPage == 1 ? 'btn-primary disabled' : 'btn-light') . '">&lsaquo; Prev</a> ';

    if ($start > 1) {
        echo '<span class="btn btn-sm btn-light disabled">...</span> ';
    }

    for ($i = $start; $i <= $end; $i++) {
        $query['page'] = $i;
        $activeClass = ($currentPage == $i) ? 'btn-primary' : 'btn-light';
        echo '<a href="?' . http_build_query($query) . '" class="btn btn-sm ' . $activeClass . '">' . $i . '</a> ';
    }

    if ($end < $totalPages) {
        echo '<span class="btn btn-sm btn-light disabled">...</span> ';
    }

    $nextPage = min($totalPages, $currentPage + 1);
    $query['page'] = $nextPage;
    echo '<a href="?' . http_build_query($query) . '" class="btn btn-sm ' . ($currentPage == $totalPages ? 'btn-primary disabled' : 'btn-light') . '">Next &rsaquo;</a> ';

    $query['page'] = $totalPages;
    echo '<a href="?' . http_build_query($query) . '" class="btn btn-sm ' . ($currentPage == $totalPages ? 'btn-primary disabled' : 'btn-light') . '">Last &raquo;</a>';
}
?>
<link href="path_to/kt_datatable.bundle.css" rel="stylesheet" type="text/css" />
<script src="path_to/kt_datatable.bundle.js"></script>
<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong>Customer Records</strong>
                    </h3>
                </div>
                <div class="card-toolbar">
                    <a href="<?php echo base_url('Customer/Create'); ?>" class="btn btn-primary font-weight-bold mr-1 mb-2" style="width:180px;">
                        <i class="la la-user-alt"></i>Create Customer
                    </a>
                    <?php $current_url = base_url($_SERVER['REQUEST_URI']); ?>
                    <a href="<?php if(strpos($current_url, '?') == true) { echo base_url('Customer/Download?') . (explode('?', $current_url))[1]; } else { echo base_url('Customer/Download'); } ?>" class="btn btn-light-warning font-weight-bold mb-2" style="width:180px;">
                        <i class="las la-arrow-circle-down"></i>Customer Records
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="accordion accordion-solid accordion-toggle-plus">
                    <div class="card">
                        <div class="card-header">
                            <div id="customer_header" data-toggle="collapse" data-target="#customer_info" class="card-title collapsed" style="font-size:13px;">Filter By Customer Information</div>
                        </div>
                        <div id="customer_info" class="collapse">
                            <div class="card-body">
                                <form action="<?php echo base_url('Customer') ?>" method="get" class="form">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Name</label>
                                                <div class="input-icon">
                                                    <input type="text" name="name" value="<?php if(!empty($this->input->get('name'))) { echo strtoupper($this->input->get('name')); } ?>" autocomplete="off" class="form-control">
                                                    <span><i class="la la-user-alt"></i></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Phone Number</label>
                                                <div class="input-icon">
                                                    <input type="text" name="phone_number" value="<?php if(!empty($this->input->get('phone_number'))) { echo $this->input->get('phone_number'); } ?>" autocomplete="off" class="form-control">
                                                    <span><i class="la la-phone"></i></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Customer Code</label>
                                                <div class="input-icon">
                                                    <input type="text" name="CustomerCode" value="<?php if(!empty($this->input->get('CustomerCode'))) { echo strtoupper($this->input->get('CustomerCode')); } ?>" autocomplete="off" class="form-control">
                                                    <span><i class="la la-clipboard-list"></i></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Chat Language</label>
                                                <div class="input-icon">
                                                    <input type="text" name="ChatLanguage" value="<?php if(!empty($this->input->get('ChatLanguage'))) { echo strtoupper($this->input->get('ChatLanguage')); } ?>" autocomplete="off" class="form-control">
                                                    <span><i class="la la-language"></i></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <input type="submit" value="Filter" class="btn btn-light-success font-weight-bold" style="width:80px;">
                                    <input type="button" id="reset" value="Reset" class="btn btn-light-primary font-weight-bold" style="width:80px;">
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <br><br>
                <div class="dataTables_wrapper dt-bootstrap4 no-footer" <?php if(empty($customers)) { echo 'style="overflow-x:auto;"'; } ?>>
                    <table id="kt_datatable" class="table table-bordered table-head-custom table-checkable dataTable no-footer dtr-inline">
                        <thead>
                            <tr>
                                <th style="text-align:center;">No.</th>
                                <th style="text-align:center;">Name</th>
                                <th style="text-align:center;">Phone Number</th>
                                <th style="text-align:center;">Customer Code</th>
                                <th style="text-align:center;">Chat Language</th>
                                <th style="text-align:center;">Created Date</th>
                                <th style="text-align:center;">Autocount Sync Status</th>
                                <th class="action" style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($customers)) { ?>
                                <td colspan="8" style="text-align:center; padding-top:10px; padding-bottom:10px;">Customer Records Not Found</td>
                            <?php } else { ?>
                                <?php 
                                    $count = ($page - 1) * $limit + 1; 
                                    foreach($customers as $customer) { 
                                ?>
                                    <tr>
                                        <td style="text-align:center; padding-top:15px; padding-bottom:15px;"><?php echo $count++; ?></td>
                                        <td style="text-align:center;"><?php echo $customer->name; ?></td>
                                        <td style="text-align:center;"><?php echo $customer->phone_number; ?></td>
                                        <td style="text-align:center;"><?php echo $customer->CustomerCode; ?></td>
                                        <td style="text-align:center;"><?php echo $customer->ChatLanguage; ?></td>
                                        <td style="text-align:center;"><?php echo date('Y-m-d', strtotime($customer->created_at)); ?></td>
                                        <td style="text-align:center;">
                                            <?php 
                                                $statusColor = '#000000';
                                                $statusText  = 'UNKNOWN';

                                                switch ($customer->AutocountSyncStatus) {
                                                    case 'P': $statusColor = '#808080'; $statusText = 'Pending'; break;
                                                    case 'S': $statusColor = '#50C878'; $statusText = 'Synced'; break;
                                                    case 'F': $statusColor = '#FF4500'; $statusText = 'Failed'; break;
                                                }

                                                $tooltipAttr = '';
                                                if (!empty($customer->AutocountSyncMessage)) {
                                                    $decoded = json_decode($customer->AutocountSyncMessage, true);
                                                    if (json_last_error() === JSON_ERROR_NONE) {
                                                        if (isset($decoded['error']) && $decoded['error'] === null) {
                                                            $tooltipText = "SUCCESS";
                                                        } elseif (isset($decoded['error']) && $decoded['error'] !== null) {
                                                            $tooltipText = "ERROR: " . (is_string($decoded['error']) ? $decoded['error'] : json_encode($decoded['error']));
                                                        } else {
                                                            $tooltipText = $customer->AutocountSyncMessage;
                                                        }
                                                    } else {
                                                        $tooltipText = $customer->AutocountSyncMessage;
                                                    }
                                                    $tooltipAttr = ' data-toggle="tooltip" data-placement="top" title="' . htmlspecialchars($tooltipText) . '"';
                                                }
                                            ?>
                                            <span class="font-weight-bold" style="color:<?= $statusColor ?>;" <?= $tooltipAttr ?>><?= $statusText ?></span>
                                        </td>
                                        <td style="text-align:center;">
                                            <div class="btn-group">
                                                <button type="button" data-toggle="dropdown" class="btn btn-light-primary btn-sm dropdown-toggle" style="padding-left:3px;"></button>
                                                <div class="dropdown-menu">
                                                    <!-- delete disable -- controller, model, autocount all done QA, if need just add button -->
                                                    <a href="<?php echo base_url('Customer/Update?customer_id=') . $customer->CustomerID; ?>" class="dropdown-item" style="font-size:11px;">Update Customer</a>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php } ?>
                            <?php } ?>
                        </tbody>
                    </table>

                   <div class="d-flex justify-content-between align-items-center mt-3">
    <?php if (!empty($customers)) {
        $start = ($page - 1) * $limit + 1;
        $end = min($page * $limit, $total);
    ?>
        <div class="text-left font-weight-bold" style="padding-left:15px;">
            Showing <?= $start ?> to <?= $end ?> of <?= $total ?> entries
        </div>
    <?php } ?>

    <div class="text-center">
        <?php
        $totalPages = ceil($total / $limit);
        $query = $_GET;
        unset($query['page']);

        // Only show pagination if more than 1 page
        if ($totalPages > 1):
            $maxPagesToShow = 7; // max page buttons to show
            $half = floor($maxPagesToShow / 2);
            $startPage = max(1, $page - $half);
            $endPage = min($totalPages, $page + $half);

            // Adjust start or end if near beginning or end
            if ($page <= $half) {
                $endPage = min($totalPages, $maxPagesToShow);
            }
            if ($page + $half > $totalPages) {
                $startPage = max(1, $totalPages - $maxPagesToShow + 1);
            }
        ?>

        <!-- First Page -->
        <?php
            $query['page'] = 1;
        ?>
        <a href="?<?= http_build_query($query) ?>" class="btn btn-sm <?= ($page == 1 ? 'btn-primary disabled' : 'btn-light') ?>">« First</a>

        <!-- Previous Page -->
        <?php
            $prevPage = max(1, $page - 1);
            $query['page'] = $prevPage;
        ?>
        <a href="?<?= http_build_query($query) ?>" class="btn btn-sm <?= ($page == 1 ? 'btn-primary disabled' : 'btn-light') ?>">‹ Prev</a>

        <!-- Ellipsis before -->
        <?php if ($startPage > 1): ?>
            <span class="btn btn-sm btn-light disabled">...</span>
        <?php endif; ?>

        <!-- Page Numbers -->
        <?php for ($i = $startPage; $i <= $endPage; $i++):
            $query['page'] = $i;
        ?>
            <a href="?<?= http_build_query($query) ?>" class="btn btn-sm <?= ($page == $i ? 'btn-primary' : 'btn-light') ?>"><?= $i ?></a>
        <?php endfor; ?>

        <!-- Ellipsis after -->
        <?php if ($endPage < $totalPages): ?>
            <span class="btn btn-sm btn-light disabled">...</span>
        <?php endif; ?>

        <!-- Next Page -->
        <?php
            $nextPage = min($totalPages, $page + 1);
            $query['page'] = $nextPage;
        ?>
        <a href="?<?= http_build_query($query) ?>" class="btn btn-sm <?= ($page == $totalPages ? 'btn-primary disabled' : 'btn-light') ?>">Next ›</a>

        <!-- Last Page -->
        <?php
            $query['page'] = $totalPages;
        ?>
        <a href="?<?= http_build_query($query) ?>" class="btn btn-sm <?= ($page == $totalPages ? 'btn-primary disabled' : 'btn-light') ?>">Last »</a>

        <?php endif; ?>
    </div>
</div>


                </div>
            </div>
        </div>
    </div>
</div>
<script>
$(document).ready(function() {
    $('#kt_datatable').KTDatatable({
        sortable: true,        // enable sorting
        pagination: true,      // enable pagination
        search: {
            input: $('#search_input'), // optional search input if you want custom filtering
            key: 'generalSearch'
        },
        // Other options you might want to configure:
        // data source, page size, etc.
    });
});
</script>

<script>
    <?php if(!empty($this->input->get('name')) || !empty($this->input->get('phone_number')) || !empty($this->input->get('CustomerCode')) || !empty($this->input->get('ChatLanguage'))) { ?>
        $('#customer_header').click();
    <?php } ?>

    $('#reset').click(function() {
        Reset('<?php echo base_url('Customer'); ?>');
    });
</script>
