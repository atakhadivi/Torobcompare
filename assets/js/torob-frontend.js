/**
 * Frontend JavaScript for Torob Price Compare plugin
 */

(function($) {
    'use strict';
    
    // Plugin object
    var TorobFrontend = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.autoLoadPrices();
            this.autoSearchOnLoad();
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            // Search button click
            $(document).on('click', '.torob-search-btn', this.searchPrice);
            
            // Refresh button click
            $(document).on('click', '.torob-refresh-btn', this.refreshPrice);
            
            // Auto-refresh on page load if enabled
            if (torob_frontend_vars.auto_search && $('.torob-price-comparison').length) {
                this.autoSearchPrice();
            }
        },
        
        /**
         * Search for price
         */
        searchPrice: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $container = $button.closest('.torob-price-comparison');
            var productId = $button.data('product-id');
            var productName = $button.data('product-name');
            
            if (!productId || !productName) {
                TorobFrontend.showMessage($container, 'خطا: اطلاعات محصول یافت نشد', 'error');
                return;
            }
            
            // Show loading state
            TorobFrontend.setLoadingState($button, true);
            
            // AJAX request
            $.ajax({
                url: torob_frontend_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'torob_search_price',
                    product_id: productId,
                    product_name: productName,
                    nonce: torob_frontend_vars.nonce
                },
                success: function(response) {
                    TorobFrontend.setLoadingState($button, false);
                    
                    if (response.success) {
                        // Update the container with new HTML
                        if (response.data.html) {
                            $container.html(response.data.html);
                        } else {
                            TorobFrontend.displayPriceData($container, response.data);
                        }
                        
                        // Show success message
                        TorobFrontend.showMessage($container, 'قیمت با موفقیت یافت شد!', 'success');
                        
                        // Track event
                        TorobFrontend.trackEvent('price_search_success', {
                            product_id: productId,
                            torob_price: response.data.data.min_price,
                            savings: response.data.savings
                        });
                    } else {
                        TorobFrontend.showMessage($container, response.data || 'خطا در جستجو', 'error');
                        
                        // Track error
                        TorobFrontend.trackEvent('price_search_error', {
                            product_id: productId,
                            error: response.data
                        });
                    }
                },
                error: function(xhr, status, error) {
                    TorobFrontend.setLoadingState($button, false);
                    TorobFrontend.showMessage($container, 'خطا در ارتباط با سرور', 'error');
                    
                    console.error('Torob AJAX Error:', error);
                }
            });
        },
        
        /**
         * Refresh cached price
         */
        refreshPrice: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $container = $button.closest('.torob-price-comparison');
            var productId = $button.data('product-id');
            
            if (!productId) {
                TorobFrontend.showMessage($container, 'خطا: شناسه محصول یافت نشد', 'error');
                return;
            }
            
            // Show loading state
            TorobFrontend.setLoadingState($button, true);
            
            // AJAX request
            $.ajax({
                url: torob_frontend_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'torob_refresh_price',
                    product_id: productId,
                    nonce: torob_frontend_vars.nonce
                },
                success: function(response) {
                    TorobFrontend.setLoadingState($button, false);
                    
                    if (response.success) {
                        // Update the container with new HTML
                        if (response.data.html) {
                            $container.html(response.data.html);
                        } else {
                            TorobFrontend.displayPriceData($container, response.data);
                        }
                        
                        // Show success message
                        TorobFrontend.showMessage($container, 'قیمت بروزرسانی شد!', 'success');
                        
                        // Track event
                        TorobFrontend.trackEvent('price_refresh_success', {
                            product_id: productId,
                            torob_price: response.data.data.min_price
                        });
                    } else {
                        TorobFrontend.showMessage($container, response.data || 'خطا در بروزرسانی', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    TorobFrontend.setLoadingState($button, false);
                    TorobFrontend.showMessage($container, 'خطا در ارتباط با سرور', 'error');
                    
                    console.error('Torob AJAX Error:', error);
                }
            });
        },
        
        /**
         * Auto search on page load for containers with auto-search enabled
         */
        autoSearchOnLoad: function() {
            var self = this;
            
            $('.torob-price-compare[data-auto-search="true"]').each(function() {
                var $container = $(this);
                var productId = $container.data('product-id');
                var productName = $container.data('product-name');
                
                if (productId && productName && !$container.find('.torob-price-display').length) {
                    // Start auto search after a short delay
                    setTimeout(function() {
                        self.performAutoSearch($container, productId, productName);
                    }, 500);
                }
            });
        },
        
        /**
         * Perform automatic search
         */
        performAutoSearch: function($container, productId, productName) {
            var self = this;
            
            if (!productId || !productName) {
                this.showAutoSearchError($container, 'اطلاعات محصول یافت نشد', 'missing_data');
                return;
            }
            
            // AJAX request for auto search
            $.ajax({
                url: torob_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'torob_search_price',
                    product_id: productId,
                    product_name: productName,
                    nonce: torob_ajax.nonce
                },
                timeout: 30000, // 30 second timeout
                success: function(response) {
                    if (response.success && response.data) {
                        // Replace loading with price display
                        self.displayAutoSearchResult($container, response.data);
                        
                        // Track successful auto search
                        self.trackEvent('auto_search_success', {
                            product_id: productId,
                            torob_price: response.data.min_price
                        });
                    } else {
                        // Show error state
                        var errorMessage = response.data || 'محصول در ترب یافت نشد';
                        self.showAutoSearchError($container, errorMessage, 'search_failed');
                        self.trackEvent('auto_search_error', {
                            product_id: productId,
                            error: errorMessage
                        });
                    }
                },
                error: function(xhr, status, error) {
                    var errorMessage = 'خطا در ارتباط با سرور';
                    var errorType = 'network_error';
                    
                    if (status === 'timeout') {
                        errorMessage = 'زمان انتظار تمام شد. لطفاً دوباره تلاش کنید';
                        errorType = 'timeout';
                    } else if (xhr.status === 0) {
                        errorMessage = 'عدم دسترسی به اینترنت';
                        errorType = 'no_internet';
                    } else if (xhr.status >= 500) {
                        errorMessage = 'خطای سرور. لطفاً بعداً تلاش کنید';
                        errorType = 'server_error';
                    }
                    
                    // Show error state
                    self.showAutoSearchError($container, errorMessage, errorType);
                    self.trackEvent('auto_search_error', {
                        product_id: productId,
                        error: errorMessage,
                        error_type: errorType,
                        status_code: xhr.status
                    });
                    console.error('Torob Auto Search Error:', error);
                }
            });
        },
        
        /**
         * Display auto search result
         */
        displayAutoSearchResult: function($container, data) {
            var torobPrice = parseInt(data.min_price);
            var productPrice = parseFloat($container.closest('.product').find('.price .amount').text().replace(/[^0-9]/g, '')) || 0;
            var savings = productPrice > torobPrice ? productPrice - torobPrice : 0;
            
            var priceHtml = '<div class="torob-price-display">';
            priceHtml += '<div class="torob-price-info">';
            priceHtml += '<span class="torob-price-label">کمترین قیمت در ترب:</span>';
            priceHtml += '<span class="torob-price-amount">' + this.formatPrice(torobPrice) + ' تومان</span>';
            
            if (savings > 0) {
                priceHtml += '<div class="torob-savings">';
                priceHtml += '<span class="savings-text">شما ' + this.formatPrice(savings) + ' تومان صرفه‌جویی می‌کنید!</span>';
                priceHtml += '</div>';
            }
            
            priceHtml += '</div>';
            
            if (data.torob_url) {
                priceHtml += '<div class="torob-actions">';
                priceHtml += '<a href="' + data.torob_url + '" target="_blank" class="torob-link button">مشاهده در ترب</a>';
                priceHtml += '<button type="button" class="torob-refresh button-secondary" data-product-id="' + data.product_id + '">بروزرسانی قیمت</button>';
                priceHtml += '</div>';
            }
            
            priceHtml += '<div class="torob-meta">';
            priceHtml += '<small>بروزرسانی شده در ' + new Date().toLocaleString('fa-IR') + '</small>';
            if (data.found_products) {
                priceHtml += '<small> • ' + data.found_products + ' فروشگاه</small>';
            }
            priceHtml += '</div>';
            
            priceHtml += '</div>';
            
            // Replace loading with price display
            $container.html(priceHtml);
        },
        
        /**
         * Show auto search error
         */
        showAutoSearchError: function($container, errorMessage, errorType) {
            var errorHtml = '<div class="torob-error torob-error-' + (errorType || 'general') + '">';
            errorHtml += '<div class="torob-error-message">';
            
            // Different icons for different error types
            var errorIcon = '⚠️';
            if (errorType === 'no_internet') {
                errorIcon = '🌐';
            } else if (errorType === 'timeout') {
                errorIcon = '⏱️';
            } else if (errorType === 'server_error') {
                errorIcon = '🔧';
            }
            
            errorHtml += '<span class="error-icon">' + errorIcon + '</span>';
            errorHtml += '<span class="error-text">' + errorMessage + '</span>';
            errorHtml += '</div>';
            
            // Different retry options based on error type
            if (errorType === 'no_internet') {
                errorHtml += '<div class="torob-error-actions">';
                errorHtml += '<button type="button" class="torob-retry-btn button-secondary" onclick="location.reload()">بررسی اتصال</button>';
                errorHtml += '</div>';
            } else if (errorType === 'missing_data') {
                errorHtml += '<div class="torob-error-actions">';
                errorHtml += '<small class="error-help">لطفاً صفحه را بازخوانی کنید</small>';
                errorHtml += '</div>';
            } else {
                errorHtml += '<div class="torob-error-actions">';
                errorHtml += '<button type="button" class="torob-retry-btn button-secondary" data-product-id="' + $container.data('product-id') + '" data-product-name="' + $container.data('product-name') + '">تلاش مجدد</button>';
                errorHtml += '<button type="button" class="torob-manual-search-btn button-link" data-product-id="' + $container.data('product-id') + '" data-product-name="' + $container.data('product-name') + '">جستجوی دستی</button>';
                errorHtml += '</div>';
            }
            
            errorHtml += '</div>';
            
            // Replace loading with error display
            $container.html(errorHtml);
            
            // Bind retry button
            $container.find('.torob-retry-btn').on('click', function() {
                var $btn = $(this);
                var productId = $btn.data('product-id');
                var productName = $btn.data('product-name');
                
                if (productId && productName) {
                    // Show loading state again
                    TorobFrontend.showLoadingState($container);
                    // Retry search
                    setTimeout(function() {
                        TorobFrontend.performAutoSearch($container, productId, productName);
                    }, 500);
                } else {
                    location.reload();
                }
            });
            
            // Bind manual search button
            $container.find('.torob-manual-search-btn').on('click', function() {
                var $btn = $(this);
                var productId = $btn.data('product-id');
                var productName = $btn.data('product-name');
                
                if (productId && productName) {
                    // Show search button instead of auto search
                    TorobFrontend.showManualSearchOption($container, productId, productName);
                }
            });
        },
        
        /**
         * Auto load prices for products that have cache
         */
        autoLoadPrices: function() {
            $('.torob-price-comparison').each(function() {
                var $container = $(this);
                var productId = $container.find('[data-product-id]').data('product-id');
                
                if (productId && !$container.find('.torob-price-display').length) {
                    TorobFrontend.checkCachedPrice(productId, function(hasCache, data) {
                        if (hasCache && data) {
                            TorobFrontend.displayPriceData($container, data);
                        }
                    });
                }
            });
        },
        
        /**
         * Check if product has cached price
         */
        checkCachedPrice: function(productId, callback) {
            $.ajax({
                url: torob_frontend_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'torob_get_cached_price',
                    product_id: productId,
                    nonce: torob_frontend_vars.nonce
                },
                success: function(response) {
                    if (response.success) {
                        callback(response.data.has_cache, response.data);
                    } else {
                        callback(false);
                    }
                },
                error: function() {
                    callback(false);
                }
            });
        },
        
        /**
         * Display price data
         */
        displayPriceData: function($container, data) {
            var torobPrice = parseInt(data.data.min_price);
            var productPrice = parseInt(data.product_price);
            var savings = parseInt(data.savings);
            var torobUrl = data.data.torob_url;
            
            var priceHtml = '<div class="torob-price-display">';
            priceHtml += '<div class="torob-price-info">';
            priceHtml += '<span class="torob-label">کمترین قیمت در ترب:</span>';
            priceHtml += '<span class="torob-price">' + this.formatPrice(torobPrice) + ' تومان</span>';
            
            if (savings > 0) {
                priceHtml += '<span class="torob-savings">صرفه‌جویی: ' + this.formatPrice(savings) + ' تومان</span>';
            }
            
            priceHtml += '</div>';
            
            if (torobUrl) {
                priceHtml += '<a href="' + torobUrl + '" target="_blank" class="torob-link">مشاهده در ترب</a>';
            }
            
            priceHtml += '<button class="torob-refresh-btn" data-product-id="' + data.product_id + '">';
            priceHtml += '<span class="dashicons dashicons-update"></span> بروزرسانی';
            priceHtml += '</button>';
            priceHtml += '</div>';
            
            // Replace search button with price display
            $container.find('.torob-search-container').html(priceHtml);
        },
        
        /**
         * Set loading state for button
         */
        setLoadingState: function($button, loading) {
            if (loading) {
                $button.prop('disabled', true)
                       .addClass('loading')
                       .find('.torob-btn-text').text('در حال جستجو...');
                       
                // Add spinner
                if (!$button.find('.spinner').length) {
                    $button.prepend('<span class="spinner"></span>');
                }
            } else {
                $button.prop('disabled', false)
                       .removeClass('loading')
                       .find('.spinner').remove();
                       
                // Restore original text
                var originalText = $button.data('original-text') || 'جستجو در ترب';
                $button.find('.torob-btn-text').text(originalText);
            }
        },
        
        /**
         * Show message to user
         */
        showMessage: function($container, message, type) {
            // Remove existing messages
            $container.find('.torob-message').remove();
            
            // Add new message
            var messageHtml = '<div class="torob-message torob-message-' + type + '">' + message + '</div>';
            $container.prepend(messageHtml);
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $container.find('.torob-message').fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },
        
        /**
         * Format price with thousands separator
         */
        formatPrice: function(price) {
            return parseInt(price).toLocaleString('fa-IR');
        },
        
        /**
         * Track events (for analytics)
         */
        trackEvent: function(eventName, data) {
            // You can integrate with Google Analytics, etc.
            if (typeof gtag !== 'undefined') {
                gtag('event', eventName, {
                    'custom_parameter_1': data.product_id,
                    'custom_parameter_2': data.torob_price || 0,
                    'custom_parameter_3': data.savings || 0
                });
            }
            
            // Console log for debugging
            if (torob_frontend_vars.debug) {
                console.log('Torob Event:', eventName, data);
            }
        },
        
        /**
         * Show loading state
         */
        showLoadingState: function($container) {
            var loadingHtml = '<div class="torob-loading-state">';
            loadingHtml += '<div class="torob-spinner"></div>';
            loadingHtml += '<span class="torob-loading-text">در حال جستجوی قیمت در ترب...</span>';
            loadingHtml += '</div>';
            
            $container.html(loadingHtml);
        },
        
        /**
         * Show manual search option
         */
        showManualSearchOption: function($container, productId, productName) {
            var searchHtml = '<div class="torob-search-container">';
            searchHtml += '<button class="torob-search-btn button-primary" data-product-id="' + productId + '" data-product-name="' + productName + '">';
            searchHtml += '<span class="torob-btn-text">جستجو در ترب</span>';
            searchHtml += '</button>';
            searchHtml += '<small class="torob-search-help">برای مقایسه قیمت کلیک کنید</small>';
            searchHtml += '</div>';
            
            $container.html(searchHtml);
            
            // Bind search button
            $container.find('.torob-search-btn').on('click', function() {
                var $btn = $(this);
                TorobFrontend.setLoadingState($btn, true);
                TorobFrontend.searchPrice($btn);
            });
        },
        
        /**
         * Handle responsive behavior
         */
        handleResponsive: function() {
            var $containers = $('.torob-price-comparison');
            
            if ($(window).width() < 768) {
                $containers.addClass('torob-mobile');
            } else {
                $containers.removeClass('torob-mobile');
            }
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        TorobFrontend.init();
        TorobFrontend.handleResponsive();
    });
    
    // Handle window resize
    $(window).resize(function() {
        TorobFrontend.handleResponsive();
    });
    
    // Expose to global scope for external access
    window.TorobFrontend = TorobFrontend;
    
})(jQuery);