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
                    $contact = $this->api->search_contact( $email );
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
    
    /**
     * Process serial numbers and add them to orders
     * This method handles incoming serial number data and creates device records
     */
    public function process_serial_numbers( $serial_numbers_data ) {
        if ( empty( $serial_numbers_data ) || ! is_array( $serial_numbers_data ) ) {
            $this->log_error( 'Invalid serial numbers data provided' );
            return false;
        }
        
        $processed_count = 0;
        
        foreach ( $serial_numbers_data as $serial_data ) {
            $order_id = $serial_data['Order ID'] ?? '';
            $serial_number = $serial_data['Product Serial Number Shipped'] ?? '';
            
            if ( empty( $order_id ) || empty( $serial_number ) ) {
                continue;
            }
            
            if ( $this->update_serial_number( $order_id, $serial_number ) ) {
                $processed_count++;
            }
        }
        
        $this->log_debug( "Processed {$processed_count} serial numbers" );
        return $processed_count > 0;
    }
    
    /**
     * Update serial number for a specific order
     */
    public function update_serial_number( $order_id, $serial_number ) {
        if ( get_post_type( $order_id ) !== 'shop_order' ) {
            $this->log_error( "Invalid order ID: {$order_id}" );
            return false;
        }
        
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            $this->log_error( "Order not found: {$order_id}" );
            return false;
        }
        
        $this->log_debug( "Processing serial number {$serial_number} for order {$order_id}" );
        
        $updated = false;
        $order_serial_numbers = array();
        
        // Process order items to find Milli product
        foreach ( $order->get_items() as $item ) {
            $product_name = $item->get_name();
            
            // Check if this is the Milli Vaginal Dilator product
            if ( stripos( $product_name, 'Milli Vaginal Dilator' ) !== false ) {
                // Add serial number to item meta
                $item->add_meta_data( 'Serial number', $serial_number );
                $item->save();
                
                if ( $serial_number !== 'N/A' ) {
                    $order_serial_numbers[] = $serial_number;
                    $updated = true;
                    
                    $this->log_debug( "Added serial number {$serial_number} to item {$item->get_id()}" );
                    
                    // Create HubSpot device record
                    $device_result = $this->create_hubspot_device( $order, $serial_number );
                    
                    if ( $device_result['success'] ) {
                        $this->log_debug( "Created HubSpot device for serial number {$serial_number}" );
                    } else {
                        $this->log_error( "Failed to create HubSpot device: " . $device_result['message'] );
                    }
                }
                
                break; // Only process first Milli product found
            }
        }
        
        if ( $updated ) {
            // Update order meta with all serial numbers
            $existing_serial_numbers = $order->get_meta( 'serial_numbers' );
            $all_serial_numbers = $existing_serial_numbers ? explode( ',', $existing_serial_numbers ) : array();
            
            // Add new serial numbers that aren't already present
            foreach ( $order_serial_numbers as $sn ) {
                if ( ! in_array( $sn, $all_serial_numbers ) ) {
                    $all_serial_numbers[] = $sn;
                }
            }
            
            $order->update_meta_data( 'serial_numbers', implode( ',', $all_serial_numbers ) );
            $order->save();
            
            $this->log_debug( "Updated order {$order_id} with serial numbers: " . implode( ',', $all_serial_numbers ) );
            
            // Trigger device sync for this order
            $this->sync_order_devices( $order );
            
            return true;
        }
        
        if ( empty( $order_serial_numbers ) ) {
            // If no serial numbers were added, set N/A
            $order->update_meta_data( 'serial_numbers', 'N/A' );
            $order->save();
            $this->log_debug( "Set serial numbers to N/A for order {$order_id}" );
        }
        
        return false;
    }
    
    /**
     * Batch process orders and update their serial numbers from external source
     */
    public function batch_update_serial_numbers( $order_limit = 100 ) {
        $this->log_debug( 'Starting batch serial number update process' );
        
        // Get recent orders that might need serial number updates
        $date_query = '>' . ( time() - ( 2 * DAY_IN_SECONDS ) ); // Last 2 days
        
        $orders = wc_get_orders( array(
            'type' => 'shop_order',
            'status' => array( 'processing', 'completed' ),
            'date_created' => $date_query,
            'limit' => $order_limit
        ) );
        
        $processed_count = 0;
        
        foreach ( $orders as $order ) {
            $serial_numbers = $order->get_meta( 'serial_numbers' );
            
            // Only process if serial numbers exist
            if ( ! empty( $serial_numbers ) && $serial_numbers !== 'N/A' ) {
                $order_id = $order->get_id();
                $serial_numbers_array = explode( ',', $serial_numbers );
                
                foreach ( $serial_numbers_array as $serial_number ) {
                    $serial_number = trim( $serial_number );
                    if ( ! empty( $serial_number ) && $serial_number !== 'N/A' ) {
                        // Process each serial number
                        $device_result = $this->create_hubspot_device( $order, $serial_number );
                        
                        if ( $device_result['success'] ) {
                            $this->log_debug( "Processed device {$serial_number} for order {$order_id}" );
                            $processed_count++;
                        } else {
                            $this->log_error( "Failed to process device {$serial_number} for order {$order_id}: " . $device_result['message'] );
                        }
                        
                        // Small delay to avoid rate limiting
                        usleep( 110000 );
                    }
                }
            }
        }
        
        $this->log_debug( "Batch processing completed. Processed {$processed_count} devices from " . count( $orders ) . " orders" );
        return $processed_count;
    }
    
    /**
     * Sync abandoned cart data to HubSpot
     */
    public function sync_abandoned_cart( $cart_data ) {
        try {
            $this->log_debug( "Starting abandoned cart sync for hash: {$cart_data['cart_hash']}" );
            
            // First, search for existing deal with this cart hash
            $existing_deal = $this->search_deal_by_cart_hash( $cart_data['cart_hash'] );
            
            // Create or update contact
            $contact_result = $this->api->create_or_update_contact( 
                $cart_data['email'], 
                $this->prepare_contact_data( $cart_data )
            );
            
            if ( ! $contact_result['success'] ) {
                return array(
                    'success' => false,
                    'message' => 'Failed to create/update contact: ' . $contact_result['message']
                );
            }
            
            $contact_id = $contact_result['contact_id'];
            
            // Prepare deal data
            $deal_data = $this->prepare_abandoned_cart_deal_data( $cart_data );
            
            if ( $existing_deal ) {
                // Update existing abandoned cart deal
                $deal_result = $this->api->update_deal( $existing_deal['id'], $deal_data );
                $deal_id = $existing_deal['id'];
                $this->log_debug( "Updated existing abandoned cart deal: {$deal_id}" );
            } else {
                // Create new abandoned cart deal
                $deal_result = $this->api->create_deal( $deal_data );
                if ( ! $deal_result['success'] ) {
                    return array(
                        'success' => false,
                        'message' => 'Failed to create deal: ' . $deal_result['message']
                    );
                }
                $deal_id = $deal_result['deal_id'];
                $this->log_debug( "Created new abandoned cart deal: {$deal_id}" );
                
                // Associate deal with contact
                $this->api->create_association( 'deals', $deal_id, 'contacts', $contact_id, 'deal_to_contact' );
            }
            
            return array(
                'success' => true,
                'deal_id' => $deal_id,
                'contact_id' => $contact_id,
                'is_update' => $existing_deal ? true : false
            );
            
        } catch ( Exception $e ) {
            $this->log_error( "Exception in sync_abandoned_cart: " . $e->getMessage() );
            return array(
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Convert abandoned cart to completed order
     */
    public function convert_abandoned_cart_to_order( $cart_hash, $order ) {
        try {
            $this->log_debug( "Converting abandoned cart {$cart_hash} to order {$order->get_id()}" );
            
            // Find existing abandoned cart deal
            $existing_deal = $this->search_deal_by_cart_hash( $cart_hash );
            
            if ( ! $existing_deal ) {
                // No existing deal found, sync as new order
                $this->log_debug( "No abandoned cart found for hash {$cart_hash}, syncing as new order" );
                return $this->sync_order( $order->get_id(), 'abandoned_cart_conversion' );
            }
            
            // Update contact with order data
            $contact_result = $this->api->create_or_update_contact( 
                $order->get_billing_email(), 
                $this->prepare_contact_data_from_order( $order )
            );
            
            if ( ! $contact_result['success'] ) {
                return array(
                    'success' => false,
                    'message' => 'Failed to update contact: ' . $contact_result['message']
                );
            }
            
            // Prepare updated deal data for completed order
            $deal_data = $this->prepare_completed_order_deal_data( $order, $cart_hash );
            
            // Update the existing deal
            $deal_result = $this->api->update_deal( $existing_deal['id'], $deal_data );
            
            if ( ! $deal_result['success'] ) {
                return array(
                    'success' => false,
                    'message' => 'Failed to convert deal: ' . $deal_result['message']
                );
            }
            
            $this->log_debug( "Successfully converted abandoned cart to order. Deal ID: {$existing_deal['id']}" );
            
            return array(
                'success' => true,
                'deal_id' => $existing_deal['id'],
                'contact_id' => $contact_result['contact_id'],
                'converted' => true
            );
            
        } catch ( Exception $e ) {
            $this->log_error( "Exception in convert_abandoned_cart_to_order: " . $e->getMessage() );
            return array(
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Search for deal by cart hash
     */
    private function search_deal_by_cart_hash( $cart_hash ) {
        $search_result = $this->api->search_deals( array(
            'filters' => array(
                array(
                    'propertyName' => 'hubspot_cart_hash',
                    'operator' => 'EQ',
                    'value' => $cart_hash
                )
            ),
            'properties' => array( 'id', 'dealname', 'dealstage', 'hubspot_cart_hash' )
        ));
        
        if ( $search_result['success'] && ! empty( $search_result['deals'] ) ) {
            return $search_result['deals'][0]; // Return first match
        }
        
        return null;
    }
    
    /**
     * Prepare contact data from cart data
     */
    private function prepare_contact_data( $cart_data ) {
        $contact_data = array(
            'email' => $cart_data['email'],
            'firstname' => $cart_data['first_name'] ?? '',
            'lastname' => $cart_data['last_name'] ?? '',
            'phone' => $cart_data['phone'] ?? '',
            'company' => $cart_data['company'] ?? ''
        );
        
        // Add address data
        if ( ! empty( $cart_data['checkout_data'] ) ) {
            $checkout_data = $cart_data['checkout_data'];
            
            // Billing address
            $contact_data['address'] = implode( ', ', array_filter( array(
                $checkout_data['billing_address_1'] ?? '',
                $checkout_data['billing_address_2'] ?? '',
                $checkout_data['billing_city'] ?? '',
                $checkout_data['billing_state'] ?? '',
                $checkout_data['billing_postcode'] ?? '',
                $checkout_data['billing_country'] ?? ''
            )));
            
            $contact_data['city'] = $checkout_data['billing_city'] ?? '';
            $contact_data['state'] = $checkout_data['billing_state'] ?? '';
            $contact_data['zip'] = $checkout_data['billing_postcode'] ?? '';
            $contact_data['country'] = $checkout_data['billing_country'] ?? '';
        }
        
        return $contact_data;
    }
    
    /**
     * Prepare contact data from order
     */
    private function prepare_contact_data_from_order( $order ) {
        return array(
            'email' => $order->get_billing_email(),
            'firstname' => $order->get_billing_first_name(),
            'lastname' => $order->get_billing_last_name(),
            'phone' => $order->get_billing_phone(),
            'company' => $order->get_billing_company(),
            'address' => implode( ', ', array_filter( array(
                $order->get_billing_address_1(),
                $order->get_billing_address_2(),
                $order->get_billing_city(),
                $order->get_billing_state(),
                $order->get_billing_postcode(),
                $order->get_billing_country()
            ))),
            'city' => $order->get_billing_city(),
            'state' => $order->get_billing_state(),
            'zip' => $order->get_billing_postcode(),
            'country' => $order->get_billing_country()
        );
    }
    
    /**
     * Prepare abandoned cart deal data
     */
    private function prepare_abandoned_cart_deal_data( $cart_data ) {
        $settings = $this->settings;
        $site_prefix = $settings['site_prefix'] ?? '';
        
        $deal_data = array(
            'dealname' => $site_prefix . ' cart #' . substr( $cart_data['cart_hash'], 0, 8 ),
            'amount' => $cart_data['cart_total'],
            'pipeline' => $settings['deal_pipeline'] ?? '',
            'dealstage' => $settings['deal_stages']['abandoned'] ?? '',
            'hubspot_cart_hash' => $cart_data['cart_hash'],
            'woocommerce_order_id' => '', // Empty for abandoned carts
            'order_source' => 'abandoned_cart'
        );
        
        // Add tax and discount information
        if ( isset( $cart_data['cart_tax'] ) ) {
            $deal_data['tax_amount'] = $cart_data['cart_tax'];
        }
        
        if ( isset( $cart_data['discount_total'] ) && $cart_data['discount_total'] > 0 ) {
            $deal_data['discount_amount'] = $cart_data['discount_total'];
        }
        
        // Add coupon codes
        if ( ! empty( $cart_data['applied_coupons'] ) ) {
            $deal_data['coupon_codes'] = implode( ', ', $cart_data['applied_coupons'] );
        }
        
        // Add cart items information
        if ( ! empty( $cart_data['cart_items'] ) ) {
            $product_names = array();
            foreach ( $cart_data['cart_items'] as $cart_item ) {
                $product_names[] = $cart_item['data']->get_name() . ' (x' . $cart_item['quantity'] . ')';
            }
            $deal_data['products'] = implode( '; ', $product_names );
        }
        
        return $deal_data;
    }
    
    /**
     * Prepare completed order deal data
     */
    private function prepare_completed_order_deal_data( $order, $cart_hash ) {
        $settings = $this->settings;
        $site_prefix = $settings['site_prefix'] ?? '';
        
        $deal_data = array(
            'dealname' => $site_prefix . ' order #' . $order->get_order_number(),
            'amount' => $order->get_total(),
            'pipeline' => $settings['deal_pipeline'] ?? '',
            'dealstage' => $this->get_deal_stage_for_order_status( $order->get_status() ),
            'hubspot_cart_hash' => $cart_hash, // Keep cart hash for tracking
            'woocommerce_order_id' => $order->get_id(),
            'order_source' => 'converted_from_abandoned_cart',
            'order_status' => $order->get_status(),
            'order_date' => $order->get_date_created()->format( 'Y-m-d H:i:s' ),
            'order_total' => $order->get_total(),
            'tax_amount' => $order->get_total_tax()
        );
        
        // Add discount information
        if ( $order->get_total_discount() > 0 ) {
            $deal_data['discount_amount'] = $order->get_total_discount();
        }
        
        // Add coupon codes
        $coupons = $order->get_coupon_codes();
        if ( ! empty( $coupons ) ) {
            $deal_data['coupon_codes'] = implode( ', ', $coupons );
        }
        
        return $deal_data;
    }
    
    /**
     * Get deal stage for order status
     */
    private function get_deal_stage_for_order_status( $status ) {
        $stages = $this->settings['deal_stages'] ?? array();
        
        switch ( $status ) {
            case 'completed':
                return $stages['won'] ?? '';
            case 'processing':
                return $stages['processing'] ?? $stages['won'] ?? '';
            case 'cancelled':
                return $stages['cancelled'] ?? '';
            case 'refunded':
                return $stages['refunded'] ?? '';
            case 'failed':
                return $stages['failed'] ?? '';
            default:
                return $stages['processing'] ?? '';
        }
    }
}