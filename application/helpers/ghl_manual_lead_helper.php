<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Prepare a hand-entered ("Manual") GHL lead for insertion into ghl_contacts.
 *
 * The GHL Leads page normally reads contacts synced from the GHL API (upserted
 * by their GHL contact_id). A user can also create a lead by hand: we store it
 * in the SAME table so it shows in the same listing, but flag it lead_source =
 * 'manual' and give it a SYNTHETIC contact_id ("manual:<uid>") that can never
 * collide with a real GHL id — so the API sync (which upserts by GHL id) never
 * touches, overwrites or deletes a manual lead.
 *
 * This is a pure mapper/validator (no DB, no clock, no randomness) so it unit
 * tests in isolation: the caller passes the unique suffix and the timestamp.
 *
 * Fields mirror the GHL Leads columns: first/last name, contact number, email,
 * tags, gender, language, race, nationality, date of birth. A lead needs at
 * least ONE identifying value (name, phone or email) — the same "not blank"
 * rule the booking branch applies — otherwise it would be an invisible, useless
 * "—" row.
 *
 * @param array  $post request fields (first_name,last_name,phone,email,tags,
 *                     gender,chat_language,race,nationality,date_of_birth)
 * @param string $uid  a unique suffix for the synthetic contact_id (caller supplies)
 * @param string $now  'Y-m-d H:i:s' capture time (caller supplies)
 * @return array{ok:bool,errors:string[],row:array}
 */
function ghl_manual_lead_prepare($post, $uid, $now)
{
    $post = is_array($post) ? $post : array();

    $get = function ($key) use ($post) {
        return isset($post[$key]) ? trim((string) $post[$key]) : '';
    };

    $first_name = $get('first_name');
    $last_name  = $get('last_name');
    $phone      = $get('phone');
    $email      = $get('email');
    $gender     = $get('gender');
    $language   = $get('chat_language');
    $race       = $get('race');
    $nationality = $get('nationality');
    $dob_raw    = $get('date_of_birth');
    $tags_raw   = isset($post['tags']) ? (string) $post['tags'] : '';

    $errors = array();

    // A lead must carry at least one identifying value, else it is a blank row.
    if ($first_name === '' && $last_name === '' && $phone === '' && $email === '') {
        $errors[] = 'Enter at least a name, contact number or email.';
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email address is not valid.';
    }

    // Accept only a real calendar date; HTML5 <input type="date"> emits Y-m-d.
    $dob = ghl_manual_lead_normalize_dob($dob_raw);
    if ($dob_raw !== '' && $dob === null) {
        $errors[] = 'Date of birth is not a valid date.';
    }

    $gender = ghl_manual_lead_normalize_gender($gender);

    $tags_json = ghl_manual_lead_encode_tags($tags_raw);

    if (!empty($errors)) {
        return array('ok' => false, 'errors' => $errors, 'row' => array());
    }

    $row = array(
        'contact_id'    => 'manual:' . $uid,
        'lead_source'   => 'manual',
        'first_name'    => $first_name !== '' ? $first_name : null,
        'last_name'     => $last_name !== '' ? $last_name : null,
        'phone'         => $phone !== '' ? $phone : null,
        'email'         => $email !== '' ? $email : null,
        'gender'        => $gender !== '' ? $gender : null,
        'chat_language' => $language !== '' ? $language : null,
        'race'          => $race !== '' ? $race : null,
        'nationality'   => $nationality !== '' ? $nationality : null,
        'date_of_birth' => $dob,
        'tags_json'     => $tags_json,
        'date_added'    => $now,
    );

    return array('ok' => true, 'errors' => array(), 'row' => $row);
}

/**
 * Normalize the free-typed tags field into the SAME storage shape the GHL Leads
 * listing already parses (a JSON array of tag strings, exactly like one
 * ghl_conversations.tags_json row), so ghl_lead_tags_parse() renders manual and
 * synced tags identically. Splits on comma / newline, trims, drops blanks, and
 * de-duplicates case-insensitively (first-seen order). Returns null when empty.
 *
 * @param string $raw
 * @return string|null JSON array, or null when no tags
 */
function ghl_manual_lead_encode_tags($raw)
{
    $raw = (string) $raw;
    if (trim($raw) === '') {
        return null;
    }

    $out  = array();
    $seen = array();
    foreach (preg_split('/[,\r\n]+/', $raw) as $tag) {
        $tag = trim($tag);
        if ($tag === '') {
            continue;
        }
        $key = function_exists('mb_strtolower') ? mb_strtolower($tag) : strtolower($tag);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out[] = $tag;
    }

    if (empty($out)) {
        return null;
    }

    return json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Accept only an actual Y-m-d calendar date (what <input type="date"> sends);
 * anything else — blank, "0000-00-00", garbage — becomes null.
 *
 * @param string $raw
 * @return string|null 'Y-m-d' or null
 */
function ghl_manual_lead_normalize_dob($raw)
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', $raw);
    if ($d === false) {
        return null;
    }
    // createFromFormat is lax (2026-13-40 rolls over); reject if it round-trips
    // to a different string.
    if ($d->format('Y-m-d') !== $raw) {
        return null;
    }
    return $raw;
}

/**
 * Constrain gender to the three values the Gender filter/column uses; anything
 * else is dropped to '' (stored as null by the caller).
 *
 * @param string $raw
 * @return string 'Male' | 'Female' | 'Other' | ''
 */
function ghl_manual_lead_normalize_gender($raw)
{
    $raw = trim((string) $raw);
    foreach (array('Male', 'Female', 'Other') as $allowed) {
        if (strcasecmp($raw, $allowed) === 0) {
            return $allowed;
        }
    }
    return '';
}
