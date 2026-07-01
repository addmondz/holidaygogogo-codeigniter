<?php
class Report_Model extends CI_Model
{
    protected $messageTimeColumn = null;

    // Request-level caches so the same expensive work is not repeated within one page load.
    protected $replyCreatedBaseRowsCache = array();
    protected $assignmentDateExpressionCache = null;

    private function get_processed_lead_response_slot_select($alias = 'pl')
    {
        $prefix = $alias !== '' ? $alias . '.' : '';
        $parts = array();

        for ($i = 1; $i <= 5; $i++) {
            $parts[] = "{$prefix}response_{$i}_agent_message_id AS aid{$i}";
            $parts[] = "{$prefix}response_{$i}_customer_message_at AS ct{$i}";
            $parts[] = "{$prefix}response_{$i}_agent_message_at AS at{$i}";
            $parts[] = "{$prefix}response_{$i}_seconds AS s{$i}";
        }

        for ($i = 1; $i <= 5; $i++) {
            $parts[] = "{$prefix}recent_response_{$i}_agent_message_id AS raid{$i}";
            $parts[] = "{$prefix}recent_response_{$i}_customer_message_at AS rct{$i}";
            $parts[] = "{$prefix}recent_response_{$i}_agent_message_at AS rat{$i}";
            $parts[] = "{$prefix}recent_response_{$i}_seconds AS rs{$i}";
        }

        return implode(",\n                ", $parts);
    }

    private function calculate_combined_response_averages($slotRows, $groupKey = null, $useDutyHours = false)
    {
        if ($useDutyHours) {
            $this->load->helper('duty_hours');
        }

        $stats = array();

        foreach ((array) $slotRows as $sr) {
            $bucket = $groupKey !== null
                ? (isset($sr[$groupKey]) ? (string) $sr[$groupKey] : '')
                : '__all__';

            if (!isset($stats[$bucket])) {
                $stats[$bucket] = array('total' => 0, 'count' => 0);
            }

            $seen = array();

            for ($i = 1; $i <= 5; $i++) {
                $secs = isset($sr['s' . $i]) ? $sr['s' . $i] : null;
                if ($secs === null || $secs === '') continue;

                $seconds = $useDutyHours
                    ? calculate_duty_response_seconds($sr['ct' . $i], $sr['at' . $i])
                    : (int) $secs;
                if ($seconds === null) continue;

                $aid = isset($sr['aid' . $i]) ? $sr['aid' . $i] : null;
                if ($aid !== null && $aid !== '') $seen[$aid] = true;

                $stats[$bucket]['total'] += (int) $seconds;
                $stats[$bucket]['count']++;
            }

            for ($i = 1; $i <= 5; $i++) {
                $secs = isset($sr['rs' . $i]) ? $sr['rs' . $i] : null;
                if ($secs === null || $secs === '') continue;

                $aid = isset($sr['raid' . $i]) ? $sr['raid' . $i] : null;
                if ($aid !== null && $aid !== '') {
                    if (isset($seen[$aid])) continue;
                    $seen[$aid] = true;
                }

                $seconds = $useDutyHours
                    ? calculate_duty_response_seconds($sr['rct' . $i], $sr['rat' . $i])
                    : (int) $secs;
                if ($seconds === null) continue;

                $stats[$bucket]['total'] += (int) $seconds;
                $stats[$bucket]['count']++;
            }
        }

        $averages = array();
        foreach ($stats as $bucket => $stat) {
            $averages[$bucket] = $stat['count'] > 0
                ? (int) round($stat['total'] / $stat['count'])
                : null;
        }

        return $averages;
    }

    // The Lead Ownership "Average Response" card + column use a dedicated
    // response window (7AM-10PM, everyday) that is kept independent of the
    // global duty-hours constants so it can be tuned on its own.
    private function ownership_duty_window()
    {
        return array('days' => array(1, 2, 3, 4, 5, 6, 7), 'start_hour' => 7, 'end_hour' => 22);
    }

    // Recomputes the first-5 / last-5 / combined average response seconds from
    // the raw slot timestamps, clipped to the ownership duty window. Returns a
    // map of bucket => array('first' => ?int, 'recent' => ?int, 'combined' => ?int),
    // where the bucket is $groupKey's value (owner_user_id) or '__all__' overall.
    //
    //   first    - the first-5 reply slots
    //   recent   - the most-recent-5 reply slots, counted on their own
    //   combined - first-5 merged with recent-5, deduped by agent_message_id so
    //              short leads (whose first/recent slots overlap) are not
    //              double-counted. This is the figure the card + column show.
    private function calculate_ownership_duty_response_averages($slotRows, $groupKey = null)
    {
        $this->load->helper('duty_hours');
        $window = $this->ownership_duty_window();
        $stats = array();

        foreach ((array) $slotRows as $sr) {
            $bucket = $groupKey !== null
                ? (isset($sr[$groupKey]) ? (string) $sr[$groupKey] : '')
                : '__all__';

            if (!isset($stats[$bucket])) {
                $stats[$bucket] = array(
                    'first_total' => 0, 'first_count' => 0,
                    'recent_total' => 0, 'recent_count' => 0,
                    'combined_total' => 0, 'combined_count' => 0,
                );
            }

            $seen = array();

            for ($i = 1; $i <= 5; $i++) {
                $secs = isset($sr['s' . $i]) ? $sr['s' . $i] : null;
                if ($secs === null || $secs === '') continue;

                $seconds = calculate_duty_response_seconds($sr['ct' . $i], $sr['at' . $i], $window);
                if ($seconds === null) continue;

                $aid = isset($sr['aid' . $i]) ? $sr['aid' . $i] : null;
                if ($aid !== null && $aid !== '') $seen[$aid] = true;

                $stats[$bucket]['first_total'] += (int) $seconds;
                $stats[$bucket]['first_count']++;
                $stats[$bucket]['combined_total'] += (int) $seconds;
                $stats[$bucket]['combined_count']++;
            }

            for ($i = 1; $i <= 5; $i++) {
                $secs = isset($sr['rs' . $i]) ? $sr['rs' . $i] : null;
                if ($secs === null || $secs === '') continue;

                $seconds = calculate_duty_response_seconds($sr['rct' . $i], $sr['rat' . $i], $window);
                if ($seconds === null) continue;

                // Last-5 average counts every recent slot regardless of overlap.
                $stats[$bucket]['recent_total'] += (int) $seconds;
                $stats[$bucket]['recent_count']++;

                // Combined dedupes against the first-5 set.
                $aid = isset($sr['raid' . $i]) ? $sr['raid' . $i] : null;
                if ($aid !== null && $aid !== '') {
                    if (isset($seen[$aid])) continue;
                    $seen[$aid] = true;
                }

                $stats[$bucket]['combined_total'] += (int) $seconds;
                $stats[$bucket]['combined_count']++;
            }
        }

        $averages = array();
        foreach ($stats as $bucket => $stat) {
            $averages[$bucket] = array(
                'first' => $stat['first_count'] > 0 ? (int) round($stat['first_total'] / $stat['first_count']) : null,
                'recent' => $stat['recent_count'] > 0 ? (int) round($stat['recent_total'] / $stat['recent_count']) : null,
                'combined' => $stat['combined_count'] > 0 ? (int) round($stat['combined_total'] / $stat['combined_count']) : null,
            );
        }

        return $averages;
    }

    // Slot-timestamp SELECT joined through to ghl_processed_leads so the
    // ownership duty-window averages can be recomputed at query time. Shares the
    // ownership WHERE clause so it always covers the same rows as the summary.
    private function ownership_response_slot_rows($where, $groupKey = null)
    {
        $extraJoins = isset($where['extra_joins']) ? $where['extra_joins'] : '';
        $slotSelect = $this->get_processed_lead_response_slot_select('pl');
        $bucketSelect = $groupKey === 'owner_user_id' ? "glo.owner_user_id AS owner_user_id,\n                " : '';

        $sql = "
            SELECT
                {$bucketSelect}{$slotSelect}
            FROM ghl_lead_ownership glo
            LEFT JOIN ghl_users gu ON gu.UserID = glo.owner_user_id
            INNER JOIN ghl_processed_leads pl ON pl.id = glo.processed_lead_id
            {$extraJoins}
            {$where['sql']}
        ";

        return $this->calculate_ownership_duty_response_averages(
            $this->db->query($sql, $where['params'])->result_array(),
            $groupKey
        );
    }

