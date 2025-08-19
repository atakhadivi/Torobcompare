<?php
/**
 * Frontend functionality for Torob Price Compare plugin
 *
 * @package Torob_Price_Compare
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Torob_Frontend {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('woocommerce_single_product_summary', array($this, 'display_price_comparison'), 25);
        add_shortcode('torob_price_compare', array($this, 'price_compare_shortcode'));
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_frontend_scripts() {
        if (!is_product()) {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'torob-frontend',
            TOROB_PRICE_COMPARE_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            TOROB_PRICE_COMPARE_VERSION,
            true
        );
        
        wp_localize_script('torob-frontend', 'torob_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('torob_frontend_nonce'),
            'messages' => array(
                'searching' => 'در حال جستجو در ترب...',
                'error' => 'خطا در جستجو',
                'not_found' => 'محصول در ترب یافت نشد',
                'try_again' => 'تلاش مجدد'
            ),
            'settings' => array(
                'auto_search' => get_option('torob_auto_search', 1),
                'search_delay' => get_option('torob_search_delay', 2) * 1000 // Convert to milliseconds
            )
        ));
        
        wp_enqueue_style(
            'torob-frontend',
            TOROB_PRICE_COMPARE_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            TOROB_PRICE_COMPARE_VERSION
        );
    }
    
    /**
     * Display price comparison on product page
     */
    public function display_price_comparison() {
        global $product;
        
        if (!$product || !get_option('torob_enabled', 1)) {
            return;
        }
        
        $product_id = $product->get_id();
        $product_name = $product->get_name();
        $product_price = $product->get_price();
        
        // Check if we have cached price
        $cache = new Torob_Cache();
        $cached_price = $cache->get_cached_price($product_id);
        
        $display_position = get_option('torob_display_position', 'after_price');
        
        // Only display if position matches current hook
        $current_priority = current_filter() === 'woocommerce_single_product_summary' ? 25 : 0;
        $expected_priority = $this->get_display_priority($display_position);
        
        if ($current_priority !== $expected_priority) {
            return;
        }
        
        echo '<div id="torob-price-compare" class="torob-price-compare" data-product-id="' . esc_attr($product_id) . '" data-product-name="' . esc_attr($product_name) . '" data-auto-search="true">';
        
        if ($cached_price && !empty($cached_price['min_price'])) {
            $this->render_price_comparison($cached_price, $product_price);
        } else {
            // Show loading state immediately and start auto search
            $this->render_loading_state();
        }
        
        echo '</div>';
    }
    
    /**
     * Get display priority based on position setting
     *
     * @param string $position Display position
     * @return int Priority number
     */
    private function get_display_priority($position) {
        switch ($position) {
            case 'before_price':
                return 10;
            case 'after_price':
                return 25;
            case 'after_summary':
                return 35;
            case 'before_add_to_cart':
                return 29;
            default:
                return 25;
        }
    }
    
    /**
     * Render price comparison display
     *
     * @param array $torob_data Torob price data
     * @param float $product_price WooCommerce product price
     */
    private function render_price_comparison($torob_data, $product_price) {
        $price_text = get_option('torob_price_text', 'کمترین قیمت در ترب:');
        $show_comparison = get_option('torob_show_comparison', 1);
        $comparison_text = get_option('torob_comparison_text', 'شما %s تومان صرفه‌جویی می‌کنید!');
        
        $torob_price = $torob_data['min_price'];
        $savings = $product_price - $torob_price;
        
        echo '<div class="torob-price-display">';
        echo '<div class="torob-price-info">';
        echo '<span class="torob-price-label">' . esc_html($price_text) . '</span>';
        echo '<span class="torob-price-amount">' . number_format($torob_price) . ' تومان</span>';
        
        if ($show_comparison && $savings > 0) {
            echo '<div class="torob-savings">';
            echo '<span class="savings-text">' . sprintf(esc_html($comparison_text), number_format($savings)) . '</span>';
            echo '</div>';
        }
        
        echo '</div>';
        
        echo '<div class="torob-actions">';
        echo '<a href="' . esc_url($torob_data['torob_url']) . '" target="_blank" class="torob-link button">';
        echo 'مشاهده در ترب';
        echo '</a>';
        echo '<button type="button" class="torob-refresh button-secondary" data-product-id="' . esc_attr($torob_data['product_id'] ?? '') . '">';
        echo 'بروزرسانی قیمت';
        echo '</button>';
        echo '</div>';
        
        echo '<div class="torob-meta">';
        echo '<small>آخرین بروزرسانی: ' . human_time_diff(strtotime($torob_data['last_updated']), current_time('timestamp')) . ' پیش</small>';
        if (isset($torob_data['found_products'])) {
            echo '<small> • ' . $torob_data['found_products'] . ' فروشگاه</small>';
        }
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Render loading state for auto search
     */
    private function render_loading_state() {
        echo '<div class="torob-search-container">';
        echo '<div class="torob-loading" style="display: block;">';
        echo '<span class="spinner is-active"></span>';
        echo '<span class="loading-text">در حال جستجو قیمت در ترب...</span>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Render search button (fallback)
     */
    private function render_search_button() {
        $button_text = get_option('torob_button_text', 'مقایسه قیمت در ترب');
        
        echo '<div class="torob-search-container">';
        echo '<button type="button" class="torob-search-btn button" id="torob-search-btn">';
        echo esc_html($button_text);
        echo '</button>';
        echo '<div class="torob-loading" style="display: none;">';
        echo '<span class="spinner"></span>';
        echo '<span class="loading-text">در حال جستجو در ترب...</span>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Price compare shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Shortcode output
     */
    public function price_compare_shortcode($atts) {
        $atts = shortcode_atts(array(
            'product_id' => get_the_ID(),
            'show_button' => 'true',
            'auto_search' => 'false'
        ), $atts);
        
        $product_id = intval($atts['product_id']);
        $product = wc_get_product($product_id);
        
        if (!$product || !get_option('torob_enabled', 1)) {
            return '';
        }
        
        $product_name = $product->get_name();
        $product_price = $product->get_price();
        
        // Check if we have cached price
        $cache = new Torob_Cache();
        $cached_price = $cache->get_cached_price($product_id);
        
        ob_start();
        
        echo '<div class="torob-shortcode-wrapper" data-product-id="' . esc_attr($product_id) . '" data-product-name="' . esc_attr($product_name) . '" data-auto-search="' . esc_attr($atts['auto_search']) . '">';
        
        if ($cached_price) {
            $this->render_price_comparison($cached_price, $product_price);
        } elseif ($atts['show_button'] === 'true') {
            $this->render_search_button();
        }
        
        echo '</div>';
        
        return ob_get_clean();
    }
    
    /**
     * Add hooks based on display position
     */
    public function add_display_hooks() {
        $display_position = get_option('torob_display_position', 'after_price');
        
        // Remove existing hooks first
        remove_action('woocommerce_single_product_summary', array($this, 'display_price_comparison'));
        
        switch ($display_position) {
            case 'before_price':
                add_action('woocommerce_single_product_summary', array($this, 'display_price_comparison'), 10);
                break;
            case 'after_price':
                add_action('woocommerce_single_product_summary', array($this, 'display_price_comparison'), 25);
                break;
            case 'after_summary':
                add_action('woocommerce_single_product_summary', array($this, 'display_price_comparison'), 35);
                break;
            case 'before_add_to_cart':
                add_action('woocommerce_single_product_summary', array($this, 'display_price_comparison'), 29);
                break;
            default:
                add_action('woocommerce_single_product_summary', array($this, 'display_price_comparison'), 25);
                break;
        }
    }
    
    /**
     * Get product price comparison data for AJAX
     *
     * @param int $product_id Product ID
     * @return array|false Price comparison data or false
     */
    public function get_price_comparison_data($product_id) {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return false;
        }
        
        $cache = new Torob_Cache();
        $cached_price = $cache->get_cached_price($product_id);
        
        if ($cached_price) {
            return array(
                'has_cache' => true,
                'torob_data' => $cached_price,
                'product_price' => $product->get_price(),
                'html' => $this->get_price_comparison_html($cached_price, $product->get_price())
            );
        }
        
        return array(
            'has_cache' => false,
            'product_price' => $product->get_price()
        );
    }
    
    /**
     * Get price comparison HTML
     *
     * @param array $torob_data Torob price data
     * @param float $product_price WooCommerce product price
     * @return string HTML output
     */
    private function get_price_comparison_html($torob_data, $product_price) {
        ob_start();
        $this->render_price_comparison($torob_data, $product_price);
        return ob_get_clean();
    }
    
    /**
     * Check if current page should show price comparison
     *
     * @return bool
     */
    public function should_show_price_comparison() {
        return is_product() && get_option('torob_enabled', 1);
    }
    
    /**
     * Get formatted price difference
     *
     * @param float $wc_price WooCommerce price
     * @param float $torob_price Torob price
     * @return array Price difference data
     */
    public function get_price_difference($wc_price, $torob_price) {
        $difference = $wc_price - $torob_price;
        $percentage = $wc_price > 0 ? round(($difference / $wc_price) * 100, 1) : 0;
        
        return array(
            'amount' => $difference,
            'percentage' => $percentage,
            'is_cheaper' => $difference > 0,
            'formatted_amount' => number_format(abs($difference)),
            'formatted_percentage' => $percentage . '%'
        );
    }
    
    /**
     * Generate structured data for price comparison
     *
     * @param array $torob_data Torob price data
     * @param int $product_id Product ID
     */
    public function add_structured_data($torob_data, $product_id) {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return;
        }
        
        $structured_data = array(
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->get_name(),
            'offers' => array(
                array(
                    '@type' => 'AggregateOffer',
                    'lowPrice' => $torob_data['min_price'],
                    'priceCurrency' => 'IRR',
                    'offerCount' => $torob_data['found_products'] ?? 1,
                    'url' => $torob_data['torob_url']
                )
            )
        );
        
        echo '<script type="application/ld+json">' . wp_json_encode($structured_data) . '</script>';
    }
}