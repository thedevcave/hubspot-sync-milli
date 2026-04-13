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
        // Check if contact sync and checkout fields are both enabled
        if ( ! ( $this->settings['sync_contact_fields'] ?? true ) || ! ( $this->settings['enable_checkout_fields'] ?? true ) ) {
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
        echo '<h3 style="margin-top: 30px;padding: 0;font-size: 26px;color: #85334e;">' . esc_html__( 'Were you referred by a healthcare provider?', 'hubspot-sync-milli' ) . ' <sup style="color: #ff0000;">*</sup></h3>';
        
        // Use WooCommerce field structure for better integration
        $field = array(
            'type'        => 'radio',
            'label'       => esc_html__( 'Were you referred by a healthcare provider?', 'hubspot-sync-milli' ),
            'required'    => true,
            'class'       => array( 'form-row-wide', 'hubspot-simple-radio-field', 'validate-required' ),
            'options'     => array(
                'Yes' => esc_html__( 'Yes', 'hubspot-sync-milli' ),
                'No'  => esc_html__( 'No', 'hubspot-sync-milli' )
            ),
            'default'     => '',
            'clear'       => true,
            'label_class' => array( 'screen-reader-text' ), // Hide the default label since we have custom heading
        );
        
        $current_value = $checkout->get_value( 'provider_referred' );
        
        echo '<p class="form-row form-row-wide hubspot-simple-radio-field validate-required woocommerce-validated" id="provider_referred_field">';
        
        echo '<span class="woocommerce-input-wrapper">';
        
        foreach ( $field['options'] as $option_key => $option_text ) {
            $checked = checked( $current_value, $option_key, false );
            echo '<label class="radio-option" style="display:inline-block;margin-right:20px;cursor:pointer;">';
            echo '<input type="radio" name="provider_referred" id="provider_referred_' . esc_attr( $option_key ) . '" value="' . esc_attr( $option_key ) . '" required="required" aria-required="true" ' . $checked . ' />';
            echo ' ' . esc_html( $option_text );
            echo '</label>';
        }
        
        echo '</span>';
        echo '</p>';
        echo '</div>';
        
        // Add custom styling and validation JavaScript
        $this->add_checkout_styles();
        $this->add_validation_script();
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
            position: relative;
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
        
        /* Enhanced validation styling */
        #hubspot-simple-checkout-fields .hubspot-simple-radio-field.validate-required .woocommerce-input-wrapper {
            border: none;
            padding: 0;
        }
        
        #hubspot-simple-checkout-fields .hubspot-simple-radio-field.woocommerce-invalid .woocommerce-input-wrapper {
            border: 2px solid #e2401c;
            padding: 5px;
            border-radius: 4px;
            background-color: #ffe6e6;
            margin-top: 5px;
        }

        #hubspot-simple-checkout-fields .hubspot-simple-radio-field.woocommerce-invalid::after { display: none !important; }
        
        #hubspot-simple-checkout-fields .hubspot-simple-radio-field.woocommerce-invalid .radio-option {
            margin-bottom: 8px;
        }
        
        #hubspot-simple-checkout-fields .hubspot-simple-radio-field .hubspot-error-message {
            color: #e2401c;
            font-size: 0.875em;
            display: block;
            margin-top: 5px;
            font-weight: normal;
        }
        
        /* Make sure radio buttons are easily clickable */
        #hubspot-simple-checkout-fields .hubspot-simple-radio-field .radio-option {
            padding: 5px 0;
        }
        
        #hubspot-simple-checkout-fields .hubspot-simple-radio-field .radio-option input[type="radio"] {
            margin-right: 8px;
            transform: scale(1.1);
        }
        </style>
        <?php
    }
    
    /**
     * Add validation JavaScript
     */
    private function add_validation_script() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var providerFieldValidated = false;
            
            // Function to validate provider field
            function validateProviderField() {
                var isSelected = $('input[name="provider_referred"]:checked').length > 0;
                var fieldWrapper = $('#provider_referred_field');
                
                if (!isSelected) {
                    fieldWrapper.addClass('woocommerce-invalid').removeClass('woocommerce-validated');
                    fieldWrapper.find('.woocommerce-input-wrapper').append('<span class="hubspot-error-message" style="color: #e2401c; font-size: 0.875em; display: block; margin-top: 5px;">This field is required.</span>');
                    return false;
                } else {
                    fieldWrapper.removeClass('woocommerce-invalid').addClass('woocommerce-validated');
                    fieldWrapper.find('.hubspot-error-message').remove();
                    return true;
                }
            }
            
            // Validate on radio button change
            $(document).on('change', 'input[name="provider_referred"]', function() {
                providerFieldValidated = validateProviderField();
            });
            
            // Validate before checkout submission
            $(document.body).on('checkout_error', function() {
                validateProviderField();
            });
            
            // Hook into WooCommerce checkout validation
            $('form.checkout').on('checkout_place_order', function() {
                // Remove existing error messages
                $('#provider_referred_field .hubspot-error-message').remove();
                
                if (!validateProviderField()) {
                    // Scroll to the error field
                    $('html, body').animate({
                        scrollTop: $('#provider_referred_field').offset().top - 100
                    }, 500);
                    return false; // Prevent form submission
                }
                return true;
            });
        });
        </script>
        <?php
    }

    /**
     * Validate checkout fields
     */
    public function validate_fields() {
        // Clear any existing validation state
        $field_value = isset( $_POST['provider_referred'] ) ? sanitize_text_field( $_POST['provider_referred'] ) : '';
        
        // Validate that the provider_referred field is filled and has a valid value
        if ( empty( $field_value ) || ! in_array( $field_value, array( 'Yes', 'No' ), true ) ) {
            wc_add_notice( 
                esc_html__( 'Please let us know if you were referred by a healthcare provider.', 'hubspot-sync-milli' ), 
                'error' 
            );
            
            // Add JavaScript to mark field as invalid for visual styling
            wc_enqueue_js( "
                jQuery(document).ready(function($) {
                    $('#provider_referred_field').addClass('woocommerce-invalid').removeClass('woocommerce-validated');
                    if ($('#provider_referred_field .hubspot-error-message').length === 0) {
                        $('#provider_referred_field .woocommerce-input-wrapper').append('<span class=\"hubspot-error-message\" style=\"color: #e2401c; font-size: 0.875em; display: block; margin-top: 5px;\">This field is required.</span>');
                    }
                    $('html, body').animate({
                        scrollTop: $('#provider_referred_field').offset().top - 100
                    }, 500);
                });
            " );
            
            return false;
        }
        
        return true;
    }
    
    /**
     * Save checkout fields
     */
    public function save_fields( $order_id ) {
        // Check if contact sync and checkout fields are both enabled
        if ( ! ( $this->settings['sync_contact_fields'] ?? true ) || ! ( $this->settings['enable_checkout_fields'] ?? true ) ) {
            return;
        }
        
        $field_mapping = $this->settings['contact_field_mapping'] ?? array();
        
        // Only save the provider_referred field if it has a valid value
        if ( isset( $_POST['provider_referred'] ) && isset( $field_mapping['provider_referred'] ) ) {
            $value = sanitize_text_field( $_POST['provider_referred'] );
            
            // Validate the value is one of our expected options
            if ( in_array( $value, array( 'Yes', 'No' ), true ) ) {
                update_post_meta( $order_id, '_provider_referred', $value );
                
                // Log for debugging (optional - can be removed in production)
                error_log( "HubSpot Sync Milli: Saved provider_referred field with value: {$value} for order {$order_id}" );
            } else {
                error_log( "HubSpot Sync Milli: Invalid provider_referred value attempted to be saved: {$value} for order {$order_id}" );
            }
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