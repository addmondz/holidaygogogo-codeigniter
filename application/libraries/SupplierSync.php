<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class SupplierSync {

    protected $CI;

    public function __construct()
    {
        // get CI super object so we can use $this->CI->db, $this->CI->load etc.
        $this->CI =& get_instance();
    }

    public function autocount_create($data = [], $config = [])
	{
		try {
			// Prepare supplier (creditor) payload
			$param = [
				"accNo"            => arr_get($data, 'accNo', ''),
				"parentAccNo"      => arr_get($data, 'parentAccNo', ''),
				"companyName"      => arr_get($data, 'Name', ''),
				"desc2"            => arr_get($data, 'desc2', ''),
				"registerNo"       => arr_get($data, 'registerNo', ''),
				"isActive"         => (bool)arr_get($data, 'isActive', true),
				"address"          => arr_get($data, 'Address', ''),
				"postCode"         => arr_get($data, 'postCode', ''),
				"phone1"           => arr_get($data, 'Phone', ''),
				"phone2"           => arr_get($data, 'phone2', ''),
				"fax1"             => arr_get($data, 'fax1', ''),
				"fax2"             => arr_get($data, 'fax2', ''),
				"areaCode"         => arr_get($data, 'areaCode', ''),
				"emailAddress"     => arr_get($data, 'PrimaryEmail', ''),
				"webURL"           => arr_get($data, 'webURL', ''),
				"attention"        => arr_get($data, 'attention', ''),
				"natureOfBusiness" => arr_get($data, 'natureOfBusiness', ''),
				"currencyCode"     => arr_get($data, 'CurrencyCode', 'MYR'),
				"creditTerm"       => $config['creditTerm'],
				"taxCode"          => arr_get($data, 'taxCode', ''),
				"taxRegisterNo"    => arr_get($data, 'taxRegisterNo', ''),
				"note"             => arr_get($data, 'note', ''),
			];

			// Send request to AutoCount (Creditor)
			return autocount_request('POST', 'supplier.create', $param);

		} catch (Exception $e) {
			log_message('error', 'Autocount supplier creation error: ' . $e->getMessage());

			return [
				'status' => 500,
				'error'  => $e->getMessage(),
				'data'   => [],
			];
		}
	}

    public function autocount_update($data = [], $config = [])
	{
		try {
			// Prepare supplier (creditor) update body
			$param = [
				"accNo"            => arr_get($data, 'SupplierCode', ''),
				"parentAccNo"      => arr_get($data, 'parentAccNo', ''),
				"companyName"      => arr_get($data, 'Name', ''),
				"desc2"            => arr_get($data, 'desc2', ''),
				"registerNo"       => arr_get($data, 'registerNo', ''),
				"isActive"         => (bool)arr_get($data, 'isActive', true),
				"address"          => arr_get($data, 'Address', ''),
				"postCode"         => arr_get($data, 'postCode', ''),
				"phone1"           => arr_get($data, 'Phone', ''),
				"phone2"           => arr_get($data, 'phone2', ''),
				"fax1"             => arr_get($data, 'fax1', ''),
				"fax2"             => arr_get($data, 'fax2', ''),
				"areaCode"         => arr_get($data, 'areaCode', ''),
				"emailAddress"     => arr_get($data, 'PrimaryEmail', ''),
				"webURL"           => arr_get($data, 'webURL', ''),
				"attention"        => arr_get($data, 'attention', ''),
				"natureOfBusiness" => arr_get($data, 'natureOfBusiness', ''),
				"currencyCode"     => arr_get($data, 'CurrencyCode', 'MYR'),
				"creditTerm"       => $config['creditTerm'],
				"taxCode"          => arr_get($data, 'taxCode', ''),
				"taxRegisterNo"    => arr_get($data, 'taxRegisterNo', ''),
				"note"             => arr_get($data, 'note', ''),
			];

			// Send request to AutoCount Supplier Update API
			return autocount_request(
				'PUT',
				'supplier.update',
				$param,
				['accNo' => arr_get($data, 'accNo', null)]
			);

		} catch (Exception $e) {
			log_message('error', 'Autocount supplier update error: ' . $e->getMessage());

			return [
				'status' => 500,
				'error'  => $e->getMessage(),
				'data'   => [],
			];
		}
	}

    public function autocount_delete($data = [], $config = [])
    {
        $docNo = isset($data['supplier_code']) ? $data['supplier_code'] : '';

        return autocount_request(
            'DELETE',
            'payment.delete',
            [],
            ['code' => $docNo]
        );
    }
}
