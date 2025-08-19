<?php
/**
 * Uninstall Torob Price Compare Plugin
 *
 * @package Torob_Price_Compare
 * @version 1.0.0
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Security check
if (!current_user_can('activate_plugins')) {
    return;
}

// Check if it was intended as an uninstall
if (__FILE__ != WP_UNINSTALL_PLUGIN) {
    return;
}

/**
 * Clean up plugin data on uninstall
 */
class Torob_Price_Compare_Uninstaller {
    
    /**
     * Run uninstall process
     */
    public static function uninstall() {
        global $wpdb;
        
        // Remove database tables
        self::drop_tables();
        
        // Remove plugin options
        self::remove_options();
        
        // Clear scheduled events
        self::clear_scheduled_events();
        
        // Remove user meta
        self::remove_user_meta();
        
        // Clear transients
        self::clear_transients();
        
        // Remove uploaded files (if any)
        self::remove_uploaded_files();
        
        // Clear any cached data
        self::clear_cache();
    }
    
    /**
     * Drop plugin database tables
     */
    private static function drop_tables() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'torob_price_cache',
            $wpdb->prefix . 'torob_search_logs'
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
    }
    
    /**
     * Remove plugin options
     */
    private static function remove_options() {
        $options = [
            'torob_price_compare_settings',
            'torob_price_compare_version',
            'torob_price_compare_db_version',
            'torob_price_compare_activation_time',
            'torob_price_compare_api_key',
            'torob_price_compare_api_endpoint',
            'torob_price_compare_cache_duration',
            'torob_price_compare_display_position',
            'torob_price_compare_button_text',
            'torob_price_compare_auto_search',
            'torob_price_compare_show_savings',
            'torob_price_compare_show_torob_link',
            'torob_price_compare_custom_css',
            'torob_price_compare_request_timeout',
            'torob_price_compare_rate_limit',
            'torob_price_compare_enabled'
        ];
        
        foreach ($options as $option) {
            delete_option($option);
            delete_site_option($option); // For multisite
        }
    }
    
    /**
     * Clear scheduled events
     */
    private static function clear_scheduled_events() {
        // Clear cron events
        wp_clear_scheduled_hook('torob_price_compare_cleanup_cache');
        wp_clear_scheduled_hook('torob_price_compare_cleanup_logs');
        wp_clear_scheduled_hook('torob_price_compare_update_prices');
    }
    
    /**
     * Remove user meta related to plugin
     */
    private static function remove_user_meta() {
        global $wpdb;
        
        $meta_keys = [
            'torob_price_compare_last_search',
            'torob_price_compare_preferences',
            'torob_price_compare_dismissed_notices'
        ];
        
        foreach ($meta_keys as $meta_key) {
            $wpdb->delete(
                $wpdb->usermeta,
                ['meta_key' => $meta_key],
                ['%s']
            );
        }
    }
    
    /**
     * Clear plugin transients
     */
    private static function clear_transients() {
        global $wpdb;
        
        // Delete transients with plugin prefix
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_torob_price_compare_%' 
             OR option_name LIKE '_transient_timeout_torob_price_compare_%'"
        );
        
        // For multisite
        if (is_multisite()) {
            $wpdb->query(
                "DELETE FROM {$wpdb->sitemeta} 
                 WHERE meta_key LIKE '_site_transient_torob_price_compare_%' 
                 OR meta_key LIKE '_site_transient_timeout_torob_price_compare_%'"
            );
        }
    }
    
    /**
     * Remove uploaded files
     */
    private static function remove_uploaded_files() {
        $upload_dir = wp_upload_dir();
        $plugin_upload_dir = $upload_dir['basedir'] . '/torob-price-compare';
        
        if (is_dir($plugin_upload_dir)) {
            self::delete_directory($plugin_upload_dir);
        }
    }
    
    /**
     * Clear cache
     */
    private static function clear_cache() {
        // Clear object cache
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // Clear any external cache if needed
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }
        
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }
    }
    
    /**
     * Recursively delete directory
     *
     * @param string $dir Directory path
     * @return bool
     */
    private static function delete_directory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            
            if (is_dir($path)) {
                self::delete_directory($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($dir);
    }
}

// Run the uninstaller
Torob_Price_Compare_Uninstaller::uninstall();

// Log uninstall for debugging (optional)
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Torob Price Compare Plugin: Uninstall completed at ' . current_time('mysql'));
}