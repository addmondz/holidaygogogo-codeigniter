<?php

require FCPATH.'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;  
use PhpOffice\PhpSpreadsheet\Writer\Xlxs;

class Cron extends CI_Controller
{
	public $allowGhlModuleSync = true;
	public $allowGhlModuleLog = true;
	public $ghlModuleLogFile = 'GHL_MODULES_SYNC.log';

	function __construct()
	{
		parent::__construct();
		$this->load->model('Booking_Model');
		$this->load->model('Booking_Product_Model');
		$this->load->model('Payment_Model');
		$this->load->model('Admin_Model');
		$this->load->model('Category_Model');
		$this->load->model('Supplier_Model');
		$this->load->model('Booking_Model');
		$this->load->model('Customer_Model');
		$this->load->library('AutoCountService'); // <-- where you put Guzzle API
	}

	function index()
	{

		// 1. Get all the bookings
		$bookings = $this->Booking_Model->Read_All_Bookings();

		// 2. Run the logic
		foreach($bookings as $booking) {
			if((date('Y-m-d') >= $booking->StartDate && date('Y-m-d') <= $booking->EndDate) && ($booking->Status == 'PT' || $booking->Status == 'Y')) {
				$this->Booking_Model->Update_After_Sales_Service2($booking->BookingID);
				$this->Booking_Model->Update_Status('OG', $booking->BookingID);
				$this->Booking_Model->Create_Booking_Log2($booking->Status, 'OG', $booking->BookingID);
			} else {
				if((date('Y-m-d') < $booking->StartDate && ($booking->Status == 'OG' || $booking->Status == 'Y'))) {
					$this->Booking_Model->Update_After_Sales_Service2($booking->BookingID);
					$this->Booking_Model->Update_Status('PT', $booking->BookingID);
					$this->Booking_Model->Create_Booking_Log2($booking->Status, 'PT', $booking->BookingID);
				} else {
					if((date('Y-m-d') > $booking->EndDate && ($booking->Status == 'PT' || $booking->Status == 'OG'))) {
						$this->Booking_Model->Update_After_Sales_Service2($booking->BookingID);
						$this->Booking_Model->Update_Status('Y', $booking->BookingID);
						$this->Booking_Model->Create_Booking_Log2($booking->Status, 'Y', $booking->BookingID);
					}
				}
			}
			
			$payments = $this->Booking_Model->Read_Payments($booking->BookingID);
			$total_approved_credit = 0;
			if(!empty($payments)) {
				foreach($payments as $payment) {
					if($payment->Type != 'SUPPLIER REFUND' && $payment->Credit != 0.00 && $payment->Status == 'Y') {
						$total_approved_credit += $payment->Credit;
					}
				}
				if($total_approved_credit != 0) {
					if(strval($total_approved_credit) >= $booking->NetTotal) {
						$full_payment_existed = $this->Payment_Model->Read_Type($booking->BookingID);
						if($full_payment_existed) {
							if($booking->Status == 'P' || $booking->Status == 'PP') {
								$this->Booking_Model->Update_Status('PTV', $booking->BookingID);
								$this->Booking_Model->Create_Booking_Log2($booking->Status, 'PTV', $booking->BookingID);
							}
						} else {
							if($booking->Status == 'P') {
								$this->Booking_Model->Update_Status('PP', $booking->BookingID);
								$this->Booking_Model->Create_Booking_Log2($booking->Status, 'PP', $booking->BookingID);
							}
						}
					} else {
						if($booking->Status != 'PP') {
							$this->Booking_Model->Update_Status('PP', $booking->BookingID);
							$this->Booking_Model->Create_Booking_Log2($booking->Status, 'PP', $booking->BookingID);
						}
					}
				} else {
					if($booking->Status != 'P') {
						$this->Booking_Model->Update_Status('P', $booking->BookingID);
						$this->Booking_Model->Create_Booking_Log2($booking->Status, 'P', $booking->BookingID);
					}
				}
			} else {
				if($booking->Status != 'P') {
					$this->Booking_Model->Update_Status('P', $booking->BookingID);
					$this->Booking_Model->Create_Booking_Log2($booking->Status, 'P', $booking->BookingID);
				}
			}
		}

		echo "DONE!";
	}

	function old_index()
	{
		$startTime = date("Y-m-d H:i:s.u");
		if(in_array('VB', $this->session->access_control)) {
			$titles = array('tab_title' => 'HolidayGoGoGo | Booking', 'breadcrumb_title' => 'Booking');
			$array = array('total_sales' => 0, 'total_net_profit' => 0);
			$bookings = $this->Booking_Model->Read_All_Bookings();
			$array['admins'] = $this->Booking_Model->Read_Admins();
			$array['categories'] = $this->Booking_Model->Read_Categories();
			$array['tags'] = $this->Booking_Model->Read_Tags();
			$array['sources'] = $this->Booking_Model->Read_Sources();
			$total_sales = 0;
			$total_net_profit = 0;

				foreach($bookings as $booking) {
					if((date('Y-m-d') >= $booking->StartDate && date('Y-m-d') <= $booking->EndDate) && ($booking->Status == 'PT' || $booking->Status == 'Y')) {
						$this->Booking_Model->Update_After_Sales_Service2($booking->BookingID);
						$this->Booking_Model->Update_Status('OG', $booking->BookingID);
						$this->Booking_Model->Create_Booking_Log2($booking->Status, 'OG', $booking->BookingID);
					} else {
						if((date('Y-m-d') < $booking->StartDate && ($booking->Status == 'OG' || $booking->Status == 'Y'))) {
							$this->Booking_Model->Update_After_Sales_Service2($booking->BookingID);
							$this->Booking_Model->Update_Status('PT', $booking->BookingID);
							$this->Booking_Model->Create_Booking_Log2($booking->Status, 'PT', $booking->BookingID);
						} else {
							if((date('Y-m-d') > $booking->EndDate && ($booking->Status == 'PT' || $booking->Status == 'OG'))) {
								$this->Booking_Model->Update_After_Sales_Service2($booking->BookingID);
								$this->Booking_Model->Update_Status('Y', $booking->BookingID);
								$this->Booking_Model->Create_Booking_Log2($booking->Status, 'Y', $booking->BookingID);
							}
						}
					}
					
					$payments = $this->Booking_Model->Read_Payments($booking->BookingID);
					$total_approved_credit = 0;
					if(!empty($payments)) {
						foreach($payments as $payment) {
							if($payment->Type != 'SUPPLIER REFUND' && $payment->Credit != 0.00 && $payment->Status == 'Y') {
								$total_approved_credit += $payment->Credit;
							}
						}
						if($total_approved_credit != 0) {
							if(strval($total_approved_credit) >= $booking->NetTotal) {
								$full_payment_existed = $this->Payment_Model->Read_Type($booking->BookingID);
								if($full_payment_existed) {
									if($booking->Status == 'P' || $booking->Status == 'PP') {
										$this->Booking_Model->Update_Status('PTV', $booking->BookingID);
										$this->Booking_Model->Create_Booking_Log2($booking->Status, 'PTV', $booking->BookingID);
									}
								} else {
									if($booking->Status == 'P') {
										$this->Booking_Model->Update_Status('PP', $booking->BookingID);
										$this->Booking_Model->Create_Booking_Log2($booking->Status, 'PP', $booking->BookingID);
									}
								}
							} else {
								if($booking->Status != 'PP') {
									$this->Booking_Model->Update_Status('PP', $booking->BookingID);
									$this->Booking_Model->Create_Booking_Log2($booking->Status, 'PP', $booking->BookingID);
								}
							}
						} else {
							if($booking->Status != 'P') {
								$this->Booking_Model->Update_Status('P', $booking->BookingID);
								$this->Booking_Model->Create_Booking_Log2($booking->Status, 'P', $booking->BookingID);
							}
						}
					} else {
						if($booking->Status != 'P') {
							$this->Booking_Model->Update_Status('P', $booking->BookingID);
							$this->Booking_Model->Create_Booking_Log2($booking->Status, 'P', $booking->BookingID);
						}
					}
				}
			

			$array['bookings'] = $this->Booking_Model->Read_Bookings();

			if(isset($_GET['nick'])) {
				print_r($this->db->last_query());exit;
			}

			foreach($array['bookings'] as $booking1) {
				$booking1->CustomerMobile = $booking1->CountryCode . str_replace([' ', '-'], '', $booking1->CustomerMobile);
				if(!empty($booking1->StartDate)) {
					$booking1->StartDate = strtoupper(date('j M Y', strtotime($booking1->StartDate)));
				} else {
					$booking1->StartDate = null;
				}
				if(!empty($booking1->EndDate)) {
					$booking1->EndDate = strtoupper(date('j M Y', strtotime($booking1->EndDate)));
				} else {
					$booking1->EndDate = null;
				}
				if($booking1->BookingConfirmationTitle == 'BOOKING CONFIRMATION') {
					$booking1->BookingConfirmationTitle = 'BC';
				} else {
					if($booking1->BookingConfirmationTitle == 'QUOTATION') {
						$booking1->BookingConfirmationTitle = 'QU';
					} else {
						$booking1->BookingConfirmationTitle = 'PI';
					}
				}

				$payments = $this->Booking_Model->Read_Payments($booking1->BookingID);
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
						$profit_margin = round(($net_profit / $booking1->NetTotal) * 100);
					}
				}
				$booking1->Profit = number_format($net_profit, 2, '.', ',');
				$booking1->ProfitMargin = $net_profit != 0 ? $profit_margin : 0;
				if($booking1->LockStatus == 'N' && $booking1->Status == 'PTV') {
					$booking1->Status = 'PGL';
				}
				if($booking1->AfterSalesService == 'PENDING' && $booking1->Status == 'Y') {
					$booking1->Status = 'PR';
				}
				if(empty($booking1->DepositDeadline)) {
					if(date('Y-m-d') > $booking1->FullPaymentDeadline && ($booking1->Status == 'P' || $booking1->Status == 'PP')) {
						$booking1->Status = 'PO';
					}
				} else {
					if((date('Y-m-d') > $booking1->DepositDeadline && $booking1->Status == 'P') || (date('Y-m-d') > $booking1->FullPaymentDeadline && ($booking1->Status == 'P' || $booking1->Status == 'PP'))) {
						$booking1->Status = 'PO';
					}
				}
				$total_sales += $booking1->NetTotal;
				$total_net_profit += $net_profit;
				$booking1->NetTotal = number_format($booking1->NetTotal, 2, '.', ',');
				$booking1->InsertDate = strtoupper(date('j M Y', strtotime($booking1->InsertDate)));

			}
			
