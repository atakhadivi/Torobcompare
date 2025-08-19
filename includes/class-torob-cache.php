<?php
/**
 * Cache management class
 *
 * @package Torob_Price_Compare
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Torob_Cache {
    
    /**
     * Cache table name
     */
    private $cache_table;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->cache_table = $wpdb->prefix . 'torob_price_cache';
    }
    
    /**
     * Get cached price for a product
     *
     * @param int $product_id Product ID
     * @return array|false Cached data or false if not found/expired
     */
    public function get_cached_price($product_id) {
        global $wpdb;
        
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->cache_table} 
                WHERE product_id = %d 
                AND expires_at > NOW() 
                ORDER BY last_updated DESC 
                LIMIT 1",
                $product_id
            ),
            ARRAY_A
        );
        
        if (!$result) {
            return false;
        }
        
        return array(
            'min_price' => $result['min_price'],
            'torob_url' => $result['torob_url'],
            'last_updated' => $result['last_updated'],
            'search_query' => $result['search_query']
        );
    }
    
    /**
     * Set cached price for a product
     *
     * @param int $product_id Product ID
     * @param array $data Price data
     * @return bool Success status
     */
    public function set_cached_price($product_id, $data) {
        global $wpdb;
        
        $settings = get_option('torob_price_compare_settings', array());
        $cache_duration = isset($settings['cache_duration']) ? (int)$settings['cache_duration'] : 24;
        
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$cache_duration} hours"));
        
        // Delete existing cache for this product
        $this->delete_cached_price($product_id);
        
        // Insert new cache entry
        $result = $wpdb->insert(
            $this->cache_table,
            array(
                'product_id' => $product_id,
                'min_price' => isset($data['min_price']) ? $data['min_price'] : null,
                'torob_url' => isset($data['torob_url']) ? $data['torob_url'] : '',
                'search_query' => isset($data['search_query']) ? $data['search_query'] : '',
                'last_updated' => current_time('mysql'),
                'expires_at' => $expires_at
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s')
        );
        
        return $result !== false;
    }
    
    /**
     * Delete cached price for a product
     *
     * @param int $product_id Product ID
     * @return bool Success status
     */
    public function delete_cached_price($product_id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            $this->cache_table,
            array('product_id' => $product_id),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Clear all cached prices
     *
     * @return bool Success status
     */
    public function clear_all_cache() {
        global $wpdb;
        
        $result = $wpdb->query("TRUNCATE TABLE {$this->cache_table}");
        
        return $result !== false;
    }
    
    /**
     * Cleanup expired cache entries
     *
     * @return int Number of deleted entries
     */
    public function cleanup_expired() {
        global $wpdb;
        
        $result = $wpdb->query(
            "DELETE FROM {$this->cache_table} WHERE expires_at < NOW()"
        );
        
        return $result;
    }
    
    /**
     * Get cache statistics
     *
     * @return array Cache statistics
     */
    public function get_cache_stats() {
        global $wpdb;
        
        $total_entries = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->cache_table}"
        );
        
        $expired_entries = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->cache_table} WHERE expires_at < NOW()"
        );
        
        $valid_entries = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->cache_table} WHERE expires_at > NOW()"
        );
        
        $cache_size = $wpdb->get_var(
            "SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'DB Size in MB' 
            FROM information_schema.tables 
            WHERE table_schema = DATABASE() 
            AND table_name = '{$this->cache_table}'"
        );
        
        return array(
            'total_entries' => (int)$total_entries,
            'expired_entries' => (int)$expired_entries,
            'valid_entries' => (int)$valid_entries,
            'cache_size_mb' => (float)$cache_size
        );
    }
    
    /**
     * Get recent cache entries
     *
     * @param int $limit Number of entries to retrieve
     * @return array Recent cache entries
     */
    public function get_recent_entries($limit = 10) {
        global $wpdb;
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.*, p.post_title as product_name 
                FROM {$this->cache_table} c 
                LEFT JOIN {$wpdb->posts} p ON c.product_id = p.ID 
                ORDER BY c.last_updated DESC 
                LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
        
        return $results;
    }
    
    /**
     * Check if cache is enabled
     *
     * @return bool Cache enabled status
     */
    public function is_cache_enabled() {
        $settings = get_option('torob_price_compare_settings', array());
        return isset($settings['cache_duration']) && $settings['cache_duration'] > 0;
    }
    
    /**
     * Get cache duration in hours
     *
     * @return int Cache duration
     */
    public function get_cache_duration() {
        $settings = get_option('torob_price_compare_settings', array());
        return isset($settings['cache_duration']) ? (int)$settings['cache_duration'] : 24;
    }
    
    /**
     * Update cache duration
     *
     * @param int $hours Cache duration in hours
     * @return bool Success status
     */
    public function update_cache_duration($hours) {
        $settings = get_option('torob_price_compare_settings', array());
        $settings['cache_duration'] = (int)$hours;
        
        return update_option('torob_price_compare_settings', $settings);
    }
    
    /**
     * Force refresh cache for a product
     *
     * @param int $product_id Product ID
     * @return bool Success status
     */
    public function force_refresh($product_id) {
        // Delete existing cache
        $this->delete_cached_price($product_id);
        
        // Get product name
        $product = wc_get_product($product_id);
        if (!$product) {
            return false;
        }
        
        // Trigger new API search
        $api = new Torob_API();
        return $api->search_product($product_id, $product->get_name());
    }
}