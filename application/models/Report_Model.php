<?php
class Report_Model extends CI_Model
{
    protected $messageTimeColumn = null;

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

    function Lead_Dashboard_Summary($filters = array())
    {
        $this->load->helper('lead_conversion_credit');
        $where = $this->build_lead_dashboard_where_clause($filters);

        $extraJoins = isset($where['extra_joins']) ? $where['extra_joins'] : '';
        $creditFragment = lead_conversion_credit_sql_fragment();
        $sql = "
            SELECT
                COUNT(*) AS total_leads,
                SUM(CASE WHEN pl.responded_message_count > 0 THEN 1 ELSE 0 END) AS responded_leads,
                AVG(pl.avg_first_5_response_seconds) AS avg_response_time_seconds,
                AVG(pl.avg_recent_5_response_seconds) AS avg_recent_response_time_seconds,
                AVG(pl.responded_message_count) AS avg_responded_messages,
                AVG(pl.recent_responded_message_count) AS avg_recent_responded_messages,
                SUM(CASE WHEN pl.is_converted = 1 AND pl.booking_id IS NOT NULL AND {$creditFragment} THEN 1 ELSE 0 END) AS converted_leads,
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
        $this->load->helper('duty_hours');
        $slotSql = "
            SELECT
                pl.response_1_customer_message_at AS ct1, pl.response_1_agent_message_at AS at1, pl.response_1_seconds AS s1,
                pl.response_2_customer_message_at AS ct2, pl.response_2_agent_message_at AS at2, pl.response_2_seconds AS s2,
                pl.response_3_customer_message_at AS ct3, pl.response_3_agent_message_at AS at3, pl.response_3_seconds AS s3,
                pl.response_4_customer_message_at AS ct4, pl.response_4_agent_message_at AS at4, pl.response_4_seconds AS s4,
                pl.response_5_customer_message_at AS ct5, pl.response_5_agent_message_at AS at5, pl.response_5_seconds AS s5
            FROM ghl_processed_leads pl
            LEFT JOIN ghl_conversations gc ON gc.conversation_id = pl.conversation_id
            {$extraJoins}
            {$where['sql']}
        ";
        $slotRows = $this->db->query($slotSql, $where['params'])->result_array();
        $dutyTotal = 0;
        $dutyCount = 0;
        foreach ($slotRows as $sr) {
            for ($i = 1; $i <= 5; $i++) {
                $secs = $sr['s' . $i];
                if ($secs === null || $secs === '') continue;
                $dutySeconds = calculate_duty_response_seconds($sr['ct' . $i], $sr['at' . $i]);
                if ($dutySeconds === null) continue;
                $dutyTotal += (int) $dutySeconds;
                $dutyCount++;
            }
        }
        $avgResponseSeconds = $dutyCount > 0
            ? (int) round($dutyTotal / $dutyCount)
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
                SUM(CASE WHEN pl.is_converted = 1 AND pl.booking_id IS NOT NULL AND {$creditFragment} THEN 1 ELSE 0 END) AS converted_leads,
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
        $results = array();

        foreach ($rows as $row) {
            $totalLeads = (int) $row['total_leads'];
            $respondedLeads = (int) $row['responded_leads'];
            $convertedLeads = (int) $row['converted_leads'];
            $avgResponseSeconds = $row['avg_response_time_seconds'] !== null
                ? (int) round($row['avg_response_time_seconds'])
                : null;
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
        $where = $this->build_lead_ownership_where_clause($filters);
        $extraJoins = isset($where['extra_joins']) ? $where['extra_joins'] : '';

        $sql = "
            SELECT
                COUNT(*) AS owned_leads,
                COUNT(DISTINCT glo.processed_lead_id) AS unique_leads,
                SUM(CASE WHEN glo.is_assigned_owner = 1 THEN 1 ELSE 0 END) AS assigned_owned_leads,
                SUM(CASE WHEN glo.is_assigned_owner = 0 AND glo.is_reply_owner = 1 THEN 1 ELSE 0 END) AS reply_owned_leads,
                SUM(CASE WHEN glo.responded_message_count > 0 THEN 1 ELSE 0 END) AS responded_leads,
                SUM(CASE WHEN glo.follow_up_status IN ('sent', 'completed') THEN 1 ELSE 0 END) AS follow_up_leads,
                SUM(CASE WHEN glo.is_converted = 1 AND glo.booking_id IS NOT NULL THEN 1 ELSE 0 END) AS converted_leads,
                COUNT(DISTINCT glo.owner_user_id) AS active_owners,
                AVG(glo.avg_first_5_response_seconds) AS avg_response_time_seconds,
                AVG(glo.avg_recent_5_response_seconds) AS avg_recent_response_time_seconds,
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
            'avg_response_time_seconds' => $row['avg_response_time_seconds'] !== null ? (int) round($row['avg_response_time_seconds']) : null,
            'avg_recent_response_time_seconds' => $row['avg_recent_response_time_seconds'] !== null ? (int) round($row['avg_recent_response_time_seconds']) : null,
            'avg_responded_messages' => $row['avg_responded_messages'] !== null ? round((float) $row['avg_responded_messages'], 1) : 0.0,
            'avg_recent_responded_messages' => $row['avg_recent_responded_messages'] !== null ? round((float) $row['avg_recent_responded_messages'], 1) : 0.0,
        );
    }

    function Lead_Ownership_By_Agent($filters = array())
    {
        $where = $this->build_lead_ownership_where_clause($filters);
        $extraJoins = isset($where['extra_joins']) ? $where['extra_joins'] : '';

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
                SUM(CASE WHEN glo.is_converted = 1 AND glo.booking_id IS NOT NULL THEN 1 ELSE 0 END) AS converted_leads,
                AVG(glo.avg_first_5_response_seconds) AS avg_response_time_seconds,
                AVG(glo.avg_recent_5_response_seconds) AS avg_recent_response_time_seconds,
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
        $results = array();

        foreach ($rows as $row) {
            $ownedLeads = (int) $row['owned_leads'];
            $respondedLeads = (int) $row['responded_leads'];
            $followUpLeads = (int) $row['follow_up_leads'];
            $convertedLeads = (int) $row['converted_leads'];

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
                'conversion_rate' => $ownedLeads > 0 ? round(($convertedLeads / $ownedLeads) * 100, 1) : 0.0,
                'avg_response_time_seconds' => $row['avg_response_time_seconds'] !== null ? (int) round($row['avg_response_time_seconds']) : null,
                'avg_recent_response_time_seconds' => $row['avg_recent_response_time_seconds'] !== null ? (int) round($row['avg_recent_response_time_seconds']) : null,
                'avg_responded_messages' => $row['avg_responded_messages'] !== null ? round((float) $row['avg_responded_messages'], 1) : 0.0,
                'avg_recent_responded_messages' => $row['avg_recent_responded_messages'] !== null ? round((float) $row['avg_recent_responded_messages'], 1) : 0.0,
                'last_calculated_at' => $row['last_calculated_at'],
            );
        }

        return $results;
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

    function Lead_Dashboard_Team_Leads()
    {
        $this->db->select('AdminID, Name');
        $this->db->where('Level', '25');
        $this->db->where('Status', 'Y');
        $this->db->order_by('Name', 'ASC');
        return $this->db->get('admin')->result();
    }
}
