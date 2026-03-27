---
layout: page
title: Developer Reference
nav_order: 5
---

# Complete Hook and Trigger Mapping

This document provides a comprehensive mapping of ALL hooks, triggers, and execution points in the HubSpot Sync - Milli plugin.

## WordPress Hooks (Execution Order)

### **Plugin Initialization** (init phase)
```php
// Priority 10 - Main plugin initialization
add_action('init', array($this, 'init_hooks'), 10);
  ↳ Loads abandoned cart tracker
  ↳ File: class-hubspot-sync-milli.php:62

// Load dependencies
add_action('plugins_loaded', 'hubspot_sync_milli_init');
  ↳ File: hubspot-sync-milli.php:61
```

### **Frontend Hooks** (checkout workflow)
```php
// Load frontend tracking scripts
add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
  ↳ Priority: 10
  ↳ File: class-abandoned-cart-tracker.php:51
  ↳ Context: is_checkout() pages only

// Render custom checkout fields
add_action('woocommerce_after_order_notes', array($this, 'add_checkout_fields'));
  ↳ Priority: 10
  ↳ File: class-hubspot-sync-milli.php:71
  ↳ Output: Clinician, source, provider fields
```

### **AJAX Endpoints** (real-time tracking)
```php
// Track abandoned cart (guests allowed)
add_action('wp_ajax_nopriv_hubspot_sync_milli_track_checkout', array($this, 'track_checkout_data'));
add_action('wp_ajax_hubspot_sync_milli_track_checkout', array($this, 'track_checkout_data'));
  ↳ File: class-abandoned-cart-tracker.php:25-26
  ↳ Trigger: Frontend field changes (debounced 1000ms)
  ↳ Security: Nonce verification required

// Test HubSpot connection (admin only)
add_action('wp_ajax_hubspot_sync_milli_test_connection', array($this, 'test_connection'));
  ↳ File: class-hubspot-sync-milli.php:88
  ↳ Context: Admin settings page
```

### **WooCommerce Order Hooks** (order lifecycle)
```php
// Save custom checkout fields to order
add_action('woocommerce_checkout_update_order_meta', array($this, 'save_checkout_fields'));
  ↳ Priority: 10
  ↳ File: class-hubspot-sync-milli.php:72
  ↳ Data: Clinician name, acquisition source, etc.

// Schedule background sync after order creation  
add_action('woocommerce_checkout_order_processed', array($this, 'schedule_order_sync'));
  ↳ Priority: 10
  ↳ File: class-hubspot-sync-milli.php:74
  ↳ Delay: 60 seconds (non-blocking)

// Handle abandoned cart conversion
add_action('woocommerce_payment_complete', array($this, 'handle_cart_to_order_transition'), 5);
add_action('woocommerce_order_status_processing', array($this, 'handle_cart_to_order_transition'), 5);
  ↳ Priority: 5 (HIGH - before other processing)
  ↳ File: class-abandoned-cart-tracker.php:29-30
  ↳ Purpose: Convert abandoned cart deal to completed order

// Monitor all order status changes
add_action('woocommerce_order_status_changed', array($this, 'handle_order_status_change'), 10, 4);
  ↳ Priority: 10
  ↳ File: class-hubspot-sync-milli.php:76
  ↳ Trigger: Any status change (pending → processing → completed, etc.)

// Monitor order meta updates for ShipHero integration
add_action('updated_post_meta', array($this, 'on_order_meta_updated'), 10, 4);
  ↳ Priority: 10
  ↳ File: class-hubspot-sync-milli.php:80
  ↳ Purpose: Detect ShipHero serial number additions (non-destructive)
  ↳ Meta Key: 'serial_numbers' on 'shop_order' posts
  ↳ Trigger: Automatic device creation when ShipHero processes fulfillment
```

### **Background Processing Hooks** (async operations)
```php
// Execute scheduled sync (cron)
add_action('hubspot_sync_milli_cron', array($this, 'process_scheduled_sync'));
  ↳ File: class-hubspot-sync-milli.php:79
  ↳ Trigger: wp_schedule_single_event (60s delay)
  ↳ Purpose: Non-blocking HubSpot sync

// Device serial number processing
add_action('hubspot_sync_milli_process_serial_number', array($this, 'process_device_sync'), 10, 2);
  ↳ File: class-sync-manager.php:617 (inferred)
  ↳ Data: order_id, serial_number
  ↳ Purpose: Create HubSpot device objects
```

### **Admin Interface Hooks** (admin functionality)
```php
// Admin menu and settings
add_action('admin_menu', array($this, 'add_admin_menu'));
  ↳ File: class-hubspot-sync-milli.php:84
  ↳ Page: Settings → HubSpot Sync

add_action('admin_init', array($this, 'register_settings'));
  ↳ File: class-hubspot-sync-milli.php:86
  ↳ Purpose: Register settings and handle redirects

// Admin order page enhancements
add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_order_sync_status'));
  ↳ File: class-sync-manager.php:306
  ↳ Output: HubSpot sync status, manual sync buttons

// Bulk order actions
add_filter('bulk_actions-edit-shop_order', array($this, 'add_bulk_order_actions'));
  ↳ File: class-sync-manager.php:290
  ↳ Actions: Sync selected orders to HubSpot
```

## JavaScript Event Listeners (Frontend)

### **Form Field Monitoring** (21 fields tracked)
```javascript
// Individual field change events
$('#billing_email, #billing_first_name, ...').on('change blur', function() {
    // Debounced AJAX call (1000ms)
});

// Shipping checkbox
$('#ship-to-different-address-checkbox').on('change', function() {
    // Immediate tracking
});

// WooCommerce checkout updates
$(document.body).on('updated_checkout', function() {
    // Re-initialize tracking after dynamic updates
});
```

