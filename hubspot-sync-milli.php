<?php
/**
 * Plugin Name: HubSpot Sync - Milli
 * Plugin URI: https://teamoutsiders.com
 * Description: Consolidated HubSpot integration for WooCommerce. Syncs orders, contacts, deals, and custom checkout fields to HubSpot CRM.
 * Version: 1.0.3
 * Author: Team Outsiders
 * Author URI: https://teamoutsiders.com
 * License: GPL-3.0+
 * License URI: http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain: hubspot-sync-milli
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 4.0
 * WC tested up to: 8.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Checkout Fields Configuration
// Set to true for simplified single healthcare provider question
// Set to false for full multi-step checkout fields
define( 'HUBSPOT_SYNC_MILLI_USE_SIMPLE_CHECKOUT', true );

// Plugin version
define( 'HUBSPOT_SYNC_MILLI_VERSION', '1.0.3' );

// Plugin paths
define( 'HUBSPOT_SYNC_MILLI_PLUGIN_FILE', __FILE__ );
define( 'HUBSPOT_SYNC_MILLI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HUBSPOT_SYNC_MILLI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check if WooCommerce is active
 */
function hubspot_sync_milli_check_woocommerce() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'hubspot_sync_milli_woocommerce_missing_notice' );
        return false;
    }
    return true;
}

/**
 * WooCommerce missing notice
 */
function hubspot_sync_milli_woocommerce_missing_notice() {
    echo '<div class="error notice"><p>';
    echo esc_html__( 'HubSpot Sync - Milli requires WooCommerce to be installed and active.', 'hubspot-sync-milli' );
    echo '</p></div>';
}

/**
 * Initialize the plugin
 */
function hubspot_sync_milli_init() {
    if ( ! hubspot_sync_milli_check_woocommerce() ) {
        return;
    }

    // Load the updater class
    require_once HUBSPOT_SYNC_MILLI_PLUGIN_DIR . 'includes/class-updater.php';
    
    // Initialize the updater
    new HubSpot_Sync_Milli_Updater( HUBSPOT_SYNC_MILLI_PLUGIN_FILE );

    // Load the main plugin class
    require_once HUBSPOT_SYNC_MILLI_PLUGIN_DIR . 'includes/class-hubspot-sync-milli.php';
    
    // Initialize the plugin
    HubSpot_Sync_Milli::get_instance();
}

/**
 * Declare compatibility with WooCommerce HPOS (custom order tables).
 */
function hubspot_sync_milli_declare_hpos_compatibility() {
    if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
}

/**
 * Plugin activation
 */
function hubspot_sync_milli_activate() {
    if ( ! hubspot_sync_milli_check_woocommerce() ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die( esc_html__( 'HubSpot Sync - Milli requires WooCommerce to be installed and active.', 'hubspot-sync-milli' ) );
    }

    // Set default options
    if ( ! get_option( 'hubspot_sync_milli_settings' ) ) {
        $default_settings = array(
            'api_token' => '',
            'site_environment' => 'staging',
            'site_prefix' => '',
            'owner_id' => '',
            'deal_pipeline' => '',
            'deal_stages' => array(
                'won' => '',
                'lost' => '',
                'cancelled' => '',
                'refunded' => '',
                'failed' => '',
                'abandoned' => '',
                'processing' => ''
            ),
            'sync_contact_fields' => true,
            'enable_checkout_fields' => true,
            'sync_deal_fields' => true,
            'contact_field_mapping' => array(
                'acquisition_source' => 'how_did_you_hear_about_us_consumer',
                'clinician_name' => 'referring_clinician',
                'clinic_state' => 'referring_state',
                'clinic_name' => 'referring_clinic',
                'talked_to_provider' => 'have_you_talked_to_healthcare_provider',
                'provider_referred' => 'did_your_provider_refer_you_to_milli_'
            ),
            'association_ids' => array(
                'deal_to_device' => '',
                'contact_to_device' => '',
                'company_to_device' => ''
            ),
            'serial_numbers_folder_id' => '',
            'sync_on_status_change' => array( 'processing', 'completed', 'pending-payment', 'on-hold' ),
            'debug_logging' => false
        );
        
        update_option( 'hubspot_sync_milli_settings', $default_settings );
    }
    
    flush_rewrite_rules();
}

/**
 * Plugin deactivation
 */
function hubspot_sync_milli_deactivate() {
    // Clear any scheduled cron events
    wp_clear_scheduled_hook( 'hubspot_sync_milli_cron' );
    flush_rewrite_rules();
}

/**
 * Plugin uninstall
 */
function hubspot_sync_milli_uninstall() {
    // Delete plugin options (uncomment if you want to remove data on uninstall)
    // delete_option( 'hubspot_sync_milli_settings' );
    
    // Clear any scheduled cron events
    wp_clear_scheduled_hook( 'hubspot_sync_milli_cron' );
}

// Hook into WordPress
add_action( 'before_woocommerce_init', 'hubspot_sync_milli_declare_hpos_compatibility' );
add_action( 'plugins_loaded', 'hubspot_sync_milli_init' );

// Activation and deactivation hooks
register_activation_hook( __FILE__, 'hubspot_sync_milli_activate' );
register_deactivation_hook( __FILE__, 'hubspot_sync_milli_deactivate' );
register_uninstall_hook( __FILE__, 'hubspot_sync_milli_uninstall' );