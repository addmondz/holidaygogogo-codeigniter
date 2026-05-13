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
        $where = $this->build_lead_dashboard_where_clause($filters);

        $sql = "
            SELECT
                COUNT(*) AS total_leads,
                SUM(CASE WHEN pl.responded_message_count > 0 THEN 1 ELSE 0 END) AS responded_leads,
                AVG(pl.avg_first_5_response_seconds) AS avg_response_time_seconds,
                AVG(pl.avg_recent_5_response_seconds) AS avg_recent_response_time_seconds,
                AVG(pl.responded_message_count) AS avg_responded_messages,
                AVG(pl.recent_responded_message_count) AS avg_recent_responded_messages,
                SUM(CASE WHEN pl.is_converted = 1 THEN 1 ELSE 0 END) AS converted_leads,
                COUNT(DISTINCT NULLIF(pl.assigned_to_user_id, '')) AS active_agents
            FROM ghl_processed_leads pl
            LEFT JOIN ghl_conversations gc ON gc.conversation_id = pl.conversation_id
            {$where['sql']}
        ";

        $row = $this->db->query($sql, $where['params'])->row_array();
        $totalLeads = !empty($row['total_leads']) ? (int) $row['total_leads'] : 0;
        $respondedLeads = !empty($row['responded_leads']) ? (int) $row['responded_leads'] : 0;
        $convertedLeads = !empty($row['converted_leads']) ? (int) $row['converted_leads'] : 0;
        $avgResponseSeconds = isset($row['avg_response_time_seconds']) && $row['avg_response_time_seconds'] !== null
            ? (int) round($row['avg_response_time_seconds'])
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

    function Lead_Dashboard_By_Agent($filters = array())
    {
        $where = $this->build_lead_dashboard_where_clause($filters);
        $clauses = array();

        if (!empty($where['sql'])) {
            $clauses[] = preg_replace('/^\s*WHERE\s+/i', '', $where['sql']);
        }

        $clauses[] = "NULLIF(pl.assigned_to_user_id, '') IS NOT NULL";
        $agentWhereSql = !empty($clauses) ? 'WHERE ' . implode(' AND ', $clauses) : '';

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
                SUM(CASE WHEN pl.is_converted = 1 THEN 1 ELSE 0 END) AS converted_leads,
                MAX(pl.updated_at) AS last_updated_at
            FROM ghl_processed_leads pl
            LEFT JOIN ghl_conversations gc ON gc.conversation_id = pl.conversation_id
            LEFT JOIN ghl_users gu ON gu.UserID = NULLIF(pl.assigned_to_user_id, '')
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

    function Lead_Dashboard_Agents()
    {
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
            WHERE user_ref.agent_id IS NOT NULL AND user_ref.agent_id <> ''
            ORDER BY agent_name ASC
        ";

        return $this->db->query($sql)->result();
    }

    function Lead_Data_Total_Count($filters = array())
    {
        $where = $this->build_lead_dashboard_where_clause($filters);

        $sql = "
            SELECT COUNT(*) AS total_rows
            FROM ghl_processed_leads pl
            LEFT JOIN ghl_conversations gc ON gc.conversation_id = pl.conversation_id
            {$where['sql']}
        ";

        $row = $this->db->query($sql, $where['params'])->row_array();
        return !empty($row['total_rows']) ? (int) $row['total_rows'] : 0;
    }

    function Lead_Data_Rows($filters = array(), $limit = null, $offset = null)
    {
        $messageTimeColumn = $this->escape_identifier($this->get_message_time_column());
        $where = $this->build_lead_dashboard_where_clause($filters);
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

        if (!empty($filters['start_date'])) {
            $clauses[] = 'pl.lead_started_at >= ?';
            $params[] = $filters['start_date'] . ' 00:00:00';
        }

        if (!empty($filters['end_date'])) {
            $clauses[] = 'pl.lead_started_at <= ?';
            $params[] = $filters['end_date'] . ' 23:59:59';
        }

        if (!empty($filters['agent_id'])) {
            if ($filters['agent_id'] === '__unassigned__') {
                $clauses[] = "(NULLIF(pl.assigned_to_user_id, '') IS NULL)";
            } else {
                $clauses[] = "NULLIF(pl.assigned_to_user_id, '') = ?";
                $params[] = $filters['agent_id'];
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

        if (isset($filters['response_status']) && $filters['response_status'] !== '') {
            if ($filters['response_status'] === 'responded') {
                $clauses[] = "pl.responded_message_count > 0";
            } elseif ($filters['response_status'] === 'pending') {
                $clauses[] = "pl.responded_message_count = 0";
            }
        }

        if (isset($filters['conversion_status']) && $filters['conversion_status'] !== '') {
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
        );
    }
}
