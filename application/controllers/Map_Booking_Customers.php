<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Map Booking CustomerID and CustomerCode by Customer Name
 * 
 * This script:
 * 1. Finds bookings where CustomerID is NULL but Customer (name) is not NULL
 * 2. Matches bookings with customers by name (case-insensitive)
 * 3. Updates bookings with CustomerID and CustomerCode from matched customer
 * 
 * Usage:
 *   - CLI only:  php index.php Map_Booking_Customers
 * 
 * Options:
 *   --dry-run    : Show what would be updated without making changes
 *   --verbose    : Show detailed information for each booking
 */
class Map_Booking_Customers extends CI_Controller
{
    private $dry_run = false;
    private $verbose = false;

    /**
     * Remap to handle CLI flags that CodeIgniter treats as method names
     */
    public function _remap($method = 'index')
    {
        // Only allow CLI execution
        if (!$this->input->is_cli_request()) {
            show_error('This script can only be run from the command line.', 403);
            return;
        }

        // Parse command line arguments from raw argv
        $args = isset($_SERVER['argv']) ? $_SERVER['argv'] : [];
        
        // Get all segments (CodeIgniter treats --dry-run as a method/segment)
        $uri_segments = $this->uri->segment_array();
        
        // Combine argv flags and URI segments
        $all_flags = array_merge(
            array_slice($args, 3), // Skip: php, index.php, controller_name
            $uri_segments ? array_slice($uri_segments, 1) : [] // Skip first segment (controller)
        );
        
        // Check for flags
        if (in_array('--dry-run', $all_flags)) {
            $this->dry_run = true;
            echo "🔍 DRY RUN MODE - No changes will be made" . PHP_EOL;
        }
        if (in_array('--verbose', $all_flags)) {
            $this->verbose = true;
        }
        
        // Call the actual index method
        $this->index();
    }

    public function index()
    {
        // Only allow CLI execution
        if (!$this->input->is_cli_request()) {
            show_error('This script can only be run from the command line.', 403);
            return;
        }

        echo "⚙️ Starting Booking-Customer mapping..." . PHP_EOL;
        echo str_repeat('-', 60) . PHP_EOL;

        // Find bookings with null CustomerID but non-null Customer name
        $this->db->select('BookingID, BookingNumber, Customer, CustomerCode');
        $this->db->from('booking');
        $this->db->where('CustomerID IS NULL', null, false);
        $this->db->where('Customer IS NOT NULL', null, false);
        $this->db->where('Customer !=', '');
        $this->db->order_by('BookingID', 'ASC');
        $bookings = $this->db->get()->result_array();

        if (empty($bookings)) {
            echo "✅ No bookings found that need mapping." . PHP_EOL;
            return;
        }

        echo "📋 Found " . count($bookings) . " booking(s) with null CustomerID" . PHP_EOL;
        echo str_repeat('-', 60) . PHP_EOL;

        $updated_count = 0;
        $skipped_count = 0;
        $error_count = 0;
        $no_match_count = 0;
        $multiple_match_count = 0;

        // Process each booking
        foreach ($bookings as $booking) {
            $booking_id = $booking['BookingID'];
            $booking_number = $booking['BookingNumber'];
            $customer_name = trim($booking['Customer']);

            if ($this->verbose) {
                echo PHP_EOL . "🔍 Processing Booking #{$booking_number} (ID: {$booking_id})" . PHP_EOL;
                echo "   Customer Name: {$customer_name}" . PHP_EOL;
            }

            // Find matching customer(s) by name (case-insensitive)
            // Clean the customer name - remove newlines, carriage returns, tabs, and normalize whitespace
            $clean_customer_name = trim(preg_replace('/[\r\n\t]+/', ' ', $customer_name));
            $clean_customer_name = preg_replace('/\s+/', ' ', $clean_customer_name);
            $clean_customer_name_lower = strtolower($clean_customer_name);
            
            // Escape the customer name for use in SQL
            $escaped_name = $this->db->escape($clean_customer_name_lower);
            
            // Use raw query with proper escaping to handle special characters
            $sql = "SELECT CustomerID, CustomerCode, name 
                    FROM customer 
                    WHERE LOWER(TRIM(REPLACE(REPLACE(REPLACE(name, CHAR(13), ' '), CHAR(10), ' '), CHAR(9), ' '))) = {$escaped_name}
                    AND Status != 'N'";
            $customers = $this->db->query($sql)->result_array();

            if (empty($customers)) {
                if ($this->verbose) {
                    echo "   ⚠️  No matching customer found" . PHP_EOL;
                }
                $no_match_count++;
                $skipped_count++;
                continue;
            }

            if (count($customers) > 1) {
                if ($this->verbose) {
                    echo "   ⚠️  Multiple customers found with same name (" . count($customers) . " matches)" . PHP_EOL;
                    foreach ($customers as $cust) {
                        echo "      - CustomerID: {$cust['CustomerID']}, Code: {$cust['CustomerCode']}" . PHP_EOL;
                    }
                    echo "   ⚠️  Using first match (CustomerID: {$customers[0]['CustomerID']})" . PHP_EOL;
                }
                $multiple_match_count++;
            }

            // Use the first match (or only match)
            $matched_customer = $customers[0];
            $customer_id = $matched_customer['CustomerID'];
            $customer_code = $matched_customer['CustomerCode'];

            if ($this->verbose) {
                echo "   ✅ Matched CustomerID: {$customer_id}, CustomerCode: {$customer_code}" . PHP_EOL;
            }

            // Update booking
            if (!$this->dry_run) {
                $update_data = [
                    'CustomerID' => $customer_id
                ];

                // Only update CustomerCode if it's different or null
                if (!empty($customer_code) && $booking['CustomerCode'] != $customer_code) {
                    $update_data['CustomerCode'] = $customer_code;
                }

                $this->db->where('BookingID', $booking_id);
                $update_result = $this->db->update('booking', $update_data);

                if ($update_result) {
                    if ($this->verbose) {
                        echo "   ✅ Updated successfully" . PHP_EOL;
                    }
                    $updated_count++;
                } else {
                    $db_error = $this->db->error();
                    $error_msg = !empty($db_error['message']) ? $db_error['message'] : 'Unknown error';
                    if ($this->verbose) {
                        echo "   ❌ Update failed: {$error_msg}" . PHP_EOL;
                    }
                    $error_count++;
                }
            } else {
                // Dry run - just show what would be updated
                if ($this->verbose) {
                    echo "   [DRY RUN] Would update:" . PHP_EOL;
                    echo "      CustomerID: NULL → {$customer_id}" . PHP_EOL;
                    if (!empty($customer_code) && $booking['CustomerCode'] != $customer_code) {
                        echo "      CustomerCode: {$booking['CustomerCode']} → {$customer_code}" . PHP_EOL;
                    }
                }
                $updated_count++;
            }
        }

        // Summary
        echo PHP_EOL . str_repeat('=', 60) . PHP_EOL;
        echo "📊 Summary:" . PHP_EOL;
        echo str_repeat('-', 60) . PHP_EOL;
        echo "   ✅ Updated: {$updated_count} booking(s)" . PHP_EOL;
        echo "   ⏭️  Skipped: {$skipped_count} booking(s)" . PHP_EOL;
        
        if ($no_match_count > 0) {
            echo "   ⚠️  No match found: {$no_match_count} booking(s)" . PHP_EOL;
        }
        
        if ($multiple_match_count > 0) {
            echo "   ⚠️  Multiple matches: {$multiple_match_count} booking(s) (used first match)" . PHP_EOL;
        }
        
        if ($error_count > 0) {
            echo "   ❌ Errors: {$error_count} booking(s)" . PHP_EOL;
        }

        if ($this->dry_run) {
            echo PHP_EOL . "💡 This was a DRY RUN. No changes were made." . PHP_EOL;
            echo "   Run without --dry-run to apply changes." . PHP_EOL;
        } else {
            echo PHP_EOL . "🎉 Mapping completed successfully." . PHP_EOL;
        }
    }

