<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Guest List auto-save safety helpers.
 *
 * The GL lock-timer auto-save (Guest_List::auto_save) posts whatever is in
 * the form — which, for a stale/leftover tab, is a blank form. Writing those
 * blanks over already-saved guest data wiped real names/IC/passport. These
 * helpers make auto-save NON-DESTRUCTIVE: a blank incoming field is dropped so
 * it can never overwrite an existing value. The normal submit (Guest_List::
 * index) still clears fields on purpose, so it does NOT use this.
 */

if (!function_exists('gl_autosave_prune_blanks')) {
    /**
     * Drop keys whose value is null or an empty/whitespace string so an
     * auto-save only ever writes the values the user actually typed. Keys in
     * $always_keep are preserved regardless (structural columns the write path
     * needs). Returns the pruned field array.
     */
    function gl_autosave_prune_blanks(array $fields, array $always_keep = array())
    {
        $out = array();
        foreach ($fields as $key => $value) {
            if (in_array($key, $always_keep, true)) {
                $out[$key] = $value;
                continue;
            }
            if ($value === null) {
                continue;
            }
            if (is_string($value) && trim($value) === '') {
                continue;
            }
            $out[$key] = $value;
        }
        return $out;
    }
}

if (!function_exists('gl_autosave_should_write')) {
    /**
     * Whether an auto-save is allowed to persist at all. The clobber fires the
     * instant the GL lock timer hits zero — i.e. when the lock is expired — and
     * a stale tab may no longer own the lock. Refuse the write when the lock is
     * missing/expired or held by a different session, so a dead form can never
     * touch the row.
     *
     * @param bool $lock_expired   Result of Guest_list_lock_model::isExpired()
     * @param bool $is_same_owner  Result of Guest_list_lock_model::isSameOwner()
     * @return bool
     */
    function gl_autosave_should_write($lock_expired, $is_same_owner)
    {
        return (!$lock_expired) && ($is_same_owner === true);
    }
}
