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
                    'LastEditedByAdmin' => isset($row['LastEditedByAdmin']) ? $row['LastEditedByAdmin'] : null,
                    'LastEditedDate' => isset($row['LastEditedDate']) ? $row['LastEditedDate'] : null,
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
     * Fetch the earliest SubmittedDate among active submitted pax rows for this
     * booking. Used by the admin edit flow so the original customer submission
     * moment is preserved across the replace-all save. Returns null if none.
     */
    function Get_Original_Submitted_Date($booking_id)
    {
        $this->db->select('SubmittedDate');
        $this->db->where('BookingID', $booking_id);
        $this->db->where('Status', 'Y');
        $this->db->where('SubmitStatus', 'S');
        $this->db->where('SubmittedDate IS NOT NULL');
        $this->db->order_by('SubmittedDate', 'ASC');
        $this->db->limit(1);
        $row = $this->db->get('invoice_split_pax')->row_array();
        return !empty($row['SubmittedDate']) ? $row['SubmittedDate'] : null;
    }

    /**
     * Validate raw e-invoice pax input against the booking. Shared between
     * Customer_Portal (customer submit/draft) and Booking (admin edit) so both
     * flows enforce identical rules.
     *
     * @param array $raw_data        Parsed JSON body, expected shape: ['pax' => [...]].
     * @param int   $booking_id      Target booking ID.
     * @param bool  $require_full    True for submit/admin-edit (every pax must have
     *                               products, total qty per product must equal booking
     *                               qty). False for draft (partial allocation allowed).
     * @return array ['ok' => true, 'pax_data' => [...]] on success;
     *               ['ok' => false, 'message' => '...'] on failure.
     */
    function Validate_Pax_Input($raw_data, $booking_id, $require_full_allocation = true)
    {
        if (empty($raw_data) || !is_array($raw_data)) {
            return ['ok' => false, 'message' => 'No pax data provided'];
        }
        if (empty($raw_data['pax'])) {
            if ($require_full_allocation) {
                return ['ok' => false, 'message' => 'No pax data provided'];
            }
            $raw_data['pax'] = [];
        }

        $this->load->model('Booking_Model');
        $pax_counts = $this->Booking_Model->Compute_Pax_Counts($booking_id);
        $max_pax = (int)$pax_counts['adult'] + (int)$pax_counts['child'] + (int)$pax_counts['infant'];
        if (count($raw_data['pax']) > $max_pax) {
            return [
                'ok' => false,
                'message' => 'Pax count (' . count($raw_data['pax']) . ') exceeds booking pax (' . $max_pax . ')'
            ];
        }

        // Booking products (source of truth for valid IDs, prices, max qty).
        $this->db->select('BookingProductID, Name as ProductName, Quantity, Price');
        $this->db->where('BookingID', $booking_id);
        $this->db->where('Status', 'Y');
        $booking_products = $this->db->get('booking_product')->result_array();

        $product_lookup = [];
        $qty_allocated = [];
        foreach ($booking_products as $bp) {
            $product_lookup[$bp['BookingProductID']] = $bp;
            $qty_allocated[$bp['BookingProductID']] = 0;
        }

        $pax_data = [];
        foreach ($raw_data['pax'] as $index => $pax) {
            $pax_name = isset($pax['PaxName']) ? trim($pax['PaxName']) : '';
            if (empty($pax_name)) {
                return ['ok' => false, 'message' => 'Pax #' . ($index + 1) . ' must have a name'];
            }

            if (empty($pax['products']) || !is_array($pax['products'])) {
                if ($require_full_allocation) {
                    return [
                        'ok' => false,
                        'message' => 'Pax "' . htmlspecialchars($pax_name) . '" must have at least one product'
                    ];
                }
                $pax['products'] = [];
            }

            $validated_products = [];
            foreach ($pax['products'] as $product) {
                $bp_id = isset($product['BookingProductID']) ? intval($product['BookingProductID']) : 0;
                $qty = isset($product['Quantity']) ? floatval($product['Quantity']) : 0;

                if (!isset($product_lookup[$bp_id])) {
                    return [
                        'ok' => false,
                        'message' => 'Invalid product selected for pax "' . htmlspecialchars($pax_name) . '"'
                    ];
                }
                if ($qty <= 0) {
                    return [
                        'ok' => false,
                        'message' => 'Quantity must be greater than 0 for pax "' . htmlspecialchars($pax_name) . '"'
                    ];
                }
                if (floor($qty) != $qty) {
                    return [
                        'ok' => false,
                        'message' => 'Quantity must be a whole number for pax "' . htmlspecialchars($pax_name) . '"'
                    ];
                }
                $qty = intval($qty);

                $max_qty = floatval($product_lookup[$bp_id]['Quantity']);
                if ($qty > $max_qty + 0.01) {
                    return [
                        'ok' => false,
                        'message' => 'Quantity ' . $qty . ' exceeds booking quantity ' . $max_qty . ' for product "' . htmlspecialchars($product_lookup[$bp_id]['ProductName']) . '" in pax "' . htmlspecialchars($pax_name) . '"'
                    ];
                }

                $qty_allocated[$bp_id] += $qty;
                $validated_products[] = [
                    'BookingProductID' => $bp_id,
                    'Quantity' => $qty,
                    'UnitPrice' => floatval($product_lookup[$bp_id]['Price'])
                ];
            }

            $tin = isset($pax['TIN']) ? trim($pax['TIN']) : '';
            if (empty($tin)) {
                return ['ok' => false, 'message' => 'TIN (Tax Identification Number) is required for pax "' . htmlspecialchars($pax_name) . '"'];
            }
            $email = isset($pax['Email']) ? trim($pax['Email']) : '';
            if (empty($email)) {
                return ['ok' => false, 'message' => 'Email is required for pax "' . htmlspecialchars($pax_name) . '"'];
            }
            $address = isset($pax['Address']) ? trim($pax['Address']) : '';
            if (empty($address)) {
                return ['ok' => false, 'message' => 'Address is required for pax "' . htmlspecialchars($pax_name) . '"'];
            }
            $phone_number = isset($pax['PhoneNumber']) ? trim($pax['PhoneNumber']) : '';
            if (empty($phone_number)) {
                return ['ok' => false, 'message' => 'Phone Number is required for pax "' . htmlspecialchars($pax_name) . '"'];
            }

            $pax_data[] = [
                'PaxName' => $pax_name,
                'TIN' => $tin,
                'Email' => $email,
                'Address' => $address,
                'PhoneNumber' => $phone_number,
                'products' => $validated_products
            ];
        }

        // Upper-bound check: total allocated for any product cannot exceed
        // booking quantity. Enforced for both draft and full-allocation paths.
        foreach ($booking_products as $bp) {
            $bp_id = $bp['BookingProductID'];
            $expected = floatval($bp['Quantity']);
            $actual = $qty_allocated[$bp_id];
            if ($actual > $expected + 0.01) {
                return [
                    'ok' => false,
                    'message' => 'Product "' . htmlspecialchars($bp['ProductName']) . '" total allocated quantity (' . $actual . ') exceeds booking quantity (' . $expected . ') across all pax'
                ];
            }
        }

        // Strict equality only enforced when full allocation is required.
        if ($require_full_allocation) {
            foreach ($booking_products as $bp) {
                $bp_id = $bp['BookingProductID'];
                $expected = floatval($bp['Quantity']);
                $actual = $qty_allocated[$bp_id];
                if (abs($expected - $actual) > 0.01) {
                    return [
                        'ok' => false,
                        'message' => 'Product "' . htmlspecialchars($bp['ProductName']) . '" requires total quantity of ' . $expected . ' but ' . $actual . ' was allocated'
                    ];
                }
            }
        }

        return ['ok' => true, 'pax_data' => $pax_data];
    }

    /**
     * Save invoice split data (replace-all pattern)
     *
     * @param int   $booking_id
     * @param array $pax_data                Array of pax, each with: PaxName, TIN, products[]
     * @param float $booking_subtotal        The booking subtotal (before discount)
     * @param float $booking_discount        The total booking discount
     * @param string $submit_status          'D' = draft (default), 'S' = submitted
     * @param bool  $preserve_submitted_date When true and submit_status is 'S', reuse the
     *                                       previously-recorded SubmittedDate (admin edit
     *                                       path) rather than stamping a new one.
     * @param int|null $admin_editor_id      When set, writes LastEditedByAdmin/LastEditedDate
     *                                       on every pax row so the admin booking detail page
     *                                       can render an audit strip.
     */
    function Save_Split(
        $booking_id,
        $pax_data,
        $booking_subtotal,
        $booking_discount,
        $submit_status = 'D',
        $preserve_submitted_date = false,
        $admin_editor_id = null
    ) {
        $submit_status = ($submit_status === 'S') ? 'S' : 'D';

        // Capture the original submitted_at BEFORE Delete_Split runs (which
        // soft-deletes the rows we'd otherwise read it from). Only meaningful
        // for the admin edit path.
        $original_submitted_date = null;
        if ($preserve_submitted_date && $submit_status === 'S') {
            $original_submitted_date = $this->Get_Original_Submitted_Date($booking_id);
        }

        $this->db->trans_start();

        // Soft-delete existing records
        $this->Delete_Split($booking_id);

        $now = date('Y-m-d H:i:s');

        // SubmittedDate to write for this save: either the original (admin edit
        // preserves the customer's moment) or now (fresh customer submit).
        $submitted_date = null;
        if ($submit_status === 'S') {
            $submitted_date = $original_submitted_date ?: $now;
        }

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
                'SubmittedDate' => $submitted_date,
                'LastEditedByAdmin' => $admin_editor_id !== null ? (int)$admin_editor_id : null,
                'LastEditedDate' => $admin_editor_id !== null ? $now : null,
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

    /**
     * Send an e-invoice notification to every active finance admin via the
     * Resend HTTP API. Used by both the customer-portal submit flow and the
     * admin edit flow.
     *
     * Best-effort: any failure (missing API key, network error, non-2xx
     * response) is logged but never surfaced to the caller, so a mail-provider
     * outage cannot break the submission flow.
     *
     * @param int    $booking_id
     * @param bool   $is_admin_edit  When true, the subject is "Updated by Admin"
     *                               and the email body indicates the change.
     * @param string $edited_by_name When is_admin_edit is true, the admin's name
     *                               surfaced in the email body.
     */
    function Send_Finance_Notification($booking_id, $is_admin_edit = false, $edited_by_name = '')
    {
        try {
            $flag = get_env('EINVOICE_NOTIFY_FINANCE');
            if ($flag !== null && strtolower(trim($flag)) === 'false') {
                log_message('info', 'E-invoice: finance notifications disabled by EINVOICE_NOTIFY_FINANCE=false (booking ' . $booking_id . ')');
                return;
            }

            $this->load->model('Admin_Model');

            $finance = $this->Admin_Model->get_finance_admins();
            if (empty($finance)) {
                log_message('info', 'E-invoice: no active finance admins to notify (booking ' . $booking_id . ')');
                return;
            }

            $api_key = get_env('RESEND_API_KEY');
            if (empty($api_key)) {
                log_message('error', 'E-invoice: RESEND_API_KEY not configured (booking ' . $booking_id . ')');
                return;
            }

            $this->db->select('BookingID, BookingNumber, Customer, NetTotal');
            $this->db->where('BookingID', $booking_id);
            $booking = $this->db->get('booking')->row_array();
            if (empty($booking)) {
                log_message('error', 'E-invoice: booking ' . $booking_id . ' not found when sending finance emails');
                return;
            }

            $pax_rows = $this->Get_Pax_By_Booking($booking_id);
            $pax_count = is_array($pax_rows) ? count($pax_rows) : 0;

            $booking_url = base_url('Booking/Update?booking_id=' . $booking_id) . '#invoice-split-section';
            $submitted_at = date('Y-m-d H:i:s');
            $subject = $is_admin_edit
                ? 'E-Invoice Request Updated by Admin — Booking ' . $booking['BookingNumber']
                : 'E-Invoice Request Submitted — Booking ' . $booking['BookingNumber'];

            $from_addr = get_env('MAIL_FROM_ADDRESS') ?: 'no-reply@holidaygogogo.com';
            $from_name = get_env('MAIL_FROM_NAME') ?: 'HolidayGoGoGo';
            $from_field = $from_name ? sprintf('%s <%s>', $from_name, $from_addr) : $from_addr;

            $messages = [];
            foreach ($finance as $admin) {
                $body = $this->load->view('emails/einvoice_submitted', [
                    'admin_name'     => $admin['Name'],
                    'booking_id'     => $booking_id,
                    'booking_number' => $booking['BookingNumber'],
                    'customer_name'  => $booking['Customer'],
                    'pax_count'      => $pax_count,
                    'net_total'      => number_format((float)$booking['NetTotal'], 2),
                    'submitted_at'   => $submitted_at,
                    'booking_url'    => $booking_url,
                    'is_admin_edit'  => $is_admin_edit,
                    'edited_by_name' => $edited_by_name,
                ], true);

                $messages[] = [
                    'from'    => $from_field,
                    'to'      => [$admin['Email']],
                    'subject' => $subject,
                    'html'    => $body,
                ];
            }

            list($ok, $err) = $this->_resend_send_batch($api_key, $messages);
            if (!$ok) {
                log_message('error', 'E-invoice email send failed for booking ' . $booking_id . ': ' . $err);
            }
        } catch (\Exception $e) {
            log_message('error', 'E-invoice email send failed for booking ' . $booking_id . ': ' . $e->getMessage());
        }
    }

    /**
     * POST a batch of emails to https://api.resend.com/emails/batch.
     *
     * Rate-limit handling: on HTTP 429 we honor the Retry-After header (capped
     * at 3s) plus 0-500ms jitter to desynchronize concurrent customers
     * submitting at the same instant, then retry once.
     *
     * Returns [bool $ok, string|null $error_message].
     */
    private function _resend_send_batch($api_key, array $messages, $attempt = 1)
    {
        if (empty($messages)) return [true, null];

        $max_attempts = 2;

        $ch = curl_init('https://api.resend.com/emails/batch');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($messages));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $err = 'cURL error: ' . curl_error($ch);
            curl_close($ch);
            return [false, $err];
        }
        $http_code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers_raw = substr($response, 0, $header_size);
        $body        = substr($response, $header_size);
        curl_close($ch);

        if ($http_code >= 200 && $http_code < 300) {
            return [true, null];
        }

        if ($http_code === 429 && $attempt < $max_attempts) {
            $retry_after = $this->_parse_retry_after_seconds($headers_raw, 1);
            $retry_after = min($retry_after, 3);
            $sleep_us = ($retry_after * 1000000) + mt_rand(0, 500000);
            usleep($sleep_us);
            return $this->_resend_send_batch($api_key, $messages, $attempt + 1);
        }

        $decoded = json_decode($body, true);
        $msg = is_array($decoded) && !empty($decoded['message'])
            ? $decoded['message']
            : substr((string)$body, 0, 500);
        return [false, "HTTP {$http_code}: {$msg}"];
    }

    private function _parse_retry_after_seconds($headers_raw, $default)
    {
        if (preg_match('/^Retry-After:\s*(\d+)/im', $headers_raw, $m)) {
            return max(0, (int)$m[1]);
        }
        return $default;
    }
}
