<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Time-gap bot-bounce detection for blasted WhatsApp leads.
 *
 * When we blast an outbound message, many recipient numbers are businesses
 * whose OWN auto-responder fires an inbound reply within seconds ("Thank you
 * for contacting ...", "I'm an AI assistant", etc.). That instant inbound opens
 * a brand-new lead window and inflates "New Lead Picked Up" / "Total New Leads".
 *
 * A human cannot read + reply that fast, so an inbound that lands within a few
 * seconds of OUR immediately-preceding outbound is treated as a bot bounce and
 * flagged (is_bot_bounce), so the lead reports can exclude it.
 */

if (!defined('GHL_BOT_AUTOREPLY_GAP_SECONDS')) {
    // Inbound arriving within this many seconds of our preceding outbound is a bounce.
    define('GHL_BOT_AUTOREPLY_GAP_SECONDS', 20);
}

if (!function_exists('ghl_is_bot_autoreply_bounce')) {
    /**
     * Decide whether the inbound message that opens a lead window is an instant
     * auto-reply to our own outbound blast (a bot bounce), purely from the
     * preceding message and the two timestamps.
     *
     * @param string|null    $prevDirection    direction of the message immediately before this inbound
     * @param int|false|null $prevMessageTs    unix ts of that preceding message
     * @param int|false|null $inboundTs        unix ts of the inbound opening this lead window
     * @param int|null       $thresholdSeconds override; defaults to GHL_BOT_AUTOREPLY_GAP_SECONDS
     * @return bool
     */
    function ghl_is_bot_autoreply_bounce($prevDirection, $prevMessageTs, $inboundTs, $thresholdSeconds = null)
    {
        $threshold = ($thresholdSeconds === null) ? GHL_BOT_AUTOREPLY_GAP_SECONDS : (int) $thresholdSeconds;
        if ($threshold < 0) {
            return false;
        }

        // Only an inbound that immediately follows OUR outbound can be a blast bounce.
        // A conversation that opens on the customer's own message (no preceding
        // outbound) is a genuine organic lead.
        if ((string) $prevDirection !== 'outbound') {
            return false;
        }

        if ($prevMessageTs === null || $prevMessageTs === false
            || $inboundTs === null || $inboundTs === false) {
            return false;
        }

        $gap = (int) $inboundTs - (int) $prevMessageTs;

        // Non-negative (ignore clock skew) and inside the instant-reply window.
        return $gap >= 0 && $gap <= $threshold;
    }
}
