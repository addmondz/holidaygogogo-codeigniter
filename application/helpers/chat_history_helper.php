<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Chat-history helpers — pure, DB-free functions for the "Chat History" action
 * on the Guest / Customer / GHL Leads listing (views/guests/index.php).
 *
 * Agents export a WhatsApp chat as a .txt file and attach it to a person. These
 * helpers validate the upload, pick a safe on-disk name, parse the export into
 * messages the viewer renders as chat bubbles, and decide which side (ours vs
 * the customer) a message sits on. Kept side-effect free so they unit-test
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
		if ($ext !== 'txt' && $ext !== 'zip') {
			return array('ok' => false, 'error' => 'Only .txt or .zip chat exports are allowed.');
		}
		$size = (int) $size;
		if ($size <= 0) {
			return array('ok' => false, 'error' => 'The file is empty.');
		}
		// WhatsApp text exports are tiny; a .zip may bundle many of them.
		$max = ($ext === 'zip') ? 30 * 1024 * 1024 : 5 * 1024 * 1024;
		if ($size > $max) {
			$cap = ($ext === 'zip') ? '30MB' : '5MB';
			return array('ok' => false, 'error' => 'File too large (max ' . $cap . ').');
		}
		return array('ok' => true, 'error' => '');
	}
}

if (!function_exists('chat_history_zip_entry_is_txt')) {
	/**
	 * Decide whether a ZIP entry should be extracted as a chat export: a real
	 * .txt file only. Skips directory entries, macOS archive junk (__MACOSX/
	 * folder + ._ AppleDouble resource forks) and any other hidden dotfiles, so
	 * a zip made on a Mac doesn't produce phantom/garbage chat records.
	 */
	function chat_history_zip_entry_is_txt($entry_name)
	{
		$name = str_replace('\\', '/', (string) $entry_name);
		if ($name === '' || substr($name, -1) === '/') {
			return false; // directory entry
		}
		if (strpos($name, '__MACOSX/') !== false) {
			return false;
		}
		$base = basename($name);
		if ($base === '' || $base[0] === '.') {
			return false; // ._foo AppleDouble forks and other dotfiles
		}
		return strtolower(pathinfo($base, PATHINFO_EXTENSION)) === 'txt';
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

if (!function_exists('chat_history_is_outbound')) {
	/**
	 * True when a message sender is us (the company), so the viewer floats it to
	 * the right. WhatsApp names our line like "Holidaygogogo Tours Simon"; match
	 * the brand loosely (spaces/case ignored) so any staff suffix still counts.
	 * iOS / WhatsApp-Web exports label our own line simply "You" — treat that as
	 * outbound too (the exporter is always us).
	 */
	function chat_history_is_outbound($sender)
	{
		$s = strtolower((string) $sender);
		$s = str_replace(array(' ', '-', '_'), '', $s);
		if ($s === 'you') {
			return true;
		}
		return strpos($s, 'holidaygogogo') !== false;
	}
}

if (!function_exists('chat_history_parse')) {
	/**
	 * Parse a WhatsApp .txt export into an ordered list of messages:
	 *   array('ts'=>string, 'sender'=>string, 'body'=>string,
	 *         'system'=>bool, 'outbound'=>bool)
	 *
	 * A new message begins on a timestamped header line, in either export dialect:
	 *   - Android : "<date>, <time> - <rest>"
	 *   - iOS/Web : "[<date>, <time>] <rest>"  (bracketed, seconds in the time,
	 *               an optional leading LTR mark, "] - " system notices)
	 * The <rest> is "Sender: body" for a chat line, or a bare notice (E2E-encryption
	 * banner, "Missed voice call", …) for a system line — flagged system=true with an
	 * empty sender. Lines that don't start a new message are continuation lines
	 * and fold onto the previous message's body (multi-line messages). Fully
	 * blank system lines are dropped.
	 */
	function chat_history_parse($text)
	{
		$text  = str_replace(array("\r\n", "\r"), "\n", (string) $text);
		$lines = explode("\n", $text);

		// Android: "7/23/26, 1:35 PM - rest" / "13/07/2026, 13:35 - rest" (24h too).
		$head_android = '/^(\d{1,2}\/\d{1,2}\/\d{2,4}),\s+(\d{1,2}:\d{2}(?::\d{2})?(?:\s?[APap][Mm])?)\s+-\s+(.*)$/u';
		// iOS/Web: "[9/4/26, 2:43:02 PM] rest" (optional leading LTR mark \x{200e}).
		$head_ios     = '/^\x{200e}?\[(\d{1,2}\/\d{1,2}\/\d{2,4}),\s+(\d{1,2}:\d{2}(?::\d{2})?(?:\s?[APap][Mm])?)\]\s*(.*)$/u';

		$messages = array();
		$last     = -1; // index of the message continuation lines append to.

		foreach ($lines as $line) {
			$m      = null;
			$is_ios = false;
			if (preg_match($head_android, $line, $m)) {
				// matched Android dialect
			} elseif (preg_match($head_ios, $line, $m)) {
				$is_ios = true;
			}
			if ($m) {
				$ts   = trim($m[1] . ', ' . $m[2]);
				$rest = $m[3];

				// iOS system notices come as "] - <notice>"; drop the leading dash
				// so they read the same as the Android bare-notice form.
				if ($is_ios) {
					$rest = preg_replace('/^-\s+/u', '', $rest);
				}

				// "Sender: body" → chat line; no "Name: " prefix → system notice.
				$sender = '';
				$body   = $rest;
				$system = true;
				if (preg_match('/^([^:\n]{1,80}?):\s(.*)$/us', $rest, $mm)) {
					$sender = trim($mm[1]);
					$body   = $mm[2];
					$system = false;
				}

				if ($system && trim($body) === '') {
					// A bare "…- " placeholder line carries nothing; skip it, but
					// keep continuation folding pointed at the real prior message.
					continue;
				}

				$messages[] = array(
					'ts'       => $ts,
					'sender'   => $sender,
					'body'     => $body,
					'system'   => $system,
					'outbound' => $system ? false : chat_history_is_outbound($sender),
				);
				$last = count($messages) - 1;
			} else {
				// Continuation of the current message (a wrapped/multi-line body).
				if ($last >= 0) {
					$messages[$last]['body'] .= "\n" . $line;
				}
			}
		}

		return $messages;
	}
}
