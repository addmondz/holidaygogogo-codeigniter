<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class CustomerSync {

    protected $CI;

    public function __construct()
    {
        // get CI super object so we can use $this->CI->db, $this->CI->load etc.
        $this->CI =& get_instance();
    }

    public function autocount_create($data = [], $config = [])
	{
		try {
			// Prepare customer (creditor) payload
			$param = [
				"accNo"             => arr_get($data, 'accNo', ''),
				"parentAccNo"       => arr_get($data, 'parentAccNo', ''),
				"companyName"       => arr_get($data, 'Customer', ''),
				"desc2"             => arr_get($data, 'desc2', ''),
				"registerNo"        => arr_get($data, 'registerNo', ''),
				"isActive"          => (bool)arr_get($data, 'isActive', true),
				"address"           => arr_get($data, 'Address', ''),
				"postCode"          => arr_get($data, 'postCode', ''),
				"phone1"            => arr_get($data, 'Mobile', ''),
				"phone2"            => arr_get($data, 'phone2', ''),
				"fax1"              => arr_get($data, 'fax1', ''),
				"fax2"              => arr_get($data, 'fax2', ''),
				"deliverAddress"    => arr_get($data, 'DeliverAddress', ''),
				"deliverPostCode"   => arr_get($data, 'DeliverPostCode', ''),
				"areaCode"          => arr_get($data, 'areaCode', ''),
				"emailAddress"      => arr_get($data, 'PrimaryEmail', ''),
				"webURL"            => arr_get($data, 'webURL', ''),
				"attention"         => arr_get($data, 'attention', ''),
				"natureOfBusiness"  => arr_get($data, 'natureOfBusiness', ''),
				"salesAgent"        => arr_get($data, 'SalesAgent', ''),
				"currencyCode"      => arr_get($data, 'CurrencyCode', 'MYR'),
				"creditTerm"        => $config['customer_creditTerm'],
				"taxCode"           => arr_get($data, 'taxCode', ''),
				"taxRegisterNo"     => arr_get($data, 'taxRegisterNo', ''),
				"note"              => arr_get($data, 'note', ''),
			];

			// Send request to AutoCount (Debtor)
			return autocount_request('POST', 'debtor.create', $param);

		} catch (Exception $e) {
			log_message('error', 'Autocount customer creation error: ' . $e->getMessage());

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
			// Prepare customer (debtor) update body
			$param = [
				"accNo"            => arr_get($data, 'CustomerCode', ''),
				"parentAccNo"      => arr_get($data, 'parentAccNo', ''),
				"companyName"      => arr_get($data, 'Customer', ''),
				"desc2"            => arr_get($data, 'desc2', ''),
				"registerNo"       => arr_get($data, 'registerNo', ''),
				"isActive"         => (bool)arr_get($data, 'isActive', true),
				"address"          => arr_get($data, 'Address', ''),
				"postCode"         => arr_get($data, 'postCode', ''),
				"phone1"           => arr_get($data, 'Mobile', ''),
				"phone2"           => arr_get($data, 'phone2', ''),
				"fax1"             => arr_get($data, 'fax1', ''),
				"fax2"             => arr_get($data, 'fax2', ''),
				"areaCode"         => arr_get($data, 'areaCode', ''),
				"emailAddress"     => arr_get($data, 'PrimaryEmail', ''),
				"webURL"           => arr_get($data, 'webURL', ''),
				"attention"        => arr_get($data, 'attention', ''),
				"natureOfBusiness" => arr_get($data, 'natureOfBusiness', ''),
				"currencyCode"     => arr_get($data, 'CurrencyCode', 'MYR'),
				"creditTerm"       => $config['customer_creditTerm'],
				"taxCode"          => arr_get($data, 'taxCode', ''),
				"taxRegisterNo"    => arr_get($data, 'taxRegisterNo', ''),
				"note"             => arr_get($data, 'note', ''),
			];

			// Send request to AutoCount customer Update API
			return autocount_request(
				'PUT',
				'debtor.update',
				$param,
				['code' => arr_get($data, 'accNo', null)]
			);

		} catch (Exception $e) {
			log_message('error', 'Autocount customer update error: ' . $e->getMessage());

			return [
				'status' => 500,
				'error'  => $e->getMessage(),
				'data'   => [],
			];
		}
	}

    public function autocount_delete($data = [], $config = [])
    {
        $docNo = isset($data['customer_code']) ? $data['customer_code'] : '';

        return autocount_request(
            'DELETE',
            'debtor.delete',
            [],
            ['code' => $docNo]
        );
    }
}
