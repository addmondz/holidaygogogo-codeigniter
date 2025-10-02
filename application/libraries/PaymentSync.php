<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class PaymentSync {

    protected $CI;

    public function __construct()
    {
        // get CI super object so we can use $this->CI->db, $this->CI->load etc.
        $this->CI =& get_instance();
    }

    public function autocount_create($data = [], $config = [])
	{
		try {
			// Master (single row only)
			$param = [
				'master' => [
					'docNo'           => arr_get($data, 'ReferenceNumber', ''),
					'docNo2'          => arr_get($data, 'docNo2', ''),
					'docNoFormatName' => arr_get($data, 'docNoFormatName', null),
					'docType'         => 'PV', // required
					'docDate'         => date('Y-m-d', strtotime(arr_get($data, 'InsertDate'))),  // Date -> docDate
					'taxDate'         => null,  // Date -> taxDate
					'currencyCode'    => arr_get($data, 'currency_code', 'MYR'),
					'currencyRate'    => arr_get($data, 'currency_rate', 1),
					'journalType'     => 'GENERAL',
					'dealWith'        => arr_get($data, 'supplier_name', ''),
					'description'     => arr_get($data, 'PaymentRemark', ''),
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
						$acc_no = $config['payment_acc_no_1'];
						$amount = $data['Credit'];
					} else if ($data['Debit'] != 0.00){
						$acc_no = $config['payment_acc_no_2'];
						$amount = $data['Debit'];
					}

					$param['details'][] = [
						'accNo'              => $acc_no,
						'toAccountRate'      => arr_get($detail, 'toAccountRate', 1),
						'description'        => arr_get($detail, 'description', ''),
						'furtherDescription' => arr_get($detail, 'furtherDescription', ''),
						'amount'             => (float)$amount,
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
					$acc_no = $config['payment_acc_no_1'];
					$amount = $data['Credit'];
				} else if ($data['Debit'] != 0.00) {
					$acc_no = $config['payment_acc_no_2'];
					$amount = $data['Debit'];
				}

				$param['details'][] = [
					'accNo'  => $acc_no,
					'amount' => (float)$amount,
										'toAccountRate'      => arr_get($data, 'toAccountRate', 1),       // Default to 1

				];

			}

			// Payment details (loop through $data['paymentDetails'])
			if (!empty($data['paymentDetails']) && is_array($data['paymentDetails'])) {
				foreach ($data['paymentDetails'] as $payment) {
					$param['paymentDetails'][] = [
						'paymentMethod'      => arr_get($payment, 'paymentMethod','CASH'),
						'paymentBy'          => arr_get($payment, 'paymentBy', ''),
						'chequeNo'           => arr_get($payment, 'chequeNo', ''),
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
					$acc_no = $config['payment_acc_no_1'];
					$amount = $data['Credit'];
				} else if ($data['Debit'] != 0.00) {
					$acc_no = $config['payment_acc_no_2'];
					$amount = $data['Debit'];
				}

				$param['paymentDetails'][] = [
					'paymentMethod' => 'CASH',
					'paymentAmt'    => (float)$amount,
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
			// Ensure the docNo is passed (either BookingNumber or DocNo)
			$docNo = $data['ReferenceNumber'] ?? $data['ReferenceNumber'] ?? '';
			if ($docNo === '') {
				return ['error' => 'Missing required parameter: ReferenceNumber (or DocNo).'];
			}

			$body = [];

			// Master data (single row only)
			$body['master'] = [
				'docNo'           => $docNo,                                // Reference Number -> docNo
				'docNo2'          => arr_get($data, 'docNo2', ''),
				'docNoFormatName' => arr_get($data, 'docNoFormatName', null),
				'docType'         => arr_get($data, 'docType', 'PV'),        // Document Type (Payment Voucher)
				'docDate'         => date('Y-m-d', strtotime(arr_get($data, 'InsertDate'))),  // Date -> docDate
				'taxDate'         => null,//date('Y-m-d', strtotime(arr_get($data, 'tax_date'))),  // Date -> taxDate
				'currencyCode'    => arr_get($data, 'Currency', 'MYR'),      // Currency -> currencyCode
				'currencyRate'    => (float)arr_get($data, 'ForeignCurrency', 1),   // Foreign Currency -> currencyRate
				'journalType'     => 'GENERAL',                               // Journal Type
				'dealWith'        => arr_get($data, 'SupplierID', ''),       // Supplier -> dealWith
				'description'     => arr_get($data, 'PaymentRemark', ''),    // Payment Remark -> description
				'note'            => arr_get($data, 'Remark', ''),           // Remark -> note
			];
			

			// Details (loop through $data['details'])
			if (!empty($data['details']) && is_array($data['details'])) {
				foreach ($data['details'] as $detail) {
					$acc_no = '';
					$amount = 0.00;
					if ($data['Credit'] != 0.00){
						$acc_no = $config['payment_acc_no_1'];
						$amount = $data['Credit'];
					} else if ($data['Debit'] != 0.00){
						$acc_no = $config['payment_acc_no_2'];
						$amount = $data['Debit'];
					}
					$body['details'][] = [
						'accNo'              => $acc_no,        // account_no -> accNo
						'toAccountRate'      => arr_get($detail, 'toAccountRate', 1),       // Default to 1
						'description'        => arr_get($detail, 'description', ''),
						'furtherDescription' => arr_get($detail, 'furtherDescription', ''),
						'amount'             => (float)$amount,        // Debit -> amount
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
					$acc_no = $config['payment_acc_no_1'];
					$amount = $data['Credit'];
				} else if ($data['Debit'] != 0.00) {
					$acc_no = $config['payment_acc_no_2'];
					$amount = $data['Debit'];
				}

				$body['details'][] = [
					'accNo'  => $acc_no,
					'amount' => (float)$amount,
					'toAccountRate'      => arr_get($data, 'toAccountRate', 1),       // Default to 1
				];
			}

			// Payment details (loop through $data['paymentDetails'])
			if (!empty($data['paymentDetails']) && is_array($data['paymentDetails'])) {
				foreach ($data['paymentDetails'] as $payment) {
					$body['paymentDetails'][] = [
						'paymentMethod'      => arr_get($payment, 'paymentMethod', 'CASH'),
						'paymentBy'          => arr_get($payment, 'paymentBy', ''),
						'chequeNo'           => arr_get($payment, 'chequeNo', ''),
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
					$acc_no = $config['payment_acc_no_1'];
					$amount = $data['Credit'];
				} else if ($data['Debit'] != 0.00) {
					$acc_no = $config['payment_acc_no_2'];
					$amount = $data['Debit'];
				}

				$body['paymentDetails'][] = [
					'paymentMethod' => 'CASH',
					'paymentAmt'    => (float)$amount,
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
				['docNo' => $docNo]
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
        $docNo = isset($data['ReferenceNumber']) ? $data['ReferenceNumber'] : '';

        return autocount_request(
            'DELETE',
            'payment.delete',
            [],
            ['docNo' => $docNo]
        );
    }

    public function autocount_void($data = [])
    {
        $docNo = isset($data['ReferenceNumber']) ? $data['ReferenceNumber'] : '';
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
