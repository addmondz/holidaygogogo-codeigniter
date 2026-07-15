<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class PaymentSync {

    protected $CI;

    public function __construct()
    {
        // get CI super object so we can use $this->CI->db, $this->CI->load etc.
        $this->CI =& get_instance();
    }

    /**
	 * Effective journal line amount (document currency). Foreign docs carry the
	 * amount in foreign_amount; local docs use Credit (OR) or Debit (PV).
	 */
	protected function line_amount($data)
	{
		if (arr_get($data, 'foreign_amount', null) !== null) {
			return (float)$data['foreign_amount'];
		}
		if ((float)arr_get($data, 'Credit', 0) != 0.00) {
			return (float)$data['Credit'];
		}
		return (float)arr_get($data, 'Debit', 0);
	}

	/**
	 * A payment with no amount (Credit == Debit == 0) has nothing to post.
	 * AutoCount rejects the empty line with "Detail line [1] missing value in
	 * Amount field.", so we skip the API call and let the caller mark it done.
	 */
	protected function skip_zero_amount()
	{
		return [
			'status'  => 200,
			'error'   => null,
			'skipped' => true,
			'message' => 'Zero-amount payment skipped (no journal posted)',
		];
	}

    public function autocount_create($data = [], $config = [])
	{
		try {
			if ($this->line_amount($data) == 0.00) {
				return $this->skip_zero_amount();
			}

			// Master (single row only)
			$param = [
				'master' => [
					'docNo'           => '',
					'docNo2'          => arr_get($data, 'BookingNumber', ''),
					'docNoFormatName' => arr_get($data, 'docNoFormatName', null),
					'docType'         => ($data['Credit'] != 0.00) ? 'OR' : 'PV', // required
					'docDate'         => date('Y-m-d', strtotime(arr_get($data, 'Date'))),  // Date -> docDate
					'taxDate'         => null,  // Date -> taxDate
					'currencyCode'    => arr_get($data, 'currency_code', 'MYR'),
					'currencyRate'    => arr_get($data, 'currency_rate', 1),
					// AutoCount requires the tax currency rate to equal the document
					// rate (tax is converted to home currency at the same rate).
					'toTaxCurrencyRate' => (float)arr_get($data, 'currency_rate', 1),
					'journalType'     => 'GENERAL',
					'dealWith'        => arr_get($data, 'dealWith', null),
					'description'     => arr_get($data, 'description', ''),
					'note'            => arr_get($data, 'note', ''),
				],
				'details'        => [],
				'paymentDetails' => [],
				'autoFillOption' => [
					'taxCode'    => arr_get($data, 'tax_code', false),
					'tariffCode' => arr_get($data, 'tariff_code', false),
				],
				'saveApprove' => arr_get($data, 'saveApprove', null),
			];

			// Details (loop through $data['details'])
			if (!empty($data['details']) && is_array($data['details'])) {
				foreach ($data['details'] as $detail) {
					$acc_no = '';
					$amount = 0.00;
					if ($data['Credit'] != 0.00){
						$acc_no = arr_get($data, 'CustomerCode', '');//$config['payment_acc_no_1'];
						$amount = $data['Credit'];
					} else if ($data['Debit'] != 0.00){
						$acc_no = arr_get($data, 'SupplierCode', '');//$config['payment_acc_no_2'];
						$amount = $data['Debit'];
					}

					if (!empty($data['bankaccNo'])) {
						$acc_no = $data['bankaccNo'];
					}

					$param['details'][] = [
						'accNo'              => $acc_no,
						'toAccountRate'      => arr_get($detail, 'toAccountRate', 1),
						'description'        => arr_get($detail, 'description', ''),
						'furtherDescription' => arr_get($detail, 'ReservationNumber', ''),
						// Foreign-currency doc: line amount is in document (foreign) currency.
						'amount'             => (float)(arr_get($data, 'foreign_amount', null) !== null ? $data['foreign_amount'] : $amount),
						'taxCode'            => arr_get($detail, 'taxCode', ''),
						'taxAdjustment'      => arr_get($detail, 'taxAdjustment', 0),
						'localTaxAdjustment' => arr_get($detail, 'localTaxAdjustment', 0),
						'tariffCode'         => arr_get($detail, 'tariffCode', ''),
						'taxExportCountry'   => arr_get($detail, 'taxExportCountry', ''),
						'taxPermitNo'        => arr_get($detail, 'taxPermitNo', ''),
						'taxBRNo'            => arr_get($detail, 'taxBRNo', ''),
						'taxBName'           => arr_get($detail, 'taxBName', ''),
						'taxRefNo'           => arr_get($detail, 'taxRefNo', ''),
						'taxRegisterNo'      => arr_get($detail, 'taxRegisterNo', ''),
						'taxBillDate'        => arr_get($detail, 'taxBillDate', null),
						'salesAgent'         => arr_get($detail, 'salesAgent', ''),
						'inclusiveTax'       => arr_get($detail, 'inclusiveTax', true),
						'deptNo'             => arr_get($detail, 'deptNo', ''),
					];
				}
			} else {
				// Fallback: minimal details
				$acc_no = '';
				$amount = 0.00;
				if ($data['Credit'] != 0.00) { // OR
					$acc_no = arr_get($data, 'CustomerCode', '');//$config['payment_acc_no_1'];
					$amount = $data['Credit'];
				} else if ($data['Debit'] != 0.00) { // PV
					$acc_no = arr_get($data, 'SupplierCode', '');//$config['payment_acc_no_2'];
					$amount = $data['Debit'];
				}

				if (!empty($data['bankaccNo'])) {
					$acc_no = $data['bankaccNo'];
				}

				$param['details'][] = [
					'accNo'  => $acc_no,
					// Foreign-currency doc: line amount is in document (foreign) currency.
					'amount' => (float)(arr_get($data, 'foreign_amount', null) !== null ? $data['foreign_amount'] : $amount),
					'toAccountRate'      => arr_get($data, 'toAccountRate', 1),
					'salesAgent' => arr_get($data, 'salesAgent', ''),
					'description'        => arr_get($data, 'description', ''),
					'furtherDescription' => arr_get($data, 'ReservationNumber', ''),
				];

			}

			// Payment details (loop through $data['paymentDetails'])
			if (!empty($data['paymentDetails']) && is_array($data['paymentDetails'])) {
				foreach ($data['paymentDetails'] as $payment) {
					$param['paymentDetails'][] = [
						'paymentMethod'      => arr_get($payment, 'paymentMethod','BANK'),
						'paymentBy'          => arr_get($payment, 'paymentBy', ''),
						'chequeNo'           => arr_get($payment, 'ReferenceNumber', ''),
						'floatDay'           => arr_get($payment, 'floatDay', 0),
						'bankCharge'         => (float)arr_get($payment, 'bankCharge', 0),
						'toBankRate'         => arr_get($payment, 'toBankRate', 1),
						'paymentAmt'         => (float)arr_get($payment, 'paymentAmt'),
						'bankChargeTaxCode'  => arr_get($payment, 'bankChargeTaxCode', ''),
						'bankChargeTaxRate'  => arr_get($payment, 'bankChargeTaxRate', 0),
						'bankChargeTax'      => arr_get($payment, 'bankChargeTax', 0),
						'bankChargeTaxRefNo' => arr_get($payment, 'bankChargeTaxRefNo', ''),
					];
				}
			} else {
				// Fallback: minimal paymentDetails
				if ($data['Credit'] != 0.00) {
					$acc_no = arr_get($data, 'CustomerCode', '');//$config['payment_acc_no_1'];
					$amount = $data['Credit'];
				} else if ($data['Debit'] != 0.00) {
					$acc_no = arr_get($data, 'SupplierCode', '');//$config['payment_acc_no_2'];
					$amount = $data['Debit'];
				}

				if (!empty($data['bankaccNo'])) {
					$acc_no = $data['bankaccNo'];
				}

				$param['paymentDetails'][] = [
					'paymentMethod' => 'BANK',
					'chequeNo'      => arr_get($data, 'ReferenceNumber', ''),
					// Payment is expressed in document currency; AutoCount requires
					// toBankRate to equal the document rate (foreign docs pay in the
					// foreign amount, converted to home at the doc rate).
					'paymentAmt'    => $this->line_amount($data),
					'toBankRate'    => (float)arr_get($data, 'currency_rate', 1),
				];
			}

			// Send request to AutoCount
			return autocount_request('POST', 'payment.create', $param);

		} catch (Exception $e) {
			log_message('error', 'Autocount payment creation error: ' . $e->getMessage());

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
			if ($this->line_amount($data) == 0.00) {
				return $this->skip_zero_amount();
			}

			$body = [];

			// Master data (single row only)
			$body['master'] = [
				'docNo'           => arr_get($data, 'AutocountReferenceNumber', ''),                                // Reference Number -> docNo
				'docNo2'          => arr_get($data, 'BookingNumber', ''),
				'docNoFormatName' => arr_get($data, 'docNoFormatName', null),
				'docType'         => ($data['Credit'] != 0.00) ? 'OR' : 'PV', // required
				'docDate'         => date('Y-m-d', strtotime(arr_get($data, 'Date'))),  // Date -> docDate
				'taxDate'         => null,//date('Y-m-d', strtotime(arr_get($data, 'tax_date'))),  // Date -> taxDate
				'currencyCode'    => arr_get($data, 'currency_code', 'MYR'),      // Currency -> currencyCode
				'currencyRate'    => (float)arr_get($data, 'currency_rate', 1),   // Foreign Currency -> currencyRate
				// AutoCount requires the tax currency rate to equal the document rate.
				'toTaxCurrencyRate' => (float)arr_get($data, 'currency_rate', 1),
				'journalType'     => 'GENERAL',                               // Journal Type
				'dealWith'        => arr_get($data, 'dealWith', null),      // Supplier -> dealWith
				'description'     => arr_get($data, 'description', ''),    // Payment Remark -> description
				'note'            => arr_get($data, 'Remark', ''),           // Remark -> note
			];
			

			// Details (loop through $data['details'])
			if (!empty($data['details']) && is_array($data['details'])) {
				foreach ($data['details'] as $detail) {
					$acc_no = '';
					$amount = 0.00;
					if ($data['Credit'] != 0.00){
						$acc_no = arr_get($data, 'CustomerCode', '');//$config['payment_acc_no_1'];
						$amount = $data['Credit'];
					} else if ($data['Debit'] != 0.00){
						$acc_no = arr_get($data, 'SupplierCode', '');//$config['payment_acc_no_2'];
						$amount = $data['Debit'];
					}

					if (!empty($data['bankaccNo'])) {
						$acc_no = $data['bankaccNo'];
					}
					$body['details'][] = [
						'accNo'              => $acc_no,        // account_no -> accNo
						'toAccountRate'      => arr_get($detail, 'toAccountRate', 1),
						'description'        => arr_get($detail, 'description', ''),
						'furtherDescription' => arr_get($detail, 'ReservationNumber', ''),
						// Foreign-currency doc: line amount is in document (foreign) currency.
						'amount'             => (float)(arr_get($data, 'foreign_amount', null) !== null ? $data['foreign_amount'] : $amount),        // Debit -> amount
						'taxCode'            => arr_get($detail, 'taxCode', ''),
						'taxAdjustment'      => arr_get($detail, 'taxAdjustment', 0),
						'localTaxAdjustment' => arr_get($detail, 'localTaxAdjustment', 0),
						'tariffCode'         => arr_get($detail, 'tariffCode', ''),
						'taxExportCountry'   => arr_get($detail, 'taxExportCountry', ''),
						'taxPermitNo'        => arr_get($detail, 'taxPermitNo', ''),
						'taxBRNo'            => arr_get($detail, 'taxBRNo', ''),
						'taxBName'           => arr_get($detail, 'taxBName', ''),
						'taxRefNo'           => arr_get($detail, 'taxRefNo', ''),
						'taxRegisterNo'      => arr_get($detail, 'taxRegisterNo', ''),
						'taxBillDate'        => arr_get($detail, 'taxBillDate', null),
						'salesAgent'         => arr_get($detail, 'salesAgent', ''),
						'inclusiveTax'       => arr_get($detail, 'inclusiveTax', true),
						'deptNo'             => arr_get($detail, 'deptNo', ''),
					];
				}
			} else {
				// Fallback: minimal details
				$acc_no = '';
				$amount = 0.00;
				if ($data['Credit'] != 0.00) {
					$acc_no = arr_get($data, 'CustomerCode', '');//$config['payment_acc_no_1'];
					$amount = $data['Credit'];
				} else if ($data['Debit'] != 0.00) {
					$acc_no = arr_get($data, 'SupplierCode', '');//$config['payment_acc_no_2'];
					$amount = $data['Debit'];
				}

				if (!empty($data['bankaccNo'])) {
					$acc_no = $data['bankaccNo'];
				}

				$body['details'][] = [
					'accNo'  => $acc_no,
					// Foreign-currency doc: line amount is in document (foreign) currency.
					'amount' => (float)(arr_get($data, 'foreign_amount', null) !== null ? $data['foreign_amount'] : $amount),
					'toAccountRate'      => arr_get($data, 'toAccountRate', 1),
					'salesAgent' => arr_get($data, 'salesAgent', ''),
					'description'        => arr_get($data, 'description', ''),
					'furtherDescription' => arr_get($data, 'ReservationNumber', ''),
				];
			}

			// Payment details (loop through $data['paymentDetails'])
			if (!empty($data['paymentDetails']) && is_array($data['paymentDetails'])) {
				foreach ($data['paymentDetails'] as $payment) {
					$body['paymentDetails'][] = [
						'paymentMethod'      => arr_get($payment, 'paymentMethod', 'BANK'),
						'paymentBy'          => arr_get($payment, 'paymentBy', ''),
						'chequeNo'           => arr_get($payment, 'ReferenceNumber', ''),
						'floatDay'           => arr_get($payment, 'floatDay', 0),
						'bankCharge'         => (float)arr_get($payment, 'bankCharge', 0),
						'toBankRate'         => arr_get($payment, 'toBankRate', 1),
						'paymentAmt'         => (float)arr_get($payment, 'paymentAmt', 0),  // Debit -> paymentAmt
						'bankChargeTaxCode'  => arr_get($payment, 'bankChargeTaxCode', ''),
						'bankChargeTaxRate'  => arr_get($payment, 'bankChargeTaxRate', 0),
						'bankChargeTax'      => arr_get($payment, 'bankChargeTax', 0),
						'bankChargeTaxRefNo' => arr_get($payment, 'bankChargeTaxRefNo', ''),
					];
				}
			} else {
				// Fallback: minimal paymentDetails
				$acc_no = '';
				$amount = 0.00;
				if ($data['Credit'] != 0.00) {
					$acc_no = arr_get($data, 'CustomerCode', '');//$config['payment_acc_no_1'];
					$amount = $data['Credit'];
				} else if ($data['Debit'] != 0.00) {
					$acc_no = arr_get($data, 'SupplierCode', '');//$config['payment_acc_no_2'];
					$amount = $data['Debit'];
				}

				if (!empty($data['bankaccNo'])) {
					$acc_no = $data['bankaccNo'];
				}
				
				$body['paymentDetails'][] = [
					'paymentMethod' => 'BANK',
					'chequeNo'      => arr_get($data, 'ReferenceNumber', ''),
					// Payment is expressed in document currency; AutoCount requires
					// toBankRate to equal the document rate (foreign docs pay in the
					// foreign amount, converted to home at the doc rate).
					'paymentAmt'    => $this->line_amount($data),
					'toBankRate'    => (float)arr_get($data, 'currency_rate', 1),
				];
			}

			// AutoFill Options (tax code, etc.)
			$body['autoFillOption'] = [
				'TaxCode' => arr_get($data, 'TaxCode', false),
			];
			

			// Send request to AutoCount for updating the payment
			return autocount_request(
				'PUT',
				'payment.update',
				$body,
				['docNo' => arr_get($data, 'AutocountReferenceNumber', '')]
			);
		} catch (Exception $e) {
			log_message('error', 'Autocount payment update error: ' . $e->getMessage());

			return [
				'status' => 500,
				'error'  => $e->getMessage(),
				'data'   => [],
			];
		}
	}

    public function autocount_delete($data = [], $config = [])
    {
        $docNo = isset($data['AutocountReferenceNumber']) ? $data['AutocountReferenceNumber'] : '';

        return autocount_request(
            'DELETE',
            'payment.delete',
            [],
            ['docNo' => $docNo]
        );
    }

    public function autocount_void($data = [])
    {
        $docNo = isset($data['AutocountReferenceNumber']) ? $data['AutocountReferenceNumber'] : '';
        $body = [
            'voidReason' => isset($data['reason']) ? $data['reason'] : ''
        ];

        return autocount_request(
            'POST',
            'payment.void',
            $body,
            ['docNo' => $docNo]
        );
        
    }   
}
