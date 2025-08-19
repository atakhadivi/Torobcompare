/**
 * Admin JavaScript for Torob Price Compare plugin
 */

(function($) {
    'use strict';
    
    // Admin object
    var TorobAdmin = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initCharts();
            this.loadDashboardData();
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            // Test connection button
            $(document).on('click', '#torob-test-connection', this.testConnection);
            
            // Clear cache button
            $(document).on('click', '#torob-clear-cache', this.clearCache);
            
            // Manual search button
            $(document).on('click', '#torob-manual-search', this.manualSearch);
            
            // Bulk search button
            $(document).on('click', '#torob-bulk-search', this.bulkSearch);
            
            // Export data button
            $(document).on('click', '.torob-export-btn', this.exportData);
            
            // Settings form submit
            $(document).on('submit', '#torob-settings-form', this.saveSettings);
            
            // Tab navigation
            $(document).on('click', '.nav-tab', this.switchTab);
            
            // Product selection for bulk operations
            $(document).on('change', '#select-all-products', this.toggleAllProducts);
            $(document).on('change', '.product-checkbox', this.updateBulkActions);
            
            // Refresh stats
            $(document).on('click', '#refresh-stats', this.refreshStats);
            
            // Auto-refresh dashboard every 30 seconds
            if ($('#torob-dashboard').length) {
                setInterval(this.refreshDashboardStats, 30000);
            }
        },
        
        /**
         * Test API connection
         */
        testConnection: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $result = $('#connection-result');
            
            // Show loading
            TorobAdmin.setButtonLoading($button, true);
            $result.html('<div class="notice notice-info"><p>در حال تست اتصال...</p></div>');
            
            $.ajax({
                url: torob_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'torob_test_connection',
                    nonce: torob_admin_vars.nonce
                },
                success: function(response) {
                    TorobAdmin.setButtonLoading($button, false);
                    
                    if (response.success) {
                        $result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                    } else {
                        $result.html('<div class="notice notice-error"><p>' + (response.data || 'خطا در تست اتصال') + '</p></div>');
                    }
                },
                error: function() {
                    TorobAdmin.setButtonLoading($button, false);
                    $result.html('<div class="notice notice-error"><p>خطا در ارتباط با سرور</p></div>');
                }
            });
        },
        
        /**
         * Clear cache
         */
        clearCache: function(e) {
            e.preventDefault();
            
            if (!confirm('آیا مطمئن هستید که می‌خواهید تمام کش را پاک کنید؟')) {
                return;
            }
            
            var $button = $(this);
            var $result = $('#cache-result');
            
            TorobAdmin.setButtonLoading($button, true);
            $result.html('<div class="notice notice-info"><p>در حال پاک کردن کش...</p></div>');
            
            $.ajax({
                url: torob_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'torob_clear_cache',
                    nonce: torob_admin_vars.nonce
                },
                success: function(response) {
                    TorobAdmin.setButtonLoading($button, false);
                    
                    if (response.success) {
                        $result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                        // Refresh cache stats
                        TorobAdmin.refreshCacheStats();
                    } else {
                        $result.html('<div class="notice notice-error"><p>' + (response.data || 'خطا در پاک کردن کش') + '</p></div>');
                    }
                },
                error: function() {
                    TorobAdmin.setButtonLoading($button, false);
                    $result.html('<div class="notice notice-error"><p>خطا در ارتباط با سرور</p></div>');
                }
            });
        },
        
        /**
         * Manual search for a product
         */
        manualSearch: function(e) {
            e.preventDefault();
            
            var productId = $('#manual-search-product').val();
            if (!productId) {
                alert('لطفاً یک محصول انتخاب کنید');
                return;
            }
            
            var $button = $(this);
            var $result = $('#search-result');
            
            TorobAdmin.setButtonLoading($button, true);
            $result.html('<div class="notice notice-info"><p>در حال جستجو...</p></div>');
            
            $.ajax({
                url: torob_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'torob_manual_search',
                    product_id: productId,
                    nonce: torob_admin_vars.nonce
                },
                success: function(response) {
                    TorobAdmin.setButtonLoading($button, false);
                    
                    if (response.success) {
                        var data = response.data.data;
                        var resultHtml = '<div class="notice notice-success">';
                        resultHtml += '<p><strong>جستجو موفقیت‌آمیز بود!</strong></p>';
                        resultHtml += '<p>کمترین قیمت: ' + TorobAdmin.formatPrice(data.min_price) + ' تومان</p>';
                        if (data.torob_url) {
                            resultHtml += '<p><a href="' + data.torob_url + '" target="_blank">مشاهده در ترب</a></p>';
                        }
                        resultHtml += '</div>';
                        $result.html(resultHtml);
                    } else {
                        $result.html('<div class="notice notice-error"><p>' + (response.data || 'محصول در ترب یافت نشد') + '</p></div>');
                    }
                },
                error: function() {
                    TorobAdmin.setButtonLoading($button, false);
                    $result.html('<div class="notice notice-error"><p>خطا در ارتباط با سرور</p></div>');
                }
            });
        },
        
        /**
         * Bulk search for selected products
         */
        bulkSearch: function(e) {
            e.preventDefault();
            
            var selectedProducts = $('.product-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
            
            if (selectedProducts.length === 0) {
                alert('لطفاً حداقل یک محصول انتخاب کنید');
                return;
            }
            
            if (!confirm('آیا مطمئن هستید که می‌خواهید برای ' + selectedProducts.length + ' محصول جستجو انجام دهید؟')) {
                return;
            }
            
            var $button = $(this);
            var $result = $('#bulk-search-result');
            var $progress = $('#bulk-search-progress');
            
            TorobAdmin.setButtonLoading($button, true);
            $progress.show().find('.progress-bar').css('width', '0%');
            $result.html('<div class="notice notice-info"><p>در حال انجام جستجوی گروهی...</p></div>');
            
            $.ajax({
                url: torob_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'torob_bulk_search',
                    product_ids: selectedProducts,
                    nonce: torob_admin_vars.nonce
                },
                success: function(response) {
                    TorobAdmin.setButtonLoading($button, false);
                    $progress.hide();
                    
                    if (response.success) {
                        var summary = response.data.summary;
                        var resultHtml = '<div class="notice notice-success">';
                        resultHtml += '<p><strong>' + response.data.message + '</strong></p>';
                        resultHtml += '<p>کل: ' + summary.total + ' | موفق: ' + summary.success + ' | ناموفق: ' + summary.error + '</p>';
                        resultHtml += '</div>';
                        
                        // Show detailed results
                        if (response.data.results && response.data.results.length > 0) {
                            resultHtml += '<div class="bulk-search-details">';
                            resultHtml += '<h4>جزئیات نتایج:</h4>';
                            resultHtml += '<table class="wp-list-table widefat fixed striped">';
                            resultHtml += '<thead><tr><th>محصول</th><th>وضعیت</th><th>قیمت ترب</th></tr></thead><tbody>';
                            
                            response.data.results.forEach(function(result) {
                                resultHtml += '<tr>';
                                resultHtml += '<td>' + result.product_name + '</td>';
                                resultHtml += '<td>' + (result.status === 'success' ? '<span class="dashicons dashicons-yes-alt" style="color: green;"></span> موفق' : '<span class="dashicons dashicons-dismiss" style="color: red;"></span> ناموفق') + '</td>';
                                resultHtml += '<td>' + (result.torob_price ? TorobAdmin.formatPrice(result.torob_price) + ' تومان' : '-') + '</td>';
                                resultHtml += '</tr>';
                            });
                            
                            resultHtml += '</tbody></table></div>';
                        }
                        
                        $result.html(resultHtml);
                        
                        // Refresh stats
                        TorobAdmin.refreshStats();
                    } else {
                        $result.html('<div class="notice notice-error"><p>' + (response.data || 'خطا در جستجوی گروهی') + '</p></div>');
                    }
                },
                error: function() {
                    TorobAdmin.setButtonLoading($button, false);
                    $progress.hide();
                    $result.html('<div class="notice notice-error"><p>خطا در ارتباط با سرور</p></div>');
                }
            });
        },
        
        /**
         * Export data
         */
        exportData: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var exportType = $button.data('export-type');
            var format = $('#export-format').val() || 'csv';
            
            TorobAdmin.setButtonLoading($button, true);
            
            $.ajax({
                url: torob_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'torob_export_data',
                    export_type: exportType,
                    format: format,
                    nonce: torob_admin_vars.nonce
                },
                success: function(response) {
                    TorobAdmin.setButtonLoading($button, false);
                    
                    if (response.success) {
                        // Create download link
                        var blob = new Blob([response.data.data], {
                            type: format === 'json' ? 'application/json' : 'text/csv'
                        });
                        var url = window.URL.createObjectURL(blob);
                        var a = document.createElement('a');
                        a.href = url;
                        a.download = response.data.filename;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        window.URL.revokeObjectURL(url);
                        
                        TorobAdmin.showNotice('فایل با موفقیت دانلود شد', 'success');
                    } else {
                        TorobAdmin.showNotice(response.data || 'خطا در صادرات داده‌ها', 'error');
                    }
                },
                error: function() {
                    TorobAdmin.setButtonLoading($button, false);
                    TorobAdmin.showNotice('خطا در ارتباط با سرور', 'error');
                }
            });
        },
        
        /**
         * Save settings
         */
        saveSettings: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $submitBtn = $form.find('input[type="submit"]');
            
            TorobAdmin.setButtonLoading($submitBtn, true);
            
            // Let WordPress handle the form submission
            // This is just for UI feedback
            setTimeout(function() {
                TorobAdmin.setButtonLoading($submitBtn, false);
            }, 1000);
        },
        
        /**
         * Switch tabs
         */
        switchTab: function(e) {
            e.preventDefault();
            
            var $tab = $(this);
            var target = $tab.attr('href');
            
            // Update active tab
            $('.nav-tab').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');
            
            // Show target content
            $('.tab-content').hide();
            $(target).show();
            
            // Update URL hash
            window.location.hash = target;
        },
        
        /**
         * Toggle all products selection
         */
        toggleAllProducts: function() {
            var checked = $(this).prop('checked');
            $('.product-checkbox').prop('checked', checked);
            TorobAdmin.updateBulkActions();
        },
        
        /**
         * Update bulk actions based on selection
         */
        updateBulkActions: function() {
            var selectedCount = $('.product-checkbox:checked').length;
            var $bulkBtn = $('#torob-bulk-search');
            
            if (selectedCount > 0) {
                $bulkBtn.prop('disabled', false).text('جستجو برای ' + selectedCount + ' محصول');
            } else {
                $bulkBtn.prop('disabled', true).text('جستجوی گروهی');
            }
        },
        
        /**
         * Refresh stats
         */
        refreshStats: function(e) {
            if (e) e.preventDefault();
            
            var $button = $('#refresh-stats');
            TorobAdmin.setButtonLoading($button, true);
            
            // Reload the page to refresh stats
            window.location.reload();
        },
        
        /**
         * Refresh dashboard stats (auto)
         */
        refreshDashboardStats: function() {
            $.ajax({
                url: torob_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'torob_get_dashboard_stats',
                    nonce: torob_admin_vars.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        // Update stats cards
                        TorobAdmin.updateStatsCards(response.data);
                    }
                }
            });
        },
        
        /**
         * Refresh cache stats
         */
        refreshCacheStats: function() {
            $.ajax({
                url: torob_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'torob_get_cache_stats',
                    nonce: torob_admin_vars.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        // Update cache stats
                        $('#cache-total').text(response.data.total_cached);
                        $('#cache-expired').text(response.data.expired_cache);
                    }
                }
            });
        },
        
        /**
         * Update stats cards
         */
        updateStatsCards: function(stats) {
            if (stats.search_stats) {
                $('#total-searches').text(stats.search_stats.total_searches || 0);
                $('#successful-searches').text(stats.search_stats.successful_searches || 0);
                $('#success-rate').text((stats.search_stats.success_rate || 0).toFixed(1) + '%');
            }
            
            if (stats.cache_stats) {
                $('#cache-total').text(stats.cache_stats.total_cached || 0);
                $('#cache-expired').text(stats.cache_stats.expired_cache || 0);
            }
        },
        
        /**
         * Load dashboard data
         */
        loadDashboardData: function() {
            if (!$('#torob-dashboard').length) return;
            
            // Load recent activities
            this.loadRecentActivities();
        },
        
        /**
         * Load recent activities
         */
        loadRecentActivities: function() {
            $.ajax({
                url: torob_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'torob_get_recent_activities',
                    nonce: torob_admin_vars.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        TorobAdmin.displayRecentActivities(response.data);
                    }
                }
            });
        },
        
        /**
         * Display recent activities
         */
        displayRecentActivities: function(activities) {
            var $container = $('#recent-activities');
            if (!$container.length) return;
            
            var html = '<ul class="activity-list">';
            
            if (activities.length === 0) {
                html += '<li>هیچ فعالیت اخیری یافت نشد</li>';
            } else {
                activities.forEach(function(activity) {
                    html += '<li>';
                    html += '<span class="activity-time">' + activity.time + '</span>';
                    html += '<span class="activity-text">' + activity.text + '</span>';
                    html += '</li>';
                });
            }
            
            html += '</ul>';
            $container.html(html);
        },
        
        /**
         * Initialize charts
         */
        initCharts: function() {
            // Initialize Chart.js charts if available
            if (typeof Chart !== 'undefined') {
                this.initSearchChart();
                this.initCacheChart();
            }
        },
        
        /**
         * Initialize search statistics chart
         */
        initSearchChart: function() {
            var ctx = document.getElementById('searchChart');
            if (!ctx) return;
            
            // Sample data - replace with real data from server
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['۱ هفته پیش', '۶ روز پیش', '۵ روز پیش', '۴ روز پیش', '۳ روز پیش', '۲ روز پیش', 'دیروز'],
                    datasets: [{
                        label: 'جستجوهای موفق',
                        data: [12, 19, 3, 5, 2, 3, 7],
                        borderColor: 'rgb(75, 192, 192)',
                        tension: 0.1
                    }, {
                        label: 'جستجوهای ناموفق',
                        data: [2, 3, 1, 1, 0, 1, 2],
                        borderColor: 'rgb(255, 99, 132)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'آمار جستجوهای هفته گذشته'
                        }
                    }
                }
            });
        },
        
        /**
         * Initialize cache statistics chart
         */
        initCacheChart: function() {
            var ctx = document.getElementById('cacheChart');
            if (!ctx) return;
            
            // Sample data - replace with real data from server
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['کش معتبر', 'کش منقضی'],
                    datasets: [{
                        data: [75, 25],
                        backgroundColor: [
                            'rgb(54, 162, 235)',
                            'rgb(255, 205, 86)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'وضعیت کش'
                        }
                    }
                }
            });
        },
        
        /**
         * Set button loading state
         */
        setButtonLoading: function($button, loading) {
            if (loading) {
                $button.prop('disabled', true)
                       .addClass('updating-message')
                       .val($button.data('loading-text') || 'در حال پردازش...');
            } else {
                $button.prop('disabled', false)
                       .removeClass('updating-message')
                       .val($button.data('original-text') || $button.val());
            }
        },
        
        /**
         * Show admin notice
         */
        showNotice: function(message, type) {
            var noticeHtml = '<div class="notice notice-' + type + ' is-dismissible">';
            noticeHtml += '<p>' + message + '</p>';
            noticeHtml += '<button type="button" class="notice-dismiss"><span class="screen-reader-text">بستن این اعلان</span></button>';
            noticeHtml += '</div>';
            
            $('.wrap h1').after(noticeHtml);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $('.notice').fadeOut();
            }, 5000);
        },
        
        /**
         * Format price with thousands separator
         */
        formatPrice: function(price) {
            return parseInt(price).toLocaleString('fa-IR');
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        TorobAdmin.init();
        
        // Handle tab switching from URL hash
        if (window.location.hash) {
            $('.nav-tab[href="' + window.location.hash + '"]').trigger('click');
        }
    });
    
    // Expose to global scope
    window.TorobAdmin = TorobAdmin;
    
})(jQuery);