/**
 * Frontend abandoned cart tracking
 * Monitors checkout form changes and syncs to HubSpot
 */

(function($) {
    'use strict';
    
    // Checkout fields to monitor (same as original plugin)
    var monitoredFields = [
        'billing_email',
        'billing_first_name', 
        'billing_last_name',
        'billing_company',
        'billing_address_1',
        'billing_address_2', 
        'billing_city',
        'billing_state',
        'billing_postcode',
        'billing_country',
        'billing_phone',
        'shipping_first_name',
        'shipping_last_name', 
        'shipping_company',
        'shipping_address_1',
        'shipping_address_2',
        'shipping_city',
        'shipping_state', 
        'shipping_postcode',
        'shipping_country',
        'shipping_phone'
    ];
    
    var abandonedCartTracker = {
        
        /**
         * Initialize tracking
         */
        init: function() {
            this.bindFieldChanges();
            console.log('HubSpot abandoned cart tracking initialized');
        },
        
        /**
         * Bind change events to checkout fields
         */
        bindFieldChanges: function() {
            var self = this;
            
            // Monitor each field for changes
            monitoredFields.forEach(function(fieldId) {
                $('#' + fieldId).on('change blur', function() {
                    // Debounce to avoid too many requests
                    clearTimeout(self.trackingTimeout);
                    self.trackingTimeout = setTimeout(function() {
                        self.trackCheckoutData();
                    }, 1000);
                });
            });
            
            // Monitor shipping checkbox
            $('#ship-to-different-address-checkbox').on('change', function() {
                clearTimeout(self.trackingTimeout);
                self.trackingTimeout = setTimeout(function() {
                    self.trackCheckoutData();
                }, 1000);
            });
            
            // Monitor on checkout update
            $(document.body).on('updated_checkout', function() {
                clearTimeout(self.trackingTimeout);
                self.trackingTimeout = setTimeout(function() {
                    self.trackCheckoutData();
                }, 2000);
            });
        },
        
        /**
         * Collect and send checkout data to backend
         */
        trackCheckoutData: function() {
            var email = $('#billing_email').val();
            
            // Only track if we have a valid email
            if (!email || !this.isValidEmail(email)) {
                console.log('HubSpot tracking skipped - invalid email');
                return;
            }
            
            var data = {
                action: 'hubspot_sync_milli_track_checkout',
                nonce: hubspotAjax.nonce
            };
            
            // Collect all field values
            monitoredFields.forEach(function(fieldId) {
                var fieldValue = $('#' + fieldId).val();
                if (fieldValue && fieldValue.trim() !== '') {
                    data[fieldId] = fieldValue.trim();
                }
            });
            
            // Add shipping checkbox state
            data['ship_to_different_address'] = $('#ship-to-different-address-checkbox').is(':checked') ? 'yes' : 'no';
            
            console.log('HubSpot tracking checkout data for:', email);
            
            // Send AJAX request
            $.ajax({
                url: hubspotAjax.ajaxUrl,
                type: 'POST',
                data: data,
                timeout: 10000,
                success: function(response) {
                    try {
                        var result = typeof response === 'string' ? JSON.parse(response) : response;
                        if (result.success) {
                            console.log('HubSpot cart tracked successfully:', result.cart_hash);
                            
                            // Store cart hash in sessionStorage for reference
                            if (result.cart_hash && window.sessionStorage) {
                                sessionStorage.setItem('hubspot_cart_hash', result.cart_hash);
                            }
                            
                            // Trigger checkout update to refresh totals
                            $(document.body).trigger('update_checkout');
                        } else {
                            console.warn('HubSpot tracking failed:', result.message);
                        }
                    } catch (e) {
                        console.error('HubSpot tracking response parsing error:', e);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('HubSpot tracking AJAX error:', status, error);
                }
            });
        },
        
        /**
         * Validate email format
         */
        isValidEmail: function(email) {
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        },
        
        /**
         * Timeout reference for debouncing
         */
        trackingTimeout: null
    };
    
    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        // Only run on checkout page
        if ($('body').hasClass('woocommerce-checkout')) {
            abandonedCartTracker.init();
        }
    });
    
    /**
     * Re-initialize after checkout updates (for dynamic content)
     */
    $(document.body).on('updated_checkout', function() {
        if ($('body').hasClass('woocommerce-checkout')) {
            // Small delay to ensure DOM is updated
            setTimeout(function() {
                abandonedCartTracker.bindFieldChanges();
            }, 500);
        }
    });
    
})(jQuery);