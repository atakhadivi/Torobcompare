<?php
/**
 * AJAX functionality for Torob Price Compare plugin
 *
 * @package Torob_Price_Compare
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Torob_AJAX {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Frontend AJAX actions (for logged in and non-logged in users)
        add_action('wp_ajax_torob_search_price', array($this, 'search_price'));
        add_action('wp_ajax_nopriv_torob_search_price', array($this, 'search_price'));
        
        add_action('wp_ajax_torob_refresh_price', array($this, 'refresh_price'));
        add_action('wp_ajax_nopriv_torob_refresh_price', array($this, 'refresh_price'));
        
        add_action('wp_ajax_torob_get_cached_price', array($this, 'get_cached_price'));
        add_action('wp_ajax_nopriv_torob_get_cached_price', array($this, 'get_cached_price'));
        
        // Admin AJAX actions (already defined in admin class, but we can add more here)
        add_action('wp_ajax_torob_bulk_search', array($this, 'bulk_search'));
        add_action('wp_ajax_torob_export_data', array($this, 'export_data'));
    }
    
    /**
     * Search for product price on Torob
     */
    public function search_price() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'torob_frontend_nonce')) {
            wp_send_json_error('نامعتبر است nonce');
        }
        
        $product_id = intval($_POST['product_id']);
        $product_name = sanitize_text_field($_POST['product_name']);
        
        if (!$product_id || !$product_name) {
            wp_send_json_error('اطلاعات محصول نامعتبر است');
        }
        
        // Check if plugin is enabled
        if (!get_option('torob_enabled', 1)) {
            wp_send_json_error('پلاگین غیرفعال است');
        }
        
        // Get product
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error('محصول یافت نشد');
        }
        
        // Search on Torob
        $api = new Torob_API();
        $result = $api->search_product($product_id, $product_name);
        
        if ($result) {
            // Get frontend instance to render HTML
            $frontend = new Torob_Frontend();
            $html = $frontend->get_price_comparison_data($product_id);
            
            wp_send_json_success(array(
                'message' => 'جستجو موفقیت‌آمیز بود',
                'data' => $result,
                'html' => $html['html'] ?? '',
                'product_price' => $product->get_price(),
                'savings' => max(0, $product->get_price() - $result['min_price'])
            ));
        } else {
            wp_send_json_error('محصول در ترب یافت نشد');
        }
    }
    
    /**
     * Refresh cached price for a product
     */
    public function refresh_price() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'torob_frontend_nonce')) {
            wp_send_json_error('نامعتبر است nonce');
        }
        
        $product_id = intval($_POST['product_id']);
        
        if (!$product_id) {
            wp_send_json_error('شناسه محصول نامعتبر است');
        }
        
        // Check if plugin is enabled
        if (!get_option('torob_enabled', 1)) {
            wp_send_json_error('پلاگین غیرفعال است');
        }
        
        // Get product
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error('محصول یافت نشد');
        }
        
        // Force refresh cache
        $cache = new Torob_Cache();
        $cache->delete_cached_price($product_id);
        
        // Search again
        $api = new Torob_API();
        $result = $api->search_product($product_id, $product->get_name());
        
        if ($result) {
            // Get frontend instance to render HTML
            $frontend = new Torob_Frontend();
            $html = $frontend->get_price_comparison_data($product_id);
            
            wp_send_json_success(array(
                'message' => 'قیمت بروزرسانی شد',
                'data' => $result,
                'html' => $html['html'] ?? '',
                'product_price' => $product->get_price(),
                'savings' => max(0, $product->get_price() - $result['min_price'])
            ));
        } else {
            wp_send_json_error('خطا در بروزرسانی قیمت');
        }
    }
    
    /**
     * Get cached price for a product
     */
    public function get_cached_price() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'torob_frontend_nonce')) {
            wp_send_json_error('نامعتبر است nonce');
        }
        
        $product_id = intval($_POST['product_id']);
        
        if (!$product_id) {
            wp_send_json_error('شناسه محصول نامعتبر است');
        }
        
        // Get cached price
        $cache = new Torob_Cache();
        $cached_price = $cache->get_cached_price($product_id);
        
        if ($cached_price) {
            $product = wc_get_product($product_id);
            $product_price = $product ? $product->get_price() : 0;
            
            wp_send_json_success(array(
                'has_cache' => true,
                'data' => $cached_price,
                'product_price' => $product_price,
                'savings' => max(0, $product_price - $cached_price['min_price'])
            ));
        } else {
            wp_send_json_success(array(
                'has_cache' => false
            ));
        }
    }
    
    /**
     * Bulk search for multiple products (Admin only)
     */
    public function bulk_search() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('دسترسی غیرمجاز');
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'torob_admin_nonce')) {
            wp_send_json_error('نامعتبر است nonce');
        }
        
        $product_ids = array_map('intval', $_POST['product_ids'] ?? array());
        
        if (empty($product_ids)) {
            wp_send_json_error('هیچ محصولی انتخاب نشده');
        }
        
        // Limit to prevent timeout
        if (count($product_ids) > 50) {
            wp_send_json_error('حداکثر ۵۰ محصول در هر بار قابل پردازش است');
        }
        
        $api = new Torob_API();
        $results = array();
        $success_count = 0;
        $error_count = 0;
        
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product) {
                $error_count++;
                continue;
            }
            
            $result = $api->search_product($product_id, $product->get_name());
            
            if ($result) {
                $success_count++;
                $results[] = array(
                    'product_id' => $product_id,
                    'product_name' => $product->get_name(),
                    'status' => 'success',
                    'torob_price' => $result['min_price']
                );
            } else {
                $error_count++;
                $results[] = array(
                    'product_id' => $product_id,
                    'product_name' => $product->get_name(),
                    'status' => 'error',
                    'torob_price' => null
                );
            }
            
            // Add small delay to prevent overwhelming the server
            usleep(100000); // 0.1 second
        }
        
        wp_send_json_success(array(
            'message' => sprintf('جستجو تکمیل شد. %d موفق، %d ناموفق', $success_count, $error_count),
            'results' => $results,
            'summary' => array(
                'total' => count($product_ids),
                'success' => $success_count,
                'error' => $error_count
            )
        ));
    }
    
    /**
     * Export data (Admin only)
     */
    public function export_data() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('دسترسی غیرمجاز');
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'torob_admin_nonce')) {
            wp_send_json_error('نامعتبر است nonce');
        }
        
        $export_type = sanitize_text_field($_POST['export_type'] ?? 'cache');
        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        
        switch ($export_type) {
            case 'cache':
                $data = $this->export_cache_data($format);
                break;
            case 'logs':
                $data = $this->export_logs_data($format);
                break;
            case 'stats':
                $data = $this->export_stats_data($format);
                break;
            default:
                wp_send_json_error('نوع صادرات نامعتبر است');
        }
        
        if ($data) {
            wp_send_json_success(array(
                'message' => 'صادرات موفقیت‌آمیز بود',
                'data' => $data,
                'filename' => 'torob_' . $export_type . '_' . date('Y-m-d_H-i-s') . '.' . $format
            ));
        } else {
            wp_send_json_error('خطا در صادرات داده‌ها');
        }
    }
    
    /**
     * Export cache data
     *
     * @param string $format Export format
     * @return string|false Exported data or false on failure
     */
    private function export_cache_data($format) {
        global $wpdb;
        
        $cache_table = $wpdb->prefix . 'torob_price_cache';
        
        $results = $wpdb->get_results(
            "SELECT c.*, p.post_title as product_name 
            FROM {$cache_table} c 
            LEFT JOIN {$wpdb->posts} p ON c.product_id = p.ID 
            ORDER BY c.cached_at DESC",
            ARRAY_A
        );
        
        if (empty($results)) {
            return false;
        }
        
        if ($format === 'json') {
            return wp_json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } else {
            // CSV format
            $csv = "Product ID,Product Name,Min Price,Torob URL,Cached At,Expires At\n";
            foreach ($results as $row) {
                $csv .= sprintf(
                    "%d,\"%s\",%d,\"%s\",\"%s\",\"%s\"\n",
                    $row['product_id'],
                    str_replace('"', '""', $row['product_name'] ?: 'N/A'),
                    $row['min_price'],
                    $row['torob_url'],
                    $row['cached_at'],
                    $row['expires_at']
                );
            }
            return $csv;
        }
    }
    
    /**
     * Export logs data
     *
     * @param string $format Export format
     * @return string|false Exported data or false on failure
     */
    private function export_logs_data($format) {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'torob_search_logs';
        
        $results = $wpdb->get_results(
            "SELECT l.*, p.post_title as product_name 
            FROM {$logs_table} l 
            LEFT JOIN {$wpdb->posts} p ON l.product_id = p.ID 
            ORDER BY l.created_at DESC 
            LIMIT 1000",
            ARRAY_A
        );
        
        if (empty($results)) {
            return false;
        }
        
        if ($format === 'json') {
            return wp_json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } else {
            // CSV format
            $csv = "Product ID,Product Name,Search Query,Success,Error Message,Response Time,Created At\n";
            foreach ($results as $row) {
                $csv .= sprintf(
                    "%d,\"%s\",\"%s\",%s,\"%s\",%d,\"%s\"\n",
                    $row['product_id'],
                    str_replace('"', '""', $row['product_name'] ?: 'N/A'),
                    str_replace('"', '""', $row['search_query']),
                    $row['success'] ? 'Yes' : 'No',
                    str_replace('"', '""', $row['error_message'] ?: ''),
                    $row['response_time'] ?: 0,
                    $row['created_at']
                );
            }
            return $csv;
        }
    }
    
    /**
     * Export stats data
     *
     * @param string $format Export format
     * @return string|false Exported data or false on failure
     */
    private function export_stats_data($format) {
        $api = new Torob_API();
        $cache = new Torob_Cache();
        
        $stats = array(
            'search_stats_7_days' => $api->get_search_stats(7),
            'search_stats_30_days' => $api->get_search_stats(30),
            'search_stats_90_days' => $api->get_search_stats(90),
            'cache_stats' => $cache->get_cache_stats(),
            'export_date' => current_time('mysql')
        );
        
        if ($format === 'json') {
            return wp_json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } else {
            // CSV format
            $csv = "Metric,7 Days,30 Days,90 Days\n";
            $csv .= sprintf("Total Searches,%d,%d,%d\n", 
                $stats['search_stats_7_days']['total_searches'],
                $stats['search_stats_30_days']['total_searches'],
                $stats['search_stats_90_days']['total_searches']
            );
            $csv .= sprintf("Successful Searches,%d,%d,%d\n", 
                $stats['search_stats_7_days']['successful_searches'],
                $stats['search_stats_30_days']['successful_searches'],
                $stats['search_stats_90_days']['successful_searches']
            );
            $csv .= sprintf("Success Rate,%.2f%%,%.2f%%,%.2f%%\n", 
                $stats['search_stats_7_days']['success_rate'],
                $stats['search_stats_30_days']['success_rate'],
                $stats['search_stats_90_days']['success_rate']
            );
            $csv .= sprintf("Avg Response Time,%.2f ms,%.2f ms,%.2f ms\n", 
                $stats['search_stats_7_days']['avg_response_time'],
                $stats['search_stats_30_days']['avg_response_time'],
                $stats['search_stats_90_days']['avg_response_time']
            );
            $csv .= "\nCache Stats\n";
            $csv .= sprintf("Total Cached,%d\n", $stats['cache_stats']['total_cached']);
            $csv .= sprintf("Expired Cache,%d\n", $stats['cache_stats']['expired_cache']);
            
            return $csv;
        }
    }
    
    /**
     * Validate product access (helper method)
     *
     * @param int $product_id Product ID
     * @return bool|WC_Product Product object or false
     */
    private function validate_product_access($product_id) {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return false;
        }
        
        // Check if product is published
        if ($product->get_status() !== 'publish') {
            return false;
        }
        
        return $product;
    }
    
    /**
     * Rate limiting check (helper method)
     *
     * @param string $action Action name
     * @param int $limit Requests per minute
     * @return bool True if within limit
     */
    private function check_rate_limit($action, $limit = 10) {
        $user_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $transient_key = 'torob_rate_limit_' . $action . '_' . md5($user_ip);
        
        $requests = get_transient($transient_key) ?: 0;
        
        if ($requests >= $limit) {
            return false;
        }
        
        set_transient($transient_key, $requests + 1, 60); // 1 minute
        
        return true;
    }
}