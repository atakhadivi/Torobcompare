<?php
/**
 * Admin functionality for Torob Price Compare plugin
 *
 * @package Torob_Price_Compare
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Torob_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_torob_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_torob_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_torob_manual_search', array($this, 'ajax_manual_search'));
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        // Main menu page
        add_menu_page(
            'مقایسه قیمت ترب',
            'مقایسه قیمت ترب',
            'manage_options',
            'torob-price-compare',
            array($this, 'admin_page'),
            'dashicons-chart-line',
            30
        );
        
        // Settings submenu
        add_submenu_page(
            'torob-price-compare',
            'تنظیمات',
            'تنظیمات',
            'manage_options',
            'torob-settings',
            array($this, 'settings_page')
        );
        
        // Reports submenu
        add_submenu_page(
            'torob-price-compare',
            'گزارشات',
            'گزارشات',
            'manage_options',
            'torob-reports',
            array($this, 'reports_page')
        );
        
        // Cache management submenu
        add_submenu_page(
            'torob-price-compare',
            'مدیریت کش',
            'مدیریت کش',
            'manage_options',
            'torob-cache',
            array($this, 'cache_page')
        );
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('torob_settings', 'torob_enabled');
        register_setting('torob_settings', 'torob_cache_duration');
        register_setting('torob_settings', 'torob_display_position');
        register_setting('torob_settings', 'torob_button_text');
        register_setting('torob_settings', 'torob_price_text');
        register_setting('torob_settings', 'torob_auto_search');
        register_setting('torob_settings', 'torob_search_delay');
        register_setting('torob_settings', 'torob_show_comparison');
        register_setting('torob_settings', 'torob_comparison_text');
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'torob') === false) {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'torob-admin',
            TOROB_PRICE_COMPARE_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            TOROB_PRICE_COMPARE_VERSION,
            true
        );
        
        wp_localize_script('torob-admin', 'torob_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('torob_admin_nonce'),
            'messages' => array(
                'testing' => 'در حال تست اتصال...',
                'clearing_cache' => 'در حال پاک کردن کش...',
                'searching' => 'در حال جستجو...',
                'success' => 'عملیات موفقیت‌آمیز بود',
                'error' => 'خطا در انجام عملیات'
            )
        ));
        
        wp_enqueue_style(
            'torob-admin',
            TOROB_PRICE_COMPARE_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            TOROB_PRICE_COMPARE_VERSION
        );
    }
    
    /**
     * Main admin page
     */
    public function admin_page() {
        $cache = new Torob_Cache();
        $api = new Torob_API();
        
        $cache_stats = $cache->get_cache_stats();
        $search_stats = $api->get_search_stats(7); // Last 7 days
        
        ?>
        <div class="wrap torob-admin">
            <h1>مقایسه قیمت ترب</h1>
            
            <div class="torob-dashboard">
                <div class="torob-stats-grid">
                    <div class="torob-stat-card">
                        <h3>آمار کش</h3>
                        <div class="stat-number"><?php echo number_format($cache_stats['total_cached']); ?></div>
                        <div class="stat-label">محصولات کش شده</div>
                    </div>
                    
                    <div class="torob-stat-card">
                        <h3>جستجوهای اخیر</h3>
                        <div class="stat-number"><?php echo number_format($search_stats['total_searches']); ?></div>
                        <div class="stat-label">در ۷ روز گذشته</div>
                    </div>
                    
                    <div class="torob-stat-card">
                        <h3>نرخ موفقیت</h3>
                        <div class="stat-number"><?php echo $search_stats['success_rate']; ?>%</div>
                        <div class="stat-label">جستجوهای موفق</div>
                    </div>
                    
                    <div class="torob-stat-card">
                        <h3>زمان پاسخ</h3>
                        <div class="stat-number"><?php echo round($search_stats['avg_response_time']); ?></div>
                        <div class="stat-label">میلی‌ثانیه</div>
                    </div>
                </div>
                
                <div class="torob-quick-actions">
                    <h3>عملیات سریع</h3>
                    <div class="action-buttons">
                        <button type="button" class="button button-primary" id="test-connection">
                            تست اتصال به ترب
                        </button>
                        <button type="button" class="button" id="clear-cache">
                            پاک کردن کش
                        </button>
                        <a href="<?php echo admin_url('admin.php?page=torob-settings'); ?>" class="button">
                            تنظیمات
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=torob-reports'); ?>" class="button">
                            گزارشات تفصیلی
                        </a>
                    </div>
                </div>
                
                <div class="torob-recent-activity">
                    <h3>فعالیت‌های اخیر</h3>
                    <?php
                    $recent_logs = $api->get_recent_logs(10);
                    if (!empty($recent_logs)) {
                        echo '<table class="wp-list-table widefat fixed striped">';
                        echo '<thead><tr><th>محصول</th><th>وضعیت</th><th>زمان</th></tr></thead>';
                        echo '<tbody>';
                        foreach ($recent_logs as $log) {
                            $status = $log['success'] ? '<span class="status-success">موفق</span>' : '<span class="status-failed">ناموفق</span>';
                            $time = human_time_diff(strtotime($log['created_at']), current_time('timestamp')) . ' پیش';
                            echo '<tr>';
                            echo '<td>' . esc_html($log['product_name'] ?: $log['search_query']) . '</td>';
                            echo '<td>' . $status . '</td>';
                            echo '<td>' . $time . '</td>';
                            echo '</tr>';
                        }
                        echo '</tbody></table>';
                    } else {
                        echo '<p>هیچ فعالیتی یافت نشد.</p>';
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        if (isset($_POST['submit'])) {
            // Save settings
            update_option('torob_enabled', isset($_POST['torob_enabled']) ? 1 : 0);
            update_option('torob_cache_duration', intval($_POST['torob_cache_duration']));
            update_option('torob_display_position', sanitize_text_field($_POST['torob_display_position']));
            update_option('torob_button_text', sanitize_text_field($_POST['torob_button_text']));
            update_option('torob_price_text', sanitize_text_field($_POST['torob_price_text']));
            update_option('torob_auto_search', isset($_POST['torob_auto_search']) ? 1 : 0);
            update_option('torob_search_delay', intval($_POST['torob_search_delay']));
            update_option('torob_show_comparison', isset($_POST['torob_show_comparison']) ? 1 : 0);
            update_option('torob_comparison_text', sanitize_text_field($_POST['torob_comparison_text']));
            
            echo '<div class="notice notice-success"><p>تنظیمات ذخیره شد.</p></div>';
        }
        
        // Get current settings
        $enabled = get_option('torob_enabled', 1);
        $cache_duration = get_option('torob_cache_duration', 24);
        $display_position = get_option('torob_display_position', 'after_price');
        $button_text = get_option('torob_button_text', 'مقایسه قیمت در ترب');
        $price_text = get_option('torob_price_text', 'کمترین قیمت در ترب:');
        $auto_search = get_option('torob_auto_search', 1);
        $search_delay = get_option('torob_search_delay', 2);
        $show_comparison = get_option('torob_show_comparison', 1);
        $comparison_text = get_option('torob_comparison_text', 'شما %s تومان صرفه‌جویی می‌کنید!');
        
        ?>
        <div class="wrap torob-admin">
            <h1>تنظیمات مقایسه قیمت ترب</h1>
            
            <form method="post" action="">
                <table class="form-table">
                    <tr>
                        <th scope="row">فعال‌سازی پلاگین</th>
                        <td>
                            <label>
                                <input type="checkbox" name="torob_enabled" value="1" <?php checked($enabled, 1); ?>>
                                پلاگین فعال باشد
                            </label>
                            <p class="description">با غیرفعال کردن این گزینه، پلاگین کار نخواهد کرد.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">مدت زمان کش (ساعت)</th>
                        <td>
                            <input type="number" name="torob_cache_duration" value="<?php echo esc_attr($cache_duration); ?>" min="1" max="168">
                            <p class="description">قیمت‌ها برای چند ساعت کش می‌شوند. (حداکثر ۱۶۸ ساعت = ۱ هفته)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">موقعیت نمایش</th>
                        <td>
                            <select name="torob_display_position">
                                <option value="before_price" <?php selected($display_position, 'before_price'); ?>>قبل از قیمت</option>
                                <option value="after_price" <?php selected($display_position, 'after_price'); ?>>بعد از قیمت</option>
                                <option value="after_summary" <?php selected($display_position, 'after_summary'); ?>>بعد از خلاصه محصول</option>
                                <option value="before_add_to_cart" <?php selected($display_position, 'before_add_to_cart'); ?>>قبل از دکمه افزودن به سبد</option>
                            </select>
                            <p class="description">مکان نمایش مقایسه قیمت در صفحه محصول</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">متن دکمه</th>
                        <td>
                            <input type="text" name="torob_button_text" value="<?php echo esc_attr($button_text); ?>" class="regular-text">
                            <p class="description">متن دکمه مقایسه قیمت</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">متن نمایش قیمت</th>
                        <td>
                            <input type="text" name="torob_price_text" value="<?php echo esc_attr($price_text); ?>" class="regular-text">
                            <p class="description">متن نمایش داده شده قبل از قیمت ترب</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">جستجوی خودکار</th>
                        <td>
                            <label>
                                <input type="checkbox" name="torob_auto_search" value="1" <?php checked($auto_search, 1); ?>>
                                جستجوی خودکار در هنگام بارگذاری صفحه
                            </label>
                            <p class="description">اگر فعال باشد، قیمت‌ها به صورت خودکار جستجو می‌شوند.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">تاخیر جستجو (ثانیه)</th>
                        <td>
                            <input type="number" name="torob_search_delay" value="<?php echo esc_attr($search_delay); ?>" min="0" max="10">
                            <p class="description">تاخیر قبل از شروع جستجوی خودکار (برای بهبود عملکرد)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">نمایش مقایسه صرفه‌جویی</th>
                        <td>
                            <label>
                                <input type="checkbox" name="torob_show_comparison" value="1" <?php checked($show_comparison, 1); ?>>
                                نمایش میزان صرفه‌جویی
                            </label>
                            <p class="description">نمایش میزان صرفه‌جویی در صورت کمتر بودن قیمت ترب</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">متن مقایسه صرفه‌جویی</th>
                        <td>
                            <input type="text" name="torob_comparison_text" value="<?php echo esc_attr($comparison_text); ?>" class="regular-text">
                            <p class="description">متن نمایش صرفه‌جویی. از %s برای نمایش مبلغ استفاده کنید.</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('ذخیره تنظیمات'); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Reports page
     */
    public function reports_page() {
        $api = new Torob_API();
        $cache = new Torob_Cache();
        
        $period = isset($_GET['period']) ? intval($_GET['period']) : 30;
        $search_stats = $api->get_search_stats($period);
        $cache_stats = $cache->get_cache_stats();
        $recent_logs = $api->get_recent_logs(50);
        
        ?>
        <div class="wrap torob-admin">
            <h1>گزارشات مقایسه قیمت ترب</h1>
            
            <div class="torob-reports">
                <div class="period-selector">
                    <label>دوره زمانی:</label>
                    <select onchange="location.href='?page=torob-reports&period='+this.value">
                        <option value="7" <?php selected($period, 7); ?>>۷ روز گذشته</option>
                        <option value="30" <?php selected($period, 30); ?>>۳۰ روز گذشته</option>
                        <option value="90" <?php selected($period, 90); ?>>۹۰ روز گذشته</option>
                    </select>
                </div>
                
                <div class="stats-summary">
                    <h3>خلاصه آمار (<?php echo $period; ?> روز گذشته)</h3>
                    <div class="torob-stats-grid">
                        <div class="torob-stat-card">
                            <h4>کل جستجوها</h4>
                            <div class="stat-number"><?php echo number_format($search_stats['total_searches']); ?></div>
                        </div>
                        <div class="torob-stat-card">
                            <h4>جستجوهای موفق</h4>
                            <div class="stat-number"><?php echo number_format($search_stats['successful_searches']); ?></div>
                        </div>
                        <div class="torob-stat-card">
                            <h4>نرخ موفقیت</h4>
                            <div class="stat-number"><?php echo $search_stats['success_rate']; ?>%</div>
                        </div>
                        <div class="torob-stat-card">
                            <h4>میانگین زمان پاسخ</h4>
                            <div class="stat-number"><?php echo round($search_stats['avg_response_time']); ?> ms</div>
                        </div>
                    </div>
                </div>
                
                <div class="cache-summary">
                    <h3>وضعیت کش</h3>
                    <div class="torob-stats-grid">
                        <div class="torob-stat-card">
                            <h4>محصولات کش شده</h4>
                            <div class="stat-number"><?php echo number_format($cache_stats['total_cached']); ?></div>
                        </div>
                        <div class="torob-stat-card">
                            <h4>کش منقضی شده</h4>
                            <div class="stat-number"><?php echo number_format($cache_stats['expired_cache']); ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="recent-searches">
                    <h3>جستجوهای اخیر</h3>
                    <?php if (!empty($recent_logs)): ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>محصول</th>
                                    <th>جستجو</th>
                                    <th>وضعیت</th>
                                    <th>زمان پاسخ</th>
                                    <th>پیام خطا</th>
                                    <th>تاریخ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_logs as $log): ?>
                                    <tr>
                                        <td><?php echo esc_html($log['product_name'] ?: '-'); ?></td>
                                        <td><?php echo esc_html($log['search_query']); ?></td>
                                        <td>
                                            <?php if ($log['success']): ?>
                                                <span class="status-success">موفق</span>
                                            <?php else: ?>
                                                <span class="status-failed">ناموفق</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $log['response_time'] ? $log['response_time'] . ' ms' : '-'; ?></td>
                                        <td><?php echo esc_html($log['error_message'] ?: '-'); ?></td>
                                        <td><?php echo date_i18n('Y/m/d H:i', strtotime($log['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>هیچ جستجویی یافت نشد.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Cache management page
     */
    public function cache_page() {
        $cache = new Torob_Cache();
        
        if (isset($_POST['clear_cache'])) {
            $cache->clear_all_cache();
            echo '<div class="notice notice-success"><p>کش پاک شد.</p></div>';
        }
        
        if (isset($_POST['cleanup_expired'])) {
            $deleted = $cache->cleanup_expired_cache();
            echo '<div class="notice notice-success"><p>' . sprintf('%d ورودی منقضی شده پاک شد.', $deleted) . '</p></div>';
        }
        
        $cache_stats = $cache->get_cache_stats();
        $recent_cache = $cache->get_recent_cache_entries(20);
        
        ?>
        <div class="wrap torob-admin">
            <h1>مدیریت کش</h1>
            
            <div class="torob-cache-management">
                <div class="cache-stats">
                    <h3>آمار کش</h3>
                    <div class="torob-stats-grid">
                        <div class="torob-stat-card">
                            <h4>کل ورودی‌ها</h4>
                            <div class="stat-number"><?php echo number_format($cache_stats['total_cached']); ?></div>
                        </div>
                        <div class="torob-stat-card">
                            <h4>ورودی‌های منقضی</h4>
                            <div class="stat-number"><?php echo number_format($cache_stats['expired_cache']); ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="cache-actions">
                    <h3>عملیات کش</h3>
                    <form method="post" style="display: inline-block; margin-left: 10px;">
                        <input type="submit" name="clear_cache" value="پاک کردن کل کش" class="button button-secondary" 
                               onclick="return confirm('آیا مطمئن هستید؟')">
                    </form>
                    <form method="post" style="display: inline-block;">
                        <input type="submit" name="cleanup_expired" value="پاک کردن ورودی‌های منقضی" class="button">
                    </form>
                </div>
                
                <div class="recent-cache">
                    <h3>ورودی‌های اخیر کش</h3>
                    <?php if (!empty($recent_cache)): ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>محصول</th>
                                    <th>قیمت ترب</th>
                                    <th>تاریخ کش</th>
                                    <th>انقضا</th>
                                    <th>وضعیت</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_cache as $entry): ?>
                                    <?php
                                    $product = wc_get_product($entry['product_id']);
                                    $product_name = $product ? $product->get_name() : 'محصول حذف شده';
                                    $is_expired = strtotime($entry['expires_at']) < current_time('timestamp');
                                    ?>
                                    <tr>
                                        <td><?php echo esc_html($product_name); ?></td>
                                        <td><?php echo number_format($entry['min_price']); ?> تومان</td>
                                        <td><?php echo date_i18n('Y/m/d H:i', strtotime($entry['cached_at'])); ?></td>
                                        <td><?php echo date_i18n('Y/m/d H:i', strtotime($entry['expires_at'])); ?></td>
                                        <td>
                                            <?php if ($is_expired): ?>
                                                <span class="status-expired">منقضی شده</span>
                                            <?php else: ?>
                                                <span class="status-active">فعال</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>هیچ ورودی کشی یافت نشد.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for testing connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('torob_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز');
        }
        
        $api = new Torob_API();
        $result = $api->test_connection();
        
        wp_send_json($result);
    }
    
    /**
     * AJAX handler for clearing cache
     */
    public function ajax_clear_cache() {
        check_ajax_referer('torob_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز');
        }
        
        $cache = new Torob_Cache();
        $result = $cache->clear_all_cache();
        
        wp_send_json(array(
            'success' => $result,
            'message' => $result ? 'کش با موفقیت پاک شد' : 'خطا در پاک کردن کش'
        ));
    }
    
    /**
     * AJAX handler for manual search
     */
    public function ajax_manual_search() {
        check_ajax_referer('torob_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز');
        }
        
        $product_id = intval($_POST['product_id']);
        $product = wc_get_product($product_id);
        
        if (!$product) {
            wp_send_json_error('محصول یافت نشد');
        }
        
        $api = new Torob_API();
        $result = $api->search_product($product_id, $product->get_name());
        
        if ($result) {
            wp_send_json_success(array(
                'message' => 'جستجو موفقیت‌آمیز بود',
                'data' => $result
            ));
        } else {
            wp_send_json_error('جستجو ناموفق بود');
        }
    }
}