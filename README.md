# HubSpot Sync - Milli

A comprehensive WordPress plugin that syncs WooCommerce orders, contacts, and custom checkout fields to HubSpot CRM. Built by Team Outsiders as a consolidated replacement for multiple HubSpot gateway plugins.

## Features

### 🔄 **Unified Sync System**
- **Contact Sync**: Custom checkout fields automatically sync to HubSpot contact properties
- **Deal Sync**: WooCommerce orders create and update HubSpot deals with full order data
- **Company Integration**: Automatic company creation and association
- **Manual Sync**: Admin action to manually sync individual orders

### ⚙️ **Configurable Settings**
- **Environment Detection**: Staging, Production, Development modes with automatic prefixing
- **Custom Field Mapping**: Map any checkout field to any HubSpot property
- **Deal Stage Management**: Configure deal stages for different order statuses
- **Sync Triggers**: Choose which order status changes trigger syncing

### 🛒 **Enhanced Checkout Fields**
- **Conditional Logic**: Smart fields that show/hide based on user selections
- **Acquisition Tracking**: "How did you hear about us?" with provider referral tracking
- **Provider Details**: Clinician name, clinic name, and state collection
- **Smooth UX**: Animated transitions and accessibility-focused design

### 🎛️ **Advanced Features**
- **API Connection Testing**: Test HubSpot connection directly from admin
- **Debug Logging**: Detailed logging for troubleshooting
- **Error Handling**: Graceful handling of API failures and portal mismatches
- **Async Processing**: Background sync processing to avoid blocking checkout
- **Association Management**: Custom object associations for devices and companies

## Installation

1. Upload the `hubspot-sync-milli` folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress 'Plugins' menu
3. Configure settings at **Settings → HubSpot Sync**

## Configuration

### 1. API Setup
- Create a HubSpot Private App with the following scopes:
  - `crm.objects.contacts.read`
  - `crm.objects.contacts.write`
  - `crm.objects.deals.read`
  - `crm.objects.deals.write`
  - `crm.objects.companies.read`
  - `crm.objects.companies.write`
- Copy the access token to the plugin settings

### 2. Field Mapping
Configure how WooCommerce checkout fields map to HubSpot properties:

| Checkout Field | Default HubSpot Property | Description |
|---|---|---|
| Acquisition Source | `how_did_you_hear_about_us_consumer` | Customer acquisition channel |
| Clinician Name | `referring_clinician` | Healthcare provider name |
| Clinic State | `referring_state` | Provider location state |
| Clinic Name | `referring_clinic` | Clinic/practice name |
| Talked to Provider | `have_you_talked_to_healthcare_provider` | Yes/No field |
| Provider Referred | `did_your_provider_refer_you_to_milli_` | Yes/No field |

### 3. Deal Stages
Map WooCommerce order statuses to HubSpot deal stages:
- **Won**: Completed orders
- **Processing**: Orders being processed
- **Lost**: Failed orders
- **Cancelled**: Cancelled orders
- **Refunded**: Refunded orders
- **Abandoned**: Abandoned carts

## Usage

### Automatic Sync
The plugin automatically syncs when:
- Orders are placed (checkout completion)
- Order status changes (configurable)
- Background cron jobs run

### Manual Sync
1. Go to **WooCommerce → Orders**
2. Click on an order
3. Select **"Sync to HubSpot"** from Order Actions
4. Click **Update**

### Checkout Integration
The plugin automatically adds conditional fields to WooCommerce checkout:
1. **Acquisition source selection**
2. **Provider conversation tracking**
3. **Referral verification**
4. **Provider details collection** (when applicable)

## Technical Details

### File Structure
```
hubspot-sync-milli/
├── hubspot-sync-milli.php      # Main plugin file
├── includes/
│   ├── class-hubspot-sync-milli.php    # Core plugin class
│   ├── class-admin-settings.php        # Admin interface
│   ├── class-hubspot-api.php           # HubSpot API wrapper
│   ├── class-checkout-fields.php       # Checkout field management
│   └── class-sync-manager.php          # Sync orchestration
└── assets/
    ├── css/
    │   ├── admin.css           # Admin styling
    │   └── checkout.css        # Checkout styling
    └── js/
        ├── admin.js            # Admin functionality
        └── checkout.js         # Checkout interactions
```

### Hooks and Filters
- `hubspot_sync_milli_before_contact_sync` - Run before contact sync
- `hubspot_sync_milli_after_contact_sync` - Run after contact sync
- `hubspot_sync_milli_before_deal_sync` - Run before deal sync
- `hubspot_sync_milli_after_deal_sync` - Run after deal sync
- `hubspot_sync_milli_contact_data` - Filter contact data before sync
- `hubspot_sync_milli_deal_data` - Filter deal data before sync

### Requirements
- WordPress 5.0+
- WooCommerce 4.0+
- PHP 7.4+
- HubSpot Private App with appropriate permissions

## Troubleshooting

### Common Issues

**1. Connection Test Fails**
- Verify API token is correct
- Check HubSpot app permissions
- Ensure token belongs to correct portal

**2. Fields Not Syncing**
- Verify property names in HubSpot match mapping
- Check if properties exist in your portal
- Enable debug logging for detailed error info

**3. Portal ID Mismatch**
- The plugin will automatically detect and clear old portal data
- Check that API token belongs to intended portal

### Debug Mode
Enable debug logging in **Advanced Settings** to see detailed sync information in WordPress debug logs.

### Log Locations
- WordPress debug log: `/wp-content/debug.log`
- Server error logs: Check your hosting provider's error logs

## Support

For support with this plugin, contact Team Outsiders or check the plugin documentation.

## Changelog

### Version 1.0.0
- Initial release
- Consolidated functionality from multiple legacy plugins
- Comprehensive admin interface
- Enhanced checkout field system
- Improved error handling and debugging
- Modern, responsive design

## Credits

**Author**: Team Outsiders  
**Original Plugin**: Based on HubSpot Gateway for WooCommerce by Jerome Cloutier (Globalia)  
**License**: GPL-3.0+