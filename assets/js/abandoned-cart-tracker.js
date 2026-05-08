/**
 * Frontend abandoned cart tracking
 * Monitors checkout form changes and syncs to HubSpot
 */

(function($) {
    'use strict';

    // Prevent duplicate tracker instances if the script is loaded more than once.
    if (window.__hubspotMilliCartTrackerLoaded) {
        return;
    }
    window.__hubspotMilliCartTrackerLoaded = true;

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
        bound: false,
        lastPayloadSignature: null,
        lastTrackedAt: 0,
        minTrackIntervalMs: 15000,
        inFlight: false,
        
        /**
         * Initialize tracking
         */
        init: function() {
            if (!this.bound) {
                this.bindFieldChanges();
                this.bound = true;
            }
            console.log('HubSpot abandoned cart tracking initialized');
        },
        
        /**
         * Bind change events to checkout fields
         */
        bindFieldChanges: function() {
            var self = this;
            var fieldSelector = monitoredFields.map(function(fieldId) {
                return '#' + fieldId;
            }).join(', ');
            
            $(document.body)
                .off('input.hubspotTracker change.hubspotTracker', fieldSelector)
                .on('input.hubspotTracker change.hubspotTracker', fieldSelector, function(event) {
                    // Ignore programmatic events fired by other scripts.
                    if (event && event.isTrigger) {
                        return;
                    }
                    clearTimeout(self.trackingTimeout);
                    self.trackingTimeout = setTimeout(function() {
                        self.trackCheckoutData();
                    }, 1200);
                });

            $(document.body)
                .off('change.hubspotTracker', '#ship-to-different-address-checkbox')
                .on('change.hubspotTracker', '#ship-to-different-address-checkbox', function(event) {
                if (event && event.isTrigger) {
                    return;
                }
                clearTimeout(self.trackingTimeout);
                self.trackingTimeout = setTimeout(function() {
                    self.trackCheckoutData();
                }, 1200);
            });
        },
        
        /**
         * Collect and send checkout data to backend
         */
        trackCheckoutData: function() {
            if (this.inFlight) {
                return;
            }

            if ((Date.now() - this.lastTrackedAt) < this.minTrackIntervalMs) {
                return;
            }

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

            // Skip duplicate payloads to avoid repeat AJAX calls during checkout refreshes.
            var payloadSignature = JSON.stringify(data);
            if (payloadSignature === this.lastPayloadSignature) {
                return;
            }
            this.lastPayloadSignature = payloadSignature;
            this.lastTrackedAt = Date.now();
            this.inFlight = true;
            
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
                        } else {
                            console.warn('HubSpot tracking failed:', result.message);
                        }
                    } catch (e) {
                        console.error('HubSpot tracking response parsing error:', e);
                    }
                },
                complete: function() {
                    abandonedCartTracker.inFlight = false;
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
    
})(jQuery);