			$array['total_sales'] = number_format($total_sales, 2, '.', ',');
			$array['total_net_profit'] = $total_net_profit != 0 && $total_sales != 0 ? number_format($total_net_profit, 2, '.', ',') . ' (' . round(($total_net_profit / $total_sales) * 100) . '%)' : number_format($total_net_profit, 2, '.', ',') . ' (0%)';
			$this->load->view('layout/header', $titles);
			$this->load->view('booking/index', $array);
			$this->load->view('layout/footer');
			
		} else {
			redirect('Dashboard');
		}
	}

	/**
     * Master function: run all sync jobs
     */
    public function syncAll()
    {
		// check if request is from CLI (cron job)
		if (is_cli()) {
			$this->runSync();
			return;
		}
		$this->load->helper('autocount');
		$config = get_autocount_config();
		
		$apiKey = $this->input->get('key');
    	$expectedKey = $config['manual_sync_autocount_key']; // store in config or .env

		if ($apiKey !== $expectedKey) {
			show_error('Unauthorized access', 401);
			return;
		}
		$this->runSync();
    }
	
	private function runSync()
	{
		$this->syncCustomer();
		$this->syncSupplier();
		$this->syncBookings();
		$this->syncPayments();
		$this->syncDeletedPayments();

		// $this->syncGhlModules();
	}

	public function syncGhlModules()
	{
		$this->customCronLogging('[CRON] syncGhlModules');
		
		// run this hourly at 10 minutes past the hour
		if ($this->shouldRunHourly(10)) {
			if($this->allowGhlModuleSync) {
				$this->customCronLogging('[CRON-10] syncGhlModules');
				$this->syncGhlUsers();
				$this->syncGhlContacts();
				$this->syncGhlConversations();
				$this->syncGhlMessages();
			}
		}

		// process the leads every hour at 40 minutes past the hour, let it have 30 minutes to finish syncing the messages
		if ($this->shouldRunHourly(40)) {
			if($this->allowGhlModuleSync) {
				$this->customCronLogging('[CRON-40] syncGhlModules');
				$this->process_ghl_leads();
			}
		}

		
		
		// just a sample code to show
		// run this daily at 00:00
		// if ($this->shouldRunDaily(0, 0)) {
		// }	
	}

	private function shouldRunHourly($minute = 0)
	{
		return (int) date('i') === (int) $minute;
	}

	private function shouldRunDaily($hour, $minute = 0)
	{
		return (int) date('G') === (int) $hour
			&& (int) date('i') === (int) $minute;
	}

	public function process_ghl_leads($chunkSize = 100)
	{
		if (!$this->input->is_cli_request()) {
			show_error('This script can only be run from the command line.', 403);
			return;
		}

		$this->load->model('Ghl_Processed_Leads_Model');

		$args = isset($_SERVER['argv']) ? $_SERVER['argv'] : array();
		$uriSegments = $this->uri->segment_array();
		$cliArgs = array_merge(
			array_slice($args, 3),
			$uriSegments ? array_slice($uriSegments, 2) : array()
		);

		$flags = array();
		foreach ($cliArgs as $arg) {
			if (strncmp((string) $arg, '--', 2) === 0) {
				$flags[] = (string) $arg;
				continue;
			}

			if (!is_numeric($arg)) {
				continue;
			}

			$chunkSize = (int) $arg;
			break;
		}

		$chunkSize = (int) $chunkSize;
		if ($chunkSize <= 0) {
			$chunkSize = 100;
		}

		$shouldRebuild = in_array('--rebuild', $flags, true)
			|| in_array('--restart', $flags, true)
			|| in_array('--reset', $flags, true);

		$summary = array(
			'conversations_processed' => 0,
			'leads_rebuilt' => 0,
			'batches' => 0,
		);

		echo "=== GHL Lead Processing Start ===" . PHP_EOL;
		echo "Chunk size: {$chunkSize}" . PHP_EOL;

		if (!$this->Ghl_Processed_Leads_Model->acquire_processor_lock('ghl_leads_processor', 0)) {
			echo "Another ghl lead processor run is already active." . PHP_EOL;
			return;
		}

		try {
			if ($shouldRebuild) {
				echo "Rebuild mode: clearing ghl_processed_leads and ghl_processing_state before processing." . PHP_EOL;

				if (!$this->Ghl_Processed_Leads_Model->reset_processing_data()) {
					show_error('Failed resetting ghl lead processing data.', 500);
				}
			}

			while (true) {
				$batch = $this->Ghl_Processed_Leads_Model->get_next_conversation_batch('ghl_leads_processor', $chunkSize);

				if (empty($batch['conversations'])) {
					break;
				}

				$summary['batches']++;
				echo "Processing batch {$summary['batches']} with " . count($batch['conversations']) . " conversation(s)" . PHP_EOL;

				foreach ($batch['conversations'] as $conversationMeta) {
					$leadCount = $this->process_single_ghl_conversation(
						$conversationMeta['conversation_id'],
						(int) $conversationMeta['first_new_message_row_id']
					);
					$summary['conversations_processed']++;
					$summary['leads_rebuilt'] += $leadCount;

					echo " - {$conversationMeta['conversation_id']}: {$leadCount} lead(s)" . PHP_EOL;
				}

				$this->Ghl_Processed_Leads_Model->save_processor_state(
					'ghl_leads_processor',
					$batch['cursor']['last_processed_message_row_id']
				);
			}
		} finally {
			$this->Ghl_Processed_Leads_Model->release_processor_lock('ghl_leads_processor');
		}

		echo "Processed conversations: {$summary['conversations_processed']}" . PHP_EOL;
		echo "Rebuilt leads: {$summary['leads_rebuilt']}" . PHP_EOL;
		echo "Batches: {$summary['batches']}" . PHP_EOL;
		echo "=== GHL Lead Processing End ===" . PHP_EOL;
	}

	private function process_single_ghl_conversation($conversationId, $firstNewMessageRowId = 0)
	{
		$messages = $this->Ghl_Processed_Leads_Model->get_conversation_messages($conversationId);
		$existingConversions = $this->Ghl_Processed_Leads_Model->get_existing_conversion_map($conversationId);
		$currentAssignedTo = $this->Ghl_Processed_Leads_Model->get_conversation_assigned_to($conversationId);

		if (empty($messages)) {
			$replaced = $this->Ghl_Processed_Leads_Model->replace_conversation_leads($conversationId, array());
			if (!$replaced) {
				show_error('Failed clearing processed leads for conversation: ' . $conversationId, 500);
			}
			return 0;
		}

		$leads = array();
		$currentLead = null;
		$fallbackContactId = null;
		$now = date('Y-m-d H:i:s');

		foreach ($messages as $message) {
			$messageTimestamp = strtotime($message['message_timestamp']);

			if ($messageTimestamp === false) {
				continue;
			}

			if ($fallbackContactId === null && !empty($message['contact_id'])) {
				$fallbackContactId = $message['contact_id'];
			}

			if ($message['direction'] === 'inbound') {
				$startsNewLead = ($currentLead === null);

				if ($startsNewLead) {
					if ($currentLead !== null) {
						$currentLead['lead_ended_at'] = $message['message_timestamp'];
						$this->finalize_ghl_processed_lead($currentLead);
						$leads[] = $currentLead;
					}

					$currentLead = array(
						'conversation_id' => $conversationId,
						'contact_id' => !empty($message['contact_id']) ? $message['contact_id'] : $fallbackContactId,
						// Rebuild from the canonical message stream only. Persisted processed leads are
						// derived data and must not become future split boundaries.
						'assigned_to_user_id' => $currentAssignedTo,
						'lead_started_at' => $message['message_timestamp'],
						'lead_ended_at' => null,
						'first_customer_message_id' => $message['message_id'],
						'tracked_message_count' => 0,
						'responded_message_count' => 0,
						'avg_first_5_response_seconds' => null,
						'is_converted' => 0,
						'converted_at' => null,
						'created_at' => $now,
						'updated_at' => $now,
						'_pending_response_slots' => array(),
						'_open_response_slot' => null,
					);
					$this->initialize_ghl_processed_lead_response_slots($currentLead);
				}

				if ($currentLead !== null) {
					if ($currentLead['_open_response_slot'] !== null) {
						$slotNumber = (int) $currentLead['_open_response_slot'];
						$currentLead['response_' . $slotNumber . '_customer_message_id'] = $message['message_id'];
						$currentLead['response_' . $slotNumber . '_customer_message_at'] = $message['message_timestamp'];
					} elseif ((int) $currentLead['tracked_message_count'] < 5) {
						$slotNumber = ((int) $currentLead['tracked_message_count']) + 1;
						$currentLead['tracked_message_count'] = $slotNumber;
						$currentLead['response_' . $slotNumber . '_customer_message_id'] = $message['message_id'];
						$currentLead['response_' . $slotNumber . '_customer_message_at'] = $message['message_timestamp'];
						$currentLead['response_' . $slotNumber . '_agent_message_id'] = null;
						$currentLead['response_' . $slotNumber . '_agent_message_at'] = null;
						$currentLead['response_' . $slotNumber . '_seconds'] = null;
						$currentLead['_pending_response_slots'][] = $slotNumber;
						$currentLead['_open_response_slot'] = $slotNumber;
					}
				}
			} elseif ($message['direction'] === 'outbound' && $currentLead !== null && !empty($currentLead['_pending_response_slots'])) {
				$slotNumber = (int) $currentLead['_pending_response_slots'][0];
				$customerTimestamp = strtotime((string) $currentLead['response_' . $slotNumber . '_customer_message_at']);

				if ($customerTimestamp !== false && $messageTimestamp >= $customerTimestamp) {
					array_shift($currentLead['_pending_response_slots']);
					$currentLead['response_' . $slotNumber . '_agent_message_id'] = $message['message_id'];
					$currentLead['response_' . $slotNumber . '_agent_message_at'] = $message['message_timestamp'];
					$currentLead['response_' . $slotNumber . '_seconds'] = $messageTimestamp - $customerTimestamp;
					if ((int) $currentLead['_open_response_slot'] === $slotNumber) {
						$currentLead['_open_response_slot'] = null;
					}
				}
			}
		}

		if ($currentLead !== null) {
			$this->finalize_ghl_processed_lead($currentLead);
			$leads[] = $currentLead;
		}

		foreach ($leads as &$lead) {
			$conversionKey = $lead['lead_started_at'] . '|' . $lead['first_customer_message_id'];

			if (isset($existingConversions[$conversionKey])) {
				if (empty($lead['assigned_to_user_id']) && !empty($existingConversions[$conversionKey]['assigned_to_user_id'])) {
					$lead['assigned_to_user_id'] = $existingConversions[$conversionKey]['assigned_to_user_id'];
				}
				$lead['is_converted'] = (int) $existingConversions[$conversionKey]['is_converted'];
				$lead['converted_at'] = $existingConversions[$conversionKey]['converted_at'];
			}

			unset($lead['_pending_response_slots'], $lead['_open_response_slot']);
		}
		unset($lead);

		$leads = $this->filter_ghl_processed_leads_for_current_assignment($leads, $currentAssignedTo);

		$replaced = $this->Ghl_Processed_Leads_Model->replace_conversation_leads($conversationId, $leads);
		if (!$replaced) {
			show_error('Failed rebuilding processed leads for conversation: ' . $conversationId, 500);
		}

		return count($leads);
	}

	private function finalize_ghl_processed_lead(&$lead)
	{
		$responseTotal = 0;
		$responseCount = 0;

		for ($slotNumber = 1; $slotNumber <= 5; $slotNumber++) {
			$key = 'response_' . $slotNumber . '_seconds';
			if (isset($lead[$key]) && $lead[$key] !== null) {
				$responseTotal += (int) $lead[$key];
				$responseCount++;
			}
		}

		$lead['responded_message_count'] = $responseCount;
		$lead['avg_first_5_response_seconds'] = $responseCount > 0
			? (int) round($responseTotal / $responseCount)
			: null;
		$lead['updated_at'] = date('Y-m-d H:i:s');
	}

	private function initialize_ghl_processed_lead_response_slots(&$lead)
	{
		for ($slotNumber = 1; $slotNumber <= 5; $slotNumber++) {
			$lead['response_' . $slotNumber . '_customer_message_id'] = null;
			$lead['response_' . $slotNumber . '_customer_message_at'] = null;
			$lead['response_' . $slotNumber . '_agent_message_id'] = null;
			$lead['response_' . $slotNumber . '_agent_message_at'] = null;
			$lead['response_' . $slotNumber . '_seconds'] = null;
		}
	}

	private function filter_ghl_processed_leads_for_current_assignment($leads, $currentAssignedTo)
	{
		if (empty($leads)) {
			return $leads;
		}

		$currentAssignedTo = !empty($currentAssignedTo) ? (string) $currentAssignedTo : null;

		// Once a conversation has a newer assignee, drop legacy assignee-owned leads so
		// the dashboard only reflects the current owner from the point their lead begins.
		if ($currentAssignedTo !== null) {
			$filteredLeads = array();

			foreach ($leads as $lead) {
				$leadAssignedTo = !empty($lead['assigned_to_user_id']) ? (string) $lead['assigned_to_user_id'] : null;
				if ($leadAssignedTo === $currentAssignedTo) {
					$filteredLeads[] = $lead;
				}
			}

			if (!empty($filteredLeads)) {
				return array_values($filteredLeads);
			}
		}

		return array_values($leads);
	}

	/**
     * Sync Customer
     */
	private function syncCustomer()
	{
		echo "=== Sync Customer Start ===\n";

		$customers = $this->Customer_Model->get_pending_sycn_customers();

		$this->load->library('CustomerSync');
		$this->load->helper('autocount');
		$config = get_autocount_config();
		
		foreach ($customers as $customer) {
			echo "Customer {$customer['CustomerID']} [{$customer['AutocountSyncAction']}]... ";

			$customer = $this->enrichCustomer($customer);

			try {
				switch ($customer['AutocountSyncAction']) {
					case 'C':
						$result = $this->customersync->autocount_create($customer, $config);
						break;
					case 'U':
						$result = $this->customersync->autocount_update($customer, $config);
						break;
					case 'S':
						$result = $this->customersync->autocount_update_status($customer, $config);
						break;
					case 'D':
						$result = $this->customersync->autocount_delete($customer, $config);
						break;
					default:
						$result = ['error' => 'ERROR Autocount Sync Action'];
				}
				if (isset($result['status']) && ($result['status'] == 201 || $result['status'] == 204) && $result['error'] === null) {
					$this->Customer_Model->update_by_id($customer['CustomerID'], [
						'AutocountSyncStatus'  => 'S',
						'AutocountSyncMessage' => json_encode($result)
					]);

					if (isset($result['docNo']) && !empty($result['docNo'])) {
						$updateData['CustomerCode'] = $result['docNo'];
					}
					if (!empty($updateData)) {
						$this->Customer_Model->update_by_id($customer['CustomerID'], $updateData);
					}
					echo "SUCCESS\n";
				} else {
					$this->Customer_Model->update_by_id($customer['CustomerID'], [
						'AutocountSyncStatus'  => 'F',
						'AutocountSyncMessage' => json_encode($result)
					]);
					echo "FAILED\n";
				}
			} catch (\Exception $e) {
				$this->Customer_Model->update_by_id($customer['CustomerID'], [
					'AutocountSyncStatus'  => 'F',
					'AutocountSyncMessage' => $e->getMessage()
				]);
				echo "ERROR: {$e->getMessage()}\n";
			}
		}
		echo "=== Sync Customer End ===\n\n";
	}

	private function enrichCustomer($customer)
	{
		return $customer;
	}

	/**
     * Sync Supplier
     */
	private function syncSupplier() // creditor
	{
		echo "=== Sync Supplier Start ===\n";

		$suppliers = $this->Supplier_Model->get_pending_sycn_suppliers();

		$this->load->library('SupplierSync');
		$this->load->helper('autocount');
		$config = get_autocount_config();
		
		foreach ($suppliers as $supplier) {
			echo "Supplier ID {$supplier['SupplierID']} [{$supplier['AutocountSyncAction']}]... ";

			$supplier = $this->enrichSupplier($supplier);

			try {
				switch ($supplier['AutocountSyncAction']) {
					case 'C':
						$result = $this->suppliersync->autocount_create($supplier,$config);
						break;
					case 'U':
						$result = $this->suppliersync->autocount_update($supplier, $config);
						break;
					case 'S':
						$result = $this->suppliersync->autocount_update_status($supplier, $config);
						break;
					case 'D':
						$result = $this->suppliersync->autocount_delete($supplier, $config);
						break;
					default:
						$result = ['error' => 'ERROR Autocount Sync Action'];
				}
				if (isset($result['status']) && ($result['status'] == 201 || $result['status'] == 204) && $result['error'] === null) {
					$this->Supplier_Model->update_by_id($supplier['SupplierID'], [
						'AutocountSyncStatus'  => 'S',
						'AutocountSyncMessage' => json_encode($result)
					]);

					if (isset($result['docNo']) && !empty($result['docNo'])) {
						$updateData['SupplierCode'] = $result['docNo'];
					}
					if (!empty($updateData)){
						$this->Supplier_Model->update_by_id($supplier['SupplierID'], $updateData);
					}
					echo "SUCCESS\n";
				} else {
					$this->Supplier_Model->update_by_id($supplier['SupplierID'], [
						'AutocountSyncStatus'  => 'F',
						'AutocountSyncMessage' => json_encode($result)
					]);
					echo "FAILED\n";
				}
			} catch (\Exception $e) {
				$this->Supplier_Model->update_by_id($supplier['SupplierID'], [
					'AutocountSyncStatus'  => 'F',
					'AutocountSyncMessage' => $e->getMessage()
				]);
				echo "ERROR: {$e->getMessage()}\n";
			}
		}
		echo "=== Sync Suppliers End ===\n\n";
	}

	private function enrichSupplier($supplier)
	{
		return $supplier;
	}

    /**
     * Sync Bookings
     */
	private function syncBookings()
	{
		echo "=== Sync Booking Start ===\n";

		$bookings = $this->Booking_Model->getPendingBookingsWithDetails();

		$this->load->library('BookingSync');
		$this->load->helper('autocount');
		$config = get_autocount_config();
		
		foreach ($bookings as $booking) {
			echo "Booking ID {$booking['BookingID']} [{$booking['AutocountSyncAction']}]... ";

			$booking = $this->enrichBooking($booking);

			try {
				switch ($booking['AutocountSyncAction']) {
					case 'C':
						$result = $this->bookingsync->autocount_create($booking);
						break;
					case 'U':
						$result = $this->bookingsync->autocount_update($booking);
						break;
					case 'S':
						$result = $this->bookingsync->autocount_update_status($booking);
						break;
					case 'D':
						$result = $this->bookingsync->autocount_delete($booking);
						break;
					default:
						$result = ['error' => 'ERROR Autocount Sync Action'];
				}
				if (isset($result['status']) && ($result['status'] == 201 || $result['status'] == 204) && $result['error'] === null) {
					$this->Booking_Model->update_by_id($booking['BookingID'], [
						'AutocountSyncStatus'  => 'S',
						'AutocountSyncMessage' => json_encode($result)
					]);
					echo "SUCCESS\n";
				} else {
					$this->Booking_Model->update_by_id($booking['BookingID'], [
						'AutocountSyncStatus'  => 'F',
						'AutocountSyncMessage' => json_encode($result)
					]);
					echo "FAILED\n";
				}
			} catch (\Exception $e) {
				$this->Booking_Model->update_by_id($booking['BookingID'], [
					'AutocountSyncStatus'  => 'F',
					'AutocountSyncMessage' => $e->getMessage()
				]);
				echo "ERROR: {$e->getMessage()}\n";
			}
		}
		echo "=== Sync Bookings End ===\n\n";
	}


	private function enrichBooking($booking)
	{
		// Sales agent
		if (!empty($booking['SalesAgent'])) {
			$sale_agent = $this->Admin_Model->find($booking['SalesAgent']);
			if ($sale_agent) {
				$booking['salesAgent'] = $sale_agent->Name;
			}
		}

		//validity
		if (!empty($booking['StartDate']) && !empty($booking['EndDate'])) {
			$booking['validity'] = $booking['StartDate'] . ' - ' . $booking['EndDate'];
			$booking['BokingRemark'] = $booking['StartDate'] . ' - ' . $booking['EndDate'];
		}

		// yourRef
		if (!empty($booking['ReservationNumber'])) {
			$booking['yourRef'] = $booking['ReservationNumber'];
			$booking['remark2'] = $booking['ReservationNumber'];
		}

		// cc 
		// Check for Adult, Children, and Infant; if empty, set to 0
		$booking['cc'] = '';

		$booking['cc'] .= (!empty($booking['Adult']) ? $booking['Adult'] : 0) . ' A, ';
		$booking['cc'] .= (!empty($booking['Children']) ? $booking['Children'] : 0) . ' C, ';
		$booking['cc'] .= (!empty($booking['Infant']) ? $booking['Infant'] : 0) . ' IN';

		// Remove trailing comma and space
		$booking['cc'] = rtrim($booking['cc'], ', ');

		if (!empty($booking['cc'])) {
			$booking['remark3'] = $booking['cc'];
		}

		// deliveryTerm  
		if (!empty($booking['Destination'])) {
			$Destination = $this->Category_Model->find($booking['Destination']);
			if ($Destination) {
				$booking['Destination'] = $Destination->Name;
				$booking['deliveryTerm'] = $booking['Destination'];
				$booking['remark4'] = $booking['Destination'];
			}
		}

		if (!empty($booking['CustomerID'])) {
			$customer = $this->Customer_Model->find($booking['CustomerID']);
			if (!empty($customer)) {
				if (!empty($customer->CustomerCode) && $customer->CustomerCode != null) {
					$booking['CustomerCode'] = $customer->CustomerCode;
				}
			}
		}

		return $booking;
	}

    /**
     * Sync Payments
     */
  	private function syncPayments()
	{
		echo "=== Sync Payments Start ===\n";
		$payments = $this->Payment_Model->getAllPaymentsWithBookingAndSupplier(null, false);

		$this->load->library('PaymentSync');
		$this->load->helper('autocount');
		$config = get_autocount_config();
		
		foreach ($payments as $payment) {
			echo "Payment ID {$payment['PaymentID']} [{$payment['AutocountSyncAction']}]... ";

			$payment = $this->enrichPayment($payment, $config);

			try {
				switch ($payment['AutocountSyncAction']) {
					case 'C':
						$result = $this->paymentsync->autocount_create($payment, $config);
						break;
					case 'U':
						$result = $this->paymentsync->autocount_update($payment, $config);
						break;
					case 'D':
						$result = $this->paymentsync->autocount_delete($payment, $config);
						break;
					default:
						$result = ['error' => 'ERROR Autocount Sync Action'];
				}

				if (isset($result['status']) && ($result['status'] == 201 || $result['status'] == 204) && $result['error'] === null) {
					$updateData = [
						'AutocountSyncStatus'  => 'S',
						'AutocountSyncMessage' => json_encode($result)
					];

					if (isset($result['docNo']) && !empty($result['docNo'])) {
						$updateData['AutocountReferenceNumber'] = $result['docNo'];
					}
					if (!empty($updateData)){
						$this->Payment_Model->update_by_id($payment['PaymentID'], $updateData);
					}
					echo "SUCCESS\n";
				} else {
					$this->Payment_Model->update_by_id($payment['PaymentID'], [
						'AutocountSyncStatus'  => 'F',
						'AutocountSyncMessage' => json_encode($result)
					]);
					echo "FAILED: " . ($result['error'] ?? 'Unknown error') . "\n";
				}

			} catch (\Exception $e) {
				$this->Payment_Model->update_by_id($payment['PaymentID'], [
					'AutocountSyncStatus'  => 'F',
					'AutocountSyncMessage' => $e->getMessage()
				]);
				echo "ERROR: {$e->getMessage()}\n";
			}
		}

		echo "=== Sync Payments End ===\n\n";
	}

	private function syncDeletedPayments()
	{
		echo "=== Sync Deleted Payments Start ===\n";
		$payments = $this->Payment_Model->getAllPaymentsWithBookingAndSupplier(null, true);

		$this->load->library('PaymentSync');
		$this->load->helper('autocount');
		$config = get_autocount_config();
		
		foreach ($payments as $payment) {
			echo "Payment ID {$payment['PaymentID']} [{$payment['AutocountSyncAction']}]... ";

			$payment = $this->enrichPayment($payment, $config);

			try {
				switch ($payment['AutocountSyncAction']) {
					case 'D':
						$result = $this->paymentsync->autocount_delete($payment, $config);
						break;
					default:
						$result = ['error' => 'ERROR Autocount Sync Action'];
				}

				if (isset($result['status']) && ($result['status'] == 201 || $result['status'] == 204) && $result['error'] === null) {
					$this->Payment_Model->update_by_id($payment['PaymentID'], [
						'AutocountSyncStatus'  => 'S',
						'AutocountSyncMessage' => json_encode($result)
					]);
					echo "SUCCESS\n";
				} else {
					$this->Payment_Model->update_by_id($payment['PaymentID'], [
						'AutocountSyncStatus'  => 'F',
						'AutocountSyncMessage' => json_encode($result)
					]);
					echo "FAILED: " . ($result['error'] ?? 'Unknown error') . "\n";
				}

			} catch (\Exception $e) {
				$this->Payment_Model->update_by_id($payment['PaymentID'], [
					'AutocountSyncStatus'  => 'F',
					'AutocountSyncMessage' => $e->getMessage()
				]);
				echo "ERROR: {$e->getMessage()}\n";
			}
		}

		echo "=== Sync Deleted Payments End ===\n\n";
	}

	private function enrichPayment($payment, $config)
	{
		// Sales agent
		if (!empty($payment['SalesAgent'])) {
			$sale_agent = $this->Admin_Model->find($payment['SalesAgent']);
			if ($sale_agent) {
				$payment['salesAgent'] = $sale_agent->Name;
			}
		}

		// description
		$desc = !empty($payment['Customer']) ? $payment['Customer'] : '';
		// if ($payment['Credit'] != 0.00) { // OR
		// 	$desc = !empty($payment['Customer']) ? $payment['Customer'] : '';
		// } else { // PV
		// 	$desc = !empty($payment['supplier_name']) ? $payment['supplier_name'] : (!empty($payment['Customer']) ? $payment['Customer'] : '');
		// }

		if (!empty($payment['StartDate']) && !empty($payment['EndDate'])) {
			$travelDate = $payment['StartDate'] . ' - ' . $payment['EndDate'];
			$desc = !empty($desc) ? $desc . ' (' . $travelDate . ')' : $travelDate;
			$payment['travelDate'] = $travelDate;
		}

		if (!empty($payment['Type'])) {
			if(!empty($payment['ReservationNumber'])) {
				$payment['ReservationNumber'] .= ' ' . $payment['Type'];
			} else {
				$payment['ReservationNumber'] = $payment['Type'];
			}
		}

		$payment['description'] = trim($desc);

		// deal with 
		if ($payment['Credit'] != 0.00) { // OR
			$payment['dealWith'] = !empty($payment['Customer']) ? $payment['Customer'] : '';
		} else { // PV
			$payment['dealWith'] = !empty($payment['supplier_name']) ? $payment['supplier_name'] : '';
		}
		
		$payment['bankaccNo'] = '';

		if (!empty($payment['Type'])) {
			$type = [
				'AGENT COMMISSION',
				'CUSTOMER REFUND',
				'BANK CHARGES',
				'CREDIT CARD CHARGES',
				'ONE-TIME PAYMENT'
			];		
			if (in_array($payment['Type'], $type)) {
				$payment['dealWith'] = $payment['BankHolder'];
			}

			$bank_acc_code_types = [
				'BANK CHARGES',
				'CREDIT CARD CHARGES'
			];
			if (in_array($payment['Type'], $bank_acc_code_types)) {
				$payment['bankaccNo'] = $config['payment_acc_no_3'];
			}
		}

		$payment['CustomerCode'] = $config['payment_acc_no_1'];
		if (!empty($payment['CustomerID'])) {
			$customer = $this->Customer_Model->find($payment['CustomerID']);
			if (!empty($customer)) {
				if (!empty($customer->CustomerCode) && $customer->CustomerCode != null) {
					$payment['CustomerCode'] = $customer->CustomerCode;
				}
			}
		}

		$payment['SupplierCode'] = $config['payment_acc_no_2'];
		if (!empty($payment['SupplierID'])) {
			$supplier = $this->Supplier_Model->find($payment['SupplierID']);
			if (!empty($supplier)) {
				if (!empty($supplier->SupplierCode) && $supplier->SupplierCode != null) {
					$payment['SupplierCode'] = $supplier->SupplierCode;
				}
			}
		}

		return $payment;
	}

	// public function generateSupplierCustomerSQL() // generatequery 
	// {
	// 	$apiKey = "75be787f-d7fb-4f40-ab8d-32848af7a169";
	// 	$keyId = "f66b6d44-9433-42b1-8399-2bb5135783f3";

	// 	$supplierUrl = "https://accounting-api.autocountcloud.com/26516/creditor/listing?activeOnly=true&field=accNo&field=parentAccNo&field=desc2&field=registerNo&field=isActive&field=address&field=postCode&field=phone1&field=phone2&field=fax1&field=fax2&field=areaCode&field=emailAddress&field=webURL&field=attention&field=natureOfBusiness&field=taxCode&field=taxRegisterNo&field=note";
	// 	$customerUrl = "https://accounting-api.autocountcloud.com/26516/debtor/listing?activeOnly=true&field=accNo&field=parentAccNo&field=desc2&field=registerNo&field=isActive&field=address&field=postCode&field=phone1&field=phone2&field=fax1&field=fax2&field=deliverAddress&field=deliverPostCode&field=areaCode&field=emailAddress&field=webURL&field=attention&field=natureOfBusiness&field=salesAgent&field=taxCode&field=taxRegisterNo&field=taxExemptionNo&field=taxExemptionExpiryDate&field=note";

	// 	// Fetch data
	// 	//$responseSuppliers = $this->fetchAllAutoCount($supplierUrl, $apiKey, $keyId);
	// 	$responseCustomers = $this->fetchAllAutoCount($customerUrl, $apiKey, $keyId);


	// 	$allSuppliers = []; //$responseSuppliers ?? [];
	// 	$allCustomers = $responseCustomers ?? [];

	// 	$now = date('Y-m-d H:i:s');
	// 	$userId = 1;

	// 	$queries = [
	// 		'supplier' => [],
	// 		'customer' => []
	// 	];

	// 	// Supplier queries
	// 	// foreach ($allSuppliers as $supplier) {
	// 	// 	$supplierCode = $supplier['AccNo'] ?? null;
	// 	// 	if (!$supplierCode) continue;

	// 	// 	$exists = \DB::table('supplier')->where('SupplierCode', $supplierCode)->exists();

	// 	// 	$name = addslashes($supplier['CompanyName'] ?? '');
	// 	// 	$phone = addslashes($supplier['Phone1'] ?? '');
	// 	// 	$email = addslashes($supplier['EmailAddress'] ?? '');
	// 	// 	$currency = addslashes($supplier['CurrencyCode'] ?? '');
	// 	// 	$address = addslashes($supplier['Address'] ?? '');

	// 	// 	if ($exists) {
	// 	// 		$query = "UPDATE supplier SET
	// 	// 			Name = '{$name}',
	// 	// 			Phone = '{$phone}',
	// 	// 			PrimaryEmail = '{$email}',
	// 	// 			CurrencyCode = '{$currency}',
	// 	// 			Addressa = '{$address}',
	// 	// 			UpdateDate = '{$now}',
	// 	// 			UpdateBy = {$userId}
	// 	// 			WHERE SupplierCode = '{$supplierCode}';";
	// 	// 	} else {
	// 	// 		$query = "INSERT INTO supplier
	// 	// 			(Name, SupplierCode, Phone, PrimaryEmail, CurrencyCode, Addressa, InsertDate, InsertBy, UpdateDate, UpdateBy)
	// 	// 			VALUES
	// 	// 			('{$name}', '{$supplierCode}', '{$phone}', '{$email}', '{$currency}', '{$address}', '{$now}', {$userId}, '{$now}', {$userId});";
	// 	// 	}

	// 	// 	$queries['supplier'][] = $query;
	// 	// }

	// 	// foreach ($allSuppliers as $supplier) {
	// 	// 	$supplierCode = $supplier['AccNo'] ?? null;
	// 	// 	if (!$supplierCode) continue;

	// 	// 	$name = addslashes($supplier['CompanyName'] ?? '');
	// 	// 	$phone = addslashes($supplier['Phone1'] ?? '');
	// 	// 	$email = addslashes($supplier['EmailAddress'] ?? '');
	// 	// 	$currency = addslashes($supplier['CurrencyCode'] ?? '');
	// 	// 	$address = addslashes($supplier['Address'] ?? '');

	// 	// 	$query = "INSERT INTO supplier
	// 	// 			(Name, SupplierCode, Phone, PrimaryEmail, CurrencyCode, Address, InsertDate, InsertBy, UpdateDate, UpdateBy)
	// 	// 			VALUES
	// 	// 			('{$name}', '{$supplierCode}', '{$phone}', '{$email}', '{$currency}', '{$address}', '{$now}', '{$userId}', '{$now}', '{$userId}')";

	// 	// 	$queries['supplier'][] = $query;
	// 	// }

	// 	// Customer queries
	// 	// foreach ($allCustomers as $customer) {
	// 	// 	$customerCode = $customer['AccNo'] ?? null;
	// 	// 	if (!$customerCode) continue;

	// 	// 	$exists = \DB::table('customer')->where('CustomerCode', $customerCode)->exists();

	// 	// 	$name = addslashes($customer['CompanyName'] ?? '');
	// 	// 	$phone = addslashes($customer['Phone1'] ?? '');

	// 	// 	if ($exists) {
	// 	// 		$query = "UPDATE customer SET
	// 	// 			name = '{$name}',
	// 	// 			phone_number = '{$phone}',
	// 	// 			updated_at = '{$now}'
	// 	// 			WHERE CustomerCode = '{$customerCode}';";
	// 	// 	} else {
	// 	// 		$query = "INSERT INTO customer
	// 	// 			(name, CustomerCode, phone_number, created_at, updated_at)
	// 	// 			VALUES
	// 	// 			('{$name}', '{$customerCode}', '{$phone}', '{$now}', '{$now}');";
	// 	// 	}

	// 	// 	$queries['customer'][] = $query;
	// 	// }

	// 	foreach ($allCustomers as $customer) {
	// 			$customerCode = $customer['AccNo'] ?? null;
	// 		if (!$customerCode) continue;
	// 			$name = addslashes($customer['CompanyName'] ?? '');
	// 		$phone = addslashes($customer['Phone1'] ?? '');

	// 		$query = "INSERT INTO customer
	// 				(name, CustomerCode, phone_number, created_at, updated_at)
	// 				VALUES
	// 				('{$name}', '{$customerCode}', '{$phone}', '{$now}', '{$now}');";

	// 		$queries['customer'][] = $query;
	// 	}

	// 	// Optionally echo
	// 	// foreach ($queries['supplier'] as $index => $sql) {
	// 	// 	echo   $sql . PHP_EOL . PHP_EOL . '<br>';
	// 	// }
	// 	foreach ($queries['customer'] as $index => $sql) {
	// 		echo $sql . PHP_EOL . PHP_EOL . '<br>';
	// 	}

	// 	return $queries;
	// }

	// public function syncSuppliersAndCustomers() // straight execute
	// {
	// 	$apiKey = "75be787f-d7fb-4f40-ab8d-32848af7a169";
	// 	$keyId = "f66b6d44-9433-42b1-8399-2bb5135783f3";

	// 	$supplierUrl = "https://accounting-api.autocountcloud.com/26516/creditor/listing?activeOnly=true&field=accNo&field=parentAccNo&field=desc2&field=registerNo&field=isActive&field=address&field=postCode&field=phone1&field=phone2&field=fax1&field=fax2&field=areaCode&field=emailAddress&field=webURL&field=attention&field=natureOfBusiness&field=taxCode&field=taxRegisterNo&field=note";
	// 	$customerUrl = "https://accounting-api.autocountcloud.com/26516/debtor/listing?activeOnly=true&field=accNo&field=parentAccNo&field=desc2&field=registerNo&field=isActive&field=address&field=postCode&field=phone1&field=phone2&field=fax1&field=fax2&field=deliverAddress&field=deliverPostCode&field=areaCode&field=emailAddress&field=webURL&field=attention&field=natureOfBusiness&field=salesAgent&field=taxCode&field=taxRegisterNo&field=taxExemptionNo&field=taxExemptionExpiryDate&field=note";

	// 	// Fetch data
	// 	$responseSuppliers = $this->fetchAllAutoCount($supplierUrl, $apiKey, $keyId);
	// 	$responseCustomers = $this->fetchAllAutoCount($customerUrl, $apiKey, $keyId);

	// 	$allSuppliers = $responseSuppliers['data'] ?? [];
	// 	$allCustomers = $responseCustomers['data'] ?? [];

	// 	echo "Total suppliers: " . count($allSuppliers) . PHP_EOL;
	// 	echo "Total customers: " . count($allCustomers) . PHP_EOL;

	// 	$now = date('Y-m-d H:i:s');
	// 	$userId = 1; // fixed InsertBy / UpdateBy user ID as you requested

	// 	// Process suppliers
	// 	foreach ($allSuppliers as $supplier) {
	// 		$supplierCode = $supplier['AccNo'] ?? null;
	// 		if (!$supplierCode) {
	// 			continue; // skip if no AccNo
	// 		}

	// 		// Check if supplier exists in DB by SupplierCode
	// 		$exists = \DB::table('supplier')->where('SupplierCode', $supplierCode)->exists();

	// 		$data = [
	// 			'Name'          => $supplier['CompanyName'] ?? '',
	// 			'SupplierCode'  => $supplierCode,
	// 			'Phone'         => $supplier['Phone1'] ?? '',
	// 			'PrimaryEmail'  => $supplier['EmailAddress'] ?? '',
	// 			'CurrencyCode'  => $supplier['CurrencyCode'] ?? '',
	// 			'Addressa'      => $supplier['Address'] ?? '',
	// 		];

	// 		if ($exists) {
	// 			// Update existing
	// 			\DB::table('supplier')
	// 				->where('SupplierCode', $supplierCode)
	// 				->update(array_merge($data, [
	// 					'UpdateDate' => $now,
	// 					'UpdateBy'   => $userId,
	// 				]));
	// 			echo "Updated supplier: $supplierCode" . PHP_EOL;
	// 		} else {
	// 			// Insert new
	// 			\DB::table('supplier')->insert(array_merge($data, [
	// 				'InsertDate' => $now,
	// 				'InsertBy'   => $userId,
	// 				'UpdateDate' => $now,
	// 				'UpdateBy'   => $userId,
	// 			]));
	// 			echo "Inserted supplier: $supplierCode" . PHP_EOL;
	// 		}
	// 	}

	// 	// Process customers
	// 	foreach ($allCustomers as $customer) {
	// 		$customerCode = $customer['AccNo'] ?? null;
	// 		if (!$customerCode) {
	// 			continue; // skip if no AccNo
	// 		}

	// 		// Check if customer exists in DB by CustomerCode
	// 		$exists = \DB::table('customer')->where('CustomerCode', $customerCode)->exists();

	// 		$data = [
	// 			'name'          => $customer['CompanyName'] ?? '',
	// 			'CustomerCode'  => $customerCode,
	// 			'phone_number'  => $customer['Phone1'] ?? '',
	// 			// add other fields here if needed
	// 		];

	// 		if ($exists) {
	// 			// Update existing
	// 			\DB::table('customer')
	// 				->where('CustomerCode', $customerCode)
	// 				->update(array_merge($data, [
	// 					'updated_at' => $now,
	// 				]));
	// 			echo "Updated customer: $customerCode" . PHP_EOL;
	// 		} else {
	// 			// Insert new
	// 			\DB::table('customer')->insert(array_merge($data, [
	// 				'created_at' => $now,
	// 				'updated_at' => $now,
	// 			]));
	// 			echo "Inserted customer: $customerCode" . PHP_EOL;
	// 		}
	// 	}
	// }


	public function fetchAllAutoCount($url, $apiKey, $keyId)
	{
		$allData = [];
		$page = 1;
		$totalCount = null;

		do {
			$fullUrl = $url . "&page=" . $page;

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $fullUrl);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, [
				"API-Key: $apiKey",
				"Key-ID: $keyId",
				"Content-Type: application/json"
			]);

			$response = curl_exec($ch);
			curl_close($ch);

			$json = json_decode($response, true);

			if (!$json || !isset($json['data'])) {
				break; // invalid response
			}

			// Set totalCount once
			if ($totalCount === null && isset($json['totalCount'])) {
				$totalCount = (int) $json['totalCount'];
			}

			// Merge results
			$allData = array_merge($allData, $json['data']);

			$page++;
		
		} while (count($allData) < $totalCount);

		return $allData;
	}

	public function mapSupplierCustomerFromAutoCount()
	{
		$this->load->helper('autocount');
		$config = get_autocount_config();
		
		$apiKey = $this->input->get('key');
    	$expectedKey = $config['manual_sync_autocount_key'];

		if ($apiKey !== $expectedKey) {
			show_error('Unauthorized access', 401);
			return;
		}

		$dryRun = true; // SET TO FALSE after testing

		$apiKey = "75be787f-d7fb-4f40-ab8d-32848af7a169";
		$keyId = "f66b6d44-9433-42b1-8399-2bb5135783f3";

		$supplierUrl = "https://accounting-api.autocountcloud.com/26516/creditor/listing?activeOnly=true&field=accNo&field=companyName";
		$customerUrl = "https://accounting-api.autocountcloud.com/26516/debtor/listing?activeOnly=true&field=accNo&field=companyName";

		// Fetch from API
		$apiSuppliers = $this->fetchAllAutoCount($supplierUrl, $apiKey, $keyId) ?? [];
		$apiCustomers = $this->fetchAllAutoCount($customerUrl, $apiKey, $keyId) ?? [];

		// INDEX API DATA BY NAME
		$supplierIndex = [];
		foreach ($apiSuppliers as $item) {
			$key = trim(strtoupper($item['CompanyName'] ?? ''));
			if ($key) $supplierIndex[$key] = $item;
		}

		$customerIndex = [];
		foreach ($apiCustomers as $item) {
			$key = trim(strtoupper($item['CompanyName'] ?? ''));
			if ($key) $customerIndex[$key] = $item;
		}

		// SYSTEM SUPPLIERS
		$systemSuppliers = $this->db
			->where('SupplierCode IS NULL', null, false)
			->where('AutocountSyncStatus', 'P')
			->where('AutocountSyncAction', 'C')
			->where('Name IS NOT NULL', null, false)
			->where('Name !=', '')
			->get('supplier')
			->result();

		// SYSTEM CUSTOMERS
		$systemCustomers = $this->db
			->where('CustomerCode IS NULL', null, false)
			->where('AutocountSyncStatus', 'P')
			->where('AutocountSyncAction', 'C')
			->where('Status', 'Y')
			->where('name IS NOT NULL', null, false)
			->where('name !=', '')
			->get('customer')
			->result();

		$now = date('Y-m-d H:i:s');
		$supplierUpdates = 0;
		$customerUpdates = 0;

		echo "===== SUPPLIER MATCHES =====\n";

		foreach ($systemSuppliers as $row) {

			$nameKey = strtoupper(trim($row->Name));

			if (isset($supplierIndex[$nameKey])) {

				$apiItem = $supplierIndex[$nameKey];

				echo "Match Supplier: {$row->Name} → {$apiItem['AccNo']}\n";

				if (!$dryRun) {

					$this->db->where('SupplierID', $row->SupplierID)
						->update('supplier', [
							'SupplierCode'         => $apiItem['AccNo'],
							'AutocountSyncStatus'  => 'S',
							'AutocountSyncMessage' => json_encode($apiItem),
							'UpdateDate'           => $now,
							'UpdateBy'             => 1,
						]);
				}

				$supplierUpdates++;
			}
		}

		echo "\n===== CUSTOMER MATCHES =====\n";

		foreach ($systemCustomers as $row) {

			$nameKey = strtoupper(trim($row->name));

			if (isset($customerIndex[$nameKey])) {

				$apiItem = $customerIndex[$nameKey];

				echo "Match Customer: {$row->name} → {$apiItem['AccNo']}\n";

				if (!$dryRun) {

					$this->db->where('CustomerID', $row->CustomerID)
						->update('customer', [
							'CustomerCode'         => $apiItem['AccNo'],
							'AutocountSyncStatus'  => 'S',
							'AutocountSyncMessage' => json_encode($apiItem),
							'updated_at'           => $now,
						]);
				}

				$customerUpdates++;
			}
		}

		echo "\n===== SUMMARY =====\n";
		print_r(json_encode([
			'dry_run_mode'            => $dryRun,
			'total_api_suppliers'     => count($apiSuppliers),
			'total_api_customers'     => count($apiCustomers),
			'system_suppliers_to_map' => count($systemSuppliers),
			'system_customers_to_map' => count($systemCustomers),
			'supplier_matched'        => $supplierUpdates,
			'customer_matched'        => $customerUpdates,
		]));
	}

	/**
	 * GHL users sync. CLI only: php index.php Cron syncGhlUsers
	 */
	public function syncGhlUsers()
	{
		if (!$this->input->is_cli_request()) {
			show_error('Not allowed', 403);
			return;
		}

		$this->load->library('GhlUsersSyncService');
		$result = $this->ghluserssyncservice->sync();

		$this->output
			->set_content_type('application/json')
			->set_output(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	}

	/**
	 * GHL contacts sync. CLI only: php index.php Cron syncGhlContacts
	 */
	public function syncGhlContacts()
	{
		if (!$this->input->is_cli_request()) {
			show_error('Not allowed', 403);
			return;
		}

		$args = isset($_SERVER['argv']) ? $_SERVER['argv'] : array();
		$uriSegments = $this->uri->segment_array();
		$flags = array_merge(
			array_slice($args, 3),
			$uriSegments ? array_slice($uriSegments, 2) : array()
		);

		$this->load->library('GhlContactsSyncService');
		$result = $this->ghlcontactssyncservice->sync(array(
			'mode' => in_array('--full', $flags, true) ? 'full' : 'recent',
		));

		$this->output
			->set_content_type('application/json')
			->set_output(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	}

	/**
	 * GHL conversations sync. CLI only: php index.php Cron syncGhlConversations
	 */
	public function syncGhlConversations()
	{
		if (!$this->input->is_cli_request()) {
			show_error('Not allowed', 403);
			return;
		}

		$args = isset($_SERVER['argv']) ? $_SERVER['argv'] : array();
		$uriSegments = $this->uri->segment_array();
		$flags = array_merge(
			array_slice($args, 3),
			$uriSegments ? array_slice($uriSegments, 2) : array()
		);

		$this->load->library('GhlConversationsSyncService');
		$result = $this->ghlconversationssyncservice->sync(array(
			'mode' => in_array('--full', $flags, true) ? 'full' : 'recent',
		));

		$this->output
			->set_content_type('application/json')
			->set_output(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	}

	/**
	 * GHL messages sync. CLI only: php index.php Cron syncGhlMessages
	 */
	public function syncGhlMessages()
	{
		if (!$this->input->is_cli_request()) {
			show_error('Not allowed', 403);
			return;
		}

		$args = isset($_SERVER['argv']) ? $_SERVER['argv'] : array();
		$uriSegments = $this->uri->segment_array();
		$flags = array_merge(
			array_slice($args, 3),
			$uriSegments ? array_slice($uriSegments, 2) : array()
		);

		$this->load->library('GhlMessagesSyncService');
		$result = $this->ghlmessagessyncservice->sync(array(
			'mode' => in_array('--full', $flags, true) ? 'full' : 'recent',
		));

		$this->output
			->set_content_type('application/json')
			->set_output(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	}

	public function AddCustomerFromAutoCount()
	{
		$this->load->helper('autocount');
		$config = get_autocount_config();

		// 🔐 Simple API key protection
		$apiKey = $this->input->get('key');
		$expectedKey = $config['manual_sync_autocount_key'];

		if ($apiKey !== $expectedKey) {
			show_error('Unauthorized access', 401);
			return;
		}

		// 🔁 Dry run mode (SET FALSE AFTER TESTING)
		$dryRun = true;

		// 🔑 AutoCount API credentials
		$apiKey = "75be787f-d7fb-4f40-ab8d-32848af7a169";
		$keyId  = "f66b6d44-9433-42b1-8399-2bb5135783f3";

		// 🌐 AutoCount customer listing API
		$customerUrl = "https://accounting-api.autocountcloud.com/26516/debtor/listing"
			. "?activeOnly=true"
			. "&field=accNo"
			. "&field=companyName"
			. "&field=phone1";

		// 📡 Fetch customers from AutoCount
		$apiCustomers = $this->fetchAllAutoCount($customerUrl, $apiKey, $keyId) ?? [];

		// 📦 Existing CustomerCode index (FAST lookup)
		$existingCodes = $this->db
			->select('CustomerCode')
			->where('CustomerCode IS NOT NULL', null, false)
			->get('customer')
			->result_array();

		$existingIndex = array_flip(
			array_map('strtoupper', array_column($existingCodes, 'CustomerCode'))
		);

		$now       = date('Y-m-d H:i:s');
		$inserted  = 0;
		$ignored   = 0;

		echo "\n===== AUTOCOUNT CUSTOMER SYNC =====\n";

		foreach ($apiCustomers as $item) {

			$accNo = strtoupper(trim($item['AccNo'] ?? ''));

			// Skip invalid records
			if (!$accNo) {
				continue;
			}

			// 🚫 Ignore if already exists
			if (isset($existingIndex[$accNo])) {
				echo "Ignored (exists): {$accNo}\n";
				$ignored++;
				continue;
			}

			echo "Insert New Customer: {$accNo} - {$item['CompanyName']}\n";

			if (!$dryRun) {
				// $this->db->insert('customer', [
				// 	'CustomerCode'         => $item['AccNo'],
				// 	'name'                 => $item['CompanyName'] ?? '',
				// 	'phone_number'         => $item['Phone1'] ?? '',
				// 	'Status'               => 'Y',
				// 	'AutocountSyncStatus'  => 'S',
				// 	'AutocountSyncAction'  => 'C',
				// 	'AutocountSyncMessage' => json_encode($item),
				// 	'created_at'           => $now,
				// 	'updated_at'           => $now,
				// ]);
			}

			// Add to index to prevent double insert in same run
			$existingIndex[$accNo] = true;
			$inserted++;
		}

		echo "\n===== SUMMARY =====\n";
		echo json_encode([
			'dry_run_mode'        => $dryRun,
			'total_api_customers' => count($apiCustomers),
			'inserted'            => $inserted,
			'ignored_existing'    => $ignored,
		], JSON_PRETTY_PRINT);
	}

	public function customCronLogging($message, $meta = array())
	{
		if ($this->allowGhlModuleLog) {
			logInFile('GHL_MODULES_SYNC', $message, $meta);
		}
	}

}
