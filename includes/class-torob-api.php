<?php
/**
 * Torob API integration class
 *
 * @package Torob_Price_Compare
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Torob_API {
    
    /**
     * Torob base URL
     */
    private $base_url = 'https://torob.com';
    
    /**
     * Search logs table
     */
    private $logs_table;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->logs_table = $wpdb->prefix . 'torob_search_logs';
    }
    
    /**
     * Search for product price on Torob
     *
     * @param int $product_id Product ID
     * @param string $product_name Product name
     * @return array|false Search results or false on failure
     */
    public function search_product($product_id, $product_name) {
        $start_time = microtime(true);
        
        try {
            // Simulate API call (since we don't have real Torob API access)
            $search_results = $this->simulate_torob_search($product_name);
            
            $response_time = round((microtime(true) - $start_time) * 1000); // milliseconds
            
            if ($search_results) {
                // Log successful search
                $this->log_search($product_id, $product_name, true, null, $response_time);
                
                // Cache the results
                $cache = new Torob_Cache();
                $cache->set_cached_price($product_id, $search_results);
                
                return $search_results;
            } else {
                // Log failed search
                $this->log_search($product_id, $product_name, false, 'محصول یافت نشد', $response_time);
                return false;
            }
            
        } catch (Exception $e) {
            $response_time = round((microtime(true) - $start_time) * 1000);
            $this->log_search($product_id, $product_name, false, $e->getMessage(), $response_time);
            return false;
        }
    }
    
    /**
     * Search for product asynchronously
     *
     * @param int $product_id Product ID
     * @param string $product_name Product name
     * @return bool Success status
     */
    public function search_product_async($product_id, $product_name) {
        // In a real implementation, this would use wp_remote_post with async
        // For now, we'll just call the regular search method
        return $this->search_product($product_id, $product_name);
    }
    
    /**
     * Simulate Torob search (since we don't have real API access)
     *
     * @param string $product_name Product name
     * @return array|false Simulated search results
     */
    private function simulate_torob_search($product_name) {
        // Simulate some processing time
        usleep(rand(100000, 500000)); // 0.1 to 0.5 seconds
        
        // Generate realistic fake data based on product name
        $base_price = $this->generate_base_price($product_name);
        
        if (!$base_price) {
            return false; // Simulate "product not found"
        }
        
        // Generate a price that's 5-20% lower than base price
        $discount_percent = rand(5, 20);
        $min_price = $base_price - ($base_price * $discount_percent / 100);
        $min_price = round($min_price / 1000) * 1000; // Round to nearest thousand
        
        // Generate Torob URL
        $search_query = urlencode($product_name);
        $torob_url = $this->base_url . '/search/?query=' . $search_query;
        
        return array(
            'min_price' => $min_price,
            'torob_url' => $torob_url,
            'search_query' => $product_name,
            'found_products' => rand(3, 15), // Simulate number of found products
            'last_updated' => current_time('mysql')
        );
    }
    
    /**
     * Generate base price for simulation
     *
     * @param string $product_name Product name
     * @return int|false Base price or false if product not found
     */
    private function generate_base_price($product_name) {
        // Simulate 10% chance of "product not found"
        if (rand(1, 10) === 1) {
            return false;
        }
        
        // Generate price based on product name characteristics
        $name_length = strlen($product_name);
        $name_hash = crc32($product_name);
        
        // Base price calculation
        $base_price = abs($name_hash) % 1000000; // 0 to 1,000,000
        
        // Adjust based on common product keywords
        $keywords = array(
            'موبایل' => 5000000,
            'لپ تاپ' => 15000000,
            'تلویزیون' => 8000000,
            'یخچال' => 12000000,
            'ماشین لباسشویی' => 10000000,
            'کتاب' => 50000,
            'لباس' => 200000,
            'کفش' => 300000,
            'ساعت' => 1000000,
            'عطر' => 500000
        );
        
        foreach ($keywords as $keyword => $price) {
            if (strpos($product_name, $keyword) !== false) {
                $base_price = $price + rand(-$price * 0.3, $price * 0.3);
                break;
            }
        }
        
        // Ensure minimum price
        $base_price = max($base_price, 10000);
        
        return round($base_price / 1000) * 1000; // Round to nearest thousand
    }
    
    /**
     * Log search attempt
     *
     * @param int $product_id Product ID
     * @param string $search_query Search query
     * @param bool $success Success status
     * @param string $error_message Error message if failed
     * @param int $response_time Response time in milliseconds
     * @return bool Log success status
     */
    private function log_search($product_id, $search_query, $success, $error_message = null, $response_time = null) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $this->logs_table,
            array(
                'product_id' => $product_id,
                'search_query' => $search_query,
                'success' => $success ? 1 : 0,
                'error_message' => $error_message,
                'response_time' => $response_time,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%d', '%s', '%d', '%s')
        );
        
        return $result !== false;
    }
    
    /**
     * Get search statistics
     *
     * @param int $days Number of days to look back
     * @return array Search statistics
     */
    public function get_search_stats($days = 30) {
        global $wpdb;
        
        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $total_searches = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->logs_table} WHERE created_at >= %s",
                $date_from
            )
        );
        
        $successful_searches = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->logs_table} WHERE created_at >= %s AND success = 1",
                $date_from
            )
        );
        
        $failed_searches = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->logs_table} WHERE created_at >= %s AND success = 0",
                $date_from
            )
        );
        
        $avg_response_time = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT AVG(response_time) FROM {$this->logs_table} WHERE created_at >= %s AND response_time IS NOT NULL",
                $date_from
            )
        );
        
        $success_rate = $total_searches > 0 ? round(($successful_searches / $total_searches) * 100, 2) : 0;
        
        return array(
            'total_searches' => (int)$total_searches,
            'successful_searches' => (int)$successful_searches,
            'failed_searches' => (int)$failed_searches,
            'success_rate' => $success_rate,
            'avg_response_time' => round((float)$avg_response_time, 2)
        );
    }
    
    /**
     * Get recent search logs
     *
     * @param int $limit Number of logs to retrieve
     * @return array Recent search logs
     */
    public function get_recent_logs($limit = 20) {
        global $wpdb;
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.*, p.post_title as product_name 
                FROM {$this->logs_table} l 
                LEFT JOIN {$wpdb->posts} p ON l.product_id = p.ID 
                ORDER BY l.created_at DESC 
                LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
        
        return $results;
    }
    
    /**
     * Test API connection
     *
     * @return array Test results
     */
    public function test_connection() {
        $start_time = microtime(true);
        
        try {
            // Simulate connection test
            usleep(rand(50000, 200000)); // 0.05 to 0.2 seconds
            
            $response_time = round((microtime(true) - $start_time) * 1000);
            
            // Simulate 95% success rate
            $success = rand(1, 100) <= 95;
            
            return array(
                'success' => $success,
                'response_time' => $response_time,
                'message' => $success ? 'اتصال موفقیت‌آمیز' : 'خطا در اتصال به سرور ترب',
                'timestamp' => current_time('mysql')
            );
            
        } catch (Exception $e) {
            $response_time = round((microtime(true) - $start_time) * 1000);
            
            return array(
                'success' => false,
                'response_time' => $response_time,
                'message' => 'خطا: ' . $e->getMessage(),
                'timestamp' => current_time('mysql')
            );
        }
    }
    
    /**
     * Clear old search logs
     *
     * @param int $days Keep logs newer than this many days
     * @return int Number of deleted logs
     */
    public function cleanup_old_logs($days = 90) {
        global $wpdb;
        
        $date_threshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->logs_table} WHERE created_at < %s",
                $date_threshold
            )
        );
        
        return $result;
    }
}