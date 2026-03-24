<?php
/**
 * AfterPay Integration Test
 * 
 * Test file to verify that AfterPay orders sync correctly to HubSpot
 * when payment status changes occur.
 * 
 * Usage: Navigate to this file in your browser to test AfterPay order syncing
 */

// Load WordPress
require_once( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php' );

// Only run if user is admin
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Unauthorized access' );
}

echo '<h1>AfterPay → HubSpot Integration Test</h1>';

// Check if plugins are active
$hubspot_active = class_exists( 'HubSpot_Sync_Milli' );
$wc_active = class_exists( 'WooCommerce' );
$afterpay_active = is_plugin_active( 'afterpay-gateway-for-woocommerce/afterpay-gateway-for-woocommerce.php' );

echo '<h2>Plugin Status</h2>';
echo '<ul>';
echo '<li>WooCommerce: ' . ( $wc_active ? '✅ Active' : '❌ Not Active' ) . '</li>';
echo '<li>HubSpot Sync Milli: ' . ( $hubspot_active ? '✅ Active' : '❌ Not Active' ) . '</li>';
echo '<li>AfterPay Gateway: ' . ( $afterpay_active ? '✅ Active' : '❌ Not Active' ) . '</li>';
echo '</ul>';

if ( ! $wc_active || ! $hubspot_active ) {
    echo '<p><strong>Error:</strong> Both WooCommerce and HubSpot Sync Milli plugins must be active.</p>';
    exit;
}

// Test parameters
$test_order_id = isset( $_GET['order_id'] ) ? intval( $_GET['order_id'] ) : 0;
$test_status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';

if ( $test_order_id && $test_status && isset( $_GET['test'] ) ) {
    echo '<h2>Running AfterPay Status Test</h2>';
    echo '<p>Testing with Order ID: ' . $test_order_id . ', Status Change: ' . $test_status . '</p>';
    
    // Verify order exists
    $order = wc_get_order( $test_order_id );
    if ( ! $order ) {
        echo '<p><strong>Error:</strong> Order ' . $test_order_id . ' not found.</p>';
        exit;
    }
    
    echo '<p>✅ Order found: ' . $order->get_order_number() . '</p>';
    echo '<p>Current status: ' . $order->get_status() . '</p>';
    
    // Simulate AfterPay status change
    echo '<p>🔄 Simulating AfterPay status change...</p>';
    
    $old_status = $order->get_status();
    $order->update_status( $test_status, 'AfterPay test status change' );
    
    echo '<p>✅ Status updated from "' . $old_status . '" to "' . $test_status . '"</p>';
    echo '<p>📝 Check your error logs for HubSpot Sync Milli messages</p>';
    
} else {
    echo '<h2>AfterPay Status Configuration</h2>';
    
    // Show current sync settings
    $settings = get_option( 'hubspot_sync_milli_settings', array() );
    $sync_statuses = $settings['sync_on_status_change'] ?? array( 'processing', 'completed' );
    
    echo '<h3>Current Sync Triggers</h3>';
    echo '<p>These order statuses will trigger HubSpot sync:</p>';
    echo '<ul>';
    foreach ( $sync_statuses as $status ) {
        echo '<li><strong>' . esc_html( $status ) . '</strong></li>';
    }
    echo '</ul>';
    
    // Check if AfterPay statuses are included
    $afterpay_statuses = array( 'pending-payment', 'on-hold' );
    $missing_statuses = array_diff( $afterpay_statuses, $sync_statuses );
    
    if ( empty( $missing_statuses ) ) {
        echo '<p style="color: green;">✅ <strong>AfterPay statuses are configured for sync!</strong></p>';
    } else {
        echo '<p style="color: orange;">⚠️ <strong>Missing AfterPay status triggers:</strong> ' . implode( ', ', $missing_statuses ) . '</p>';
        echo '<p>Go to <strong>Settings → HubSpot Sync</strong> and enable these statuses for sync.</p>';
    }
    
    echo '<h3>Recent Orders with Payment Gateways</h3>';
    $recent_orders = wc_get_orders( array(
        'limit' => 10,
        'orderby' => 'date',
        'order' => 'DESC',
        'status' => array( 'wc-pending', 'wc-on-hold', 'wc-processing', 'wc-completed' )
    ) );
    
    if ( empty( $recent_orders ) ) {
        echo '<p>No recent orders found.</p>';
    } else {
        echo '<table border="1" cellpadding="5">';
        echo '<tr><th>Order ID</th><th>Status</th><th>Payment Method</th><th>Date</th><th>Test Link</th></tr>';
        
        foreach ( $recent_orders as $order ) {
            $payment_method = $order->get_payment_method_title();
            $is_afterpay = ( strpos( strtolower( $payment_method ), 'afterpay' ) !== false );
            
            $test_url = add_query_arg( array(
                'test' => '1',
                'order_id' => $order->get_id(),
                'status' => 'processing'
            ) );
            
            echo '<tr' . ( $is_afterpay ? ' style="background-color: #ffffcc;"' : '' ) . '>';
            echo '<td>' . $order->get_id() . '</td>';
            echo '<td>' . $order->get_status() . '</td>';
            echo '<td>' . ( $payment_method ?: 'Unknown' ) . ( $is_afterpay ? ' ⭐' : '' ) . '</td>';
            echo '<td>' . $order->get_date_created()->format( 'Y-m-d H:i' ) . '</td>';
            echo '<td><a href="' . esc_url( $test_url ) . '">Test Status Change</a></td>';
            echo '</tr>';
        }
        
        echo '</table>';
        echo '<p><em>AfterPay orders highlighted with ⭐</em></p>';
    }
    
    echo '<h3>Manual Test</h3>';
    $manual_url = add_query_arg( array(
        'test' => '1',
        'order_id' => 'YOUR_ORDER_ID',
        'status' => 'pending-payment'
    ) );
    echo '<p>Manual test URL format: <code>' . esc_url( $manual_url ) . '</code></p>';
    echo '<p>Replace YOUR_ORDER_ID with an actual order ID.</p>';
}

echo '<h2>AfterPay Integration Notes</h2>';
echo '<ul>';
echo '<li><strong>Common AfterPay Flow:</strong> pending → on-hold → processing → completed</li>';
echo '<li><strong>Sync Points:</strong> Each status change should now trigger HubSpot sync</li>';
echo '<li><strong>Debug Logging:</strong> Enable in HubSpot Sync settings to see sync activity</li>';
echo '</ul>';

echo '<h2>Troubleshooting</h2>';
echo '<ol>';
echo '<li>Verify AfterPay status triggers are enabled in <strong>Settings → HubSpot Sync</strong></li>';
echo '<li>Enable debug logging to see sync activity</li>';
echo '<li>Check order notes for HubSpot sync status</li>';
echo '<li>Test with a real AfterPay order to verify flow</li>';
echo '</ol>';

echo '<hr>';
echo '<p><strong>Integration Status:</strong> AfterPay compatibility has been added to the sync system!</p>';
?>