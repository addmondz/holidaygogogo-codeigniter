<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ghl_Custom_Field_Viewer extends MY_Controller
{
    public function index()
    {
        $fieldId = trim((string) $this->input->get('field_id'));

        $fields = $this->db->query("
            SELECT field_id, field_key, name, data_type, model
            FROM ghl_custom_fields
            WHERE LOWER(COALESCE(model, '')) IN ('', 'contact')
            ORDER BY COALESCE(position, 999999), name, field_key, field_id
        ")->result_array();

        $selected = null;
        foreach ($fields as $field) {
            if ((string) $field['field_id'] === $fieldId) {
                $selected = $field;
                break;
            }
        }

        $rows = array();
        if ($selected) {
            $rows = $this->db->query("
                SELECT
                    c.contact_id,
                    COALESCE(NULLIF(c.contact_name, ''), TRIM(CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, '')))) AS contact_name,
                    c.first_name,
                    c.last_name,
                    c.email,
                    c.phone,
                    c.source,
                    c.assigned_to,
                    c.date_added,
                    c.date_updated,
                    v.field_name,
                    v.field_id,
                    v.field_key,
                    v.value_text,
                    v.value_json
                FROM ghl_contact_custom_field_values v
                INNER JOIN ghl_contacts c ON c.contact_id = v.contact_id
                WHERE v.field_id = ?
                   OR (? <> '' AND v.field_key = ?)
                   OR v.field_identity = ?
                ORDER BY c.date_added DESC, c.id DESC
                LIMIT 1000
            ", array(
                (string) $selected['field_id'],
                (string) $selected['field_key'],
                (string) $selected['field_key'],
                (string) $selected['field_id'],
            ))->result_array();
        }

        echo '<!doctype html><html><head><meta charset="utf-8"><title>GHL Custom Field Viewer</title></head><body>';
        echo '<h2>GHL Custom Field Viewer</h2>';
        echo '<form method="get">';
        echo '<select name="field_id">';
        echo '<option value="">-- choose custom field --</option>';
        foreach ($fields as $field) {
            $label = $this->label($field);
            $sel = ((string) $field['field_id'] === $fieldId) ? ' selected' : '';
            echo '<option value="' . html_escape($field['field_id']) . '"' . $sel . '>' . html_escape($label) . '</option>';
        }
        echo '</select> ';
        echo '<button type="submit">Show contacts</button>';
        echo '</form>';

        echo '<p>Total custom fields: ' . count($fields) . '</p>';

        if ($selected) {
            echo '<h3>' . html_escape($this->label($selected)) . '</h3>';
            echo '<p>Contacts found: ' . count($rows) . ' (limited to 1000)</p>';
            echo '<table border="1" cellpadding="5" cellspacing="0">';
            echo '<tr><th>#</th><th>Contact ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Custom Field Value</th><th>Field Name In Contact</th><th>Field Key</th><th>Source</th><th>Assigned To</th><th>Date Added</th><th>Date Updated</th></tr>';
            foreach ($rows as $i => $row) {
                $value = $row['value_text'];
                if (($value === null || $value === '') && $row['value_json'] !== null) {
                    $value = $row['value_json'];
                }
                echo '<tr>';
                echo '<td>' . ($i + 1) . '</td>';
                echo '<td>' . html_escape($row['contact_id']) . '</td>';
                echo '<td>' . html_escape($row['contact_name']) . '</td>';
                echo '<td>' . html_escape($row['email']) . '</td>';
                echo '<td>' . html_escape($row['phone']) . '</td>';
                echo '<td>' . nl2br(html_escape($value)) . '</td>';
                echo '<td>' . html_escape($row['field_name']) . '</td>';
                echo '<td>' . html_escape($row['field_key']) . '</td>';
                echo '<td>' . html_escape($row['source']) . '</td>';
                echo '<td>' . html_escape($row['assigned_to']) . '</td>';
                echo '<td>' . html_escape($row['date_added']) . '</td>';
                echo '<td>' . html_escape($row['date_updated']) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        } elseif ($fieldId !== '') {
            echo '<p>Selected custom field not found.</p>';
        }

        echo '</body></html>';
    }

    private function label($field)
    {
        $parts = array();
        $parts[] = $field['name'] !== null && $field['name'] !== '' ? $field['name'] : $field['field_id'];
        if (!empty($field['field_key'])) {
            $parts[] = $field['field_key'];
        }
        if (!empty($field['data_type'])) {
            $parts[] = $field['data_type'];
        }
        return implode(' | ', $parts);
    }
}
