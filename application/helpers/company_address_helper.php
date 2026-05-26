<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!defined('PDF_ADDRESS_CUTOFF_DATE')) {
    define('PDF_ADDRESS_CUTOFF_DATE', '2026-06-01');
}

if (!defined('LEGACY_COMPANY_ADDRESS')) {
    define('LEGACY_COMPANY_ADDRESS', 'NO. 47-1, JALAN SETIA GEMILANG BG U13/BG, 40170, SETIA ALAM, SELANGOR');
}

if (!defined('NEW_COMPANY_ADDRESS')) {
    define('NEW_COMPANY_ADDRESS', 'CO-18-01 & 13A, Menara Sunsuria, Sunsuria Forum, Jln Setia Dagang AL U13/AL, Setia Alam, 40170 Shah Alam, Selangor, Malaysia.');
}

if (!function_exists('pdf_company_address_for_date')) {
    function pdf_company_address_for_date($document_date)
    {
        if (empty($document_date)) {
            return NEW_COMPANY_ADDRESS;
        }
        $doc_ts = strtotime($document_date);
        $cutoff_ts = strtotime(PDF_ADDRESS_CUTOFF_DATE);
        if ($doc_ts === false || $cutoff_ts === false) {
            return NEW_COMPANY_ADDRESS;
        }
        return $doc_ts < $cutoff_ts ? LEGACY_COMPANY_ADDRESS : NEW_COMPANY_ADDRESS;
    }
}
