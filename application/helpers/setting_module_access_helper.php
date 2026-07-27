<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Per-admin access to the Setting-module pages that used to be gated by role
 * level alone: Product, Customer and Guest List.
 *
 * Each page keeps its historical "base" roles (who may open it with no extra
 * permission). On top of that, an Owner can grant access to any admin via an
 * AccessControl flag on the Admin edit page — the flag OVERRIDES the role
 * block, so e.g. a Sales Agent (level 20) holding 'VPR' may reach Product.
 *
 *   Module    Flag   Base roles (allowed without the flag)
 *   product   VPR    10 OWNER, 30 FINANCE, 40 OP, 45 OP TEAM LEAD
 *   customer  VC     10 OWNER, 30 FINANCE, 40 OP
 *   guests    VGL    10 OWNER, 30 FINANCE, 40 OP   (also needs show_guest_list)
 *
 * Guest List additionally requires the global `show_guest_list` feature flag —
 * when that is off, nobody reaches it regardless of role or permission.
 */
if ( ! function_exists('setting_module_access_map'))
{
    function setting_module_access_map()
    {
        return array(
            'product'  => array('flag' => 'VPR', 'base' => array('10', '30', '40', '45')),
            'customer' => array('flag' => 'VC',  'base' => array('10', '30', '40')),
            'guests'   => array('flag' => 'VGL', 'base' => array('10', '30', '40')),
        );
    }
}

/**
 * Whether an admin may access a Setting module.
 *
 * @param string $module          'product' | 'customer' | 'guests'
 * @param mixed  $level           session level (string/int)
 * @param mixed  $access_control  session access_control codes (array)
 * @param bool   $show_guest_list global feature flag (only affects 'guests')
 * @return bool true => allowed; false => blocked
 */
if ( ! function_exists('admin_can_access_setting_module'))
{
    function admin_can_access_setting_module($module, $level, $access_control, $show_guest_list = TRUE)
    {
        $map = setting_module_access_map();
        if ( ! isset($map[$module])) {
            return FALSE;
        }

        // Guest List is hard-gated by the global feature flag.
        if ($module === 'guests' && ! $show_guest_list) {
            return FALSE;
        }

        $access_control = is_array($access_control) ? $access_control : array();

        if (in_array((string) $level, $map[$module]['base'], TRUE)) {
            return TRUE;
        }

        return in_array($map[$module]['flag'], $access_control, TRUE);
    }
}
