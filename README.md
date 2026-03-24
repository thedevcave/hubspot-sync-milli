# HubSpot Sync - Milli

A comprehensive WordPress plugin that provides complete WooCommerce-to-HubSpot integration with advanced abandoned cart tracking, device management, and external system integration. Built by Team Outsiders as a modern, consolidated replacement for multiple HubSpot gateway plugins.

## ✨ Key Features

### 🛒 **Advanced Abandoned Cart Tracking**
- **Real-Time Monitoring**: Tracks 21+ checkout fields with debounced AJAX calls
- **Smart Cart Conversion**: Converts abandoned cart deals to completed orders (prevents duplicates)
- **Persistent Hash System**: Maintains cart identity across sessions and page reloads
- **Customer Journey Tracking**: Complete lifecycle from abandonment to conversion in single HubSpot deal

### 🔄 **Comprehensive Sync System**
- **Contact Management**: Bi-directional sync with custom field mapping
- **Deal Lifecycle**: Automated deal creation, updates, and stage transitions
- **Device Integration**: Serial number tracking from fulfillment to HubSpot custom objects
- **Background Processing**: Non-blocking async sync with cron job scheduling
- **External API Ready**: REST endpoints for ShipHero and other fulfillment systems

### 📱 **Device & Serial Number Management**
- **Order Item Tracking**: Serial numbers stored in WooCommerce order meta
- **HubSpot Device Objects**: Automatic device creation with full property mapping
- **Association Management**: Links devices to contacts, deals, and companies
- **ShipHero Integration**: Automatic monitoring of existing ShipHero webhooks for serial numbers
- **Batch Processing**: Handle multiple serial number assignments efficiently
- **Manual Sync Options**: Admin controls for immediate device synchronization

### ⚙️ **Enterprise-Grade Configuration**
- **Environment Detection**: Staging, Production, Development modes with safe testing
- **Custom Field Mapping**: Flexible mapping between WooCommerce and HubSpot properties
- **Deal Stage Management**: Configurable stages for all order statuses including abandonment
- **Sync Triggers**: Granular control over when syncing occurs
- **Rate Limiting**: Built-in API throttling to prevent HubSpot rate limits

### 🛡️ **Security & Reliability**
- **Nonce Verification**: Secure AJAX endpoints with proper authentication
- **Data Sanitization**: All inputs sanitized and validated
- **Error Handling**: Comprehensive try-catch blocks with detailed logging
- **Fault Tolerance**: Graceful degradation when external services are unavailable
- **Debug Mode**: Detailed logging for troubleshooting and monitoring

## 🚀 Installation

1. Upload the `hubspot-sync-milli` folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress 'Plugins' menu
3. Configure settings at **Settings → HubSpot Sync**

**Requirements:**
- WordPress 5.0+
- WooCommerce 4.0+  
- PHP 7.4+
- HubSpot Private App with CRM permissions

## ⚡ Quick Configuration

### 1. HubSpot API Setup
Create a HubSpot Private App with these scopes:
```
• crm.objects.contacts.read
• crm.objects.contacts.write
• crm.objects.deals.read
• crm.objects.deals.write
• crm.objects.companies.read
• crm.objects.companies.write
• crm.objects.custom.read
• crm.objects.custom.write
```

### 2. Essential Settings
- **API Token**: Your HubSpot Private App access token
- **Site Prefix**: Unique identifier for this site (e.g., "MyStore")
- **Deal Pipeline**: HubSpot pipeline ID for WooCommerce deals
- **Owner ID**: Default HubSpot owner for new contacts/deals

### 3. Deal Stage Mapping
| Order Status | HubSpot Stage | Purpose |
|--------------|---------------|---------|
| **Abandoned Cart** | `abandoned_cart` | Real-time cart tracking |
| **Processing** | `processing` | Orders being fulfilled |
| **Completed** | `won` | Successfully completed orders |
| **Cancelled** | `cancelled` | Customer cancellations |
| **Refunded** | `refunded` | Processed refunds |
| **Failed** | `failed` | Payment failures |

## 🎯 Core Functionality

