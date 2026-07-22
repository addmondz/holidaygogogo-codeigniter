<?php
/**
 * Run with: php tests/helpers/VoucherImageUploadValidationTest.php
 *
 * Drives validate_voucher_image_upload() with stub $_FILES shapes plus a real
 * PNG fixture and an HTML-disguised-as-image fixture. The controller relies
 * on this helper as the first gate before the CodeIgniter upload library.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

if (!defined('FCPATH')) {
    define('FCPATH', realpath(__DIR__ . '/../..') . DIRECTORY_SEPARATOR);
}

require_once __DIR__ . '/../../application/helpers/voucher_image_helper.php';

$fixtures_dir = __DIR__ . '/../fixtures';
$png_fixture  = $fixtures_dir . '/sample.png';
$fake_jpg     = $fixtures_dir . '/fake.jpg'; // HTML payload renamed to .jpg

if (!file_exists($png_fixture)) {
    fwrite(STDERR, "Missing fixture: $png_fixture\n");
    exit(2);
}

file_put_contents($fake_jpg, '<html><body>not an image</body></html>');

$assertions = [];

$assertions['empty meta -> fail'] =
    validate_voucher_image_upload([])['ok'] === false;

$assertions['missing name -> fail'] =
    validate_voucher_image_upload(['size' => 100, 'tmp_name' => $png_fixture])['ok'] === false;

$assertions['exe extension -> fail'] =
    validate_voucher_image_upload([
        'name' => 'evil.exe',
        'size' => 1024,
        'tmp_name' => $png_fixture,
        'error' => UPLOAD_ERR_OK,
    ])['ok'] === false;

$assertions['php extension -> fail'] =
    validate_voucher_image_upload([
        'name' => 'shell.php',
        'size' => 1024,
        'tmp_name' => $png_fixture,
        'error' => UPLOAD_ERR_OK,
    ])['ok'] === false;

$assertions['oversize -> fail'] =
    validate_voucher_image_upload([
        'name' => 'big.png',
        'size' => 6 * 1024 * 1024, // 6 MB
        'tmp_name' => $png_fixture,
        'error' => UPLOAD_ERR_OK,
    ], 5120)['ok'] === false;

$assertions['zero size -> fail'] =
    validate_voucher_image_upload([
        'name' => 'empty.png',
        'size' => 0,
        'tmp_name' => $png_fixture,
        'error' => UPLOAD_ERR_OK,
    ])['ok'] === false;

$assertions['upload error code -> fail'] =
    validate_voucher_image_upload([
        'name' => 'ok.png',
        'size' => 100,
        'tmp_name' => $png_fixture,
        'error' => UPLOAD_ERR_INI_SIZE,
    ])['ok'] === false;

$assertions['html disguised as jpg -> fail'] =
    validate_voucher_image_upload([
        'name' => 'fake.jpg',
        'size' => filesize($fake_jpg),
        'tmp_name' => $fake_jpg,
        'error' => UPLOAD_ERR_OK,
    ])['ok'] === false;

$assertions['valid png -> pass'] =
    validate_voucher_image_upload([
        'name' => 'sample.png',
        'size' => filesize($png_fixture),
        'tmp_name' => $png_fixture,
        'error' => UPLOAD_ERR_OK,
    ])['ok'] === true;

$assertions['uppercase extension -> pass'] =
    validate_voucher_image_upload([
        'name' => 'SAMPLE.PNG',
        'size' => filesize($png_fixture),
        'tmp_name' => $png_fixture,
        'error' => UPLOAD_ERR_OK,
    ])['ok'] === true;

$assertions['jpeg extension -> pass when image valid'] =
    validate_voucher_image_upload([
        'name' => 'photo.jpeg',
        'size' => filesize($png_fixture), // helper checks raster shape, not ext-vs-bytes
        'tmp_name' => $png_fixture,
        'error' => UPLOAD_ERR_OK,
    ])['ok'] === true;

@unlink($fake_jpg);

// --- inline_voucher_images_html() -----------------------------------------

// Stage a fixture under FCPATH/assets/upload/voucher/ so the inliner can resolve it.
$voucher_dir = FCPATH . 'assets/upload/voucher';
if (!is_dir($voucher_dir)) {
    mkdir($voucher_dir, 0755, true);
}
$staged_png = $voucher_dir . '/test_inline_fixture.png';
copy($png_fixture, $staged_png);

$assertions['no img tags -> unchanged'] =
    inline_voucher_images_html('<p>hello world</p>') === '<p>hello world</p>';

$assertions['empty -> empty string'] =
    inline_voucher_images_html('') === '';

$assertions['null -> empty string'] =
    inline_voucher_images_html(null) === '';

$data_html_in = '<p><img src="data:image/png;base64,abc" alt="x"></p>';
$assertions['data uri preserved'] =
    inline_voucher_images_html($data_html_in) === $data_html_in;

$external_html = '<img src="https://example.com/foo.png">';
$assertions['external url left alone'] =
    inline_voucher_images_html($external_html) === $external_html;

// Absolute URL pointing at our host -> rewritten to data URI
// (Inliner re-encodes through GD as JPEG to dodge DomPDF's PNG-alpha bug.)
$absolute = '<img src="http://example.test/assets/upload/voucher/test_inline_fixture.png">';
$inlined_abs = inline_voucher_images_html($absolute);
$assertions['absolute local url -> data uri'] =
    strpos($inlined_abs, 'data:image/jpeg;base64,') !== false &&
    strpos($inlined_abs, 'http://example.test') === false;

// Root-relative URL -> rewritten
$rel = '<img src="/assets/upload/voucher/test_inline_fixture.png">';
$inlined_rel = inline_voucher_images_html($rel);
$assertions['root-relative url -> data uri'] =
    strpos($inlined_rel, 'data:image/jpeg;base64,') !== false;

// Path traversal attempt -> not rewritten (file is not under voucher path or doesn't exist)
$traversal = '<img src="/assets/upload/voucher/../../../etc/passwd">';
$assertions['traversal attempt left alone'] =
    inline_voucher_images_html($traversal) === $traversal;

// Single-quoted src
$single = "<img src='/assets/upload/voucher/test_inline_fixture.png'>";
$inlined_single = inline_voucher_images_html($single);
$assertions['single-quoted src rewritten'] =
    strpos($inlined_single, 'data:image/jpeg;base64,') !== false;

// Missing file -> left alone
$missing = '<img src="/assets/upload/voucher/does_not_exist.png">';
$assertions['missing file left alone'] =
    inline_voucher_images_html($missing) === $missing;

// Multiple images mixed
$mixed = '<img src="/assets/upload/voucher/test_inline_fixture.png"><img src="https://external.example/x.png">';
$inlined_mixed = inline_voucher_images_html($mixed);
$assertions['mixed images: local rewritten, external left'] =
    substr_count($inlined_mixed, 'data:image/jpeg;base64,') === 1 &&
    strpos($inlined_mixed, 'https://external.example/x.png') !== false;

@unlink($staged_png);
// Don't rmdir voucher_dir — it may legitimately hold real uploads.

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
