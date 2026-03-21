<?php
/**
 * Sync manager class
 *
 * @package HubSpot_Sync_Milli
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HubSpot_Sync_Milli_Sync_Manager {
    
    /**
     * Plugin settings
     */
    private $settings;
    
    /**
     * HubSpot client
     */
    private $hubspot_client;
    
    /**
     * HubSpot API wrapper
     */
    private $api;
    
    /**
     * Constructor
     */
    public function __construct( $settings, $hubspot_client = null ) {
        $this->settings = $settings;
        $this->hubspot_client = $hubspot_client;
        
        if ( ! class_exists( 'HubSpot_Sync_Milli_HubSpot_API' ) ) {
            return;
        }
        
        $this->api = new HubSpot_Sync_Milli_HubSpot_API( $this->settings );
    }
    
    /**
     * Sync an order to HubSpot
     */
    public function sync_order( $order_id, $trigger = 'manual' ) {
        $order = wc_get_order( $order_id );
        
        if ( ! $order ) {
            return array(
                'success' => false,
                'message' => 'Order not found'
            );
        }
        
        $this->log_debug( "Starting sync for order {$order_id} (trigger: {$trigger})" );
        
        $results = array(
            'order_id' => $order_id,
            'trigger' => $trigger,
            'contact' => null,
            'deal' => null,
            'success' => false,
            'errors' => array()
        );
        
        try {
            // Sync contact if enabled
            if ( $this->settings['sync_contact_fields'] ?? true ) {
                $contact_result = $this->sync_contact( $order );
                $results['contact'] = $contact_result;
                
                if ( ! $contact_result['success'] ) {
                    $results['errors'][] = 'Contact sync failed: ' . $contact_result['message'];
                }
            }
            
            // Sync deal if enabled  
            if ( $this->settings['sync_deal_fields'] ?? true ) {
                $deal_result = $this->sync_deal( $order );
                $results['deal'] = $deal_result;
                
                if ( ! $deal_result['success'] ) {
                    $results['errors'][] = 'Deal sync failed: ' . $deal_result['message'];
                }
            }
            
            // Sync devices if enabled and order has serial numbers
            $device_result = $this->sync_order_devices( $order );
            if ( $device_result !== null ) {
                $results['devices'] = $device_result;
                
                if ( ! $device_result['success'] ) {
                    $results['errors'][] = 'Device sync failed: ' . $device_result['message'];
                }
            }
            
            // Overall success if at least one sync succeeded
            $results['success'] = ( $results['contact']['success'] ?? false ) || ( $results['deal']['success'] ?? false ) || ( $results['devices']['success'] ?? false );
            
            $this->log_debug( "Sync completed for order {$order_id}. Success: " . ( $results['success'] ? 'Yes' : 'No' ) );
            
        } catch ( Exception $e ) {
            $results['errors'][] = 'Sync exception: ' . $e->getMessage();
            $this->log_error( "Sync exception for order {$order_id}: " . $e->getMessage() );
        }
        
        return $results;
    }
    
    /**
     * Sync contact data
     */
    private function sync_contact( $order ) {
        $email = $order->get_billing_email();
        
        if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
            return array(
                'success' => false,
                'message' => 'Invalid email address'
            );
        }
        
        try {
            // Get custom field values
            if ( ! class_exists( 'HubSpot_Sync_Milli_Checkout_Fields' ) ) {
                return array(
                    'success' => false,
                    'message' => 'Checkout fields class not found'
                );
            }
            
            $checkout_fields = new HubSpot_Sync_Milli_Checkout_Fields( $this->settings );
            $custom_fields = $checkout_fields->get_order_field_values( $order->get_id() );
            
            // Search for existing contact
            $existing_contact = $this->api->search_contact( $email );
            
            // Prepare contact data
            $contact_data = array_merge( $custom_fields, array(
                'email' => $email,
                'firstname' => $order->get_billing_first_name(),
                'lastname' => $order->get_billing_last_name(),
                'phone' => $order->get_billing_phone(),
                'address' => $order->get_billing_address_1(),
                'city' => $order->get_billing_city(),
                'state' => $order->get_billing_state(),
                'zip' => $order->get_billing_postcode(),
                'country' => $order->get_billing_country()
            ) );
            
            // Add owner if specified
            if ( ! empty( $this->settings['owner_id'] ) ) {
                $contact_data['hubspot_owner_id'] = $this->settings['owner_id'];
            }
            
            // Filter out empty values
            $contact_data = array_filter( $contact_data, function( $value ) {
                return $value !== '' && $value !== null;
            } );
            
            $this->log_debug( "Contact data for order {$order->get_id()}: " . wp_json_encode( $contact_data ) );
            
            // Create or update contact
            $contact_id = $existing_contact ? $existing_contact->getId() : null;
            $contact = $this->api->upsert_contact( $contact_data, $contact_id );
            
            if ( $contact ) {
                // Store contact ID in order meta
                $order->update_meta_data( 'hubspot_contact_id', $contact->getId() );
                $order->save();
                
                return array(
                    'success' => true,
                    'message' => $contact_id ? 'Contact updated' : 'Contact created',
                    'contact_id' => $contact->getId(),
                    'data' => $contact_data
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'Failed to create/update contact'
                );
            }
            
        } catch ( Exception $e ) {
            return array(
                'success' => false,
                'message' => 'Contact sync error: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Sync deal data
     */
    private function sync_deal( $order ) {
        try {
            // Generate unique cart ID
            $unique_cart_id = $this->generate_unique_cart_id( $order );
            
            // Search for existing deal
            $existing_deal = $this->api->search_deal( $unique_cart_id );
            
            // Prepare deal data
            $deal_data = array(
                'dealname' => $this->generate_deal_name( $order ),
                'amount' => $order->get_total(),
                'tax_amount' => $order->get_total_tax(),
                'closedate' => $order->get_date_paid() ? $order->get_date_paid()->format( 'Y-m-d' ) : null,
                'pipeline' => $this->settings['deal_pipeline'] ?? 'default',
                'dealstage' => $this->get_deal_stage( $order ),
                'order_number' => $order->get_id(),
                'deal_notes' => $order->get_customer_note(),
                'woocommerce_unique_cart_id' => $unique_cart_id
            );
            
            // Add coupon information
            $coupons = $order->get_coupons();
            if ( ! empty( $coupons ) ) {
                $coupon_codes = array();
                $coupon_amount = 0;
                
                foreach ( $coupons as $coupon ) {
                    $coupon_codes[] = $coupon->get_code();
                    $coupon_amount += $coupon->get_discount();
                }
                
                $deal_data['unific_coupon_code_used_text'] = implode( ', ', $coupon_codes );
                $deal_data['coupon_amount'] = $coupon_amount;
                $deal_data['discount_amount'] = $coupon_amount;
            }
            
            // Add shipping information
            $deal_data = array_merge( $deal_data, array(
                'shipping_first_name' => $order->get_shipping_first_name() ?: $order->get_billing_first_name(),
                'shipping_last_name' => $order->get_shipping_last_name() ?: $order->get_billing_last_name(),
                'shipping_company' => $order->get_shipping_company() ?: $order->get_billing_company(),
                'shipping_address_line_1' => $order->get_shipping_address_1() ?: $order->get_billing_address_1(),
                'shipping_address_line_2' => $order->get_shipping_address_2() ?: $order->get_billing_address_2(),
                'shipping_city' => $order->get_shipping_city() ?: $order->get_billing_city(),
                'shipping_state' => $order->get_shipping_state() ?: $order->get_billing_state(),
                'shipping_postcode' => $order->get_shipping_postcode() ?: $order->get_billing_postcode(),
                'shipping_country' => $order->get_shipping_country() ?: $order->get_billing_country(),
                'shipping_phone' => $order->get_meta( '_shipping_phone' ) ?: $order->get_billing_phone()
            ) );
            
            // Add owner if specified
            if ( ! empty( $this->settings['owner_id'] ) ) {
                $deal_data['hubspot_owner_id'] = $this->settings['owner_id'];
            }
            
            // Filter out empty values
            $deal_data = array_filter( $deal_data, function( $value ) {
                return $value !== '' && $value !== null;
            } );
            
            $this->log_debug( "Deal data for order {$order->get_id()}: " . wp_json_encode( $deal_data ) );
            
            // Prepare associations
            $associations = array();
            
            // Associate with contact
            $contact_id = $order->get_meta( 'hubspot_contact_id' );
            if ( $contact_id ) {
                $association_spec = new \HubSpot\Client\Crm\Deals\Model\AssociationSpec();
                $association_spec->setAssociationCategory( 'HUBSPOT_DEFINED' );
                $association_spec->setAssociationTypeId( 3 );
                
                $to_object = new \HubSpot\Client\Crm\Deals\Model\PublicObjectId();
                $to_object->setId( $contact_id );
                
                $association = new \HubSpot\Client\Crm\Deals\Model\PublicAssociationsForObject();
                $association->setTypes( [$association_spec] );
                $association->setTo( $to_object );
                
                $associations[] = $association;
            }
            
            // Create or update deal
            $deal_id = $existing_deal ? $existing_deal->getId() : null;
            $deal = $this->api->upsert_deal( $deal_data, $deal_id, $associations );
            
            if ( $deal ) {
                // Store deal ID in order meta
                $order->update_meta_data( 'hubspot_deal_id', $deal->getId() );
                $order->save();
                
                return array(
                    'success' => true,
                    'message' => $deal_id ? 'Deal updated' : 'Deal created',
                    'deal_id' => $deal->getId(),
                    'unique_cart_id' => $unique_cart_id,
                    'data' => $deal_data
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'Failed to create/update deal'
                );
            }
            
        } catch ( Exception $e ) {
            return array(
                'success' => false,
                'message' => 'Deal sync error: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Generate unique cart ID
     */
    private function generate_unique_cart_id( $order ) {
        $email = $order->get_billing_email();
        $site_prefix = $this->get_site_prefix();
        $unique_identifier = "order_{$order->get_id()}";
        
        return md5( $email . $site_prefix . $unique_identifier );
    }
    
    /**
     * Generate deal name
     */
    private function generate_deal_name( $order ) {
        $site_prefix = $this->get_site_prefix();
        return "{" . $site_prefix . "} " . $order->get_order_number();
    }
    
    /**
     * Get site prefix
     */
    private function get_site_prefix() {
        if ( ! empty( $this->settings['site_prefix'] ) ) {
            return $this->settings['site_prefix'];
        }
        
        $environment = $this->settings['site_environment'] ?? 'staging';
        return ucfirst( $environment );
    }
    
    /**
     * Get deal stage based on order status
     */
    private function get_deal_stage( $order ) {
        $status = $order->get_status();
        $deal_stages = $this->settings['deal_stages'] ?? array();
        
        // Map order status to deal stage
        $status_mapping = array(
            'completed' => 'won',
            'processing' => 'processing',
            'cancelled' => 'cancelled',
            'refunded' => 'refunded',
            'failed' => 'failed'
        );
        
        $stage_key = $status_mapping[ $status ] ?? 'processing';
        
        return $deal_stages[ $stage_key ] ?? '';
    }
    
    /**
     * Sync devices for an order
     */
    public function sync_order_devices( $order ) {
        // Check if order has serial numbers
        $serial_numbers_data = $order->get_meta( 'serial_numbers' );
        if ( empty( $serial_numbers_data ) ) {
            return null; // No devices to sync
        }
        
        $this->log_debug( "Starting device sync for order {$order->get_id()}" );
        
        try {
            $serial_numbers = explode( ',', $serial_numbers_data );
            $synced_devices = array();
            $errors = array();
            
            foreach ( $serial_numbers as $serial_number ) {
                $serial_number = trim( $serial_number );
                
                if ( empty( $serial_number ) || $serial_number === 'N/A' ) {
                    continue;
                }
                
                $device_result = $this->create_hubspot_device( $order, $serial_number );
                
                if ( $device_result['success'] ) {
                    $synced_devices[] = $serial_number;
                    
                    // Small delay to avoid rate limiting
                    usleep( 110000 ); // 110ms
                } else {
                    $errors[] = "Device {$serial_number}: {$device_result['message']}";
                }
            }
            
            $total_devices = count( array_filter( $serial_numbers, function( $sn ) {
                return ! empty( trim( $sn ) ) && trim( $sn ) !== 'N/A';
            }));
            
            if ( count( $synced_devices ) > 0 ) {
                $this->log_debug( "Successfully synced " . count( $synced_devices ) . " devices for order {$order->get_id()}" );
                
                return array(
                    'success' => true,
                    'message' => sprintf( 'Synced %d of %d devices', count( $synced_devices ), $total_devices ),
                    'synced_devices' => $synced_devices,
                    'errors' => $errors
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'No devices were successfully synced',
                    'errors' => $errors
                );
            }
            
        } catch ( Exception $e ) {
            $this->log_error( "Device sync exception for order {$order->get_id()}: " . $e->getMessage() );
            
            return array(
                'success' => false,
                'message' => 'Device sync exception: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Create HubSpot device custom object
     */
    private function create_hubspot_device( $order, $serial_number ) {
        try {
            // Get HubSpot contact ID from order
            $contact_id = $order->get_meta( 'hubspot_contact_id' );
            if ( empty( $contact_id ) ) {
                // Try to get contact by email
                $email = $order->get_billing_email();
                if ( ! empty( $email ) ) {
                    $contact = $this->api->get_contact_by_email( $email );
                    if ( $contact ) {
                        $contact_id = $contact->getId();
                    }
                }
            }
            
            // Create device object properties
            $device_properties = array(
                'serial_numbers' => $serial_number
            );
            
            // Get device associations
            $associations = $this->get_device_associations( $order, $contact_id );
            
            // Create device via API
            $device = $this->api->create_device( $device_properties, $associations );
            
            if ( $device ) {
                $this->log_debug( "Created device {$serial_number} for order {$order->get_id()}" );
                
                return array(
                    'success' => true,
                    'message' => 'Device created successfully',
                    'device_id' => $device->getId()
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'Failed to create device'
                );
            }
            
        } catch ( Exception $e ) {
            $this->log_error( "Device creation exception: " . $e->getMessage() );
            
            return array(
                'success' => false,
                'message' => 'Device creation failed: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Get device associations for contact, deal, and company
     */
    private function get_device_associations( $order, $contact_id = null ) {
        $associations = array();
        $association_ids = $this->settings['association_ids'] ?? array();
        
        // Contact to Device association
        if ( ! empty( $contact_id ) && ! empty( $association_ids['contact_to_device'] ) ) {
            $associations[] = array(
                'to' => array( 'id' => $contact_id ),
                'types' => array(
                    array(
                        'associationCategory' => 'USER_DEFINED',
                        'associationTypeId' => (int) $association_ids['contact_to_device']
                    )
                )
            );
        }
        
        // Deal to Device association
        $deal_id = $order->get_meta( 'hubspot_deal_id' );
        if ( ! empty( $deal_id ) && ! empty( $association_ids['deal_to_device'] ) ) {
            $associations[] = array(
                'to' => array( 'id' => $deal_id ),
                'types' => array(
                    array(
                        'associationCategory' => 'USER_DEFINED',
                        'associationTypeId' => (int) $association_ids['deal_to_device']
                    )
                )
            );
        }
        
        // Company to Device association
        $company_id = $order->get_meta( 'associated_company_id' );
        if ( ! empty( $company_id ) && ! empty( $association_ids['company_to_device'] ) ) {
            $associations[] = array(
                'to' => array( 'id' => $company_id ),
                'types' => array(
                    array(
                        'associationCategory' => 'USER_DEFINED',
                        'associationTypeId' => (int) $association_ids['company_to_device']
                    )
                )
            );
        }
        
        return $associations;
    }
    
    /**
     * Update device company association when company is linked to order
     */
    public function update_device_company_association( $order_id, $company_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return false;
        }
        
        // Get serial numbers from order
        $serial_numbers_data = $order->get_meta( 'serial_numbers' );
        if ( empty( $serial_numbers_data ) ) {
            return false;
        }
        
        $association_id = $this->settings['association_ids']['company_to_device'] ?? '';
        if ( empty( $association_id ) ) {
            $this->log_debug( "No company_to_device association ID configured" );
            return false;
        }
        
        try {
            $serial_numbers = explode( ',', $serial_numbers_data );
            $success_count = 0;
            
            foreach ( $serial_numbers as $serial_number ) {
                $serial_number = trim( $serial_number );
                
                if ( empty( $serial_number ) || $serial_number === 'N/A' ) {
                    continue;
                }
                
                // Get device by serial number
                $device = $this->api->get_device_by_serial( $serial_number );
                
                if ( $device ) {
                    // Create association
                    $association_result = $this->api->create_device_association(
                        $device->getId(),
                        $company_id,
                        'company',
                        (int) $association_id
                    );
                    
                    if ( $association_result ) {
                        $success_count++;
                        $this->log_debug( "Updated company association for device {$serial_number}" );
                    }
                }
            }
            
            return $success_count > 0;
            
        } catch ( Exception $e ) {
            $this->log_error( "Device company association update failed: " . $e->getMessage() );
            return false;
        }
    }
    
    /**
     * Log debug message
     */
    private function log_debug( $message ) {
        if ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || ( $this->settings['debug_logging'] ?? false ) ) {
            error_log( '[HubSpot Sync - Milli] DEBUG: ' . $message );
        }
    }
    
    /**
     * Log error message
     */
    private function log_error( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[HubSpot Sync - Milli] ERROR: ' . $message );
        }
    }
}