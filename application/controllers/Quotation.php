<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Quotation extends CI_Controller {

    public function create($param = []) {
        $data = [
            'DocNo'       => $param['DocNo']       ?? '',
            'DebtorCode'  => $param['DebtorCode']  ?? '',
            'DocDate'     => $param['DocDate']     ?? date('Y-m-d'),
            'Details'     => $param['Details']     ?? []
        ];

        return autocount_request('POST', 'quotation_create', $data);
    }

    public function update($param = []) {
        $data = [
            'DocNo'       => $param['DocNo']       ?? '',
            'DebtorCode'  => $param['DebtorCode']  ?? '',
            'DocDate'     => $param['DocDate']     ?? date('Y-m-d'),
            'Details'     => $param['Details']     ?? []
        ];

        return autocount_request('POST', 'quotation_update', $data);
    }

    public function update_status($param = []) {
        $data = [
            'DocNo'  => $param['DocNo']  ?? '',
            'Status' => $param['Status'] ?? ''
        ];

        return autocount_request('POST', 'quotation_update_status', $data);
    }

    public function delete($param = []) {
        $data = [
            'DocNo' => $param['DocNo'] ?? ''
        ];

        return autocount_request('POST', 'quotation_delete', $data);
    }

    public function void($param = []) {
        $data = [
            'DocNo' => $param['DocNo'] ?? ''
        ];

        return autocount_request('POST', 'quotation_void', $data);
    }
}
