<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('next_customer_code')) {
    /**
     * Pick the next AutoCount debtor code for a customer name.
     *
     * Format: "<prefix>-<FirstLetter><seq>", e.g. "303-T126". The prefix runs
     * 303..399; within each prefix the running number is 001..999. We take the
     * highest number already used for that prefix and step forward to the first
     * number that is NOT in $used_codes.
     *
     * We never re-use a freed/lower number: the same code may still live in
     * AutoCount's Chart of Account, and re-issuing it triggers the
     * `AccNo "303-T126" exists in Chart of Account` failure on sync. The
     * !isset() guard is a defensive backstop in case the used-set is not
     * contiguous (e.g. a code pulled back from AutoCount via docNo).
     *
     * NOTE: pass EVERY code currently in use (local rows + AutoCount-issued).
     * This avoids re-issuing a code that exists but was missed by a naive
     * MAX()+1 over a stale subset. It does NOT defend against a truly
     * concurrent insert — that is handled in code by the advisory lock and
     * code_exists() guard in Customer_Model::create_with_generated_code().
     *
     * @param string   $customer_name
     * @param string[] $used_codes  All CustomerCodes currently in use.
     * @return string|null Null when the name is empty or 303..399 is exhausted.
     */
    function next_customer_code($customer_name, array $used_codes)
    {
        $customer_name = trim((string) $customer_name);
        if ($customer_name === '') {
            return null;
        }

        $first_char = substr($customer_name, 0, 1);
        if (ctype_alpha($first_char)) {
            $first_char = strtoupper($first_char);
        }

        // O(1) membership test for the "is this exact code taken?" backstop.
        $used = array_flip($used_codes);

        for ($prefix_num = 303; $prefix_num <= 399; $prefix_num++) {
            $prefix = $prefix_num . '-' . $first_char;
            $plen   = strlen($prefix);

            // Highest running number already taken for this prefix.
            $max_seq = 0;
            foreach ($used_codes as $code) {
                if (strncmp($code, $prefix, $plen) === 0) {
                    $tail = substr($code, $plen);
                    if (ctype_digit($tail)) {
                        $seq = (int) $tail;
                        if ($seq > $max_seq) {
                            $max_seq = $seq;
                        }
                    }
                }
            }

            for ($seq = $max_seq + 1; $seq <= 999; $seq++) {
                $candidate = $prefix . sprintf('%03d', $seq);
                if (!isset($used[$candidate])) {
                    return $candidate;
                }
            }
        }

        return null;
    }
}

if (!function_exists('is_customer_code_clash')) {
    /**
     * Whether an AutoCount sync error means the AccNo we sent already exists in
     * AutoCount's Chart of Account.
     *
     * This happens when a CustomerCode lives in AutoCount but NOT in the local
     * `customer` table (an orphaned debtor — e.g. a customer deleted locally or
     * whose code was changed), so local generation re-issues it and AutoCount
     * rejects the create with `AccNo "303-T126" exists in Chart of Account`.
     * Detecting it lets the sync bump to the next free code and retry.
     *
     * @param string|null $message AutoCount error string ($result['error']).
     * @return bool
     */
    function is_customer_code_clash($message)
    {
        if (!is_string($message) || $message === '') {
            return false;
        }

        return stripos($message, 'exists in Chart of Account') !== false;
    }
}
