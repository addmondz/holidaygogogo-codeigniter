<?php
$items = isset($items) ? $items : array();
$currency_options = isset($currency_options) ? $currency_options : array();
$categories = isset($categories) ? $categories : array();
$item_filters = isset($item_filters) ? $item_filters : array('name' => '', 'category' => '');
$filter_open = !empty($item_filters['name']) || !empty($item_filters['category']);

if (!function_exists('costing_item_category_label')) {
    function costing_item_category_label($categories, $key)
    {
        return isset($categories[$key]) ? $categories[$key] : ucwords(str_replace('_', ' ', (string) $key));
    }
}
?>

<style>
    .costing-item-toolbar { gap: 10px; }
    .costing-item-cat-mark { min-width: 120px; justify-content: center; }
</style>

<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;"><strong>Costing Item Master</strong></h3>
                </div>
                <div class="card-toolbar costing-item-toolbar d-flex flex-wrap">
                    <a href="<?php echo base_url('Costing'); ?>" class="btn btn-light-primary font-weight-bold">
                        <i class="la la-arrow-left"></i>Back To Packages
                    </a>
                    <button type="button" class="btn btn-primary font-weight-bold" id="add_item_button" data-toggle="modal" data-target="#item_modal">
                        <i class="la la-plus"></i>Add Item
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if ($this->session->flashdata('message_success')) { ?>
                    <div class="alert alert-success"><?php echo html_escape($this->session->flashdata('message_success')); ?></div>
                <?php } ?>
                <?php if ($this->session->flashdata('message_error')) { ?>
                    <div class="alert alert-danger"><?php echo html_escape($this->session->flashdata('message_error')); ?></div>
                <?php } ?>

                <div class="d-flex justify-content-between align-items-start flex-wrap mb-6">
                    <div class="mb-3">
                        <div class="font-size-h5 font-weight-bold text-dark mb-2">Item Setup</div>
                        <div class="text-muted">Reusable cost items, each in one of the five categories, with a default currency and unit cost.</div>
                    </div>
                </div>

                <div class="accordion accordion-solid accordion-toggle-plus mb-6">
                    <div class="card">
                        <div class="card-header">
                            <div data-toggle="collapse" data-target="#item_filter_body" class="card-title <?php echo $filter_open ? '' : 'collapsed'; ?>" style="font-size:13px;">Filter Items</div>
                        </div>
                        <div id="item_filter_body" class="collapse <?php echo $filter_open ? 'show' : ''; ?>">
                            <div class="card-body">
                                <form action="<?php echo base_url('Costing_Item'); ?>" method="get" class="form">
                                    <div class="row">
                                        <div class="col-md-5">
                                            <div class="form-group">
                                                <label>Name</label>
                                                <input type="text" name="item_name" value="<?php echo html_escape($item_filters['name']); ?>" autocomplete="off" class="form-control">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Category</label>
                                                <select name="item_category" class="form-control">
                                                    <option value="">All</option>
                                                    <?php foreach ($categories as $key => $label) { ?>
                                                        <option value="<?php echo html_escape($key); ?>" <?php echo $item_filters['category'] === $key ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-light-success font-weight-bold" style="width:80px;">Filter</button>
                                    <a href="<?php echo base_url('Costing_Item'); ?>" class="btn btn-light-primary font-weight-bold" style="width:80px;">Reset</a>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dataTables_wrapper dt-bootstrap4 no-footer">
                    <table class="table table-bordered table-head-custom table-checkable dataTable no-footer">
                        <thead>
                            <tr>
                                <th style="text-align:center; width:70px;">No.</th>
                                <th style="text-align:center; width:160px;">Category</th>
                                <th style="text-align:center;">Item Name</th>
                                <th style="text-align:center; width:120px;">Currency</th>
                                <th style="text-align:center; width:180px;">Default Unit Cost</th>
                                <th class="action" style="text-align:center; width:200px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)) { ?>
                                <tr><td colspan="6" style="text-align:center; padding:16px;">Items Not Found</td></tr>
                            <?php } else { $count = 1; ?>
                                <?php foreach ($items as $item) { ?>
                                    <tr>
                                        <td style="text-align:center; padding:16px 8px;"><?php echo $count; ?></td>
                                        <td style="text-align:center;">
                                            <span class="label label-lg label-light-primary label-inline costing-item-cat-mark"><?php echo html_escape(costing_item_category_label($categories, $item['category'])); ?></span>
                                        </td>
                                        <td><div class="font-weight-bold text-dark"><?php echo html_escape($item['name']); ?></div></td>
                                        <td style="text-align:center;"><?php echo html_escape($item['currency_code'] !== null ? $item['currency_code'] : '-'); ?></td>
                                        <td style="text-align:center;"><span class="font-weight-bold text-dark"><?php echo html_escape($item['currency_code']); ?> <?php echo number_format((float) $item['default_unit_cost'], 2); ?></span></td>
                                        <td>
                                            <div class="d-flex justify-content-center flex-wrap" style="gap:6px;">
                                                <button type="button" class="btn btn-sm btn-light-primary font-weight-bold edit-item" data-item="<?php echo html_escape(json_encode($item)); ?>">Edit</button>
                                                <form method="post" action="<?php echo base_url('Costing_Item/Delete_Item'); ?>" class="delete-item-form">
                                                    <input type="hidden" name="item_id" value="<?php echo (int) $item['id']; ?>">
                                                    <button type="button" class="btn btn-sm btn-light-danger font-weight-bold delete-item-button" data-item-name="<?php echo html_escape($item['name']); ?>">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php $count++; } ?>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="item_modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form method="post" action="<?php echo base_url('Costing_Item/Save_Item'); ?>" id="item_form_modal">
                <input type="hidden" name="item_id" id="modal_item_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="item_modal_title">Add Item</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><i aria-hidden="true" class="ki ki-close"></i></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" id="modal_item_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category" id="modal_item_category" class="form-control" required>
                            <?php foreach ($categories as $key => $label) { ?>
                                <option value="<?php echo html_escape($key); ?>"><?php echo html_escape($label); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Default Currency</label>
                        <select name="default_currency_id" id="modal_item_currency" class="form-control" required>
                            <?php foreach ($currency_options as $currency) { ?>
                                <option value="<?php echo (int) $currency['id']; ?>"><?php echo html_escape($currency['code']); ?> — <?php echo html_escape($currency['name']); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-group mb-0">
                        <label>Default Unit Cost</label>
                        <input type="number" step="0.01" min="0" name="default_unit_cost" id="modal_item_cost" class="form-control" value="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light-primary font-weight-bold" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary font-weight-bold">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function () {
        var title = document.getElementById('item_modal_title');
        var idInput = document.getElementById('modal_item_id');
        var nameInput = document.getElementById('modal_item_name');
        var categoryInput = document.getElementById('modal_item_category');
        var currencyInput = document.getElementById('modal_item_currency');
        var costInput = document.getElementById('modal_item_cost');

        function resetModal() {
            title.textContent = 'Add Item';
            idInput.value = '';
            nameInput.value = '';
            categoryInput.selectedIndex = 0;
            currencyInput.selectedIndex = 0;
            costInput.value = '0';
        }

        document.getElementById('add_item_button').addEventListener('click', resetModal);
        $('#item_modal').on('hidden.bs.modal', resetModal);

        $(document).on('click', '.edit-item', function () {
            try {
                var item = JSON.parse(this.getAttribute('data-item'));
                title.textContent = 'Edit Item';
                idInput.value = item.id || '';
                nameInput.value = item.name || '';
                categoryInput.value = item.category || '';
                currencyInput.value = item.default_currency_id || '';
                costInput.value = item.default_unit_cost || '0';
                $('#item_modal').modal('show');
            } catch (e) { console.error('Unable to read item data.', e); }
        });

        $(document).on('click', '.delete-item-button', function () {
            var form = this.closest('form');
            var name = this.getAttribute('data-item-name') || 'this item';
            if (!form) { return; }
            if (typeof Swal === 'undefined') { if (confirm('Delete ' + name + '?')) { form.submit(); } return; }
            Swal.mixin({ customClass: { confirmButton: 'btn btn-danger', cancelButton: 'btn btn-light-primary' }, buttonsStyling: false })
                .fire({ title: 'Delete Item: ' + name + '?', text: 'This item will be removed from the master list.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, Delete', cancelButtonText: 'Cancel', reverseButtons: true })
                .then(function (r) { if (r.isConfirmed) { form.submit(); } });
        });
    })();
</script>
