<?php
/**
 * Abandoned Cart Tracker
 * Handles frontend checkout tracking and abandoned cart functionality
 *
 * @package HubSpot_Sync_Milli
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HubSpot_Sync_Milli_Abandoned_Cart_Tracker {
    
    /**
     * The checkout data collected from frontend
     */
    private $checkout_data = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // AJAX endpoints for frontend tracking
        add_action( 'wp_ajax_hubspot_sync_milli_track_checkout', array( $this, 'track_checkout_data' ) );
        add_action( 'wp_ajax_nopriv_hubspot_sync_milli_track_checkout', array( $this, 'track_checkout_data' ) );
        
        // Enqueue frontend scripts
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
        
        // Order completion hooks to handle cart-to-order transitions
        add_action( 'woocommerce_payment_complete', array( $this, 'handle_cart_to_order_transition' ), 5 );
        add_action( 'woocommerce_order_status_processing', array( $this, 'handle_cart_to_order_transition' ), 5 );
    }
    
    /**
     * Enqueue frontend tracking scripts
     */
    public function enqueue_frontend_scripts() {
        // Only load on checkout page
        if ( ! is_checkout() ) {
            return;
        }
        
        wp_enqueue_script(
            'hubspot-abandoned-cart-tracker',
            HUBSPOT_SYNC_MILLI_PLUGIN_URL . 'assets/js/abandoned-cart-tracker.js',
            array( 'jquery' ),
            HUBSPOT_SYNC_MILLI_VERSION,
            true
        );
        
        wp_localize_script( 'hubspot-abandoned-cart-tracker', 'hubspotAjax', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'hubspot_abandoned_cart_nonce' )
        ));
    }
    
    /**
     * AJAX endpoint to track checkout form changes
     */
    public function track_checkout_data() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'hubspot_abandoned_cart_nonce' ) ) {
            wp_die( json_encode( array( 'success' => false, 'message' => 'Invalid nonce' ) ) );
        }
        
        // Validate required data
        $email = sanitize_email( $_POST['billing_email'] ?? '' );
        if ( empty( $email ) || ! is_email( $email ) ) {
            wp_die( json_encode( array( 'success' => false, 'message' => 'Invalid email' ) ) );
        }
        
        // Collect and sanitize checkout data
        $this->checkout_data = $this->sanitize_checkout_data( $_POST );
        
        // Check if cart is not empty
        if ( WC()->cart && ! WC()->cart->is_empty() ) {
            // Generate tracking hash
            $cart_hash = $this->generate_cart_hash( $email );
            
            // Update HubSpot with abandoned cart data
            $this->sync_abandoned_cart_to_hubspot( $cart_hash );
            
            wp_die( json_encode( array( 'success' => true, 'cart_hash' => $cart_hash ) ) );
        }
        
        wp_die( json_encode( array( 'success' => false, 'message' => 'Empty cart' ) ) );
    }
    
    /**
     * Sanitize checkout form data
     */
    private function sanitize_checkout_data( $raw_data ) {
        $allowed_fields = array(
            'billing_email', 'billing_first_name', 'billing_last_name', 'billing_company',
            'billing_address_1', 'billing_address_2', 'billing_city', 'billing_state',
            'billing_postcode', 'billing_country', 'billing_phone',
            'shipping_first_name', 'shipping_last_name', 'shipping_company',
            'shipping_address_1', 'shipping_address_2', 'shipping_city', 'shipping_state',
            'shipping_postcode', 'shipping_country', 'shipping_phone',
            'ship_to_different_address'
        );
        
        $sanitized_data = array();
        foreach ( $allowed_fields as $field ) {
            if ( isset( $raw_data[$field] ) && ! empty( trim( $raw_data[$field] ) ) ) {
                $sanitized_data[$field] = sanitize_text_field( $raw_data[$field] );
            }
        }
        
        return $sanitized_data;
    }
    
    /**
     * Generate consistent cart hash for tracking
     */
    public function generate_cart_hash( $email, $preserve_existing = true ) {
        $settings = get_option( 'hubspot_sync_milli_settings', array() );
        $site_prefix = $settings['site_prefix'] ?? '';
        
        // For cart tracking, we want a consistent hash that doesn't change
        // Check if we already have a hash stored in session
        if ( $preserve_existing && WC()->session ) {
            $existing_hash = WC()->session->get( 'hubspot_cart_hash' );
            if ( $existing_hash ) {
                return $existing_hash;
            }
        }
        
        // Generate new persistent hash using session-based identifier
        $session_id = WC()->session ? WC()->session->get_customer_id() : uniqid( 'guest_' );
        $unique_identifier = 'persistent_' . $session_id;
        
        // Create hash
        $hash_input = $email . $site_prefix . $unique_identifier;
        $cart_hash = md5( $hash_input );
        
        // Store in session for consistency
        if ( WC()->session ) {
            WC()->session->set( 'hubspot_cart_hash', $cart_hash );
        }
        
        error_log( "[HubSpot Sync - Milli] Generated cart hash: {$cart_hash} for email: {$email}" );
        
        return $cart_hash;
    }
    
    /**
     * Sync abandoned cart data to HubSpot
     */
    private function sync_abandoned_cart_to_hubspot( $cart_hash ) {
        try {
            $sync_manager = new HubSpot_Sync_Milli_Sync_Manager();
            
            // Prepare cart data
            $cart_data = array(
                'cart_hash' => $cart_hash,
                'email' => $this->checkout_data['billing_email'],
                'first_name' => $this->checkout_data['billing_first_name'] ?? '',
                'last_name' => $this->checkout_data['billing_last_name'] ?? '',
                'phone' => $this->checkout_data['billing_phone'] ?? '',
                'company' => $this->checkout_data['billing_company'] ?? '',
                'checkout_data' => $this->checkout_data,
                'cart_total' => WC()->cart->get_total( 'raw' ),
                'cart_tax' => WC()->cart->get_total_tax( 'raw' ),
                'cart_items' => WC()->cart->get_cart(),
                'applied_coupons' => WC()->cart->get_applied_coupons(),
                'discount_total' => WC()->cart->get_discount_total( 'raw' )
            );
            
            // Sync to HubSpot
            $result = $sync_manager->sync_abandoned_cart( $cart_data );
            
            if ( $result['success'] ) {
                error_log( "[HubSpot Sync - Milli] Abandoned cart synced successfully. Deal ID: {$result['deal_id']}" );
            } else {
                error_log( "[HubSpot Sync - Milli] Failed to sync abandoned cart: {$result['message']}" );
            }
            
        } catch ( Exception $e ) {
            error_log( "[HubSpot Sync - Milli] Exception syncing abandoned cart: " . $e->getMessage() );
        }
    }
    
    /**
     * Handle cart-to-order transition
     */
    public function handle_cart_to_order_transition( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }
        
        // Get the cart hash from session
        $cart_hash = WC()->session ? WC()->session->get( 'hubspot_cart_hash' ) : null;
        
        if ( $cart_hash ) {
            // Store cart hash in order meta for future reference
            $order->update_meta_data( 'hubspot_cart_hash', $cart_hash );
            $order->save();
            
            // Update the abandoned cart deal to completed
            $this->convert_abandoned_cart_to_order( $cart_hash, $order );
            
            // Clear the session hash since order is now complete
            if ( WC()->session ) {
                WC()->session->__unset( 'hubspot_cart_hash' );
            }
            
            error_log( "[HubSpot Sync - Milli] Converting abandoned cart {$cart_hash} to completed order {$order_id}" );
        } else {
            // No cart hash found, this is a direct order
            error_log( "[HubSpot Sync - Milli] No cart hash found for order {$order_id}, treating as direct order" );
        }
    }
    
    /**
     * Convert abandoned cart deal to completed order
     */
    private function convert_abandoned_cart_to_order( $cart_hash, $order ) {
        try {
            $sync_manager = new HubSpot_Sync_Milli_Sync_Manager();
            $result = $sync_manager->convert_abandoned_cart_to_order( $cart_hash, $order );
            
            if ( $result['success'] ) {
                // Store HubSpot deal ID in order meta
                $order->update_meta_data( 'hubspot_deal_id', $result['deal_id'] );
                $order->save();
                
                error_log( "[HubSpot Sync - Milli] Successfully converted abandoned cart to order. Deal ID: {$result['deal_id']}" );
            } else {
                error_log( "[HubSpot Sync - Milli] Failed to convert abandoned cart: {$result['message']}" );
            }
            
        } catch ( Exception $e ) {
            error_log( "[HubSpot Sync - Milli] Exception converting abandoned cart: " . $e->getMessage() );
        }
    }
    
    /**
     * Get cart hash for a completed order
     */
    public static function get_order_cart_hash( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return null;
        }
        
        return $order->get_meta( 'hubspot_cart_hash' );
    }
    
    /**
     * Check if an order was converted from abandoned cart
     */
    public static function is_converted_from_abandoned_cart( $order_id ) {
        $cart_hash = self::get_order_cart_hash( $order_id );
        return ! empty( $cart_hash );
    }
}