	function Destination_Profits()
	{
		$this->db->select('MIN(EndDate) As Month, SUM(Credit) - SUM(Debit) As Profit, category.Name As Destination');
		$this->db->join('payment', 'payment.BookingID = booking.BookingID', 'left');
		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
        if(!empty($this->input->get('destination'))) {
            $this->db->where('Destination', $this->input->get('destination'));
        }
        if(!empty($this->input->get('sales_agent'))) {
            $this->db->where('SalesAgent', $this->input->get('sales_agent'));
        }
        if(!empty($this->input->get('sales_agent_2'))) {
            $this->db->where('SalesAgent2', $this->input->get('sales_agent_2'));
        }
        if(!empty($this->input->get('travel_date'))) {
            $travel_date = explode(' - ', $this->input->get('travel_date'));
            $start_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[0])));
            $end_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[1])));
            $this->db->where("((`StartDate` <= '".$start_date."' AND `EndDate` >= '".$end_date."') OR (`StartDate` >= '".$start_date."' AND `StartDate` <= '".$end_date."') OR (`EndDate` >= '".$start_date."' AND `EndDate` <= '".$end_date."'))");
        } else {
            $this->db->where("((`StartDate` <= '".date('Y-m-1')."' AND `EndDate` >= '".date('Y-m-t')."') OR (`StartDate` >= '".date('Y-m-1')."' AND `StartDate` <= '".date('Y-m-t')."') OR (`EndDate` >= '".date('Y-m-1')."' AND `EndDate` <= '".date('Y-m-t')."'))");
        }
        $this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');
        $this->db->where('CancelStatus', 'N');
        $this->db->where('AfterSalesService', 'COMPLETE');
        $this->db->where('booking.Status', 'Y');
        $this->db->where_in('payment.Status', array('Y', 'P'));
        $this->db->group_by('Destination');
        $this->db->group_by('YEAR(EndDate)');
        $this->db->group_by('MONTH(EndDate)');
        $this->db->order_by('YEAR(EndDate)', 'ASC');
        $this->db->order_by('MONTH(EndDate)', 'ASC');
        $this->db->order_by('Destination', 'ASC');
        $destination_profits = $this->db->get('booking');
        return $destination_profits->result();
	}

    function Destination_Net_Totals()
	{
		$this->db->select('SUM(NetTotal) As NetTotal');
        $this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
        if(!empty($this->input->get('destination'))) {
            $this->db->where('Destination', $this->input->get('destination'));
        }
        if(!empty($this->input->get('sales_agent'))) {
            $this->db->where('SalesAgent', $this->input->get('sales_agent'));
        }
        if(!empty($this->input->get('sales_agent_2'))) {
            $this->db->where('SalesAgent2', $this->input->get('sales_agent_2'));
        }
        if(!empty($this->input->get('travel_date'))) {
            $travel_date = explode(' - ', $this->input->get('travel_date'));
            $start_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[0])));
            $end_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[1])));
            $this->db->where("((`StartDate` <= '".$start_date."' AND `EndDate` >= '".$end_date."') OR (`StartDate` >= '".$start_date."' AND `StartDate` <= '".$end_date."') OR (`EndDate` >= '".$start_date."' AND `EndDate` <= '".$end_date."'))");
        } else {
            $this->db->where("((`StartDate` <= '".date('Y-m-1')."' AND `EndDate` >= '".date('Y-m-t')."') OR (`StartDate` >= '".date('Y-m-1')."' AND `StartDate` <= '".date('Y-m-t')."') OR (`EndDate` >= '".date('Y-m-1')."' AND `EndDate` <= '".date('Y-m-t')."'))");
        }
        $this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');
        $this->db->where('CancelStatus', 'N');
        $this->db->where('AfterSalesService', 'COMPLETE');
        $this->db->where('booking.Status', 'Y');
        $this->db->group_by('category.Name');
        $this->db->group_by('YEAR(EndDate)');
        $this->db->group_by('MONTH(EndDate)');
        $this->db->order_by('YEAR(EndDate)', 'ASC');
        $this->db->order_by('MONTH(EndDate)', 'ASC');
        $this->db->order_by('category.Name', 'ASC');
        $destination_net_totals = $this->db->get('booking');
        return $destination_net_totals->result();
	}

    function City_Profits()
	{
		$this->db->select('MIN(EndDate) As Month, SUM(Credit) - SUM(Debit) As Profit, City');
		$this->db->join('payment', 'payment.BookingID = booking.BookingID', 'left');
		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
        $this->db->join('country_code', 'country_code.CountryCodeID = category.Country', 'left');
        if(!empty($this->input->get('city'))) {
            $this->db->where('City', $this->input->get('city'));
        }
        if(!empty($this->input->get('state'))) {
            $this->db->where('State', $this->input->get('state'));
        }
        if(!empty($this->input->get('country'))) {
            $this->db->where('category.Country', $this->input->get('country'));
        }
        if(!empty($this->input->get('sales_agent'))) {
            $this->db->where('SalesAgent', $this->input->get('sales_agent'));
        }
        if(!empty($this->input->get('sales_agent_2'))) {
            $this->db->where('SalesAgent2', $this->input->get('sales_agent_2'));
        }
        if(!empty($this->input->get('travel_date'))) {
            $travel_date = explode(' - ', $this->input->get('travel_date'));
            $start_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[0])));
            $end_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[1])));
            $this->db->where("((`StartDate` <= '".$start_date."' AND `EndDate` >= '".$end_date."') OR (`StartDate` >= '".$start_date."' AND `StartDate` <= '".$end_date."') OR (`EndDate` >= '".$start_date."' AND `EndDate` <= '".$end_date."'))");
        } else {
            $this->db->where("((`StartDate` <= '".date('Y-m-1')."' AND `EndDate` >= '".date('Y-m-t')."') OR (`StartDate` >= '".date('Y-m-1')."' AND `StartDate` <= '".date('Y-m-t')."') OR (`EndDate` >= '".date('Y-m-1')."' AND `EndDate` <= '".date('Y-m-t')."'))");
        }
        $this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');
        $this->db->where('CancelStatus', 'N');
        $this->db->where('AfterSalesService', 'COMPLETE');
        $this->db->where('booking.Status', 'Y');
        $this->db->where_in('payment.Status', array('Y', 'P'));
        $this->db->where('City !=', '');
        $this->db->group_by('City');
        $this->db->group_by('YEAR(EndDate)');
        $this->db->group_by('MONTH(EndDate)');
        $this->db->order_by('YEAR(EndDate)', 'ASC');
        $this->db->order_by('MONTH(EndDate)', 'ASC');
        $this->db->order_by('City', 'ASC');
        $city_profits = $this->db->get('booking');
        return $city_profits->result();
	}

    function City_Net_Totals()
	{
		$this->db->select('SUM(NetTotal) As NetTotal');
        $this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
        $this->db->join('country_code', 'country_code.CountryCodeID = category.Country', 'left');
        if(!empty($this->input->get('city'))) {
            $this->db->where('City', $this->input->get('city'));
        }
        if(!empty($this->input->get('state'))) {
            $this->db->where('State', $this->input->get('state'));
        }
        if(!empty($this->input->get('country'))) {
            $this->db->where('category.Country', $this->input->get('country'));
        }
        if(!empty($this->input->get('sales_agent'))) {
            $this->db->where('SalesAgent', $this->input->get('sales_agent'));
        }
        if(!empty($this->input->get('sales_agent_2'))) {
            $this->db->where('SalesAgent2', $this->input->get('sales_agent_2'));
        }
        if(!empty($this->input->get('travel_date'))) {
            $travel_date = explode(' - ', $this->input->get('travel_date'));
            $start_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[0])));
            $end_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[1])));
            $this->db->where("((`StartDate` <= '".$start_date."' AND `EndDate` >= '".$end_date."') OR (`StartDate` >= '".$start_date."' AND `StartDate` <= '".$end_date."') OR (`EndDate` >= '".$start_date."' AND `EndDate` <= '".$end_date."'))");
        } else {
            $this->db->where("((`StartDate` <= '".date('Y-m-1')."' AND `EndDate` >= '".date('Y-m-t')."') OR (`StartDate` >= '".date('Y-m-1')."' AND `StartDate` <= '".date('Y-m-t')."') OR (`EndDate` >= '".date('Y-m-1')."' AND `EndDate` <= '".date('Y-m-t')."'))");
        }
        $this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');
        $this->db->where('CancelStatus', 'N');
        $this->db->where('AfterSalesService', 'COMPLETE');
        $this->db->where('booking.Status', 'Y');
        $this->db->where('City !=', '');
        $this->db->group_by('City');
        $this->db->group_by('YEAR(EndDate)');
        $this->db->group_by('MONTH(EndDate)');
        $this->db->order_by('YEAR(EndDate)', 'ASC');
        $this->db->order_by('MONTH(EndDate)', 'ASC');
        $this->db->order_by('City', 'ASC');
        $city_net_totals = $this->db->get('booking');
        return $city_net_totals->result();
	}

    function State_Profits()
	{
		$this->db->select('MIN(EndDate) As Month, SUM(Credit) - SUM(Debit) As Profit, State');
		$this->db->join('payment', 'payment.BookingID = booking.BookingID', 'left');
		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
        $this->db->join('country_code', 'country_code.CountryCodeID = category.Country', 'left');
        if(!empty($this->input->get('state'))) {
            $this->db->where('State', $this->input->get('state'));
        }
        if(!empty($this->input->get('country'))) {
            $this->db->where('category.Country', $this->input->get('country'));
        }
        if(!empty($this->input->get('sales_agent'))) {
            $this->db->where('SalesAgent', $this->input->get('sales_agent'));
        }
        if(!empty($this->input->get('sales_agent_2'))) {
            $this->db->where('SalesAgent2', $this->input->get('sales_agent_2'));
        }
        if(!empty($this->input->get('travel_date'))) {
            $travel_date = explode(' - ', $this->input->get('travel_date'));
            $start_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[0])));
            $end_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[1])));
            $this->db->where("((`StartDate` <= '".$start_date."' AND `EndDate` >= '".$end_date."') OR (`StartDate` >= '".$start_date."' AND `StartDate` <= '".$end_date."') OR (`EndDate` >= '".$start_date."' AND `EndDate` <= '".$end_date."'))");
        } else {
            $this->db->where("((`StartDate` <= '".date('Y-m-1')."' AND `EndDate` >= '".date('Y-m-t')."') OR (`StartDate` >= '".date('Y-m-1')."' AND `StartDate` <= '".date('Y-m-t')."') OR (`EndDate` >= '".date('Y-m-1')."' AND `EndDate` <= '".date('Y-m-t')."'))");
        }
        $this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');
        $this->db->where('CancelStatus', 'N');
        $this->db->where('AfterSalesService', 'COMPLETE');
        $this->db->where('booking.Status', 'Y');
        $this->db->where_in('payment.Status', array('Y', 'P'));
        $this->db->where('State !=', '');
        $this->db->group_by('State');
        $this->db->group_by('YEAR(EndDate)');
        $this->db->group_by('MONTH(EndDate)');
        $this->db->order_by('YEAR(EndDate)', 'ASC');
        $this->db->order_by('MONTH(EndDate)', 'ASC');
        $this->db->order_by('State', 'ASC');
        $state_profits = $this->db->get('booking');
        return $state_profits->result();
	}

    function State_Net_Totals()
	{
		$this->db->select('SUM(NetTotal) As NetTotal');
        $this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
        $this->db->join('country_code', 'country_code.CountryCodeID = category.Country', 'left');
        if(!empty($this->input->get('state'))) {
            $this->db->where('State', $this->input->get('state'));
        }
        if(!empty($this->input->get('country'))) {
            $this->db->where('category.Country', $this->input->get('country'));
        }
        if(!empty($this->input->get('sales_agent'))) {
            $this->db->where('SalesAgent', $this->input->get('sales_agent'));
        }
        if(!empty($this->input->get('sales_agent_2'))) {
            $this->db->where('SalesAgent2', $this->input->get('sales_agent_2'));
        }
        if(!empty($this->input->get('travel_date'))) {
            $travel_date = explode(' - ', $this->input->get('travel_date'));
            $start_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[0])));
            $end_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[1])));
            $this->db->where("((`StartDate` <= '".$start_date."' AND `EndDate` >= '".$end_date."') OR (`StartDate` >= '".$start_date."' AND `StartDate` <= '".$end_date."') OR (`EndDate` >= '".$start_date."' AND `EndDate` <= '".$end_date."'))");
        } else {
            $this->db->where("((`StartDate` <= '".date('Y-m-1')."' AND `EndDate` >= '".date('Y-m-t')."') OR (`StartDate` >= '".date('Y-m-1')."' AND `StartDate` <= '".date('Y-m-t')."') OR (`EndDate` >= '".date('Y-m-1')."' AND `EndDate` <= '".date('Y-m-t')."'))");
        }
        $this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');
        $this->db->where('CancelStatus', 'N');
        $this->db->where('AfterSalesService', 'COMPLETE');
        $this->db->where('booking.Status', 'Y');
        $this->db->where('State !=', '');
        $this->db->group_by('State');
        $this->db->group_by('YEAR(EndDate)');
        $this->db->group_by('MONTH(EndDate)');
        $this->db->order_by('YEAR(EndDate)', 'ASC');
        $this->db->order_by('MONTH(EndDate)', 'ASC');
        $this->db->order_by('State', 'ASC');
        $state_net_totals = $this->db->get('booking');
        return $state_net_totals->result();
	}

    function Country_Profits()
	{
		$this->db->select('MIN(EndDate) As Month, SUM(Credit) - SUM(Debit) As Profit, country_code.Country');
		$this->db->join('payment', 'payment.BookingID = booking.BookingID', 'left');
		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
        $this->db->join('country_code', 'country_code.CountryCodeID = category.Country', 'left');
        if(!empty($this->input->get('country'))) {
            $this->db->where('category.Country', $this->input->get('country'));
        }
        if(!empty($this->input->get('sales_agent'))) {
            $this->db->where('SalesAgent', $this->input->get('sales_agent'));
        }
        if(!empty($this->input->get('sales_agent_2'))) {
            $this->db->where('SalesAgent2', $this->input->get('sales_agent_2'));
        }
        if(!empty($this->input->get('travel_date'))) {
            $travel_date = explode(' - ', $this->input->get('travel_date'));
            $start_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[0])));
            $end_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[1])));
            $this->db->where("((`StartDate` <= '".$start_date."' AND `EndDate` >= '".$end_date."') OR (`StartDate` >= '".$start_date."' AND `StartDate` <= '".$end_date."') OR (`EndDate` >= '".$start_date."' AND `EndDate` <= '".$end_date."'))");
        } else {
            $this->db->where("((`StartDate` <= '".date('Y-m-1')."' AND `EndDate` >= '".date('Y-m-t')."') OR (`StartDate` >= '".date('Y-m-1')."' AND `StartDate` <= '".date('Y-m-t')."') OR (`EndDate` >= '".date('Y-m-1')."' AND `EndDate` <= '".date('Y-m-t')."'))");
        }
        $this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');
        $this->db->where('CancelStatus', 'N');
        $this->db->where('AfterSalesService', 'COMPLETE');
        $this->db->where('booking.Status', 'Y');
        $this->db->where_in('payment.Status', array('Y', 'P'));
        $this->db->group_by('country_code.Country');
        $this->db->group_by('YEAR(EndDate)');
        $this->db->group_by('MONTH(EndDate)');
        $this->db->order_by('YEAR(EndDate)', 'ASC');
        $this->db->order_by('MONTH(EndDate)', 'ASC');
        $this->db->order_by('country_code.Country', 'ASC');
        $country_profits = $this->db->get('booking');
        return $country_profits->result();
	}

    function Country_Net_Totals()
	{
		$this->db->select('SUM(NetTotal) As NetTotal');
        $this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
        $this->db->join('country_code', 'country_code.CountryCodeID = category.Country', 'left');
        if(!empty($this->input->get('country'))) {
            $this->db->where('category.Country', $this->input->get('country'));
        }
        if(!empty($this->input->get('sales_agent'))) {
            $this->db->where('SalesAgent', $this->input->get('sales_agent'));
        }
        if(!empty($this->input->get('sales_agent_2'))) {
            $this->db->where('SalesAgent2', $this->input->get('sales_agent_2'));
        }
        if(!empty($this->input->get('travel_date'))) {
            $travel_date = explode(' - ', $this->input->get('travel_date'));
            $start_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[0])));
            $end_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[1])));
            $this->db->where("((`StartDate` <= '".$start_date."' AND `EndDate` >= '".$end_date."') OR (`StartDate` >= '".$start_date."' AND `StartDate` <= '".$end_date."') OR (`EndDate` >= '".$start_date."' AND `EndDate` <= '".$end_date."'))");
        } else {
            $this->db->where("((`StartDate` <= '".date('Y-m-1')."' AND `EndDate` >= '".date('Y-m-t')."') OR (`StartDate` >= '".date('Y-m-1')."' AND `StartDate` <= '".date('Y-m-t')."') OR (`EndDate` >= '".date('Y-m-1')."' AND `EndDate` <= '".date('Y-m-t')."'))");
        }
        $this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');
        $this->db->where('CancelStatus', 'N');
        $this->db->where('AfterSalesService', 'COMPLETE');
        $this->db->where('booking.Status', 'Y');
        $this->db->group_by('country_code.Country');
        $this->db->group_by('YEAR(EndDate)');
        $this->db->group_by('MONTH(EndDate)');
        $this->db->order_by('YEAR(EndDate)', 'ASC');
        $this->db->order_by('MONTH(EndDate)', 'ASC');
        $this->db->order_by('country_code.Country', 'ASC');
        $country_net_totals = $this->db->get('booking');
        return $country_net_totals->result();
	}

    function Product_Sales()
	{
		$this->db->select('MIN(EndDate) As Month, SUM(Quantity) As Quantity, SUM(Total) As NetTotal, product.ProductCode, product.Name As Product, SUM(Total) - (SupplierPrice * SUM(Quantity)) As Profit');
		$this->db->join('booking_product', 'booking_product.BookingID = booking.BookingID', 'left');
        $this->db->join('product', 'product.ProductID = booking_product.ProductID', 'left');
        if(!empty($this->input->get('product'))) {
            $this->db->where('product.ProductID', $this->input->get('product'));
        }
        if(!empty($this->input->get('sales_agent'))) {
            $this->db->where('SalesAgent', $this->input->get('sales_agent'));
        }
        if(!empty($this->input->get('sales_agent_2'))) {
            $this->db->where('SalesAgent2', $this->input->get('sales_agent_2'));
        }
        if(!empty($this->input->get('travel_date'))) {
            $travel_date = explode(' - ', $this->input->get('travel_date'));
            $start_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[0])));
            $end_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[1])));
            $this->db->where("((`StartDate` <= '".$start_date."' AND `EndDate` >= '".$end_date."') OR (`StartDate` >= '".$start_date."' AND `StartDate` <= '".$end_date."') OR (`EndDate` >= '".$start_date."' AND `EndDate` <= '".$end_date."'))");
        } else {
            $this->db->where("((`StartDate` <= '".date('Y-m-1')."' AND `EndDate` >= '".date('Y-m-t')."') OR (`StartDate` >= '".date('Y-m-1')."' AND `StartDate` <= '".date('Y-m-t')."') OR (`EndDate` >= '".date('Y-m-1')."' AND `EndDate` <= '".date('Y-m-t')."'))");
        }
        $this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');
        $this->db->where('CancelStatus', 'N');
        $this->db->where('AfterSalesService', 'COMPLETE');
        $this->db->where('booking.Status', 'Y');
        $this->db->where('booking_product.Status', 'Y');
        $this->db->group_by('product.Name');
        $this->db->group_by('YEAR(EndDate)');
        $this->db->group_by('MONTH(EndDate)');
        $this->db->order_by('YEAR(EndDate)', 'ASC');
        $this->db->order_by('MONTH(EndDate)', 'ASC');
        $this->db->order_by('product.Name', 'ASC');
        $product_profits = $this->db->get('booking');
        return $product_profits->result();
	}

    /*
    function Product_Discounts()
	{
		$this->db->select('booking.BookingID, Discount');
        $this->db->join('booking_product', 'booking_product.BookingID = booking.BookingID', 'left');
        if(!empty($this->input->get('product'))) {
            $this->db->where('booking_product.ProductID', $this->input->get('product'));
        }
        if(!empty($this->input->get('sales_agent'))) {
            $this->db->where('SalesAgent', $this->input->get('sales_agent'));
        }
        if(!empty($this->input->get('sales_agent_2'))) {
            $this->db->where('SalesAgent2', $this->input->get('sales_agent_2'));
        }
        if(!empty($this->input->get('travel_date'))) {
            $travel_date = explode(' - ', $this->input->get('travel_date'));
            $start_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[0])));
            $end_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[1])));
            $this->db->where("((`StartDate` <= '".$start_date."' AND `EndDate` >= '".$end_date."') OR (`StartDate` >= '".$start_date."' AND `StartDate` <= '".$end_date."') OR (`EndDate` >= '".$start_date."' AND `EndDate` <= '".$end_date."'))");
        } else {
            $this->db->where("((`StartDate` <= '".date('Y-m-1')."' AND `EndDate` >= '".date('Y-m-t')."') OR (`StartDate` >= '".date('Y-m-1')."' AND `StartDate` <= '".date('Y-m-t')."') OR (`EndDate` >= '".date('Y-m-1')."' AND `EndDate` <= '".date('Y-m-t')."'))");
        }
        $this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');
        $this->db->where('CancelStatus', 'N');
        $this->db->where('AfterSalesService', 'COMPLETE');
        $this->db->where('booking.Status', 'Y');
        $this->db->where('booking_product.Status', 'Y');
        $product_discounts = $this->db->get('booking');
        return $product_discounts->result();
	}
    */

    function BC_By_Source()
	{
		$this->db->select('MIN(EndDate) As Month, SUM(Credit) - SUM(Debit) As Profit, source.Name As Source');
		$this->db->join('payment', 'payment.BookingID = booking.BookingID', 'left');
		$this->db->join('source', 'source.SourceID = booking.Source', 'left');
        if(!empty($this->input->get('source'))) {
            $this->db->where('Source', $this->input->get('source'));
        }
        if(!empty($this->input->get('sales_agent'))) {
            $this->db->where('SalesAgent', $this->input->get('sales_agent'));
        }
        if(!empty($this->input->get('sales_agent_2'))) {
            $this->db->where('SalesAgent2', $this->input->get('sales_agent_2'));
        }
        if(!empty($this->input->get('travel_date'))) {
            $travel_date = explode(' - ', $this->input->get('travel_date'));
            $start_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[0])));
            $end_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[1])));
            $this->db->where("((`StartDate` <= '".$start_date."' AND `EndDate` >= '".$end_date."') OR (`StartDate` >= '".$start_date."' AND `StartDate` <= '".$end_date."') OR (`EndDate` >= '".$start_date."' AND `EndDate` <= '".$end_date."'))");
        } else {
            $this->db->where("((`StartDate` <= '".date('Y-m-1')."' AND `EndDate` >= '".date('Y-m-t')."') OR (`StartDate` >= '".date('Y-m-1')."' AND `StartDate` <= '".date('Y-m-t')."') OR (`EndDate` >= '".date('Y-m-1')."' AND `EndDate` <= '".date('Y-m-t')."'))");
        }
        $this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');
        $this->db->where('CancelStatus', 'N');
        $this->db->where('AfterSalesService', 'COMPLETE');
        $this->db->where('booking.Status', 'Y');
        $this->db->where_in('payment.Status', array('Y', 'P'));
        $this->db->group_by('source.Name');
        $this->db->group_by('YEAR(EndDate)');
        $this->db->group_by('MONTH(EndDate)');
        $this->db->order_by('YEAR(EndDate)', 'ASC');
        $this->db->order_by('MONTH(EndDate)', 'ASC');
        $this->db->order_by('source.Name', 'ASC');
        $bc_by_source = $this->db->get('booking');
        return $bc_by_source->result();
	}

    function BC_By_Source_Net_Totals()
	{
		$this->db->select('COUNT(BookingID) As TotalBC, SUM(NetTotal) As NetTotal');
        $this->db->join('source', 'source.SourceID = booking.Source', 'left');
        if(!empty($this->input->get('source'))) {
            $this->db->where('Source', $this->input->get('source'));
        }
        if(!empty($this->input->get('sales_agent'))) {
            $this->db->where('SalesAgent', $this->input->get('sales_agent'));
        }
        if(!empty($this->input->get('sales_agent_2'))) {
            $this->db->where('SalesAgent2', $this->input->get('sales_agent_2'));
        }
        if(!empty($this->input->get('travel_date'))) {
            $travel_date = explode(' - ', $this->input->get('travel_date'));
            $start_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[0])));
            $end_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[1])));
            $this->db->where("((`StartDate` <= '".$start_date."' AND `EndDate` >= '".$end_date."') OR (`StartDate` >= '".$start_date."' AND `StartDate` <= '".$end_date."') OR (`EndDate` >= '".$start_date."' AND `EndDate` <= '".$end_date."'))");
        } else {
            $this->db->where("((`StartDate` <= '".date('Y-m-1')."' AND `EndDate` >= '".date('Y-m-t')."') OR (`StartDate` >= '".date('Y-m-1')."' AND `StartDate` <= '".date('Y-m-t')."') OR (`EndDate` >= '".date('Y-m-1')."' AND `EndDate` <= '".date('Y-m-t')."'))");
        }
        $this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');
        $this->db->where('CancelStatus', 'N');
        $this->db->where('AfterSalesService', 'COMPLETE');
        $this->db->where('booking.Status', 'Y');
        $this->db->group_by('source.Name');
        $this->db->group_by('YEAR(EndDate)');
        $this->db->group_by('MONTH(EndDate)');
        $this->db->order_by('YEAR(EndDate)', 'ASC');
        $this->db->order_by('MONTH(EndDate)', 'ASC');
        $this->db->order_by('source.Name', 'ASC');
        $bc_by_source_net_totals = $this->db->get('booking');
        return $bc_by_source_net_totals->result();
	}

    function Guest_By_Country()
	{
		$this->db->select('MIN(EndDate) As Month, SUM(Adult) As TotalAdult, SUM(Children) As TotalChildren, SUM(Infant) As TotalInfant, country_code.Country');
		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
        $this->db->join('country_code', 'country_code.CountryCodeID = category.Country', 'left');
        if(!empty($this->input->get('country'))) {
            $this->db->where('category.Country', $this->input->get('country'));
        }
        if(!empty($this->input->get('sales_agent'))) {
            $this->db->where('SalesAgent', $this->input->get('sales_agent'));
        }
        if(!empty($this->input->get('sales_agent_2'))) {
            $this->db->where('SalesAgent2', $this->input->get('sales_agent_2'));
        }
        if(!empty($this->input->get('travel_date'))) {
            $travel_date = explode(' - ', $this->input->get('travel_date'));
            $start_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[0])));
            $end_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[1])));
            $this->db->where("((`StartDate` <= '".$start_date."' AND `EndDate` >= '".$end_date."') OR (`StartDate` >= '".$start_date."' AND `StartDate` <= '".$end_date."') OR (`EndDate` >= '".$start_date."' AND `EndDate` <= '".$end_date."'))");
        } else {
            $this->db->where("((`StartDate` <= '".date('Y-m-1')."' AND `EndDate` >= '".date('Y-m-t')."') OR (`StartDate` >= '".date('Y-m-1')."' AND `StartDate` <= '".date('Y-m-t')."') OR (`EndDate` >= '".date('Y-m-1')."' AND `EndDate` <= '".date('Y-m-t')."'))");
        }
        $this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');
        $this->db->where('CancelStatus', 'N');
        $this->db->where('AfterSalesService', 'COMPLETE');
        $this->db->where('booking.Status', 'Y');
        $this->db->group_by('country_code.Country');
        $this->db->group_by('YEAR(EndDate)');
        $this->db->group_by('MONTH(EndDate)');
        $this->db->order_by('YEAR(EndDate)', 'ASC');
        $this->db->order_by('MONTH(EndDate)', 'ASC');
        $this->db->order_by('country_code.Country', 'ASC');
        $guest_by_country = $this->db->get('booking');
        return $guest_by_country->result();
	}

    function Payments($booking_id)
	{
		$this->db->select('Credit, Debit, payment.Status');
        $this->db->where('payment.BookingID', $booking_id);
        $this->db->where('payment.Status !=', 'N');
        return $this->db->get('payment')->result();
	}

    function Lead_Dashboard_Summary($filters = array())
    {
        $this->load->helper('lead_conversion_credit');
        $where = $this->build_lead_dashboard_where_clause($filters);

        $extraJoins = isset($where['extra_joins']) ? $where['extra_joins'] : '';
        $creditFragment = lead_conversion_credit_sql_fragment();
        $activeBooking = lead_conversion_active_booking_sql('pl');
        $sql = "
            SELECT
                COUNT(*) AS total_leads,
                SUM(CASE WHEN pl.responded_message_count > 0 THEN 1 ELSE 0 END) AS responded_leads,
                AVG(pl.avg_first_5_response_seconds) AS avg_response_time_seconds,
                AVG(pl.avg_recent_5_response_seconds) AS avg_recent_response_time_seconds,
                AVG(pl.responded_message_count) AS avg_responded_messages,
                AVG(pl.recent_responded_message_count) AS avg_recent_responded_messages,
                SUM(CASE WHEN pl.is_converted = 1 AND pl.booking_id IS NOT NULL AND {$creditFragment} AND {$activeBooking} THEN 1 ELSE 0 END) AS converted_leads,
                COUNT(DISTINCT NULLIF(pl.assigned_to_user_id, '')) AS active_agents
            FROM ghl_processed_leads pl
            LEFT JOIN ghl_conversations gc ON gc.conversation_id = pl.conversation_id
            {$extraJoins}
            {$where['sql']}
        ";

        $row = $this->db->query($sql, $where['params'])->row_array();
        $totalLeads = !empty($row['total_leads']) ? (int) $row['total_leads'] : 0;
        $respondedLeads = !empty($row['responded_leads']) ? (int) $row['responded_leads'] : 0;
        $convertedLeads = !empty($row['converted_leads']) ? (int) $row['converted_leads'] : 0;

        // Duty-hour-aware avg first-response time. Computed at query time from
        // the per-slot timestamps so the duty-hour window can be changed
        // (see duty_hours_helper.php) without re-running the cron.
        //
        // Two figures come out of one slot pass:
        //   avg_response_time_seconds          - the first-5 reply slots only
        //   avg_combined_response_time_seconds - first-5 merged with the
        //       most-recent-5 reply slots, deduped by agent_message_id so a
        //       short lead (whose first-5 and recent-5 overlap) is not
        //       double-counted. This powers the TC "My Response Time" card.
        $this->load->helper('duty_hours');
        $slotSql = "
            SELECT
                pl.response_1_agent_message_id AS aid1, pl.response_1_customer_message_at AS ct1, pl.response_1_agent_message_at AS at1, pl.response_1_seconds AS s1,
                pl.response_2_agent_message_id AS aid2, pl.response_2_customer_message_at AS ct2, pl.response_2_agent_message_at AS at2, pl.response_2_seconds AS s2,
                pl.response_3_agent_message_id AS aid3, pl.response_3_customer_message_at AS ct3, pl.response_3_agent_message_at AS at3, pl.response_3_seconds AS s3,
                pl.response_4_agent_message_id AS aid4, pl.response_4_customer_message_at AS ct4, pl.response_4_agent_message_at AS at4, pl.response_4_seconds AS s4,
                pl.response_5_agent_message_id AS aid5, pl.response_5_customer_message_at AS ct5, pl.response_5_agent_message_at AS at5, pl.response_5_seconds AS s5,
                pl.recent_response_1_agent_message_id AS raid1, pl.recent_response_1_customer_message_at AS rct1, pl.recent_response_1_agent_message_at AS rat1, pl.recent_response_1_seconds AS rs1,
                pl.recent_response_2_agent_message_id AS raid2, pl.recent_response_2_customer_message_at AS rct2, pl.recent_response_2_agent_message_at AS rat2, pl.recent_response_2_seconds AS rs2,
                pl.recent_response_3_agent_message_id AS raid3, pl.recent_response_3_customer_message_at AS rct3, pl.recent_response_3_agent_message_at AS rat3, pl.recent_response_3_seconds AS rs3,
                pl.recent_response_4_agent_message_id AS raid4, pl.recent_response_4_customer_message_at AS rct4, pl.recent_response_4_agent_message_at AS rat4, pl.recent_response_4_seconds AS rs4,
                pl.recent_response_5_agent_message_id AS raid5, pl.recent_response_5_customer_message_at AS rct5, pl.recent_response_5_agent_message_at AS rat5, pl.recent_response_5_seconds AS rs5
            FROM ghl_processed_leads pl
            LEFT JOIN ghl_conversations gc ON gc.conversation_id = pl.conversation_id
            {$extraJoins}
            {$where['sql']}
        ";
        $slotRows = $this->db->query($slotSql, $where['params'])->result_array();
        $dutyTotal = 0;
        $dutyCount = 0;
        $combinedTotal = 0;
        $combinedCount = 0;
        foreach ($slotRows as $sr) {
            $seen = array();
            // First-5 slots: feed both the first-5-only figure and the merged one.
            for ($i = 1; $i <= 5; $i++) {
                $secs = $sr['s' . $i];
                if ($secs === null || $secs === '') continue;
                $dutySeconds = calculate_duty_response_seconds($sr['ct' . $i], $sr['at' . $i]);
                if ($dutySeconds === null) continue;
                $dutyTotal += (int) $dutySeconds;
                $dutyCount++;

                $aid = $sr['aid' . $i];
                if ($aid !== null && $aid !== '') $seen[$aid] = true;
                $combinedTotal += (int) $dutySeconds;
                $combinedCount++;
            }
            // Recent-5 slots: merged figure only, skipping messages already
            // counted above (overlap on short leads).
            for ($i = 1; $i <= 5; $i++) {
                $secs = $sr['rs' . $i];
                if ($secs === null || $secs === '') continue;
                $aid = $sr['raid' . $i];
                if ($aid !== null && $aid !== '') {
                    if (isset($seen[$aid])) continue;
                    $seen[$aid] = true;
                }
                $dutySeconds = calculate_duty_response_seconds($sr['rct' . $i], $sr['rat' . $i]);
                if ($dutySeconds === null) continue;
                $combinedTotal += (int) $dutySeconds;
                $combinedCount++;
            }
        }
        $avgResponseSeconds = $dutyCount > 0
            ? (int) round($dutyTotal / $dutyCount)
            : null;
        $avgCombinedResponseSeconds = $combinedCount > 0
            ? (int) round($combinedTotal / $combinedCount)
            : null;
        $avgRecentResponseSeconds = isset($row['avg_recent_response_time_seconds']) && $row['avg_recent_response_time_seconds'] !== null
            ? (int) round($row['avg_recent_response_time_seconds'])
            : null;
        $avgRespondedMessages = isset($row['avg_responded_messages']) && $row['avg_responded_messages'] !== null
            ? round((float) $row['avg_responded_messages'], 1)
            : 0.0;
        $avgRecentRespondedMessages = isset($row['avg_recent_responded_messages']) && $row['avg_recent_responded_messages'] !== null
            ? round((float) $row['avg_recent_responded_messages'], 1)
            : 0.0;

        return array(
            'total_leads' => $totalLeads,
            'responded_leads' => $respondedLeads,
            'avg_response_time_seconds' => $avgResponseSeconds,
            'avg_combined_response_time_seconds' => $avgCombinedResponseSeconds,
            'avg_recent_response_time_seconds' => $avgRecentResponseSeconds,
            'avg_responded_messages' => $avgRespondedMessages,
            'avg_recent_responded_messages' => $avgRecentRespondedMessages,
            'converted_leads' => $convertedLeads,
            'active_agents' => !empty($row['active_agents']) ? (int) $row['active_agents'] : 0,
            'response_rate' => $totalLeads > 0 ? round(($respondedLeads / $totalLeads) * 100, 1) : 0.0,
            'conversion_rate' => $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 1) : 0.0,
        );
    }

    /**
     * "Lead Pickup Speed" summary for one set of agents in one window.
     *
     * Pickup speed = RAW wall-clock seconds from when a lead started a brand-new
     * conversation (pl.lead_started_at) to the agent's FIRST reply
     * (pl.response_1_agent_message_at). Unlike Lead_Dashboard_Summary's
     * avg_response_time_seconds, this is the single first-touch gap (not the mean
     * of the first 5 reply gaps) and is NOT duty-hours adjusted -- it reflects
     * how long the customer actually waited for someone to pick the lead up.
     *
     * Only leads that were actually picked up (response_1_agent_message_at not
     * null, valid start anchor, non-negative gap) are averaged; un-replied leads
     * are ignored rather than counted as infinitely slow. The windowing /
     * agent / restriction filters are shared with the rest of the lead dashboard
     * via build_lead_dashboard_where_clause (windows by pl.lead_started_at).
     *
     * @param array $filters  Same shape as Lead_Dashboard_Summary (agent_id,
     *                         start_date, end_date, _restrict_agent_ids, ...).
     * @return array { avg_seconds: int|null, count: int }
     */
    function Lead_Pickup_Speed_Summary($filters = array())
    {
        $where = $this->build_lead_dashboard_where_clause($filters);
        $extraJoins = isset($where['extra_joins']) ? $where['extra_joins'] : '';

        // Conditional aggregation keeps the shared WHERE untouched: non-qualifying
        // rows fall to NULL inside AVG() (ignored) and 0 inside the SUM() count.
        $gap  = 'UNIX_TIMESTAMP(pl.response_1_agent_message_at) - UNIX_TIMESTAMP(pl.lead_started_at)';
        $qual = "pl.response_1_agent_message_at IS NOT NULL
                 AND pl.lead_started_at IS NOT NULL
                 AND ({$gap}) >= 0";
        $sql = "
            SELECT
                AVG(CASE WHEN {$qual} THEN ({$gap}) END) AS avg_seconds,
                SUM(CASE WHEN {$qual} THEN 1 ELSE 0 END) AS n
            FROM ghl_processed_leads pl
            LEFT JOIN ghl_conversations gc ON gc.conversation_id = pl.conversation_id
            {$extraJoins}
            {$where['sql']}
        ";
        $row = $this->db->query($sql, $where['params'])->row_array();

        return array(
            'avg_seconds' => (isset($row['avg_seconds']) && $row['avg_seconds'] !== null)
                ? (int) round((float) $row['avg_seconds'])
                : null,
            'count' => !empty($row['n']) ? (int) $row['n'] : 0,
        );
    }

    /**
     * Fastest-picking-up agent team-wide in a window -- powers the "Best:" footer
     * on the Lead Pickup Speed card. Groups by the GHL assigned agent, requires
     * at least 2 qualifying leads (min-sample guard shared with the Draft ->
     * Payment Time card so a single lucky lead can't top the board), and returns
     * the lowest (fastest) average. Name resolves from ghl_users.Name, falling
     * back to the raw UID.
     *
     * @param string $start_date  'Y-m-d'
     * @param string $end_date    'Y-m-d'
     * @return array|null { agent_id, agent_name, avg_seconds, n } or null.
     */
    function Lead_Pickup_Speed_Best_Agent($start_date, $end_date)
    {
        $gap = 'UNIX_TIMESTAMP(pl.response_1_agent_message_at) - UNIX_TIMESTAMP(pl.lead_started_at)';
        $sql = "
            SELECT
                NULLIF(pl.assigned_to_user_id, '') AS agent_id,
                COALESCE(NULLIF(gu.Name, ''), NULLIF(pl.assigned_to_user_id, '')) AS agent_name,
                AVG({$gap}) AS avg_seconds,
                COUNT(*) AS n
            FROM ghl_processed_leads pl
            LEFT JOIN ghl_users gu ON gu.UserID = NULLIF(pl.assigned_to_user_id, '')
            WHERE NULLIF(pl.assigned_to_user_id, '') IS NOT NULL
              AND pl.lead_started_at >= ? AND pl.lead_started_at <= ?
              AND pl.response_1_agent_message_at IS NOT NULL
              AND pl.lead_started_at IS NOT NULL
              AND ({$gap}) >= 0
            GROUP BY agent_id, agent_name
            HAVING n >= 2
            ORDER BY avg_seconds ASC
            LIMIT 1
        ";
        $row = $this->db->query($sql, array(
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59',
        ))->row_array();

        if (empty($row)) { return null; }
        return array(
            'agent_id'    => $row['agent_id'],
            'agent_name'  => $row['agent_name'],
            'avg_seconds' => (int) round((float) $row['avg_seconds']),
            'n'           => (int) $row['n'],
        );
    }

    /**
     * Per-agent pickup speed for a window -- one row per GHL assigned agent,
     * not just the fastest. Same gap definition and qualification rules as
     * Lead_Pickup_Speed_Best_Agent (raw wall-clock lead_started_at -> first
     * agent reply, non-negative, picked-up leads only) but WITHOUT the
     * min-sample HAVING / single-row LIMIT, so the Agent Score card can score
     * every agent and apply its own min-sample anchor in PHP. Name resolves
     * from ghl_users.Name, falling back to the raw UID.
     *
     * @param string $start_date  'Y-m-d'
     * @param string $end_date    'Y-m-d'
     * @return array list of { agent_id, agent_name, avg_seconds:int, n:int }
     */
    function Lead_Pickup_Speed_By_Agent($start_date, $end_date)
    {
        $gap = 'UNIX_TIMESTAMP(pl.response_1_agent_message_at) - UNIX_TIMESTAMP(pl.lead_started_at)';
        $sql = "
            SELECT
                NULLIF(pl.assigned_to_user_id, '') AS agent_id,
                COALESCE(NULLIF(gu.Name, ''), NULLIF(pl.assigned_to_user_id, '')) AS agent_name,
                AVG({$gap}) AS avg_seconds,
                COUNT(*) AS n
            FROM ghl_processed_leads pl
            LEFT JOIN ghl_users gu ON gu.UserID = NULLIF(pl.assigned_to_user_id, '')
            WHERE NULLIF(pl.assigned_to_user_id, '') IS NOT NULL
              AND pl.lead_started_at >= ? AND pl.lead_started_at <= ?
              AND pl.response_1_agent_message_at IS NOT NULL
              AND pl.lead_started_at IS NOT NULL
              AND ({$gap}) >= 0
            GROUP BY agent_id, agent_name
        ";
        $rows = $this->db->query($sql, array(
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59',
        ))->result_array();

        $out = array();
        foreach ($rows as $r) {
            $out[] = array(
                'agent_id'    => $r['agent_id'],
                'agent_name'  => $r['agent_name'],
                'avg_seconds' => (int) round((float) $r['avg_seconds']),
                'n'           => (int) $r['n'],
            );
        }
        return $out;
    }

    /**
     * Per-agent outbound message counts over a period. Powers the OWNER matrix
     * "Outbound Messages" column. Generalises the single-agent outbound count in
     * Booking::ajax_summary_cards (the TC "Outbound Messages" card) — same
     * direction='outbound' + null-user guard + date_added window, but grouped by
     * sending GHL user instead of scoped to one agent's uid(s).
     *
     * @param string $start_date 'Y-m-d'
     * @param string $end_date   'Y-m-d'
     * @return array  rows of { agent_id (GHL uid), outbound_count }
     */
    function Outbound_Messages_By_Agent($start_date, $end_date)
    {
        $sql = "
            SELECT gm.user_id AS agent_id,
                   COUNT(*) AS outbound_count
            FROM ghl_messages gm
            WHERE gm.direction='outbound'
              AND NULLIF(gm.user_id,'') IS NOT NULL
              AND gm.date_added BETWEEN ? AND ?
            GROUP BY gm.user_id
        ";
        $rows = $this->db->query($sql, array(
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59',
        ))->result_array();

        $out = array();
        foreach ($rows as $r) {
            $out[] = array(
                'agent_id'       => $r['agent_id'],
                'outbound_count' => (int) $r['outbound_count'],
            );
        }
        return $out;
    }

    /**
     * Per-agent cancellation counts over a period, grouped by the credited TC
     * slot. Powers the OWNER matrix "Cancellation %" column. This is the
     * single-agent Cancellation Rate rule (cancellation_rate_exclude_duplicate_-
     * clause — duplicates dropped from BOTH numerator and denominator) re-grouped
     * per credited admin via lead_conversion_credit_agent_expr() so the universe
     * matches the TC1/TC2 attribution used everywhere else.
     *
     * Rate is intentionally NOT computed here — the caller divides cancelled/total
     * with an explicit total>0 guard so a zero-BC agent never divides by zero.
     *
     * Scoped to the TC sales-agent role (admin Level 20/50) via an INNER JOIN on
     * the credited slot — matching the owner matrix's sales source — so a BC
     * credited to a non-TC admin can't introduce an out-of-scope row.
     *
     * @param string $start_date 'Y-m-d'
     * @param string $end_date   'Y-m-d'
     * @return array  rows of { admin_id, total, cancelled }
     */
    function Cancellation_By_Agent($start_date, $end_date)
    {
        $this->load->helper(array('cancellation_rate', 'lead_conversion_credit'));
        $agent_expr = lead_conversion_credit_agent_expr('booking');
        $exclude    = cancellation_rate_exclude_duplicate_clause('booking');

        $sql = "
            SELECT {$agent_expr} AS admin_id,
                   COUNT(*) AS total,
                   SUM(CASE WHEN booking.CancelStatus='Y' THEN 1 ELSE 0 END) AS cancelled
            FROM booking
            INNER JOIN admin ON admin.AdminID = {$agent_expr} AND admin.Level IN ('20','50')
            WHERE booking.BookingConfirmationTitle='BOOKING CONFIRMATION'
              AND booking.Status!='N'
              AND {$exclude}
              AND CAST(booking.InsertDate AS DATE) BETWEEN ? AND ?
            GROUP BY admin_id
            HAVING admin_id IS NOT NULL AND admin_id > 0
        ";
        $rows = $this->db->query($sql, array($start_date, $end_date))->result_array();

        $out = array();
        foreach ($rows as $r) {
            $out[] = array(
                'admin_id'  => (int) $r['admin_id'],
                'total'     => (int) $r['total'],
                'cancelled' => (int) $r['cancelled'],
            );
        }
        return $out;
    }

    /**
     * Per-agent lead counts for the three "Leads" card windows (Today / Week /
     * Month) in a single conditional-SUM pass. Powers the OWNER-only
     * "Leads by Agent" table — the same new-lead total as the Leads card, but
     * broken out one row per agent.
     *
     * Windowed by pl.lead_started_at, identical to the Leads card. The outer
     * WHERE is bounded by the union of the three windows so a week that spills
     * into an adjacent month near a boundary is still scanned, while all-time
     * leads are not. Unassigned leads ('' / NULL) are excluded; agent_name
     * resolves from ghl_users.Name and falls back to the raw UID.
     *
     * @param string $today       'Y-m-d'
     * @param string $week_start   'Y-m-d' (Monday)
     * @param string $week_end     'Y-m-d' (Sunday)
     * @param string $month_start  'Y-m-d' (1st)
     * @param string $month_end    'Y-m-d' (last)
     * @return array  rows of { agent_id, agent_name, day, week, month }
     */
    function Lead_Dashboard_Leads_By_Agent_DWM($today, $week_start, $week_end, $month_start, $month_end)
    {
        $range_start = min($today, $week_start, $month_start);
        $range_end   = max($today, $week_end, $month_end);

        $sql = "
            SELECT
                NULLIF(pl.assigned_to_user_id, '') AS agent_id,
                COALESCE(NULLIF(gu.Name, ''), NULLIF(pl.assigned_to_user_id, '')) AS agent_name,
                SUM(CASE WHEN pl.lead_started_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS day_cnt,
                SUM(CASE WHEN pl.lead_started_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS week_cnt,
                SUM(CASE WHEN pl.lead_started_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS month_cnt
            FROM ghl_processed_leads pl
            LEFT JOIN ghl_users gu ON gu.UserID = NULLIF(pl.assigned_to_user_id, '')
            WHERE NULLIF(pl.assigned_to_user_id, '') IS NOT NULL
              AND pl.lead_started_at BETWEEN ? AND ?
            GROUP BY agent_id, agent_name
            HAVING (day_cnt + week_cnt + month_cnt) > 0
            ORDER BY month_cnt DESC, week_cnt DESC, day_cnt DESC, agent_name ASC
        ";

        $rows = $this->db->query($sql, array(
            $today . ' 00:00:00',       $today . ' 23:59:59',
            $week_start . ' 00:00:00',  $week_end . ' 23:59:59',
            $month_start . ' 00:00:00', $month_end . ' 23:59:59',
            $range_start . ' 00:00:00', $range_end . ' 23:59:59',
        ))->result_array();

        $results = array();
        foreach ($rows as $row) {
            $results[] = array(
                'agent_id'   => $row['agent_id'],
                'agent_name' => $row['agent_name'],
                'day'        => (int) $row['day_cnt'],
                'week'       => (int) $row['week_cnt'],
                'month'      => (int) $row['month_cnt'],
            );
        }
        return $results;
    }

    function Lead_Dashboard_By_Agent($filters = array(), $credit_fragment_override = null)
    {
        $this->load->helper('lead_conversion_credit');
        $where = $this->build_lead_dashboard_where_clause($filters);
        $clauses = array();

        if (!empty($where['sql'])) {
            $clauses[] = preg_replace('/^\s*WHERE\s+/i', '', $where['sql']);
        }

        $clauses[] = "NULLIF(pl.assigned_to_user_id, '') IS NOT NULL";
        $agentWhereSql = !empty($clauses) ? 'WHERE ' . implode(' AND ', $clauses) : '';

        $extraJoins = isset($where['extra_joins']) ? $where['extra_joins'] : '';
        // Callers can inject an alternate credit fragment (e.g. the TC2-only
        // variant for the dashboard's YTD Conversion Rate card); default keeps
        // the standard cutoff-based TC1/TC2 attribution.
        $creditFragment = ($credit_fragment_override !== null && $credit_fragment_override !== '')
            ? $credit_fragment_override
            : lead_conversion_credit_sql_fragment();
        $activeBooking = lead_conversion_active_booking_sql('pl');
        $sql = "
            SELECT
                COALESCE(NULLIF(pl.assigned_to_user_id, ''), '__unassigned__') AS agent_id,
                COALESCE(NULLIF(gu.Name, ''), NULLIF(pl.assigned_to_user_id, ''), 'Unassigned') AS agent_name,
                COUNT(*) AS total_leads,
                SUM(CASE WHEN pl.responded_message_count > 0 THEN 1 ELSE 0 END) AS responded_leads,
                AVG(pl.avg_first_5_response_seconds) AS avg_response_time_seconds,
                AVG(pl.avg_recent_5_response_seconds) AS avg_recent_response_time_seconds,
                AVG(pl.responded_message_count) AS avg_responded_messages,
                AVG(pl.recent_responded_message_count) AS avg_recent_responded_messages,
                SUM(CASE WHEN pl.is_converted = 1 AND pl.booking_id IS NOT NULL AND {$creditFragment} AND {$activeBooking} THEN 1 ELSE 0 END) AS converted_leads,
                MAX(pl.updated_at) AS last_updated_at
            FROM ghl_processed_leads pl
            LEFT JOIN ghl_conversations gc ON gc.conversation_id = pl.conversation_id
            LEFT JOIN ghl_users gu ON gu.UserID = NULLIF(pl.assigned_to_user_id, '')
            {$extraJoins}
            {$agentWhereSql}
            GROUP BY agent_id, agent_name
            ORDER BY total_leads DESC, agent_name ASC
        ";

        $rows = $this->db->query($sql, $where['params'])->result_array();
        $slotSelect = $this->get_processed_lead_response_slot_select('pl');
        $slotSql = "
            SELECT
                COALESCE(NULLIF(pl.assigned_to_user_id, ''), '__unassigned__') AS agent_id,
                {$slotSelect}
            FROM ghl_processed_leads pl
            LEFT JOIN ghl_conversations gc ON gc.conversation_id = pl.conversation_id
            LEFT JOIN ghl_users gu ON gu.UserID = NULLIF(pl.assigned_to_user_id, '')
            {$extraJoins}
            {$agentWhereSql}
        ";
        $combinedResponseByAgent = $this->calculate_combined_response_averages(
            $this->db->query($slotSql, $where['params'])->result_array(),
            'agent_id',
            false
        );
        $results = array();

        foreach ($rows as $row) {
            $totalLeads = (int) $row['total_leads'];
            $respondedLeads = (int) $row['responded_leads'];
            $convertedLeads = (int) $row['converted_leads'];
            $avgResponseSeconds = $row['avg_response_time_seconds'] !== null
                ? (int) round($row['avg_response_time_seconds'])
                : null;
            if (array_key_exists($row['agent_id'], $combinedResponseByAgent)) {
                $avgResponseSeconds = $combinedResponseByAgent[$row['agent_id']];
            }
            $avgRecentResponseSeconds = $row['avg_recent_response_time_seconds'] !== null
                ? (int) round($row['avg_recent_response_time_seconds'])
                : null;
            $avgRespondedMessages = $row['avg_responded_messages'] !== null
                ? round((float) $row['avg_responded_messages'], 1)
                : 0.0;
            $avgRecentRespondedMessages = $row['avg_recent_responded_messages'] !== null
                ? round((float) $row['avg_recent_responded_messages'], 1)
                : 0.0;

            $results[] = array(
                'agent_id' => $row['agent_id'],
                'agent_name' => $row['agent_name'],
                'total_leads' => $totalLeads,
                'responded_leads' => $respondedLeads,
                'avg_first_response_time_seconds' => $row['avg_response_time_seconds'] !== null ? (int) round($row['avg_response_time_seconds']) : null,
                'avg_response_time_seconds' => $avgResponseSeconds,
                'avg_recent_response_time_seconds' => $avgRecentResponseSeconds,
                'avg_responded_messages' => $avgRespondedMessages,
                'avg_recent_responded_messages' => $avgRecentRespondedMessages,
                'converted_leads' => $convertedLeads,
                'response_rate' => $totalLeads > 0 ? round(($respondedLeads / $totalLeads) * 100, 1) : 0.0,
                'conversion_rate' => $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 1) : 0.0,
                'last_updated_at' => $row['last_updated_at'],
            );
        }
        return $results;
    }

    function Lead_Ownership_Summary($filters = array())
    {
        $this->load->helper('lead_conversion_credit');
        $where = $this->build_lead_ownership_where_clause($filters);
        $extraJoins = isset($where['extra_joins']) ? $where['extra_joins'] : '';
        $activeBooking = lead_conversion_active_booking_sql('glo');

        // Response-time averages are recomputed below from the slot timestamps
        // through the ownership duty window, so they are not selected here.
        $sql = "
            SELECT
                COUNT(*) AS owned_leads,
                COUNT(DISTINCT glo.processed_lead_id) AS unique_leads,
                SUM(CASE WHEN glo.is_assigned_owner = 1 THEN 1 ELSE 0 END) AS assigned_owned_leads,
                SUM(CASE WHEN glo.is_assigned_owner = 0 AND glo.is_reply_owner = 1 THEN 1 ELSE 0 END) AS reply_owned_leads,
                SUM(CASE WHEN glo.responded_message_count > 0 THEN 1 ELSE 0 END) AS responded_leads,
                SUM(CASE WHEN glo.follow_up_status IN ('sent', 'completed') THEN 1 ELSE 0 END) AS follow_up_leads,
                SUM(CASE WHEN glo.is_converted = 1 AND glo.booking_id IS NOT NULL AND {$activeBooking} THEN 1 ELSE 0 END) AS converted_leads,
                COUNT(DISTINCT glo.owner_user_id) AS active_owners,
                AVG(glo.responded_message_count) AS avg_responded_messages,
                AVG(glo.recent_responded_message_count) AS avg_recent_responded_messages
            FROM ghl_lead_ownership glo
            LEFT JOIN ghl_users gu ON gu.UserID = glo.owner_user_id
            {$extraJoins}
            {$where['sql']}
        ";

        $row = $this->db->query($sql, $where['params'])->row_array();
        $ownedLeads = !empty($row['owned_leads']) ? (int) $row['owned_leads'] : 0;
        $respondedLeads = !empty($row['responded_leads']) ? (int) $row['responded_leads'] : 0;
        $followUpLeads = !empty($row['follow_up_leads']) ? (int) $row['follow_up_leads'] : 0;
        $convertedLeads = !empty($row['converted_leads']) ? (int) $row['converted_leads'] : 0;

        // Override the wall-clock response averages with the dedicated
        // 7AM-10PM everyday duty-window figures, recomputed from the slot stamps.
        $duty = $this->ownership_response_slot_rows($where, null);
        $dutyAll = isset($duty['__all__']) ? $duty['__all__'] : array('first' => null, 'recent' => null, 'combined' => null);

        return array(
            'owned_leads' => $ownedLeads,
            'unique_leads' => !empty($row['unique_leads']) ? (int) $row['unique_leads'] : 0,
            'assigned_owned_leads' => !empty($row['assigned_owned_leads']) ? (int) $row['assigned_owned_leads'] : 0,
            'reply_owned_leads' => !empty($row['reply_owned_leads']) ? (int) $row['reply_owned_leads'] : 0,
            'responded_leads' => $respondedLeads,
            'follow_up_leads' => $followUpLeads,
            'converted_leads' => $convertedLeads,
            'active_owners' => !empty($row['active_owners']) ? (int) $row['active_owners'] : 0,
            'response_rate' => $ownedLeads > 0 ? round(($respondedLeads / $ownedLeads) * 100, 1) : 0.0,
            'follow_up_rate' => $ownedLeads > 0 ? round(($followUpLeads / $ownedLeads) * 100, 1) : 0.0,
            'conversion_rate' => $ownedLeads > 0 ? round(($convertedLeads / $ownedLeads) * 100, 1) : 0.0,
            'avg_first_response_time_seconds' => $dutyAll['first'],
            'avg_response_time_seconds' => $dutyAll['combined'],
            'avg_recent_response_time_seconds' => $dutyAll['recent'],
            'avg_responded_messages' => $row['avg_responded_messages'] !== null ? round((float) $row['avg_responded_messages'], 1) : 0.0,
            'avg_recent_responded_messages' => $row['avg_recent_responded_messages'] !== null ? round((float) $row['avg_recent_responded_messages'], 1) : 0.0,
        );
    }

    function Lead_Ownership_By_Agent($filters = array())
    {
        $this->load->helper('lead_conversion_credit');
        $where = $this->build_lead_ownership_where_clause($filters);
        $extraJoins = isset($where['extra_joins']) ? $where['extra_joins'] : '';
        $activeBooking = lead_conversion_active_booking_sql('glo');

        // Response-time averages are recomputed below from the slot timestamps
        // through the ownership duty window, so they are not selected here.
        $sql = "
            SELECT
                glo.owner_user_id,
                COALESCE(NULLIF(gu.Name, ''), glo.owner_user_id) AS owner_name,
                COUNT(*) AS owned_leads,
                COUNT(DISTINCT glo.processed_lead_id) AS unique_leads,
                SUM(CASE WHEN glo.is_assigned_owner = 1 THEN 1 ELSE 0 END) AS assigned_owned_leads,
                SUM(CASE WHEN glo.is_assigned_owner = 0 AND glo.is_reply_owner = 1 THEN 1 ELSE 0 END) AS reply_owned_leads,
                SUM(CASE WHEN glo.responded_message_count > 0 THEN 1 ELSE 0 END) AS responded_leads,
                SUM(CASE WHEN glo.follow_up_status IN ('sent', 'completed') THEN 1 ELSE 0 END) AS follow_up_leads,
                SUM(CASE WHEN glo.is_converted = 1 AND glo.booking_id IS NOT NULL AND {$activeBooking} THEN 1 ELSE 0 END) AS converted_leads,
                AVG(glo.responded_message_count) AS avg_responded_messages,
                AVG(glo.recent_responded_message_count) AS avg_recent_responded_messages,
                MAX(glo.calculated_at) AS last_calculated_at
            FROM ghl_lead_ownership glo
            LEFT JOIN ghl_users gu ON gu.UserID = glo.owner_user_id
            {$extraJoins}
            {$where['sql']}
            GROUP BY glo.owner_user_id, owner_name
            ORDER BY owned_leads DESC, owner_name ASC
        ";

        $rows = $this->db->query($sql, $where['params'])->result_array();
        // Per-owner 7AM-10PM everyday duty-window response averages, recomputed
        // from the slot timestamps to override the stored wall-clock figures.
        $duty = $this->ownership_response_slot_rows($where, 'owner_user_id');

        // Per-agent conversion % is the MEAN of each day's "locked" conversion
        // rate spread over every calendar day in the selected range, not a single
        // aggregate ratio. See lead_ownership_daily_conversion_by_agent().
        $rangeDays = $this->lead_ownership_range_day_count($filters);
        $dailyConversion = $this->lead_ownership_daily_conversion_by_agent($where, $extraJoins);

        $results = array();

        foreach ($rows as $row) {
            $ownedLeads = (int) $row['owned_leads'];
            $respondedLeads = (int) $row['responded_leads'];
            $followUpLeads = (int) $row['follow_up_leads'];
            $convertedLeads = (int) $row['converted_leads'];
            $ownerDuty = isset($duty[(string) $row['owner_user_id']])
                ? $duty[(string) $row['owner_user_id']]
                : array('first' => null, 'recent' => null, 'combined' => null);

            $ownerKey = (string) $row['owner_user_id'];
            $daily = isset($dailyConversion[$ownerKey])
                ? $dailyConversion[$ownerKey]
                : array('sum_daily_rate' => 0.0, 'active_days' => 0);
            // Denominator: calendar days in the range (empty days count as 0%);
            // when no range is bounded, fall back to days that actually had leads.
            $denomDays = $rangeDays !== null ? $rangeDays : (int) $daily['active_days'];
            $conversionRate = $denomDays > 0 ? round(((float) $daily['sum_daily_rate']) / $denomDays, 1) : 0.0;

            $results[] = array(
                'owner_user_id' => $row['owner_user_id'],
                'owner_name' => $row['owner_name'],
                'owned_leads' => $ownedLeads,
                'unique_leads' => (int) $row['unique_leads'],
                'assigned_owned_leads' => (int) $row['assigned_owned_leads'],
                'reply_owned_leads' => (int) $row['reply_owned_leads'],
                'responded_leads' => $respondedLeads,
                'follow_up_leads' => $followUpLeads,
                'converted_leads' => $convertedLeads,
                'response_rate' => $ownedLeads > 0 ? round(($respondedLeads / $ownedLeads) * 100, 1) : 0.0,
                'follow_up_rate' => $ownedLeads > 0 ? round(($followUpLeads / $ownedLeads) * 100, 1) : 0.0,
                'conversion_rate' => $conversionRate,
                'avg_first_response_time_seconds' => $ownerDuty['first'],
                'avg_response_time_seconds' => $ownerDuty['combined'],
                'avg_recent_response_time_seconds' => $ownerDuty['recent'],
                'avg_responded_messages' => $row['avg_responded_messages'] !== null ? round((float) $row['avg_responded_messages'], 1) : 0.0,
                'avg_recent_responded_messages' => $row['avg_recent_responded_messages'] !== null ? round((float) $row['avg_recent_responded_messages'], 1) : 0.0,
                'last_calculated_at' => $row['last_calculated_at'],
            );
        }

        return $results;
    }

    /**
     * Number of calendar days in the lead-ownership date range, inclusive of
     * both endpoints. Returns null when the range is not bounded (e.g. a direct
     * model call without start/end dates), letting the caller fall back to the
     * count of days that actually carried leads.
     */
    private function lead_ownership_range_day_count($filters)
    {
        if (empty($filters['start_date']) || empty($filters['end_date'])) {
            return null;
        }

        $start = strtotime($filters['start_date'] . ' 00:00:00');
        $end = strtotime($filters['end_date'] . ' 00:00:00');

        if ($start === false || $end === false || $end < $start) {
            return null;
        }

        return (int) floor(($end - $start) / 86400) + 1;
    }

    /**
     * Per-agent "locked daily" conversion components. Each day's conversion rate
     * is 100 * converted(day) / owned(day) on DATE(lead_started_at); we return,
     * per owner, the SUM of those daily rates and the count of days that had
     * leads. The caller divides the sum by the calendar days in the range so
     * that days with no leads count as 0%. Reuses the same WHERE/joins as the
     * agent listing so every filter (date, owner, team lead, ownership type)
     * applies identically.
     *
     * @return array map of owner_user_id => ['sum_daily_rate' => float, 'active_days' => int]
     */
    private function lead_ownership_daily_conversion_by_agent($where, $extraJoins)
    {
        $this->load->helper('lead_conversion_credit');
        $activeBooking = lead_conversion_active_booking_sql('glo');
        $sql = "
            SELECT owner_user_id,
                   SUM(daily_rate) AS sum_daily_rate,
                   COUNT(*) AS active_days
            FROM (
                SELECT glo.owner_user_id AS owner_user_id,
                       (100.0 * SUM(CASE WHEN glo.is_converted = 1 AND glo.booking_id IS NOT NULL AND {$activeBooking} THEN 1 ELSE 0 END) / COUNT(*)) AS daily_rate
                FROM ghl_lead_ownership glo
                LEFT JOIN ghl_users gu ON gu.UserID = glo.owner_user_id
                {$extraJoins}
                {$where['sql']}
                GROUP BY glo.owner_user_id, DATE(glo.lead_started_at)
            ) daily
            GROUP BY owner_user_id
        ";

        $rows = $this->db->query($sql, $where['params'])->result_array();
        $map = array();

        foreach ($rows as $row) {
            $map[(string) $row['owner_user_id']] = array(
                'sum_daily_rate' => (float) $row['sum_daily_rate'],
                'active_days' => (int) $row['active_days'],
            );
        }

        return $map;
    }

    function Lead_Ownership_Agents($restrict_agent_ids = null)
    {
        $params = array();
        $extra = '';
        if (is_array($restrict_agent_ids)) {
            if (empty($restrict_agent_ids)) {
                return array();
            }
            $placeholders = implode(',', array_fill(0, count($restrict_agent_ids), '?'));
            $extra = " AND glo.owner_user_id IN ({$placeholders}) ";
            $params = array_values(array_map('strval', $restrict_agent_ids));
        }

        $sql = "
            SELECT DISTINCT
                glo.owner_user_id AS agent_id,
                COALESCE(NULLIF(gu.Name, ''), glo.owner_user_id) AS agent_name
            FROM ghl_lead_ownership glo
            LEFT JOIN ghl_users gu ON gu.UserID = glo.owner_user_id
            WHERE glo.owner_user_id IS NOT NULL
              AND glo.owner_user_id <> ''
              {$extra}
            ORDER BY agent_name ASC
        ";

        return $this->db->query($sql, $params)->result();
    }

    function Lead_Ownership_Last_Calculated_At()
    {
        $row = $this->db
            ->select('MAX(calculated_at) AS calculated_at', false)
            ->from('ghl_lead_ownership')
            ->get()
            ->row_array();

        return !empty($row['calculated_at']) ? $row['calculated_at'] : null;
    }

    function Lead_Ownership_Data_Total_Count($filters = array())
    {
        $where = $this->build_lead_ownership_where_clause($filters);
        $extraJoins = isset($where['extra_joins']) ? $where['extra_joins'] : '';

        $sql = "
            SELECT COUNT(*) AS total_rows
            FROM ghl_lead_ownership glo
            LEFT JOIN ghl_users gu ON gu.UserID = glo.owner_user_id
            {$extraJoins}
            {$where['sql']}
        ";

        $row = $this->db->query($sql, $where['params'])->row_array();
        return !empty($row['total_rows']) ? (int) $row['total_rows'] : 0;
    }

    function Lead_Ownership_Data_Rows($filters = array(), $limit = null, $offset = null)
    {
        $where = $this->build_lead_ownership_where_clause($filters);
        $extraJoins = isset($where['extra_joins']) ? $where['extra_joins'] : '';
        $assignedAtSelect = $this->db->field_exists('assigned_at', 'ghl_lead_ownership')
            ? 'glo.assigned_at'
            : 'NULL AS assigned_at';
        $limit = $limit !== null ? max(1, (int) $limit) : null;
        $offset = $offset !== null ? max(0, (int) $offset) : null;

        $sql = "
            SELECT
                glo.id,
                glo.processed_lead_id,
                glo.conversation_id,
                glo.contact_id,
                COALESCE(NULLIF(gc.contact_name, ''), NULLIF(gc.full_name, ''), 'Unknown Contact') AS contact_name,
                gc.phone,
                COALESCE(NULLIF(gu.Name, ''), glo.owner_user_id) AS owner_name,
                glo.owner_user_id,
                COALESCE(NULLIF(assigned_gu.Name, ''), NULLIF(glo.assigned_to_user_id, ''), 'Unassigned') AS assigned_name,
                glo.assigned_to_user_id,
                {$assignedAtSelect},
                glo.is_assigned_owner,
                glo.is_reply_owner,
                glo.outbound_reply_count,
                glo.lead_started_at,
                glo.lead_ended_at,
                glo.tracked_message_count,
                glo.responded_message_count,
                glo.avg_first_5_response_seconds,
                glo.recent_tracked_message_count,
                glo.recent_responded_message_count,
                glo.avg_recent_5_response_seconds,
                glo.follow_up_status,
                glo.is_converted,
                glo.booking_id,
                glo.converted_at,
                glo.calculated_at,
                b.BookingNumber
            FROM ghl_lead_ownership glo
            LEFT JOIN ghl_users gu ON gu.UserID = glo.owner_user_id
            LEFT JOIN ghl_users assigned_gu ON assigned_gu.UserID = glo.assigned_to_user_id
            LEFT JOIN ghl_conversations gc ON gc.conversation_id = glo.conversation_id
            LEFT JOIN booking b ON b.BookingID = glo.booking_id
            {$extraJoins}
            {$where['sql']}
            ORDER BY glo.lead_started_at DESC, glo.id DESC
        ";

        $params = $where['params'];

        if ($limit !== null) {
            $sql .= " LIMIT ?";
            $params[] = $limit;

            if ($offset !== null) {
                $sql .= " OFFSET ?";
                $params[] = $offset;
            }
        }

        return $this->db->query($sql, $params)->result_array();
    }

    function Lead_Reply_Activity_Summary($filters = array())
    {
        $assignedLeads = $this->Lead_Reply_Activity_Assigned_New_Leads_Summary($filters);
        $replyCreated = $this->Lead_Reply_Activity_Reply_Created_Summary($filters);
        $replyCreatedLeads = !empty($replyCreated['reply_created_leads']) ? (int) $replyCreated['reply_created_leads'] : 0;
        $replyCreatedNotAssignedLeads = !empty($replyCreated['reply_created_not_assigned_leads']) ? (int) $replyCreated['reply_created_not_assigned_leads'] : 0;
        $assignedReplyCreatedLeads = !empty($replyCreated['assigned_reply_created_leads']) ? (int) $replyCreated['assigned_reply_created_leads'] : 0;

        return array(
            'assigned_leads' => $assignedLeads,
            'reply_created_leads' => $replyCreatedLeads,
            'reply_created_not_assigned_leads' => $replyCreatedNotAssignedLeads,
            'assigned_reply_created_leads' => $assignedReplyCreatedLeads,
            'total_productivity_leads' => $assignedLeads + $replyCreatedNotAssignedLeads,
            'active_owners' => !empty($replyCreated['active_owners']) ? (int) $replyCreated['active_owners'] : 0,
        );
    }

    function Lead_Reply_Activity_By_Agent($filters = array())
    {
        $this->load->helper('ghl_messages_log');
        $assignedRows = $this->Lead_Reply_Activity_Assigned_New_Leads_By_Agent($filters);
        $replyCreatedRows = $this->Lead_Reply_Activity_Reply_Created_By_Agent($filters);
        $respondedRows = $this->Lead_Reply_Activity_Responded_By_Agent($filters);
        $results = array();

        foreach ($assignedRows as $ownerId => $assignedRow) {
            $assignedLeads = (int) $assignedRow['assigned_new_leads'];
            $results[$ownerId] = array(
                'owner_user_id' => $ownerId,
                'owner_name' => $assignedRow['owner_name'],
                'assigned_leads' => $assignedLeads,
                'reply_created_leads' => 0,
                'reply_created_not_assigned_leads' => 0,
                'assigned_reply_created_leads' => 0,
                'lead_responded' => 0,
                'today_handling_leads' => 0,
                'total_productivity_leads' => $assignedLeads,
                'first_reply_created_at' => null,
                'last_reply_created_at' => null,
            );
        }

        foreach ($replyCreatedRows as $ownerId => $replyCreatedRow) {
            if (!isset($results[$ownerId])) {
                $results[$ownerId] = array(
                    'owner_user_id' => $ownerId,
                    'owner_name' => $replyCreatedRow['owner_name'],
                    'assigned_leads' => 0,
                    'reply_created_leads' => 0,
                    'reply_created_not_assigned_leads' => 0,
                    'assigned_reply_created_leads' => 0,
                    'lead_responded' => 0,
                    'today_handling_leads' => 0,
                    'total_productivity_leads' => 0,
                    'first_reply_created_at' => null,
                    'last_reply_created_at' => null,
                );
            }

            $results[$ownerId]['reply_created_leads'] = (int) $replyCreatedRow['reply_created_leads'];
            $results[$ownerId]['reply_created_not_assigned_leads'] = (int) $replyCreatedRow['reply_created_not_assigned_leads'];
            $results[$ownerId]['assigned_reply_created_leads'] = (int) $replyCreatedRow['assigned_reply_created_leads'];
            $results[$ownerId]['total_productivity_leads'] =
                (int) $results[$ownerId]['assigned_leads'] + (int) $replyCreatedRow['reply_created_not_assigned_leads'];
            $results[$ownerId]['first_reply_created_at'] = $replyCreatedRow['first_reply_created_at'];
            $results[$ownerId]['last_reply_created_at'] = $replyCreatedRow['last_reply_created_at'];
        }

        // "Lead Responded" stands on its own: an owner can have replied to leads
        // in business hours without crossing the reply-created threshold or being
        // assigned, so seed any owner the earlier passes missed.
        foreach ($respondedRows as $ownerId => $respondedRow) {
            if (!isset($results[$ownerId])) {
                $results[$ownerId] = array(
                    'owner_user_id' => $ownerId,
                    'owner_name' => $respondedRow['owner_name'],
                    'assigned_leads' => 0,
                    'reply_created_leads' => 0,
                    'reply_created_not_assigned_leads' => 0,
                    'assigned_reply_created_leads' => 0,
                    'lead_responded' => 0,
                    'today_handling_leads' => 0,
                    'total_productivity_leads' => 0,
                    'first_reply_created_at' => null,
                    'last_reply_created_at' => null,
                );
            }

            $results[$ownerId]['lead_responded'] = (int) $respondedRow['lead_responded'];
        }

        foreach ($results as $ownerId => $row) {
            $results[$ownerId]['today_handling_leads'] = lead_reply_activity_today_handling(
                $row['lead_responded'],
                $row['reply_created_leads']
            );
        }

        usort($results, function($a, $b) {
            if ($a['lead_responded'] !== $b['lead_responded']) {
                return $b['lead_responded'] - $a['lead_responded'];
            }
            if ($a['today_handling_leads'] !== $b['today_handling_leads']) {
                return $b['today_handling_leads'] - $a['today_handling_leads'];
            }
            if ($a['reply_created_leads'] !== $b['reply_created_leads']) {
                return $b['reply_created_leads'] - $a['reply_created_leads'];
            }
            return strcasecmp($a['owner_name'], $b['owner_name']);
        });

        return array_values($results);
    }

    /**
     * "Lead Responded" per owner: the DISTINCT leads an owner replied to whose
     * qualifying outbound reply lands on any day between 07:00 and 22:00. Many
     * replies (incl. more than three) to the same lead collapse to a single
     * count via COUNT(DISTINCT processed_lead_id). Shares the reply-activity
     * universe (is_reply_owner = 1) and owner/team filters with Transfer Out so
     * Today Handling (responded - transfer out) stays meaningful.
     */
    private function Lead_Reply_Activity_Responded_By_Agent($filters = array())
    {
        $where = $this->build_lead_reply_created_where_clause($filters);
        $extraJoins = isset($where['extra_joins']) ? $where['extra_joins'] : '';
        $messageTimeColumn = $this->escape_identifier($this->get_message_time_column());
        $businessHours = $this->lead_reply_business_hours_sql("gm.{$messageTimeColumn}");
        $start = !empty($filters['start_date']) ? $filters['start_date'] . ' 00:00:00' : '1970-01-01 00:00:00';
        $end = !empty($filters['end_date']) ? $filters['end_date'] . ' 23:59:59' : '9999-12-31 23:59:59';

        $sql = "
            SELECT
                glo.owner_user_id,
                COALESCE(NULLIF(gu.Name, ''), glo.owner_user_id) AS owner_name,
                COUNT(DISTINCT glo.processed_lead_id) AS lead_responded
            FROM ghl_lead_ownership glo
            INNER JOIN ghl_messages gm
                ON gm.conversation_id = glo.conversation_id
               AND gm.user_id = glo.owner_user_id
               AND gm.direction = 'outbound'
               AND gm.{$messageTimeColumn} >= glo.lead_started_at
               AND (
                    glo.lead_ended_at IS NULL
                    OR gm.{$messageTimeColumn} < glo.lead_ended_at
               )
            LEFT JOIN ghl_users gu ON gu.UserID = glo.owner_user_id
            {$extraJoins}
            {$where['sql']}
              AND gm.{$messageTimeColumn} BETWEEN ? AND ?
              AND {$businessHours}
            GROUP BY glo.owner_user_id, owner_name
        ";

        $params = array_merge($where['params'], array($start, $end));
        $rows = $this->db->query($sql, $params)->result_array();
        $results = array();

        foreach ($rows as $row) {
            $results[(string) $row['owner_user_id']] = array(
                'owner_user_id' => (string) $row['owner_user_id'],
                'owner_name' => $row['owner_name'],
                'lead_responded' => (int) $row['lead_responded'],
            );
        }

        return $results;
    }

    /**
     * "Lead Responded" for a single agent who may own MORE THAN ONE GHL inbox:
     * the DISTINCT leads replied to across ALL the given owner uids, counted once
     * even when a lead was handled by two of the agent's inboxes (e.g. transferred
     * between her own team inboxes). This is deliberately NOT the per-owner figure
     * summed -- summing double counts such shared leads. Same reply-activity
     * universe (is_reply_owner = 1), ownership window and 07:00-22:00 gate as the
     * dashboard's per-owner "Lead Responded", so a single-inbox agent still equals
     * her dashboard row exactly while a multi-inbox agent is de-duplicated.
     *
     * @param array  $uids   GHL owner user ids belonging to one agent
     * @param string $start  inclusive date 'Y-m-d'
     * @param string $end    inclusive date 'Y-m-d'
     * @return int   distinct leads responded to across the uid set
     */
    public function Lead_Reply_Activity_Responded_Distinct_For_Uids($uids, $start, $end)
    {
        $uids = array_values(array_filter(array_map('strval', (array) $uids), 'strlen'));
        if (empty($uids)) {
            return 0;
        }

        $messageTimeColumn = $this->escape_identifier($this->get_message_time_column());
        $businessHours = $this->lead_reply_business_hours_sql("gm.{$messageTimeColumn}");
        $placeholders = implode(',', array_fill(0, count($uids), '?'));

        $sql = "
            SELECT COUNT(DISTINCT glo.processed_lead_id) AS lead_responded
            FROM ghl_lead_ownership glo
            INNER JOIN ghl_messages gm
                ON gm.conversation_id = glo.conversation_id
               AND gm.user_id = glo.owner_user_id
               AND gm.direction = 'outbound'
               AND gm.{$messageTimeColumn} >= glo.lead_started_at
               AND (
                    glo.lead_ended_at IS NULL
                    OR gm.{$messageTimeColumn} < glo.lead_ended_at
               )
            WHERE glo.is_reply_owner = 1
              AND glo.owner_user_id IN ({$placeholders})
              AND gm.{$messageTimeColumn} BETWEEN ? AND ?
              AND {$businessHours}
        ";

        $params = array_merge($uids, array($start . ' 00:00:00', $end . ' 23:59:59'));
        $row = $this->db->query($sql, $params)->row_array();

        return !empty($row['lead_responded']) ? (int) $row['lead_responded'] : 0;
    }

    /**
     * Hourly inbound/outbound message counts for a single owner on one day. Powers
     * the per-owner "Lead Reply Hourly" drill-down. Scoped to the owner's leads via
     * ghl_lead_ownership (same reply-activity universe as the dashboard), bounded to
     * each lead's ownership window so messages are only attributed while the owner
     * actually held the lead. Outbound is restricted to the owner's own replies;
     * inbound is every customer message landing on those leads. Returns one row per
     * hour (0-23) that had any traffic -- gaps are filled in by the view helper.
     */
    function Lead_Reply_Activity_Hourly_By_Owner($filters = array())
    {
        $where = $this->build_lead_reply_created_where_clause($filters);
        if (trim($where['sql']) === 'WHERE 1=0') {
            return array();
        }

        $extraJoins = isset($where['extra_joins']) ? $where['extra_joins'] : '';
        $messageTimeColumn = $this->escape_identifier($this->get_message_time_column());
        $start = !empty($filters['start_date']) ? $filters['start_date'] . ' 00:00:00' : '1970-01-01 00:00:00';
        $end = !empty($filters['end_date']) ? $filters['end_date'] . ' 23:59:59' : '9999-12-31 23:59:59';

        $sql = "
            SELECT
                HOUR(gm.{$messageTimeColumn}) AS hour_of_day,
                COUNT(DISTINCT CASE WHEN gm.direction = 'inbound' THEN gm.id END) AS inbound_count,
                COUNT(DISTINCT CASE WHEN gm.direction = 'outbound' AND gm.user_id = glo.owner_user_id THEN gm.id END) AS outbound_count
            FROM ghl_lead_ownership glo
            INNER JOIN ghl_messages gm
                ON gm.conversation_id = glo.conversation_id
               AND gm.{$messageTimeColumn} >= glo.lead_started_at
               AND (
                    glo.lead_ended_at IS NULL
                    OR gm.{$messageTimeColumn} < glo.lead_ended_at
               )
            {$extraJoins}
            {$where['sql']}
              AND gm.{$messageTimeColumn} BETWEEN ? AND ?
              AND gm.direction IN ('inbound', 'outbound')
            GROUP BY hour_of_day
            ORDER BY hour_of_day ASC
        ";

        $params = array_merge($where['params'], array($start, $end));

        return $this->db->query($sql, $params)->result_array();
    }

    /**
     * Average reply time (seconds) for a single owner on one day, matching the
     * scoping of Lead_Reply_Activity_Hourly_By_Owner(): the owner's leads via
     * ghl_lead_ownership, bounded to each lead's ownership window, inbound = every
     * customer message, outbound = the owner's own replies. Raw messages are pulled
     * grouped by conversation and ascending in time so the inbound->outbound pairing
     * in ghl_message_log_average_reply_seconds() (in-hours pairs only) is sound.
     * Powers the "Avg Response Time" card on the Lead Reply Hourly page.
     *
     * @param array $filters Same filter shape as Lead_Reply_Activity_Hourly_By_Owner().
     * @return float|null Average seconds, or null when no pair qualifies.
     */
    function Lead_Reply_Activity_Hourly_Avg_Reply_Seconds_By_Owner($filters = array())
    {
        $where = $this->build_lead_reply_created_where_clause($filters);
        if (trim($where['sql']) === 'WHERE 1=0') {
            return null;
        }

        $this->load->helper('ghl_messages_log');
        $extraJoins = isset($where['extra_joins']) ? $where['extra_joins'] : '';
        $messageTimeColumn = $this->escape_identifier($this->get_message_time_column());
        $start = !empty($filters['start_date']) ? $filters['start_date'] . ' 00:00:00' : '1970-01-01 00:00:00';
        $end = !empty($filters['end_date']) ? $filters['end_date'] . ' 23:59:59' : '9999-12-31 23:59:59';

        $sql = "
            SELECT
                gm.conversation_id AS conversation_id,
                gm.direction AS direction,
                gm.{$messageTimeColumn} AS ts
            FROM ghl_lead_ownership glo
            INNER JOIN ghl_messages gm
                ON gm.conversation_id = glo.conversation_id
               AND gm.{$messageTimeColumn} >= glo.lead_started_at
               AND (
                    glo.lead_ended_at IS NULL
                    OR gm.{$messageTimeColumn} < glo.lead_ended_at
               )
            {$extraJoins}
            {$where['sql']}
              AND gm.{$messageTimeColumn} BETWEEN ? AND ?
              AND (
                    gm.direction = 'inbound'
                    OR (gm.direction = 'outbound' AND gm.user_id = glo.owner_user_id)
              )
            ORDER BY gm.conversation_id ASC, gm.{$messageTimeColumn} ASC, gm.id ASC
        ";

        $params = array_merge($where['params'], array($start, $end));
        $rows = $this->db->query($sql, $params)->result_array();

        return ghl_message_log_average_reply_seconds($rows);
    }

    /**
     * SQL predicate restricting a timestamp expression to working hours: any day
     * (everyday) between 07:00:00 and 22:00:00 inclusive. Mirrors
     * ghl_message_log_within_business_hours() so the dashboard only counts
     * replies made while the office is meant to be answering.
     *
     * @param string $expr A safe SQL timestamp expression (column/derived value).
     * @return string
     */
    private function lead_reply_business_hours_sql($expr)
    {
        return "(TIME({$expr}) BETWEEN '07:00:00' AND '22:00:00')";
    }

    private function Lead_Reply_Activity_Assigned_New_Leads_Summary($filters = array())
    {
        $where = $this->build_lead_reply_assignment_where_clause($filters);
        $extraJoins = isset($where['extra_joins']) ? $where['extra_joins'] : '';

        $sql = "
            SELECT COUNT(DISTINCT CONCAT(glo.owner_user_id, ':', glo.processed_lead_id)) AS assigned_new_leads
            FROM ghl_lead_ownership glo
            LEFT JOIN ghl_users gu ON gu.UserID = glo.owner_user_id
            {$extraJoins}
            {$where['sql']}
        ";

        $row = $this->db->query($sql, $where['params'])->row_array();
        return !empty($row['assigned_new_leads']) ? (int) $row['assigned_new_leads'] : 0;
    }

    private function Lead_Reply_Activity_Assigned_New_Leads_By_Agent($filters = array())
    {
        $where = $this->build_lead_reply_assignment_where_clause($filters);
        $extraJoins = isset($where['extra_joins']) ? $where['extra_joins'] : '';

        $sql = "
            SELECT
                glo.owner_user_id,
                COALESCE(NULLIF(gu.Name, ''), glo.owner_user_id) AS owner_name,
                COUNT(DISTINCT glo.processed_lead_id) AS assigned_new_leads
            FROM ghl_lead_ownership glo
            LEFT JOIN ghl_users gu ON gu.UserID = glo.owner_user_id
            {$extraJoins}
            {$where['sql']}
            GROUP BY glo.owner_user_id, owner_name
        ";

        $rows = $this->db->query($sql, $where['params'])->result_array();
        $results = array();

        foreach ($rows as $row) {
            $results[(string) $row['owner_user_id']] = array(
                'owner_user_id' => (string) $row['owner_user_id'],
                'owner_name' => $row['owner_name'],
                'assigned_new_leads' => (int) $row['assigned_new_leads'],
            );
        }

        return $results;
    }

    private function Lead_Reply_Activity_Reply_Created_Summary($filters = array())
    {
        $rows = $this->Lead_Reply_Activity_Reply_Created_Base_Rows($filters);
        $replyCreatedLeads = 0;
        $replyCreatedNotAssignedLeads = 0;
        $assignedReplyCreatedLeads = 0;
        $owners = array();

        foreach ($rows as $row) {
            $replyCreatedLeads++;
            if ((int) $row['is_assigned_owner'] === 1) {
                $assignedReplyCreatedLeads++;
            } else {
                $replyCreatedNotAssignedLeads++;
            }
            $owners[(string) $row['owner_user_id']] = true;
        }

        return array(
            'reply_created_leads' => $replyCreatedLeads,
            'reply_created_not_assigned_leads' => $replyCreatedNotAssignedLeads,
            'assigned_reply_created_leads' => $assignedReplyCreatedLeads,
            'active_owners' => count($owners),
        );
    }

    private function Lead_Reply_Activity_Reply_Created_By_Agent($filters = array())
    {
        $rows = $this->Lead_Reply_Activity_Reply_Created_Base_Rows($filters);
        $results = array();

        foreach ($rows as $row) {
            $ownerId = (string) $row['owner_user_id'];
            if (!isset($results[$ownerId])) {
                $results[$ownerId] = array(
                    'owner_user_id' => $ownerId,
                    'owner_name' => $row['owner_name'],
                    'reply_created_leads' => 0,
                    'reply_created_not_assigned_leads' => 0,
                    'assigned_reply_created_leads' => 0,
                    'first_reply_created_at' => $row['reply_created_at'],
                    'last_reply_created_at' => $row['reply_created_at'],
                );
            }

            $results[$ownerId]['reply_created_leads']++;
            if ((int) $row['is_assigned_owner'] === 1) {
                $results[$ownerId]['assigned_reply_created_leads']++;
            } else {
                $results[$ownerId]['reply_created_not_assigned_leads']++;
            }

            if ($row['reply_created_at'] < $results[$ownerId]['first_reply_created_at']) {
                $results[$ownerId]['first_reply_created_at'] = $row['reply_created_at'];
            }
            if ($row['reply_created_at'] > $results[$ownerId]['last_reply_created_at']) {
                $results[$ownerId]['last_reply_created_at'] = $row['reply_created_at'];
            }
        }

        return $results;
    }

    private function Lead_Reply_Activity_Reply_Created_Base_Rows($filters = array())
    {
        // This query is invoked by both the summary and the by-agent payloads on every
        // page load. Cache per filter-set so the heavy aggregation only runs once per request.
        $cacheKey = md5(serialize($filters));
        if (array_key_exists($cacheKey, $this->replyCreatedBaseRowsCache)) {
            return $this->replyCreatedBaseRowsCache[$cacheKey];
        }

        $where = $this->build_lead_reply_created_where_clause($filters);
        $extraJoins = isset($where['extra_joins']) ? $where['extra_joins'] : '';
        $messageTimeColumn = $this->escape_identifier($this->get_message_time_column());
        $assignmentDate = $this->lead_reply_assignment_date_expression('glo');
        $start = !empty($filters['start_date']) ? $filters['start_date'] . ' 00:00:00' : '1970-01-01 00:00:00';
        $end = !empty($filters['end_date']) ? $filters['end_date'] . ' 23:59:59' : '9999-12-31 23:59:59';

        // A lead only qualifies when its 4th outbound message (reply_created_at) lands inside
        // the requested window, which means the conversation MUST have an owner outbound message
        // in that window. Pre-filtering to those leads lets MySQL skip aggregating the entire
        // ghl_lead_ownership / ghl_messages history just to discard it in the outer WHERE.
        $datePreFilter = "
                  AND EXISTS (
                      SELECT 1
                      FROM ghl_messages gm_window
                      WHERE gm_window.conversation_id = glo.conversation_id
                        AND gm_window.user_id = glo.owner_user_id
                        AND gm_window.direction = 'outbound'
                        AND gm_window.{$messageTimeColumn} BETWEEN ? AND ?
                  )
        ";

        $sql = "
            SELECT *
            FROM (
                SELECT
                    glo.owner_user_id,
                    COALESCE(NULLIF(gu.Name, ''), glo.owner_user_id) AS owner_name,
                    glo.processed_lead_id,
                    glo.is_assigned_owner,
                    glo.assigned_to_user_id,
                    {$assignmentDate} AS assigned_activity_at,
                    SUBSTRING_INDEX(
                        SUBSTRING_INDEX(GROUP_CONCAT(gm.{$messageTimeColumn} ORDER BY gm.{$messageTimeColumn} ASC, gm.id ASC), ',', 4),
                        ',',
                        -1
                    ) AS reply_created_at
                FROM ghl_lead_ownership glo
                INNER JOIN ghl_messages gm
                    ON gm.conversation_id = glo.conversation_id
                   AND gm.user_id = glo.owner_user_id
                   AND gm.direction = 'outbound'
                   AND gm.{$messageTimeColumn} >= glo.lead_started_at
                   AND (
                        glo.lead_ended_at IS NULL
                        OR gm.{$messageTimeColumn} < glo.lead_ended_at
                   )
                LEFT JOIN ghl_users gu ON gu.UserID = glo.owner_user_id
                {$extraJoins}
                {$where['sql']}
                {$datePreFilter}
                GROUP BY
                    glo.owner_user_id,
                    owner_name,
                    glo.processed_lead_id,
                    glo.is_assigned_owner,
                    glo.assigned_to_user_id,
                    assigned_activity_at
                HAVING COUNT(*) > 3
            ) reply_created
            WHERE reply_created.reply_created_at BETWEEN ? AND ?
              AND NOT (
                  NULLIF(reply_created.assigned_to_user_id, '') = reply_created.owner_user_id
                  AND reply_created.assigned_activity_at BETWEEN ? AND ?
              )
        ";

        // Param order matches placeholder order: inner WHERE, then EXISTS window, then outer WHERE.
        $params = array_merge($where['params'], array($start, $end), array($start, $end, $start, $end));
        $rows = $this->db->query($sql, $params)->result_array();

        $this->replyCreatedBaseRowsCache[$cacheKey] = $rows;
        return $rows;
    }

    function Lead_Reply_Activity_Details_Summary($filters = array())
    {
        $summary = $this->Lead_Reply_Activity_Summary($filters);

        if (isset($filters['lead_type']) && $filters['lead_type'] === 'assigned') {
            $summary['reply_created_leads'] = 0;
        } elseif (isset($filters['lead_type']) && $filters['lead_type'] === 'reply_created') {
            $summary['assigned_leads'] = 0;
        }

        return $summary;
    }

    function Lead_Reply_Activity_Details_Rows($filters = array())
    {
        $rows = array();

        if (empty($filters['lead_type']) || $filters['lead_type'] === 'assigned') {
            $rows = array_merge($rows, $this->Lead_Reply_Activity_Assigned_Detail_Rows($filters));
        }

        if (empty($filters['lead_type']) || $filters['lead_type'] === 'reply_created') {
            $rows = array_merge($rows, $this->Lead_Reply_Activity_Reply_Created_Detail_Rows($filters));
        }

        usort($rows, function($a, $b) {
            if ($a['activity_at'] === $b['activity_at']) {
                return strcasecmp($a['owner_name'], $b['owner_name']);
            }
            return strcmp($b['activity_at'], $a['activity_at']);
        });

        return $rows;
    }

    private function Lead_Reply_Activity_Assigned_Detail_Rows($filters = array())
    {
        $where = $this->build_lead_reply_assignment_where_clause($filters);
        $extraJoins = isset($where['extra_joins']) ? $where['extra_joins'] : '';
        $messageTimeColumn = $this->escape_identifier($this->get_message_time_column());
        $assignmentDate = $this->lead_reply_assignment_date_expression('glo');

        $sql = "
            SELECT
                'Assigned Lead' AS activity_type,
                {$assignmentDate} AS activity_at,
                glo.processed_lead_id,
                glo.conversation_id,
                glo.contact_id,
                COALESCE(NULLIF(gc.contact_name, ''), NULLIF(gc.full_name, ''), 'Unknown Contact') AS contact_name,
                gc.phone,
                glo.owner_user_id,
                COALESCE(NULLIF(gu.Name, ''), glo.owner_user_id) AS owner_name,
                COALESCE(NULLIF(assigned_gu.Name, ''), NULLIF(glo.assigned_to_user_id, ''), 'Unassigned') AS assigned_name,
                glo.lead_started_at,
                NULL AS reply_created_at,
                (
                    SELECT COUNT(*)
                    FROM ghl_messages gm_count
                    WHERE gm_count.conversation_id = glo.conversation_id
                      AND gm_count.{$messageTimeColumn} >= glo.lead_started_at
                      AND (
                          glo.lead_ended_at IS NULL
                          OR gm_count.{$messageTimeColumn} < glo.lead_ended_at
                      )
                ) AS message_count,
                glo.outbound_reply_count AS outbound_replies,
                glo.follow_up_status,
                glo.is_converted,
                glo.booking_id,
                b.BookingNumber
            FROM ghl_lead_ownership glo
            LEFT JOIN ghl_conversations gc ON gc.conversation_id = glo.conversation_id
            LEFT JOIN ghl_users gu ON gu.UserID = glo.owner_user_id
            LEFT JOIN ghl_users assigned_gu ON assigned_gu.UserID = glo.assigned_to_user_id
            LEFT JOIN booking b ON b.BookingID = glo.booking_id
            {$extraJoins}
            {$where['sql']}
        ";

        return $this->db->query($sql, $where['params'])->result_array();
    }

    private function Lead_Reply_Activity_Reply_Created_Detail_Rows($filters = array())
    {
        $where = $this->build_lead_reply_created_where_clause($filters);
        $extraJoins = isset($where['extra_joins']) ? $where['extra_joins'] : '';
        $messageTimeColumn = $this->escape_identifier($this->get_message_time_column());
        $assignmentDate = $this->lead_reply_assignment_date_expression('glo');
        $start = !empty($filters['start_date']) ? $filters['start_date'] . ' 00:00:00' : '1970-01-01 00:00:00';
        $end = !empty($filters['end_date']) ? $filters['end_date'] . ' 23:59:59' : '9999-12-31 23:59:59';

        $sql = "
            SELECT *
            FROM (
                SELECT
                    'Reply-Created Lead' AS activity_type,
                    SUBSTRING_INDEX(
                        SUBSTRING_INDEX(GROUP_CONCAT(gm.{$messageTimeColumn} ORDER BY gm.{$messageTimeColumn} ASC, gm.id ASC), ',', 4),
                        ',',
                        -1
                    ) AS activity_at,
                    glo.processed_lead_id,
                    glo.conversation_id,
                    glo.contact_id,
                    COALESCE(NULLIF(gc.contact_name, ''), NULLIF(gc.full_name, ''), 'Unknown Contact') AS contact_name,
                    gc.phone,
                    glo.owner_user_id,
                    COALESCE(NULLIF(gu.Name, ''), glo.owner_user_id) AS owner_name,
                    glo.assigned_to_user_id,
                    {$assignmentDate} AS assigned_activity_at,
                    COALESCE(NULLIF(assigned_gu.Name, ''), NULLIF(glo.assigned_to_user_id, ''), 'Unassigned') AS assigned_name,
                    glo.lead_started_at,
                    SUBSTRING_INDEX(
                        SUBSTRING_INDEX(GROUP_CONCAT(gm.{$messageTimeColumn} ORDER BY gm.{$messageTimeColumn} ASC, gm.id ASC), ',', 4),
                        ',',
                        -1
                    ) AS reply_created_at,
                    (
                        SELECT COUNT(*)
                        FROM ghl_messages gm_count
                        WHERE gm_count.conversation_id = glo.conversation_id
                          AND gm_count.{$messageTimeColumn} >= glo.lead_started_at
                          AND (
                              glo.lead_ended_at IS NULL
                              OR gm_count.{$messageTimeColumn} < glo.lead_ended_at
                          )
                    ) AS message_count,
                    COUNT(*) AS outbound_replies,
                    glo.follow_up_status,
                    glo.is_converted,
                    glo.booking_id,
                    b.BookingNumber
                FROM ghl_lead_ownership glo
                INNER JOIN ghl_messages gm
                    ON gm.conversation_id = glo.conversation_id
                   AND gm.user_id = glo.owner_user_id
                   AND gm.direction = 'outbound'
                   AND gm.{$messageTimeColumn} >= glo.lead_started_at
                   AND (
                        glo.lead_ended_at IS NULL
                        OR gm.{$messageTimeColumn} < glo.lead_ended_at
                   )
                LEFT JOIN ghl_conversations gc ON gc.conversation_id = glo.conversation_id
                LEFT JOIN ghl_users gu ON gu.UserID = glo.owner_user_id
                LEFT JOIN ghl_users assigned_gu ON assigned_gu.UserID = glo.assigned_to_user_id
                LEFT JOIN booking b ON b.BookingID = glo.booking_id
                {$extraJoins}
                {$where['sql']}
                GROUP BY
                    glo.processed_lead_id,
                    glo.conversation_id,
                    glo.contact_id,
                    contact_name,
                    gc.phone,
                    glo.owner_user_id,
                    owner_name,
                    glo.assigned_to_user_id,
                    assigned_activity_at,
                    assigned_name,
                    glo.lead_started_at,
                    glo.lead_ended_at,
                    glo.follow_up_status,
                    glo.is_converted,
                    glo.booking_id,
                    b.BookingNumber
                HAVING COUNT(*) > 3
            ) reply_created
            WHERE reply_created.reply_created_at BETWEEN ? AND ?
              AND NOT (
                  NULLIF(reply_created.assigned_to_user_id, '') = reply_created.owner_user_id
                  AND reply_created.assigned_activity_at BETWEEN ? AND ?
              )
        ";

        $params = array_merge($where['params'], array($start, $end, $start, $end));
        return $this->db->query($sql, $params)->result_array();
    }

    function Lead_Reply_Activity_Mobile_Summary($mobile, $filters = array())
    {
        $where = $this->build_mobile_search_where_clause($mobile, $filters, 'gc.phone', 'glo.owner_user_id');
        if ($where['empty']) {
            return array(
                'conversation_count' => 0,
                'lead_count' => 0,
                'message_count' => 0,
                'inbound_message_count' => 0,
                'outbound_message_count' => 0,
                'owner_count' => 0,
                'first_message_at' => null,
                'last_message_at' => null,
            );
        }

        $messageTimeColumn = $this->escape_identifier($this->get_message_time_column());

        $sql = "
            SELECT
                COUNT(DISTINCT gc.conversation_id) AS conversation_count,
                COUNT(DISTINCT pl.id) AS lead_count,
                COUNT(DISTINCT gm.id) AS message_count,
                COUNT(DISTINCT CASE WHEN gm.direction = 'inbound' THEN gm.id END) AS inbound_message_count,
                COUNT(DISTINCT CASE WHEN gm.direction = 'outbound' THEN gm.id END) AS outbound_message_count,
                COUNT(DISTINCT NULLIF(glo.owner_user_id, '')) AS owner_count,
                MIN(gm.{$messageTimeColumn}) AS first_message_at,
                MAX(gm.{$messageTimeColumn}) AS last_message_at
            FROM ghl_conversations gc
            LEFT JOIN ghl_processed_leads pl ON pl.conversation_id = gc.conversation_id
            LEFT JOIN ghl_messages gm ON gm.conversation_id = gc.conversation_id
            LEFT JOIN ghl_lead_ownership glo ON glo.processed_lead_id = pl.id
            {$where['sql']}
        ";

        $row = $this->db->query($sql, $where['params'])->row_array();

        return array(
            'conversation_count' => !empty($row['conversation_count']) ? (int) $row['conversation_count'] : 0,
            'lead_count' => !empty($row['lead_count']) ? (int) $row['lead_count'] : 0,
            'message_count' => !empty($row['message_count']) ? (int) $row['message_count'] : 0,
            'inbound_message_count' => !empty($row['inbound_message_count']) ? (int) $row['inbound_message_count'] : 0,
            'outbound_message_count' => !empty($row['outbound_message_count']) ? (int) $row['outbound_message_count'] : 0,
            'owner_count' => !empty($row['owner_count']) ? (int) $row['owner_count'] : 0,
            'first_message_at' => isset($row['first_message_at']) ? $row['first_message_at'] : null,
            'last_message_at' => isset($row['last_message_at']) ? $row['last_message_at'] : null,
        );
    }

    function Lead_Reply_Activity_Mobile_Leads($mobile, $filters = array())
    {
        $where = $this->build_mobile_search_where_clause($mobile, $filters, 'gc.phone', 'pl.assigned_to_user_id');
        if ($where['empty']) {
            return array();
        }

        $messageTimeColumn = $this->escape_identifier($this->get_message_time_column());

        $sql = "
            SELECT
                pl.id AS processed_lead_id,
                pl.conversation_id,
                COALESCE(NULLIF(gc.contact_name, ''), NULLIF(gc.full_name, ''), 'Unknown Contact') AS contact_name,
                gc.phone,
                COALESCE(NULLIF(gu.Name, ''), NULLIF(pl.assigned_to_user_id, ''), 'Unassigned') AS agent_name,
                pl.lead_started_at,
                pl.lead_ended_at,
                pl.responded_message_count,
                pl.tracked_message_count,
                (
                    SELECT COUNT(*)
                    FROM ghl_messages gm_count
                    WHERE gm_count.conversation_id = pl.conversation_id
                      AND gm_count.{$messageTimeColumn} >= pl.lead_started_at
                      AND (
                          pl.lead_ended_at IS NULL
                          OR gm_count.{$messageTimeColumn} < pl.lead_ended_at
                      )
                ) AS message_count,
                pl.is_converted,
                b.BookingNumber
            FROM ghl_processed_leads pl
            LEFT JOIN ghl_conversations gc ON gc.conversation_id = pl.conversation_id
            LEFT JOIN ghl_users gu ON gu.UserID = pl.assigned_to_user_id
            LEFT JOIN booking b ON b.BookingID = pl.booking_id
            {$where['sql']}
            ORDER BY pl.lead_started_at DESC, pl.id DESC
            LIMIT 100
        ";

        return $this->db->query($sql, $where['params'])->result_array();
    }

    function Lead_Reply_Activity_Mobile_Owners($mobile, $filters = array())
    {
        $where = $this->build_mobile_search_where_clause($mobile, $filters, 'gc.phone', 'glo.owner_user_id');
        if ($where['empty']) {
            return array();
        }

        $sql = "
            SELECT
                glo.owner_user_id,
                COALESCE(NULLIF(gu.Name, ''), glo.owner_user_id) AS owner_name,
                COUNT(DISTINCT glo.processed_lead_id) AS lead_count,
                SUM(glo.outbound_reply_count) AS outbound_reply_count
            FROM ghl_lead_ownership glo
            LEFT JOIN ghl_conversations gc ON gc.conversation_id = glo.conversation_id
            LEFT JOIN ghl_users gu ON gu.UserID = glo.owner_user_id
            {$where['sql']}
            GROUP BY glo.owner_user_id, owner_name
            ORDER BY lead_count DESC, outbound_reply_count DESC, owner_name ASC
        ";

        return $this->db->query($sql, $where['params'])->result_array();
    }

    /**
     * Active (unconverted) leads grouped by allowlisted GHL tag, broken into
     * destination / language / race buckets. Powers the TC LEAD card
     * "Active Leads by Tag" — see ghl_tag_categories_helper.php for the
     * allowlist and the bucketing rules.
     *
     * One SELECT fetches every unconverted lead + its raw tags_json; the
     * PHP-side aggregator handles the per-tag bucketing. We deliberately
     * avoid per-tag JSON_CONTAINS queries (one per allowlisted tag, ~100+
     * round-trips) because JSON columns aren't indexed by content and the
     * dashboard re-runs this card on every page load.
     */
    function Active_Leads_By_Tag($category_tags)
    {
        $this->load->helper('ghl_tag_categories');

        $rows = $this->db->query(
            "SELECT pl.id, gc.tags_json
             FROM ghl_processed_leads pl
             LEFT JOIN ghl_conversations gc ON gc.conversation_id = pl.conversation_id
             WHERE pl.is_converted = 0
               AND gc.tags_json IS NOT NULL
               AND JSON_LENGTH(gc.tags_json) > 0"
        )->result_array();

        return ghl_aggregate_active_leads_by_tag($category_tags, $rows);
    }

    function Lead_Dashboard_Agents($restrict_agent_ids = null)
    {
        $params = array();
        $extra = '';
        if (is_array($restrict_agent_ids)) {
            if (empty($restrict_agent_ids)) {
                return array();
            }
            $placeholders = implode(',', array_fill(0, count($restrict_agent_ids), '?'));
            $extra = " AND user_ref.agent_id IN ({$placeholders}) ";
            $params = array_values(array_map('strval', $restrict_agent_ids));
        }

        $sql = "
            SELECT DISTINCT
                user_ref.agent_id,
                COALESCE(NULLIF(gu.Name, ''), user_ref.agent_id) AS agent_name
            FROM (
                SELECT NULLIF(assigned_to_user_id, '') AS agent_id
                FROM ghl_processed_leads
                WHERE assigned_to_user_id IS NOT NULL AND assigned_to_user_id <> ''
            ) user_ref
            LEFT JOIN ghl_users gu ON gu.UserID = user_ref.agent_id
            WHERE user_ref.agent_id IS NOT NULL AND user_ref.agent_id <> '' {$extra}
            ORDER BY agent_name ASC
        ";

        return $this->db->query($sql, $params)->result();
    }

    function Get_Allowed_Lead_Dashboard_Agents($admin_id)
    {
        $admin_id = (int) $admin_id;
        if ($admin_id <= 0) return array();
        $this->db->select('GhlUserID');
        $this->db->where('AdminID', $admin_id);
        $rows = $this->db->get('admin_lead_dashboard_agents')->result();
        return array_map(function($r) { return $r->GhlUserID; }, $rows);
    }

    /**
     * Resolve an admin to their OWN GHL UserID(s): the admin_lead_dashboard_agents
     * mapping is canonical, with an admin.Email <-> ghl_users.Email match as a
     * fallback for admins whose mapping hasn't been configured yet. Used to
     * self-scope a sales agent's Lead Reply Activity dashboard to themselves
     * (mirrors the sales-agent summary-card resolver in Booking.php).
     *
     * @param int $admin_id
     * @return array list of GHL UserID strings (empty when no identity exists)
     */
    function Resolve_Self_Ghl_Agents($admin_id)
    {
        $admin_id = (int) $admin_id;
        if ($admin_id <= 0) return array();

        $mapped = $this->Get_Allowed_Lead_Dashboard_Agents($admin_id);
        $mapped = array_values(array_filter(array_map('strval', $mapped), 'strlen'));
        if (!empty($mapped)) return $mapped;

        $rows = $this->db->query(
            "SELECT gu.UserID
             FROM admin a
             INNER JOIN ghl_users gu ON LOWER(TRIM(gu.Email)) = LOWER(TRIM(a.Email))
             WHERE a.AdminID = ?",
            array($admin_id)
        )->result();
        return array_values(array_filter(array_map(function($r) {
            return (string) $r->UserID;
        }, $rows), 'strlen'));
    }

    function Lead_Data_Total_Count($filters = array())
    {
        $where = $this->build_lead_dashboard_where_clause($filters);
        $extraJoins = isset($where['extra_joins']) ? $where['extra_joins'] : '';

        $sql = "
            SELECT COUNT(*) AS total_rows
            FROM ghl_processed_leads pl
            LEFT JOIN ghl_conversations gc ON gc.conversation_id = pl.conversation_id
            {$extraJoins}
            {$where['sql']}
        ";

        $row = $this->db->query($sql, $where['params'])->row_array();
        return !empty($row['total_rows']) ? (int) $row['total_rows'] : 0;
    }

    function Lead_Data_Rows($filters = array(), $limit = null, $offset = null)
    {
        $messageTimeColumn = $this->escape_identifier($this->get_message_time_column());
        $where = $this->build_lead_dashboard_where_clause($filters);
        $extraJoins = isset($where['extra_joins']) ? $where['extra_joins'] : '';
        $limit = $limit !== null ? max(1, (int) $limit) : null;
        $offset = $offset !== null ? max(0, (int) $offset) : null;
        $order = $this->build_lead_data_order_clause($filters);

        $sql = "
            SELECT
                pl.id,
                pl.conversation_id,
                pl.contact_id,
                COALESCE(NULLIF(gc.contact_name, ''), NULLIF(gc.full_name, ''), 'Unknown Contact') AS contact_name,
                gc.phone,
                gc.tags_json,
                COALESCE(NULLIF(pl.assigned_to_user_id, ''), '__unassigned__') AS agent_id,
                COALESCE(NULLIF(gu.Name, ''), NULLIF(pl.assigned_to_user_id, ''), 'Unassigned') AS agent_name,
                pl.lead_started_at,
                pl.lead_ended_at,
                pl.first_customer_message_id,
                pl.tracked_message_count,
                pl.responded_message_count,
                pl.avg_first_5_response_seconds,
                pl.recent_tracked_message_count,
                pl.recent_responded_message_count,
                pl.avg_recent_5_response_seconds,
                pl.follow_up_status,
                pl.follow_up_sent_at,
                pl.follow_up_replied_at,
                pl.follow_up_expired_at,
                pl.response_1_customer_message_at,
                pl.response_1_agent_message_at,
                pl.response_2_customer_message_at,
                pl.response_2_agent_message_at,
                pl.response_3_customer_message_at,
                pl.response_3_agent_message_at,
                pl.response_4_customer_message_at,
                pl.response_4_agent_message_at,
                pl.response_5_customer_message_at,
                pl.response_5_agent_message_at,
                pl.response_1_seconds,
                pl.response_2_seconds,
                pl.response_3_seconds,
                pl.response_4_seconds,
                pl.response_5_seconds,
                pl.recent_response_1_customer_message_at,
                pl.recent_response_1_agent_message_at,
                pl.recent_response_2_customer_message_at,
                pl.recent_response_2_agent_message_at,
                pl.recent_response_3_customer_message_at,
                pl.recent_response_3_agent_message_at,
                pl.recent_response_4_customer_message_at,
                pl.recent_response_4_agent_message_at,
                pl.recent_response_5_customer_message_at,
                pl.recent_response_5_agent_message_at,
                pl.recent_response_1_seconds,
                pl.recent_response_2_seconds,
                pl.recent_response_3_seconds,
                pl.recent_response_4_seconds,
                pl.recent_response_5_seconds,
                pl.is_converted,
                pl.booking_id,
                pl.converted_at,
                b.BookingNumber,
                (
                    SELECT COUNT(*)
                    FROM ghl_messages gm_count
                    WHERE gm_count.conversation_id = pl.conversation_id
                      AND gm_count.{$messageTimeColumn} >= pl.lead_started_at
                      AND (
                          pl.lead_ended_at IS NULL
                          OR gm_count.{$messageTimeColumn} < pl.lead_ended_at
                      )
                ) AS message_count
            FROM ghl_processed_leads pl
            LEFT JOIN ghl_conversations gc ON gc.conversation_id = pl.conversation_id
            LEFT JOIN ghl_users gu ON gu.UserID = NULLIF(pl.assigned_to_user_id, '')
            LEFT JOIN booking b ON b.BookingID = pl.booking_id
            {$extraJoins}
            {$where['sql']}
            {$order}
        ";

        $params = $where['params'];

        if ($limit !== null) {
            $sql .= " LIMIT ?";
            $params[] = $limit;

            if ($offset !== null) {
                $sql .= " OFFSET ?";
                $params[] = $offset;
            }
        }

        return $this->db->query($sql, $params)->result_array();
    }

    function Lead_Data_Tag_Options($filters = array())
    {
        $optionFilters = $filters;
        unset($optionFilters['tag']);

        $where = $this->build_lead_dashboard_where_clause($optionFilters);
        $extraJoins = isset($where['extra_joins']) ? $where['extra_joins'] : '';

        $sql = "
            SELECT gc.tags_json
            FROM ghl_processed_leads pl
            LEFT JOIN ghl_conversations gc ON gc.conversation_id = pl.conversation_id
            {$extraJoins}
            {$where['sql']}
              " . (!empty($where['sql']) ? 'AND' : 'WHERE') . " gc.tags_json IS NOT NULL
              AND JSON_LENGTH(gc.tags_json) > 0
        ";

        $rows = $this->db->query($sql, $where['params'])->result_array();
        $tags = array();

        foreach ($rows as $row) {
            foreach ($this->extract_ghl_tags(isset($row['tags_json']) ? $row['tags_json'] : null) as $tag) {
                $tags[$tag] = $tag;
            }
        }

        $tags = array_values($tags);
        usort($tags, 'strcasecmp');

        return $tags;
    }

    function Lead_Data_Messages($conversationId, $leadStartedAt, $nextLeadStartedAt = null)
    {
        $messageTimeColumn = $this->escape_identifier($this->get_message_time_column());
        $params = array(
            (string) $conversationId,
            (string) $leadStartedAt,
        );

        $sql = "
            SELECT
                gm.message_id,
                gm.direction,
                COALESCE(NULLIF(gu.Name, ''), NULLIF(gm.user_id, ''), '') AS user_name,
                gm.message_type,
                gm.body,
                gm.attachments_json,
                gm.{$messageTimeColumn} AS message_timestamp
            FROM ghl_messages gm
            LEFT JOIN ghl_users gu ON gu.UserID = gm.user_id
            WHERE gm.conversation_id = ?
              AND gm.{$messageTimeColumn} >= ?
        ";

        if ($nextLeadStartedAt !== null && $nextLeadStartedAt !== '') {
            $sql .= " AND gm.{$messageTimeColumn} < ?";
            $params[] = (string) $nextLeadStartedAt;
        }

        $sql .= " ORDER BY gm.{$messageTimeColumn} ASC, gm.id ASC";

        return $this->db->query($sql, $params)->result_array();
    }

    function Lead_Data_Last_Synced_At()
    {
        $row = $this->db
            ->select('MAX(updated_at) AS updated_at', false)
            ->from('ghl_processed_leads')
            ->get()
            ->row_array();

        return !empty($row['updated_at']) ? $row['updated_at'] : null;
    }

    /**
     * One page of raw GHL messages (newest first) for the Message Log. Selects
     * only the four display columns within the inclusive date window and bounds
     * the result with LIMIT/OFFSET so the page is memory-safe regardless of how
     * many rows match.
     *
     * @param string $startDate 'Y-m-d' inclusive lower bound.
     * @param string $endDate   'Y-m-d' inclusive upper bound (whole day covered).
     * @param int    $limit     Rows per page.
     * @param int    $offset    Rows to skip.
     * @param string $contact   Optional contact number; when set, only the
     *                          two-way thread for that number is returned
     *                          (matched against from_number OR to_number).
     * @return array Rows keyed: message_timestamp, direction, from_number,
     *               to_number, agent, body.
     */
    function Ghl_Messages_Log($startDate, $endDate, $limit, $offset, $contact = '', $agent = '')
    {
        $messageTimeColumn = $this->escape_identifier($this->get_message_time_column());

        $params = array(
            $startDate . ' 00:00:00',
            $endDate . ' 23:59:59',
        );
        $contactClause = $this->ghl_message_contact_clause($contact, $params);
        $agentClause = $this->ghl_message_agent_clause($agent, $params);
        $params[] = (int) $limit;
        $params[] = (int) $offset;

        $sql = "
            SELECT
                gm.{$messageTimeColumn} AS message_timestamp,
                gm.direction AS direction,
                gm.from_number AS from_number,
                gm.to_number AS to_number,
                COALESCE(NULLIF(gu.Name, ''), NULLIF(gu_assigned.Name, '')) AS agent,
                gm.body AS body
            FROM ghl_messages gm
            LEFT JOIN ghl_users gu ON gu.UserID = gm.user_id
            LEFT JOIN ghl_conversations gc ON gc.conversation_id = gm.conversation_id
            LEFT JOIN ghl_users gu_assigned ON gu_assigned.UserID = gc.assigned_to
            WHERE gm.{$messageTimeColumn} >= ?
              AND gm.{$messageTimeColumn} <= ?
              {$contactClause}
              {$agentClause}
            ORDER BY gm.{$messageTimeColumn} DESC, gm.id DESC
            LIMIT ? OFFSET ?
        ";

        return $this->db->query($sql, $params)->result_array();
    }

    /**
     * One chunk of raw GHL messages shaped for the CSV export. Unlike the
     * on-screen page (newest-first, flat), the export is meant to be analysed one
     * chatroom at a time, so rows are grouped by contact (then conversation) and
     * ordered oldest-first within a thread -- the natural reading order of a chat.
     * A resolved contact name is selected as the leading column so each chatroom
     * is labelled.
     *
     * The ORDER BY is total and deterministic, so chunked LIMIT/OFFSET paging
     * keeps every thread contiguous across chunk boundaries.
     *
     * @param string $startDate 'Y-m-d' inclusive lower bound.
     * @param string $endDate   'Y-m-d' inclusive upper bound (whole day covered).
     * @param int    $limit     Rows per chunk.
     * @param int    $offset    Rows to skip.
     * @param string $contact   Optional contact number; when set, only the
     *                          two-way thread for that number is returned.
     * @return array Rows keyed: contact_name, message_timestamp, direction,
     *               from_number, to_number, agent, body.
     */
    function Ghl_Messages_Log_Export($startDate, $endDate, $limit, $offset, $contact = '', $agent = '')
    {
        $messageTimeColumn = $this->escape_identifier($this->get_message_time_column());

        $params = array(
            $startDate . ' 00:00:00',
            $endDate . ' 23:59:59',
        );
        $contactClause = $this->ghl_message_contact_clause($contact, $params);
        $agentClause = $this->ghl_message_agent_clause($agent, $params);
        $params[] = (int) $limit;
        $params[] = (int) $offset;

        $sql = "
            SELECT
                COALESCE(NULLIF(gc.contact_name, ''), NULLIF(gc.full_name, ''), '') AS contact_name,
                gm.{$messageTimeColumn} AS message_timestamp,
                gm.direction AS direction,
                gm.from_number AS from_number,
                gm.to_number AS to_number,
                COALESCE(NULLIF(gu.Name, ''), NULLIF(gu_assigned.Name, '')) AS agent,
                gm.body AS body
            FROM ghl_messages gm
            LEFT JOIN ghl_users gu ON gu.UserID = gm.user_id
            LEFT JOIN ghl_conversations gc ON gc.conversation_id = gm.conversation_id
            LEFT JOIN ghl_users gu_assigned ON gu_assigned.UserID = gc.assigned_to
            WHERE gm.{$messageTimeColumn} >= ?
              AND gm.{$messageTimeColumn} <= ?
              {$contactClause}
              {$agentClause}
            ORDER BY COALESCE(gc.contact_id, gm.conversation_id, '') ASC,
                     gm.conversation_id ASC,
                     gm.{$messageTimeColumn} ASC,
                     gm.id ASC
            LIMIT ? OFFSET ?
        ";

        return $this->db->query($sql, $params)->result_array();
    }

    /**
     * Count of raw GHL messages in the date window, to drive Message Log
     * pagination.
     *
     * @param string $startDate 'Y-m-d' inclusive lower bound.
     * @param string $endDate   'Y-m-d' inclusive upper bound (whole day covered).
     * @param string $contact   Optional contact number; counts only the two-way
     *                          thread for that number when set.
     * @return int
     */
    function Ghl_Messages_Log_Count($startDate, $endDate, $contact = '', $agent = '')
    {
        $messageTimeColumn = $this->escape_identifier($this->get_message_time_column());

        $params = array($startDate . ' 00:00:00', $endDate . ' 23:59:59');
        $contactClause = $this->ghl_message_contact_clause($contact, $params);
        $agentClause = $this->ghl_message_agent_clause($agent, $params);

        // The agent filter compares the resolved agent name, so the user/conversation
        // joins are only needed -- and only added -- when an agent is selected. They
        // stay out of the unfiltered count to avoid any chance of row fan-out.
        $agentJoins = $agentClause !== ''
            ? " LEFT JOIN ghl_users gu ON gu.UserID = gm.user_id
                LEFT JOIN ghl_conversations gc ON gc.conversation_id = gm.conversation_id
                LEFT JOIN ghl_users gu_assigned ON gu_assigned.UserID = gc.assigned_to "
            : '';

        $row = $this->db->query(
            "SELECT COUNT(*) AS total
               FROM ghl_messages gm
               {$agentJoins}
              WHERE gm.{$messageTimeColumn} >= ?
                AND gm.{$messageTimeColumn} <= ?
                {$contactClause}
                {$agentClause}",
            $params
        )->row_array();

        return isset($row['total']) ? (int) $row['total'] : 0;
    }

    /**
     * Average agent reply time (in seconds) for the Message Log, across the whole
     * filtered range -- NOT just the visible page. Measures how fast the agent
     * answers a customer: each INBOUND message answered by the next OUTBOUND
     * message in the same conversation, counting only pairs that stay inside
     * working hours (everyday 7AM-10PM); pairs that leave the window are excluded.
     *
     * Messages are pulled grouped by conversation and ascending in time so the
     * adjacent inbound->outbound pairing in ghl_message_log_average_reply_seconds()
     * is sound. The reduction (direction pairing, business-hours eligibility) is
     * done in PHP because it cannot be expressed as a single GROUP BY.
     *
     * @param string $startDate 'Y-m-d' inclusive lower bound.
     * @param string $endDate   'Y-m-d' inclusive upper bound.
     * @param string $contact   Optional contact-number filter.
     * @param string $agent     Optional resolved agent-name filter.
     * @return float|null Average seconds, or null when nothing qualifies.
     */
    function Ghl_Messages_Log_Avg_Reply_Seconds($startDate, $endDate, $contact = '', $agent = '')
    {
        $messageTimeColumn = $this->escape_identifier($this->get_message_time_column());

        $params = array($startDate . ' 00:00:00', $endDate . ' 23:59:59');
        $contactClause = $this->ghl_message_contact_clause($contact, $params);
        $agentClause = $this->ghl_message_agent_clause($agent, $params);

        // Joins are needed only when filtering by the resolved agent name.
        $agentJoins = $agentClause !== ''
            ? " LEFT JOIN ghl_users gu ON gu.UserID = gm.user_id
                LEFT JOIN ghl_conversations gc ON gc.conversation_id = gm.conversation_id
                LEFT JOIN ghl_users gu_assigned ON gu_assigned.UserID = gc.assigned_to "
            : '';

        $rows = $this->db->query(
            "SELECT gm.conversation_id AS conversation_id,
                    gm.direction AS direction,
                    gm.{$messageTimeColumn} AS ts
               FROM ghl_messages gm
               {$agentJoins}
              WHERE gm.{$messageTimeColumn} >= ?
                AND gm.{$messageTimeColumn} <= ?
                {$contactClause}
                {$agentClause}
              ORDER BY gm.conversation_id ASC, gm.{$messageTimeColumn} ASC, gm.id ASC",
            $params
        )->result_array();

        return ghl_message_log_average_reply_seconds($rows);
    }

    /**
     * Average agent reply time (Message-Log logic) for a set of GHL user IDs,
     * windowed by message timestamp. This is the per-agent figure behind the
     * dashboard "Avg Reply Time to Inbound" card: it scans raw ghl_messages and
     * pairs each inbound customer message with the agent's next outbound reply in
     * the same conversation, counting only in-hours same-day pairs (see
     * ghl_message_log_average_reply_seconds()). Scoping mirrors the Message Log's
     * resolved-agent idea but keyed by ID -- a row belongs to the agent when its
     * sender (gm.user_id) is one of the IDs, or, for customer inbound messages
     * with no sender, when the conversation is assigned to one of them. The whole
     * matching conversation thread is therefore included so the inbound->outbound
     * pairing stays intact.
     *
     * @param string $startDate 'Y-m-d' inclusive lower bound (message date).
     * @param string $endDate   'Y-m-d' inclusive upper bound (message date).
     * @param array  $uids      GHL user IDs to scope to.
     * @return float|null Average seconds, or null when no pair qualifies.
     */
    function Ghl_Messages_Avg_Reply_Seconds_For_Uids($startDate, $endDate, $uids)
    {
        $uids = array_values(array_filter(array_map('strval', (array) $uids), 'strlen'));
        if (empty($uids)) {
            return null;
        }

        $this->load->helper('ghl_messages_log');
        $messageTimeColumn = $this->escape_identifier($this->get_message_time_column());
        $placeholders = implode(',', array_fill(0, count($uids), '?'));

        $params = array($startDate . ' 00:00:00', $endDate . ' 23:59:59');
        foreach ($uids as $u) {
            $params[] = $u;
        }

        $rows = $this->db->query(
            "SELECT gm.conversation_id AS conversation_id,
                    gm.direction AS direction,
                    gm.{$messageTimeColumn} AS ts
               FROM ghl_messages gm
               LEFT JOIN ghl_conversations gc ON gc.conversation_id = gm.conversation_id
              WHERE gm.{$messageTimeColumn} >= ?
                AND gm.{$messageTimeColumn} <= ?
                AND COALESCE(NULLIF(gm.user_id, ''), gc.assigned_to) IN ({$placeholders})
              ORDER BY gm.conversation_id ASC, gm.{$messageTimeColumn} ASC, gm.id ASC",
            $params
        )->result_array();

        return ghl_message_log_average_reply_seconds($rows);
    }

    /**
     * Per-agent average reply time (Message-Log logic) across the whole team for
     * a message-date window, feeding the card's team-wide "Best:" benchmark so it
     * is computed identically to each agent's own number. Returns one row per
     * resolved agent (sender, or chatroom assignee for customer inbound rows)
     * shaped like Lead_Dashboard_By_Agent so the caller's existing leaderboard
     * picker can be reused: 'agent_id', 'agent_name', 'total_leads' (distinct
     * conversations that produced at least one counted reply -- the min-sample
     * basis) and 'avg_response_time_seconds'.
     *
     * @param string $startDate 'Y-m-d' inclusive lower bound (message date).
     * @param string $endDate   'Y-m-d' inclusive upper bound (message date).
     * @return array List of agent rows (unscoped; caller restricts to a role pool).
     */
    function Ghl_Messages_Avg_Reply_By_Agent($startDate, $endDate)
    {
        $this->load->helper('ghl_messages_log');
        $messageTimeColumn = $this->escape_identifier($this->get_message_time_column());

        $params = array($startDate . ' 00:00:00', $endDate . ' 23:59:59');
        $rows = $this->db->query(
            "SELECT COALESCE(NULLIF(gm.user_id, ''), gc.assigned_to) AS agent_id,
                    gm.conversation_id AS conversation_id,
                    gm.direction AS direction,
                    gm.{$messageTimeColumn} AS ts,
                    COALESCE(NULLIF(gu.Name, ''), NULLIF(gu_assigned.Name, '')) AS agent_name
               FROM ghl_messages gm
               LEFT JOIN ghl_users gu ON gu.UserID = gm.user_id
               LEFT JOIN ghl_conversations gc ON gc.conversation_id = gm.conversation_id
               LEFT JOIN ghl_users gu_assigned ON gu_assigned.UserID = gc.assigned_to
              WHERE gm.{$messageTimeColumn} >= ?
                AND gm.{$messageTimeColumn} <= ?
              ORDER BY agent_id ASC, gm.conversation_id ASC, gm.{$messageTimeColumn} ASC, gm.id ASC",
            $params
        )->result_array();

        $byGroup = ghl_message_log_average_reply_seconds_by_group($rows, 'agent_id', 'ts');

        // First non-empty resolved name seen per agent id (sender name on the
        // agent's own outbound rows, assignee name on customer inbound rows).
        $names = array();
        foreach ($rows as $r) {
            $aid = (string) (isset($r['agent_id']) ? $r['agent_id'] : '');
            if ($aid === '' || isset($names[$aid])) {
                continue;
            }
            if (isset($r['agent_name']) && $r['agent_name'] !== '' && $r['agent_name'] !== null) {
                $names[$aid] = $r['agent_name'];
            }
        }

        $results = array();
        foreach ($byGroup as $aid => $stat) {
            $aid = (string) $aid;
            if ($aid === '') {
                continue;
            }
            $results[] = array(
                'agent_id'   => $aid,
                'agent_name' => isset($names[$aid]) ? $names[$aid] : $aid,
                'total_leads' => (int) $stat['lead_count'],
                'avg_response_time_seconds' => $stat['avg_seconds'] !== null
                    ? (int) round($stat['avg_seconds'])
                    : null,
            );
        }

        return $results;
    }

    /**
     * Distinct agent names available to the Message Log agent filter. Restricted
     * to agents that actually appear in the log -- either as a message sender
     * (ghl_messages.user_id) or as a chatroom assignee (ghl_conversations.
     * assigned_to) -- so the dropdown never pads out with named GHL users who
     * never touched a conversation. Names match the resolved Agent column exactly.
     *
     * @return array List of agent name strings, ordered A-Z.
     */
    function Ghl_Message_Log_Agents()
    {
        $rows = $this->db->query(
            "SELECT DISTINCT gu.Name
               FROM ghl_users gu
              WHERE gu.Name IS NOT NULL AND gu.Name <> ''
                AND (
                    gu.UserID IN (SELECT user_id FROM ghl_messages WHERE user_id IS NOT NULL AND user_id <> '')
                    OR gu.UserID IN (SELECT assigned_to FROM ghl_conversations WHERE assigned_to IS NOT NULL AND assigned_to <> '')
                )
              ORDER BY gu.Name ASC"
        )->result_array();

        return array_column($rows, 'Name');
    }

    /**
     * Build the optional contact-number WHERE fragment (and append its bound
     * params) for the Message Log queries. Both the column and the typed value
     * are reduced to digits only, so any phone format matches the same lead.
     * Matching either from_number OR to_number returns the full two-way thread.
     *
     * @param string $contact Raw contact filter.
     * @param array  $params  Query params, appended to in place.
     * @return string SQL fragment beginning with ' AND ...', or '' when no filter.
     */
    protected function ghl_message_contact_clause($contact, array &$params)
    {
        $digits = preg_replace('/\D+/', '', (string) $contact);
        if ($digits === '') {
            return '';
        }

        $normFrom = $this->normalize_phone_sql('gm.from_number');
        $normTo = $this->normalize_phone_sql('gm.to_number');

        $like = '%' . $digits . '%';
        $params[] = $like;
        $params[] = $like;

        return " AND ({$normFrom} LIKE ? OR {$normTo} LIKE ?)";
    }

    /**
     * Build the optional agent WHERE fragment (and append its bound param) for the
     * Message Log queries. The filter matches the *resolved* agent -- the same
     * COALESCE(sender name, assigned-chatroom name) shown in the Agent column --
     * so the filtered list always agrees with what the reader sees. Requires the
     * gu / gu_assigned joins to be present in the query.
     *
     * @param string $agent  Resolved agent name to match exactly.
     * @param array  $params Query params, appended to in place.
     * @return string SQL fragment beginning with ' AND ...', or '' when no filter.
     */
    protected function ghl_message_agent_clause($agent, array &$params)
    {
        $agent = trim((string) $agent);
        if ($agent === '') {
            return '';
        }

        $params[] = $agent;

        return " AND COALESCE(NULLIF(gu.Name, ''), NULLIF(gu_assigned.Name, '')) = ?";
    }

    /**
     * SQL expression that strips '+', spaces, dashes and parentheses from a
     * phone column so it can be compared digit-for-digit against a normalized
     * filter value.
     *
     * @param string $column Qualified column name.
     * @return string
     */
    protected function normalize_phone_sql($column)
    {
        return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$column}, '+', ''), ' ', ''), '-', ''), '(', ''), ')', '')";
    }

    private function build_lead_data_order_clause($filters = array())
    {
        $sortBy = isset($filters['sort_by']) ? (string) $filters['sort_by'] : 'lead_started_at';
        $sortDir = isset($filters['sort_dir']) && strtolower((string) $filters['sort_dir']) === 'asc' ? 'ASC' : 'DESC';

        switch ($sortBy) {
            case 'contact_name':
                return "ORDER BY contact_name {$sortDir}, pl.id DESC";
            case 'agent_name':
                return "ORDER BY agent_name {$sortDir}, pl.id DESC";
            case 'conversation_id':
                return "ORDER BY pl.conversation_id {$sortDir}, pl.id DESC";
            case 'response_status':
                return "ORDER BY (CASE WHEN pl.responded_message_count > 0 THEN 1 ELSE 0 END) {$sortDir}, pl.id DESC";
            case 'response_time':
                return "ORDER BY (CASE WHEN pl.avg_first_5_response_seconds IS NULL THEN 1 ELSE 0 END) ASC, pl.avg_first_5_response_seconds {$sortDir}, pl.id DESC";
            case 'conversion_status':
                return "ORDER BY pl.is_converted {$sortDir}, pl.converted_at {$sortDir}, pl.id DESC";
            case 'follow_up_status':
                return "ORDER BY FIELD(pl.follow_up_status, 'completed', 'sent', 'pending') {$sortDir}, pl.id DESC";
            case 'message_count':
                return "ORDER BY message_count {$sortDir}, pl.id DESC";
            case 'lead_started_at':
            default:
                return "ORDER BY pl.lead_started_at {$sortDir}, pl.id DESC";
        }
    }

    protected function get_message_time_column()
    {
        if ($this->messageTimeColumn !== null) {
            return $this->messageTimeColumn;
        }

        $fields = $this->db->list_fields('ghl_messages');

        if (in_array('timestamp', $fields, true)) {
            $this->messageTimeColumn = 'timestamp';
            return $this->messageTimeColumn;
        }

        if (in_array('date_added', $fields, true)) {
            $this->messageTimeColumn = 'date_added';
            return $this->messageTimeColumn;
        }

        show_error('Unable to detect message timestamp column on ghl_messages.', 500);
    }

    protected function escape_identifier($identifier)
    {
        return '`' . str_replace('`', '', (string) $identifier) . '`';
    }
    
    function Destinations()
	{
		$this->db->select('CategoryID, Name');
		$this->db->where('IsDestination', 'YES');
		$this->db->where('Status', 'Y');
        $this->db->order_by('Name', 'ASC');
		$destinations = $this->db->get('category');
		return $destinations->result();
	}

    function Sales_Agents()
	{
		$this->db->select('AdminID, Name');
		$this->db->where('AdminID !=', 8);
		$this->db->where('Level !=', '30');
		$this->db->where('Status', 'Y');
        $this->db->order_by('Name', 'ASC');
		$sales_agents = $this->db->get('admin');
		return $sales_agents->result();
	}

    function Cities()
	{
		$this->db->select('City');
		$this->db->where('City !=', '');
		$this->db->where('Status', 'Y');
        $this->db->order_by('City', 'ASC');
        $this->db->distinct();
		$cities = $this->db->get('category');
		return $cities->result();
	}

    function States()
	{
		$this->db->select('State');
		$this->db->where('State !=', '');
		$this->db->where('Status', 'Y');
        $this->db->order_by('State', 'ASC');
        $this->db->distinct();
		$states = $this->db->get('category');
		return $states->result();
	}

    function Countries()
	{
		$this->db->select('CountryCodeID, Country');
		$this->db->where('Status', 'Y');
        $this->db->order_by('Country', 'ASC');
		$countries = $this->db->get('country_code');
		return $countries->result();
	}

    function Products()
	{
		$this->db->select('ProductID, ProductCode, Name');
		$this->db->where('Status', 'Y');
        $this->db->order_by('Name', 'ASC');
		$products = $this->db->get('product');
		return $products->result();
	}

    function Sources()
	{
		$this->db->select('SourceID, Name');
		$this->db->where('Status', 'Y');
        $this->db->order_by('Name', 'ASC');
		$sources = $this->db->get('source');
		return $sources->result();
	}

    function Report()
	{
		$this->db->select('BookingID, booking.InsertDate, admin.Name As SalesAgent, BookingNumber, ReservationNumber, Customer, booking.CountryCodeID, booking.Mobile, StartDate, EndDate, Adult, Children, Infant, DepositDeadline, FullPaymentDeadline, category.Name As Destination, Subtotal, Discount, NetTotal, CancelStatus, LockStatus, AfterSalesService, booking.Status, BookingRemark, ChatLanguage, source.Name As Source, City, State, country_code.Country');
		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');
		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
        $this->db->join('country_code', 'country_code.CountryCodeID = category.Country', 'left');
        $this->db->join('source', 'source.SourceID = booking.Source', 'left');
        if(!empty($this->input->get('destination'))) {
            $this->db->where('Destination', $this->input->get('destination'));
        }
        if(!empty($this->input->get('city'))) {
            $this->db->where('City', $this->input->get('city'));
        }
        if(!empty($this->input->get('state'))) {
            $this->db->where('State', $this->input->get('state'));
        }
        if(!empty($this->input->get('country'))) {
            $this->db->where('category.Country', $this->input->get('country'));
        }
        if(!empty($this->input->get('source'))) {
            $this->db->where('Source', $this->input->get('source'));
        }
        if(!empty($this->input->get('sales_agent'))) {
            $this->db->where('SalesAgent', $this->input->get('sales_agent'));
        }
        if(!empty($this->input->get('sales_agent_2'))) {
            $this->db->where('SalesAgent2', $this->input->get('sales_agent_2'));
        }
        if(!empty($this->input->get('travel_date'))) {
            $travel_date = explode(' - ', $this->input->get('travel_date'));
            $start_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[0])));
            $end_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[1])));
            $this->db->where("((`StartDate` <= '".$start_date."' AND `EndDate` >= '".$end_date."') OR (`StartDate` >= '".$start_date."' AND `StartDate` <= '".$end_date."') OR (`EndDate` >= '".$start_date."' AND `EndDate` <= '".$end_date."'))");
        } else {
            $this->db->where("((`StartDate` <= '".date('Y-m-1')."' AND `EndDate` >= '".date('Y-m-t')."') OR (`StartDate` >= '".date('Y-m-1')."' AND `StartDate` <= '".date('Y-m-t')."') OR (`EndDate` >= '".date('Y-m-1')."' AND `EndDate` <= '".date('Y-m-t')."'))");
        }
        $this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');
        $this->db->where('CancelStatus', 'N');
        $this->db->where('AfterSalesService', 'COMPLETE');
        $this->db->where('booking.Status', 'Y');
        $this->db->order_by('admin.Name', 'ASC');
        $this->db->order_by('BookingNumber', 'ASC');
        $report = $this->db->get('booking');
        return $report->result();
    }

    private function build_lead_dashboard_where_clause($filters = array())
    {
        $clauses = array();
        $params = array();
        $extraJoins = '';

        // Server-only restriction set by the controller for non-OWNER users.
        // Empty list => zero rows. Never sourced from request input.
        if (array_key_exists('_restrict_agent_ids', $filters)) {
            $allowed = array_values(array_filter(
                array_map('strval', (array) $filters['_restrict_agent_ids']),
                'strlen'
            ));
            if (empty($allowed)) {
                return array('sql' => 'WHERE 1=0', 'params' => array(), 'extra_joins' => '');
            }
            $placeholders = implode(',', array_fill(0, count($allowed), '?'));
            $clauses[] = "NULLIF(pl.assigned_to_user_id, '') IN ({$placeholders})";
            foreach ($allowed as $id) { $params[] = $id; }
        }

        if (!empty($filters['start_date'])) {
            $clauses[] = 'pl.lead_started_at >= ?';
            $params[] = $filters['start_date'] . ' 00:00:00';
        }

        if (!empty($filters['end_date'])) {
            $clauses[] = 'pl.lead_started_at <= ?';
            $params[] = $filters['end_date'] . ' 23:59:59';
        }

        if (!empty($filters['agent_id'])) {
            $agentIds = is_array($filters['agent_id']) ? $filters['agent_id'] : array($filters['agent_id']);
            $agentIds = array_values(array_filter($agentIds, function($v) { return $v !== '' && $v !== null; }));

            if (!empty($agentIds)) {
                if (count($agentIds) === 1 && $agentIds[0] === '__unassigned__') {
                    $clauses[] = "(NULLIF(pl.assigned_to_user_id, '') IS NULL)";
                } else {
                    $placeholders = implode(',', array_fill(0, count($agentIds), '?'));
                    $clauses[] = "NULLIF(pl.assigned_to_user_id, '') IN ({$placeholders})";
                    foreach ($agentIds as $id) { $params[] = $id; }
                }
            }
        }

        if (!empty($filters['team_lead'])) {
            $teamLeadIds = is_array($filters['team_lead']) ? $filters['team_lead'] : array($filters['team_lead']);
            $teamLeadIds = array_values(array_filter($teamLeadIds, function($v) { return $v !== '' && $v !== null; }));

            if (!empty($teamLeadIds)) {
                $extraJoins  = " LEFT JOIN admin_lead_dashboard_agents tl_alda ON tl_alda.GhlUserID = NULLIF(pl.assigned_to_user_id, '') ";
                $extraJoins .= " LEFT JOIN admin tl_admin ON tl_admin.AdminID = tl_alda.AdminID AND tl_admin.Status = 'Y' ";

                $placeholders = implode(',', array_fill(0, count($teamLeadIds), '?'));
                $clauses[] = "tl_admin.TeamLeadID IN ({$placeholders})";
                foreach ($teamLeadIds as $id) { $params[] = $id; }
            }
        }

        if (!empty($filters['conversation_id'])) {
            $clauses[] = 'pl.conversation_id = ?';
            $params[] = $filters['conversation_id'];
        }

        if (!empty($filters['contact_name'])) {
            $clauses[] = "(gc.contact_name LIKE ? ESCAPE '!' OR gc.full_name LIKE ? ESCAPE '!')";
            $escapedKeyword = '%' . $this->db->escape_like_str($filters['contact_name']) . '%';
            $params[] = $escapedKeyword;
            $params[] = $escapedKeyword;
        }

        if (!empty($filters['phone'])) {
            $clauses[] = "gc.phone LIKE ? ESCAPE '!'";
            $params[] = '%' . $this->db->escape_like_str($filters['phone']) . '%';
        }

        if (!empty($filters['tag'])) {
            $clauses[] = "JSON_SEARCH(gc.tags_json, 'one', ?, '!') IS NOT NULL";
            $params[] = $this->db->escape_like_str($filters['tag']);
        }

        if (isset($filters['response_status']) && $filters['response_status'] !== '') {
            if ($filters['response_status'] === 'responded') {
                $clauses[] = "pl.responded_message_count > 0";
            } elseif ($filters['response_status'] === 'pending') {
                $clauses[] = "pl.responded_message_count = 0";
            }
        }

        if (isset($filters['conversion_status']) && $filters['conversion_status'] !== '') {
            // Row-level fact: did this lead become a booking? Independent of the
            // TC1/TC2 credit rule applied to per-TC conversion-rate aggregates.
            if ($filters['conversion_status'] === 'converted') {
                $clauses[] = 'pl.is_converted = 1';
            } elseif ($filters['conversion_status'] === 'open') {
                $clauses[] = 'pl.is_converted = 0';
            }
        }

        $sql = '';
        if (!empty($clauses)) {
            $sql = 'WHERE ' . implode(' AND ', $clauses);
        }

        return array(
            'sql' => $sql,
            'params' => $params,
            'extra_joins' => $extraJoins,
        );
    }

    private function build_lead_ownership_where_clause($filters = array())
    {
        $clauses = array();
        $params = array();
        $extraJoins = '';

        if (array_key_exists('_restrict_agent_ids', $filters)) {
            $allowed = array_values(array_filter(
                array_map('strval', (array) $filters['_restrict_agent_ids']),
                'strlen'
            ));
            if (empty($allowed)) {
                return array('sql' => 'WHERE 1=0', 'params' => array(), 'extra_joins' => '');
            }
            $placeholders = implode(',', array_fill(0, count($allowed), '?'));
            $clauses[] = "glo.owner_user_id IN ({$placeholders})";
            foreach ($allowed as $id) { $params[] = $id; }
        }

        if (!empty($filters['start_date'])) {
            $clauses[] = 'glo.lead_started_at >= ?';
            $params[] = $filters['start_date'] . ' 00:00:00';
        }

        if (!empty($filters['end_date'])) {
            $clauses[] = 'glo.lead_started_at <= ?';
            $params[] = $filters['end_date'] . ' 23:59:59';
        }

        if ($this->db->field_exists('assigned_at', 'ghl_lead_ownership')) {
            if (!empty($filters['assignment_start_date'])) {
                $clauses[] = 'glo.assigned_at >= ?';
                $params[] = $filters['assignment_start_date'] . ' 00:00:00';
            }

            if (!empty($filters['assignment_end_date'])) {
                $clauses[] = 'glo.assigned_at <= ?';
                $params[] = $filters['assignment_end_date'] . ' 23:59:59';
            }
        }

        if (!empty($filters['owner_user_id'])) {
            $ownerIds = is_array($filters['owner_user_id']) ? $filters['owner_user_id'] : array($filters['owner_user_id']);
            $ownerIds = array_values(array_filter($ownerIds, function($v) { return $v !== '' && $v !== null; }));

            if (!empty($ownerIds)) {
                $placeholders = implode(',', array_fill(0, count($ownerIds), '?'));
                $clauses[] = "glo.owner_user_id IN ({$placeholders})";
                foreach ($ownerIds as $id) { $params[] = $id; }
            }
        }

        if (!empty($filters['team_lead'])) {
            $teamLeadIds = is_array($filters['team_lead']) ? $filters['team_lead'] : array($filters['team_lead']);
            $teamLeadIds = array_values(array_filter($teamLeadIds, function($v) { return $v !== '' && $v !== null; }));

            if (!empty($teamLeadIds)) {
                $extraJoins  = " LEFT JOIN admin_lead_dashboard_agents tl_alda ON tl_alda.GhlUserID = glo.owner_user_id ";
                $extraJoins .= " LEFT JOIN admin tl_admin ON tl_admin.AdminID = tl_alda.AdminID AND tl_admin.Status = 'Y' ";

                $placeholders = implode(',', array_fill(0, count($teamLeadIds), '?'));
                $clauses[] = "tl_admin.TeamLeadID IN ({$placeholders})";
                foreach ($teamLeadIds as $id) { $params[] = $id; }
            }
        }

        if (isset($filters['ownership_type']) && $filters['ownership_type'] !== '') {
            if ($filters['ownership_type'] === 'assigned') {
                $clauses[] = 'glo.is_assigned_owner = 1';
            } elseif ($filters['ownership_type'] === 'reply') {
                $clauses[] = 'glo.is_assigned_owner = 0 AND glo.is_reply_owner = 1';
            }
        }

        if (isset($filters['follow_up_status']) && $filters['follow_up_status'] !== '') {
            $clauses[] = 'glo.follow_up_status = ?';
            $params[] = $filters['follow_up_status'];
        }

        $sql = '';
        if (!empty($clauses)) {
            $sql = 'WHERE ' . implode(' AND ', $clauses);
        }

        return array(
            'sql' => $sql,
            'params' => $params,
            'extra_joins' => $extraJoins,
        );
    }

    private function extract_ghl_tags($tagsJson)
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
                $tags[] = $tag;
            }
        }

        return $tags;
    }

    private function build_lead_reply_activity_where_clause($filters = array())
    {
        $clauses = array();
        $params = array();
        $extraJoins = '';
        $messageTimeColumn = $this->escape_identifier($this->get_message_time_column());

        $clauses[] = "gm.direction = 'outbound'";
        $clauses[] = "gm.user_id IS NOT NULL";
        $clauses[] = "gm.user_id <> ''";

        if (array_key_exists('_restrict_agent_ids', $filters)) {
            $allowed = array_values(array_filter(
                array_map('strval', (array) $filters['_restrict_agent_ids']),
                'strlen'
            ));
            if (empty($allowed)) {
                return array('sql' => 'WHERE 1=0', 'params' => array(), 'extra_joins' => '');
            }
            $placeholders = implode(',', array_fill(0, count($allowed), '?'));
            $clauses[] = "glo.owner_user_id IN ({$placeholders})";
            foreach ($allowed as $id) { $params[] = $id; }
        }

        if (!empty($filters['start_date'])) {
            $clauses[] = "gm.{$messageTimeColumn} >= ?";
            $params[] = $filters['start_date'] . ' 00:00:00';
        }

        if (!empty($filters['end_date'])) {
            $clauses[] = "gm.{$messageTimeColumn} <= ?";
            $params[] = $filters['end_date'] . ' 23:59:59';
        }

        if (!empty($filters['lead_type']) && !empty($filters['start_date']) && !empty($filters['end_date'])) {
            if ($filters['lead_type'] === 'new') {
                $clauses[] = 'glo.lead_started_at BETWEEN ? AND ?';
            } elseif ($filters['lead_type'] === 'replied') {
                $clauses[] = 'glo.lead_started_at NOT BETWEEN ? AND ?';
            }

            if ($filters['lead_type'] === 'new' || $filters['lead_type'] === 'replied') {
                $params[] = $filters['start_date'] . ' 00:00:00';
                $params[] = $filters['end_date'] . ' 23:59:59';
            }
        }

        if (!empty($filters['owner_user_id'])) {
            $ownerIds = is_array($filters['owner_user_id']) ? $filters['owner_user_id'] : array($filters['owner_user_id']);
            $ownerIds = array_values(array_filter($ownerIds, function($v) { return $v !== '' && $v !== null; }));

            if (!empty($ownerIds)) {
                $placeholders = implode(',', array_fill(0, count($ownerIds), '?'));
                $clauses[] = "glo.owner_user_id IN ({$placeholders})";
                foreach ($ownerIds as $id) { $params[] = $id; }
            }
        }

        if (!empty($filters['team_lead'])) {
            $teamLeadIds = is_array($filters['team_lead']) ? $filters['team_lead'] : array($filters['team_lead']);
            $teamLeadIds = array_values(array_filter($teamLeadIds, function($v) { return $v !== '' && $v !== null; }));

            if (!empty($teamLeadIds)) {
                $extraJoins  = " LEFT JOIN admin_lead_dashboard_agents tl_alda ON tl_alda.GhlUserID = glo.owner_user_id ";
                $extraJoins .= " LEFT JOIN admin tl_admin ON tl_admin.AdminID = tl_alda.AdminID AND tl_admin.Status = 'Y' ";

                $placeholders = implode(',', array_fill(0, count($teamLeadIds), '?'));
                $clauses[] = "tl_admin.TeamLeadID IN ({$placeholders})";
                foreach ($teamLeadIds as $id) { $params[] = $id; }
            }
        }

        $sql = '';
        if (!empty($clauses)) {
            $sql = 'WHERE ' . implode(' AND ', $clauses);
        }

        return array(
            'sql' => $sql,
            'params' => $params,
            'extra_joins' => $extraJoins,
        );
    }

    private function build_lead_reply_assignment_where_clause($filters = array())
    {
        $clauses = array();
        $params = array();
        $extraJoins = '';
        $assignmentDate = $this->lead_reply_assignment_date_expression('glo');

        $clauses[] = 'glo.is_assigned_owner = 1';
        $clauses[] = "NULLIF(glo.assigned_to_user_id, '') = glo.owner_user_id";

        if (array_key_exists('_restrict_agent_ids', $filters)) {
            $allowed = array_values(array_filter(
                array_map('strval', (array) $filters['_restrict_agent_ids']),
                'strlen'
            ));
            if (empty($allowed)) {
                return array('sql' => 'WHERE 1=0', 'params' => array(), 'extra_joins' => '');
            }
            $placeholders = implode(',', array_fill(0, count($allowed), '?'));
            $clauses[] = "glo.owner_user_id IN ({$placeholders})";
            foreach ($allowed as $id) { $params[] = $id; }
        }

        if (!empty($filters['start_date'])) {
            $clauses[] = "{$assignmentDate} >= ?";
            $params[] = $filters['start_date'] . ' 00:00:00';
        }

        if (!empty($filters['end_date'])) {
            $clauses[] = "{$assignmentDate} <= ?";
            $params[] = $filters['end_date'] . ' 23:59:59';
        }

        if (!empty($filters['owner_user_id'])) {
            $ownerIds = is_array($filters['owner_user_id']) ? $filters['owner_user_id'] : array($filters['owner_user_id']);
            $ownerIds = array_values(array_filter($ownerIds, function($v) { return $v !== '' && $v !== null; }));

            if (!empty($ownerIds)) {
                $placeholders = implode(',', array_fill(0, count($ownerIds), '?'));
                $clauses[] = "glo.owner_user_id IN ({$placeholders})";
                foreach ($ownerIds as $id) { $params[] = $id; }
            }
        }

        if (!empty($filters['team_lead'])) {
            $teamLeadIds = is_array($filters['team_lead']) ? $filters['team_lead'] : array($filters['team_lead']);
            $teamLeadIds = array_values(array_filter($teamLeadIds, function($v) { return $v !== '' && $v !== null; }));

            if (!empty($teamLeadIds)) {
                $extraJoins  = " LEFT JOIN admin_lead_dashboard_agents tl_alda ON tl_alda.GhlUserID = glo.owner_user_id ";
                $extraJoins .= " LEFT JOIN admin tl_admin ON tl_admin.AdminID = tl_alda.AdminID AND tl_admin.Status = 'Y' ";

                $placeholders = implode(',', array_fill(0, count($teamLeadIds), '?'));
                $clauses[] = "tl_admin.TeamLeadID IN ({$placeholders})";
                foreach ($teamLeadIds as $id) { $params[] = $id; }
            }
        }

        return array(
            'sql' => 'WHERE ' . implode(' AND ', $clauses),
            'params' => $params,
            'extra_joins' => $extraJoins,
        );
    }

    private function build_lead_reply_created_where_clause($filters = array())
    {
        $clauses = array();
        $params = array();
        $extraJoins = '';

        $clauses[] = 'glo.is_reply_owner = 1';

        if (array_key_exists('_restrict_agent_ids', $filters)) {
            $allowed = array_values(array_filter(
                array_map('strval', (array) $filters['_restrict_agent_ids']),
                'strlen'
            ));
            if (empty($allowed)) {
                return array('sql' => 'WHERE 1=0', 'params' => array(), 'extra_joins' => '');
            }
            $placeholders = implode(',', array_fill(0, count($allowed), '?'));
            $clauses[] = "glo.owner_user_id IN ({$placeholders})";
            foreach ($allowed as $id) { $params[] = $id; }
        }

        if (!empty($filters['owner_user_id'])) {
            $ownerIds = is_array($filters['owner_user_id']) ? $filters['owner_user_id'] : array($filters['owner_user_id']);
            $ownerIds = array_values(array_filter($ownerIds, function($v) { return $v !== '' && $v !== null; }));

            if (!empty($ownerIds)) {
                $placeholders = implode(',', array_fill(0, count($ownerIds), '?'));
                $clauses[] = "glo.owner_user_id IN ({$placeholders})";
                foreach ($ownerIds as $id) { $params[] = $id; }
            }
        }

        if (!empty($filters['team_lead'])) {
            $teamLeadIds = is_array($filters['team_lead']) ? $filters['team_lead'] : array($filters['team_lead']);
            $teamLeadIds = array_values(array_filter($teamLeadIds, function($v) { return $v !== '' && $v !== null; }));

            if (!empty($teamLeadIds)) {
                $extraJoins  = " LEFT JOIN admin_lead_dashboard_agents tl_alda ON tl_alda.GhlUserID = glo.owner_user_id ";
                $extraJoins .= " LEFT JOIN admin tl_admin ON tl_admin.AdminID = tl_alda.AdminID AND tl_admin.Status = 'Y' ";

                $placeholders = implode(',', array_fill(0, count($teamLeadIds), '?'));
                $clauses[] = "tl_admin.TeamLeadID IN ({$placeholders})";
                foreach ($teamLeadIds as $id) { $params[] = $id; }
            }
        }

        return array(
            'sql' => 'WHERE ' . implode(' AND ', $clauses),
            'params' => $params,
            'extra_joins' => $extraJoins,
        );
    }

    private function lead_reply_assignment_date_expression($alias)
    {
        $alias = preg_replace('/[^A-Za-z0-9_]/', '', (string) $alias);

        // field_exists() hits the schema; cache the boolean for the rest of the request.
        if ($this->assignmentDateExpressionCache === null) {
            $this->assignmentDateExpressionCache = $this->db->field_exists('assigned_at', 'ghl_lead_ownership');
        }

        if ($this->assignmentDateExpressionCache) {
            return "COALESCE({$alias}.assigned_at, {$alias}.lead_started_at)";
        }

        return "{$alias}.lead_started_at";
    }

    private function build_mobile_search_where_clause($mobile, $filters, $phoneColumn, $ownerColumn)
    {
        $raw = trim((string) $mobile);
        if ($raw === '') {
            return array('sql' => 'WHERE 1=0', 'params' => array(), 'empty' => true);
        }

        $digits = preg_replace('/\D+/', '', $raw);
        $normalizedPhoneColumn = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$phoneColumn}, '+', ''), ' ', ''), '-', ''), '(', ''), ')', '')";
        $clauses = array();
        $params = array();

        if ($digits !== '') {
            $clauses[] = "({$phoneColumn} LIKE ? OR {$normalizedPhoneColumn} LIKE ?)";
            $params[] = '%' . $raw . '%';
            $params[] = '%' . $digits . '%';
        } else {
            $clauses[] = "{$phoneColumn} LIKE ?";
            $params[] = '%' . $raw . '%';
        }

        if (array_key_exists('_restrict_agent_ids', $filters)) {
            $allowed = array_values(array_filter(
                array_map('strval', (array) $filters['_restrict_agent_ids']),
                'strlen'
            ));
            if (empty($allowed)) {
                return array('sql' => 'WHERE 1=0', 'params' => array(), 'empty' => true);
            }
            $placeholders = implode(',', array_fill(0, count($allowed), '?'));
            $clauses[] = "{$ownerColumn} IN ({$placeholders})";
            foreach ($allowed as $id) { $params[] = $id; }
        }

        return array(
            'sql' => 'WHERE ' . implode(' AND ', $clauses),
            'params' => $params,
            'empty' => false,
        );
    }

    function Lead_Dashboard_Team_Leads()
    {
        $this->db->select('AdminID, Name');
        $this->db->where('Level', '25');
        $this->db->where('Status', 'Y');
        $this->db->order_by('Name', 'ASC');
        return $this->db->get('admin')->result();
    }
}
