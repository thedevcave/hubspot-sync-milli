<?php
/**
 * Example: How to add serial numbers to WooCommerce orders
 * 
 * This example shows how external systems can
 * programmatically add serial numbers to WooCommerce orders.
 * 
 * @package HubSpot_Sync_Milli
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Example 1: Add a single serial number to an order
 */
function example_add_single_serial_number() {
    $order_id = 12345; // WooCommerce order ID
    $serial_number = 'SN123456789'; // Device serial number
    
    // Add the serial number to the order
    $success = HubSpot_Sync_Milli_Serial_Number_Manager::add_serial_number( 
        $order_id, 
        $serial_number 
    );
    
    if ( $success ) {
        error_log( "Serial number {$serial_number} added to order {$order_id}" );
    } else {
        error_log( "Failed to add serial number {$serial_number} to order {$order_id}" );
    }
}

/**
 * Example 2: Process serial numbers from a CSV export from external fulfillment systems
 */
function example_process_external_export() {
    // Example CSV data from external fulfillment system
    $csv_data = array(
        array(
            'Order ID' => '12345',
            'Product Serial Number Shipped' => 'SN123456789'
        ),
        array(
            'Order ID' => '12346',
            'Product Serial Number Shipped' => 'SN987654321'
        ),
        // ... more rows
    );
    
    // Batch process all serial numbers
    $result = HubSpot_Sync_Milli_Serial_Number_Manager::batch_add_serial_numbers( $csv_data );
    
    error_log( "Batch processing result: " . print_r( $result, true ) );
    
    // Example result:
    // array(
    //     'success' => true,
    //     'message' => 'Processed 2 of 2 serial numbers',
    //     'processed' => 2,
    //     'errors' => array()
    // )
}

/**
 * Example 3: Use in a WordPress action/hook
 */
function example_process_serial_numbers_on_webhook() {
    // This could be triggered by a webhook from external fulfillment system
    add_action( 'wp_ajax_nopriv_external_serial_update', function() {
        // Get POST data
        $order_id = $_POST['order_id'] ?? '';
        $serial_number = $_POST['serial_number'] ?? '';
        
        if ( empty( $order_id ) || empty( $serial_number ) ) {
            wp_die( json_encode( array( 'success' => false, 'message' => 'Missing data' ) ) );
        }
        
        $success = HubSpot_Sync_Milli_Serial_Number_Manager::add_serial_number( 
            $order_id, 
            $serial_number 
        );
        
        wp_die( json_encode( array( 'success' => $success ) ) );
    });
}

/**
 * Example 4: Check if an order has serial numbers
 */
function example_check_order_serial_numbers() {
    $order_id = 12345;
    
    // Check if order has any serial numbers
    $has_serials = HubSpot_Sync_Milli_Serial_Number_Manager::order_has_serial_numbers( $order_id );
    
    if ( $has_serials ) {
        // Get the actual serial numbers
        $serial_numbers = HubSpot_Sync_Milli_Serial_Number_Manager::get_order_serial_numbers( $order_id );
        error_log( "Order {$order_id} has serial numbers: " . implode( ', ', $serial_numbers ) );
    } else {
        error_log( "Order {$order_id} has no serial numbers" );
    }
}

/**
 * Example 5: WordPress REST API endpoint for external systems
 */
function example_register_rest_endpoint() {
    add_action( 'rest_api_init', function() {
        register_rest_route( 'hubspot-sync-milli/v1', '/serial-number', array(
            'methods' => 'POST',
            'callback' => function( $request ) {
                $order_id = $request->get_param( 'order_id' );
                $serial_number = $request->get_param( 'serial_number' );
                
                if ( empty( $order_id ) || empty( $serial_number ) ) {
                    return new WP_Error( 'missing_data', 'Order ID and serial number are required', array( 'status' => 400 ) );
                }
                
                $success = HubSpot_Sync_Milli_Serial_Number_Manager::add_serial_number( 
                    $order_id, 
                    $serial_number 
                );
                
                return array(
                    'success' => $success,
                    'order_id' => $order_id,
                    'serial_number' => $serial_number
                );
            },
            'permission_callback' => function() {
                // Add your authentication logic here
                return current_user_can( 'manage_woocommerce' );
            }
        ));
    });
}