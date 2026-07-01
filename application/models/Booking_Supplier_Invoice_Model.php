<?php
class Booking_Supplier_Invoice_Model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('supplier_invoice');
    }

    /**
     * Active invoices for a single booking, with derived PaidAmount + BalanceDue
     * sourced from approved supplier payments matched on (SupplierID, InvoiceNumber).
     */
    function Read_By_Booking($booking_id)
    {
        $paid = supplier_invoice_paid_subquery_sql();
        $this->db->select(
            "bsi.SupplierInvoiceID, bsi.BookingID, bsi.SupplierID, bsi.InvoiceNumber,
             bsi.InvoiceAmount, bsi.PaymentDeadline, bsi.Remark, bsi.InvoiceFilePath, bsi.Status,
             s.Name AS SupplierName,
             COALESCE(({$paid}), 0) AS PaidAmount,
             (bsi.InvoiceAmount - COALESCE(({$paid}), 0)) AS BalanceDue",
            false
        );
        $this->db->from('booking_supplier_invoice bsi');
        $this->db->join('supplier s', 's.SupplierID = bsi.SupplierID', 'left');
        $this->db->where('bsi.BookingID', $booking_id);
        $this->db->where('bsi.Status', 'Y');
        $this->db->order_by('bsi.PaymentDeadline', 'ASC');
        $this->db->order_by('bsi.SupplierInvoiceID', 'ASC');
        return $this->db->get()->result();
    }

    /**
     * Single invoice row by id (all columns, regardless of Status). Used by the
     * auth-gated attachment download/upload endpoints to resolve InvoiceFilePath
     * and verify the row belongs to the expected booking.
     */
    function Get_By_Id($supplier_invoice_id)
    {
        $this->db->where('SupplierInvoiceID', (int) $supplier_invoice_id);
        return $this->db->get('booking_supplier_invoice')->row();
    }

    /**
     * Bulk-insert new invoice rows for a booking. Mirrors Booking_Product_Model::Create()
     * — rows posted from the form have BookingID stamped here so the UI doesn't
     * need to repeat it on every row.
     */
    function Create($rows, $booking_id)
    {
        $admin_id = $this->session->userdata('admin_id');
        $rows = json_decode(json_encode($rows));
        if (!is_array($rows) || empty($rows)) {
            return;
        }
        $batch = [];
        foreach ($rows as $r) {
            // A file-only row (no supplier / no number) is valid now — SupplierID
            // and InvoiceNumber are nullable. Only skip a genuinely blank row so a
            // stray "Add Invoice" click doesn't insert an empty record.
            if (!supplier_invoice_row_has_data($r)) {
                continue;
            }
            $batch[] = [
                'BookingID'       => $booking_id,
                'SupplierID'      => (isset($r->SupplierID) && (int) $r->SupplierID > 0) ? (int) $r->SupplierID : null,
                'InvoiceNumber'   => (isset($r->InvoiceNumber) && trim($r->InvoiceNumber) !== '') ? trim($r->InvoiceNumber) : null,
                'InvoiceAmount'   => isset($r->InvoiceAmount) ? $r->InvoiceAmount : 0,
                'PaymentDeadline' => !empty($r->PaymentDeadline) ? $r->PaymentDeadline : null,
                'Remark'          => isset($r->Remark) ? $r->Remark : null,
                'InvoiceFilePath' => (isset($r->InvoiceFilePath) && $r->InvoiceFilePath !== '') ? $r->InvoiceFilePath : null,
                'Status'          => 'Y',
                'InsertBy'        => $admin_id,
                'InsertDate'      => date('Y-m-d H:i:s'),
            ];
        }
        if (!empty($batch)) {
            $this->db->insert_batch('booking_supplier_invoice', $batch);
        }
    }

    /**
     * Update existing invoices. Soft-deletes are submitted as rows with Status='N'.
     * Each row must include SupplierInvoiceID.
     */
    function Update($rows)
    {
        $admin_id = $this->session->userdata('admin_id');
        $rows = json_decode(json_encode($rows));
        if (!is_array($rows) || empty($rows)) {
            return;
        }
        $batch = [];
        foreach ($rows as $r) {
            if (empty($r->SupplierInvoiceID)) {
                continue;
            }
            $row = ['SupplierInvoiceID' => (int) $r->SupplierInvoiceID];
            // property_exists (not isset) so clearing a supplier / number back to
            // blank persists as NULL — both columns are nullable now.
            if (property_exists($r, 'SupplierID'))    { $row['SupplierID']    = ((int) $r->SupplierID > 0) ? (int) $r->SupplierID : null; }
            if (property_exists($r, 'InvoiceNumber')) { $row['InvoiceNumber'] = (trim((string) $r->InvoiceNumber) !== '') ? trim($r->InvoiceNumber) : null; }
            if (isset($r->InvoiceAmount))   { $row['InvoiceAmount']   = $r->InvoiceAmount; }
            if (isset($r->PaymentDeadline)) { $row['PaymentDeadline'] = !empty($r->PaymentDeadline) ? $r->PaymentDeadline : null; }
            if (isset($r->Remark))          { $row['Remark']          = $r->Remark; }
            // property_exists (not isset) so an explicit empty/null clears the attachment.
            if (property_exists($r, 'InvoiceFilePath')) { $row['InvoiceFilePath'] = ($r->InvoiceFilePath !== '' && $r->InvoiceFilePath !== null) ? $r->InvoiceFilePath : null; }
            if (isset($r->Status))          { $row['Status']          = $r->Status; }
            $row['UpdateBy']   = $admin_id;
            $row['UpdateDate'] = date('Y-m-d H:i:s');
            $batch[] = $row;
        }
        if (!empty($batch)) {
            $this->db->update_batch('booking_supplier_invoice', $batch, 'SupplierInvoiceID');
        }
    }
}
