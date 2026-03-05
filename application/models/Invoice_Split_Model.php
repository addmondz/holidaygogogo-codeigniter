<?php
class Invoice_Split_Model extends CI_Model
{
    /**
     * Get all active pax with their product allocations for a booking
     */
    function Get_Pax_By_Booking($booking_id)
    {
        $this->db->select('isp.*, ispp.InvoiceSplitPaxProductID, ispp.BookingProductID, ispp.Quantity, ispp.UnitPrice, ispp.Amount, bp.Name as ProductName, bp.Quantity as BookingProductQuantity');
        $this->db->from('invoice_split_pax isp');
        $this->db->join('invoice_split_pax_product ispp', 'ispp.InvoiceSplitPaxID = isp.InvoiceSplitPaxID AND ispp.Status = "Y"', 'left');
        $this->db->join('booking_product bp', 'bp.BookingProductID = ispp.BookingProductID', 'left');
        $this->db->where('isp.BookingID', $booking_id);
        $this->db->where('isp.Status', 'Y');
        $this->db->order_by('isp.SortOrder', 'ASC');
        $this->db->order_by('isp.InvoiceSplitPaxID', 'ASC');
        $results = $this->db->get()->result_array();

        // Group by pax
        $pax_list = [];
        foreach ($results as $row) {
            $pax_id = $row['InvoiceSplitPaxID'];
            if (!isset($pax_list[$pax_id])) {
                $pax_list[$pax_id] = [
                    'InvoiceSplitPaxID' => $row['InvoiceSplitPaxID'],
                    'BookingID' => $row['BookingID'],
                    'PaxName' => $row['PaxName'],
                    'TIN' => $row['TIN'],
                    'Email' => $row['Email'],
                    'Address' => $row['Address'],
                    'PhoneNumber' => $row['PhoneNumber'],
                    'SubtotalAmount' => $row['SubtotalAmount'],
                    'DiscountAmount' => $row['DiscountAmount'],
                    'NetAmount' => $row['NetAmount'],
                    'SortOrder' => $row['SortOrder'],
                    'products' => []
                ];
            }
            if (!empty($row['InvoiceSplitPaxProductID'])) {
                $pax_list[$pax_id]['products'][] = [
                    'InvoiceSplitPaxProductID' => $row['InvoiceSplitPaxProductID'],
                    'BookingProductID' => $row['BookingProductID'],
                    'ProductName' => $row['ProductName'],
                    'Quantity' => $row['Quantity'],
                    'UnitPrice' => $row['UnitPrice'],
                    'Amount' => $row['Amount']
                ];
            }
        }

        return array_values($pax_list);
    }

    /**
     * Check if a booking has invoice split data
     */
    function Has_Split($booking_id)
    {
        $this->db->where('BookingID', $booking_id);
        $this->db->where('Status', 'Y');
        return $this->db->count_all_results('invoice_split_pax') > 0;
    }

    /**
     * Save invoice split data (replace-all pattern)
     *
     * @param int $booking_id
     * @param array $pax_data Array of pax, each with: PaxName, TIN, products[]
     * @param float $booking_subtotal The booking subtotal (before discount)
     * @param float $booking_discount The total booking discount
     */
    function Save_Split($booking_id, $pax_data, $booking_subtotal, $booking_discount)
    {
        $this->db->trans_start();

        // Soft-delete existing records
        $this->Delete_Split($booking_id);

        $now = date('Y-m-d H:i:s');

        foreach ($pax_data as $sort_order => $pax) {
            // Calculate pax subtotal
            $pax_subtotal = 0;
            foreach ($pax['products'] as $product) {
                $pax_subtotal += round($product['UnitPrice'] * $product['Quantity'], 2);
            }

            // Calculate pro-rata discount
            $pax_discount = 0;
            if ($booking_subtotal > 0 && $booking_discount > 0) {
                $pax_discount = round(($pax_subtotal / $booking_subtotal) * $booking_discount, 2);
            }

            $pax_net = round($pax_subtotal - $pax_discount, 2);

            // Insert pax record
            $this->db->insert('invoice_split_pax', [
                'BookingID' => $booking_id,
                'PaxName' => $pax['PaxName'],
                'TIN' => $pax['TIN'],
                'Email' => $pax['Email'],
                'Address' => $pax['Address'],
                'PhoneNumber' => $pax['PhoneNumber'],
                'SubtotalAmount' => $pax_subtotal,
                'DiscountAmount' => $pax_discount,
                'NetAmount' => $pax_net,
                'SortOrder' => $sort_order,
                'Status' => 'Y',
                'InsertDate' => $now,
                'UpdateDate' => $now
            ]);

            $pax_id = $this->db->insert_id();

            // Insert product records
            foreach ($pax['products'] as $product) {
                $amount = round($product['UnitPrice'] * $product['Quantity'], 2);
                $this->db->insert('invoice_split_pax_product', [
                    'InvoiceSplitPaxID' => $pax_id,
                    'BookingProductID' => $product['BookingProductID'],
                    'Quantity' => $product['Quantity'],
                    'UnitPrice' => $product['UnitPrice'],
                    'Amount' => $amount,
                    'Status' => 'Y'
                ]);
            }
        }

        $this->db->trans_complete();

        return $this->db->trans_status();
    }

    /**
     * Soft-delete all split data for a booking
     */
    function Delete_Split($booking_id)
    {
        // Get all pax IDs for this booking
        $this->db->select('InvoiceSplitPaxID');
        $this->db->where('BookingID', $booking_id);
        $this->db->where('Status', 'Y');
        $pax_rows = $this->db->get('invoice_split_pax')->result_array();

        if (!empty($pax_rows)) {
            $pax_ids = array_column($pax_rows, 'InvoiceSplitPaxID');

            // Soft-delete products
            $this->db->where_in('InvoiceSplitPaxID', $pax_ids);
            $this->db->where('Status', 'Y');
            $this->db->update('invoice_split_pax_product', ['Status' => 'N']);

            // Soft-delete pax
            $this->db->where('BookingID', $booking_id);
            $this->db->where('Status', 'Y');
            $this->db->update('invoice_split_pax', [
                'Status' => 'N',
                'UpdateDate' => date('Y-m-d H:i:s')
            ]);
        }

        return true;
    }
}