**Monitored Fields:**
- **Billing:** email, first_name, last_name, company, address_1, address_2, city, state, postcode, country, phone
- **Shipping:** first_name, last_name, company, address_1, address_2, city, state, postcode, country, phone
- **Options:** ship-to-different-address checkbox

## REST API Endpoints

### **External Integration Endpoints**
```php
// Serial number assignment (for external systems)
register_rest_route('hubspot-sync-milli/v1', '/serial-number', [
    'methods' => 'POST',
    'callback' => 'handle_serial_number_assignment',
    'permission_callback' => function() {
        return current_user_can('manage_woocommerce');
    }
]);
```

**Example Usage:**
```bash
POST /wp-json/hubspot-sync-milli/v1/serial-number
{
    "order_id": 12345,
    "serial_number": "SN123456789"
}
```

### **ShipHero Integration (Automatic Monitoring)**
Unlike REST API endpoints, ShipHero integration uses WordPress post meta monitoring:

```php
// Automatic detection of existing ShipHero workflow
add_action('updated_post_meta', array($this, 'on_order_meta_updated'), 10, 4);

// Integration flow:
// 1. ShipHero webhook → api-shiphero.php (existing, unchanged)
// 2. $order->update_meta_data('serial_numbers', $serial) (existing)  
// 3. WordPress updated_post_meta action fires (automatic)
// 4. Plugin detects serial_numbers meta change (new monitoring)
// 5. do_action('hubspot_sync_milli_process_serial_number') (existing system)

public function on_order_meta_updated($meta_id, $post_id, $meta_key, $meta_value) {
    if ($meta_key === 'serial_numbers' && get_post_type($post_id) === 'shop_order') {
        // Parse comma-separated serials and trigger device creation
        foreach (explode(',', $meta_value) as $serial_number) {
            do_action('hubspot_sync_milli_process_serial_number', $post_id, trim($serial_number));
        }
    }
}
```

**Benefits:**
- ✅ Non-destructive integration (no changes to working ShipHero code)
- ✅ Automatic detection of any serial number source
- ✅ Uses existing HubSpot device creation infrastructure
- ✅ WordPress native hook system for reliability

## Data Flow Sequence

### **Complete Customer Journey Timeline**
```
TIME    EVENT                           HOOK/TRIGGER                         ACTION
------------------------------------------------------------------------------------
0:00    Customer loads checkout         wp_enqueue_scripts                  Load tracking JS
0:00    Checkout fields rendered        woocommerce_after_order_notes       Custom fields
0:05    Customer types email           jQuery('change')                     Debounced AJAX
0:06    Ajax fires (debounced)         wp_ajax_nopriv_..._track_checkout   Create abandoned cart
0:06    HubSpot deal created           sync_abandoned_cart()                Deal in HubSpot
1:30    Customer makes changes         jQuery('change')                     Update same deal
5:00    Customer completes order       woocommerce_checkout_order_processed Order created
5:00    Payment completes              woocommerce_payment_complete         Convert cart→order
5:00    Background sync scheduled      wp_schedule_single_event             60s delay
6:00    Background sync runs           hubspot_sync_milli_cron              Update HubSpot
24hrs   External system ships product   REST API call                        Add serial number
24hrs   ShipHero ships product         api-shiphero.php webhook             Auto-detect serial
24hrs   Device synced                  hubspot_sync_milli_process_serial    HubSpot device
```

## Hook Priorities (Execution Order)

### **Critical Order Dependencies**
```php
Priority 5:  woocommerce_payment_complete → handle_cart_to_order_transition
             ↳ MUST run before other payment processing to capture cart hash

Priority 10: woocommerce_checkout_order_processed → schedule_order_sync
             ↳ Standard priority for order creation

Priority 10: woocommerce_checkout_update_order_meta → save_checkout_fields
             ↳ Save custom fields to order meta

Priority 10: woocommerce_order_status_changed → handle_order_status_change
             ↳ Monitor status changes for re-sync
```

## Rate Limiting and Performance

### **API Call Throttling**
```php
// Prevent HubSpot rate limit issues
usleep(110000); // 110ms delay between API calls

// Background processing
wp_schedule_single_event(time() + 60); // 60-second delay for non-blocking
```

### **Debouncing (Frontend)**
```javascript
// Prevent AJAX spam
clearTimeout(this.trackingTimeout);
this.trackingTimeout = setTimeout(function() {
    self.trackCheckoutData();
}, 1000); // 1-second debounce
```

## Error Handling and Logging

### **Debug Logging Points**
```php
// Cart hash generation
error_log("[HubSpot Sync] Generated cart hash: {$hash} for email: {$email}");

// Abandoned cart sync
error_log("[HubSpot Sync] Abandoned cart synced. Deal ID: {$deal_id}");

// Cart conversion  
error_log("[HubSpot Sync] Converting cart {$hash} to order {$order_id}");

// Device assignment
error_log("[HubSpot Sync] Device {$serial} created with ID: {$device_id}");
```

## Security Considerations

### **Authentication & Authorization**
- **AJAX calls:** Nonce verification (`wp_create_nonce`)
- **REST API:** `current_user_can('manage_woocommerce')`
- **Admin functions:** `current_user_can('manage_options')`
- **Data sanitization:** `sanitize_text_field`, `sanitize_email`

### **Data Protection**
- **Session storage:** WooCommerce session (secure)
- **Order meta:** WordPress meta tables (protected)
- **API tokens:** WordPress options (encrypted recommended)

This comprehensive mapping provides complete traceability of every trigger point and data flow in the system.