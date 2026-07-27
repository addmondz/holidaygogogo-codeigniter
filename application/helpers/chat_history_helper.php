<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Chat-history helpers — pure, DB-free functions for the "Chat History" action
 * on the Guest / Customer / GHL Leads listing (views/guests/index.php).
 *
 * Agents export a WhatsApp chat as a .txt file and attach it to a person. These
 * helpers validate the upload and pick a safe on-disk name; the raw file is kept
 * for download only (no in-app viewer). Kept side-effect free so they unit-test
 * without a DB (see tests/helpers/ChatHistoryHelperTest.php).
 */

if (!function_exists('chat_history_validate_upload')) {
	/**
	 * Gate an uploaded chat file: .txt only, non-empty, and under the size cap.
	 * $size is the byte count; $ext_source is the original filename (extension is
	 * read from it). Returns array('ok'=>bool, 'error'=>string).
	 */
	function chat_history_validate_upload($original_name, $size)
	{
		$name = trim((string) $original_name);
		if ($name === '') {
			return array('ok' => false, 'error' => 'No file was selected.');
		}
		$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
		if ($ext !== 'txt') {
			return array('ok' => false, 'error' => 'Only .txt chat exports are allowed.');
		}
		$size = (int) $size;
		if ($size <= 0) {
			return array('ok' => false, 'error' => 'The file is empty.');
		}
		$max = 5 * 1024 * 1024; // 5 MB — WhatsApp text exports are tiny.
		if ($size > $max) {
			return array('ok' => false, 'error' => 'File too large (max 5MB).');
		}
		return array('ok' => true, 'error' => '');
	}
}

if (!function_exists('chat_history_stored_name')) {
	/**
	 * A random, traversal-proof on-disk name for a stored chat file, always
	 * ending .txt regardless of the original name (which is kept separately for
	 * download). Uses the same md5(uniqid(rand())) recipe as the passport upload.
	 */
	function chat_history_stored_name($original_name = '')
	{
		return md5(uniqid(rand(), true) . microtime(true)) . '.txt';
	}
}
