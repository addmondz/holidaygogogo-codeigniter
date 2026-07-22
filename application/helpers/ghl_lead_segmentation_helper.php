<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * GHL processed-lead segmentation rules.
 *
 * Decides whether an inbound message should close the current processed lead
 * and open a brand-new one while Cron rebuilds a conversation's lead stream.
 */

if (!defined('GHL_LEAD_INACTIVITY_SPLIT_SECONDS')) {
    // Unconverted conversations split into a new lead after 90 days of silence.
    define('GHL_LEAD_INACTIVITY_SPLIT_SECONDS', 90 * 86400);
}

if (!defined('GHL_LEAD_POST_CONVERSION_SPLIT_SECONDS')) {
    // After a lead has converted, an ongoing conversation only becomes a new
    // lead once it has gone quiet for 24h (rolling gap between messages).
    define('GHL_LEAD_POST_CONVERSION_SPLIT_SECONDS', 86400);
}

if (!function_exists('ghl_should_start_new_processed_lead')) {
    /**
     * @param array|null $currentLead          The lead currently being built (null before the first one).
     * @param array      $existingConversions   Map keyed by "lead_started_at|first_customer_message_id".
     * @param int|null   $lastMessageTimestamp  Unix ts of the previous message in the stream.
     * @param int        $messageTimestamp      Unix ts of the inbound message under consideration.
     * @param int        $inactivitySplitSeconds      Gap that splits an unconverted lead.
     * @param int        $postConversionSplitSeconds  Gap that splits a converted lead.
     * @return bool
     */
    function ghl_should_start_new_processed_lead(
        $currentLead,
        $existingConversions,
        $lastMessageTimestamp,
        $messageTimestamp,
        $inactivitySplitSeconds = GHL_LEAD_INACTIVITY_SPLIT_SECONDS,
        $postConversionSplitSeconds = GHL_LEAD_POST_CONVERSION_SPLIT_SECONDS
    ) {
        if ($currentLead === null) {
            return false;
        }

        // Out-of-order or first message: never split on a non-positive gap.
        if ($lastMessageTimestamp === null || $messageTimestamp < $lastMessageTimestamp) {
            return false;
        }

        $gap = $messageTimestamp - $lastMessageTimestamp;

        $conversionKey = $currentLead['lead_started_at'] . '|' . $currentLead['first_customer_message_id'];
        if (!empty($existingConversions[$conversionKey]['converted_at'])) {
            $convertedAt = strtotime((string) $existingConversions[$conversionKey]['converted_at']);
            if ($convertedAt !== false && $messageTimestamp > $convertedAt) {
                // Post-conversion: keep follow-on chatter on the converted lead
                // until the conversation has been quiet for the 24h window.
                return $gap >= $postConversionSplitSeconds;
            }
        }

        return $gap >= $inactivitySplitSeconds;
    }
}
