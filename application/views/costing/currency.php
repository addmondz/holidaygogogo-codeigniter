<?php
$active_tab = isset($active_tab) ? $active_tab : 'currencies';
$currencies = isset($currencies) ? $currencies : array();
$exchange_rates = isset($exchange_rates) ? $exchange_rates : array();
$currency_options = isset($currency_options) ? $currency_options : array();
$currency_filters = isset($currency_filters) ? $currency_filters : array('code' => '', 'name' => '', 'symbol' => '');
$rate_filters = isset($rate_filters) ? $rate_filters : array('from_currency_id' => 0, 'to_currency_id' => 0, 'valid_from' => '');
$currency_filter_open = !empty($currency_filters['code']) || !empty($currency_filters['name']) || $currency_filters['symbol'] !== '';
$rate_filter_open = !empty($rate_filters['from_currency_id']) || !empty($rate_filters['to_currency_id']) || !empty($rate_filters['valid_from']);
$myr_currency_id = 0;

foreach ($currency_options as $currency) {
    if (isset($currency['code']) && $currency['code'] === 'MYR') {
        $myr_currency_id = (int) $currency['id'];
        break;
    }
}
?>

<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong>Costing Currency Records</strong>
                    </h3>
                </div>
                <div class="card-toolbar">
                    <a href="<?php echo base_url('Costing'); ?>" class="btn btn-light-primary font-weight-bold mr-2">
                        <i class="la la-arrow-left"></i>Back To Packages
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if ($this->session->flashdata('message_success')) { ?>
                    <div class="alert alert-success"><?php echo html_escape($this->session->flashdata('message_success')); ?></div>
                <?php } ?>
                <?php if ($this->session->flashdata('message_error')) { ?>
                    <div class="alert alert-danger"><?php echo html_escape($this->session->flashdata('message_error')); ?></div>
                <?php } ?>

                <div class="mb-6">
                    <div class="font-size-h5 font-weight-bold text-dark mb-2">Currency Setup</div>
                    <div class="text-muted">Manage currencies and customer-facing selling rates in the same screen. For rates, the team should read them as <strong>1 MYR = XX Currency</strong>.</div>
                </div>

                <ul class="nav nav-tabs nav-tabs-line mb-8">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab === 'currencies' ? 'active' : ''; ?>" data-toggle="tab" href="#currency_tab" data-tab="currencies">
                            <span class="nav-text">Currencies</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab === 'rates' ? 'active' : ''; ?>" data-toggle="tab" href="#rate_tab" data-tab="rates">
                            <span class="nav-text">Currency Rate</span>
                        </a>
                    </li>
                </ul>

                <input type="hidden" id="active_tab_state" value="<?php echo html_escape($active_tab); ?>">

                <div class="tab-content">
                    <div class="tab-pane fade <?php echo $active_tab === 'currencies' ? 'show active' : ''; ?>" id="currency_tab" role="tabpanel">
                        <div class="accordion accordion-solid accordion-toggle-plus mb-6">
                            <div class="card">
                                <div class="card-header">
                                    <div id="currency_filter_header" data-toggle="collapse" data-target="#currency_filter_body" class="card-title <?php echo $currency_filter_open ? '' : 'collapsed'; ?>" style="font-size:13px;">
                                        Filter By Currency Information
                                    </div>
                                </div>
                                <div id="currency_filter_body" class="collapse <?php echo $currency_filter_open ? 'show' : ''; ?>">
                                    <div class="card-body">
                                        <form action="<?php echo base_url('Costing/Currency'); ?>" method="get" class="form">
                                            <input type="hidden" name="active_tab" value="currencies">
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <div class="form-group">
                                                        <label>Code</label>
                                                        <input type="text" name="currency_code" value="<?php echo html_escape($currency_filters['code']); ?>" autocomplete="off" class="form-control">
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label>Name</label>
                                                        <input type="text" name="currency_name" value="<?php echo html_escape($currency_filters['name']); ?>" autocomplete="off" class="form-control">
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group">
                                                        <label>Symbol</label>
                                                        <input type="text" name="currency_symbol" value="<?php echo html_escape($currency_filters['symbol']); ?>" autocomplete="off" class="form-control">
                                                    </div>
                                                </div>
                                            </div>
                                            <button type="submit" class="btn btn-light-success font-weight-bold" style="width:80px;">Filter</button>
                                            <button type="button" class="btn btn-light-primary font-weight-bold reset-tab" data-url="<?php echo base_url('Costing/Currency?active_tab=currencies'); ?>" style="width:80px;">Reset</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center flex-wrap mb-5">
                            <div>
                                <h4 class="mb-1">Currency Records</h4>
                                <div class="text-muted">All available currencies for costing and selling-rate mapping.</div>
                            </div>
                            <button type="button" class="btn btn-primary font-weight-bold" id="add_currency_button" data-toggle="modal" data-target="#currency_modal">
                                <i class="la la-plus"></i>Add Currency
                            </button>
                        </div>

                        <div class="dataTables_wrapper dt-bootstrap4 no-footer" <?php if (empty($currencies)) { echo 'style="overflow-x:auto;"'; } ?>>
                            <table class="table table-bordered table-head-custom table-checkable dataTable no-footer dtr-inline">
                                <thead>
                                    <tr>
                                        <th style="text-align:center;">No.</th>
                                        <th style="text-align:center;">Code</th>
                                        <th style="text-align:center;">Name</th>
                                        <th style="text-align:center;">Symbol</th>
                                        <th class="action" style="text-align:center;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($currencies)) { ?>
                                        <tr>
                                            <td colspan="5" style="text-align:center; padding-top:10px; padding-bottom:10px;">Currency Records Not Found</td>
                                        </tr>
                                    <?php } else { ?>
                                        <?php $count = 1; ?>
                                        <?php foreach ($currencies as $currency) { ?>
                                            <tr>
                                                <td style="text-align:center; padding-top:15px; padding-bottom:15px;"><?php echo $count; ?></td>
                                                <td style="text-align:center;"><span class="label label-lg label-light-primary label-inline"><?php echo html_escape($currency['code']); ?></span></td>
                                                <td style="text-align:center;"><?php echo html_escape($currency['name']); ?></td>
                                                <td style="text-align:center;"><?php echo html_escape($currency['symbol']); ?></td>
                                                <td style="text-align:center;">
                                                    <div class="btn-group">
                                                        <button type="button" data-toggle="dropdown" class="btn btn-light-primary btn-sm dropdown-toggle" style="padding-left:3px;"></button>
                                                        <div class="dropdown-menu">
                                                            <button
                                                                type="button"
                                                                class="dropdown-item edit-currency"
                                                                data-currency="<?php echo html_escape(json_encode($currency)); ?>"
                                                                style="font-size:11px;"
                                                            >Update</button>
                                                            <form method="post" action="<?php echo base_url('Costing/Delete_Currency'); ?>" onsubmit="return confirm('Delete this currency?');">
                                                                <input type="hidden" name="active_tab" value="currencies">
                                                                <input type="hidden" name="currency_id" value="<?php echo (int) $currency['id']; ?>">
                                                                <button type="submit" class="dropdown-item" style="color:#E37383; font-size:11px;">Delete</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php $count++; ?>
                                        <?php } ?>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade <?php echo $active_tab === 'rates' ? 'show active' : ''; ?>" id="rate_tab" role="tabpanel">
                        <div class="accordion accordion-solid accordion-toggle-plus mb-6">
                            <div class="card">
                                <div class="card-header">
                                    <div id="rate_filter_header" data-toggle="collapse" data-target="#rate_filter_body" class="card-title <?php echo $rate_filter_open ? '' : 'collapsed'; ?>" style="font-size:13px;">
                                        Filter By Rate Information
                                    </div>
                                </div>
                                <div id="rate_filter_body" class="collapse <?php echo $rate_filter_open ? 'show' : ''; ?>">
                                    <div class="card-body">
                                        <form action="<?php echo base_url('Costing/Currency'); ?>" method="get" class="form">
                                            <input type="hidden" name="active_tab" value="rates">
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label>Customer Currency</label>
                                                        <select name="rate_from_currency_id" class="form-control">
                                                            <option value="">All</option>
                                                            <?php foreach ($currency_options as $currency) { ?>
                                                                <?php if ($currency['code'] === 'MYR') { continue; } ?>
                                                                <option value="<?php echo (int) $currency['id']; ?>" <?php echo (int) $rate_filters['from_currency_id'] === (int) $currency['id'] ? 'selected' : ''; ?>>
                                                                    <?php echo html_escape($currency['code'] . ' - ' . $currency['name']); ?>
                                                                </option>
                                                            <?php } ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label>Base Currency</label>
                                                        <input type="text" class="form-control" value="MYR" readonly>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label>Valid From</label>
                                                        <input type="date" name="rate_valid_from" value="<?php echo html_escape($rate_filters['valid_from']); ?>" class="form-control">
                                                    </div>
                                                </div>
                                            </div>
                                            <button type="submit" class="btn btn-light-success font-weight-bold" style="width:80px;">Filter</button>
                                            <button type="button" class="btn btn-light-primary font-weight-bold reset-tab" data-url="<?php echo base_url('Costing/Currency?active_tab=rates'); ?>" style="width:80px;">Reset</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center flex-wrap mb-5">
                            <div>
                                <h4 class="mb-1">Foreign Exchange Rate</h4>
                                <div class="text-muted">Simple view for quick reference. Read every row as <strong>1 foreign currency = X MYR</strong>.</div>
                            </div>
                            <button type="button" class="btn btn-primary font-weight-bold" id="add_rate_button" data-toggle="modal" data-target="#exchange_rate_modal">
                                <i class="la la-plus"></i>Add Rate
                            </button>
                        </div>

                        <div class="alert alert-light-info">
                            Keep this list simple. Example: <strong>USD 4.10</strong> means <strong>1 USD = 4.10 MYR</strong>.
                        </div>

                        <div class="dataTables_wrapper dt-bootstrap4 no-footer" <?php if (empty($exchange_rates)) { echo 'style="overflow-x:auto;"'; } ?>>
                            <table class="table table-bordered table-head-custom table-checkable dataTable no-footer dtr-inline">
                                <thead>
                                    <tr>
                                        <th style="text-align:center;">No.</th>
                                        <th style="text-align:center;">Currency</th>
                                        <th style="text-align:center;">Rate (MYR)</th>
                                        <th style="text-align:center;">Valid From</th>
                                        <th class="action" style="text-align:center;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($exchange_rates)) { ?>
                                        <tr>
                                            <td colspan="5" style="text-align:center; padding-top:10px; padding-bottom:10px;">Selling Rate Records Not Found</td>
                                        </tr>
                                    <?php } else { ?>
                                        <?php $count = 1; ?>
                                        <?php foreach ($exchange_rates as $exchange_rate) { ?>
                                            <tr>
                                                <td style="text-align:center; padding-top:15px; padding-bottom:15px;"><?php echo $count; ?></td>
                                                <td style="text-align:center;"><span class="label label-lg label-light-primary label-inline"><?php echo html_escape($exchange_rate['from_currency_code']); ?></span></td>
                                                <td style="text-align:center; min-width:180px;"><?php echo number_format((float) $exchange_rate['rate'], 6, '.', ','); ?></td>
                                                <td style="text-align:center;"><?php echo !empty($exchange_rate['valid_from']) ? date('d/m/Y H:i', strtotime($exchange_rate['valid_from'])) : 'N/A'; ?></td>
                                                <td style="text-align:center;">
                                                    <div class="btn-group">
                                                        <button type="button" data-toggle="dropdown" class="btn btn-light-primary btn-sm dropdown-toggle" style="padding-left:3px;"></button>
                                                        <div class="dropdown-menu">
                                                            <button
                                                                type="button"
                                                                class="dropdown-item edit-rate"
                                                                data-rate="<?php echo html_escape(json_encode($exchange_rate)); ?>"
                                                                style="font-size:11px;"
                                                            >Update</button>
                                                            <form method="post" action="<?php echo base_url('Costing/Delete_Exchange_Rate'); ?>" onsubmit="return confirm('Delete this rate?');">
                                                                <input type="hidden" name="active_tab" value="rates">
                                                                <input type="hidden" name="exchange_rate_id" value="<?php echo (int) $exchange_rate['id']; ?>">
                                                                <button type="submit" class="dropdown-item" style="color:#E37383; font-size:11px;">Delete</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php $count++; ?>
                                        <?php } ?>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="currency_modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form method="post" action="<?php echo base_url('Costing/Save_Currency'); ?>" id="currency_form_modal">
                <input type="hidden" name="active_tab" value="currencies">
                <input type="hidden" name="currency_id" id="modal_currency_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="currency_modal_title">Add Currency</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <i aria-hidden="true" class="ki ki-close"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Code</label>
                        <input type="text" name="code" id="modal_currency_code" maxlength="3" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" id="modal_currency_name" class="form-control" required>
                    </div>
                    <div class="form-group mb-0">
                        <label>Symbol</label>
                        <input type="text" name="symbol" id="modal_currency_symbol" class="form-control">
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

<div class="modal fade" id="exchange_rate_modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <form method="post" action="<?php echo base_url('Costing/Save_Exchange_Rate'); ?>" id="exchange_rate_form_modal">
                <input type="hidden" name="active_tab" value="rates">
                <input type="hidden" name="exchange_rate_id" id="modal_exchange_rate_id" value="">
                <input type="hidden" name="to_currency_id" id="modal_to_currency_id" value="<?php echo $myr_currency_id; ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="exchange_rate_modal_title">Add Rate</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <i aria-hidden="true" class="ki ki-close"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-light-info">
                        Enter the rate as <strong>1 foreign currency = X MYR</strong>.
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Foreign Currency</label>
                                <select name="from_currency_id" id="modal_from_currency_id" class="form-control" required>
                                    <option value="">Select currency</option>
                                    <?php foreach ($currency_options as $currency) { ?>
                                        <?php if ($currency['code'] === 'MYR') { continue; } ?>
                                        <option value="<?php echo (int) $currency['id']; ?>" data-code="<?php echo html_escape($currency['code']); ?>"><?php echo html_escape($currency['code'] . ' - ' . $currency['name']); ?></option>
                                    <?php } ?>
                                </select>
                                <input type="hidden" name="unit_amount" id="modal_exchange_rate_unit_amount" value="1">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Base</label>
                                <input type="text" class="form-control" value="MYR" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Rate</label>
                                <input type="number" step="0.00000001" min="0.00000001" name="converted_amount" id="modal_exchange_rate_converted_amount" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-8">
                            <div class="alert alert-secondary mb-0" id="rate_preview_text">1 Currency = 0.000000 MYR</div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group mb-0">
                                <label>Valid From</label>
                                <input type="datetime-local" name="valid_from" id="modal_exchange_rate_valid_from" class="form-control">
                            </div>
                        </div>
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
        var activeTabState = document.getElementById('active_tab_state');
        var currencyModalTitle = document.getElementById('currency_modal_title');
        var modalCurrencyId = document.getElementById('modal_currency_id');
        var modalCurrencyCode = document.getElementById('modal_currency_code');
        var modalCurrencyName = document.getElementById('modal_currency_name');
        var modalCurrencySymbol = document.getElementById('modal_currency_symbol');

        var exchangeRateModalTitle = document.getElementById('exchange_rate_modal_title');
        var modalExchangeRateId = document.getElementById('modal_exchange_rate_id');
        var modalFromCurrencyId = document.getElementById('modal_from_currency_id');
        var modalExchangeRateConvertedAmount = document.getElementById('modal_exchange_rate_converted_amount');
        var modalExchangeRateUnitAmount = document.getElementById('modal_exchange_rate_unit_amount');
        var modalExchangeRateValidFrom = document.getElementById('modal_exchange_rate_valid_from');
        var ratePreviewText = document.getElementById('rate_preview_text');

        function setActiveTab(tab) {
            if (activeTabState) {
                activeTabState.value = tab;
            }
        }

        function resetCurrencyModal() {
            currencyModalTitle.textContent = 'Add Currency';
            modalCurrencyId.value = '';
            modalCurrencyCode.value = '';
            modalCurrencyName.value = '';
            modalCurrencySymbol.value = '';
        }

        function formatDatetimeLocal(value) {
            if (!value) {
                return '';
            }

            return value.replace(' ', 'T').slice(0, 16);
        }

        function formatAmount(value, decimals) {
            var parsed = parseFloat(value);
            if (isNaN(parsed)) {
                parsed = 0;
            }

            return parsed.toLocaleString(undefined, {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });
        }

        function getCurrencyCode() {
            if (!modalFromCurrencyId || modalFromCurrencyId.selectedIndex < 0) {
                return 'Currency';
            }

            var option = modalFromCurrencyId.options[modalFromCurrencyId.selectedIndex];
            return option && option.getAttribute('data-code') ? option.getAttribute('data-code') : 'Currency';
        }

        function updateRatePreview() {
            if (!ratePreviewText) {
                return;
            }

            ratePreviewText.textContent = '1 ' + getCurrencyCode() + ' = ' + formatAmount(modalExchangeRateConvertedAmount.value, 6) + ' MYR';
        }

        function cleanupModalState() {
            $('.modal-backdrop').remove();
            $('.dropdown-menu.show').removeClass('show');
            $('.btn-group.show, .dropdown.show').removeClass('show');
            $('[data-toggle="dropdown"]').attr('aria-expanded', 'false');

            if (!$('.modal.show').length) {
                $('body').removeClass('modal-open').css('padding-right', '');
            }
        }

        function closeOpenDropdowns() {
            $('.dropdown-menu.show').removeClass('show');
            $('.btn-group.show, .dropdown.show').removeClass('show');
            $('[data-toggle="dropdown"]').attr('aria-expanded', 'false');
        }

        function resetExchangeRateModal() {
            exchangeRateModalTitle.textContent = 'Add Rate';
            modalExchangeRateId.value = '';
            modalFromCurrencyId.value = '';
            modalExchangeRateUnitAmount.value = '1';
            modalExchangeRateConvertedAmount.value = '';
            modalExchangeRateValidFrom.value = '';
            updateRatePreview();
        }

        document.querySelectorAll('.nav-tabs .nav-link').forEach(function (tabLink) {
            tabLink.addEventListener('shown.bs.tab', function () {
                setActiveTab(tabLink.getAttribute('data-tab'));
            });
        });

        document.getElementById('add_currency_button').addEventListener('click', function () {
            resetCurrencyModal();
            setActiveTab('currencies');
        });

        document.querySelectorAll('.edit-currency').forEach(function (button) {
            button.addEventListener('click', function () {
                var currency = JSON.parse(button.getAttribute('data-currency'));
                closeOpenDropdowns();
                currencyModalTitle.textContent = 'Update Currency';
                modalCurrencyId.value = currency.id || '';
                modalCurrencyCode.value = currency.code || '';
                modalCurrencyName.value = currency.name || '';
                modalCurrencySymbol.value = currency.symbol || '';
                setActiveTab('currencies');
                $('#currency_modal').modal('show');
            });
        });

        $('#currency_modal').on('hidden.bs.modal', function () {
            resetCurrencyModal();
            cleanupModalState();
        });

        document.getElementById('add_rate_button').addEventListener('click', function () {
            resetExchangeRateModal();
            setActiveTab('rates');
        });

        document.querySelectorAll('.edit-rate').forEach(function (button) {
            button.addEventListener('click', function () {
                var rate = JSON.parse(button.getAttribute('data-rate'));
                closeOpenDropdowns();
                exchangeRateModalTitle.textContent = 'Update Rate';
                modalExchangeRateId.value = rate.id || '';
                modalFromCurrencyId.value = rate.from_currency_id || '';
                modalExchangeRateUnitAmount.value = '1';
                modalExchangeRateConvertedAmount.value = rate.rate || '';
                modalExchangeRateValidFrom.value = formatDatetimeLocal(rate.valid_from || '');
                updateRatePreview();
                setActiveTab('rates');
                $('#exchange_rate_modal').modal('show');
            });
        });

        modalFromCurrencyId.addEventListener('change', updateRatePreview);
        modalExchangeRateConvertedAmount.addEventListener('input', updateRatePreview);
        modalExchangeRateConvertedAmount.addEventListener('change', updateRatePreview);

        $('#exchange_rate_modal').on('hidden.bs.modal', function () {
            resetExchangeRateModal();
            cleanupModalState();
        });

        document.querySelectorAll('.reset-tab').forEach(function (button) {
            button.addEventListener('click', function () {
                window.location = button.getAttribute('data-url');
            });
        });

        updateRatePreview();
    })();
</script>
