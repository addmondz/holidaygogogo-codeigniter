<?php

require FCPATH.'vendor/autoload.php';  
use PhpOffice\PhpSpreadsheet\Spreadsheet;  
use PhpOffice\PhpSpreadsheet\Writer\Xlxs;

class Report extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Report_Model');
		$this->load->model('Universal_Model');
		$this->load->helper('report_access');
		// Access gate. Message Log carries its own 'ML' permission; sales agents
		// (level 20) may open the Lead Reply Activity dashboard + hourly chart
		// without VIEW REPORT ('VR') -- their data is self-scoped downstream.
		// Everyone else needs 'VR'. See report_access_helper.php.
		if(report_route_requires_redirect(
			$this->router->method,
			$this->session->level,
			$this->session->access_control
		)) {
			redirect('Dashboard');
		}
	}

	function index()
	{}

    function Lead_Dashboard()
    {
        $filters = $this->lead_dashboard_filters();
        $payload = $this->lead_dashboard_payload($filters);

        $titles = array(
            'tab_title' => 'HolidayGoGoGo | Report',
            'breadcrumb_title' => 'Report >> Real-Time Lead Dashboard'
        );

        $array = array(
            'dashboard_summary' => $payload['summary'],
            'lead_dashboard_agents' => $payload['agents'],
            'lead_dashboard_team_leads' => $payload['team_leads'],
            'lead_dashboard_rows' => $payload['rows'],
            'lead_dashboard_filters' => $filters,
            'lead_dashboard_updated_at' => $payload['updated_at'],
        );

        $this->load->view('layout/header', $titles);
        $this->load->view('report/lead_dashboard', $array);
        $this->load->view('layout/footer');
    }

    function Lead_Dashboard_Data()
    {
        $filters = $this->lead_dashboard_filters();
        $payload = $this->lead_dashboard_payload($filters);

        $response = array(
            'summary' => $payload['summary'],
            'rows' => $payload['rows'],
            'updated_at' => $payload['updated_at'],
        );

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($response));
    }

    function Ghl_Message_Log()
    {
        // Message Log exposes raw customer conversations, so it is gated by its
        // own 'ML' permission, independent of VR (VIEW REPORT). OWNER (level 10)
        // always has access.
        if($this->session->level != 10 && !in_array('ML', $this->session->access_control)) {
            redirect('Dashboard');
        }

        $this->load->helper('ghl_messages_log');

        $range = $this->ghl_message_log_range(trim((string) $this->input->get('log_date')));
        $contact = trim((string) $this->input->get('contact'));
        $agent = trim((string) $this->input->get('agent'));
        // Optional hour-of-day (0-23), set by the Lead Reply Hourly "Leads Handled"
        // drill-down so the log opens on exactly that hour of the chosen day.
        $hour = ghl_message_log_normalize_hour($this->input->get('hour'));
        // Optional time-of-day range (clock filter), applied on top of the date
        // window so it repeats across every day in range (e.g. 09:00-18:00 daily).
        $timeFrom = ghl_message_log_normalize_time($this->input->get('time_from'));
        $timeTo = ghl_message_log_normalize_time($this->input->get('time_to'));
        $range['contact'] = $contact;
        $range['agent'] = $agent;
        $range['hour'] = $hour;
        $range['hour_label'] = $hour === null ? '' : ghl_message_log_hour_label($hour);
        $range['time_from'] = $timeFrom;
        $range['time_to'] = $timeTo;

        $total = $this->Report_Model->Ghl_Messages_Log_Count($range['start_date'], $range['end_date'], $contact, $agent, $hour, $timeFrom, $timeTo);
        $pagination = ghl_messages_log_pagination($total, (int) $this->input->get('page'), 50);

        // The "Time Taken" column only makes sense when the stream is a single
        // coherent thread -- one agent's, or one contact's conversation -- so it
        // (and the average) is computed when either of those filters is applied.
        $show_reply_time = ($agent !== '' || $contact !== '');
        $avg_reply_label = '';

        if ($show_reply_time) {
            // Fetch one extra older row so even the bottom visible row has a
            // predecessor to measure its gap against; attach_reply_gaps slices
            // the result back to the displayed page size.
            $rows = $this->Report_Model->Ghl_Messages_Log(
                $range['start_date'],
                $range['end_date'],
                $pagination['per_page'] + 1,
                $pagination['offset'],
                $contact,
                $agent,
                $hour,
                $timeFrom,
                $timeTo
            );
            $messages = ghl_message_log_attach_reply_gaps($rows, $pagination['per_page']);

            $avg_reply_label = ghl_message_log_format_duration(
                $this->Report_Model->Ghl_Messages_Log_Avg_Reply_Seconds(
                    $range['start_date'],
                    $range['end_date'],
                    $contact,
                    $agent,
                    $hour,
                    $timeFrom,
                    $timeTo
                )
            );
        } else {
            $messages = $this->Report_Model->Ghl_Messages_Log(
                $range['start_date'],
                $range['end_date'],
                $pagination['per_page'],
                $pagination['offset'],
                $contact,
                $agent,
                $hour,
                $timeFrom,
                $timeTo
            );
        }

        $titles = array(
            'tab_title' => 'HolidayGoGoGo | Report',
            'breadcrumb_title' => 'Report >> Message Log'
        );

        $array = array(
            'log_messages' => $messages,
            'log_pagination' => $pagination,
            'log_filters' => $range,
            'log_agents' => $this->Report_Model->Ghl_Message_Log_Agents(),
            'log_show_reply_time' => $show_reply_time,
            'log_avg_reply' => $avg_reply_label,
        );

        $this->load->view('layout/header', $titles);
        $this->load->view('report/ghl_message_log', $array);
        $this->load->view('layout/footer');
    }

    function Ghl_Message_Log_Export()
    {
        // Same dedicated 'ML' gate as the on-screen log; the CSV export is the
        // same data, so VR alone must never reach it. OWNER (level 10) always
        // has access.
        if($this->session->level != 10 && !in_array('ML', $this->session->access_control)) {
            redirect('Dashboard');
        }

        $this->load->helper('ghl_messages_log');

        $range = $this->ghl_message_log_range(trim((string) $this->input->get('log_date')));
        $contact = trim((string) $this->input->get('contact'));
        $agent = trim((string) $this->input->get('agent'));
        $hour = ghl_message_log_normalize_hour($this->input->get('hour'));
        $timeFrom = ghl_message_log_normalize_time($this->input->get('time_from'));
        $timeTo = ghl_message_log_normalize_time($this->input->get('time_to'));

        $filename = ghl_message_log_export_filename($range['start_date'], $range['end_date']);

        // Stream the CSV directly so a large export never builds up in memory.
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads accents/emoji correctly.
        fputcsv($out, ghl_message_log_export_columns());

        // Page through the result in bounded chunks instead of loading every
        // matching row at once. The export query groups rows by contact/lead so
        // each chatroom reads as one contiguous thread.
        $chunk = 5000;
        $offset = 0;
        do {
            $rows = $this->Report_Model->Ghl_Messages_Log_Export(
                $range['start_date'],
                $range['end_date'],
                $chunk,
                $offset,
                $contact,
                $agent,
                $hour,
                $timeFrom,
                $timeTo
            );

            foreach ($rows as $row) {
                fputcsv($out, ghl_message_log_export_row($row));
            }

            $offset += $chunk;
        } while (count($rows) === $chunk);

        fclose($out);
        exit;
    }

    function Lead_Ownership_Dashboard()
    {
        $filters = $this->lead_ownership_filters();
        $payload = $this->lead_ownership_payload($filters);

        $titles = array(
            'tab_title' => 'HolidayGoGoGo | Report',
            'breadcrumb_title' => 'Report >> Lead Ownership Dashboard'
        );

        $array = array(
            'ownership_summary' => $payload['summary'],
            'lead_ownership_agents' => $payload['agents'],
            'lead_ownership_team_leads' => $payload['team_leads'],
            'lead_ownership_rows' => $payload['rows'],
            'lead_ownership_filters' => $filters,
            'lead_ownership_updated_at' => $payload['updated_at'],
        );

        $this->load->view('layout/header', $titles);
        $this->load->view('report/lead_ownership_dashboard', $array);
        $this->load->view('layout/footer');
    }

    function Lead_Ownership_Dashboard_Data()
    {
        $filters = $this->lead_ownership_filters();
        $payload = $this->lead_ownership_payload($filters);

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(array(
                'summary' => $payload['summary'],
                'rows' => $payload['rows'],
                'updated_at' => $payload['updated_at'],
            )));
    }

    function Lead_Ownership_Data()
    {
        $filters = $this->lead_ownership_data_filters();
        $payload = $this->lead_ownership_data_payload($filters);

        $titles = array(
            'tab_title' => 'HolidayGoGoGo | Report',
            'breadcrumb_title' => 'Report >> Lead Ownership Data'
        );

        $array = array(
            'lead_ownership_data_rows' => $payload['rows'],
            'lead_ownership_data_agents' => $payload['agents'],
            'lead_ownership_data_filters' => $filters,
            'lead_ownership_data_pagination' => $payload['pagination'],
            'lead_ownership_data_updated_at' => $payload['updated_at'],
        );

        $this->load->view('layout/header', $titles);
        $this->load->view('report/lead_ownership_data', $array);
        $this->load->view('layout/footer');
    }

    function Lead_Reply_Activity_Dashboard()
    {
        $filters = $this->lead_reply_activity_filters();
        $payload = $this->lead_reply_activity_payload($filters);

        $titles = array(
            'tab_title' => 'HolidayGoGoGo | Report',
            'breadcrumb_title' => 'Report >> Lead Reply Activity Dashboard'
        );

        $array = array(
            'reply_activity_summary' => $payload['summary'],
            'lead_reply_activity_agents' => $payload['agents'],
            'lead_reply_activity_team_leads' => $payload['team_leads'],
            'lead_reply_activity_rows' => $payload['rows'],
            'lead_reply_activity_filters' => $filters,
            'lead_reply_activity_updated_at' => $payload['updated_at'],
        );

        $this->load->view('layout/header', $titles);
        $this->load->view('report/lead_reply_activity_dashboard', $array);
        $this->load->view('layout/footer');
    }

    function Lead_Reply_Activity_Dashboard_Data()
    {
        $filters = $this->lead_reply_activity_filters();
        $payload = $this->lead_reply_activity_payload($filters);

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(array(
                'summary' => $payload['summary'],
                'rows' => $payload['rows'],
                'updated_at' => $payload['updated_at'],
            )));
    }

    /**
     * Stream the Lead Reply Activity figures for a date RANGE as an .xlsx.
     *
     * The dashboard itself is daily; here we walk each day in the chosen range,
     * run the same daily by-agent query once per day, and lay the results out
     * as owner rows with a (Responded / Transfer Out / Handling) column group
     * per day -- so a manager can scan a whole month in one sheet.
     */
    function Lead_Reply_Activity_Export()
    {
        $this->load->helper('lead_reply_export');

        // Reuse the dashboard filters (owner / team lead / agent restriction),
        // then override the date window with the export's own range picker.
        $baseFilters = $this->lead_reply_activity_filters();

        $exportRange = $this->parse_report_date_range(trim((string) $this->input->get('export_range')), true);
        $dates = lead_reply_export_dates($exportRange['start_date'], $exportRange['end_date'], 92);

        if (empty($dates)) {
            show_error('Please choose a valid export date range.', 400, 'Lead Reply Activity Export');
            return;
        }

        $startDate = $dates[0];
        $endDate = $dates[count($dates) - 1];

        // Run the daily by-agent query once per day, formatted exactly as the
        // on-screen table so the numbers match what users see for that date.
        $perDayRows = array();
        foreach ($dates as $date) {
            $dayFilters = $baseFilters;
            $dayFilters['reply_date'] = date('d/m/Y', strtotime($date));
            $dayFilters['start_date'] = $date;
            $dayFilters['end_date'] = $date;
            $perDayRows[$date] = $this->format_lead_reply_activity_rows(
                $this->Report_Model->Lead_Reply_Activity_By_Agent($dayFilters)
            );
        }

        $matrix = lead_reply_export_build_matrix($dates, $perDayRows);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Lead Reply Activity');
        $spreadsheet->getProperties()->setCreator('HolidayGoGoGo');

        $stringFromCol = function ($index) {
            return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index);
        };

        // --- Header rows ---------------------------------------------------
        // Dates run DOWN the left as rows; each owner is a column group across
        // the top. Row 1: owner-name group header (merged over the metric cols).
        // Row 2: the per-owner sub-headers, matching the on-screen dashboard
        // columns exactly (New Lead Picked Up / Responded / Transfer Out /
        // Handling / Avg Response Time).
        $metricHeaders = array('New Lead Picked Up', 'Responded', 'Transfer Out', 'Handling', 'Avg Response Time');
        $metricSpan = count($metricHeaders);
        $sheet->setCellValue('A1', 'Date');
        $sheet->mergeCells('A1:A2');

        $owners = $matrix['owners'];
        $colIndex = 2; // first owner's first metric column (B)
        foreach ($owners as &$owner) {
            $startCol = $stringFromCol($colIndex);
            $endCol = $stringFromCol($colIndex + $metricSpan - 1);
            $sheet->setCellValue($startCol . '1', $owner['owner_name']);
            $sheet->mergeCells($startCol . '1:' . $endCol . '1');
            foreach ($metricHeaders as $offset => $label) {
                $sheet->setCellValue($stringFromCol($colIndex + $offset) . '2', $label);
            }
            $owner['_col'] = $colIndex; // remember where this owner starts
            $colIndex += $metricSpan;
        }
        unset($owner);

        $lastColIdx = max(2, $colIndex - 1);
        $lastCol = $stringFromCol($lastColIdx);

        // Style the two header rows.
        $headerRange = 'A1:' . $lastCol . '2';
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF2F506F');
        $sheet->getStyle($headerRange)->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

        // --- Body ----------------------------------------------------------
        $rowNum = 3;
        if (empty($owners)) {
            $sheet->setCellValue('A3', 'No lead reply activity for the selected range and filters.');
        } else {
            // One row per date; each owner's trio of columns filled from the matrix.
            foreach ($dates as $date) {
                $sheet->setCellValueExplicit('A' . $rowNum, strtoupper(date('D, d M Y', strtotime($date))), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                foreach ($owners as $owner) {
                    $cell = isset($matrix['lookup'][$owner['owner_user_id']][$date])
                        ? $matrix['lookup'][$owner['owner_user_id']][$date]
                        : array(0, 0, 0, 0, '-');
                    $col = $owner['_col'];
                    // [picked_up, responded, transfer_out, handling, avg_label]
                    $sheet->setCellValue($stringFromCol($col) . $rowNum, (int) $cell[0]);
                    $sheet->setCellValue($stringFromCol($col + 1) . $rowNum, (int) $cell[1]);
                    $sheet->setCellValue($stringFromCol($col + 2) . $rowNum, (int) $cell[2]);
                    $sheet->setCellValue($stringFromCol($col + 3) . $rowNum, (int) $cell[3]);
                    $sheet->setCellValueExplicit($stringFromCol($col + 4) . $rowNum, (string) $cell[4], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
                $rowNum++;
            }

            // Trailing "RANGE TOTAL" row so each owner column still reads at a glance.
            $totalRow = $rowNum;
            $sheet->setCellValue('A' . $totalRow, 'RANGE TOTAL');
            foreach ($owners as $owner) {
                $col = $owner['_col'];
                $sheet->setCellValue($stringFromCol($col) . $totalRow, (int) $owner['total_picked_up']);
                $sheet->setCellValue($stringFromCol($col + 1) . $totalRow, (int) $owner['total_responded']);
                $sheet->setCellValue($stringFromCol($col + 2) . $totalRow, (int) $owner['total_transfer_out']);
                $sheet->setCellValue($stringFromCol($col + 3) . $totalRow, (int) $owner['total_handling']);
                $sheet->setCellValueExplicit($stringFromCol($col + 4) . $totalRow, (string) $owner['avg_response_time_label'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            $sheet->getStyle('A' . $totalRow . ':' . $lastCol . $totalRow)->getFont()->setBold(true);
            $sheet->getStyle('A' . $totalRow . ':' . $lastCol . $totalRow)->getBorders()->getTop()
                ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

            // Right-align every numeric column.
            $sheet->getStyle($stringFromCol(2) . '3:' . $lastCol . $totalRow)
                ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        }

        $sheet->getColumnDimension('A')->setWidth(18);
        for ($i = 2; $i <= $lastColIdx; $i++) {
            $sheet->getColumnDimension($stringFromCol($i))->setWidth(13);
        }
        // Freeze the date column + the two header rows.
        $sheet->freezePane('B3');

        $filename = lead_reply_export_filename($startDate, $endDate);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
    }

    function Lead_Reply_Activity_Dashboard_Details()
    {
        $filters = $this->lead_reply_activity_filters();
        $payload = $this->lead_reply_activity_details_payload($filters);

        $titles = array(
            'tab_title' => 'HolidayGoGoGo | Report',
            'breadcrumb_title' => 'Report >> Lead Reply Activity Details'
        );

        $array = array(
            'reply_activity_detail_summary' => $payload['summary'],
            'reply_activity_detail_rows' => $payload['rows'],
            'reply_activity_detail_agents' => $payload['agents'],
            'reply_activity_detail_team_leads' => $payload['team_leads'],
            'reply_activity_detail_filters' => $filters,
            'reply_activity_mobile_search' => $payload['mobile_search'],
            'reply_activity_updated_at' => $payload['updated_at'],
        );

        $this->load->view('layout/header', $titles);
        $this->load->view('report/lead_reply_activity_dashboard_details', $array);
        $this->load->view('layout/footer');
    }

    /**
     * JSON: the individual leads behind one owner's "New Lead Picked Up" or
     * "Lead Responded" number, powering the dashboard's drill-down modal. Reuses
     * lead_reply_activity_filters() so the same owner/date scoping AND the level-20
     * self-restriction that gate the dashboard also gate this list (a restricted
     * agent asking for another owner ends up with an empty owner => empty list).
     * The list queries share the count's where-clause builders, so the modal's
     * row count equals the number on screen.
     */
    function Lead_Reply_Activity_Leads()
    {
        $filters = $this->lead_reply_activity_filters();
        $metric = strtolower(trim((string) $this->input->get('metric')));
        if (!in_array($metric, array('picked_up', 'responded', 'transfer_out', 'today_handling'), true)) {
            $metric = 'picked_up';
        }

        // The modal is always scoped to one owner; with no resolvable owner there
        // is nothing to list (and nothing another agent could fish for).
        if (empty($filters['owner_user_id'])) {
            return $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(array('success' => true, 'metric' => $metric, 'leads' => array())));
        }

        $this->load->helper('lead_reply_activity_leads');

        switch ($metric) {
            case 'responded':
                $rows = $this->Report_Model->Lead_Reply_Activity_Responded_Leads($filters);
                break;
            case 'transfer_out':
                $rows = $this->Report_Model->Lead_Reply_Activity_Transfer_Out_Leads($filters);
                break;
            case 'today_handling':
                // Still-handling = replied-to leads that were NOT transferred out.
                $rows = lead_reply_activity_today_handling_leads(
                    $this->Report_Model->Lead_Reply_Activity_Responded_Leads($filters),
                    $this->Report_Model->Lead_Reply_Activity_Transfer_Out_Leads($filters)
                );
                break;
            case 'picked_up':
            default:
                $rows = $this->Report_Model->Lead_Reply_Activity_Picked_Up_Leads($filters);
                break;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(array(
                'success' => true,
                'metric' => $metric,
                'leads' => lead_reply_activity_leads_format($rows, $metric),
            )));
    }

    function Lead_Reply_Activity_Hourly()
    {
        $filters = $this->lead_reply_activity_filters();
        $payload = $this->lead_reply_activity_hourly_payload($filters);

        $titles = array(
            'tab_title' => 'HolidayGoGoGo | Report',
            'breadcrumb_title' => 'Report >> Lead Reply Hourly'
        );

        $array = array(
            'lead_reply_hourly_owner_id' => $payload['owner_id'],
            'lead_reply_hourly_owner_name' => $payload['owner_name'],
            'lead_reply_hourly_date_label' => $filters['reply_date'],
            'lead_reply_hourly_breakdown' => $payload['breakdown'],
            'lead_reply_hourly_avg_reply_label' => $payload['avg_reply_label'],
            'lead_reply_hourly_updated_at' => $payload['updated_at'],
        );

        $this->load->view('layout/header', $titles);
        $this->load->view('report/lead_reply_activity_hourly', $array);
        $this->load->view('layout/footer');
    }

    function Lead_Reply_Activity_Hourly_All()
    {
        $filters = $this->lead_reply_activity_filters();
        $payload = $this->lead_reply_activity_hourly_all_payload($filters);

        $titles = array(
            'tab_title' => 'HolidayGoGoGo | Report',
            'breadcrumb_title' => 'Report >> Lead Reply Hourly (All Agents)'
        );

        $array = array(
            'lead_reply_hourly_all_date_label' => $filters['reply_date'],
            'lead_reply_hourly_all_matrix' => $payload['matrix'],
            'lead_reply_hourly_all_updated_at' => $payload['updated_at'],
        );

        $this->load->view('layout/header', $titles);
        $this->load->view('report/lead_reply_activity_hourly_all', $array);
        $this->load->view('layout/footer');
    }

    function Lead_Data()
    {
        $filters = $this->lead_data_filters();
        $payload = $this->lead_data_payload($filters);

        $titles = array(
            'tab_title' => 'HolidayGoGoGo | Report',
            'breadcrumb_title' => 'Report >> Lead Data'
        );

        $array = array(
            'lead_data_summary' => $payload['summary'],
            'lead_data_agents' => $payload['agents'],
            'lead_data_tags' => $payload['tags'],
            'lead_data_rows' => $payload['rows'],
            'lead_data_filters' => $filters,
            'lead_data_pagination' => $payload['pagination'],
            'lead_data_updated_at' => $payload['updated_at'],
            'lead_data_sorting' => $payload['sorting'],
        );

        $this->load->view('layout/header', $titles);
        $this->load->view('report/lead_data', $array);
        $this->load->view('layout/footer');
    }

    function Lead_Data_Messages()
    {
        $conversationId = trim((string) $this->input->get('conversation_id'));
        $leadStartedAt = trim((string) $this->input->get('lead_started_at'));
        $nextLeadStartedAt = trim((string) $this->input->get('next_lead_started_at'));

        if ($conversationId === '' || $leadStartedAt === '') {
            return $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(array(
                    'success' => false,
                    'message' => 'conversation_id and lead_started_at are required.',
                )));
        }

        $messages = $this->Report_Model->Lead_Data_Messages($conversationId, $leadStartedAt, $nextLeadStartedAt !== '' ? $nextLeadStartedAt : null);
        $formatted = array();

        foreach ($messages as $message) {
            $formatted[] = array(
                'message_id' => $message['message_id'],
                'direction' => $message['direction'],
                'user_name' => $message['user_name'],
                'message_type' => $message['message_type'],
                'body' => $message['body'],
                'attachments_json' => $message['attachments_json'],
                'message_timestamp' => $message['message_timestamp'],
                'message_timestamp_label' => !empty($message['message_timestamp'])
                    ? date('d M Y h:i A', strtotime($message['message_timestamp']))
                    : '-',
            );
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(array(
                'success' => true,
                'messages' => $formatted,
            )));
    }

    /**
     * "Leads By Hour" report -- every landed lead plotted on a DATE x HOUR grid by
     * the hour its conversation first landed (pl.lead_started_at), so the owner can
     * see what time of day leads come in. This is the LANDING-TIME universe (one
     * row per ghl_processed_leads lead), distinct from the dashboard "New Lead
     * Picked Up" column, which additionally requires an assigned owner and a
     * brand-new customer. Date-range filtered (defaults to the last 30 days).
     * Owner-only in the menu; still passes the shared 'VR' gate in the constructor.
     */
    function Leads_By_Hour()
    {
        $filters = $this->lead_dashboard_filters();
        $data = $this->leads_by_hour_data($filters);

        $titles = array(
            'tab_title' => 'HolidayGoGoGo | Report',
            'breadcrumb_title' => 'Report >> Leads By Hour'
        );

        $array = array(
            'leads_by_hour_matrix' => $data['matrix'],
            'leads_by_hour_date_label' => $filters['lead_date'],
            'leads_by_hour_updated_at' => $data['updated_at'],
        );

        $this->load->view('layout/header', $titles);
        $this->load->view('report/leads_by_hour', $array);
        $this->load->view('layout/footer');
    }

    /** Stream the Leads By Hour grid as an .xlsx download (same filters). */
    function Leads_By_Hour_Export()
    {
        $filters = $this->lead_dashboard_filters();
        $data = $this->leads_by_hour_data($filters);
        $matrix = $data['matrix'];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Leads By Hour');
        $spreadsheet->getProperties()->setCreator('HolidayGoGoGo');

        // Hours occupy columns B..Y (2..25); Total lands in column Z (26).
        $totalColIndex = 2 + count($matrix['hours']);
        $totalCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalColIndex);

        // Header row.
        $sheet->setCellValue('A1', 'DATE');
        foreach ($matrix['hours'] as $i => $hour) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(2 + $i);
            $sheet->setCellValue($col . '1', strtoupper($hour['label']));
        }
        $sheet->setCellValue($totalCol . '1', 'TOTAL');
        $headerRange = 'A1:' . $totalCol . '1';
        $sheet->getStyle($headerRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_BLACK);
        $sheet->getStyle($headerRange)->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);
        $sheet->getStyle($headerRange)->getFont()->setBold(true);

        // One row per day.
        $row = 2;
        foreach ($matrix['rows'] as $r) {
            $sheet->setCellValueExplicit('A' . $row, $r['date_label'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            foreach ($r['counts'] as $i => $count) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(2 + $i);
                $sheet->setCellValue($col . $row, $count);
            }
            $sheet->setCellValue($totalCol . $row, $r['total']);
            $row++;
        }

        // Grand-total row (column totals per hour + overall).
        $sheet->setCellValueExplicit('A' . $row, 'TOTAL', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        foreach ($matrix['hour_totals'] as $i => $count) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(2 + $i);
            $sheet->setCellValue($col . $row, $count);
        }
        $sheet->setCellValue($totalCol . $row, $matrix['grand_total']);
        $totalRowRange = 'A' . $row . ':' . $totalCol . $row;
        $sheet->getStyle($totalRowRange)->getFont()->setBold(true);
        $sheet->getStyle($totalRowRange)->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        $sheet->getColumnDimension('A')->setWidth(22);
        $sheet->freezePaneByColumnAndRow(2, 2);

        $report = 'LEADS_BY_HOUR_' . date('Ymd') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $report . '"');
        header('Cache-Control: max-age=0');
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
    }

    /** Shared data build for the Leads By Hour page + export. */
    private function leads_by_hour_data($filters)
    {
        $this->load->helper('leads_by_hour');
        $dates = leads_by_hour_expand_dates($filters['start_date'], $filters['end_date']);
        $rows = $this->Report_Model->Leads_By_Hour($filters);

        return array(
            'matrix' => leads_by_hour_build_matrix($rows, $dates),
            'updated_at' => date('d M Y h:i A'),
        );
    }

	function Destination_Sales()
	{
        $titles = array('tab_title' => 'HolidayGoGoGo | Report', 'breadcrumb_title' => 'Report >> Destination Sales');
        $array = array('total_sales' => 0, 'total_net_profit' => 0);
        $array['destination_sales'] = $this->Report_Model->Destination_Profits();
        $destination_net_totals = $this->Report_Model->Destination_Net_Totals();
        $array['admins'] = $this->Report_Model->Sales_Agents();
        $array['categories'] = $this->Report_Model->Destinations();
        $count = 0;
        $total_sales = 0;
        $total_net_profit = 0;
        foreach($array['destination_sales'] as $destination_sale) {
            $total_sales += $destination_net_totals[$count]->NetTotal;
            $total_net_profit += $destination_sale->Profit;
            $destination_sale->Margin = round(($destination_sale->Profit / $destination_net_totals[$count]->NetTotal) * 100);
            $destination_sale->NetTotal = number_format($destination_net_totals[$count]->NetTotal, 2, '.', ',');
            $destination_sale->Profit = number_format($destination_sale->Profit, 2, '.', ',');
            $destination_sale->Month = strtoupper(date('M Y', strtotime($destination_sale->Month)));
            $count++;
        }
        $array['total_sales'] = number_format($total_sales, 2, '.', ',');
        $array['total_net_profit'] = $total_net_profit != 0 && $total_sales != 0 ? number_format($total_net_profit, 2, '.', ',') . ' (' . round(($total_net_profit / $total_sales) * 100) . '%)' : number_format($total_net_profit, 2, '.', ',') . ' (0%)';
        $this->load->view('layout/header', $titles);
        $this->load->view('report/destination_sales', $array);
        $this->load->view('layout/footer');
	}

    function City_Sales()
	{
        $titles = array('tab_title' => 'HolidayGoGoGo | Report', 'breadcrumb_title' => 'Report >> City Sales');
        $array = array('total_sales' => 0, 'total_net_profit' => 0);
        $array['city_sales'] = $this->Report_Model->City_Profits();
        $city_net_totals = $this->Report_Model->City_Net_Totals();
        $array['admins'] = $this->Report_Model->Sales_Agents();
        $array['cities'] = $this->Report_Model->Cities();
        $array['states'] = $this->Report_Model->States();
        $array['country_codes'] = $this->Report_Model->Countries();
        $count = 0;
        $total_sales = 0;
        $total_net_profit = 0;
        foreach($array['city_sales'] as $city_sale) {
            $total_sales += $city_net_totals[$count]->NetTotal;
            $total_net_profit += $city_sale->Profit;
            $city_sale->Margin = round(($city_sale->Profit / $city_net_totals[$count]->NetTotal) * 100);
            $city_sale->NetTotal = number_format($city_net_totals[$count]->NetTotal, 2, '.', ',');
            $city_sale->Profit = number_format($city_sale->Profit, 2, '.', ',');
            $city_sale->Month = strtoupper(date('M Y', strtotime($city_sale->Month)));
            $count++;
        }
        $array['total_sales'] = number_format($total_sales, 2, '.', ',');
        $array['total_net_profit'] = $total_net_profit != 0 && $total_sales != 0 ? number_format($total_net_profit, 2, '.', ',') . ' (' . round(($total_net_profit / $total_sales) * 100) . '%)' : number_format($total_net_profit, 2, '.', ',') . ' (0%)';
        $this->load->view('layout/header', $titles);
        $this->load->view('report/city_sales', $array);
        $this->load->view('layout/footer');
	}

    function State_Sales()
	{
        $titles = array('tab_title' => 'HolidayGoGoGo | Report', 'breadcrumb_title' => 'Report >> State Sales');
        $array = array('total_sales' => 0, 'total_net_profit' => 0);
        $array['state_sales'] = $this->Report_Model->State_Profits();
        $state_net_totals = $this->Report_Model->State_Net_Totals();
        $array['admins'] = $this->Report_Model->Sales_Agents();
        $array['categories'] = $this->Report_Model->States();
        $array['country_codes'] = $this->Report_Model->Countries();
        $count = 0;
        $total_sales = 0;
        $total_net_profit = 0;
        foreach($array['state_sales'] as $state_sale) {
            $total_sales += $state_net_totals[$count]->NetTotal;
            $total_net_profit += $state_sale->Profit;
            $state_sale->Margin = round(($state_sale->Profit / $state_net_totals[$count]->NetTotal) * 100);
            $state_sale->NetTotal = number_format($state_net_totals[$count]->NetTotal, 2, '.', ',');
            $state_sale->Profit = number_format($state_sale->Profit, 2, '.', ',');
            $state_sale->Month = strtoupper(date('M Y', strtotime($state_sale->Month)));
            $count++;
        }
        $array['total_sales'] = number_format($total_sales, 2, '.', ',');
        $array['total_net_profit'] = $total_net_profit != 0 && $total_sales != 0 ? number_format($total_net_profit, 2, '.', ',') . ' (' . round(($total_net_profit / $total_sales) * 100) . '%)' : number_format($total_net_profit, 2, '.', ',') . ' (0%)';
        $this->load->view('layout/header', $titles);
        $this->load->view('report/state_sales', $array);
        $this->load->view('layout/footer');
	}

    function Country_Sales()
	{
        $titles = array('tab_title' => 'HolidayGoGoGo | Report', 'breadcrumb_title' => 'Report >> Country Sales');
        $array = array('total_sales' => 0, 'total_net_profit' => 0);
        $array['country_sales'] = $this->Report_Model->Country_Profits();
        $country_net_totals = $this->Report_Model->Country_Net_Totals();
        $array['admins'] = $this->Report_Model->Sales_Agents();
        $array['country_codes'] = $this->Report_Model->Countries();
        $count = 0;
        $total_sales = 0;
        $total_net_profit = 0;
        foreach($array['country_sales'] as $country_sale) {
            $total_sales += $country_net_totals[$count]->NetTotal;
            $total_net_profit += $country_sale->Profit;
            $country_sale->Margin = round(($country_sale->Profit / $country_net_totals[$count]->NetTotal) * 100);
            $country_sale->NetTotal = number_format($country_net_totals[$count]->NetTotal, 2, '.', ',');
            $country_sale->Profit = number_format($country_sale->Profit, 2, '.', ',');
            $country_sale->Month = strtoupper(date('M Y', strtotime($country_sale->Month)));
            $count++;
        }
        $array['total_sales'] = number_format($total_sales, 2, '.', ',');
        $array['total_net_profit'] = $total_net_profit != 0 && $total_sales != 0 ? number_format($total_net_profit, 2, '.', ',') . ' (' . round(($total_net_profit / $total_sales) * 100) . '%)' : number_format($total_net_profit, 2, '.', ',') . ' (0%)';
        $this->load->view('layout/header', $titles);
        $this->load->view('report/country_sales', $array);
        $this->load->view('layout/footer');
	}

    function Product_Sales()
	{
        $titles = array('tab_title' => 'HolidayGoGoGo | Report', 'breadcrumb_title' => 'Report >> Product Sales');
        $array = array('total_sales' => 0, 'total_discount' => 0, 'total_net_profit' => 0);
        $array['product_sales'] = $this->Report_Model->Product_Sales();
        //$product_discounts = $this->Report_Model->Product_Discounts();
        $array['admins'] = $this->Report_Model->Sales_Agents();
        $array['products'] = $this->Report_Model->Products();
        $count = 0;
        $total_sales = 0;
        //$total_discounts = 0;
        $total_net_profit = 0;
        //$temp = null;
        foreach($array['product_sales'] as $product_sale) {
            $total_sales += $product_sale->NetTotal;
            $total_net_profit += $product_sale->Profit;
            $product_sale->Margin = round(($product_sale->Profit / $product_sale->NetTotal) * 100);
            $product_sale->AverageNetTotal = number_format($product_sale->NetTotal / $product_sale->Quantity, 2, '.', ',');
            $product_sale->NetTotal = number_format($product_sale->NetTotal, 2, '.', ',');
            $product_sale->Profit = number_format($product_sale->Profit, 2, '.', ',');
            $product_sale->Month = strtoupper(date('M Y', strtotime($product_sale->Month)));
            $count++;
        }
        /*
        foreach($product_discounts as $discount) {
            if(empty($temp) || $temp != $discount->BookingID) {
                $total_discounts += $discount->Discount;
                $temp = $discount->BookingID;
            }
        }
        */
        $array['total_sales'] = number_format($total_sales, 2, '.', ',');
        //$array['total_discount'] = number_format($total_discounts, 2, '.', ',');
        $array['total_net_profit'] = $total_net_profit != 0 && $total_sales != 0 ? number_format($total_net_profit, 2, '.', ',') . ' (' . round(($total_net_profit / $total_sales) * 100) . '%)' : number_format($total_net_profit, 2, '.', ',') . ' (0%)';
        $this->load->view('layout/header', $titles);
        $this->load->view('report/product_sales', $array);
        $this->load->view('layout/footer');
	}

    function BC_By_Source()
	{
        $titles = array('tab_title' => 'HolidayGoGoGo | Report', 'breadcrumb_title' => 'Report >> BC By Source');
        $array = array('total_sales' => 0, 'total_net_profit' => 0);
        $array['bc_by_source'] = $this->Report_Model->BC_By_Source();
        $bc_by_source_net_totals = $this->Report_Model->BC_By_Source_Net_Totals();
        $array['admins'] = $this->Report_Model->Sales_Agents();
        $array['sources'] = $this->Report_Model->Sources();
        $count = 0;
        $total_sales = 0;
        $total_net_profit = 0;
        foreach($array['bc_by_source'] as $bc) {
            $total_sales += $bc_by_source_net_totals[$count]->NetTotal;
            $total_net_profit += $bc->Profit;
            $bc->TotalBC = $bc_by_source_net_totals[$count]->TotalBC;
            $bc->Margin = round(($bc->Profit / $bc_by_source_net_totals[$count]->NetTotal) * 100);
            $bc->NetTotal = number_format($bc_by_source_net_totals[$count]->NetTotal, 2, '.', ',');
            $bc->Profit = number_format($bc->Profit, 2, '.', ',');
            $bc->Month = strtoupper(date('M Y', strtotime($bc->Month)));
            $count++;
        }
        $array['total_sales'] = number_format($total_sales, 2, '.', ',');
        $array['total_net_profit'] = $total_net_profit != 0 && $total_sales != 0 ? number_format($total_net_profit, 2, '.', ',') . ' (' . round(($total_net_profit / $total_sales) * 100) . '%)' : number_format($total_net_profit, 2, '.', ',') . ' (0%)';
        $this->load->view('layout/header', $titles);
        $this->load->view('report/bc_by_source', $array);
        $this->load->view('layout/footer');
	}

    function Guest_By_Country()
	{
        $titles = array('tab_title' => 'HolidayGoGoGo | Report', 'breadcrumb_title' => 'Report >> Guest By Country');
        $array['guest_by_country'] = $this->Report_Model->Guest_By_Country();
        $array['admins'] = $this->Report_Model->Sales_Agents();
        $array['country_codes'] = $this->Report_Model->Countries();
        foreach($array['guest_by_country'] as $guest) {
            $guest->Month = strtoupper(date('M Y', strtotime($guest->Month)));
        }
        $this->load->view('layout/header', $titles);
        $this->load->view('report/guest_by_country', $array);
        $this->load->view('layout/footer');
	}

    function PowerBI()
    {
        $titles = array('tab_title' => 'HolidayGoGoGo | Report', 'breadcrumb_title' => 'Report >> PowerBI');
        $array = array(
            'workspace_id' => '',
            'dataset_id' => '',
            'embed_url' => '',
            'embed_token' => '',
            'error' => '',
        );

        $this->load->library('powerbi');
        $this->config->load('powerbi', TRUE);
        $array['dataset_id'] = $this->config->item('dataset_id', 'powerbi');
        $array['workspace_id'] = $this->config->item('workspace_id', 'powerbi');

        if (!$this->powerbi->isConfigured()) {
            $array['error'] = 'Power BI is not configured. Please add your credentials and dataset ID to the .env file.';
        } else {
            try {
                $array['embed_url'] = $this->powerbi->getCreateEmbedUrl();
                $array['embed_token'] = $this->powerbi->getCreateEmbedToken();
            } catch (Exception $e) {
                $array['error'] = $e->getMessage();
            }
        }

        $this->load->view('layout/header', $titles);
        $this->load->view('report/powerbi', $array);
        $this->load->view('layout/footer');
    }

    function Embed_Token()
    {
        header('Content-Type: application/json');
        $this->load->library('powerbi');

        try {
            if (!$this->powerbi->isConfigured()) {
                throw new Exception('Power BI is not configured.');
            }

            echo json_encode(array(
                'embedToken' => $this->powerbi->getCreateEmbedToken(),
                'embedUrl' => $this->powerbi->getCreateEmbedUrl(),
                'datasetId' => $this->config->item('dataset_id', 'powerbi'),
                'tokenExpiry' => $this->powerbi->getTokenExpiry(),
            ));
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(array('error' => $e->getMessage()));
        }
    }

    function Download_Product_Sales() {
		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$spreadsheet->getActiveSheet()->setTitle('Report');
		$spreadsheet->getProperties()->setCreator('HolidayGoGoGo');
		$spreadsheet->getActiveSheet()->setCellValue('A1', 'NO.');
		$spreadsheet->getActiveSheet()->setCellValue('B1', 'MONTH');
		$spreadsheet->getActiveSheet()->setCellValue('C1', 'PRODUCT');
		$spreadsheet->getActiveSheet()->setCellValue('D1', 'GROSS SALES (RM)');
		$spreadsheet->getActiveSheet()->setCellValue('E1', 'TOTAL QUANTITY');
		$spreadsheet->getActiveSheet()->setCellValue('F1', 'AVERAGE GROSS SALES (RM)');
		$spreadsheet->getActiveSheet()->setCellValue('G1', 'NET PROFIT (RM)');
		$spreadsheet->getActiveSheet()->setCellValue('H1', 'NET PROFIT MARGIN (%)');
		$row = 2;
		$product_sales = $this->Report_Model->Product_Sales();
		$spreadsheet->getActiveSheet()->getStyle('A1:H1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_BLACK);
		$spreadsheet->getActiveSheet()->getStyle('A1:H1')->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);
		$spreadsheet->getActiveSheet()->getStyle('A1:H1')->getFont()->setBold(true);
		if(!empty($product_sales)) {
			$count = 1;
			$total_sales = 0;
			$total_net_profit = 0;
			foreach($product_sales as $product_sale) {
				$total_sales += $product_sale->NetTotal;
            	$total_net_profit += $product_sale->Profit;
				$product_sale->Margin = round(($product_sale->Profit / $product_sale->NetTotal) * 100);
				$product_sale->AverageNetTotal = number_format($product_sale->NetTotal / $product_sale->Quantity, 2, '.', ',');
				$product_sale->NetTotal = number_format($product_sale->NetTotal, 2, '.', ',');
				$product_sale->Profit = number_format($product_sale->Profit, 2, '.', ',');
				$product_sale->Month = strtoupper(date('M Y', strtotime($product_sale->Month)));
				$spreadsheet->getActiveSheet()->setCellValueExplicit('A' . $row, $count, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('B' . $row, $product_sale->Month, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('C' . $row, $product_sale->Product . ' (' . $product_sale->ProductCode . ')', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('D' . $row, $product_sale->NetTotal, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('E' . $row, $product_sale->Quantity, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('F' . $row, $product_sale->AverageNetTotal, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('G' . $row, $product_sale->Profit, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('H' . $row, $product_sale->Margin, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$count++;
				$row++;
			}
			$spreadsheet->getActiveSheet()->getStyle('D')->getNumberFormat()->setFormatCode('"RM "#,##0.00_-');
			$spreadsheet->getActiveSheet()->getStyle('G')->getNumberFormat()->setFormatCode('"RM "#,##0.00_-');
			$spreadsheet->getActiveSheet()->getCell('C' . ($row + 2))->setValue('Total');
			$spreadsheet->getActiveSheet()->getStyle('D' . ($row + 2) . ':' . 'G' . ($row + 2))->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
			$spreadsheet->getActiveSheet()->setCellValue('D' . ($row + 2), $total_sales);
			$spreadsheet->getActiveSheet()->setCellValue('G' . ($row + 2), $total_net_profit);
			$spreadsheet->getActiveSheet()->getStyle('D' . ($row + 2) . ':' . 'G' . ($row + 2))->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DOUBLE);
			$spreadsheet->getActiveSheet()->getStyle('A:H')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
		} else {
			$spreadsheet->getActiveSheet()->mergeCells('A2:H2');
			$spreadsheet->getActiveSheet()->getCell('A2')->setValue('Product Records Not Found');
			$spreadsheet->getActiveSheet()->getStyle('A:H')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
		}
		$spreadsheet->getActiveSheet()->getColumnDimension('A')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('B')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('C')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('D')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('E')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('F')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('G')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('H')->setWidth(35);
		$report = 'REPORT_' . date('Ymd') . '.xlsx';
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="' . $report . '"');
		header('Cache-Control: max-age=0');
		header('Cache-Control: max-age=1');
		$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
		$writer->save('php://output');
	}

	function Download_Guest_By_Country() {
		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$spreadsheet->getActiveSheet()->setTitle('Report');
		$spreadsheet->getProperties()->setCreator('HolidayGoGoGo');
		$spreadsheet->getActiveSheet()->setCellValue('A1', 'NO.');
		$spreadsheet->getActiveSheet()->setCellValue('B1', 'MONTH');
		$spreadsheet->getActiveSheet()->setCellValue('C1', 'COUNTRY');
		$spreadsheet->getActiveSheet()->setCellValue('D1', 'TOTAL ADULT');
		$spreadsheet->getActiveSheet()->setCellValue('E1', 'TOTAL CHILDREN');
		$spreadsheet->getActiveSheet()->setCellValue('F1', 'TOTAL INFANT');
		$row = 2;
		$guest_by_country = $this->Report_Model->Guest_By_Country();
		$spreadsheet->getActiveSheet()->getStyle('A1:F1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_BLACK);
		$spreadsheet->getActiveSheet()->getStyle('A1:F1')->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);
		$spreadsheet->getActiveSheet()->getStyle('A1:F1')->getFont()->setBold(true);
		if(!empty($guest_by_country)) {
			$count = 1;
			foreach($guest_by_country as $guest) {
				$guest->Month = strtoupper(date('M Y', strtotime($guest->Month)));
				$spreadsheet->getActiveSheet()->setCellValueExplicit('A' . $row, $count, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('B' . $row, $guest->Month, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('C' . $row, $guest->Country, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('D' . $row, $guest->TotalAdult, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('E' . $row, $guest->TotalChildren, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('F' . $row, $guest->TotalInfant, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$count++;
				$row++;
			}
			$spreadsheet->getActiveSheet()->getStyle('A:F')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
		} else {
			$spreadsheet->getActiveSheet()->mergeCells('A2:F2');
			$spreadsheet->getActiveSheet()->getCell('A2')->setValue('Guest Records Not Found');
			$spreadsheet->getActiveSheet()->getStyle('A:F')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
		}
		$spreadsheet->getActiveSheet()->getColumnDimension('A')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('B')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('C')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('D')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('E')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('F')->setWidth(35);
		$report = 'REPORT_' . date('Ymd') . '.xlsx';
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="' . $report . '"');
		header('Cache-Control: max-age=0');
		header('Cache-Control: max-age=1');
		$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
		$writer->save('php://output');
	}
	
	function Download() {
		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$spreadsheet->getActiveSheet()->setTitle('Report');
		$spreadsheet->getProperties()->setCreator('HolidayGoGoGo');
		$spreadsheet->getActiveSheet()->setCellValue('A1', 'BOOKING DATE');
		$spreadsheet->getActiveSheet()->setCellValue('B1', 'SALES AGENT');
		$spreadsheet->getActiveSheet()->setCellValue('C1', 'BOOKING NUMBER');
		$spreadsheet->getActiveSheet()->setCellValue('D1', 'RESERVATION NUMBER');
		$spreadsheet->getActiveSheet()->setCellValue('E1', 'CUSTOMER');
		$spreadsheet->getActiveSheet()->setCellValue('F1', 'MOBILE');
		$spreadsheet->getActiveSheet()->setCellValue('G1', 'TRAVEL DATE');
		$spreadsheet->getActiveSheet()->setCellValue('H1', 'PAX NUMBER');
		$spreadsheet->getActiveSheet()->setCellValue('I1', 'DEPOSIT DEADLINE');
		$spreadsheet->getActiveSheet()->setCellValue('J1', 'FULL PAYMENT DEADLINE');
		$spreadsheet->getActiveSheet()->setCellValue('K1', 'DESTINATION');
		$spreadsheet->getActiveSheet()->setCellValue('L1', 'SUBTOTAL');
		$spreadsheet->getActiveSheet()->setCellValue('M1', 'DISCOUNT');
		$spreadsheet->getActiveSheet()->setCellValue('N1', 'NET TOTAL');
		$spreadsheet->getActiveSheet()->setCellValue('O1', 'PROFIT');
		$spreadsheet->getActiveSheet()->setCellValue('P1', 'STATUS');
		$spreadsheet->getActiveSheet()->setCellValue('Q1', 'REMARK');
		$spreadsheet->getActiveSheet()->setCellValue('R1', 'CHAT LANGUAGE');
		$spreadsheet->getActiveSheet()->setCellValue('S1', 'SOURCE');
		$spreadsheet->getActiveSheet()->setCellValue('T1', 'CITY');
		$spreadsheet->getActiveSheet()->setCellValue('U1', 'STATE');
		$spreadsheet->getActiveSheet()->setCellValue('V1', 'COUNTRY');
		$row = 2;
		$bookings = $this->Report_Model->Report();
		$spreadsheet->getActiveSheet()->getStyle('A1:V1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_BLACK);
		$spreadsheet->getActiveSheet()->getStyle('A1:V1')->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);
		$spreadsheet->getActiveSheet()->getStyle('A1:V1')->getFont()->setBold(true);
		if(!empty($bookings)) {
			$total_subtotal = 0;
			$total_discount = 0;
			$total_net_total = 0;
			$total_profit = 0;
			foreach($bookings as $booking) {
				$total_subtotal += $booking->Subtotal;
				$total_discount += $booking->Discount;
				$total_net_total += $booking->NetTotal;
				$booking->Mobile = $this->Universal_Model->Read_Country_Code($booking->CountryCodeID) . str_replace([' ', '-'], '', $booking->Mobile);
				if(!empty($booking->StartDate) && !empty($booking->EndDate)) {
					$booking->TravelDate = strtoupper(date('j M', strtotime($booking->StartDate)) . ' - ' . date('j M Y', strtotime($booking->EndDate)));
				} else {
					$booking->TravelDate = null;
				}
				if(!empty($booking->Adult)) {
					$booking->Adult = $booking->Adult == 1 ? $booking->Adult . ' ADULT ' : $booking->Adult . ' ADULTS ';
				}
				if(!empty($booking->Children)) {
					$booking->Children = $booking->Children == 1 ? $booking->Children . ' CHILD ' : $booking->Children . ' CHILDREN ';
				}
				if(!empty($booking->Infant)) {
					$booking->Infant = $booking->Infant == 1 ? $booking->Infant . ' INFANT ' : $booking->Infant . ' INFANTS ';
				}
				if(!empty($booking->Adult) && !empty($booking->Children) && !empty($booking->Infant)) {
					$booking->PaxNumber = $booking->Adult . '& ' . $booking->Children . '& ' . $booking->Infant;
				} else {
					if(!empty($booking->Adult) && empty($booking->Children) && !empty($booking->Infant)) {
						$booking->PaxNumber = $booking->Adult . '& ' . $booking->Infant;
					} else {
						if(!empty($booking->Adult) && !empty($booking->Children) && empty($booking->Infant)) {
							$booking->PaxNumber = $booking->Adult . '& ' . $booking->Children;
						} else {
							if(!empty($booking->Adult) && empty($booking->Children) && empty($booking->Infant)) {
								$booking->PaxNumber = $booking->Adult;
							} else {
								if(empty($booking->Adult) && !empty($booking->Children) && !empty($booking->Infant)) {
									$booking->PaxNumber = $booking->Children . '& ' . $booking->Infant;
								} else {
									if(empty($booking->Adult) && empty($booking->Children) && !empty($booking->Infant)) {
										$booking->PaxNumber = $booking->Infant;
									} else {
										$booking->PaxNumber = $booking->Children;
									}
								}
							}
						}
					}
				}
				$booking->Discount = $booking->Discount == 0.00 ? '' : $booking->Discount;
				if($booking->LockStatus == 'N' && $booking->Status == 'PTV') {
					$booking->Status = 'PGL';
				}
				if($booking->AfterSalesService == 'PENDING' && $booking->Status == 'Y') {
					$booking->Status = 'PR';
				}
				if(empty($booking->DepositDeadline)) {
					if(date('Y-m-d') > $booking->FullPaymentDeadline && ($booking->Status == 'P' || $booking->Status == 'PP')) {
						$booking->Status = 'PO';
					}
				} else {
					if((date('Y-m-d') > $booking->DepositDeadline && $booking->Status == 'P') || (date('Y-m-d') > $booking->FullPaymentDeadline && ($booking->Status == 'P' || $booking->Status == 'PP'))) {
						$booking->Status = 'PO';
					}
				}
				if(!empty($booking->DepositDeadline)) {
					$booking->DepositDeadline = strtoupper(date('j M Y', strtotime($booking->DepositDeadline)));
				}
				$booking->FullPaymentDeadline = strtoupper(date('j M Y', strtotime($booking->FullPaymentDeadline)));
				if($booking->CancelStatus == 'Y') {
					$booking->Status = 'CANCELLED';
				} else {
					switch($booking->Status) {
						case 'Y':
							$booking->Status = 'COMPLETED';
							break;
						case 'PR':
							$booking->Status = 'PENDING REVIEW';
							break;
						case 'P':
							$booking->Status = 'PENDING PAYMENT';
							break;
						case 'PP':
							$booking->Status = 'PARTIAL PAYMENT';
							break;
						case 'PTV':
							$booking->Status = 'PENDING TRAVEL VOUCHER';
							break;
						case 'PGL':
							$booking->Status = 'PENDING GUEST LIST';
							break;
						case 'PT':
							$booking->Status = 'PENDING TRAVEL';
							break;
						case 'OG':
							$booking->Status = 'ON-GOING';
							break;
						case 'PO':
							$booking->Status = 'PAYMENT OVERDUE';
					}
				}
				$booking->InsertDate = strtoupper(date('j M Y', strtotime($booking->InsertDate)));
				$payments = $this->Report_Model->Payments($booking->BookingID);
				$total_credit = 0;
				$total_debit = 0;
				$net_profit = 0;
				$profit_margin = 0;
				if(!empty($payments)) {
					foreach($payments as $payment) {
						if($payment->Status == 'Y' || $payment->Status == 'P') {
							if($payment->Credit != 0.00) {
								$total_credit += $payment->Credit;
							} else {
								$total_debit += $payment->Debit;
							}
						}
					}
					$net_profit = $total_credit - $total_debit;
					if($net_profit != 0) {
						$profit_margin = round(($net_profit / $booking->NetTotal) * 100);
					}
				}
				$booking->Profit = $net_profit != 0 ? 'RM ' . number_format($net_profit, 2, '.', ',') . ' (' . $profit_margin . '%)' : 'RM ' . number_format($net_profit, 2, '.', ',') . ' (0%)';
				$total_profit += $net_profit;
				$spreadsheet->getActiveSheet()->setCellValueExplicit('A' . $row, $booking->InsertDate, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('B' . $row, $booking->SalesAgent, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('C' . $row, $booking->BookingNumber, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('D' . $row, $booking->ReservationNumber, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('E' . $row, $booking->Customer, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('F' . $row, $booking->Mobile, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('G' . $row, $booking->TravelDate, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('H' . $row, $booking->PaxNumber, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('I' . $row, $booking->DepositDeadline, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('J' . $row, $booking->FullPaymentDeadline, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('K' . $row, $booking->Destination, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValue('L' . $row, $booking->Subtotal);
				$spreadsheet->getActiveSheet()->setCellValue('M' . $row, $booking->Discount);
				$spreadsheet->getActiveSheet()->setCellValue('N' . $row, $booking->NetTotal);
				$spreadsheet->getActiveSheet()->setCellValue('O' . $row, $booking->Profit);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('P' . $row, $booking->Status, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('Q' . $row, $booking->BookingRemark, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('R' . $row, $booking->ChatLanguage, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('S' . $row, $booking->Source, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('T' . $row, $booking->City, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('U' . $row, $booking->State, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('V' . $row, $booking->Country, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$row++;
			}
			$total_profit = $total_profit != 0 && $total_net_total != 0 ? number_format($total_profit, 2, '.', ',') . ' (' . round(($total_profit / $total_net_total) * 100) . '%)' : number_format($total_profit, 2, '.', ',') . ' (0%)';
			$spreadsheet->getActiveSheet()->getStyle('L')->getNumberFormat()->setFormatCode('"RM "#,##0.00_-');
			$spreadsheet->getActiveSheet()->getStyle('M')->getNumberFormat()->setFormatCode('"RM "#,##0.00_-');
			$spreadsheet->getActiveSheet()->getStyle('N')->getNumberFormat()->setFormatCode('"RM "#,##0.00_-');
			$spreadsheet->getActiveSheet()->getStyle('O')->getNumberFormat()->setFormatCode('"RM "#,##0.00_-');
			$spreadsheet->getActiveSheet()->getCell('K' . ($row + 2))->setValue('Total');
			$spreadsheet->getActiveSheet()->getStyle('L' . ($row + 2) . ':' . 'O' . ($row + 2))->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
			$spreadsheet->getActiveSheet()->setCellValue('L' . ($row + 2), $total_subtotal);
			$spreadsheet->getActiveSheet()->setCellValue('M' . ($row + 2), $total_discount);
			$spreadsheet->getActiveSheet()->setCellValue('N' . ($row + 2), $total_net_total);
			$spreadsheet->getActiveSheet()->setCellValue('O' . ($row + 2), $total_profit);
			$spreadsheet->getActiveSheet()->getStyle('L' . ($row + 2) . ':' . 'O' . ($row + 2))->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DOUBLE);
			$spreadsheet->getActiveSheet()->getStyle('A:V')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
		} else {
			$spreadsheet->getActiveSheet()->mergeCells('A2:V2');
			$spreadsheet->getActiveSheet()->getCell('A2')->setValue('Booking Records Not Found');
			$spreadsheet->getActiveSheet()->getStyle('A:V')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
		}
		$spreadsheet->getActiveSheet()->getColumnDimension('A')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('B')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('C')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('D')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('E')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('F')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('G')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('H')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('I')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('J')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('K')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('L')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('M')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('N')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('O')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('P')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('Q')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('R')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('S')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('T')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('U')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('V')->setWidth(35);
		$report = 'REPORT_' . date('Ymd') . '.xlsx';
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="' . $report . '"');
		header('Cache-Control: max-age=0');
		header('Cache-Control: max-age=1');
		$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
		$writer->save('php://output');
	}

    private function lead_dashboard_payload($filters)
    {
        $restrict = isset($filters['_restrict_agent_ids']) ? $filters['_restrict_agent_ids'] : null;
        return array(
            'summary' => $this->format_lead_dashboard_summary($this->Report_Model->Lead_Dashboard_Summary($filters)),
            'rows' => $this->format_lead_dashboard_rows($this->Report_Model->Lead_Dashboard_By_Agent($filters)),
            'agents' => $this->Report_Model->Lead_Dashboard_Agents($restrict),
            'team_leads' => $this->Report_Model->Lead_Dashboard_Teams(),
            'updated_at' => date('Y-m-d H:i:s'),
        );
    }

    private function lead_ownership_payload($filters)
    {
        $restrict = isset($filters['_restrict_agent_ids']) ? $filters['_restrict_agent_ids'] : null;

        return array(
            'summary' => $this->format_lead_ownership_summary($this->Report_Model->Lead_Ownership_Summary($filters)),
            'rows' => $this->format_lead_ownership_rows($this->Report_Model->Lead_Ownership_By_Agent($filters)),
            'agents' => $this->Report_Model->Lead_Ownership_Agents($restrict),
            'team_leads' => $this->Report_Model->Lead_Dashboard_Teams(),
            'updated_at' => $this->Report_Model->Lead_Ownership_Last_Calculated_At(),
        );
    }

    private function lead_ownership_data_payload($filters)
    {
        $perPage = isset($filters['per_page']) ? max(10, (int) $filters['per_page']) : 25;
        $currentPage = isset($filters['page']) ? max(1, (int) $filters['page']) : 1;
        $offset = ($currentPage - 1) * $perPage;
        $totalRows = (int) $this->Report_Model->Lead_Ownership_Data_Total_Count($filters);
        $restrict = isset($filters['_restrict_agent_ids']) ? $filters['_restrict_agent_ids'] : null;

        return array(
            'rows' => $this->format_lead_ownership_data_rows($this->Report_Model->Lead_Ownership_Data_Rows($filters, $perPage, $offset)),
            'agents' => $this->Report_Model->Lead_Ownership_Agents($restrict),
            'pagination' => $this->build_lead_ownership_data_pagination($filters, $currentPage, $perPage, $totalRows),
            'updated_at' => $this->Report_Model->Lead_Ownership_Last_Calculated_At(),
        );
    }

    private function lead_reply_activity_payload($filters)
    {
        $restrict = isset($filters['_restrict_agent_ids']) ? $filters['_restrict_agent_ids'] : null;

        return array(
            'summary' => $this->format_lead_reply_activity_summary($this->Report_Model->Lead_Reply_Activity_Summary($filters)),
            'rows' => $this->format_lead_reply_activity_rows($this->Report_Model->Lead_Reply_Activity_By_Agent($filters)),
            'agents' => $this->Report_Model->Lead_Ownership_Agents($restrict),
            'team_leads' => $this->Report_Model->Lead_Dashboard_Teams(),
            'updated_at' => date('Y-m-d H:i:s'),
        );
    }

    private function lead_reply_activity_details_payload($filters)
    {
        $restrict = isset($filters['_restrict_agent_ids']) ? $filters['_restrict_agent_ids'] : null;
        $mobile = trim((string) $this->input->get('mobile'));

        return array(
            'summary' => $this->format_lead_reply_activity_detail_summary($this->Report_Model->Lead_Reply_Activity_Details_Summary($filters)),
            'rows' => $this->format_lead_reply_activity_detail_rows($this->Report_Model->Lead_Reply_Activity_Details_Rows($filters)),
            'agents' => $this->Report_Model->Lead_Ownership_Agents($restrict),
            'team_leads' => $this->Report_Model->Lead_Dashboard_Teams(),
            'mobile_search' => array(
                'mobile' => $mobile,
                'summary' => $mobile !== '' ? $this->format_lead_reply_activity_mobile_summary($this->Report_Model->Lead_Reply_Activity_Mobile_Summary($mobile, $filters)) : null,
                'leads' => $mobile !== '' ? $this->format_lead_reply_activity_mobile_leads($this->Report_Model->Lead_Reply_Activity_Mobile_Leads($mobile, $filters)) : array(),
                'owners' => $mobile !== '' ? $this->Report_Model->Lead_Reply_Activity_Mobile_Owners($mobile, $filters) : array(),
            ),
            'updated_at' => date('Y-m-d H:i:s'),
        );
    }

    private function lead_reply_activity_hourly_payload($filters)
    {
        $this->load->helper('ghl_messages_log');

        $restrict = isset($filters['_restrict_agent_ids']) ? $filters['_restrict_agent_ids'] : null;
        $ownerIds = isset($filters['owner_user_id']) ? array_values(array_filter((array) $filters['owner_user_id'], 'strlen')) : array();
        $ownerId = !empty($ownerIds) ? (string) $ownerIds[0] : '';

        // Lock the query to a single owner: this is a per-owner drill-down, never a
        // whole-team roll-up. Drop any extra ids the URL may carry.
        $singleOwnerFilters = $filters;
        $singleOwnerFilters['owner'] = $ownerId !== '' ? array($ownerId) : array();
        $singleOwnerFilters['owner_user_id'] = $singleOwnerFilters['owner'];

        $ownerName = $ownerId;
        foreach ($this->Report_Model->Lead_Ownership_Agents($restrict) as $agent) {
            if ((string) $agent->agent_id === $ownerId) {
                $ownerName = $agent->agent_name;
                break;
            }
        }

        $rows = $ownerId !== '' ? $this->Report_Model->Lead_Reply_Activity_Hourly_By_Owner($singleOwnerFilters) : array();
        $avgReplySeconds = $ownerId !== '' ? $this->Report_Model->Lead_Reply_Activity_Hourly_Avg_Reply_Seconds_By_Owner($singleOwnerFilters) : null;

        return array(
            'owner_id' => $ownerId,
            'owner_name' => $ownerName,
            'breakdown' => ghl_message_log_hourly_breakdown($rows),
            'avg_reply_label' => ghl_message_log_format_duration($avgReplySeconds),
            'updated_at' => date('Y-m-d H:i:s'),
        );
    }

    private function lead_reply_activity_hourly_all_payload($filters)
    {
        $this->load->helper('ghl_messages_log');

        // Whole-team roll-up: never lock to a single owner. Any owner id the URL
        // carried is dropped; the level-20 self-restriction (_restrict_agent_ids)
        // and the team_lead filter still apply, so a restricted user only ever
        // sees their own row.
        $allAgentFilters = $filters;
        $allAgentFilters['owner'] = array();
        $allAgentFilters['owner_user_id'] = array();

        $rows = $this->Report_Model->Lead_Reply_Activity_Hourly_By_Agent($allAgentFilters);

        return array(
            'matrix' => ghl_message_log_hourly_agent_matrix($rows),
            'updated_at' => date('Y-m-d H:i:s'),
        );
    }

    private function lead_data_payload($filters)
    {
        $perPage = isset($filters['per_page']) ? max(10, (int) $filters['per_page']) : 25;
        $currentPage = isset($filters['page']) ? max(1, (int) $filters['page']) : 1;
        $offset = ($currentPage - 1) * $perPage;
        $totalRows = (int) $this->Report_Model->Lead_Data_Total_Count($filters);
        $restrict = isset($filters['_restrict_agent_ids']) ? $filters['_restrict_agent_ids'] : null;

        return array(
            'summary' => $this->format_lead_dashboard_summary($this->Report_Model->Lead_Dashboard_Summary($filters)),
            'rows' => $this->format_lead_data_rows($this->Report_Model->Lead_Data_Rows($filters, $perPage, $offset)),
            'agents' => $this->Report_Model->Lead_Dashboard_Agents($restrict),
            'tags' => $this->Report_Model->Lead_Data_Tag_Options($filters),
            'pagination' => $this->build_lead_data_pagination($filters, $currentPage, $perPage, $totalRows),
            'updated_at' => $this->Report_Model->Lead_Data_Last_Synced_At(),
            'sorting' => $this->build_lead_data_sorting($filters),
        );
    }

    private function lead_dashboard_filters()
    {
        $leadDate = trim((string) $this->input->get('lead_date'));
        $salesAgents = $this->normalize_id_array($this->input->get('sales_agent'));
        $teamLeads = $this->normalize_id_array($this->input->get('team_lead'));
        $parsedDates = $this->parse_report_date_range($leadDate, true);

        $restriction = $this->get_lead_dashboard_agent_restriction();
        if ($restriction !== null && !empty($salesAgents)) {
            $allowedSet = array_map('strval', $restriction);
            $salesAgents = array_values(array_intersect(array_map('strval', $salesAgents), $allowedSet));
        }

        $filters = array(
            'lead_date' => $leadDate !== '' ? $leadDate : $parsedDates['display'],
            'start_date' => $parsedDates['start_date'],
            'end_date' => $parsedDates['end_date'],
            'sales_agent' => $salesAgents,
            'agent_id' => $salesAgents,
            'team_lead' => $teamLeads,
        );

        if ($restriction !== null) {
            $filters['_restrict_agent_ids'] = $restriction;
        }

        return $filters;
    }

    private function lead_ownership_filters()
    {
        $leadDate = trim((string) $this->input->get('lead_date'));
        $owners = $this->normalize_id_array($this->input->get('owner'));
        $teamLeads = $this->normalize_id_array($this->input->get('team_lead'));
        $ownershipType = strtolower(trim((string) $this->input->get('ownership_type')));
        $parsedDates = $this->parse_report_date_range($leadDate, true);

        if (!in_array($ownershipType, array('assigned', 'reply'), true)) {
            $ownershipType = '';
        }

        $restriction = $this->get_lead_dashboard_agent_restriction();
        if ($restriction !== null && !empty($owners)) {
            $allowedSet = array_map('strval', $restriction);
            $owners = array_values(array_intersect(array_map('strval', $owners), $allowedSet));
        }

        $filters = array(
            'lead_date' => $leadDate !== '' ? $leadDate : $parsedDates['display'],
            'start_date' => $parsedDates['start_date'],
            'end_date' => $parsedDates['end_date'],
            'owner' => $owners,
            'owner_user_id' => $owners,
            'team_lead' => $teamLeads,
            'ownership_type' => $ownershipType,
        );

        if ($restriction !== null) {
            $filters['_restrict_agent_ids'] = $restriction;
        }

        return $filters;
    }

    private function lead_reply_activity_filters()
    {
        $replyDate = trim((string) $this->input->get('reply_date'));
        $owners = $this->normalize_id_array($this->input->get('owner'));
        $teamLeads = $this->normalize_id_array($this->input->get('team_lead'));
        $leadType = strtolower(trim((string) $this->input->get('lead_type')));
        $parsedDate = $this->parse_report_single_date($replyDate);

        if (!in_array($leadType, array('assigned', 'reply_created'), true)) {
            $leadType = '';
        }

        if (empty($parsedDate['date'])) {
            $today = date('Y-m-d');
            $parsedDate = array(
                'date' => $today,
                'display' => date('d/m/Y', strtotime($today)),
            );
        }

        $restriction = $this->get_lead_dashboard_agent_restriction();
        if ($restriction !== null && !empty($owners)) {
            $allowedSet = array_map('strval', $restriction);
            $owners = array_values(array_intersect(array_map('strval', $owners), $allowedSet));
        }

        $filters = array(
            'reply_date' => $parsedDate['display'],
            'start_date' => $parsedDate['date'],
            'end_date' => $parsedDate['date'],
            'owner' => $owners,
            'owner_user_id' => $owners,
            'team_lead' => $teamLeads,
            'lead_type' => $leadType,
        );

        if ($restriction !== null) {
            $filters['_restrict_agent_ids'] = $restriction;
        }

        return $filters;
    }

    private function lead_ownership_data_filters()
    {
        $filters = $this->lead_ownership_filters();
        $assignmentDate = trim((string) $this->input->get('assignment_date'));
        $parsedAssignmentDates = $this->parse_report_date_range($assignmentDate, false);
        $filters['assignment_date'] = $assignmentDate;
        $filters['assignment_start_date'] = $parsedAssignmentDates['start_date'];
        $filters['assignment_end_date'] = $parsedAssignmentDates['end_date'];
        $filters['follow_up_status'] = $this->normalize_follow_up_status($this->input->get('follow_up_status'));
        $filters['page'] = max(1, (int) $this->input->get('page'));
        $filters['per_page'] = $this->normalize_lead_data_per_page($this->input->get('per_page'));
        return $filters;
    }

    private function get_lead_dashboard_agent_restriction()
    {
        if ((string) $this->session->level === '10') {
            return null;
        }
        // Sales agents (level 20) see only their OWN reply activity: resolve
        // their own GHL identity (mapping table first, email fallback) rather
        // than the broader "agents this admin may view" set team leads get.
        if ((string) $this->session->level === '20') {
            return $this->Report_Model->Resolve_Self_Ghl_Agents($this->session->admin_id);
        }
        return $this->Report_Model->Get_Allowed_Lead_Dashboard_Agents($this->session->admin_id);
    }

    private function normalize_id_array($value)
    {
        if (is_array($value)) {
            $out = array();
            foreach ($value as $v) {
                $v = trim((string) $v);
                if ($v !== '') { $out[] = $v; }
            }
            return $out;
        }
        $value = trim((string) $value);
        return $value !== '' ? array($value) : array();
    }

    private function lead_data_filters()
    {
        $leadDate = trim((string) $this->input->get('lead_date'));
        $agentId = trim((string) $this->input->get('sales_agent'));
        $conversationId = trim((string) $this->input->get('conversation_id'));
        $contactName = trim((string) $this->input->get('contact_name'));
        $phone = trim((string) $this->input->get('phone'));
        $tag = trim((string) $this->input->get('tag'));
        $responseStatus = trim((string) $this->input->get('response_status'));
        $conversionStatus = trim((string) $this->input->get('conversion_status'));
        $parsedDates = $this->parse_report_date_range($leadDate, false);

        $restriction = $this->get_lead_dashboard_agent_restriction();
        if ($restriction !== null && $agentId !== '' && !in_array($agentId, array_map('strval', $restriction), true)) {
            $agentId = '';
        }

        $filters = array(
            'lead_date' => $leadDate,
            'start_date' => $parsedDates['start_date'],
            'end_date' => $parsedDates['end_date'],
            'sales_agent' => $agentId,
            'agent_id' => $agentId,
            'conversation_id' => $conversationId,
            'contact_name' => $contactName,
            'phone' => $phone,
            'tag' => $tag,
            'response_status' => $responseStatus,
            'conversion_status' => $conversionStatus,
            'page' => max(1, (int) $this->input->get('page')),
            'per_page' => $this->normalize_lead_data_per_page($this->input->get('per_page')),
            'sort_by' => $this->normalize_lead_data_sort_by($this->input->get('sort_by')),
            'sort_dir' => $this->normalize_lead_data_sort_dir($this->input->get('sort_dir')),
        );

        if ($restriction !== null) {
            $filters['_restrict_agent_ids'] = $restriction;
        }

        return $filters;
    }

    private function build_lead_data_sorting($filters)
    {
        $baseQuery = $filters;
        unset($baseQuery['start_date'], $baseQuery['end_date']);
        $currentSortBy = isset($filters['sort_by']) ? $filters['sort_by'] : 'lead_started_at';
        $currentSortDir = isset($filters['sort_dir']) ? $filters['sort_dir'] : 'desc';

        $buildSortUrl = function($sortBy) use ($baseQuery, $currentSortBy, $currentSortDir) {
            $params = $baseQuery;
            $params['sort_by'] = $sortBy;
            $params['sort_dir'] = ($currentSortBy === $sortBy && $currentSortDir === 'asc') ? 'desc' : 'asc';
            $params['page'] = 1;

            foreach ($params as $key => $value) {
                if ($value === '' || $value === null) {
                    unset($params[$key]);
                }
            }

            $queryString = http_build_query($params);
            return base_url('Report/Lead_Data') . ($queryString !== '' ? '?' . $queryString : '');
        };

        return array(
            'current_sort_by' => $currentSortBy,
            'current_sort_dir' => $currentSortDir,
            'links' => array(
                'contact_name' => $buildSortUrl('contact_name'),
                'agent_name' => $buildSortUrl('agent_name'),
                'conversation_id' => $buildSortUrl('conversation_id'),
                'lead_started_at' => $buildSortUrl('lead_started_at'),
                'response_status' => $buildSortUrl('response_status'),
                'response_time' => $buildSortUrl('response_time'),
                'conversion_status' => $buildSortUrl('conversion_status'),
                'follow_up_status' => $buildSortUrl('follow_up_status'),
                'message_count' => $buildSortUrl('message_count'),
            ),
        );
    }

    private function build_lead_data_pagination($filters, $currentPage, $perPage, $totalRows)
    {
        $totalPages = $perPage > 0 ? (int) ceil($totalRows / $perPage) : 1;
        $totalPages = max(1, $totalPages);
        $currentPage = min(max(1, (int) $currentPage), $totalPages);
        $startRow = $totalRows > 0 ? (($currentPage - 1) * $perPage) + 1 : 0;
        $endRow = $totalRows > 0 ? min($totalRows, $startRow + $perPage - 1) : 0;

        $query = $filters;
        unset($query['start_date'], $query['end_date']);

        $buildPageUrl = function($page) use ($query) {
            $params = $query;
            $params['page'] = max(1, (int) $page);

            foreach ($params as $key => $value) {
                if ($value === '' || $value === null) {
                    unset($params[$key]);
                }
            }

            $queryString = http_build_query($params);
            return base_url('Report/Lead_Data') . ($queryString !== '' ? '?' . $queryString : '');
        };

        $pages = array();
        $windowStart = max(1, $currentPage - 2);
        $windowEnd = min($totalPages, $currentPage + 2);

        for ($page = $windowStart; $page <= $windowEnd; $page++) {
            $pages[] = array(
                'page' => $page,
                'url' => $buildPageUrl($page),
                'is_current' => $page === $currentPage,
            );
        }

        return array(
            'current_page' => $currentPage,
            'per_page' => $perPage,
            'total_rows' => $totalRows,
            'total_pages' => $totalPages,
            'start_row' => $startRow,
            'end_row' => $endRow,
            'pages' => $pages,
            'has_previous' => $currentPage > 1,
            'has_next' => $currentPage < $totalPages,
            'previous_url' => $currentPage > 1 ? $buildPageUrl($currentPage - 1) : '',
            'next_url' => $currentPage < $totalPages ? $buildPageUrl($currentPage + 1) : '',
            'first_url' => $buildPageUrl(1),
            'last_url' => $buildPageUrl($totalPages),
        );
    }

    private function build_lead_ownership_data_pagination($filters, $currentPage, $perPage, $totalRows)
    {
        $totalPages = $perPage > 0 ? (int) ceil($totalRows / $perPage) : 1;
        $totalPages = max(1, $totalPages);
        $currentPage = min(max(1, (int) $currentPage), $totalPages);
        $startRow = $totalRows > 0 ? (($currentPage - 1) * $perPage) + 1 : 0;
        $endRow = $totalRows > 0 ? min($totalRows, $startRow + $perPage - 1) : 0;

        $query = $filters;
        unset($query['start_date'], $query['end_date'], $query['assignment_start_date'], $query['assignment_end_date'], $query['owner_user_id']);

        $buildPageUrl = function($page) use ($query) {
            $params = $query;
            $params['page'] = max(1, (int) $page);

            foreach ($params as $key => $value) {
                if ($value === '' || $value === null || $value === array()) {
                    unset($params[$key]);
                }
            }

            $queryString = http_build_query($params);
            return base_url('Report/Lead_Ownership_Data') . ($queryString !== '' ? '?' . $queryString : '');
        };

        $pages = array();
        $windowStart = max(1, $currentPage - 2);
        $windowEnd = min($totalPages, $currentPage + 2);

        for ($page = $windowStart; $page <= $windowEnd; $page++) {
            $pages[] = array(
                'page' => $page,
                'url' => $buildPageUrl($page),
                'is_current' => $page === $currentPage,
            );
        }

        return array(
            'current_page' => $currentPage,
            'per_page' => $perPage,
            'total_rows' => $totalRows,
            'total_pages' => $totalPages,
            'start_row' => $startRow,
            'end_row' => $endRow,
            'pages' => $pages,
            'has_previous' => $currentPage > 1,
            'has_next' => $currentPage < $totalPages,
            'previous_url' => $currentPage > 1 ? $buildPageUrl($currentPage - 1) : '',
            'next_url' => $currentPage < $totalPages ? $buildPageUrl($currentPage + 1) : '',
            'first_url' => $buildPageUrl(1),
            'last_url' => $buildPageUrl($totalPages),
        );
    }

    private function normalize_lead_data_per_page($value)
    {
        $allowed = array(25, 50, 100);
        $value = (int) $value;
        return in_array($value, $allowed, true) ? $value : 25;
    }

    private function normalize_follow_up_status($value)
    {
        $value = strtolower(trim((string) $value));
        $allowed = array('pending', 'sent', 'completed');
        return in_array($value, $allowed, true) ? $value : '';
    }

    private function normalize_lead_data_sort_by($value)
    {
        $allowed = array(
            'contact_name',
            'agent_name',
            'conversation_id',
            'lead_started_at',
            'response_status',
            'response_time',
            'conversion_status',
            'follow_up_status',
            'message_count',
        );

        $value = trim((string) $value);
        return in_array($value, $allowed, true) ? $value : 'lead_started_at';
    }

    private function normalize_lead_data_sort_dir($value)
    {
        $value = strtolower(trim((string) $value));
        return $value === 'asc' ? 'asc' : 'desc';
    }

    private function parse_report_date_range($leadDate, $useDefaultRange)
    {
        if ($leadDate !== '' && strpos($leadDate, ' - ') !== false) {
            $parts = explode(' - ', $leadDate);
            if (count($parts) === 2) {
                $startDate = date('Y-m-d', strtotime(str_replace('/', '-', trim($parts[0]))));
                $endDate = date('Y-m-d', strtotime(str_replace('/', '-', trim($parts[1]))));

                if ($startDate !== '1970-01-01' && $endDate !== '1970-01-01') {
                    return array(
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'display' => date('d/m/Y', strtotime($startDate)) . ' - ' . date('d/m/Y', strtotime($endDate)),
                    );
                }
            }
        }

        if (!$useDefaultRange) {
            return array(
                'start_date' => null,
                'end_date' => null,
                'display' => '',
            );
        }

        $startDate = date('Y-m-d', strtotime('-29 days'));
        $endDate = date('Y-m-d');

        return array(
            'start_date' => $startDate,
            'end_date' => $endDate,
            'display' => date('d/m/Y', strtotime($startDate)) . ' - ' . date('d/m/Y', strtotime($endDate)),
        );
    }

    /**
     * Resolve the date window for the Message Log. Honours a
     * "dd/mm/YYYY - dd/mm/YYYY" picker value, otherwise defaults to the last 7
     * days so the newest messages load quickly.
     */
    private function ghl_message_log_range($rangeInput)
    {
        if ($rangeInput !== '' && strpos($rangeInput, ' - ') !== false) {
            $parts = explode(' - ', $rangeInput);
            if (count($parts) === 2) {
                $startDate = date('Y-m-d', strtotime(str_replace('/', '-', trim($parts[0]))));
                $endDate = date('Y-m-d', strtotime(str_replace('/', '-', trim($parts[1]))));

                if ($startDate !== '1970-01-01' && $endDate !== '1970-01-01' && $startDate <= $endDate) {
                    return array(
                        'log_date' => date('d/m/Y', strtotime($startDate)) . ' - ' . date('d/m/Y', strtotime($endDate)),
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                    );
                }
            }
        }

        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime('-6 days'));

        return array(
            'log_date' => date('d/m/Y', strtotime($startDate)) . ' - ' . date('d/m/Y', strtotime($endDate)),
            'start_date' => $startDate,
            'end_date' => $endDate,
        );
    }

    private function parse_report_single_date($dateValue)
    {
        $dateValue = trim((string) $dateValue);

        if ($dateValue === '') {
            return array(
                'date' => null,
                'display' => '',
            );
        }

        if (strpos($dateValue, ' - ') !== false) {
            $parts = explode(' - ', $dateValue);
            $dateValue = trim($parts[0]);
        }

        $date = date('Y-m-d', strtotime(str_replace('/', '-', $dateValue)));
        if ($date === '1970-01-01') {
            return array(
                'date' => null,
                'display' => '',
            );
        }

        return array(
            'date' => $date,
            'display' => date('d/m/Y', strtotime($date)),
        );
    }

    private function format_lead_dashboard_summary($summary)
    {
        $avgResponseSeconds = array_key_exists('avg_combined_response_time_seconds', $summary)
            ? $summary['avg_combined_response_time_seconds']
            : $summary['avg_response_time_seconds'];

        return array(
            'total_leads' => (int) $summary['total_leads'],
            'responded_leads' => (int) $summary['responded_leads'],
            'converted_leads' => (int) $summary['converted_leads'],
            'active_agents' => (int) $summary['active_agents'],
            'response_rate' => number_format((float) $summary['response_rate'], 1),
            'conversion_rate' => number_format((float) $summary['conversion_rate'], 1),
            'avg_first_response_time_seconds' => $summary['avg_response_time_seconds'],
            'avg_first_response_time_label' => $this->format_duration_label($summary['avg_response_time_seconds']),
            'avg_response_time_seconds' => $avgResponseSeconds,
            'avg_response_time_label' => $this->format_duration_label($avgResponseSeconds),
            'avg_recent_response_time_seconds' => $summary['avg_recent_response_time_seconds'],
            'avg_recent_response_time_label' => $this->format_duration_label($summary['avg_recent_response_time_seconds']),
            'avg_responded_messages' => number_format((float) $summary['avg_responded_messages'], 1),
            'avg_recent_responded_messages' => number_format((float) $summary['avg_recent_responded_messages'], 1),
        );
    }

    private function format_lead_dashboard_rows($rows)
    {
        $formatted = array();

        foreach ($rows as $row) {
            $formatted[] = array(
                'agent_id' => $row['agent_id'],
                'agent_name' => $row['agent_name'],
                'total_leads' => (int) $row['total_leads'],
                'responded_leads' => (int) $row['responded_leads'],
                'converted_leads' => (int) $row['converted_leads'],
                'response_rate' => number_format((float) $row['response_rate'], 1),
                'conversion_rate' => number_format((float) $row['conversion_rate'], 1),
                'avg_first_response_time_seconds' => isset($row['avg_first_response_time_seconds']) ? $row['avg_first_response_time_seconds'] : null,
                'avg_first_response_time_label' => $this->format_duration_label(isset($row['avg_first_response_time_seconds']) ? $row['avg_first_response_time_seconds'] : null),
                'avg_response_time_seconds' => $row['avg_response_time_seconds'],
                'avg_response_time_label' => $this->format_duration_label($row['avg_response_time_seconds']),
                'avg_recent_response_time_seconds' => $row['avg_recent_response_time_seconds'],
                'avg_recent_response_time_label' => $this->format_duration_label($row['avg_recent_response_time_seconds']),
                'avg_responded_messages' => number_format((float) $row['avg_responded_messages'], 1),
                'avg_recent_responded_messages' => number_format((float) $row['avg_recent_responded_messages'], 1),
                'last_updated_at' => $row['last_updated_at'],
            );
        }

        return $formatted;
    }

    private function format_lead_ownership_summary($summary)
    {
        return array(
            'owned_leads' => (int) $summary['owned_leads'],
            'unique_leads' => (int) $summary['unique_leads'],
            'assigned_owned_leads' => (int) $summary['assigned_owned_leads'],
            'reply_owned_leads' => (int) $summary['reply_owned_leads'],
            'responded_leads' => (int) $summary['responded_leads'],
            'follow_up_leads' => (int) $summary['follow_up_leads'],
            'converted_leads' => (int) $summary['converted_leads'],
            'active_owners' => (int) $summary['active_owners'],
            'response_rate' => number_format((float) $summary['response_rate'], 1),
            'follow_up_rate' => number_format((float) $summary['follow_up_rate'], 1),
            'conversion_rate' => number_format((float) $summary['conversion_rate'], 1),
            'avg_first_response_time_seconds' => isset($summary['avg_first_response_time_seconds']) ? $summary['avg_first_response_time_seconds'] : null,
            'avg_first_response_time_label' => $this->format_duration_label(isset($summary['avg_first_response_time_seconds']) ? $summary['avg_first_response_time_seconds'] : null),
            'avg_response_time_seconds' => $summary['avg_response_time_seconds'],
            'avg_response_time_label' => $this->format_duration_label($summary['avg_response_time_seconds']),
            'avg_recent_response_time_seconds' => $summary['avg_recent_response_time_seconds'],
            'avg_recent_response_time_label' => $this->format_duration_label($summary['avg_recent_response_time_seconds']),
            'avg_responded_messages' => number_format((float) $summary['avg_responded_messages'], 1),
            'avg_recent_responded_messages' => number_format((float) $summary['avg_recent_responded_messages'], 1),
        );
    }

    private function format_lead_ownership_rows($rows)
    {
        $formatted = array();

        foreach ($rows as $row) {
            // The "Avg Response" column mirrors the card: the combined first-5 +
            // last-5 average, duty-clipped to the 7AM-10PM everyday window.
            $avgDisplayedResponseSeconds = $row['avg_response_time_seconds'];

            $formatted[] = array(
                'owner_user_id' => $row['owner_user_id'],
                'owner_name' => $row['owner_name'],
                'owned_leads' => (int) $row['owned_leads'],
                'unique_leads' => (int) $row['unique_leads'],
                'assigned_owned_leads' => (int) $row['assigned_owned_leads'],
                'reply_owned_leads' => (int) $row['reply_owned_leads'],
                'responded_leads' => (int) $row['responded_leads'],
                'follow_up_leads' => (int) $row['follow_up_leads'],
                'converted_leads' => (int) $row['converted_leads'],
                'response_rate' => number_format((float) $row['response_rate'], 1),
                'follow_up_rate' => number_format((float) $row['follow_up_rate'], 1),
                'conversion_rate' => number_format((float) $row['conversion_rate'], 1),
                'avg_first_response_time_seconds' => isset($row['avg_first_response_time_seconds']) ? $row['avg_first_response_time_seconds'] : null,
                'avg_first_response_time_label' => $this->format_duration_label(isset($row['avg_first_response_time_seconds']) ? $row['avg_first_response_time_seconds'] : null),
                'avg_response_time_seconds' => $row['avg_response_time_seconds'],
                'avg_response_time_label' => $this->format_duration_label($row['avg_response_time_seconds']),
                'avg_recent_response_time_seconds' => $row['avg_recent_response_time_seconds'],
                'avg_recent_response_time_label' => $this->format_duration_label($row['avg_recent_response_time_seconds']),
                'avg_displayed_response_time_seconds' => $avgDisplayedResponseSeconds,
                'avg_displayed_response_time_label' => $this->format_duration_label($avgDisplayedResponseSeconds),
                'avg_responded_messages' => number_format((float) $row['avg_responded_messages'], 1),
                'avg_recent_responded_messages' => number_format((float) $row['avg_recent_responded_messages'], 1),
                'last_calculated_at' => $row['last_calculated_at'],
            );
        }

        return $formatted;
    }

    private function format_lead_reply_activity_summary($summary)
    {
        return array(
            'assigned_leads' => (int) $summary['assigned_leads'],
            'reply_created_leads' => (int) $summary['reply_created_leads'],
            'active_owners' => (int) $summary['active_owners'],
        );
    }

    private function format_lead_reply_activity_rows($rows)
    {
        $formatted = array();

        foreach ($rows as $row) {
            $responded = (int) $row['lead_responded'];
            $transferOut = (int) $row['reply_created_leads'];
            $avgResponseSeconds = isset($row['avg_response_seconds']) && $row['avg_response_seconds'] !== null
                ? (int) $row['avg_response_seconds']
                : null;
            $formatted[] = array(
                'owner_user_id' => $row['owner_user_id'],
                'owner_name' => $row['owner_name'],
                'new_leads_picked_up' => (int) $row['assigned_leads'],
                'lead_responded' => $responded,
                'transfer_out_leads' => $transferOut,
                'today_handling_leads' => (int) $row['today_handling_leads'],
                'avg_response_time_seconds' => $avgResponseSeconds,
                'avg_response_time_label' => $this->format_duration_label($avgResponseSeconds),
            );
        }

        return $formatted;
    }

    private function format_lead_reply_activity_detail_summary($summary)
    {
        return array(
            'assigned_leads' => (int) $summary['assigned_leads'],
            'reply_created_leads' => (int) $summary['reply_created_leads'],
        );
    }

    private function format_lead_reply_activity_detail_rows($rows)
    {
        $formatted = array();

        foreach ($rows as $row) {
            $formatted[] = array(
                'processed_lead_id' => (int) $row['processed_lead_id'],
                'conversation_id' => $row['conversation_id'],
                'contact_id' => $row['contact_id'],
                'contact_name' => $row['contact_name'],
                'phone' => $row['phone'],
                'owner_user_id' => $row['owner_user_id'],
                'owner_name' => $row['owner_name'],
                'assigned_name' => $row['assigned_name'],
                'activity_type' => $row['activity_type'],
                'activity_type_class' => $row['activity_type'] === 'Assigned Lead' ? 'label-light-primary' : 'label-light-success',
                'activity_at' => $row['activity_at'],
                'activity_at_label' => !empty($row['activity_at']) ? date('d M Y h:i A', strtotime($row['activity_at'])) : '-',
                'lead_started_at' => $row['lead_started_at'],
                'lead_started_at_label' => !empty($row['lead_started_at']) ? date('d M Y h:i A', strtotime($row['lead_started_at'])) : '-',
                'reply_created_at' => $row['reply_created_at'],
                'reply_created_at_label' => !empty($row['reply_created_at']) ? date('d M Y h:i A', strtotime($row['reply_created_at'])) : '-',
                'outbound_replies' => (int) $row['outbound_replies'],
                'message_count' => (int) $row['message_count'],
                'follow_up_status_label' => $this->format_follow_up_status_label(isset($row['follow_up_status']) ? $row['follow_up_status'] : 'pending'),
                'follow_up_status_class' => $this->format_follow_up_status_class(isset($row['follow_up_status']) ? $row['follow_up_status'] : 'pending'),
                'conversion_status_label' => (int) $row['is_converted'] === 1 ? 'Converted' : 'Open',
                'booking_id' => !empty($row['booking_id']) ? (int) $row['booking_id'] : null,
                'booking_number' => isset($row['BookingNumber']) ? $row['BookingNumber'] : '',
                'booking_url' => !empty($row['booking_id']) ? base_url('Booking/View?booking_id=') . (int) $row['booking_id'] : '',
                'lead_data_url' => base_url('Report/Lead_Data?conversation_id=') . urlencode($row['conversation_id']),
            );
        }

        return $formatted;
    }

    private function format_lead_reply_activity_mobile_summary($summary)
    {
        return array(
            'conversation_count' => (int) $summary['conversation_count'],
            'lead_count' => (int) $summary['lead_count'],
            'message_count' => (int) $summary['message_count'],
            'inbound_message_count' => (int) $summary['inbound_message_count'],
            'outbound_message_count' => (int) $summary['outbound_message_count'],
            'owner_count' => (int) $summary['owner_count'],
            'first_message_at_label' => !empty($summary['first_message_at']) ? date('d M Y h:i A', strtotime($summary['first_message_at'])) : '-',
            'last_message_at_label' => !empty($summary['last_message_at']) ? date('d M Y h:i A', strtotime($summary['last_message_at'])) : '-',
        );
    }

    private function format_lead_reply_activity_mobile_leads($rows)
    {
        $formatted = array();

        foreach ($rows as $row) {
            $formatted[] = array(
                'processed_lead_id' => (int) $row['processed_lead_id'],
                'conversation_id' => $row['conversation_id'],
                'contact_name' => $row['contact_name'],
                'phone' => $row['phone'],
                'agent_name' => $row['agent_name'],
                'lead_started_at_label' => !empty($row['lead_started_at']) ? date('d M Y h:i A', strtotime($row['lead_started_at'])) : '-',
                'lead_ended_at_label' => !empty($row['lead_ended_at']) ? date('d M Y h:i A', strtotime($row['lead_ended_at'])) : '-',
                'responded_message_count' => (int) $row['responded_message_count'],
                'tracked_message_count' => (int) $row['tracked_message_count'],
                'message_count' => (int) $row['message_count'],
                'conversion_status_label' => (int) $row['is_converted'] === 1 ? 'Converted' : 'Open',
                'booking_number' => isset($row['BookingNumber']) ? $row['BookingNumber'] : '',
                'lead_data_url' => base_url('Report/Lead_Data?conversation_id=') . urlencode($row['conversation_id']),
            );
        }

        return $formatted;
    }

    private function format_lead_ownership_data_rows($rows)
    {
        $formatted = array();

        foreach ($rows as $row) {
            $isAssigned = (int) $row['is_assigned_owner'] === 1;

            $formatted[] = array(
                'id' => (int) $row['id'],
                'processed_lead_id' => (int) $row['processed_lead_id'],
                'conversation_id' => $row['conversation_id'],
                'contact_id' => $row['contact_id'],
                'contact_name' => $row['contact_name'],
                'phone' => $row['phone'],
                'owner_user_id' => $row['owner_user_id'],
                'owner_name' => $row['owner_name'],
                'assigned_to_user_id' => $row['assigned_to_user_id'],
                'assigned_name' => $row['assigned_name'],
                'assigned_at' => isset($row['assigned_at']) ? $row['assigned_at'] : null,
                'assigned_at_label' => !empty($row['assigned_at']) ? date('d M Y h:i A', strtotime($row['assigned_at'])) : '-',
                'ownership_label' => $isAssigned ? 'Assigned Owned' : 'Reply Owned',
                'ownership_class' => $isAssigned ? 'label-light-primary' : 'label-light-info',
                'is_assigned_owner' => (int) $row['is_assigned_owner'],
                'is_reply_owner' => (int) $row['is_reply_owner'],
                'outbound_reply_count' => (int) $row['outbound_reply_count'],
                'lead_started_at' => $row['lead_started_at'],
                'lead_ended_at' => $row['lead_ended_at'],
                'lead_started_at_label' => !empty($row['lead_started_at']) ? date('d M Y h:i A', strtotime($row['lead_started_at'])) : '-',
                'lead_ended_at_label' => !empty($row['lead_ended_at']) ? date('d M Y h:i A', strtotime($row['lead_ended_at'])) : '-',
                'tracked_message_count' => isset($row['tracked_message_count']) ? (int) $row['tracked_message_count'] : 0,
                'responded_message_count' => isset($row['responded_message_count']) ? (int) $row['responded_message_count'] : 0,
                'response_progress_label' => (isset($row['responded_message_count']) ? (int) $row['responded_message_count'] : 0) . ' / ' . (isset($row['tracked_message_count']) ? (int) $row['tracked_message_count'] : 0),
                'avg_first_5_response_seconds' => $row['avg_first_5_response_seconds'] !== null ? (int) $row['avg_first_5_response_seconds'] : null,
                'avg_first_5_response_label' => $this->format_duration_label($row['avg_first_5_response_seconds']),
                'recent_tracked_message_count' => isset($row['recent_tracked_message_count']) ? (int) $row['recent_tracked_message_count'] : 0,
                'recent_responded_message_count' => isset($row['recent_responded_message_count']) ? (int) $row['recent_responded_message_count'] : 0,
                'recent_response_progress_label' => (isset($row['recent_responded_message_count']) ? (int) $row['recent_responded_message_count'] : 0) . ' / ' . (isset($row['recent_tracked_message_count']) ? (int) $row['recent_tracked_message_count'] : 0),
                'avg_recent_5_response_seconds' => $row['avg_recent_5_response_seconds'] !== null ? (int) $row['avg_recent_5_response_seconds'] : null,
                'avg_recent_5_response_label' => $this->format_duration_label($row['avg_recent_5_response_seconds']),
                'follow_up_status' => isset($row['follow_up_status']) ? (string) $row['follow_up_status'] : 'pending',
                'follow_up_status_label' => $this->format_follow_up_status_label(isset($row['follow_up_status']) ? $row['follow_up_status'] : 'pending'),
                'follow_up_status_class' => $this->format_follow_up_status_class(isset($row['follow_up_status']) ? $row['follow_up_status'] : 'pending'),
                'is_converted' => (int) $row['is_converted'],
                'booking_id' => !empty($row['booking_id']) ? (int) $row['booking_id'] : null,
                'booking_number' => isset($row['BookingNumber']) ? $row['BookingNumber'] : '',
                'booking_url' => !empty($row['booking_id']) ? base_url('Booking/View?booking_id=') . (int) $row['booking_id'] : '',
                'converted_at' => $row['converted_at'],
                'converted_at_label' => !empty($row['converted_at']) ? date('d M Y h:i A', strtotime($row['converted_at'])) : '-',
                'calculated_at' => $row['calculated_at'],
                'lead_data_url' => base_url('Report/Lead_Data?conversation_id=') . urlencode($row['conversation_id']),
            );
        }

        return $formatted;
    }

    private function format_lead_data_rows($rows)
    {
        $formatted = array();

        foreach ($rows as $row) {
            $formatted[] = array(
                'id' => (int) $row['id'],
                'conversation_id' => $row['conversation_id'],
                'contact_id' => $row['contact_id'],
                'contact_name' => $row['contact_name'],
                'phone' => $row['phone'],
                'tags' => $this->format_lead_data_tags(isset($row['tags_json']) ? $row['tags_json'] : null),
                'agent_id' => $row['agent_id'],
                'agent_name' => $row['agent_name'],
                'lead_started_at' => $row['lead_started_at'],
                'lead_ended_at' => $row['lead_ended_at'],
                'lead_started_at_label' => !empty($row['lead_started_at']) ? date('d M Y h:i A', strtotime($row['lead_started_at'])) : '-',
                'lead_ended_at_label' => !empty($row['lead_ended_at']) ? date('d M Y h:i A', strtotime($row['lead_ended_at'])) : '-',
                'first_customer_message_id' => $row['first_customer_message_id'],
                'tracked_message_count' => isset($row['tracked_message_count']) ? (int) $row['tracked_message_count'] : 0,
                'responded_message_count' => isset($row['responded_message_count']) ? (int) $row['responded_message_count'] : 0,
                'response_progress_label' => (isset($row['responded_message_count']) ? (int) $row['responded_message_count'] : 0) . ' / ' . (isset($row['tracked_message_count']) ? (int) $row['tracked_message_count'] : 0),
                'avg_first_5_response_seconds' => $row['avg_first_5_response_seconds'] !== null ? (int) $row['avg_first_5_response_seconds'] : null,
                'avg_first_5_response_label' => $this->format_duration_label($row['avg_first_5_response_seconds']),
                'recent_tracked_message_count' => isset($row['recent_tracked_message_count']) ? (int) $row['recent_tracked_message_count'] : 0,
                'recent_responded_message_count' => isset($row['recent_responded_message_count']) ? (int) $row['recent_responded_message_count'] : 0,
                'recent_response_progress_label' => (isset($row['recent_responded_message_count']) ? (int) $row['recent_responded_message_count'] : 0) . ' / ' . (isset($row['recent_tracked_message_count']) ? (int) $row['recent_tracked_message_count'] : 0),
                'avg_recent_5_response_seconds' => $row['avg_recent_5_response_seconds'] !== null ? (int) $row['avg_recent_5_response_seconds'] : null,
                'avg_recent_5_response_label' => $this->format_duration_label($row['avg_recent_5_response_seconds']),
                'follow_up_status' => isset($row['follow_up_status']) ? (string) $row['follow_up_status'] : 'pending',
                'follow_up_status_label' => $this->format_follow_up_status_label(isset($row['follow_up_status']) ? $row['follow_up_status'] : 'pending'),
                'follow_up_status_class' => $this->format_follow_up_status_class(isset($row['follow_up_status']) ? $row['follow_up_status'] : 'pending'),
                'follow_up_sent_at' => isset($row['follow_up_sent_at']) ? $row['follow_up_sent_at'] : null,
                'follow_up_replied_at' => isset($row['follow_up_replied_at']) ? $row['follow_up_replied_at'] : null,
                'follow_up_expired_at' => isset($row['follow_up_expired_at']) ? $row['follow_up_expired_at'] : null,
                'follow_up_sent_at_label' => !empty($row['follow_up_sent_at']) ? date('d M Y h:i A', strtotime($row['follow_up_sent_at'])) : '-',
                'follow_up_replied_at_label' => !empty($row['follow_up_replied_at']) ? date('d M Y h:i A', strtotime($row['follow_up_replied_at'])) : '-',
                'follow_up_expired_at_label' => !empty($row['follow_up_expired_at']) ? date('d M Y h:i A', strtotime($row['follow_up_expired_at'])) : '-',
                'response_1_pair_label' => $this->format_response_pair_label(isset($row['response_1_agent_message_at']) ? $row['response_1_agent_message_at'] : null, isset($row['response_1_customer_message_at']) ? $row['response_1_customer_message_at'] : null),
                'response_2_pair_label' => $this->format_response_pair_label(isset($row['response_2_agent_message_at']) ? $row['response_2_agent_message_at'] : null, isset($row['response_2_customer_message_at']) ? $row['response_2_customer_message_at'] : null),
                'response_3_pair_label' => $this->format_response_pair_label(isset($row['response_3_agent_message_at']) ? $row['response_3_agent_message_at'] : null, isset($row['response_3_customer_message_at']) ? $row['response_3_customer_message_at'] : null),
                'response_4_pair_label' => $this->format_response_pair_label(isset($row['response_4_agent_message_at']) ? $row['response_4_agent_message_at'] : null, isset($row['response_4_customer_message_at']) ? $row['response_4_customer_message_at'] : null),
                'response_5_pair_label' => $this->format_response_pair_label(isset($row['response_5_agent_message_at']) ? $row['response_5_agent_message_at'] : null, isset($row['response_5_customer_message_at']) ? $row['response_5_customer_message_at'] : null),
                'recent_response_1_pair_label' => $this->format_response_pair_label(isset($row['recent_response_1_agent_message_at']) ? $row['recent_response_1_agent_message_at'] : null, isset($row['recent_response_1_customer_message_at']) ? $row['recent_response_1_customer_message_at'] : null),
                'recent_response_2_pair_label' => $this->format_response_pair_label(isset($row['recent_response_2_agent_message_at']) ? $row['recent_response_2_agent_message_at'] : null, isset($row['recent_response_2_customer_message_at']) ? $row['recent_response_2_customer_message_at'] : null),
                'recent_response_3_pair_label' => $this->format_response_pair_label(isset($row['recent_response_3_agent_message_at']) ? $row['recent_response_3_agent_message_at'] : null, isset($row['recent_response_3_customer_message_at']) ? $row['recent_response_3_customer_message_at'] : null),
                'recent_response_4_pair_label' => $this->format_response_pair_label(isset($row['recent_response_4_agent_message_at']) ? $row['recent_response_4_agent_message_at'] : null, isset($row['recent_response_4_customer_message_at']) ? $row['recent_response_4_customer_message_at'] : null),
                'recent_response_5_pair_label' => $this->format_response_pair_label(isset($row['recent_response_5_agent_message_at']) ? $row['recent_response_5_agent_message_at'] : null, isset($row['recent_response_5_customer_message_at']) ? $row['recent_response_5_customer_message_at'] : null),
                'response_1_seconds' => $row['response_1_seconds'] !== null ? (int) $row['response_1_seconds'] : null,
                'response_2_seconds' => $row['response_2_seconds'] !== null ? (int) $row['response_2_seconds'] : null,
                'response_3_seconds' => $row['response_3_seconds'] !== null ? (int) $row['response_3_seconds'] : null,
                'response_4_seconds' => $row['response_4_seconds'] !== null ? (int) $row['response_4_seconds'] : null,
                'response_5_seconds' => $row['response_5_seconds'] !== null ? (int) $row['response_5_seconds'] : null,
                'recent_response_1_seconds' => $row['recent_response_1_seconds'] !== null ? (int) $row['recent_response_1_seconds'] : null,
                'recent_response_2_seconds' => $row['recent_response_2_seconds'] !== null ? (int) $row['recent_response_2_seconds'] : null,
                'recent_response_3_seconds' => $row['recent_response_3_seconds'] !== null ? (int) $row['recent_response_3_seconds'] : null,
                'recent_response_4_seconds' => $row['recent_response_4_seconds'] !== null ? (int) $row['recent_response_4_seconds'] : null,
                'recent_response_5_seconds' => $row['recent_response_5_seconds'] !== null ? (int) $row['recent_response_5_seconds'] : null,
                'response_1_label' => $this->format_duration_label($row['response_1_seconds']),
                'response_2_label' => $this->format_duration_label($row['response_2_seconds']),
                'response_3_label' => $this->format_duration_label($row['response_3_seconds']),
                'response_4_label' => $this->format_duration_label($row['response_4_seconds']),
                'response_5_label' => $this->format_duration_label($row['response_5_seconds']),
                'recent_response_1_label' => $this->format_duration_label($row['recent_response_1_seconds']),
                'recent_response_2_label' => $this->format_duration_label($row['recent_response_2_seconds']),
                'recent_response_3_label' => $this->format_duration_label($row['recent_response_3_seconds']),
                'recent_response_4_label' => $this->format_duration_label($row['recent_response_4_seconds']),
                'recent_response_5_label' => $this->format_duration_label($row['recent_response_5_seconds']),
                'is_converted' => (int) $row['is_converted'],
                'conversion_status_label' => (int) $row['is_converted'] === 1 ? 'Converted' : 'Open',
                'booking_id' => !empty($row['booking_id']) ? (int) $row['booking_id'] : null,
                'booking_number' => isset($row['BookingNumber']) ? $row['BookingNumber'] : '',
                'booking_url' => !empty($row['booking_id']) ? base_url('Booking/View?booking_id=') . (int) $row['booking_id'] : '',
                'converted_at' => $row['converted_at'],
                'converted_at_label' => !empty($row['converted_at']) ? date('d M Y h:i A', strtotime($row['converted_at'])) : '-',
                'message_count' => isset($row['message_count']) ? (int) $row['message_count'] : 0,
            );
        }

        return $formatted;
    }

    private function format_lead_data_tags($tagsJson)
    {
        if ($tagsJson === null || $tagsJson === '') {
            return array();
        }

        $decoded = json_decode($tagsJson, true);
        if (!is_array($decoded)) {
            return array();
        }

        $tags = array();
        foreach ($decoded as $tag) {
            if (is_array($tag)) {
                if (isset($tag['name'])) {
                    $tag = $tag['name'];
                } elseif (isset($tag['tag'])) {
                    $tag = $tag['tag'];
                } else {
                    continue;
                }
            }

            $tag = trim((string) $tag);
            if ($tag !== '') {
                $tags[$tag] = $tag;
            }
        }

        return array_values($tags);
    }

    private function format_follow_up_status_label($status)
    {
        $status = strtolower(trim((string) $status));
        $labels = array(
            'pending' => 'Pending',
            'sent' => 'Sent',
            'completed' => 'Completed',
            'expired' => 'Sent',
        );

        return isset($labels[$status]) ? $labels[$status] : 'Pending';
    }

    private function format_follow_up_status_class($status)
    {
        $status = strtolower(trim((string) $status));
        $classes = array(
            'pending' => 'label-light-warning',
            'sent' => 'label-light-info',
            'completed' => 'label-light-success',
            'expired' => 'label-light-info',
        );

        return isset($classes[$status]) ? $classes[$status] : 'label-light-warning';
    }

    private function format_duration_label($seconds)
    {
        if ($seconds === null || $seconds === '') {
            return '-';
        }

        $seconds = (int) $seconds;

        if ($seconds < 60) {
            return $seconds . ' sec';
        }

        if ($seconds < 3600) {
            return floor($seconds / 60) . ' min';
        }

        if ($seconds < 86400) {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);

            if ($minutes === 0) {
                return $hours . ' hr';
            }

            return $hours . ' hr ' . $minutes . ' min';
        }

        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);

        if ($hours === 0) {
            return $days . ' day';
        }

        return $days . ' day ' . $hours . ' hr';
    }

    private function format_response_pair_label($agentAt, $customerAt)
    {
        if (empty($agentAt) && empty($customerAt)) {
            return '-';
        }

        $agentLabel = !empty($agentAt) ? date('d M Y h:i A', strtotime($agentAt)) : '-';
        $customerLabel = !empty($customerAt) ? date('d M Y h:i A', strtotime($customerAt)) : '-';

        return 'Agent ' . $agentLabel . ' - Customer ' . $customerLabel;
    }
}
