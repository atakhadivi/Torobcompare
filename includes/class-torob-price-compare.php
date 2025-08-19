<?php
/**
 * Main plugin class
 *
 * @package Torob_Price_Compare
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Torob_Price_Compare {
    
    /**
     * Plugin version
     */
    const VERSION = '1.0.0';
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Admin instance
     */
    public $admin;
    
    /**
     * Frontend instance
     */
    public $frontend;
    
    /**
     * API instance
     */
    public $api;
    
    /**
     * Cache instance
     */
    public $cache;
    
    /**
     * AJAX instance
     */
    public $ajax;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init();
    }
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize the plugin
     */
    private function init() {
        // Initialize components
        $this->cache = new Torob_Cache();
        $this->api = new Torob_API();
        
        // Initialize admin and frontend
        if (is_admin()) {
            $this->admin = new Torob_Admin();
        } else {
            $this->frontend = new Torob_Frontend();
        }
        
        // Initialize AJAX handlers
        $this->ajax = new Torob_Ajax();
        
        // Add hooks
        $this->add_hooks();
    }
    
    /**
     * Add WordPress hooks
     */
    private function add_hooks() {
        // Plugin loaded hook
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // WooCommerce hooks
        add_action('woocommerce_single_product_summary', array($this, 'display_price_comparison'), 25);
        
        // Cron hooks for cache cleanup
        add_action('torob_cleanup_cache', array($this, 'cleanup_expired_cache'));
        
        // Schedule cache cleanup if not already scheduled
        if (!wp_next_scheduled('torob_cleanup_cache')) {
            wp_schedule_event(time(), 'daily', 'torob_cleanup_cache');
        }
    }
    
    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'torob-price-compare',
            false,
            dirname(plugin_basename(TOROB_PRICE_COMPARE_PLUGIN_FILE)) . '/languages'
        );
    }
    
    /**
     * Display price comparison on product page
     */
    public function display_price_comparison() {
        global $product;
        
        if (!$product || !$this->is_plugin_enabled()) {
            return;
        }
        
        $product_id = $product->get_id();
        $product_name = $product->get_name();
        
        // Get cached price or fetch new one
        $price_data = $this->cache->get_cached_price($product_id);
        
        if (!$price_data) {
            // Try to fetch price in background
            $this->api->search_product_async($product_id, $product_name);
            return;
        }
        
        // Display the price comparison
        $this->frontend->display_price_widget($price_data);
    }
    
    /**
     * Check if plugin is enabled
     */
    public function is_plugin_enabled() {
        $settings = get_option('torob_price_compare_settings', array());
        return isset($settings['api_enabled']) && $settings['api_enabled'];
    }
    
    /**
     * Cleanup expired cache entries
     */
    public function cleanup_expired_cache() {
        $this->cache->cleanup_expired();
    }
    
    /**
     * Plugin activation
     */
    public static function activate() {
        // Create database tables
        self::create_tables();
        
        // Set default options
        self::set_default_options();
        
        // Schedule cache cleanup
        if (!wp_next_scheduled('torob_cleanup_cache')) {
            wp_schedule_event(time(), 'daily', 'torob_cleanup_cache');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('torob_cleanup_cache');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin uninstall
     */
    public static function uninstall() {
        // Remove database tables
        self::drop_tables();
        
        // Remove options
        delete_option('torob_price_compare_settings');
        delete_option('torob_price_compare_version');
        
        // Clear scheduled events
        wp_clear_scheduled_hook('torob_cleanup_cache');
    }
    
    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Price cache table
        $cache_table = $wpdb->prefix . 'torob_price_cache';
        $cache_sql = "CREATE TABLE $cache_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id bigint(20) UNSIGNED NOT NULL,
            min_price int(11) DEFAULT NULL,
            torob_url varchar(500) DEFAULT NULL,
            search_query varchar(255) NOT NULL,
            last_updated datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY idx_product_id (product_id),
            KEY idx_expires_at (expires_at),
            KEY idx_torob_cache_product_expires (product_id, expires_at),
            KEY idx_torob_cache_updated (last_updated DESC)
        ) $charset_collate;";
        
        // Search logs table
        $logs_table = $wpdb->prefix . 'torob_search_logs';
        $logs_sql = "CREATE TABLE $logs_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id bigint(20) UNSIGNED NOT NULL,
            search_query varchar(255) NOT NULL,
            success tinyint(1) DEFAULT 0,
            error_message text DEFAULT NULL,
            response_time int(11) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_product_id (product_id),
            KEY idx_created_at (created_at DESC),
            KEY idx_success (success)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($cache_sql);
        dbDelta($logs_sql);
    }
    
    /**
     * Drop database tables
     */
    private static function drop_tables() {
        global $wpdb;
        
        $cache_table = $wpdb->prefix . 'torob_price_cache';
        $logs_table = $wpdb->prefix . 'torob_search_logs';
        
        $wpdb->query("DROP TABLE IF EXISTS $cache_table");
        $wpdb->query("DROP TABLE IF EXISTS $logs_table");
    }
    
    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        $default_settings = array(
            'api_enabled' => true,
            'cache_duration' => 24, // hours
            'display_position' => 'after_price',
            'auto_update' => true,
            'show_last_updated' => true,
            'custom_css' => ''
        );
        
        add_option('torob_price_compare_settings', $default_settings);
        add_option('torob_price_compare_version', self::VERSION);
    }
}