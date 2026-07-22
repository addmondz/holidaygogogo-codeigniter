<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Parse the tags of a single GHL contact for the GHL Leads listing.
 *
 * The listing query LEFT JOINs a per-contact aggregate of
 * ghl_conversations.tags_json — each conversation stores a JSON array of tag
 * strings, and one contact can hold several conversations. The model hands them
 * over as those JSON arrays GROUP_CONCAT'd together with a newline separator,
 * e.g.
 *     ["redang","2025 customers database"]
 *     ["redang","active contacts"]
 *
 * This flattens that into one clean, de-duplicated, first-seen-ordered list of
 * tag strings ready to render as chips. A truncated / invalid line (possible if
 * GROUP_CONCAT hits group_concat_max_len) is skipped rather than fatal.
 *
 * @param  string|null $concat newline-joined JSON arrays from GROUP_CONCAT
 * @return string[]            unique, trimmed tag strings (first-seen order)
 */
function ghl_lead_tags_parse($concat)
{
    if ($concat === null || trim((string) $concat) === '') {
        return array();
    }

    $out  = array();
    $seen = array();

    foreach (preg_split('/\r\n|\r|\n/', (string) $concat) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $decoded = json_decode($line, true);
        if (!is_array($decoded)) {
            continue; // truncated or malformed line — skip it, don't crash
        }

        foreach ($decoded as $tag) {
            if (!is_string($tag)) {
                continue;
            }
            $tag = trim($tag);
            if ($tag === '') {
                continue;
            }
            // Drop bracketed system tags — GHL auto-stamps device / channel tags
            // like "[whatsapp] - lead capture" and "[device] - sales - 016..."
            // onto every conversation; they're pure noise on a lead row and would
            // drown the real destination / segment tags.
            if (isset($tag[0]) && $tag[0] === '[') {
                continue;
            }
            $key = mb_strtolower($tag); // dedupe case-insensitively
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[]      = $tag;
        }
    }

    return $out;
}
