"use strict";
// Class definition

var KTTinymce = function () {    
    // Private functions
    var demos = function () {
        
        tinymce.init({
			selector: '#kt-tinymce-1',
            toolbar: false,
            statusbar: false
		});

		tinymce.init({
			selector: '#kt-tinymce-2'
        });
        
        tinymce.init({
            selector: '#kt-tinymce-3',
            toolbar: 'advlist | autolink | link image | lists charmap | print preview', 
            plugins : 'advlist autolink link image lists charmap print preview'
        });
        
        tinymce.init({
            selector: '#kt-tinymce-4',
            toolbar: ['styleselect fontselect fontsizeselect',
                'undo redo | bold italic underline | link | alignleft aligncenter alignright alignjustify',
                'bullist numlist | preview'], 
            plugins: 'link lists preview'
        });

        tinymce.init({
            selector: '#kt-tinymce-5',
            toolbar: ['styleselect fontselect fontsizeselect',
                'undo redo | bold italic underline | link | alignleft aligncenter alignright alignjustify',
                'bullist numlist | preview'], 
            plugins: 'link lists preview'
        });       
    }
    
    return {
        // public functions
        init: function() {
            demos(); 
        }
    };
}();

// Initialization
jQuery(document).ready(function() {
    KTTinymce.init();
});