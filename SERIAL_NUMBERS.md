# HubSpot Sync - Milli: Serial Number Integration

This document explains how to integrate serial numbers with the HubSpot Sync - Milli plugin.

## Overview

The plugin now includes comprehensive device serial number functionality that:

1. **Stores serial numbers in WooCommerce** - Updates order item meta and order meta
2. **Creates HubSpot device records** - Creates custom objects in HubSpot for each device
3. **Creates associations** - Links devices to contacts and deals in HubSpot
4. **Provides utility functions** - Easy API for external systems to add serial numbers

## Basic Usage

### Add a Serial Number to an Order

```php
// Example: Add serial number to order
$order_id = 12345;
$serial_number = 'SN123456789';

$success = HubSpot_Sync_Milli_Serial_Number_Manager::add_serial_number(
    $order_id,
    $serial_number
);

if ($success) {
    echo "Serial number added successfully!";
}
```

### Batch Processing from CSV Data

```php
// Example: Process VeraCore CSV export
$csv_data = array(
    array(
        'Order ID' => '12345',
        'Product Serial Number Shipped' => 'SN123456789'
    ),
    array(
        'Order ID' => '12346', 
        'Product Serial Number Shipped' => 'SN987654321'
    )
);

$result = HubSpot_Sync_Milli_Serial_Number_Manager::batch_add_serial_numbers($csv_data);

// Result includes success count and error details
echo $result['message']; // "Processed 2 of 2 serial numbers"
```

## Configuration

### Required HubSpot Settings

Before using device functionality, configure these settings in the plugin admin:

1. **Device Folder Mapping** (`device_folder_mapping`):
   - Key: HubSpot folder ID where devices should be stored
   - Value: Object type (e.g., "device")

2. **Device Association IDs** (`device_association_ids`):
   - `device_to_contact`: Association ID for linking devices to contacts
   - `device_to_deal`: Association ID for linking devices to deals

### Example Settings

```php
$settings = array(
    'device_folder_mapping' => array(
        '12345' => 'device' // HubSpot folder ID => object type
    ),
    'device_association_ids' => array(
        'device_to_contact' => '67890',
        'device_to_deal' => '54321'
    )
);
update_option('hubspot_sync_milli_settings', $settings);
```

## What Happens When You Add a Serial Number

1. **WooCommerce Updates**:
   - Finds the matching product item (defaults to "Milli Vaginal Dilator")
   - Adds "Serial number" meta to the order item
   - Updates order meta with comma-separated list of all serial numbers

2. **HubSpot Updates** (triggered automatically):
   - Creates a new device record in the specified HubSpot folder
   - Populates device properties (serial number, order ID, customer info)
   - Creates associations between device and contact
   - Creates associations between device and deal

## Admin Interface

### Manual Sync Options

In the WooCommerce admin order edit screen, you'll see:
- **Sync Devices to HubSpot** - Manually sync all devices for this order
- Current device sync status and serial numbers are displayed

### Bulk Actions

You can process multiple orders at once:

```php
// Sync devices for multiple orders
$order_ids = array(12345, 12346, 12347);
$result = HubSpot_Sync_Milli_Sync_Manager::batch_update_serial_numbers($order_ids);
```

## API Integration

### WordPress REST API

Create a REST endpoint for external systems:

```php
// POST to: /wp-json/hubspot-sync-milli/v1/serial-number
// Body: {"order_id": 12345, "serial_number": "SN123456789"}

register_rest_route('hubspot-sync-milli/v1', '/serial-number', array(
    'methods' => 'POST',
    'callback' => function($request) {
        $order_id = $request->get_param('order_id');
        $serial_number = $request->get_param('serial_number');
        
        $success = HubSpot_Sync_Milli_Serial_Number_Manager::add_serial_number(
            $order_id,
            $serial_number
        );
        
        return array('success' => $success);
    }
));
```

### WordPress Hooks

Listen for serial number events:

```php
// Triggered when a serial number is processed
add_action('hubspot_sync_milli_process_serial_number', function($order_id, $serial_number) {
    error_log("Processing serial {$serial_number} for order {$order_id}");
});
```

## VeraCore Integration Example

For VeraCore or similar fulfillment systems:

```php
// Process VeraCore webhook
add_action('wp_ajax_nopriv_veracore_webhook', function() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    foreach ($data as $shipment) {
        $order_id = $shipment['Order ID'];
        $serial_number = $shipment['Product Serial Number Shipped'];
        
        if (!empty($order_id) && !empty($serial_number)) {
            HubSpot_Sync_Milli_Serial_Number_Manager::add_serial_number(
                $order_id,
                $serial_number
            );
        }
    }
    
    wp_die('OK');
});
```

## Utility Functions

```php
// Check if order has serial numbers
$has_serials = HubSpot_Sync_Milli_Serial_Number_Manager::order_has_serial_numbers($order_id);

// Get serial numbers for an order
$serial_numbers = HubSpot_Sync_Milli_Serial_Number_Manager::get_order_serial_numbers($order_id);

// Remove a serial number
HubSpot_Sync_Milli_Serial_Number_Manager::remove_serial_number($order_id, $serial_number);
```

## Debugging

Enable WordPress debug logging to see detailed processing information:

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Look for logs like:
// [HubSpot Sync - Milli] DEBUG: Adding serial number SN123456789 to order 12345
// [HubSpot Sync - Milli] DEBUG: Created HubSpot device with ID: 987654321
```

## Troubleshooting

### Common Issues

1. **Serial numbers not syncing to HubSpot**:
   - Check HubSpot API token is valid
   - Verify device folder mapping is configured
   - Check device association IDs are set

2. **Product not found errors**:
   - Verify the product name matches (default: "Milli Vaginal Dilator")
   - Check the order contains the expected product

3. **Association errors**:
   - Ensure the contact exists in HubSpot
   - Verify deal is created before device sync
   - Check association IDs are correct

Look in the WordPress debug logs for detailed error messages that will help identify the specific issue.