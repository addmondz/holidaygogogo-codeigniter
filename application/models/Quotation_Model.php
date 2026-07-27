<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Quotation_Model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function create($data = [])
    {
        $param = [
            'master' => [
                'DocNo'           => $data['BookingNumber'],
                'DocNoFormatName' => null,
                'DocDate'         => $data['InsertDate'],
                'DebtorCode'      => '',
                'DebtorName'      => $data['Customer'],
                'Email'           => $data['guest_email'],
                'EmailCC'         => null,
                'EmailBCC'        => null,
                'Address'         => $data['guest_address'],
                'Attention'       => '',
                'Phone1'          => $data['guest_phone'],
                'Fax1'            => '',
                'DeliverAddress'  => $data['guest_address'],
                'DeliverContact'  => $data['Customer'],
                'DeliverPhone1'   => $data['guest_phone'],
                'DeliverFax1'     => '',
                'Ref'             => null,
                'Description'     => null,
                'Note'            => null,
                'SalesAgent'      => '',
                'CreditTerm'      => $data['credit_term'] ?? 'C.O.D.',
                'SalesLocation'   => $data['sales_location'] ?? 'HQ',
                'Remark1'         => $data['BokingRemark'],
                'Remark2'         => null,
                'Remark3'         => null,
                'Remark4'         => null,
                'CurrencyRate'    => $data['currency_rate'],
                'InclusiveTax'    => $data['inclusive_tax'] ?? false,
                'IsRoundAdj'      => $data['is_round_adj'] ?? false,
                'YourRef'         => null,
                'Validity'        => null,
                'CC'              => null,
                'DeliveryTerm'    => null,
                'PaymentTerm'     => null
            ],
            'details' => [],
            'autoFillOption' => [
                'TaxCode' => $data['tax_code'] ?? true
            ],
            'saveApprove' => null
        ];

        if (!empty($data['booking_product'])) {
            foreach ($data['booking_product'] as $product) {
                $param['details'][] = [
                    'ProductCode'        => $product['product_ProductCode'],
                    'ProductVariant'     => null,
                    'Description'        => $product['product_Description'],
                    'FurtherDescription' => '',
                    'Qty'                => $product['product_Quantity'],
                    // Null UOM => AutoCount uses the stock item's base UOM. The literal
                    // 'unit' is rejected as "unit or multi pack 'unit' not exists".
                    'Unit'               => isset($product['unit']) ? $product['unit'] : null,
                    'UnitPrice'          => $product['product_Price'],
                    'Discount'           => null,
                    'TaxCode'            => isset($product['tax_code']) ? $product['tax_code'] : 'S-5',
                    'TaxAdjustment'      => 0,
                    'LocalTaxAdjustment' => 0,
                    'DeptNo'             => null
                ];
            }
        }

        return autocount_request('POST', 'quotation.create', $param);
    }

    public function update($data = [])
    {
        $docNo = $data['BookingNumber'] ?? $data['DocNo'] ?? '';
        if ($docNo === '') {
            return ['error' => 'Missing required parameter: BookingNumber (or DocNo).'];
        }

        $body = [];

        if (!empty($data['master'])) {
            $body['master'] = $data['master'];
        }

        if (!empty($data['booking_product']) && is_array($data['booking_product'])) {
            $body['details'] = [];
            foreach ($data['booking_product'] as $product) {
                $body['details'][] = [
                    'ProductCode'        => $product['product_ProductCode'],
                    'ProductVariant'     => null,
                    'Description'        => $product['product_Description'],
                    'FurtherDescription' => '',
                    'Qty'                => $product['product_Quantity'],
                    // Null UOM => AutoCount uses the stock item's base UOM. The literal
                    // 'unit' is rejected as "unit or multi pack 'unit' not exists".
                    'Unit'               => isset($product['unit']) ? $product['unit'] : null,
                    'UnitPrice'          => $product['product_Price'],
                    'Discount'           => null,
                    'TaxCode'            => isset($product['tax_code']) ? $product['tax_code'] : 'S-5',
                    'TaxAdjustment'      => 0,
                    'LocalTaxAdjustment' => 0,
                    'DeptNo'             => null
                ];
            }
        }

        if (!empty($data['tax_code'])) {
            $body['autoFillOption'] = [
                'TaxCode' => $data['tax_code']
            ];
        }

        if (isset($data['saveApprove'])) {
            $body['saveApprove'] = $data['saveApprove'];
        }

        return autocount_request(
            'PUT',
            'quotation.update',
            $body,
            ['docNo' => $docNo]
        );
    }

    public function update_status($data = [])
    {
        $docNo = $data['BookingNumber'] ?? '';
        $body = [
            'documentStatus' => $data['Status'] ?? '',
            'lostReason'     => $data['reason'] ?? ''
        ];

        return autocount_request(
            'PUT',
            'quotation.update_status',
            $body,
            ['docNo' => $docNo]
        );
    }

    public function delete($data = [])
    {
        $docNo = $data['BookingNumber'] ?? '';

        return autocount_request(
            'DELETE',
            'quotation.delete',
            [],
            ['docNo' => $docNo]
        );
    }

    public function void($data = [])
    {
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

    public function activity_log($endpoint, $requestBody, $responseBody, $status, $user_id = null)
    {
        // Build Action JSON
        $actionData = [
            'endpoint' => $endpoint,
            'request'  => $requestBody,   // Can include headers & body
            'response' => $responseBody
        ];

        // Detect current URL (API endpoint URL)
        $url = is_string($endpoint) ? $endpoint : '';

        // Insert into DB
        $data = [
            'UserID'     => $user_id,
            'Action'     => json_encode($actionData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'IP'         => $_SERVER['REMOTE_ADDR'] ?? null,
            'Url'        => $url,
            'Status'     => strtoupper($status) === 'Y' ? 'Y' : 'N',
            'InsertBy'   => 'SYSTEM',
            'InsertDate' => date('Y-m-d H:i:s')
        ];

        return $this->db->insert('activity_log', $data);
    }
}
