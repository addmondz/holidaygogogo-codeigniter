<?php
/**
 * Run with: php tests/helpers/UploadCleanupHelperTest.php
 *
 * Drives the pure file-ops in upload_cleanup_helper.php against an isolated
 * tmp directory so the suite does not touch real uploads or backups.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

if (!defined('FCPATH')) {
    define('FCPATH', realpath(__DIR__ . '/../..') . DIRECTORY_SEPARATOR);
}

require_once __DIR__ . '/../../application/helpers/upload_cleanup_helper.php';

function ucht_rmrf($path)
{
    if (!file_exists($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }
    foreach (scandir($path) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        ucht_rmrf($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}

$root    = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'upload_cleanup_' . uniqid();
$src_dir = $root . DIRECTORY_SEPARATOR . 'src';
$bak_dir = $root . DIRECTORY_SEPARATOR . 'backups';
mkdir($src_dir, 0755, true);

$assertions = [];

// --- backup_directory_to_zip ---------------------------------------------

// Empty source -> null, no zip created
$assertions['empty source -> null'] =
    backup_directory_to_zip($src_dir, $bak_dir, 'passport') === null
    && glob($bak_dir . '/passport_*.zip') === [] || glob($bak_dir . '/passport_*.zip') === false;

// Missing source -> null
$missing_src = $root . DIRECTORY_SEPARATOR . 'does_not_exist';
$assertions['missing source -> null'] =
    backup_directory_to_zip($missing_src, $bak_dir, 'passport') === null;

// Populate src with 3 files, run backup, expect 3 entries in zip
file_put_contents($src_dir . '/a.jpg', 'aaa');
file_put_contents($src_dir . '/b.png', 'bbbb');
file_put_contents($src_dir . '/c.pdf', 'ccccc');

$zip_path = backup_directory_to_zip($src_dir, $bak_dir, 'passport');
$assertions['backup returns path'] = is_string($zip_path) && file_exists($zip_path);

$zip = new ZipArchive();
$opened = $zip->open($zip_path);
$entries = [];
if ($opened === true) {
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entries[] = $zip->getNameIndex($i);
    }
    $zip->close();
}
sort($entries);
$assertions['zip contains all 3 files'] = $entries === ['a.jpg', 'b.png', 'c.pdf'];

// Filename pattern check
$assertions['zip filename uses prefix + timestamp'] =
    (bool) preg_match('/passport_\d{8}_\d{6}\.zip$/', basename($zip_path));

// Backup ignores subdirs (only includes regular files)
ucht_rmrf($src_dir);
mkdir($src_dir, 0755, true);
file_put_contents($src_dir . '/keep.jpg', 'k');
mkdir($src_dir . '/sub', 0755, true);
file_put_contents($src_dir . '/sub/nested.jpg', 'n');
sleep(1); // ensure timestamp differs from earlier zip
$zip2_path = backup_directory_to_zip($src_dir, $bak_dir, 'passport');
$zip2 = new ZipArchive();
$entries2 = [];
if ($zip2->open($zip2_path) === true) {
    for ($i = 0; $i < $zip2->numFiles; $i++) {
        $entries2[] = $zip2->getNameIndex($i);
    }
    $zip2->close();
}
$assertions['backup skips subdirectories'] = $entries2 === ['keep.jpg'];

// --- delete_files_older_than ---------------------------------------------

ucht_rmrf($src_dir);
mkdir($src_dir, 0755, true);
$old = $src_dir . '/old.jpg';
$new = $src_dir . '/new.jpg';
file_put_contents($old, 'old');
file_put_contents($new, 'new');
touch($old, time() - (400 * 86400)); // 400 days old
touch($new, time() - (10  * 86400)); // 10 days old

$deleted = delete_files_older_than($src_dir, 365);
sort($deleted);
$assertions['delete returns basenames only'] = $deleted === ['old.jpg'];
$assertions['old file removed'] = !file_exists($old);
$assertions['new file kept'] = file_exists($new);

// Subdirectories with stale mtime must survive
mkdir($src_dir . '/sub', 0755, true);
touch($src_dir . '/sub', time() - (400 * 86400));
$deleted2 = delete_files_older_than($src_dir, 365);
$assertions['subdirectory not deleted'] =
    is_dir($src_dir . '/sub') && !in_array('sub', $deleted2, true);

// Empty dir -> empty array
ucht_rmrf($src_dir);
mkdir($src_dir, 0755, true);
$assertions['empty dir -> empty array'] = delete_files_older_than($src_dir, 365) === [];

// Missing dir -> empty array (defensive)
$assertions['missing dir -> empty array'] =
    delete_files_older_than($root . '/nope', 365) === [];

// Protected basenames are spared even when stale
ucht_rmrf($src_dir);
mkdir($src_dir, 0755, true);
$stale1 = $src_dir . '/active_booking.jpg';
$stale2 = $src_dir . '/done_booking.jpg';
$orphan = $src_dir . '/orphan.jpg';
file_put_contents($stale1, '1');
file_put_contents($stale2, '2');
file_put_contents($orphan, '3');
$ago = time() - (400 * 86400);
touch($stale1, $ago);
touch($stale2, $ago);
touch($orphan, $ago);

$deleted_excl = delete_files_older_than($src_dir, 365, ['active_booking.jpg']);
sort($deleted_excl);
$assertions['exclusion: protected file spared'] = file_exists($stale1);
$assertions['exclusion: unprotected stale removed'] = !file_exists($stale2);
$assertions['exclusion: orphan still removed'] = !file_exists($orphan);
$assertions['exclusion: returned basenames exclude protected'] =
    $deleted_excl === ['done_booking.jpg', 'orphan.jpg'];

// --- prune_backups_older_than --------------------------------------------

ucht_rmrf($bak_dir);
mkdir($bak_dir, 0755, true);
$old_zip = $bak_dir . '/passport_old.zip';
$new_zip = $bak_dir . '/passport_new.zip';
$other   = $bak_dir . '/db_backup.sql.gz';
file_put_contents($old_zip, 'x');
file_put_contents($new_zip, 'y');
file_put_contents($other,   'z');
touch($old_zip, time() - (800 * 86400));
touch($other,   time() - (800 * 86400));
touch($new_zip, time() - (10  * 86400));

$pruned = prune_backups_older_than($bak_dir, 730, 'passport_*.zip');
$assertions['prune count'] = $pruned === 1;
$assertions['old passport zip removed'] = !file_exists($old_zip);
$assertions['new passport zip kept'] = file_exists($new_zip);
$assertions['unrelated stale file untouched (glob guard)'] = file_exists($other);

// --- PASSPORT_DELETED_MESSAGE constant -----------------------------------

$assertions['constant defined'] = defined('PASSPORT_DELETED_MESSAGE');
$assertions['constant non-empty'] =
    defined('PASSPORT_DELETED_MESSAGE') && strlen(PASSPORT_DELETED_MESSAGE) > 0;
$assertions['constant mentions privacy'] =
    defined('PASSPORT_DELETED_MESSAGE') && stripos(PASSPORT_DELETED_MESSAGE, 'privacy') !== false;

// --- teardown -------------------------------------------------------------

ucht_rmrf($root);

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
