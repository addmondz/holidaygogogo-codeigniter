<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Quotation extends MY_Controller {

    public function create($data = []) {
        $param = [
            'master' => [
                'DocNo'         => $data['BookingNumber'],
                'DocNoFormatName' => null,
                'DocDate'       => $data['InsertDate'],
                'DebtorCode'    => '',
                'DebtorName'    => $data['Customer'],
                'Email'         => $data['customer_email'],
                'EmailCC'       => null,
                'EmailBCC'      => null,
                'Address'       => $data['customer_address'],
                'Attention'     => '',
                'Phone1'        => $data['customer_phone'],
                'Fax1'          => '',
                'DeliverAddress'=> $data['customer_address'],
                'DeliverContact'=> '',
                'DeliverPhone1' => '',
                'DeliverFax1'   => '',
                'Ref'           => null,
                'Description'   => null,
                'Note'          => null,
                'SalesAgent'    => '',
                'CreditTerm'    => $data['credit_term'],
                'SalesLocation' => $data['sales_location'],
                'Remark1'       => null,
                'Remark2'       => null,
                'Remark3'       => null,
                'Remark4'       => null,
                'CurrencyRate'  => $data['currency_rate'],
                'InclusiveTax'  => false,
                'IsRoundAdj'    => false,
                'YourRef'       => null,
                'Validity'      => null,
                'CC'            => null,
                'DeliveryTerm'  => null,
                'PaymentTerm'   => null
            ],
            'details' => [],
            'autoFillOption' => [
                'TaxCode' => true
            ],
            'saveApprove' => null
        ];

        if (!empty($data['booking_product'])) {
            foreach($data['booking_product'] as $product) {
                $param['details'][] = [
                    'ProductCode'        => $product['ProductCode'],
                    'ProductVariant'     => null,
                    'Description'        => $product['Description'],
                    'FurtherDescription' => '',
                    'Qty'                => $product['Quantity'],
                    'Unit'               => $product['unit'],
                    'UnitPrice'          => $product['Price'],
                    'Discount'           => null,
                    'TaxCode'            => $product['tax_code'],
                    'TaxAdjustment'      => 0,
                    'LocalTaxAdjustment' => 0,
                    'DeptNo'             => null
                ];
            }
        }

        return autocount_request('POST', 'quotation.create', $param);
    }

   public function update($data = []) {
        // docNo comes from BookingNumber (falls back to DocNo if provided)
        $docNo = $data['BookingNumber'] ?? '';
        // if ($docNo === '') {
        //     return ['error' => 'Missing required parameter: BookingNumber (or DocNo).'];
        // }

        // Choose body
        $scenario = $data['scenario'] ?? '';

        if (isset($data['body']) && is_array($data['body']) && !empty($data['body'])) {
            // Use provided body directly
            $body = $data['body'];
        } else {
            // Build from scenario presets (based on your original variants)
            switch ($scenario) {
                case 'desc_only':
                    $body = [
                        'master'  => [],
                        'details' => [
                            ['description' => 'Short Pants'],
                            ['description' => 'Short Pants'],
                        ],
                    ];
                    break;

                case 'long_pants_and_full_line':
                    $body = [
                        'master'  => [],
                        'details' => [
                            [
                                'productCode' => 'P-00002',
                                'description' => 'Long Pants',
                                'qty'         => 2,
                            ],
                            [
                                'productCode'        => 'P-00002',
                                'productVariant'     => null,
                                'accNo'              => '510-0000',
                                'description'        => 'Pants',
                                'furtherDescription' => '',
                                'qty'                => 1,
                                'unit'               => '20000',
                                'unitPrice'          => 450,
                                'discount'           => null,
                                'taxCode'            => 'S-5',
                                'tariffCode'         => '40159000',
                                'taxExportCountry'   => null,
                                'taxPermitNo'        => null,
                                'taxAdjustment'      => 0,
                                'localTaxAdjustment' => 0,
                                'unitCost'           => 300,
                                'goodsReturn'        => true,
                                'yourPONo'           => null,
                                'yourPODate'         => null,
                                'deptNo'             => null,
                            ],
                        ],
                        'autoFillOption' => [
                            'taxCode' => true,
                        ],
                        'saveApprove' => null,
                    ];
                    break;

                case 'coat_and_pants':
                    $body = [
                        'master'  => [],
                        'details' => [
                            [
                                'productCode' => 'P-00003',
                                'description' => 'Coat',
                                'qty'         => 1,
                                'unit'        => '3000',
                                'unitPrice'   => 300,
                            ],
                            [
                                'productCode' => 'P-00002',
                                'accNo'       => '500-0000',
                                'description' => 'Pants',
                                'qty'         => 1,
                                'unit'        => '20000',
                                'unitPrice'   => 450,
                                'tariffCode'  => '40159000',
                            ],
                        ],
                        'autoFillOption' => [
                            'taxCode' => true,
                        ],
                        'saveApprove' => null,
                    ];
                    break;

                default:
                    // No body and unknown scenario -> return error (don’t send empty payloads)
                    return [
                        'error' => 'No update body provided. Pass $data["body"] or a valid $data["scenario"]: desc_only, long_pants_and_full_line, coat_and_pants.'
                    ];
            }
        }

        return autocount_request(
            'PUT',
            'quotation.update',
            $body,
            ['docNo' => $docNo]
        );
    }

    public function update_status($data = []) {
        $docNo = $data['BookingNumber'] ?? '';
        $body = [
            'documentStatus' => $data['Status'] ?? ''
        ];

        return autocount_request(
            'PUT',
            'quotation.update_status',
            $body,
            ['docNo' => $docNo]
        );
    }

    public function delete($data = []) {
        $docNo = $data['BookingNumber'] ?? '';

        return autocount_request(
            'DELETE',
            'quotation.delete',
            [],
            ['docNo' => $docNo]
        );
    }

    public function void($data = []) {
        $docNo = $data['DocNo'] ?? '';
        $body = [
            'voidReason' => $data['reason'] ?? ''
        ];

        return autocount_request(
            'POST',
            'quotation.void',
            $body,
            ['docNo' => $docNo]
        );
    }
}
