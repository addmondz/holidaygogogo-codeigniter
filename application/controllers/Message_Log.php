<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Message_Log
 *
 * Serves the stored WhatsApp conversation for a single contact (by phone) as
 * JSON, for the "View message log" chat modal opened from the Guest List and
 * Booking listing. Read-only; login is enforced by MY_Controller.
 */
class Message_Log extends MY_Controller
{
    public function index()
    {
        $this->load->model('Ghl_Messages_Model');

        $phone = trim((string) $this->input->get('phone'));
        $name  = trim((string) $this->input->get('name'));

        $messages = $this->Ghl_Messages_Model->Conversation_By_Phone($phone);

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(array(
                'ok'       => true,
                'name'     => $name,
                'phone'    => $phone,
                'count'    => count($messages),
                'messages' => $messages,
            )));
    }

    /**
     * Same conversation as index(), streamed as a CSV download so a user can
     * save the chat log from the "View message log" modal.
     */
    public function csv()
    {
        $this->load->model('Ghl_Messages_Model');

        $phone = trim((string) $this->input->get('phone'));
        $name  = trim((string) $this->input->get('name'));

        $messages = $this->Ghl_Messages_Model->Conversation_By_Phone($phone);

        $slug = preg_replace('/[^0-9]/', '', $phone);
        if ($slug === '') { $slug = 'contact'; }
        $filename = 'message-log-' . $slug . '-' . date('Ymd-His') . '.csv';

        // Emit the file directly so the browser downloads it. Send headers with
        // header() (not $this->output) so they land before we stream the body.
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads unicode correctly
        fputcsv($out, array('Contact', 'Phone', 'Date/Time', 'Direction', 'Sender', 'Message'));
        foreach ($messages as $m) {
            fputcsv($out, array(
                $name,
                $phone,
                isset($m['time']) ? $m['time'] : '',
                (isset($m['side']) && $m['side'] === 'out') ? 'Outbound' : 'Inbound',
                isset($m['author']) ? $m['author'] : '',
                isset($m['body']) ? $m['body'] : '',
            ));
        }
        fclose($out);
        exit;
    }
}
