<?php
/**
 * Test script for Torob web scraping functionality
 * 
 * This script tests the updated Torob_API class to ensure
 * web scraping works correctly.
 */

// Simulate WordPress environment for testing
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}

// Include required WordPress functions (simplified for testing)
if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = array()) {
        // Simple cURL implementation for testing
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, isset($args['timeout']) ? $args['timeout'] : 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        if (isset($args['user-agent'])) {
            curl_setopt($ch, CURLOPT_USERAGENT, $args['user-agent']);
        }
        
        if (isset($args['headers']) && is_array($args['headers'])) {
            $headers = array();
            foreach ($args['headers'] as $key => $value) {
                $headers[] = $key . ': ' . $value;
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return new WP_Error('http_request_failed', $error);
        }
        
        return array(
            'response' => array('code' => $http_code),
            'body' => $response
        );
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        return isset($response['response']['code']) ? $response['response']['code'] : 0;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        return isset($response['body']) ? $response['body'] : '';
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('current_time')) {
    function current_time($type) {
        return date('Y-m-d H:i:s');
    }
}

// Simple WP_Error class for testing
class WP_Error {
    private $errors = array();
    
    public function __construct($code, $message) {
        $this->errors[$code] = array($message);
    }
    
    public function get_error_message() {
        foreach ($this->errors as $code => $messages) {
            return $messages[0];
        }
        return '';
    }
}

// Mock database for testing
class MockWPDB {
    public $prefix = 'wp_';
    
    public function prepare($query, ...$args) {
        // Simple prepare implementation for testing
        $prepared = $query;
        foreach ($args as $arg) {
            if (is_string($arg)) {
                $prepared = preg_replace('/%s/', "'" . addslashes($arg) . "'", $prepared, 1);
            } else {
                $prepared = preg_replace('/%d/', intval($arg), $prepared, 1);
            }
        }
        return $prepared;
    }
    
    public function get_var($query) {
        // Return 0 for rate limiting checks
        return 0;
    }
    
    public function insert($table, $data) {
        echo "Mock DB Insert into $table: " . print_r($data, true) . "\n";
        return true;
    }
}

// Set up global $wpdb
$wpdb = new MockWPDB();

// Include the Torob API class
require_once dirname(__FILE__) . '/includes/class-torob-api.php';

// Test the web scraping functionality
echo "=== Testing Torob Web Scraping Functionality ===\n\n";

// Create Torob API instance
$torob_api = new Torob_API();

// Test products
$test_products = array(
    array('id' => 1, 'name' => 'iPhone 15'),
    array('id' => 2, 'name' => 'Samsung Galaxy S24'),
    array('id' => 3, 'name' => 'لپ تاپ ایسوس')
);

foreach ($test_products as $product) {
    echo "Testing product: {$product['name']} (ID: {$product['id']})\n";
    echo str_repeat('-', 50) . "\n";
    
    $start_time = microtime(true);
    $result = $torob_api->search_product($product['id'], $product['name']);
    $end_time = microtime(true);
    
    $execution_time = round(($end_time - $start_time) * 1000, 2);
    
    if ($result) {
        echo "✓ Success! Found price data:\n";
        echo "  Min Price: " . (isset($result['min_price']) ? number_format($result['min_price']) . " تومان" : 'N/A') . "\n";
        echo "  Max Price: " . (isset($result['max_price']) ? number_format($result['max_price']) . " تومان" : 'N/A') . "\n";
        echo "  Product Count: " . (isset($result['product_count']) ? $result['product_count'] : 'N/A') . "\n";
        echo "  Torob URL: " . (isset($result['torob_url']) ? $result['torob_url'] : 'N/A') . "\n";
        echo "  Last Updated: " . (isset($result['last_updated']) ? $result['last_updated'] : 'N/A') . "\n";
    } else {
        echo "✗ Failed to get price data\n";
    }
    
    echo "  Execution Time: {$execution_time}ms\n";
    echo "\n";
    
    // Add delay between requests to be respectful
    sleep(2);
}

// Test connection
echo "=== Testing Connection ===\n";
$connection_test = $torob_api->test_connection();
if ($connection_test) {
    echo "✓ Connection test successful\n";
} else {
    echo "✗ Connection test failed\n";
}

echo "\n=== Test Complete ===\n";
?>