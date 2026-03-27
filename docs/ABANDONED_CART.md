# HubSpot Sync - Milli: Enhanced Abandoned Cart Tracking

This document explains the enhanced abandoned cart functionality that tracks cart-to-order transitions and prevents duplicate deals.

## Overview

The enhanced abandoned cart system solved a critical flaw in the original plugin:

### **Previous Behavior** ❌
```
Customer fills checkout → Abandoned cart deal created with hash ABC123
Customer completes order → NEW deal created with different hash XYZ789
Result: Two separate deals for same customer transaction
```

### **Enhanced Behavior** ✅
```
Customer fills checkout → Abandoned cart deal created with hash ABC123
Customer completes order → SAME deal ABC123 updated to completed order
Result: Single deal that accurately tracks the customer journey
```

## Key Features

### 1. **Persistent Cart Hashing**
- Cart hash remains **consistent** throughout the session
- Hash stored in **WooCommerce session** and **order meta**  
- Prevents hash changes that caused duplicate deals

### 2. **Cart-to-Order Transition Detection**
- Automatically detects when abandoned cart becomes completed order
- Updates existing deal instead of creating new one
- Preserves all tracking data and associations

### 3. **Deal Lifecycle Management**
- **Name:** `{site_prefix} cart #abc123...` → `{site_prefix} order #12345`
- **Stage:** `abandoned_cart` → `won/processing/etc`
- **Properties:** Enhanced with order details while preserving cart hash

## Technical Implementation

### Frontend Tracking

**JavaScript** monitors 21 checkout fields and sends real-time updates:

```javascript
// monitored fields: billing_email, billing_first_name, etc.
$('#billing_email').on('change', function() {
    // Debounced AJAX call to track cart data
});
```

**AJAX Endpoint:** `wp_ajax_hubspot_sync_milli_track_checkout`

### Cart Hash Generation

```php
// Generates consistent hash that persists across session
$hash = md5($email . $site_prefix . 'persistent_' . $session_id);

// Store in session for consistency
WC()->session->set('hubspot_cart_hash', $hash);
```

### Deal Search & Update

```php
// Search for existing deal by cart hash
$existing_deal = $api->search_deals([
    'filters' => [
        [
            'propertyName' => 'hubspot_cart_hash',
            'operator' => 'EQ', 
            'value' => $cart_hash
        ]
    ]
]);

// Update existing deal instead of creating new one
if ($existing_deal) {
    $api->update_deal($existing_deal['id'], $completed_order_data);
}
```

## Configuration

### Required Settings

Configure these in **WP Admin → Settings → HubSpot Sync**:

```php
[
    'deal_stages' => [
        'abandoned' => '12345678',     // HubSpot stage ID for abandoned carts
        'won' => '87654321',           // HubSpot stage ID for completed orders
        'processing' => '11111111'     // HubSpot stage ID for processing orders
    ],
    'deal_pipeline' => '98765432',     // HubSpot pipeline ID
    'site_prefix' => 'MyStore'         // Unique site identifier
]
```

### Deal Properties

The enhanced system uses these HubSpot deal properties:

| Property | Purpose | Example Value |
|----------|---------|---------------|
| `hubspot_cart_hash` | **Unique cart identifier** | `abc123def456...` |
| `woocommerce_order_id` | Order ID (empty for abandoned carts) | `12345` |
| `order_source` | Track conversion type | `abandoned_cart` or `converted_from_abandoned_cart` |
| `dealname` | Deal name that changes with status | `MyStore cart #abc123...` → `MyStore order #12345` |
| `dealstage` | Deal stage that updates on conversion | `abandoned_cart` → `won` |

## Workflow Examples

### Example 1: Abandoned Cart Creation

```
1. Customer enters email on checkout
   ↓
2. JavaScript sends AJAX with form data
   ↓  
3. Generate persistent cart hash: abc123...
   ↓
4. Search HubSpot for existing deal with hash abc123...
   ↓
5. Create/update abandoned cart deal:
   - dealname: "MyStore cart #abc123..."
   - dealstage: "abandoned_cart" 
   - hubspot_cart_hash: "abc123..."
   ↓
6. Store hash in WC session for consistency
```

