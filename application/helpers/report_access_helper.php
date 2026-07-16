<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Report controller methods a SALES AGENT (admin.Level = 20) may reach without
 * the VIEW REPORT ('VR') permission. These render the Lead Reply Activity
 * dashboard + its hourly drill-down; the rows/owner are self-scoped to the
 * logged-in agent's own GHL identity downstream, so the agent only ever sees
 * their own dashboard.
 */
if ( ! function_exists('report_reply_activity_self_methods'))
{
    function report_reply_activity_self_methods()
    {
        return array(
            'Lead_Reply_Activity_Dashboard',
            'Lead_Reply_Activity_Dashboard_Data',
            'Lead_Reply_Activity_Hourly',
            'Lead_Reply_Activity_Hourly_All',
            'Lead_Reply_Activity_Export',
        );
    }
}

/**
 * Decide whether a Report route must redirect away (access denied).
 *
 * - Message Log carries its own 'ML' gate, enforced per-method -> not here.
 * - Sales agents (level 20) may open the Lead Reply Activity dashboard +
 *   hourly chart without 'VR'.
 * - Everyone else needs 'VR' (VIEW REPORT).
 *
 * @param string $method          router method name
 * @param mixed  $level           session level (string/int)
 * @param mixed  $access_control  session access_control codes (array)
 * @return bool true => redirect to Dashboard (blocked); false => allowed
 */
if ( ! function_exists('report_route_requires_redirect'))
{
    function report_route_requires_redirect($method, $level, $access_control)
    {
        $access_control = is_array($access_control) ? $access_control : array();

        $message_log_methods = array('Ghl_Message_Log', 'Ghl_Message_Log_Export');
        if (in_array($method, $message_log_methods, true)) {
            return FALSE;
        }

        if ((string) $level === '20'
            && in_array($method, report_reply_activity_self_methods(), true)) {
            return FALSE;
        }

        return ! in_array('VR', $access_control, true);
    }
}