### Abandoned Cart System
**Real-time tracking prevents deal duplication:**

```mermaid
graph LR
    A[Checkout Form] -->|Field Changes| B[Debounced AJAX]
    B --> C[Cart Hash Generated]
    C --> D[HubSpot Deal Created]
    D -->|Customer Returns| E[Same Deal Updated]
    E -->|Order Complete| F[Deal Converted]
    F --> G[Single Deal Journey]
```

**Benefits:**
- ✅ Single deal tracks complete customer journey  
- ✅ No duplicate deals for same customer
- ✅ Accurate conversion rate reporting
- ✅ Preserved attribution and source data

### Device Management Workflow
**Complete serial number to HubSpot integration:**

1. **Order Completion**: Customer places order
2. **Fulfillment**: ShipHero (or external system) ships product
3. **Serial Assignment**: Webhook or API call adds serial number to order
4. **Automatic Detection**: Plugin monitors order meta updates (non-destructive)
5. **Device Creation**: HubSpot device object created automatically
6. **Association**: Device linked to contact, deal, and company

## 📊 Field Mapping

### Checkout Fields → HubSpot Properties
| Checkout Field | HubSpot Property | Type | Description |
|---|---|---|---|
| **Acquisition Source** | `how_did_you_hear_about_us_consumer` | Dropdown | Marketing attribution |
| **Clinician Name** | `referring_clinician` | Text | Healthcare provider |
| **Clinic State** | `referring_state` | Dropdown | Provider location |
| **Clinic Name** | `referring_clinic` | Text | Practice name |
| **Provider Conversation** | `have_you_talked_to_healthcare_provider` | Yes/No | Consultation status |
| **Provider Referral** | `did_your_provider_refer_you_to_milli_` | Yes/No | Referral verification |

### Device Properties
| Property | Source | Description |
|----------|--------|-------------|
| **serial_number** | Order meta | Unique device identifier |
| **order_id** | WooCommerce | Associated order number |
| **customer_email** | Order billing | Device owner |
| **assignment_date** | Current time | When device was assigned |
| **product_name** | Order items | Product associated with device |

## 🔌 API Integration

### REST Endpoints

#### Add Serial Number
```bash
POST /wp-json/hubspot-sync-milli/v1/serial-number
Authorization: Bearer {token}
Content-Type: application/json

{
    "order_id": 12345,
    "serial_number": "SN123456789"
}
```

#### Test Connection  
```bash
POST /wp-admin/admin-ajax.php
Content-Type: application/x-www-form-urlencoded

action=hubspot_sync_milli_test_connection
&nonce={admin_nonce}
```

### Webhook Integration
#### ShipHero Integration (Automatic)
The plugin automatically monitors existing ShipHero webhook processing:
```php
// No code changes needed - plugin hooks into existing ShipHero workflow:
// 1. ShipHero webhook → api-shiphero.php (existing)
// 2. Serial numbers saved to order meta (existing) 
// 3. Plugin detects meta update → triggers HubSpot device creation (new)

// Monitoring is added via WordPress hooks:
add_action('updated_post_meta', 'on_order_meta_updated', 10, 4);
```

#### External Webhook Integration (Manual)
```php
// External systems can trigger serial number assignment
add_action('wp_ajax_nopriv_external_webhook', function() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    foreach ($data as $shipment) {
        HubSpot_Sync_Milli_Serial_Number_Manager::add_serial_number(
            $shipment['Order ID'],
            $shipment['Product Serial Number Shipped']
        );
    }
    
    wp_die('OK');
});
```

