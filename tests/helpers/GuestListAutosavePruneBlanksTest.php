<?php
/**
 * Run with: php tests/helpers/GuestListAutosavePruneBlanksTest.php
 *
 * Pins the guest-list auto-save data-loss fix:
 *   1. gl_autosave_prune_blanks() drops null/blank incoming fields so a stale
 *      (blank) auto-save can never overwrite existing saved guest data, while
 *      keeping the values the user actually typed and any structural keys.
 *   2. gl_autosave_should_write() refuses the write when the GL lock is
 *      expired or owned by another session (the timer-expiry clobber path).
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
require __DIR__ . '/../../application/helpers/guest_list_autosave_helper.php';

$failed = 0;
function check($label, $expected, $actual) {
    global $failed;
    if ($expected === $actual) {
        echo "  PASS  {$label}\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        $failed++;
    }
}

// --- prune_blanks -------------------------------------------------------
// A stale/blank form: only the seeded leader name is set, everything else
// blank. Pruning must leave ONLY the non-empty typed value.
$stale = array(
    'Name' => 'KOH YIH KUANG',
    'LastName' => null,
    'Gender' => '',
    'IdentificationNumber' => null,
    'PassportNumber' => '   ',   // whitespace only = blank
);
check('stale form keeps only typed name',
    array('Name' => 'KOH YIH KUANG'),
    gl_autosave_prune_blanks($stale));

// A fully blank row prunes to nothing -> caller skips the update entirely.
check('all-blank row prunes to empty',
    array(),
    gl_autosave_prune_blanks(array('Name' => null, 'Gender' => '', 'Email' => '  ')));

// Real typed values survive untouched.
$typed = array('Name' => 'CHEN GEK YAP', 'Gender' => 'F', 'IdentificationNumber' => '890630135434');
check('typed values pass through', $typed, gl_autosave_prune_blanks($typed));

// Zero is a real value (not blank) and must survive.
check('zero string is kept',
    array('Postcode' => '0'),
    gl_autosave_prune_blanks(array('Postcode' => '0', 'City' => '')));

// always_keep preserves a structural key even when blank.
check('always_keep preserves blank structural key',
    array('guest_list_room_id' => null),
    gl_autosave_prune_blanks(array('guest_list_room_id' => null, 'Name' => ''), array('guest_list_room_id')));

// --- should_write -------------------------------------------------------
check('valid owned lock -> write', true,  gl_autosave_should_write(false, true));
check('expired lock -> no write',  false, gl_autosave_should_write(true,  true));
check('foreign owner -> no write', false, gl_autosave_should_write(false, false));
check('expired + foreign -> no write', false, gl_autosave_should_write(true, false));

echo $failed === 0 ? "\nALL PASS\n" : "\n{$failed} FAILED\n";
exit($failed === 0 ? 0 : 1);
