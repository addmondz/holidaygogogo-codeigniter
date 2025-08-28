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
		if(!in_array('VR', $this->session->access_control)) {
			redirect('Dashboard');
		}
	}

	function index()
	{}

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
}