## 📁 File Structure
```
hubspot-sync-milli/
├── hubspot-sync-milli.php              # Main plugin file
├── README.md                           # This documentation
├── ABANDONED_CART.md                   # Abandoned cart guide  
├── SERIAL_NUMBERS.md                   # Device integration guide
├── HOOKS_AND_TRIGGERS.md              # Complete hook reference
├── includes/
│   ├── class-hubspot-sync-milli.php    # Core plugin orchestration
│   ├── class-admin-settings.php        # Admin interface & settings
│   ├── class-hubspot-api.php          # HubSpot API wrapper
│   ├── class-checkout-fields.php       # Custom checkout fields
│   ├── class-sync-manager.php         # Sync orchestration & logic
│   ├── class-abandoned-cart-tracker.php # Real-time cart tracking
│   └── class-serial-number-manager.php # Device management utilities
├── assets/
│   ├── css/
│   │   ├── admin.css                  # Admin interface styling
│   │   └── checkout.css               # Checkout field styling
│   └── js/
│       ├── admin.js                   # Admin functionality
│       ├── checkout.js                # Checkout interactions
│       └── abandoned-cart-tracker.js  # Real-time form monitoring
├── examples/
│   └── serial-number-examples.php     # Integration code examples
└── tests/
    ├── complete-system-breakdown.php   # Detailed functionality trace
    └── executable-test.php            # Runnable test simulation
```

## 🧪 Testing & Debugging

### Comprehensive Testing Framework
The plugin includes detailed testing tools:

#### Executable Test Suite
```bash
# Run complete system test via WP-CLI
wp eval-file wp-content/plugins/hubspot-sync-milli/tests/executable-test.php

# Output shows complete buying process simulation:
# ✓ Stage 1: Checkout page load
# ✓ Stage 2: Form interaction (abandonment)
# ✓ Stage 3: HubSpot sync
# ✓ Stage 4: Customer return
# ✓ Stage 5: Order completion  
# ✓ Stage 6: Cart conversion
# ✓ Stage 7: Background sync
# ✓ Stage 8: Device assignment
```

#### Debug Logging
Enable in **Settings → HubSpot Sync → Advanced**:
```
[HubSpot Sync] Generated cart hash: abc123... for email: customer@example.com
[HubSpot Sync] Abandoned cart synced. Deal ID: 12345678
[HubSpot Sync] Converting cart abc123... to order 12345
[HubSpot Sync] Device SN123456 created with ID: 87654321
```

### Manual Testing Checklist
- [ ] **Abandoned Cart**: Fill checkout form, verify HubSpot deal creation
- [ ] **Cart Return**: Modify form, confirm same deal updated  
- [ ] **Order Completion**: Complete purchase, verify deal conversion
- [ ] **Device Assignment**: Add serial number, confirm HubSpot device creation
- [ ] **ShipHero Integration**: Test with existing api-shiphero.php webhook
- [ ] **Admin Interface**: Test connection, manual sync, bulk actions

## ⚙️ Advanced Usage

### Background Sync System
```php
// Orders sync in background (60-second delay)
wp_schedule_single_event(time() + 60, 'hubspot_sync_milli_cron', [$order_id]);

// Rate limiting prevents API throttling  
usleep(110000); // 110ms between API calls
```

### Custom Field Integration
```php
// Add custom fields to checkout
add_action('woocommerce_after_order_notes', function($checkout) {
    woocommerce_form_field('custom_field', [
        'type' => 'text',
        'label' => 'Custom Information',
        'id' => 'custom_field'
    ]);
});

// Map to HubSpot property
add_filter('hubspot_sync_milli_contact_data', function($data, $order) {
    $data['custom_property'] = $order->get_meta('custom_field');
    return $data;
}, 10, 2);
```

### Serial Number Batch Processing
```php
// Process CSV export from fulfillment system
$csv_data = [
    ['Order ID' => '12345', 'Product Serial Number Shipped' => 'SN123456'],
    ['Order ID' => '12346', 'Product Serial Number Shipped' => 'SN789012']
];

$result = HubSpot_Sync_Milli_Serial_Number_Manager::batch_add_serial_numbers($csv_data);
// Result: ['success' => true, 'processed' => 2, 'errors' => []]
```

## 🛠️ Hooks & Filters

### Custom Actions
```php
// Before/after sync events
do_action('hubspot_sync_milli_before_contact_sync', $contact_data, $order);
do_action('hubspot_sync_milli_after_contact_sync', $hubspot_contact_id, $order);
do_action('hubspot_sync_milli_before_deal_sync', $deal_data, $order);
do_action('hubspot_sync_milli_after_deal_sync', $hubspot_deal_id, $order);

// Device processing
do_action('hubspot_sync_milli_process_serial_number', $order_id, $serial_number);
```

