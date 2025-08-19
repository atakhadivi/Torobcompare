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
     * User agents for rotation
     */
    private $user_agents = array(
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.1 Safari/605.1.15'
    );
    
    /**
     * Rate limiting - minimum seconds between requests
     */
    private $rate_limit = 2;
    
    /**
     * Last request timestamp
     */
    private static $last_request_time = 0;
    
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
        $error_message = null;
        
        // Validate input
        if (empty($product_name) || strlen(trim($product_name)) < 2) {
            $error_message = 'نام محصول نامعتبر است';
            $this->log_search($product_id, $product_name, false, $error_message, 0);
            return false;
        }
        
        // Check cache first to minimize scraping requests
        if (class_exists('Torob_Cache')) {
            $cache = new Torob_Cache();
            $cached_result = $cache->get_cached_price($product_id);
            
            if ($cached_result && !$cache->is_cache_expired($product_id)) {
                // Return cached data if still valid
                return $cached_result;
            }
        }
        
        try {
            // Check if we're being rate limited
            if ($this->is_rate_limited()) {
                $error_message = 'درخواست‌های زیاد - لطفاً کمی صبر کنید';
                throw new Exception($error_message);
            }
            
            // Perform web scraping
            $search_results = $this->scrape_torob_search($product_name);
            
            $response_time = round((microtime(true) - $start_time) * 1000); // milliseconds
            
            if ($search_results) {
                // Check if we got valid price data
                if (isset($search_results['min_price']) && $search_results['min_price'] > 0) {
                    // Log successful search
                    $this->log_search($product_id, $product_name, true, null, $response_time);
                    
                    // Cache the results with enhanced metadata
                    if (class_exists('Torob_Cache')) {
                        $cache = new Torob_Cache();
                        
                        // Prepare enhanced cache data
                        $cache_data = array_merge($search_results, array(
                            'cached_at' => current_time('mysql'),
                            'scraping_method' => 'web_scraping',
                            'user_agent_used' => $this->get_random_user_agent(),
                            'response_time' => $response_time,
                            'cache_version' => '2.0' // Version for cache compatibility
                        ));
                        
                        $cache->set_cached_price($product_id, $cache_data);
                    }
                    
                    return $search_results;
                } else if (isset($search_results['error'])) {
                    // Partial success - products found but prices not extractable
                    $error_message = $search_results['error'];
                    $this->log_search($product_id, $product_name, false, $error_message, $response_time);
                    return false;
                } else {
                    $error_message = 'قیمت معتبر یافت نشد';
                    $this->log_search($product_id, $product_name, false, $error_message, $response_time);
                    return false;
                }
            } else {
                // No results found
                $error_message = 'محصول در ترب یافت نشد';
                $this->log_search($product_id, $product_name, false, $error_message, $response_time);
                return false;
            }
            
        } catch (Exception $e) {
            $response_time = round((microtime(true) - $start_time) * 1000);
            $error_message = $this->categorize_error($e->getMessage());
            $this->log_search($product_id, $product_name, false, $error_message, $response_time);
            
            // Invalidate cache on repeated failures
            if (class_exists('Torob_Cache')) {
                $cache = new Torob_Cache();
                $this->handle_cache_invalidation($cache, $product_id, $error_message);
            }
            
            // Log detailed error for debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Torob scraping error for product ' . $product_id . ': ' . $e->getMessage());
            }
            
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
     * Search Torob using web scraping
     *
     * @param string $product_name Product name
     * @return array|false Search results or false on failure
     */
    private function scrape_torob_search($product_name) {
        // Apply rate limiting
        $this->apply_rate_limit();
        
        // Prepare search URL
        $search_query = urlencode($product_name);
        $search_url = $this->base_url . '/search/?query=' . $search_query;
        
        // Get random user agent
        $user_agent = $this->get_random_user_agent();
        
        // Prepare request arguments
        $args = array(
            'timeout' => 15,
            'user-agent' => $user_agent,
            'headers' => array(
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'fa-IR,fa;q=0.9,en;q=0.8',
                'Accept-Encoding' => 'gzip, deflate',
                'DNT' => '1',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',
            ),
            'sslverify' => false
        );
        
        // Make the request
        $response = wp_remote_get($search_url, $args);
        
        // Check for errors
        if (is_wp_error($response)) {
            throw new Exception('خطا در اتصال به ترب: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            throw new Exception('خطا در دریافت اطلاعات از ترب. کد خطا: ' . $response_code);
        }
        
        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            throw new Exception('پاسخ خالی از سرور ترب');
        }
        
        // Parse HTML and extract prices
        $parsed_data = $this->parse_torob_html($body, $product_name);
        
        if ($parsed_data) {
            $parsed_data['torob_url'] = $search_url;
            $parsed_data['search_query'] = $product_name;
            $parsed_data['last_updated'] = current_time('mysql');
        }
        
        return $parsed_data;
    }
    
    /**
     * Apply rate limiting between requests
     */
    private function apply_rate_limit() {
        $current_time = time();
        $time_since_last_request = $current_time - self::$last_request_time;
        
        if ($time_since_last_request < $this->rate_limit) {
            $sleep_time = $this->rate_limit - $time_since_last_request;
            sleep($sleep_time);
        }
        
        self::$last_request_time = time();
    }
    
    /**
     * Check if we're being rate limited
     *
     * @return bool True if rate limited
     */
    private function is_rate_limited() {
        // Check recent failed requests
        global $wpdb;
        
        $recent_failures = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->logs_table} 
                WHERE success = 0 
                AND created_at >= %s 
                AND (error_message LIKE '%timeout%' OR error_message LIKE '%blocked%' OR error_message LIKE '%403%' OR error_message LIKE '%429%')",
                date('Y-m-d H:i:s', strtotime('-5 minutes'))
            )
        );
        
        // If more than 3 failures in last 5 minutes, consider rate limited
        return $recent_failures > 3;
    }
    
    /**
     * Categorize error messages for better user understanding
     *
     * @param string $error_message Original error message
     * @return string Categorized error message
     */
    private function categorize_error($error_message) {
        $error_lower = strtolower($error_message);
        
        // Network/connection errors
        if (strpos($error_lower, 'timeout') !== false || strpos($error_lower, 'timed out') !== false) {
            return 'خطا در اتصال - زمان انتظار تمام شد';
        }
        
        if (strpos($error_lower, 'connection') !== false || strpos($error_lower, 'network') !== false) {
            return 'خطا در اتصال به شبکه';
        }
        
        // HTTP errors
        if (strpos($error_lower, '403') !== false || strpos($error_lower, 'forbidden') !== false) {
            return 'دسترسی مسدود شده - لطفاً بعداً تلاش کنید';
        }
        
        if (strpos($error_lower, '404') !== false || strpos($error_lower, 'not found') !== false) {
            return 'صفحه مورد نظر یافت نشد';
        }
        
        if (strpos($error_lower, '429') !== false || strpos($error_lower, 'too many') !== false) {
            return 'درخواست‌های زیاد - لطفاً کمی صبر کنید';
        }
        
        if (strpos($error_lower, '500') !== false || strpos($error_lower, 'internal server') !== false) {
            return 'خطا در سرور ترب';
        }
        
        // SSL/Security errors
        if (strpos($error_lower, 'ssl') !== false || strpos($error_lower, 'certificate') !== false) {
            return 'خطا در گواهی امنیتی';
        }
        
        // Parsing errors
        if (strpos($error_lower, 'parse') !== false || strpos($error_lower, 'html') !== false) {
            return 'خطا در تجزیه اطلاعات دریافتی';
        }
        
        // Default: return original message if it's in Persian, otherwise generic message
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $error_message)) {
            return $error_message;
        }
        
        return 'خطا در دریافت اطلاعات از ترب';
    }
    
    /**
     * Handle cache invalidation for failed scraping attempts
     *
     * @param Torob_Cache $cache Cache instance
     * @param int $product_id Product ID
     * @param string $error_message Error message
     */
    private function handle_cache_invalidation($cache, $product_id, $error_message) {
        // Check if this is a critical error that should invalidate cache
        $critical_errors = array(
            'دسترسی مسدود شده',
            'درخواست‌های زیاد',
            'خطا در سرور ترب',
            'خطا در تجزیه اطلاعات'
        );
        
        $should_invalidate = false;
        foreach ($critical_errors as $critical_error) {
            if (strpos($error_message, $critical_error) !== false) {
                $should_invalidate = true;
                break;
            }
        }
        
        if ($should_invalidate) {
            // Check recent failure count for this product
            global $wpdb;
            $recent_failures = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->logs_table} 
                    WHERE product_id = %d 
                    AND success = 0 
                    AND created_at >= %s",
                    $product_id,
                    date('Y-m-d H:i:s', strtotime('-1 hour'))
                )
            );
            
            // Invalidate cache if more than 2 failures in last hour
            if ($recent_failures > 2) {
                $cache->delete_cached_price($product_id);
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Cache invalidated for product ' . $product_id . ' due to repeated failures: ' . $error_message);
                }
            }
        }
    }
    
    /**
     * Get random user agent for requests
     *
     * @return string Random user agent
     */
    private function get_random_user_agent() {
        $index = array_rand($this->user_agents);
        return $this->user_agents[$index];
    }
    
    /**
     * Parse Torob HTML to extract product prices
     *
     * @param string $html HTML content
     * @param string $product_name Original product name
     * @return array|false Parsed data or false if no products found
     */
    private function parse_torob_html($html, $product_name) {
        // Remove extra whitespace and normalize
        $html = preg_replace('/\s+/', ' ', $html);
        
        // Look for price patterns in the HTML
        $prices = array();
        $found_products = 0;
        
        // Pattern 1: Look for price spans with common class names
        $price_patterns = array(
            '/class=["\'].*price.*["\'][^>]*>([0-9,]+)\s*تومان/iu',
            '/class=["\'].*amount.*["\'][^>]*>([0-9,]+)\s*تومان/iu',
            '/([0-9,]+)\s*تومان/iu',
            '/>\s*([0-9,]+)\s*ریال/iu'
        );
        
        foreach ($price_patterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                foreach ($matches[1] as $price_str) {
                    $price = $this->normalize_price($price_str);
                    if ($price > 0) {
                        $prices[] = $price;
                        $found_products++;
                    }
                }
                if (!empty($prices)) {
                    break; // Found prices with this pattern
                }
            }
        }
        
        // If no prices found with patterns, try a more general approach
        if (empty($prices)) {
            // Look for any number followed by تومان or ریال
            if (preg_match_all('/([0-9,]+)\s*(?:تومان|ریال)/iu', $html, $matches)) {
                foreach ($matches[1] as $price_str) {
                    $price = $this->normalize_price($price_str);
                    if ($price > 1000) { // Filter out very small numbers
                        $prices[] = $price;
                        $found_products++;
                    }
                }
            }
        }
        
        // If still no prices, check for product existence
        if (empty($prices)) {
            // Look for indicators that products were found but prices might be hidden
            $product_indicators = array(
                '/class=["\'].*product.*["\']/',
                '/class=["\'].*item.*["\']/',
                '/محصول/',
                '/کالا/'
            );
            
            foreach ($product_indicators as $indicator) {
                if (preg_match($indicator, $html)) {
                    // Products found but no prices extracted
                    return array(
                        'min_price' => null,
                        'found_products' => 1,
                        'error' => 'محصولات یافت شد اما قیمت‌ها قابل استخراج نیست'
                    );
                }
            }
            
            return false; // No products found
        }
        
        // Remove duplicates and sort
        $prices = array_unique($prices);
        sort($prices);
        
        // Get minimum price
        $min_price = min($prices);
        
        return array(
            'min_price' => $min_price,
            'found_products' => $found_products,
            'all_prices' => array_slice($prices, 0, 10) // Keep first 10 prices
        );
    }
    
    /**
     * Normalize price string to integer
     *
     * @param string $price_str Price string
     * @return int Normalized price
     */
    private function normalize_price($price_str) {
        // Remove commas and convert to integer
        $price = str_replace(',', '', $price_str);
        $price = intval($price);
        
        // Convert ریال to تومان if needed (divide by 10)
        if ($price > 100000000) { // Likely in ریال
            $price = $price / 10;
        }
        
        return $price;
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
     * Test web scraping connection
     *
     * @return array Test results
     */
    public function test_connection() {
        $start_time = microtime(true);
        
        try {
            // Test with a simple search
            $test_query = 'موبایل';
            $search_url = $this->base_url . '/search/?query=' . urlencode($test_query);
            
            $user_agent = $this->get_random_user_agent();
            
            $args = array(
                'timeout' => 10,
                'user-agent' => $user_agent,
                'headers' => array(
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'fa-IR,fa;q=0.9,en;q=0.8',
                ),
                'sslverify' => false
            );
            
            $response = wp_remote_get($search_url, $args);
            $response_time = round((microtime(true) - $start_time) * 1000);
            
            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'response_time' => $response_time,
                    'message' => 'خطا در اتصال: ' . $response->get_error_message(),
                    'timestamp' => current_time('mysql')
                );
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            
            if ($response_code === 200) {
                $body = wp_remote_retrieve_body($response);
                $has_content = !empty($body) && strlen($body) > 1000;
                
                return array(
                    'success' => $has_content,
                    'response_time' => $response_time,
                    'message' => $has_content ? 'اتصال موفقیت‌آمیز و محتوا دریافت شد' : 'اتصال برقرار شد اما محتوا کامل نیست',
                    'response_code' => $response_code,
                    'content_length' => strlen($body),
                    'timestamp' => current_time('mysql')
                );
            } else {
                return array(
                    'success' => false,
                    'response_time' => $response_time,
                    'message' => 'خطا در دریافت اطلاعات. کد خطا: ' . $response_code,
                    'response_code' => $response_code,
                    'timestamp' => current_time('mysql')
                );
            }
            
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