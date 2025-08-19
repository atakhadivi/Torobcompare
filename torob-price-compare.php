<?php
/**
 * Plugin Name: Torob Price Compare for WooCommerce
 * Plugin URI: https://github.com/atakhadivi/torobcompare
 * Description: پلاگین مقایسه قیمت ترب برای ووکامرس که به طور خودکار محصولات فروشگاه را در وبسایت ترب جستجو کرده و کمترین قیمت موجود را در صفحه محصول نمایش می‌دهد.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://github.com/atakhadivi
 * Text Domain: torob-price-compare
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.4
 * Requires PHP: 8.0
 * WC requires at least: 7.0
 * WC tested up to: 8.5
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo __('پلاگین مقایسه قیمت ترب نیاز به فعال بودن ووکامرس دارد.', 'torob-price-compare');
        echo '</p></div>';
    });
    return;
}

// Define plugin constants
define('TOROB_PRICE_COMPARE_VERSION', '1.0.0');
define('TOROB_PRICE_COMPARE_PLUGIN_FILE', __FILE__);
define('TOROB_PRICE_COMPARE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TOROB_PRICE_COMPARE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TOROB_PRICE_COMPARE_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include required files
require_once TOROB_PRICE_COMPARE_PLUGIN_DIR . 'includes/class-torob-price-compare.php';
require_once TOROB_PRICE_COMPARE_PLUGIN_DIR . 'includes/class-torob-api.php';
require_once TOROB_PRICE_COMPARE_PLUGIN_DIR . 'includes/class-torob-cache.php';
require_once TOROB_PRICE_COMPARE_PLUGIN_DIR . 'includes/class-torob-admin.php';
require_once TOROB_PRICE_COMPARE_PLUGIN_DIR . 'includes/class-torob-frontend.php';
require_once TOROB_PRICE_COMPARE_PLUGIN_DIR . 'includes/class-torob-ajax.php';

// Initialize the plugin
function torob_price_compare_init() {
    new Torob_Price_Compare();
}
add_action('plugins_loaded', 'torob_price_compare_init');

// Activation hook
register_activation_hook(__FILE__, array('Torob_Price_Compare', 'activate'));

// Deactivation hook
register_deactivation_hook(__FILE__, array('Torob_Price_Compare', 'deactivate'));

// Uninstall hook
register_uninstall_hook(__FILE__, array('Torob_Price_Compare', 'uninstall'));

// Add settings link to plugins page
add_filter('plugin_action_links_' . TOROB_PRICE_COMPARE_PLUGIN_BASENAME, function($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=torob-price-compare') . '">' . __('تنظیمات', 'torob-price-compare') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});

// Load text domain for translations
add_action('init', function() {
    load_plugin_textdomain('torob-price-compare', false, dirname(TOROB_PRICE_COMPARE_PLUGIN_BASENAME) . '/languages');
});

// Check for plugin updates
add_action('admin_init', function() {
    $current_version = get_option('torob_price_compare_version', '0.0.0');
    if (version_compare($current_version, TOROB_PRICE_COMPARE_VERSION, '<')) {
        // Run update procedures if needed
        update_option('torob_price_compare_version', TOROB_PRICE_COMPARE_VERSION);
    }
});

// Add custom CSS for RTL support
add_action('wp_head', function() {
    if (is_rtl()) {
        echo '<style type="text/css">
        .torob-price-compare {
            direction: rtl;
            text-align: right;
        }
        .torob-price-compare .torob-price {
            font-family: "IRANSans", "Tahoma", sans-serif;
        }
        </style>';
    }
});

// Add admin CSS
add_action('admin_head', function() {
    echo '<style type="text/css">
    .torob-admin-page {
        direction: rtl;
        text-align: right;
        font-family: "IRANSans", "Tahoma", sans-serif;
    }
    .torob-admin-page .form-table th {
        text-align: right;
    }
    </style>';
});