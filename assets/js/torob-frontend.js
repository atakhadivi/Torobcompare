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
         * Auto search for price on page load
         */
        autoSearchPrice: function() {
            var $container = $('.torob-price-comparison');
            var $searchBtn = $container.find('.torob-search-btn');
            
            if ($searchBtn.length && !$container.find('.torob-price-display').length) {
                // Check if we have cached data first
                this.checkCachedPrice($searchBtn.data('product-id'), function(hasCache) {
                    if (!hasCache) {
                        // Trigger search after a short delay
                        setTimeout(function() {
                            $searchBtn.trigger('click');
                        }, 1000);
                    }
                });
            }
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