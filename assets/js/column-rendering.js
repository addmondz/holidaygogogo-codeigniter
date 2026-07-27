"use strict";

var KTDatatablesAdvancedColumnRendering = function() {
	var init = function() {
		var table = $("#kt_datatable");

		// begin first table
		table.DataTable({
			deferRender: true,
			responsive: true,
			paging: true,
			columnDefs: [
				{
					targets: ['booking_checkbox', 'test', 'gl_status', 'status', 'autocount_sync_status','action'],
					orderable: false
				},
				{ 'type': 'date', 'targets': ['bc_date', 'start_date', 'end_date', 'transaction_date', 'deadline', 'month'] },
				{ 'type': 'num-fmt', 'targets': ['subtotal', 'profit', 'amount_received', 'in', 'out', 'retail_price', 'supplier_price'] }
			],
			lengthMenu: [
	            [100, 200, 500, -1],
	            [100, 200, 500, 'All'],
	        ],
		});
	};
	return {

		//main function to initiate the module
		init: function() {
			init();
		}
	};
}();

jQuery(document).ready(function() {
	KTDatatablesAdvancedColumnRendering.init();
});