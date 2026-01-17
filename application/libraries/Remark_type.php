<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * REMARK_TYPE Class
 * 
 * Laravel-style enum class for remark types
 * Usage: REMARK_TYPE::INTERNAL returns 1
 *        REMARK_TYPE::CUSTOMER returns 2
 */
class REMARK_TYPE
{
    const INTERNAL = 1;
    const CUSTOMER = 2;
    
    /**
     * Get all remark types as array
     * 
     * @return array
     */
    public static function all()
    {
        return [
            self::INTERNAL => 'INTERNAL',
            self::CUSTOMER => 'CUSTOMER'
        ];
    }
    
    /**
     * Get label for a remark type
     * 
     * @param int $type
     * @return string|null
     */
    public static function getLabel($type)
    {
        $types = self::all();
        return isset($types[$type]) ? $types[$type] : null;
    }
    
    /**
     * Check if a type is valid
     * 
     * @param int $type
     * @return bool
     */
    public static function isValid($type)
    {
        return in_array($type, array_keys(self::all()));
    }
}
