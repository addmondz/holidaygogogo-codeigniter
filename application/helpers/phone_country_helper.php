<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Country-code phone helpers, shared by the Manual Lead and Customer create/edit
 * forms. Both modules store the phone in a SINGLE column, so we store the number
 * WITH its dial code in the canonical shape "+60 123456789" (dial code, one
 * space, local number). These pure functions build and read back that shape so
 * the picker can be pre-filled on edit.
 *
 * Pure (no DB, no clock) so they unit-test in isolation.
 */

if (!function_exists('phone_country_normalize_code')) {
    /**
     * Normalise a dial code to "+<digits>". Accepts "+60", "60", " +60 "; returns
     * '' when there is no code or it is not 1-4 digits (garbage).
     *
     * @param string $code
     * @return string e.g. "+60", or '' when absent/invalid
     */
    function phone_country_normalize_code($code)
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $code);
        if ($digits === '' || strlen($digits) > 4) {
            return '';
        }
        return '+' . $digits;
    }
}

if (!function_exists('phone_country_combine')) {
    /**
     * Combine a dial code and a local number into the stored shape
     * "+60 123456789". Drops a single leading trunk "0" from the local part
     * (mirrors guest_contact_format_display). With no/invalid code the local
     * number is passed through unchanged (legacy / bulk-import numbers that
     * already carry their own prefix). Returns '' when there is no local number.
     *
     * @param string $code  dial code, e.g. "+60"
     * @param string $local local number, e.g. "0123456789"
     * @return string stored phone, or ''
     */
    function phone_country_combine($code, $local)
    {
        $local = trim((string) $local);
        if ($local === '') {
            return '';
        }
        $code = phone_country_normalize_code($code);
        if ($code === '') {
            return $local;
        }
        if ($local[0] === '0') {
            $local = substr($local, 1);
        }
        if ($local === '') {
            return $code;
        }
        return $code . ' ' . $local;
    }
}

if (!function_exists('phone_country_split')) {
    /**
     * Read a stored phone "+60 123456789" back into its parts for pre-filling the
     * picker on edit: array('code' => '+60', 'local' => '123456789'). A legacy
     * value with no leading "+<code> " returns array('code' => '', 'local' => the
     * whole string) so the user can pick a code without losing the number.
     *
     * @param string $stored
     * @return array{code:string,local:string}
     */
    function phone_country_split($stored)
    {
        $stored = trim((string) $stored);
        if ($stored === '') {
            return array('code' => '', 'local' => '');
        }
        if (preg_match('/^(\+\d{1,4})\s+(\S.*)$/', $stored, $m)) {
            return array('code' => $m[1], 'local' => trim($m[2]));
        }
        return array('code' => '', 'local' => $stored);
    }
}

if (!function_exists('phone_country_has_code')) {
    /**
     * True when a stored phone carries a dial code in the canonical shape
     * "+<code> <number>". Used for the "must have country code" server guard.
     *
     * @param string $stored
     * @return bool
     */
    function phone_country_has_code($stored)
    {
        return (bool) preg_match('/^\+\d{1,4}\s+\S/', trim((string) $stored));
    }
}
