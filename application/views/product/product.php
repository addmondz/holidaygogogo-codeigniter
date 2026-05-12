<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong><?php if(current_url() == base_url('Product/Create')) { echo 'New Product Record'; } else { echo 'Product Record : ' . $ProductCode; } ?></strong>
                    </h3>
                </div>
            </div>
            <div class="card-body">
                <form id="form">
                    <strong>Product Information :</strong>
                    <br><br>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Name
                                    <span style="color:red;">*</span>
                                </label>
                                <div class="input-icon">
                                    <input type="text" id="Name" <?php if(current_url() == base_url('Product/Update')) { ?> value="<?php echo $Product; ?>" <?php } ?> autocomplete="off" maxlength="99" class="form-control">
                                    <span>
                                        <i class="la la-product-hunt"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Category
                                    <?php if(current_url() == base_url('Product/Create')) { ?><span style="color:red;">*</span><?php } ?>
                                </label>
                                <?php if(current_url() == base_url('Product/Create')) { ?>
                                    <select id="CategoryID" data-live-search="true" class="form-control selectpicker">
                                        <option selected disabled data-icon="la la-clipboard-list font-size-lg bs-icon" value="">--SELECT CATEGORY--</option>
                                        <?php foreach($categories as $category) { ?>
                                            <option data-icon="la la-clipboard-list font-size-lg bs-icon" value="<?php echo $category->CategoryID; ?>"><?php echo $category->Name; ?></option>
                                        <?php } ?>
                                    </select>
                                <?php } else { ?>
                                    <div class="input-icon">
                                        <input disabled type="text" value="<?php echo $Category; ?>" class="form-control">
                                        <span>
                                            <i class="la la-clipboard-list"></i>
                                        </span>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Supplier
                                    <?php if(current_url() == base_url('Product/Create')) { ?><span style="color:red;">*</span><?php } ?>
                                </label>
                                <select id="SupplierID" data-live-search="true" class="form-control selectpicker">
                                    <option selected disabled data-icon="la la-user-alt font-size-lg bs-icon" value="">--SELECT SUPPLIER--</option>
                                    <?php foreach($suppliers as $supplier) { ?>
                                        <option <?php if(current_url() == base_url('Product/Update') && $supplier->SupplierID == $SupplierID) { echo 'selected'; } ?> data-icon="la la-user-alt font-size-lg bs-icon" value="<?php echo $supplier->SupplierID; ?>"><?php echo $supplier->Name; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Retail Price (RM)</label>
                                <div class="input-icon">
                                    <input type="text" id="RetailPrice" <?php if(current_url() == base_url('Product/Create')) { ?> value="" <?php } else { ?> value="<?php echo $RetailPrice; ?>" <?php } ?> autocomplete="off" onchange="Validate_Price('RetailPrice')" class="form-control">
                                    <span>
                                        <i class="la la-dollar"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Supplier Price (RM)</label>
                                <div class="input-icon">
                                    <input type="text" id="SupplierPrice" <?php if(current_url() == base_url('Product/Create')) { ?> value="" <?php } else { ?> value="<?php echo $SupplierPrice; ?>" <?php } ?> autocomplete="off" onchange="Validate_Price('SupplierPrice')" class="form-control">
                                    <span>
                                        <i class="la la-dollar"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Is Child/Infant</label>
                                <select id="is_child_or_infant" class="form-control">
                                    <option value="0" <?php if(!isset($is_child_or_infant) || $is_child_or_infant == 0) { echo 'selected'; } ?>>No</option>
                                    <option value="1" <?php if(isset($is_child_or_infant) && $is_child_or_infant == 1) { echo 'selected'; } ?>>Yes</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Has Supplier Deposit</label>
                                <select id="has_supplier_deposit" class="form-control">
                                    <option value="0" <?php if(!isset($has_supplier_deposit) || $has_supplier_deposit == 0) { echo 'selected'; } ?>>No</option>
                                    <option value="1" <?php if(isset($has_supplier_deposit) && $has_supplier_deposit == 1) { echo 'selected'; } ?>>Yes</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <?php if(isset($package_checklists)) {
                        // Get required checklist IDs
                        $required_ids = array();
                        foreach($package_checklists as $checklist) {
                            if(isset($checklist->is_required) && $checklist->is_required == 1) {
                                $required_ids[] = $checklist->ID;
                            }
                        }
                        
                        // Get selected IDs from JSON (preserves order from database) - empty for Create
                        $selected_ids = $selected_checklist_ids ?? array();
                        
                        // Ensure required ones are included (add at end if missing, to preserve existing order)
                        foreach($required_ids as $req_id) {
                            if(!in_array($req_id, $selected_ids)) {
                                $selected_ids[] = $req_id; // Add at end if missing
                            }
                        }
                        
                        // Create a lookup map for faster access
                        $checklist_map = array();
                        foreach($package_checklists as $checklist) {
                            $checklist_map[$checklist->ID] = $checklist;
                        }
                        
                        // Build chosen checklists in exact order from selected_ids (preserves JSON order)
                        $chosen_checklists = array();
                        if(!empty($selected_ids)) {
                            foreach($selected_ids as $checklist_id) {
                                if(isset($checklist_map[$checklist_id])) {
                                    $chosen_checklists[] = $checklist_map[$checklist_id];
                                }
                            }
                        }
                        
                        // Build not chosen checklists (ordered by ID)
                        $not_chosen_checklists = array();
                        foreach($package_checklists as $checklist) {
                            if(!in_array($checklist->ID, $selected_ids)) {
                                $not_chosen_checklists[] = $checklist;
                            }
                        }
                        usort($not_chosen_checklists, function($a, $b) {
                            return $a->ID - $b->ID;
                        });
                    ?>
                        <br>
                        <strong>Package Checklists :</strong>
                        <br><br>
                        <div class="row">
                            <!-- Left Column: Chosen Checklists (in order, draggable) -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label style="font-weight: 600; margin-bottom: 8px;">Chosen Checklists (Drag to reorder)</label>
                                    <div class="input-icon" style="margin-bottom: 10px;">
                                        <input type="text" id="search-chosen" placeholder="Search chosen checklists..." class="form-control form-control-sm" style="font-size: 12px;">
                                        <span><i class="la la-search"></i></span>
                                    </div>
                                    <div id="chosen-checklist-list" class="draggable-zone" style="min-height: 300px; max-height: 400px; overflow-y: auto; border: 1px solid #e4e6ef; border-radius: 4px; padding: 10px; background: #f8f9fa;">
                                        <?php if(empty($chosen_checklists)) { ?>
                                            <p class="text-muted" style="font-size: 11px; margin: 0; text-align: center; padding: 20px;">No checklists selected</p>
                                        <?php } else { ?>
                                            <?php foreach($chosen_checklists as $checklist) {
                                                $is_required = (isset($checklist->is_required) && $checklist->is_required == 1);
                                            ?>
                                                <div class="draggable checklist-item chosen-item" data-checklist-id="<?php echo $checklist->ID; ?>" data-is-required="<?php echo $is_required ? '1' : '0'; ?>" style="background: white; border: 1px solid #ddd; border-radius: 4px; padding: 8px; margin-bottom: 6px; cursor: move; display: flex; align-items: center; font-size: 11px;">
                                                    <span class="draggable-handle" style="margin-right: 8px; color: #999; cursor: grab;">
                                                        <i class="la la-bars"></i>
                                                    </span>
                                                    <?php if($is_required) { ?>
                                                        <span class="label label-sm label-light-info" style="font-size: 9px; padding: 3px 6px; margin-right: 8px; min-width: 55px; text-align: center; border-radius: 4px;">Required</span>
                                                    <?php } ?>
                                                    <span class="checklist-name" style="flex: 1; color: #333;"><?php echo htmlspecialchars($checklist->name); ?></span>
                                                    <?php if(!$is_required) { ?>
                                                        <button type="button" class="btn btn-sm btn-light-danger remove-checklist-btn" data-checklist-id="<?php echo $checklist->ID; ?>" style="padding: 2px 8px; font-size: 10px; margin-left: 8px;">
                                                            <i class="la la-times"></i>
                                                        </button>
                                                    <?php } ?>
                                                </div>
                                            <?php } ?>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Right Column: Not Chosen Checklists (ordered by ID) -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label style="font-weight: 600; margin-bottom: 8px;">Available Checklists</label>
                                    <div class="input-icon" style="margin-bottom: 10px;">
                                        <input type="text" id="search-available" placeholder="Search available checklists..." class="form-control form-control-sm" style="font-size: 12px;">
                                        <span><i class="la la-search"></i></span>
                                    </div>
                                    <div id="available-checklist-list" style="min-height: 300px; max-height: 400px; overflow-y: auto; border: 1px solid #e4e6ef; border-radius: 4px; padding: 10px; background: #f8f9fa;">
                                        <?php if(empty($not_chosen_checklists)) { ?>
                                            <p class="text-muted" style="font-size: 11px; margin: 0; text-align: center; padding: 20px;">All checklists are selected</p>
                                        <?php } else { ?>
                                            <?php foreach($not_chosen_checklists as $checklist) {
                                                $is_required = (isset($checklist->is_required) && $checklist->is_required == 1);
                                            ?>
                                                <div class="checklist-item available-item" data-checklist-id="<?php echo $checklist->ID; ?>" style="background: white; border: 1px solid #ddd; border-radius: 4px; padding: 8px; margin-bottom: 6px; display: flex; align-items: center; font-size: 11px;">
                                                    <span class="checklist-name" style="flex: 1; color: #333;"><?php echo htmlspecialchars($checklist->name); ?></span>
                                                    <button type="button" class="btn btn-sm btn-light-success add-checklist-btn" data-checklist-id="<?php echo $checklist->ID; ?>" data-checklist-name="<?php echo htmlspecialchars($checklist->name); ?>" data-is-required="<?php echo $is_required ? '1' : '0'; ?>" style="padding: 2px 8px; font-size: 10px; margin-left: 8px;">
                                                        <i class="la la-plus"></i> Add
                                                    </button>
                                                </div>
                                            <?php } ?>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                    <div class="d-flex justify-content-between border-top pt-5">
                        <a class="btn btn-light-primary font-weight-bold d-flex align-items-center justify-content-center px-9 py-4" href="<?php echo base_url('Product'); ?>"><i class="la la-arrow-left"></i> Back to List</a>
                        <input type="button" value="<?php if(current_url() == base_url('Product/Create')) { echo 'Create Product'; } else { echo 'Update Product'; } ?>" class="btn btn-success font-weight-bold px-9 py-4" style="width:180px; margin-left:auto;">
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    <?php
        // Find deposit checklist ID for auto-add behavior
        $deposit_checklist_id = null;
        $deposit_checklist_name = '';
        if(isset($package_checklists)) {
            foreach($package_checklists as $checklist) {
                if(strpos($checklist->name, 'Payment Out To Supplier (deposit)') !== false) {
                    $deposit_checklist_id = $checklist->ID;
                    $deposit_checklist_name = $checklist->name;
                    break;
                }
            }
        }
    ?>

    <?php if(isset($package_checklists)) {
        // Calculate final selected_ids (with required ones included) for JavaScript
        $js_selected_ids = $selected_checklist_ids ?? array();
        $js_required_ids = array();
        foreach($package_checklists as $checklist) {
            if(isset($checklist->is_required) && $checklist->is_required == 1) {
                $js_required_ids[] = $checklist->ID;
            }
        }
        foreach($js_required_ids as $req_id) {
            if(!in_array($req_id, $js_selected_ids)) {
                $js_selected_ids[] = $req_id;
            }
        }
    ?>
    // Initialize drag and drop for chosen checklists
    var chosenChecklists = <?php echo json_encode($js_selected_ids); ?>;
    var sortableInstance = null;
    
    // Initialize Sortable for chosen checklists
    function initSortable() {
        var chosenList = document.getElementById('chosen-checklist-list');
        if(chosenList && typeof Sortable !== 'undefined') {
            if(sortableInstance) {
                sortableInstance.destroy();
            }
            sortableInstance = new Sortable.default(chosenList, {
                draggable: '.draggable',
                handle: '.draggable-handle',
                mirror: {
                    appendTo: 'body',
                    constrainDimensions: true
                },
                onEnd: function(evt) {
                    updateChosenChecklistsOrder();
                    $('#form').dirty('setDirty');
                }
            });
        }
    }
    
    // Update chosen checklists order after drag
    function updateChosenChecklistsOrder() {
        chosenChecklists = [];
        $('#chosen-checklist-list .chosen-item').each(function() {
            var checklistId = parseInt($(this).data('checklist-id'));
            if(checklistId) {
                chosenChecklists.push(checklistId);
            }
        });
    }
    
    // Initialize on document ready
    $(document).ready(function() {
        initSortable();
    });
    
    // Add checklist from available to chosen
    $(document).on('click', '.add-checklist-btn', function() {
        var checklistId = parseInt($(this).data('checklist-id'));
        var checklistName = $(this).data('checklist-name');
        var isRequired = parseInt($(this).data('is-required')) === 1;
        var $item = $(this).closest('.available-item');
        
        if(!chosenChecklists.includes(checklistId)) {
            chosenChecklists.push(checklistId);
            
            // Create new item in chosen list
            var newItem = '<div class="draggable checklist-item chosen-item" data-checklist-id="' + checklistId + '" data-is-required="' + (isRequired ? '1' : '0') + '" style="background: white; border: 1px solid #ddd; border-radius: 4px; padding: 8px; margin-bottom: 6px; cursor: move; display: flex; align-items: center; font-size: 11px;">';
            newItem += '<span class="draggable-handle" style="margin-right: 8px; color: #999; cursor: grab;"><i class="la la-bars"></i></span>';
            if(isRequired) {
                newItem += '<span class="label label-sm label-light-info" style="font-size: 9px; padding: 3px 6px; margin-right: 8px; min-width: 55px; text-align: center; border-radius: 4px;">Required</span>';
            }
            newItem += '<span class="checklist-name" style="flex: 1; color: #333;">' + checklistName + '</span>';
            if(!isRequired) {
                newItem += '<button type="button" class="btn btn-sm btn-light-danger remove-checklist-btn" data-checklist-id="' + checklistId + '" style="padding: 2px 8px; font-size: 10px; margin-left: 8px;"><i class="la la-times"></i></button>';
            }
            newItem += '</div>';
            
            if($('#chosen-checklist-list .text-muted').length) {
                $('#chosen-checklist-list .text-muted').remove();
            }
            $('#chosen-checklist-list').append(newItem);
            
            // Remove from available list
            $item.fadeOut(200, function() {
                $(this).remove();
                if($('#available-checklist-list .available-item').length === 0) {
                    $('#available-checklist-list').html('<p class="text-muted" style="font-size: 11px; margin: 0; text-align: center; padding: 20px;">All checklists are selected</p>');
                }
            });
            
            setTimeout(function() {
                initSortable();
            }, 100);
            
            $('#form').dirty('setDirty');
        }
    });
    
    // Remove checklist from chosen
    $(document).on('click', '.remove-checklist-btn', function() {
        var checklistId = parseInt($(this).data('checklist-id'));
        var $item = $(this).closest('.chosen-item');
        var checklistName = $item.find('.checklist-name').text();
        var isRequired = parseInt($item.data('is-required')) === 1;
        
        if(isRequired) {
            Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Required checklist cannot be removed', null);
            return;
        }
        
        chosenChecklists = chosenChecklists.filter(function(id) {
            return id !== checklistId;
        });
        
        $item.fadeOut(200, function() {
            $(this).remove();
            if($('#chosen-checklist-list .chosen-item').length === 0) {
                $('#chosen-checklist-list').html('<p class="text-muted" style="font-size: 11px; margin: 0; text-align: center; padding: 20px;">No checklists selected</p>');
            }
        });
        
        // Add back to available list
        var availableItem = '<div class="checklist-item available-item" data-checklist-id="' + checklistId + '" style="background: white; border: 1px solid #ddd; border-radius: 4px; padding: 8px; margin-bottom: 6px; display: flex; align-items: center; font-size: 11px;">';
        availableItem += '<span class="checklist-name" style="flex: 1; color: #333;">' + checklistName + '</span>';
        availableItem += '<button type="button" class="btn btn-sm btn-light-success add-checklist-btn" data-checklist-id="' + checklistId + '" data-checklist-name="' + checklistName + '" data-is-required="0" style="padding: 2px 8px; font-size: 10px; margin-left: 8px;"><i class="la la-plus"></i> Add</button>';
        availableItem += '</div>';
        
        if($('#available-checklist-list .text-muted').length) {
            $('#available-checklist-list .text-muted').remove();
        }
        $('#available-checklist-list').append(availableItem);
        
        var items = $('#available-checklist-list .available-item').detach().sort(function(a, b) {
            return parseInt($(a).data('checklist-id')) - parseInt($(b).data('checklist-id'));
        });
        $('#available-checklist-list').append(items);
        
        $('#form').dirty('setDirty');
    });
    
    // Search functionality for chosen checklists
    $('#search-chosen').on('keyup', function() {
        var searchTerm = $(this).val().toLowerCase();
        $('#chosen-checklist-list .chosen-item').each(function() {
            var name = $(this).find('.checklist-name').text().toLowerCase();
            if(name.indexOf(searchTerm) > -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
    
    // Search functionality for available checklists
    $('#search-available').on('keyup', function() {
        var searchTerm = $(this).val().toLowerCase();
        $('#available-checklist-list .available-item').each(function() {
            var name = $(this).find('.checklist-name').text().toLowerCase();
            if(name.indexOf(searchTerm) > -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
    // Auto-add/remove deposit checklist when "Has Supplier Deposit" toggle changes
    var depositChecklistId = <?php echo $deposit_checklist_id ? $deposit_checklist_id : 'null'; ?>;
    var depositChecklistName = '<?php echo addslashes($deposit_checklist_name); ?>';

    $('#has_supplier_deposit').change(function() {
        if(!depositChecklistId) return;

        if($(this).val() === '1') {
            // Auto-add the deposit checklist if not already chosen
            if(typeof chosenChecklists !== 'undefined' && !chosenChecklists.includes(depositChecklistId)) {
                var $availableItem = $('#available-checklist-list .available-item[data-checklist-id="' + depositChecklistId + '"]');
                if($availableItem.length) {
                    $availableItem.find('.add-checklist-btn').click();
                }
            }
        }
        $('#form').dirty('setDirty');
    });
    <?php } ?>

    function Validate_Price(value) {
        var price = value == 'RetailPrice' ? ($('#RetailPrice').val()).replace(/,/g, '') : ($('#SupplierPrice').val()).replace(/,/g, '');
        if(price.match(/^[1-9][\d]{0,9}([\.][\d]{0,2})?$/)) {
            if(value == 'RetailPrice') {
                $('#RetailPrice').val(parseFloat(price).toLocaleString('en-US', {minimumFractionDigits: 2}));
            } else {
                $('#SupplierPrice').val(parseFloat(price).toLocaleString('en-US', {minimumFractionDigits: 2}));
            }
        } else {
            if(value == 'RetailPrice') {
                $('#RetailPrice').val('');
            } else {
                $('#SupplierPrice').val('');
            }
        }
    }

    $('#Name').change(function() {
        var name = ($('#Name').val()).toUpperCase();
        $.ajax({
            url: '<?php echo base_url('Product/Detect') ?>',
            type: 'post',
            data: {
                name: name
            },
            dataType: 'json',
            success: function(redundant_name) {
                if(redundant_name) {
                    Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Redundant Name Detected', null);
                    $('#Name').val('');
                }
            }
        });
    });

    $('input[type="button"]').click(function() {
        const swalWithBootstrapButtons = Swal.mixin({
            customClass: {
                confirmButton: 'btn btn-light-success m-2',
                cancelButton: 'btn btn-danger m-2'
            },
            buttonsStyling: true
        });
        swalWithBootstrapButtons.fire({
            width: 550,
            background: 'url(<?php echo base_url('assets/image/sweetalert.jpg') ?>)',
            icon: 'warning',
            title: <?php if(current_url() == base_url('Product/Create')) { ?> 'Create New Product Record ?' <?php } else { ?> '<?php echo 'Update Product Record : ' . str_replace('\'', '', $ProductCode) . ' ?'; ?>' <?php } ?>,
            confirmButtonText: 'Confirm',
            cancelButtonText: 'Cancel',
            showCancelButton: true
        }).then((action) => {
            if(action.isConfirmed) {
                var category = $('#CategoryID').val();
                var supplier = $('#SupplierID').val();
                var name = ($('#Name').val()).toUpperCase();
                if(window.location.href == '<?php echo base_url('Product/Create'); ?>' && (category == null || category == '' || supplier == null || supplier == '' || name == '')) {
                    Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please Insert All Required Product Information', null);
                } else {
                    if(window.location.href == '<?php echo base_url('Product/Create'); ?>') {
                        $.ajax({
                            url: '<?php echo base_url('Product/GetMaxNameLength') ?>',
                            type: 'post',
                            data: { category_id: category },
                            dataType: 'json',
                            success: function(maxNameLength) {
                                if(name.length > maxNameLength) {
                                    Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Product Name Must Not Exceed '+maxNameLength+' Characters', null);
                                } else {
                                    var product = [];
                                    product.push({CategoryID:category, SupplierID:supplier, Name:name, is_child_or_infant:$('#is_child_or_infant').val(), has_supplier_deposit:$('#has_supplier_deposit').val(), InsertBy:<?php echo $this->session->userdata('admin_id') ?>, InsertDate:'<?php echo date('Y-m-d H:i:s') ?>'});
                                    var retail_price = $('#RetailPrice').val();
                                    if(retail_price != '') {
                                        product[0]['RetailPrice'] = retail_price.replace(/,/g, '');;
                                    }
                                    var supplier_price = $('#SupplierPrice').val();
                                    if(supplier_price != '') {
                                        product[0]['SupplierPrice'] = supplier_price.replace(/,/g, '');;
                                    }

                                    // Collect chosen checklists in order
                                    var selected_checklists = [];
                                    <?php if(isset($package_checklists)) { ?>
                                        $('#chosen-checklist-list .chosen-item').each(function() {
                                            var checklistId = parseInt($(this).data('checklist-id'));
                                            if(checklistId && !isNaN(checklistId)) {
                                                selected_checklists.push(checklistId);
                                            }
                                        });
                                        if(selected_checklists.length === 0 && typeof chosenChecklists !== 'undefined' && Array.isArray(chosenChecklists)) {
                                            selected_checklists = chosenChecklists.slice();
                                        }
                                    <?php } ?>

                                    Submit_Product('<?php echo base_url('Product/Create') ?>', category, product, selected_checklists);
                                }
                            }
                        });
                    } else {
                        var maxNameLength = '<?php echo $maxNameLength ?? 99 ?>';
                        if(name.length > maxNameLength) {
                            Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Product Name Must Not Exceed '+maxNameLength+' Characters', null);
                        } else {
                            var product = [{ProductID:<?php echo $ProductID ?>, UpdateBy:<?php echo $this->session->userdata('admin_id') ?>, UpdateDate:'<?php echo date('Y-m-d H:i:s') ?>'}];
                            var dirty_fields = $('#form').dirty('showDirtyFields');
                            if(dirty_fields.length > 0) {
                                for(var i = 0; i < dirty_fields.length; i++) {
                                    var key = dirty_fields[i].id;
                                    var value = (dirty_fields[i].value).toUpperCase();
                                    if(key == 'RetailPrice' || key == 'SupplierPrice') {
                                        value = value.replace(/,/g, '');
                                    }
                                    product[0][key] = value;
                                }
                            }

                            var original_is_child_or_infant = '<?php echo $is_child_or_infant ?? 0; ?>';
                            var current_is_child_or_infant = $('#is_child_or_infant').val();
                            if(current_is_child_or_infant !== original_is_child_or_infant) {
                                product[0]['is_child_or_infant'] = current_is_child_or_infant;
                            }

                            var original_has_supplier_deposit = '<?php echo $has_supplier_deposit ?? 0; ?>';
                            var current_has_supplier_deposit = $('#has_supplier_deposit').val();
                            if(current_has_supplier_deposit !== original_has_supplier_deposit) {
                                product[0]['has_supplier_deposit'] = current_has_supplier_deposit;
                            }

                            // Collect chosen checklists in order (from DOM to preserve drag-and-drop order)
                            var selected_checklists = [];
                            <?php if(isset($package_checklists)) { ?>
                                // Always read from DOM to get current order (after any drag operations)
                                $('#chosen-checklist-list .chosen-item').each(function() {
                                    var checklistId = parseInt($(this).data('checklist-id'));
                                    if(checklistId && !isNaN(checklistId)) {
                                        selected_checklists.push(checklistId);
                                    }
                                });
                                
                                // Fallback to chosenChecklists array if DOM is empty
                                if(selected_checklists.length === 0 && typeof chosenChecklists !== 'undefined' && Array.isArray(chosenChecklists)) {
                                    selected_checklists = chosenChecklists.slice(); // Use slice() to create a copy
                                }
                                
                                console.log('Collected checklists order:', selected_checklists);
                            <?php } ?>
                            
                            // Ensure selected_checklists is an array
                            if(!Array.isArray(selected_checklists)) {
                                selected_checklists = [];
                            }
                            
                            count = 0;
                            $.each(product[0], function() {
                                count++;
                            });
                            if(count == 3) {
                                // Only product metadata changed, check if checklists changed
                                var original_checklists = <?php echo json_encode($js_selected_ids ?? $selected_checklist_ids ?? array()); ?>;
                                // Compare order and content (don't sort - preserve order!)
                                var checklists_changed = JSON.stringify(selected_checklists) !== JSON.stringify(original_checklists);
                                
                                if(!checklists_changed) {
                                    Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', '<?php echo 'No Changes Detected In Product Record : ' . str_replace('\'', '', $ProductCode); ?>', '<?php echo base_url('Product') ?>');
                                } else {
                                    // Only update checklists (preserve order)
                                    console.log('Updating checklists with order:', selected_checklists);
                                    Update_Product_Checklists(<?php echo $ProductID ?>, selected_checklists);
                                }
                            } else {
                                // Product fields changed, update product first then checklists
                                Submit_Product('<?php echo base_url('Product/Update') ?>', null, product, selected_checklists);
                            }
                        }
                    }
                }
            }
        });
    });

    function Submit_Product(url, category_id, product, checklists)
    {
        var postData = {
            category_id: category_id,
            product: product
        };
        
        // Add checklists to POST data if provided
        if(checklists !== undefined && checklists !== null && Array.isArray(checklists)) {
            postData.checklist_ids = checklists;
        }
        
        $.ajax({
            url: url,
            type: 'post',
            data: postData,
            success: function(response) {
                <?php if(current_url() == base_url('Product/Update')) { ?>
                    // Update checklists after product update
                    if(checklists !== undefined && checklists !== null && checklists.length > 0) {
                        Update_Product_Checklists(<?php echo $ProductID ?>, checklists);
                    } else {
                        Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Product Record <?php echo ': ' . str_replace('\'', '', $ProductCode); ?> Successfully Updated', '<?php echo base_url('Product') ?>');
                    }
                <?php } else { ?>
                    // For create, checklists are already saved in the backend
                    Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'New Product Record Successfully Created', '<?php echo base_url('Product') ?>');
                <?php } ?>
            },
            error: function(xhr, status, error) {
                console.error('Submit_Product Error:', xhr.responseText, xhr.status, error);
                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', '<?php if(current_url() == base_url('Product/Create')) { echo 'New'; } ?> Product Record <?php if(current_url() == base_url('Product/Update')) { echo ': ' . str_replace('\'', '', $ProductCode); } ?> Could Not Be <?php if(current_url() == base_url('Product/Create')) { echo 'Created'; } else { echo 'Updated'; } ?>', null);
            }
        });
    }

    function Update_Product_Checklists(product_id, checklist_ids)
    {
        // Ensure checklist_ids is an array
        if(!Array.isArray(checklist_ids)) {
            checklist_ids = [];
        }
        
        console.log('Sending to backend - product_id:', product_id, 'checklist_ids (order):', checklist_ids);
        
        $.ajax({
            url: '<?php echo base_url('Product/UpdateChecklists') ?>',
            type: 'post',
            data: {
                product_id: product_id,
                checklist_ids: checklist_ids
            },
            dataType: 'json',
            success: function(response) {
                console.log('Backend response:', response);
                if(response && response.success) {
                    Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Product Record <?php echo ': ' . str_replace('\'', '', $ProductCode); ?> Successfully Updated', '<?php echo base_url('Product') ?>');
                } else {
                    var errorMsg = (response && response.message) ? response.message : 'Product Checklists Could Not Be Updated';
                    Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', errorMsg, null);
                }
            },
            error: function(xhr, status, error) {
                console.error('UpdateChecklists Error:', xhr.responseText);
                var errorMsg = 'Product Checklists Could Not Be Updated';
                if(xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', errorMsg, null);
            }
        });
    }
    
    $('#form').dirty('isClean');
</script>