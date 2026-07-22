<?php
/**
 * Run with: php tests/helpers/GhlMessageLogAttachmentsTest.php
 *
 * Covers ghl_message_log_attachments(): turning the stored
 * ghl_messages.attachments_json (a JSON array of URLs) into a typed list the
 * Message Log view renders (image thumbnail, voice/audio player, video, or a
 * download link) instead of a blank Message cell. Pure logic, no CodeIgniter.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/ghl_messages_log_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label}\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// Empty / junk inputs -> no attachments.
assert_eq('null',      array(), ghl_message_log_attachments(null));
assert_eq('empty',     array(), ghl_message_log_attachments(''));
assert_eq('empty[]',   array(), ghl_message_log_attachments('[]'));
assert_eq('not json',  array(), ghl_message_log_attachments('not json'));

// Images.
foreach (array('jpg', 'jpeg', 'png', 'gif', 'webp') as $ext) {
    $out = ghl_message_log_attachments('["https://cdn.test/a/b.' . $ext . '"]');
    assert_eq("image .$ext kind", 'image', $out[0]['kind']);
    assert_eq("image .$ext url", 'https://cdn.test/a/b.' . $ext, $out[0]['url']);
}

// Voice note (WhatsApp .ogg) -> audio player.
$out = ghl_message_log_attachments('["https://cdn.test/x/voice.ogg"]');
assert_eq('ogg is audio', 'audio', $out[0]['kind']);

// Video.
$out = ghl_message_log_attachments('["https://cdn.test/x/clip.mp4"]');
assert_eq('mp4 is video', 'video', $out[0]['kind']);

// Unknown / document -> file, with a readable name.
$out = ghl_message_log_attachments('["https://cdn.test/docs/quote.pdf"]');
assert_eq('pdf is file', 'file', $out[0]['kind']);
assert_eq('pdf name', 'quote.pdf', $out[0]['name']);

// Extension detected past a query string (GHL firebase URLs).
$url = 'https://firebasestorage.googleapis.com/o/loc%2Fx.png?alt=media&token=abc';
$out = ghl_message_log_attachments('["' . $url . '"]');
assert_eq('png behind query', 'image', $out[0]['kind']);
assert_eq('query url kept', $url, $out[0]['url']);

// Multiple attachments keep their order.
$out = ghl_message_log_attachments('["https://cdn.test/1.png","https://cdn.test/2.ogg"]');
assert_eq('multi count', 2, count($out));
assert_eq('multi[0]', 'image', $out[0]['kind']);
assert_eq('multi[1]', 'audio', $out[1]['kind']);

// Non-http(s) URLs are rejected (XSS guard).
$out = ghl_message_log_attachments('["javascript:alert(1)","ftp://x/y.png"]');
assert_eq('reject non-http', array(), $out);

echo "ALL PASS\n";