### Example 2: Cart-to-Order Conversion

```
1. Customer completes payment
   ↓
2. WooCommerce fires 'woocommerce_payment_complete'
   ↓
3. Get cart hash from session: abc123...
   ↓
4. Store hash in order meta for future reference
   ↓
5. Search for existing deal with hash abc123...
   ↓
6. Update existing deal:
   - dealname: "MyStore order #12345"
   - dealstage: "won"
   - woocommerce_order_id: "12345"
   - order_source: "converted_from_abandoned_cart"
   ↓
7. Clear session hash (order now complete)
```

## API Integration

### Adding Custom Properties

For external systems that need to track cart data:

```php
// Get cart hash from completed order
$cart_hash = HubSpot_Sync_Milli_Abandoned_Cart_Tracker::get_order_cart_hash($order_id);

// Check if order was converted from abandoned cart
$is_converted = HubSpot_Sync_Milli_Abandoned_Cart_Tracker::is_converted_from_abandoned_cart($order_id);

// Access the persistent hash for tracking
if ($is_converted) {
    echo "This order was converted from abandoned cart: {$cart_hash}";
}
```

### REST API Endpoints

Create custom endpoints for external systems:

```php
// Example: Webhook for external cart tracking
register_rest_route('hubspot-sync-milli/v1', '/cart-event', [
    'methods' => 'POST',
    'callback' => function($request) {
        $email = $request->get_param('email');
        $cart_data = $request->get_param('cart_data');
        
        // Use same tracker system
        $tracker = new HubSpot_Sync_Milli_Abandoned_Cart_Tracker();
        $hash = $tracker->generate_cart_hash($email);
        $tracker->sync_abandoned_cart_to_hubspot($cart_data + ['cart_hash' => $hash]);
    }
]);
```

## Order Meta Storage

Enhanced data storage for tracking:

```php
// Order meta keys added automatically:
get_post_meta($order_id, 'hubspot_cart_hash');     // Original cart hash
get_post_meta($order_id, 'hubspot_deal_id');       // HubSpot deal ID  

// Check conversion status
$cart_hash = get_post_meta($order_id, 'hubspot_cart_hash', true);
$was_abandoned_cart = !empty($cart_hash);
```

## Debugging & Monitoring

### Debug Logging

Enable in **WP Admin → Settings → HubSpot Sync → Advanced**:

```
[HubSpot Sync - Milli] Generated cart hash: abc123... for email: customer@example.com  
[HubSpot Sync - Milli] Abandoned cart synced successfully. Deal ID: 98765432
[HubSpot Sync - Milli] Converting abandoned cart abc123... to completed order 12345
[HubSpot Sync - Milli] Successfully converted abandoned cart to order. Deal ID: 98765432
```

### Monitoring Cart Conversions

Track success in HubSpot using custom reports:

```
Report: "Abandoned Cart Conversions"
Filter: order_source = "converted_from_abandoned_cart"
Metric: Count of deals by conversion status
```

## Troubleshooting

### Common Issues

**Problem**: Duplicate deals still being created
- **Check**: Cart hash consistency in session storage
- **Fix**: Clear WooCommerce sessions: `WC()->session->destroy();`

**Problem**: Abandoned carts not syncing  
- **Check**: JavaScript console for AJAX errors
- **Fix**: Verify nonce and AJAX registration

**Problem**: Cart conversions not working
- **Check**: Order meta storage and session data
- **Fix**: Ensure `woocommerce_payment_complete` hook is firing

### Verification Steps

1. **Test abandoned cart creation**:
   - Fill checkout form → Check HubSpot for new deal
   - Verify deal properties include `hubspot_cart_hash`

2. **Test cart conversion**:
   - Complete same cart → Check deal name/stage changes
   - No new deal should be created

3. **Verify data integrity**:
   - Check order meta contains `hubspot_cart_hash`
   - Ensure deal properties are properly updated

This enhanced system provides complete abandoned cart lifecycle tracking while maintaining data consistency and preventing the duplicate deal problem.