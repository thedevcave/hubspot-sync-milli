<?php
/**
 * Serial Number Manager
 * Utility class for external systems to add serial numbers to WooCommerce orders
 *
 * @package HubSpot_Sync_Milli
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HubSpot_Sync_Milli_Serial_Number_Manager {
    
    /**
     * Add serial number to an order
     * 
     * @param int $order_id WooCommerce order ID
     * @param string $serial_number The device serial number
     * @param string $product_name Optional product name to match (defaults to Milli Vaginal Dilator)
     * @return bool Success status
     */
    public static function add_serial_number( $order_id, $serial_number, $product_name = 'Milli Vaginal Dilator' ) {
        // Validate inputs
        if ( empty( $order_id ) || empty( $serial_number ) ) {
            error_log( '[HubSpot Sync - Milli] ERROR: Invalid order ID or serial number provided' );
            return false;
        }
        
        // Get the order
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            error_log( "[HubSpot Sync - Milli] ERROR: Order {$order_id} not found" );
            return false;
        }
        
        error_log( "[HubSpot Sync - Milli] DEBUG: Adding serial number {$serial_number} to order {$order_id}" );
        
        // Process order items to find matching product
        $item_found = false;
        foreach ( $order->get_items() as $item_id => $item ) {
            $item_product_name = $item->get_name();
            
            // Check if this item matches the target product
            if ( stripos( $item_product_name, $product_name ) !== false ) {
                // Add serial number to item meta
                $item->add_meta_data( 'Serial number', $serial_number );
                $item->save();
                
                error_log( "[HubSpot Sync - Milli] DEBUG: Added serial number {$serial_number} to item {$item_id}" );
                $item_found = true;
                break;
            }
        }
        
        if ( ! $item_found ) {
            error_log( "[HubSpot Sync - Milli] ERROR: Product '{$product_name}' not found in order {$order_id}" );
            return false;
        }
        
        // Update order meta with serial numbers
        $existing_serial_numbers = $order->get_meta( 'serial_numbers' );
        $serial_numbers_array = $existing_serial_numbers ? explode( ',', $existing_serial_numbers ) : array();
        
        // Add new serial number if not already present
        if ( $serial_number !== 'N/A' && ! in_array( $serial_number, $serial_numbers_array ) ) {
            $serial_numbers_array[] = $serial_number;
        }
        
        // Update order meta
        $order->update_meta_data( 'serial_numbers', implode( ',', $serial_numbers_array ) );
        $order->save();
        
        error_log( "[HubSpot Sync - Milli] DEBUG: Updated order {$order_id} meta with serial numbers: " . implode( ',', $serial_numbers_array ) );
        
        // Trigger HubSpot sync for this serial number
        do_action( 'hubspot_sync_milli_process_serial_number', $order_id, $serial_number );
        
        return true;
    }
    
    /**
     * Batch add serial numbers from an array
     * 
     * @param array $serial_data Array of arrays with 'Order ID' and 'Product Serial Number Shipped' keys
     * @return array Results with success count and errors
     */
    public static function batch_add_serial_numbers( $serial_data ) {
        if ( ! is_array( $serial_data ) || empty( $serial_data ) ) {
            return array(
                'success' => false,
                'message' => 'Invalid serial data provided',
                'processed' => 0,
                'errors' => array()
            );
        }
        
        $processed_count = 0;
        $errors = array();
        
        foreach ( $serial_data as $data ) {
            $order_id = $data['Order ID'] ?? '';
            $serial_number = $data['Product Serial Number Shipped'] ?? '';
            
            if ( empty( $order_id ) || empty( $serial_number ) ) {
                $errors[] = "Invalid data: missing Order ID or Serial Number";
                continue;
            }
            
            if ( self::add_serial_number( $order_id, $serial_number ) ) {
                $processed_count++;
            } else {
                $errors[] = "Failed to process serial number {$serial_number} for order {$order_id}";
            }
        }
        
        return array(
            'success' => $processed_count > 0,
            'message' => "Processed {$processed_count} of " . count( $serial_data ) . " serial numbers",
            'processed' => $processed_count,
            'errors' => $errors
        );
    }
    
    /**
     * Get serial numbers for an order
     * 
     * @param int $order_id WooCommerce order ID
     * @return array Serial numbers array
     */
    public static function get_order_serial_numbers( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return array();
        }
        
        $serial_numbers = $order->get_meta( 'serial_numbers' );
        if ( empty( $serial_numbers ) || $serial_numbers === 'N/A' ) {
            return array();
        }
        
        return array_filter( explode( ',', $serial_numbers ) );
    }
    
    /**
     * Check if order has serial numbers
     * 
     * @param int $order_id WooCommerce order ID
     * @return bool
     */
    public static function order_has_serial_numbers( $order_id ) {
        $serial_numbers = self::get_order_serial_numbers( $order_id );
        return ! empty( $serial_numbers );
    }
    
    /**
     * Remove serial number from order
     * 
     * @param int $order_id WooCommerce order ID
     * @param string $serial_number Serial number to remove
     * @return bool Success status
     */
    public static function remove_serial_number( $order_id, $serial_number ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return false;
        }
        
        $existing_serial_numbers = self::get_order_serial_numbers( $order_id );
        $updated_serial_numbers = array_diff( $existing_serial_numbers, array( $serial_number ) );
        
        $order->update_meta_data( 'serial_numbers', empty( $updated_serial_numbers ) ? 'N/A' : implode( ',', $updated_serial_numbers ) );
        $order->save();
        
        return true;
    }
}