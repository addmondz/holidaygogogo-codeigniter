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

        // Shared config for the four booking voucher editors. They render
        // together on the printed Travel Voucher (DomPDF), so the toolbars
        // stay in sync intentionally.
        var voucherUploadUrl = (typeof window !== 'undefined' && window.TINYMCE_IMAGE_UPLOAD_URL) || '';
        // TinyMCE's built-in uploader uses raw XHR and does not set
        // X-Requested-With, which CodeIgniter's is_ajax_request() requires.
        // A custom handler lets us set the header (and surface server errors).
        var voucherImageHandler = function (blobInfo, success, failure, progress) {
            var xhr = new XMLHttpRequest();
            xhr.withCredentials = true;
            xhr.open('POST', voucherUploadUrl);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            if (xhr.upload && typeof progress === 'function') {
                xhr.upload.onprogress = function (e) {
                    if (e.lengthComputable) progress(e.loaded / e.total * 100);
                };
            }
            xhr.onload = function () {
                var json;
                try { json = JSON.parse(xhr.responseText); } catch (e) { json = null; }
                if (xhr.status < 200 || xhr.status >= 300) {
                    var msg = (json && json.error && json.error.message) ? json.error.message : ('HTTP ' + xhr.status);
                    failure(msg, { remove: true });
                    return;
                }
                if (!json || typeof json.location !== 'string') {
                    failure('Invalid server response', { remove: true });
                    return;
                }
                success(json.location);
            };
            xhr.onerror = function () { failure('Image upload failed: network error', { remove: true }); };
            var formData = new FormData();
            formData.append('file', blobInfo.blob(), blobInfo.filename());
            xhr.send(formData);
        };
        var voucherEditorConfig = {
            toolbar: ['styleselect fontselect fontsizeselect',
                'undo redo | bold italic underline | forecolor backcolor | link image | alignleft aligncenter alignright alignjustify',
                'bullist numlist | removeformat | preview'],
            plugins: 'link lists preview image paste',
            images_upload_url: voucherUploadUrl,
            images_upload_handler: voucherImageHandler,
            images_upload_credentials: true,
            automatic_uploads: true,
            images_reuse_filename: false,
            relative_urls: false,
            remove_script_host: false,
            convert_urls: true,
            file_picker_types: 'image',
            paste_data_images: true,
            // Itineraries are often pasted from PDFs / Word / Google Docs, whose
            // source markup carries absolute positioning and Word (mso-*) styles.
            // Those make lines render on top of each other in the editor and the
            // printed voucher. Clean the paste and drop positioning styles so text
            // always flows normally. Applied on load too, so existing broken
            // vouchers self-correct when reopened.
            paste_merge_formats: true,
            paste_webkit_styles: 'none',
            paste_remove_styles_if_webkit: true,
            invalid_styles: {
                '*': 'position top left right bottom z-index'
            },
            content_style: 'img { max-width: 100%; height: auto; }'
        };

        ['#kt-tinymce-4', '#kt-tinymce-5', '#kt-tinymce-6', '#kt-tinymce-7'].forEach(function (selector) {
            tinymce.init(Object.assign({}, voucherEditorConfig, { selector: selector }));
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
