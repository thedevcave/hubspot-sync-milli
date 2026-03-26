<?php
/**
 * Simple Checkout Fields - Single healthcare provider referral question
 *
 * @package HubSpot_Sync_Milli
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Simplified checkout fields class with single healthcare provider question
 */
class HubSpot_Sync_Milli_Checkout_Fields_Simple {
    
    /**
     * Plugin settings
     *
     * @var array
     */
    private $settings;
    
    /**
     * Constructor
     */
    public function __construct( $settings = null ) {
        $this->settings = $settings ?: get_option( 'hubspot_sync_milli_settings', array() );
        $this->init();
    }
    
    /**
     * Initialize hooks
     */
    private function init() {
        if ( ! ( $this->settings['sync_contact_fields'] ?? true ) ) {
            return;
        }
        
        add_action( 'woocommerce_checkout_billing', array( $this, 'render_fields' ), 20 );
        add_action( 'woocommerce_checkout_process', array( $this, 'validate_fields' ) );
        add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_fields' ) );
    }
    
    /**
     * Render checkout fields
     */
    public function render_fields( $checkout ) {
        echo '<div id="hubspot-simple-checkout-fields">';
        echo '<h3>' . esc_html__( 'Help us serve you better', 'hubspot-sync-milli' ) . '</h3>';
        
        // Single healthcare provider referral question
        woocommerce_form_field( 'provider_referred', array(
            'type'     => 'radio',
            'class'    => array( 'form-row-wide' ),
            'label'    => esc_html__( 'Were you referred by a healthcare provider?', 'hubspot-sync-milli' ),
            'required' => false,
            'options'  => array(
                'Yes' => esc_html__( 'Yes', 'hubspot-sync-milli' ),
                'No'  => esc_html__( 'No', 'hubspot-sync-milli' )
            )
        ), $checkout->get_value( 'provider_referred' ) );
        
        echo '</div>';
    }
    
    /**
     * Validate checkout fields
     */
    public function validate_fields() {
        // No validation required - field is optional
        // Could add validation here if needed in the future
    }
    
    /**
     * Save checkout fields
     */
    public function save_fields( $order_id ) {
        if ( ! $this->settings['sync_contact_fields'] ?? true ) {
            return;
        }
        
        $field_mapping = $this->settings['contact_field_mapping'] ?? array();
        
        // Only save the provider_referred field
        if ( ! empty( $_POST['provider_referred'] ) && isset( $field_mapping['provider_referred'] ) ) {
            $value = sanitize_text_field( $_POST['provider_referred'] );
            update_post_meta( $order_id, '_provider_referred', $value );
        }
    }
    
    /**
     * Get field values for an order
     */
    public function get_order_field_values( $order_id ) {
        if ( ! $this->settings['sync_contact_fields'] ?? true ) {
            return array();
        }
        
        $field_mapping = $this->settings['contact_field_mapping'] ?? array();
        $values = array();
        
        // Only get the provider_referred field
        if ( isset( $field_mapping['provider_referred'] ) ) {
            $value = get_post_meta( $order_id, '_provider_referred', true );
            
            if ( ! empty( $value ) ) {
                // Transform Yes/No to boolean strings for HubSpot
                if ( $value === 'Yes' ) {
                    $value = 'true';
                } elseif ( $value === 'No' ) {
                    $value = 'false';
                }
                
                $values[ $field_mapping['provider_referred'] ] = $value;
            }
        }
        
        return $values;
    }
}