<?php
class Report_Model extends CI_Model
{
	function Destination_Profits()
	{
		$this->db->select('EndDate As Month, SUM(Credit) - SUM(Debit) As Profit, category.Name As Destination');
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
		$this->db->select('EndDate As Month, SUM(Credit) - SUM(Debit) As Profit, City');
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
		$this->db->select('EndDate As Month, SUM(Credit) - SUM(Debit) As Profit, State');
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
		$this->db->select('EndDate As Month, SUM(Credit) - SUM(Debit) As Profit, country_code.Country');
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
		$this->db->select('EndDate As Month, SUM(Quantity) As Quantity, SUM(Total) As NetTotal, product.ProductCode, product.Name As Product, SUM(Total) - (SupplierPrice * SUM(Quantity)) As Profit');
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
		$this->db->select('EndDate As Month, SUM(Credit) - SUM(Debit) As Profit, source.Name As Source');
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
		$this->db->select('EndDate As Month, SUM(Adult) As TotalAdult, SUM(Children) As TotalChildren, SUM(Infant) As TotalInfant, country_code.Country');
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
}