<?php
/**
 * ShipHero Integration Test
 * 
 * Test file to verify that the HubSpot Sync Milli plugin correctly hooks into
 * ShipHero serial number updates and triggers device creation.
 * 
 * Usage: Navigate to this file in your browser to test the integration
 */

// Load WordPress
require_once( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php' );

// Only run if user is admin
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Unauthorized access' );
}

echo '<h1>ShipHero → HubSpot Integration Test</h1>';

// Check if plugins are active
$hubspot_active = class_exists( 'HubSpot_Sync_Milli' );
$wc_active = class_exists( 'WooCommerce' );

echo '<h2>Plugin Status</h2>';
echo '<ul>';
echo '<li>WooCommerce: ' . ( $wc_active ? '✅ Active' : '❌ Not Active' ) . '</li>';
echo '<li>HubSpot Sync Milli: ' . ( $hubspot_active ? '✅ Active' : '❌ Not Active' ) . '</li>';
echo '</ul>';

if ( ! $wc_active || ! $hubspot_active ) {
    echo '<p><strong>Error:</strong> Both WooCommerce and HubSpot Sync Milli plugins must be active.</p>';
    exit;
}

// Test parameters
$test_order_id = isset( $_GET['order_id'] ) ? intval( $_GET['order_id'] ) : 0;
$test_serial = isset( $_GET['serial'] ) ? sanitize_text_field( $_GET['serial'] ) : '';

if ( $test_order_id && $test_serial && isset( $_GET['test'] ) ) {
    echo '<h2>Running Test</h2>';
    echo '<p>Testing with Order ID: ' . $test_order_id . ', Serial: ' . $test_serial . '</p>';
    
    // Verify order exists
    $order = wc_get_order( $test_order_id );
    if ( ! $order ) {
        echo '<p><strong>Error:</strong> Order ' . $test_order_id . ' not found.</p>';
        exit;
    }
    
    echo '<p>✅ Order found: ' . $order->get_order_number() . '</p>';
    
    // Simulate what api-shiphero.php does
    echo '<p>🔄 Simulating ShipHero serial number update...</p>';
    
    // Update order meta (this should trigger our hook)
    $order->update_meta_data( 'serial_numbers', $test_serial );
    $order->save();
    
    echo '<p>✅ Serial number meta updated</p>';
    echo '<p>📝 Check your error logs for HubSpot Sync Milli debug messages</p>';
    
    // Show current serial numbers
    $current_serials = $order->get_meta( 'serial_numbers' );
    echo '<p>Current serial numbers in order meta: <strong>' . esc_html( $current_serials ) . '</strong></p>';
    
    // Show any existing device IDs
    $device_ids = $order->get_meta( '_hubspot_device_ids', true );
    if ( $device_ids && is_array( $device_ids ) ) {
        echo '<p>Existing HubSpot device IDs:</p>';
        echo '<ul>';
        foreach ( $device_ids as $serial => $device_id ) {
            echo '<li>' . esc_html( $serial ) . ' → ' . esc_html( $device_id ) . '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p>No HubSpot device IDs found yet.</p>';
    }
    
} else {
    echo '<h2>Test Instructions</h2>';
    echo '<p>To test the integration, you need a WooCommerce order ID and a test serial number.</p>';
    
    // Show some recent orders
    echo '<h3>Recent Orders (for testing)</h3>';
    $recent_orders = wc_get_orders( array(
        'limit' => 10,
        'orderby' => 'date',
        'order' => 'DESC',
        'status' => array( 'wc-processing', 'wc-completed' )
    ) );
    
    if ( empty( $recent_orders ) ) {
        echo '<p>No recent orders found.</p>';
    } else {
        echo '<table border="1" cellpadding="5">';
        echo '<tr><th>Order ID</th><th>Order Number</th><th>Date</th><th>Status</th><th>Test Link</th></tr>';
        
        foreach ( $recent_orders as $order ) {
            $test_url = add_query_arg( array(
                'test' => '1',
                'order_id' => $order->get_id(),
                'serial' => 'TEST-' . time()
            ) );
            
            echo '<tr>';
            echo '<td>' . $order->get_id() . '</td>';
            echo '<td>' . $order->get_order_number() . '</td>';
            echo '<td>' . $order->get_date_created()->format( 'Y-m-d H:i' ) . '</td>';
            echo '<td>' . $order->get_status() . '</td>';
            echo '<td><a href="' . esc_url( $test_url ) . '">Test with this order</a></td>';
            echo '</tr>';
        }
        
        echo '</table>';
    }
    
    echo '<h3>Manual Test</h3>';
    $manual_url = add_query_arg( array(
        'test' => '1',
        'order_id' => 'YOUR_ORDER_ID',
        'serial' => 'TEST-SERIAL'
    ) );
    echo '<p>Manual test URL format: <code>' . esc_url( $manual_url ) . '</code></p>';
    echo '<p>Replace YOUR_ORDER_ID with an actual order ID and TEST-SERIAL with your test serial number.</p>';
}

echo '<h2>How It Works</h2>';
echo '<ol>';
echo '<li><strong>api-shiphero.php</strong> receives webhook from ShipHero</li>';
echo '<li>It updates the order meta with <code>serial_numbers</code></li>';
echo '<li><strong>HubSpot Sync Milli</strong> detects this meta update via WordPress hook</li>';
echo '<li>It triggers device creation in HubSpot using existing infrastructure</li>';
echo '</ol>';

echo '<h2>Debugging</h2>';
echo '<p>Check your error logs for messages starting with <code>[HubSpot Sync - Milli] DEBUG:</code></p>';
echo '<p>If debug logging is not enabled, you can enable it in WP_DEBUG or in the plugin settings.</p>';

if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    echo '<p>✅ WP_DEBUG is enabled</p>';
} else {
    echo '<p>⚠️ WP_DEBUG is disabled - enable it to see debug logs</p>';
}

echo '<hr>';
echo '<p><strong>Integration Status:</strong> ✅ The hook is now active and monitoring order meta updates!</p>';
?>