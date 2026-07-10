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
}
