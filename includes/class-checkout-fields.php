<?php
/**
 * Checkout fields class
 *
 * @package HubSpot_Sync_Milli
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HubSpot_Sync_Milli_Checkout_Fields {
    
    /**
     * Plugin settings
     */
    private $settings;
    
    /**
     * Constructor
     */
    public function __construct( $settings ) {
        $this->settings = $settings;
    }
    
    /**
     * Render checkout fields
     */
    public function render_fields( $checkout ) {
        if ( ! $this->settings['sync_contact_fields'] ?? true ) {
            return;
        }
        
        echo '<div id="hubspot_sync_milli_fields">';
        
        // Acquisition source field
        echo '<h3>' . esc_html__( 'How did you hear about us?', 'hubspot-sync-milli' ) . '</h3>';
        
        woocommerce_form_field( 'acquisition_source', array(
            'type'     => 'select',
            'class'    => array( 'form-row-wide' ),
            'label'    => esc_html__( 'By telling us where you first heard about Milli, you\'ll help us reach more women who may be struggling in silence and don\'t realize there\'s an effective, at-home therapy for vaginal muscle tightness. As a thank you, you\'ll get access to exclusive partner discounts.', 'hubspot-sync-milli' ),
            'required' => false,
            'options'  => array(
                ''                      => esc_html__( 'Select One', 'hubspot-sync-milli' ),
                'Healthcare Provider'   => esc_html__( 'Healthcare Provider', 'hubspot-sync-milli' ),
                'Google Search'         => esc_html__( 'Google Search', 'hubspot-sync-milli' ),
                'Social Media'          => esc_html__( 'Social Media', 'hubspot-sync-milli' ),
                'Blog or Article'       => esc_html__( 'Blog or Article', 'hubspot-sync-milli' ),
                'Podcast'               => esc_html__( 'Podcast', 'hubspot-sync-milli' ),
                'Online group or forum' => esc_html__( 'Online group or forum', 'hubspot-sync-milli' ),
                'Advertisement'         => esc_html__( 'Advertisement', 'hubspot-sync-milli' ),
                'Other'                 => esc_html__( 'Other', 'hubspot-sync-milli' )
            )
        ), $checkout->get_value( 'acquisition_source' ) );
        
        // Conditional fields wrapper
        echo '<div id="talked_to_provider_wrapper" style="display:none;">';
        woocommerce_form_field( 'talked_to_provider', array(
            'type'     => 'select',
            'class'    => array( 'form-row-wide' ),
            'label'    => esc_html__( 'Have you ever talked to a healthcare provider about your challenges?', 'hubspot-sync-milli' ),
            'options'  => array(
                ''    => esc_html__( 'Choose an option...', 'hubspot-sync-milli' ),
                'Yes' => esc_html__( 'Yes', 'hubspot-sync-milli' ),
                'No'  => esc_html__( 'No', 'hubspot-sync-milli' )
            )
        ), $checkout->get_value( 'talked_to_provider' ) );
        echo '</div>';
        
        echo '<div id="provider_referred_wrapper" style="display:none;">';
        woocommerce_form_field( 'provider_referred', array(
            'type'     => 'select',
            'class'    => array( 'form-row-wide' ),
            'label'    => esc_html__( 'Did a provider refer you to Milli?', 'hubspot-sync-milli' ),
            'options'  => array(
                ''    => esc_html__( 'Choose an option...', 'hubspot-sync-milli' ),
                'Yes' => esc_html__( 'Yes', 'hubspot-sync-milli' ),
                'No'  => esc_html__( 'No', 'hubspot-sync-milli' )
            )
        ), $checkout->get_value( 'provider_referred' ) );
        echo '</div>';
        
        // Provider details section
        echo '<div id="provider_details_section" style="display:none; margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 5px;">';
        
        echo '<h4>' . esc_html__( 'Thanks for sharing!', 'hubspot-sync-milli' ) . '</h4>';
        echo '<p style="font-size: 0.9em; margin-bottom: 5px;">' . esc_html__( 'Could you tell us your provider\'s name, clinic, and location?', 'hubspot-sync-milli' ) . '</p>';
        echo '<p style="font-size: 0.85em; margin-bottom: 15px; color: #666;"><em>' . esc_html__( 'Please note that we are not sharing this information.', 'hubspot-sync-milli' ) . '</em></p>';
        
        // Clinician name
        woocommerce_form_field( 'clinician_name', array(
            'type'  => 'text',
            'class' => array( 'form-row-wide' ),
            'label' => esc_html__( 'Clinician Name', 'hubspot-sync-milli' ),
            'placeholder' => esc_html__( 'Clinician Name', 'hubspot-sync-milli' )
        ), $checkout->get_value( 'clinician_name' ) );
        
        // State selector
        woocommerce_form_field( 'clinic_state', array(
            'type'    => 'select',
            'class'   => array( 'form-row-wide' ),
            'label'   => esc_html__( 'State', 'hubspot-sync-milli' ),
            'options' => array_merge( 
                array( '' => esc_html__( 'Select state', 'hubspot-sync-milli' ) ), 
                WC()->countries->get_states( 'US' ) 
            )
        ), $checkout->get_value( 'clinic_state' ) );
        
        // Clinic name
        woocommerce_form_field( 'clinic_name', array(
            'type'  => 'text',
            'class' => array( 'form-row-wide' ),
            'label' => esc_html__( 'Clinic Name', 'hubspot-sync-milli' ),
        ), $checkout->get_value( 'clinic_name' ) );
        
        echo '</div>'; // End provider details
        echo '</div>'; // End main wrapper
        
        // Add JavaScript for conditional logic
        $this->add_checkout_script();
    }
    
    /**
     * Add checkout JavaScript
     */
    private function add_checkout_script() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            function updateSurveyLogic() {
                var source = $('#acquisition_source').val();
                var talked = $('#talked_to_provider').val();
                var referred = $('#provider_referred').val();

                // Reset visibility
                $('#talked_to_provider_wrapper, #provider_referred_wrapper, #provider_details_section').hide();

                if (source === 'Healthcare Provider') {
                    // Direct to details for healthcare provider
                    $('#provider_details_section').show();
                } else if (source && source !== '') {
                    // Show "Have you talked..." for other sources
                    $('#talked_to_provider_wrapper').show();

                    if (talked === 'Yes') {
                        $('#provider_referred_wrapper').show();

                        if (referred === 'Yes') {
                            $('#provider_details_section').show();
                        }
                    }
                }
            }

            // Bind events
            $('#acquisition_source, #talked_to_provider, #provider_referred').on('change', updateSurveyLogic);

            // Initialize
            updateSurveyLogic();
        });
        </script>
        <?php
    }
    
    /**
     * Save checkout fields
     */
    public function save_fields( $order_id ) {
        if ( ! $this->settings['sync_contact_fields'] ?? true ) {
            return;
        }
        
        $field_mapping = $this->settings['contact_field_mapping'] ?? array();
        
        foreach ( $field_mapping as $field_key => $hubspot_property ) {
            if ( ! empty( $_POST[ $field_key ] ) ) {
                $value = sanitize_text_field( $_POST[ $field_key ] );
                update_post_meta( $order_id, "_$field_key", $value );
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
        $us_states = WC()->countries->get_states( 'US' );
        
        foreach ( $field_mapping as $field_key => $hubspot_property ) {
            $value = get_post_meta( $order_id, "_$field_key", true );
            
            if ( ! empty( $value ) ) {
                // Transform state codes to full names
                if ( $field_key === 'clinic_state' && isset( $us_states[ $value ] ) ) {
                    $value = $us_states[ $value ];
                }
                
                // Transform Yes/No to boolean strings for HubSpot
                if ( in_array( $field_key, array( 'talked_to_provider', 'provider_referred' ), true ) ) {
                    if ( $value === 'Yes' ) {
                        $value = 'true';
                    } elseif ( $value === 'No' ) {
                        $value = 'false';
                    }
                }
                
                $values[ $hubspot_property ] = $value;
            }
        }
        
        return $values;
    }
}