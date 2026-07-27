<?php
/**
 * Run with: php tests/helpers/ChatHistoryHelperTest.php
 *
 * Locks the pure chat_history_helper functions that power the "Chat History"
 * action on the Guest / Customer / GHL Leads listing (upload + download only):
 *   - chat_history_validate_upload : .txt-only, non-empty, <=5MB gate
 *   - chat_history_stored_name     : random, traversal-proof, always .txt
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
require __DIR__ . '/../../application/helpers/chat_history_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

function assert_true($label, $actual) { assert_eq($label, true, (bool) $actual); }
function assert_false($label, $actual) { assert_eq($label, false, (bool) $actual); }

// ---- validate_upload -------------------------------------------------------
assert_true('valid .txt accepted',        chat_history_validate_upload('WhatsApp Chat with +60.txt', 1234)['ok']);
assert_false('non-txt rejected',          chat_history_validate_upload('chat.pdf', 1234)['ok']);
assert_false('empty file rejected',       chat_history_validate_upload('chat.txt', 0)['ok']);
assert_false('blank name rejected',       chat_history_validate_upload('   ', 10)['ok']);
assert_false('oversize rejected',         chat_history_validate_upload('chat.txt', 6 * 1024 * 1024)['ok']);
assert_true('uppercase ext accepted',     chat_history_validate_upload('CHAT.TXT', 10)['ok']);

// ---- stored_name -----------------------------------------------------------
$stored = chat_history_stored_name('../../etc/passwd');
assert_true('stored ends .txt',          substr($stored, -4) === '.txt');
assert_false('stored has no slash',      strpos($stored, '/') !== false);
assert_false('stored has no dotdot',     strpos($stored, '..') !== false);
assert_true('two calls differ',          chat_history_stored_name('a.txt') !== chat_history_stored_name('a.txt'));

echo "\nAll ChatHistoryHelper assertions passed.\n";
