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
        echo '<h3 style="margin-top: 30px;padding: 0;font-size: 26px;color: #85334e;">' . esc_html__( 'Were you referred by a healthcare provider?', 'hubspot-sync-milli' ) . '</h3>';
        
        // Single healthcare provider referral question
        echo '<p class="form-row form-row-wide hubspot-simple-radio-field" id="provider_referred_field">';
        
        echo '<span class="woocommerce-input-wrapper">';
        $current_value = $checkout->get_value( 'provider_referred' );
        
        $options = array(
            'Yes' => esc_html__( 'Yes', 'hubspot-sync-milli' ),
            'No'  => esc_html__( 'No', 'hubspot-sync-milli' )
        );
        
        foreach ( $options as $option_key => $option_text ) {
            $checked = checked( $current_value, $option_key, false );
            echo '<label class="radio-option" style="display:inline-block;margin-right:20px;cursor:pointer;">';
            echo '<input type="radio" name="provider_referred" id="provider_referred_' . esc_attr( $option_key ) . '" value="' . esc_attr( $option_key ) . '" ' . $checked . ' />';
            echo ' ' . esc_html( $option_text );
            echo '</label>';
        }
        
        echo '</span>';
        echo '</p>';
        echo '</div>';
        
        // Add custom styling
        $this->add_checkout_styles();
    }
    
    /**
     * Add custom CSS for better radio button display
     */
    private function add_checkout_styles() {
        ?>
        <style type="text/css">
        #hubspot-simple-checkout-fields .hubspot-simple-radio-field .main-field-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        #hubspot-simple-checkout-fields .hubspot-simple-radio-field .woocommerce-input-wrapper {
            display: block;
        }
        
        #hubspot-simple-checkout-fields .hubspot-simple-radio-field .radio-option {
            display: block;
            margin-bottom: 6px;
            font-weight: normal;
            cursor: pointer;
        }
        
        #hubspot-simple-checkout-fields .hubspot-simple-radio-field .radio-option input[type="radio"] {
            margin-right: 6px;
            vertical-align: middle;
        }
        
        #hubspot-simple-checkout-fields .hubspot-simple-radio-field .required {
            color: #ff0000;
            text-decoration: none;
        }
        </style>
        <?php
    }
    
    /**
     * Validate checkout fields
     */
    public function validate_fields() {
        // Validate that the provider_referred field is filled
        if ( empty( $_POST['provider_referred'] ) ) {
            wc_add_notice( 
                esc_html__( 'Please let us know if you were referred by a healthcare provider.', 'hubspot-sync-milli' ), 
                'error' 
            );
        }
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