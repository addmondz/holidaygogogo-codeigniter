<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class BookingSync {

    protected $CI;

    public function __construct()
    {
        // get CI super object so we can use $this->CI->db, $this->CI->load etc.
        $this->CI =& get_instance();
    }

    public function autocount_create($data)
	{
		try {
			$body['master'] = [
				'docNo'           => arr_get($data, 'BookingNumber'),
				'docNoFormatName' => arr_get($data, 'docNoFormatName', null),
				'docDate'         => date('Y-m-d', strtotime(arr_get($data, 'InsertDate'))),
				'debtorCode'      => arr_get($data, 'debtor_code', ''),
				'debtorName'      => arr_get($data, 'Customer'),
				'email'           => arr_get($data, 'guest_email'),
				'emailCC'         => arr_get($data, 'emailCC', null),
				'emailBCC'        => arr_get($data, 'emailBCC', null),
				'address'         => arr_get($data, 'guest_address'),
				'attention'       => arr_get($data, 'attention', ''),
				'phone1'          => arr_get($data, 'guest_phone'),
				'fax1'            => arr_get($data, 'fax1', ''),
				'deliverAddress'  => arr_get($data, 'guest_address'),
				'deliverContact'  => arr_get($data, 'Customer'),
				'deliverPhone1'   => arr_get($data, 'guest_phone'),
				'deliverFax1'     => arr_get($data, 'deliver_fax1', ''),
				'ref'             => arr_get($data, 'ref', null),
				'description'     => arr_get($data, 'description', null),
				'note'            => arr_get($data, 'note', null),
				'salesAgent'      => arr_get($data, 'salesAgent', ''),
				'creditTerm'      => arr_get($data, 'credit_term', 'C.O.D.'),
				'salesLocation'   => arr_get($data, 'sales_location', 'HQ'),
				'remark1'         => arr_get($data, 'BokingRemark'),
				'remark2'         => arr_get($data, 'remark2', null),
				'remark3'         => arr_get($data, 'remark3', null),
				'remark4'         => arr_get($data, 'remark4', null),
				'currencyRate'    => arr_get($data, 'currency_rate', 1),
				'inclusiveTax'    => arr_get($data, 'inclusive_tax', false),
				'isRoundAdj'      => arr_get($data, 'is_round_adj', false),
				'yourRef'         => arr_get($data, 'yourRef', null),
				'validity'        => arr_get($data, 'validity', null),
				'cc'              => arr_get($data, 'cc', null),
				'deliveryTerm'    => arr_get($data, 'deliveryTerm', null),
				'paymentTerm'     => arr_get($data, 'paymentTerm', null),
			];

			$body['details'] = [];
			if (!empty($data['booking_product']) && is_array($data['booking_product'])) {
				foreach ($data['booking_product'] as $product) {
					$body['details'][] = [
						'productCode'        => arr_get($product, 'product_ProductCode'),
						'productVariant'     => arr_get($product, 'productVariant', null),
						'description'        => arr_get($product, 'product_Name'),
						'furtherDescription' => arr_get($product, 'product_Description', ''),
						'qty'                => (float)arr_get($product, 'product_Quantity', 1),
						'unit'               => arr_get($product, 'unit', 'unit'),
						'unitPrice'          => (float)arr_get($product, 'product_Price', 0),
						'discount'           => arr_get($product, 'discount', null),
						'taxCode'            => arr_get($product, 'tax_code'),
						'taxAdjustment'      => arr_get($product, 'taxAdjustment', 0),
						'localTaxAdjustment' => arr_get($product, 'localTaxAdjustment', 0),
						'deptNo'             => arr_get($product, 'deptNo', null),
					];
				}
			}

			$body['autoFillOption'] = [
				'taxCode' => arr_get($data, 'tax_code', true),
			];

			$body['saveApprove'] = arr_get($data, 'save_approve', null);

			return autocount_request(
				'POST',
				'quotation.create',
				$body,
				['docNo' => $data['BookingNumber']]
			);

		} catch (Exception $e) {
			log_message('error', 'Autocount create error: ' . $e->getMessage());

			return [
				'status' => 500,
				'error'  => $e->getMessage(),
				'data'   => [],
			];
		}
	}

   	public function autocount_update($data)
	{
		try {
			$docNo = arr_get($data, 'BookingNumber');

			$body['master'] = [
				'docNo'           => arr_get($data, 'BookingNumber'),
				'docNoFormatName' => arr_get($data, 'docNoFormatName', null),
				'docDate'         => date('Y-m-d', strtotime(arr_get($data, 'InsertDate'))),
				'debtorCode'      => arr_get($data, 'debtor_code', ''),
				'debtorName'      => arr_get($data, 'Customer'),
				'email'           => arr_get($data, 'guest_email'),
				'emailCC'         => arr_get($data, 'emailCC', null),
				'emailBCC'        => arr_get($data, 'emailBCC', null),
				'address'         => arr_get($data, 'guest_address'),
				'attention'       => arr_get($data, 'attention', ''),
				'phone1'          => arr_get($data, 'guest_phone'),
				'fax1'            => arr_get($data, 'fax1', ''),
				'deliverAddress'  => arr_get($data, 'guest_address'),
				'deliverContact'  => arr_get($data, 'Customer'),
				'deliverPhone1'   => arr_get($data, 'guest_phone'),
				'deliverFax1'     => arr_get($data, 'deliver_fax1', ''),
				'ref'             => arr_get($data, 'ref', null),
				'description'     => arr_get($data, 'description', null),
				'note'            => arr_get($data, 'note', null),
				'salesAgent'      => arr_get($data, 'salesAgent', ''),
				'creditTerm'      => arr_get($data, 'credit_term', 'C.O.D.'),
				'salesLocation'   => arr_get($data, 'sales_location', 'HQ'),
				'remark1'         => arr_get($data, 'BokingRemark'),
				'remark2'         => arr_get($data, 'remark2', null),
				'remark3'         => arr_get($data, 'remark3', null),
				'remark4'         => arr_get($data, 'remark4', null),
				'currencyRate'    => arr_get($data, 'currency_rate', 1),
				'inclusiveTax'    => arr_get($data, 'inclusive_tax', false),
				'isRoundAdj'      => arr_get($data, 'is_round_adj', false),
				'yourRef'         => arr_get($data, 'yourRef', null),
				'validity'        => arr_get($data, 'validity', null),
				'cc'              => arr_get($data, 'cc', null),
				'deliveryTerm'    => arr_get($data, 'deliveryTerm', null),
				'paymentTerm'     => arr_get($data, 'paymentTerm', null),
			];

			$body['details'] = [];
			if (!empty($data['booking_product']) && is_array($data['booking_product'])) {
				foreach ($data['booking_product'] as $product) {
					$body['details'][] = [
						'productCode'        => arr_get($product, 'product_ProductCode'),
						'productVariant'     => arr_get($product, 'productVariant', null),
						'description'        => arr_get($product, 'product_Name'),
						'furtherDescription' => arr_get($product, 'product_Description', ''),
						'qty'                => (float)arr_get($product, 'product_Quantity', 1),
						'unit'               => arr_get($product, 'unit', 'unit'),
						'unitPrice'          => (float)arr_get($product, 'product_Price', 0),
						'discount'           => arr_get($product, 'discount', null),
						'taxCode'            => arr_get($product, 'tax_code'),
						'taxAdjustment'      => arr_get($product, 'taxAdjustment', 0),
						'localTaxAdjustment' => arr_get($product, 'localTaxAdjustment', 0),
						'deptNo'             => arr_get($product, 'deptNo', null),
					];
				}
			}

			$body['autoFillOption'] = [
				'taxCode' => arr_get($data, 'tax_code', true),
			];

			$body['saveApprove'] = arr_get($data, 'save_approve', null);

			return autocount_request(
				'PUT',
				'quotation.update',
				$body,
				['docNo' => $docNo]
			);

		} catch (Exception $e) {
			log_message('error', 'Autocount update error: ' . $e->getMessage());

			return [
				'status' => 500,
				'error'  => $e->getMessage(),
				'data'   => [],
			];
		}
	}

	public function autocount_update_status($data)
	{
		try {
			$docNo = arr_get($data, 'BookingNumber');
			
			// Map the status to AutoCount status code
			$status = arr_get($data, 'Status', 'Y');
			$autoCountStatus = mapAutoCountStatus($status);  // Use the mapping helper

			// If no valid status is found, log an error and return
			if ($autoCountStatus === null) {
				log_message('error', 'Invalid status: ' . $status);
				return [
					'status' => 400,
					'error'  => 'Invalid status value provided.',
					'data'   => [],
				];
			}

			// Prepare the body for the request
			$body = [
				'documentStatus' => $autoCountStatus,  // Use mapped status
				'lostReason'     => arr_get($data, 'reason'),
			];

			// Make the API request
			return autocount_request(
				'PUT',
				'quotation.update_status',
				$body,
				['docNo' => $docNo]
			);

		} catch (Exception $e) {
			log_message('error', 'Autocount update_status error: ' . $e->getMessage());

			return [
				'status' => 500,
				'error'  => $e->getMessage(),
				'data'   => [],
			];
		}
	}

	public function autocount_delete($data)
	{
		try {
			$docNo = arr_get($data, 'BookingNumber');

			return autocount_request(
				'DELETE',
				'quotation.delete',
				[],
				['docNo' => $docNo]
			);

		} catch (Exception $e) {
			log_message('error', 'Autocount delete error: ' . $e->getMessage());

			return [
				'status' => 500,
				'error'  => $e->getMessage(),
				'data'   => [],
			];
		}
	}

	public function autocount_void($data)
	{
		try {
			$docNo = arr_get($data, 'BookingNumber');

			$body = [
				'voidReason' => arr_get($data, 'reason'),
			];

			return autocount_request(
				'POST',
				'quotation.void',
				$body,
				['docNo' => $docNo]
			);

		} catch (Exception $e) {
			log_message('error', 'Autocount void error: ' . $e->getMessage());

			return [
				'status' => 500,
				'error'  => $e->getMessage(),
				'data'   => [],
			];
		}
	}
}
