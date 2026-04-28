<?php
class Invoice_Split_Model extends CI_Model
{
    /**
     * Get all active pax with their product allocations for a booking
     */
    function Get_Pax_By_Booking($booking_id)
    {
        $this->db->select('isp.*, ispp.InvoiceSplitPaxProductID, ispp.BookingProductID, ispp.Quantity, ispp.UnitPrice, ispp.Amount, ispp.DiscountAmount as ProductDiscountAmount, bp.Name as ProductName, bp.Quantity as BookingProductQuantity');
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
                    'SubmitStatus' => isset($row['SubmitStatus']) ? $row['SubmitStatus'] : 'D',
                    'SubmittedDate' => isset($row['SubmittedDate']) ? $row['SubmittedDate'] : null,
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
                    'Amount' => $row['Amount'],
                    'DiscountAmount' => $row['ProductDiscountAmount']
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
     * Get the submit status for a booking's invoice split request.
     * Returns 'S' if any active pax row is submitted, 'D' if only drafts exist,
     * or null if no active rows. Save_Split writes all pax with the same status
     * in a single transaction, so checking for any 'S' row is sufficient.
     */
    function Get_Submit_Status($booking_id)
    {
        $this->db->select("MAX(CASE WHEN SubmitStatus = 'S' THEN 1 ELSE 0 END) AS has_submitted, COUNT(*) AS total");
        $this->db->where('BookingID', $booking_id);
        $this->db->where('Status', 'Y');
        $row = $this->db->get('invoice_split_pax')->row_array();
        if (empty($row) || intval($row['total']) === 0) {
            return null;
        }
        return intval($row['has_submitted']) === 1 ? 'S' : 'D';
    }

    /**
     * Save invoice split data (replace-all pattern)
     *
     * @param int $booking_id
     * @param array $pax_data Array of pax, each with: PaxName, TIN, products[]
     * @param float $booking_subtotal The booking subtotal (before discount)
     * @param float $booking_discount The total booking discount
     * @param string $submit_status 'D' = draft (default), 'S' = submitted
     */
    function Save_Split($booking_id, $pax_data, $booking_subtotal, $booking_discount, $submit_status = 'D')
    {
        $submit_status = ($submit_status === 'S') ? 'S' : 'D';
        $this->db->trans_start();

        // Soft-delete existing records
        $this->Delete_Split($booking_id);

        $now = date('Y-m-d H:i:s');

        // Determine the target BookingProductID to absorb the full booking discount:
        // the first allocated product (lowest BookingProductID) whose line amount (Qty × UnitPrice)
        // is greater than or equal to the booking discount. The line amount itself is not reduced;
        // only an accompanying DiscountAmount is recorded on that line.
        $target_bp_id = null;
        $booking_discount = round(floatval($booking_discount), 2);
        if ($booking_discount > 0) {
            $line_amounts = [];
            foreach ($pax_data as $pax) {
                foreach ($pax['products'] as $product) {
                    $bp_id = intval($product['BookingProductID']);
                    $amt = round($product['UnitPrice'] * $product['Quantity'], 2);
                    $line_amounts[$bp_id] = isset($line_amounts[$bp_id]) ? round($line_amounts[$bp_id] + $amt, 2) : $amt;
                }
            }
            $candidates = [];
            foreach ($line_amounts as $bp_id => $amt) {
                if ($amt >= $booking_discount) {
                    $candidates[] = $bp_id;
                }
            }
            if (!empty($candidates)) {
                sort($candidates);
                $target_bp_id = $candidates[0];
            }
        }
        $discount_applied = false;

        foreach ($pax_data as $sort_order => $pax) {
            // Calculate pax subtotal
            $pax_subtotal = 0;
            foreach ($pax['products'] as $product) {
                $pax_subtotal += round($product['UnitPrice'] * $product['Quantity'], 2);
            }

            // Insert pax record (totals updated below once product-level discount is known)
            $this->db->insert('invoice_split_pax', [
                'BookingID' => $booking_id,
                'PaxName' => $pax['PaxName'],
                'TIN' => $pax['TIN'],
                'Email' => $pax['Email'],
                'Address' => $pax['Address'],
                'PhoneNumber' => $pax['PhoneNumber'],
                'SubtotalAmount' => $pax_subtotal,
                'DiscountAmount' => 0,
                'NetAmount' => $pax_subtotal,
                'SortOrder' => $sort_order,
                'Status' => 'Y',
                'SubmitStatus' => $submit_status,
                'SubmittedDate' => ($submit_status === 'S') ? $now : null,
                'InsertDate' => $now,
                'UpdateDate' => $now
            ]);

            $pax_id = $this->db->insert_id();

            // Insert product records; apply full booking discount to the first qualifying target line.
            $pax_discount_total = 0;
            foreach ($pax['products'] as $product) {
                $amount = round($product['UnitPrice'] * $product['Quantity'], 2);
                $line_discount = 0;
                if (!$discount_applied && $target_bp_id !== null && intval($product['BookingProductID']) === $target_bp_id && $amount >= $booking_discount) {
                    $line_discount = $booking_discount;
                    $discount_applied = true;
                }
                $this->db->insert('invoice_split_pax_product', [
                    'InvoiceSplitPaxID' => $pax_id,
                    'BookingProductID' => $product['BookingProductID'],
                    'Quantity' => $product['Quantity'],
                    'UnitPrice' => $product['UnitPrice'],
                    'Amount' => $amount,
                    'DiscountAmount' => $line_discount,
                    'Status' => 'Y'
                ]);
                $pax_discount_total = round($pax_discount_total + $line_discount, 2);
            }

            if ($pax_discount_total > 0) {
                $this->db->where('InvoiceSplitPaxID', $pax_id);
                $this->db->update('invoice_split_pax', [
                    'DiscountAmount' => $pax_discount_total,
                    'NetAmount' => round($pax_subtotal - $pax_discount_total, 2),
                    'UpdateDate' => $now
                ]);
            }
        }

        if ($booking_discount > 0 && !$discount_applied) {
            log_message('warning', 'Invoice_Split_Model::Save_Split booking ' . $booking_id . ' has RM ' . $booking_discount . ' discount but no single product line amount is >= the discount; no product-level discount applied.');
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

    /**
     * Revert any submitted pax rows back to draft for this booking. Called
     * when a booking_product is added/edited/deleted so the customer can
     * review the revised figures and resubmit; pax data is preserved.
     * Returns the number of rows reverted (0 if none were submitted).
     */
    function Unlock_Submitted($booking_id)
    {
        $this->db->where('BookingID', $booking_id);
        $this->db->where('Status', 'Y');
        $this->db->where('SubmitStatus', 'S');
        $this->db->update('invoice_split_pax', [
            'SubmitStatus' => 'D',
            'SubmittedDate' => null,
            'UpdateDate' => date('Y-m-d H:i:s'),
        ]);
        return $this->db->affected_rows();
    }
}