### Data Filters
```php
// Modify data before HubSpot sync
add_filter('hubspot_sync_milli_contact_data', 'modify_contact_data', 10, 2);
add_filter('hubspot_sync_milli_deal_data', 'modify_deal_data', 10, 2);
add_filter('hubspot_sync_milli_device_data', 'modify_device_data', 10, 2);
```

## 🔧 Troubleshooting

### Common Issues

**❌ Connection Test Failed**
- Verify API token format and permissions
- Check HubSpot app scopes include CRM read/write
- Ensure token belongs to correct portal

**❌ Duplicate Deals Created**  
- Check cart hash generation in browser storage
- Verify session persistence across page loads
- Enable debug logging to trace cart hash flow

**❌ Device Sync Failed**
- Confirm device folder mapping in settings
- Verify association IDs are configured
- Check HubSpot custom object permissions

**❌ Fields Not Syncing**
- Validate HubSpot property names match exactly
- Check property exists in your HubSpot portal  
- Verify data types match (text, number, datetime, etc.)

### Debug Mode
1. Go to **Settings → HubSpot Sync → Advanced**
2. Enable **Debug Logging**
3. Save settings
4. Check `/wp-content/debug.log` for detailed information

### Performance Optimization
- **Rate Limiting**: Built-in 110ms delays prevent API throttling
- **Background Sync**: 60-second delay keeps checkout fast
- **Debounced AJAX**: 1000ms debouncing prevents request spam
- **Batch Processing**: Multiple records processed efficiently

## 📈 Reporting & Analytics

### HubSpot Reports
Create custom reports using these properties:
- **Abandoned Cart Conversion Rate**: `order_source = "converted_from_abandoned_cart"`
- **Device Assignment Status**: Track devices per customer/order
- **Customer Journey Time**: Time from abandonment to conversion
- **Source Attribution**: Original acquisition source preserved through conversion

### WordPress Admin
- **Order Sync Status**: Visible on individual order pages
- **Manual Sync Actions**: Force immediate synchronization
- **Bulk Operations**: Process multiple orders simultaneously
- **Connection Testing**: Validate API connectivity

## 📞 Support & Documentation

### Additional Resources
- **[ABANDONED_CART.md](ABANDONED_CART.md)**: Complete abandoned cart implementation guide
- **[SERIAL_NUMBERS.md](SERIAL_NUMBERS.md)**: Device integration and serial number management  
- **[HOOKS_AND_TRIGGERS.md](HOOKS_AND_TRIGGERS.md)**: Complete hook and trigger reference

### Professional Support
For enterprise implementation, custom development, or technical support:
**Team Outsiders** - Professional WordPress & HubSpot Integration Services

## 📄 License & Credits

**License**: GPL-3.0+  
**Author**: Team Outsiders  
**Based On**: HubSpot Gateway for WooCommerce by Jerome Cloutier (Globalia)  
**Version**: 1.0.0

---

## 🔄 Changelog

### Version 1.0.0 - Complete Rewrite
- ✨ **New**: Advanced abandoned cart tracking with conversion prevention
- ✨ **New**: Device and serial number management system
- ✨ **New**: External system integration via REST API
- ✨ **New**: ShipHero webhook monitoring with automatic device creation
- ✨ **New**: Non-destructive integration hooks for existing systems
- ✨ **New**: Comprehensive testing framework
- ✨ **New**: Background sync processing 
- 🔧 **Enhanced**: Modern admin interface with real-time connection testing
- 🔧 **Enhanced**: Flexible field mapping system
- 🔧 **Enhanced**: Error handling and debug logging
- 🔧 **Enhanced**: Security with nonce verification and data sanitization
- 🛡️ **Fixed**: Deal duplication issues from original plugin
- 🛡️ **Fixed**: Session persistence and cart hash consistency
- 🛡️ **Fixed**: Rate limiting to prevent API throttling

This version represents a complete architectural rewrite focused on reliability, extensibility, and enterprise-grade functionality.