<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!defined('PASSPORT_DELETED_MESSAGE')) {
    define(
        'PASSPORT_DELETED_MESSAGE',
        'The personal travel documents has been deleted automatically due to privacy concern. Please contact us if you wish to submit the latest travel documents for further processing.'
    );
}

if (!function_exists('backup_directory_to_zip')) {
    /**
     * Zip every regular file directly inside $source_dir into a new
     * archive under $backup_dir. Subdirectories are skipped. Returns the
     * absolute path of the created zip, or null when the source is missing
     * or empty (so the caller does not produce empty backup files).
     */
    function backup_directory_to_zip($source_dir, $backup_dir, $prefix)
    {
        if (!is_dir($source_dir)) {
            return null;
        }

        $files = [];
        foreach ((array) glob(rtrim($source_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*') as $entry) {
            if (is_file($entry)) {
                $files[] = $entry;
            }
        }
        if (empty($files)) {
            return null;
        }

        if (!is_dir($backup_dir) && !mkdir($backup_dir, 0755, true) && !is_dir($backup_dir)) {
            return null;
        }

        $zip_path = rtrim($backup_dir, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . $prefix . '_' . date('Ymd_His') . '.zip';

        $zip = new \ZipArchive();
        if ($zip->open($zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        foreach ($files as $file) {
            $zip->addFile($file, basename($file));
        }
        $zip->close();

        return $zip_path;
    }
}

if (!function_exists('delete_files_older_than')) {
    /**
     * Unlink every regular file directly inside $dir whose mtime is older
     * than $age_days, except basenames listed in $protected_basenames
     * (used by the caller to spare files referenced by still-active
     * bookings). Subdirectories are left alone. Returns an array of
     * deleted basenames so callers can map them back to DB rows.
     */
    function delete_files_older_than($dir, $age_days, array $protected_basenames = [])
    {
        if (!is_dir($dir)) {
            return [];
        }

        $cutoff = time() - ((int) $age_days * 86400);
        $protected = array_flip($protected_basenames);
        $deleted = [];

        foreach ((array) glob(rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*') as $entry) {
            if (!is_file($entry)) {
                continue;
            }
            $base = basename($entry);
            if (isset($protected[$base])) {
                continue;
            }
            $mtime = @filemtime($entry);
            if ($mtime === false || $mtime >= $cutoff) {
                continue;
            }
            if (@unlink($entry)) {
                $deleted[] = $base;
            }
        }

        return $deleted;
    }
}

if (!function_exists('prune_backups_older_than')) {
    /**
     * Unlink files in $dir whose name matches $glob_pattern and whose mtime
     * is older than $age_days. Restricted to the pattern so unrelated
     * artefacts (e.g. DB dumps sharing the backups/ directory) are never
     * touched. Returns the count removed.
     */
    function prune_backups_older_than($dir, $age_days, $glob_pattern)
    {
        if (!is_dir($dir)) {
            return 0;
        }

        $cutoff = time() - ((int) $age_days * 86400);
        $count = 0;

        foreach ((array) glob(rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $glob_pattern) as $entry) {
            if (!is_file($entry)) {
                continue;
            }
            $mtime = @filemtime($entry);
            if ($mtime === false || $mtime >= $cutoff) {
                continue;
            }
            if (@unlink($entry)) {
                $count++;
            }
        }

        return $count;
    }
}