    /**
     * Show statistics about unmapped bookings
     */
    public function stats()
    {
        // Only allow CLI execution
        if (!$this->input->is_cli_request()) {
            show_error('This script can only be run from the command line.', 403);
            return;
        }

        echo "📊 Booking-Customer Mapping Statistics" . PHP_EOL;
        echo str_repeat('=', 60) . PHP_EOL;

        // Total bookings with null CustomerID
        $this->db->select('COUNT(*) as total');
        $this->db->from('booking');
        $this->db->where('CustomerID IS NULL', null, false);
        $this->db->where('Customer IS NOT NULL', null, false);
        $this->db->where('Customer !=', '');
        $total = $this->db->get()->row_array()['total'];

        echo "📋 Bookings with null CustomerID: {$total}" . PHP_EOL;

        if ($total > 0) {
            // Get unique customer names
            $this->db->select('Customer, COUNT(*) as booking_count');
            $this->db->from('booking');
            $this->db->where('CustomerID IS NULL', null, false);
            $this->db->where('Customer IS NOT NULL', null, false);
            $this->db->where('Customer !=', '');
            $this->db->group_by('Customer');
            $this->db->order_by('booking_count', 'DESC');
            $this->db->limit(20);
            $customer_names = $this->db->get()->result_array();

            echo PHP_EOL . "Top 20 Customer Names (by booking count):" . PHP_EOL;
            echo str_repeat('-', 60) . PHP_EOL;
            foreach ($customer_names as $row) {
                $name = $row['Customer'];
                $count = $row['booking_count'];
                
                // Check if customer exists
                $this->db->select('CustomerID, CustomerCode');
                $this->db->from('customer');
                $this->db->where('LOWER(name)', strtolower($name), false);
                $this->db->where('Status !=', 'N');
                $customer = $this->db->get()->row_array();
                
                $status = $customer ? "✅ (CustomerID: {$customer['CustomerID']})" : "❌ (No match)";
                echo sprintf("   %-40s %3d booking(s) %s", $name, $count, $status) . PHP_EOL;
            }
        }
    }
}

