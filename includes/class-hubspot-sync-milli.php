<?php
/**
 * Main plugin class
 *
 * @package HubSpot_Sync_Milli
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HubSpot_Sync_Milli {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Plugin settings
     */
    private $settings;
    
    /**
     * HubSpot client
     */
    private $hubspot_client;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->settings = get_option( 'hubspot_sync_milli_settings', array() );
        $this->init();
    }
    
    /**
     * Initialize plugin
     */
    private function init() {
        // Load dependencies
        $this->load_dependencies();
        
        // Initialize HubSpot client if API token is available
        if ( ! empty( $this->settings['api_token'] ) ) {
            $this->init_hubspot_client();
        }
        
        // Hook into WordPress
        add_action( 'init', array( $this, 'init_hooks' ), 10 );
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        
        // WooCommerce hooks
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'on_order_processed' ), 20, 3 );
        add_action( 'woocommerce_order_status_changed', array( $this, 'on_order_status_changed' ), 10, 4 );
        
        // Custom checkout fields
        add_action( 'woocommerce_after_order_notes', array( $this, 'add_checkout_fields' ) );
        add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_checkout_fields' ) );
        
        // Async processing
        add_action( 'hubspot_sync_milli_cron', array( $this, 'process_sync' ) );
        
        // Device sync hook
        add_action( 'hubspot_sync_milli_add_serial_numbers', array( $this, 'update_hubspot_device_data' ) );
        add_action( 'hubspot_sync_milli_process_serial_number', array( $this, 'process_single_serial_number' ), 10, 2 );
        add_action( 'woocommerce_order_item_add_action', array( $this, 'on_order_item_added' ), 10, 3 );
        
        // Admin order actions
        add_filter( 'woocommerce_order_actions', array( $this, 'add_order_actions' ) );
        add_action( 'woocommerce_order_action_hubspot_sync_milli_sync', array( $this, 'manual_sync_order' ) );
        add_action( 'woocommerce_order_action_hubspot_sync_milli_sync_devices', array( $this, 'manual_sync_devices' ) );
        
        // AJAX endpoints
        add_action( 'wp_ajax_hubspot_sync_milli_test_connection', array( $this, 'test_connection' ) );
        
        // Add scripts and styles
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'frontend_scripts' ) );
    }
    
    /**
     * Load dependencies
     */
    private function load_dependencies() {
        
        // Load admin classes
        require_once HUBSPOT_SYNC_MILLI_PLUGIN_DIR . 'includes/class-admin-settings.php';
        require_once HUBSPOT_SYNC_MILLI_PLUGIN_DIR . 'includes/class-hubspot-api.php';
        require_once HUBSPOT_SYNC_MILLI_PLUGIN_DIR . 'includes/class-checkout-fields.php';
        require_once HUBSPOT_SYNC_MILLI_PLUGIN_DIR . 'includes/class-sync-manager.php';
        require_once HUBSPOT_SYNC_MILLI_PLUGIN_DIR . 'includes/class-serial-number-manager.php';
        require_once HUBSPOT_SYNC_MILLI_PLUGIN_DIR . 'includes/class-abandoned-cart-tracker.php';
        
        // Load vendor autoloader if exists (for HubSpot SDK)
        $autoload_file = HUBSPOT_SYNC_MILLI_PLUGIN_DIR . 'vendor/autoload.php';
        if ( file_exists( $autoload_file ) ) {
            require_once $autoload_file;
        }
    }
    
    /**
     * Initialize HubSpot client
     */
    private function init_hubspot_client() {
        if ( class_exists( 'HubSpot\Factory' ) ) {
            try {
                $this->hubspot_client = HubSpot\Factory::createWithAccessToken( $this->settings['api_token'] );
            } catch ( Exception $e ) {
                $this->log_error( 'Failed to initialize HubSpot client: ' . $e->getMessage() );
            }
        }
    }
    
    /**
     * Initialize hooks
     */
    public function init_hooks() {
        // Initialize abandoned cart tracker
        if ( class_exists( 'HubSpot_Sync_Milli_Abandoned_Cart_Tracker' ) ) {
            new HubSpot_Sync_Milli_Abandoned_Cart_Tracker();
        }
        
        // Add any additional hooks that need to run after init
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __( 'HubSpot Sync - Milli', 'hubspot-sync-milli' ),
            __( 'HubSpot Sync', 'hubspot-sync-milli' ),
            'manage_options',
            'hubspot-sync-milli',
            array( $this, 'admin_page' )
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting( 'hubspot_sync_milli_settings_group', 'hubspot_sync_milli_settings', array(
            'sanitize_callback' => array( $this, 'sanitize_settings' )
        ) );
    }
    
    /**
     * Handle settings page redirect to maintain active tab
     */
    public function handle_settings_redirect() {
        // Check if we're processing our settings
        if ( isset( $_GET['settings-updated'] ) && 
             isset( $_GET['page'] ) && 
             $_GET['page'] === 'hubspot-sync-milli' ) {
            
            // Get the active tab from POST data if it exists
            $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
            
            // Remove settings-updated to prevent duplicate notifications
            $redirect_url = remove_query_arg( 'settings-updated' );
            $redirect_url = add_query_arg( array(
                'page' => 'hubspot-sync-milli',
                'tab' => $active_tab,
                'updated' => '1'
            ), admin_url( 'admin.php' ) );
            
            wp_redirect( $redirect_url );
            exit;
        }
        
        // Show custom success message
        if ( isset( $_GET['updated'] ) && $_GET['updated'] === '1' && 
             isset( $_GET['page'] ) && $_GET['page'] === 'hubspot-sync-milli' ) {
            add_action( 'admin_notices', array( $this, 'settings_updated_notice' ) );
        }
    }
    
    /**
     * Display custom settings updated notice
     */
    public function settings_updated_notice() {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Settings saved successfully.', 'hubspot-sync-milli' ); ?></p>
        </div>
        <?php
    }
    
    /**
     * Settings page
     */
    public function admin_page() {
        if ( ! class_exists( 'HubSpot_Sync_Milli_Admin_Settings' ) ) {
            return;
        }
        
        $admin_settings = new HubSpot_Sync_Milli_Admin_Settings( $this->settings );
        $admin_settings->render_page();
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();
        
        // Sanitize each setting
        $sanitized['api_token'] = sanitize_text_field( $input['api_token'] ?? '' );
        $sanitized['site_environment'] = sanitize_text_field( $input['site_environment'] ?? 'staging' );
        $sanitized['site_prefix'] = sanitize_text_field( $input['site_prefix'] ?? '' );
        $sanitized['owner_id'] = sanitize_text_field( $input['owner_id'] ?? '' );
        $sanitized['deal_pipeline'] = sanitize_text_field( $input['deal_pipeline'] ?? '' );
        
        // Deal stages
        $sanitized['deal_stages'] = array();
        if ( isset( $input['deal_stages'] ) && is_array( $input['deal_stages'] ) ) {
            foreach ( $input['deal_stages'] as $key => $value ) {
                $sanitized['deal_stages'][ sanitize_key( $key ) ] = sanitize_text_field( $value );
            }
        }
        
        // Contact field mapping
        $sanitized['contact_field_mapping'] = array();
        if ( isset( $input['contact_field_mapping'] ) && is_array( $input['contact_field_mapping'] ) ) {
            foreach ( $input['contact_field_mapping'] as $key => $value ) {
                $sanitized['contact_field_mapping'][ sanitize_key( $key ) ] = sanitize_text_field( $value );
            }
        }
        
        // Association IDs
        $sanitized['association_ids'] = array();
        if ( isset( $input['association_ids'] ) && is_array( $input['association_ids'] ) ) {
            foreach ( $input['association_ids'] as $key => $value ) {
                $sanitized['association_ids'][ sanitize_key( $key ) ] = sanitize_text_field( $value );
            }
        }
        
        // Other settings
        $sanitized['serial_numbers_folder_id'] = sanitize_text_field( $input['serial_numbers_folder_id'] ?? '' );
        $sanitized['sync_contact_fields'] = ! empty( $input['sync_contact_fields'] );
        $sanitized['sync_deal_fields'] = ! empty( $input['sync_deal_fields'] );
        $sanitized['debug_logging'] = ! empty( $input['debug_logging'] );
        
        // Sync triggers
        $sanitized['sync_on_status_change'] = array();
        if ( isset( $input['sync_on_status_change'] ) && is_array( $input['sync_on_status_change'] ) ) {
            foreach ( $input['sync_on_status_change'] as $status ) {
                $sanitized['sync_on_status_change'][] = sanitize_text_field( $status );
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Handle order processed
     */
    public function on_order_processed( $order_id, $posted_data, $order ) {
        if ( ! $this->should_sync() ) {
            return;
        }
        
        $this->schedule_sync( $order_id, 'order_processed' );
    }
    
    /**
     * Handle order status change
     */
    public function on_order_status_changed( $order_id, $old_status, $new_status, $order ) {
        if ( ! $this->should_sync() ) {
            return;
        }
        
        $sync_statuses = $this->settings['sync_on_status_change'] ?? array( 'processing', 'completed' );
        
        if ( in_array( $new_status, $sync_statuses, true ) ) {
            $this->schedule_sync( $order_id, 'status_change' );
        }
    }
    
    /**
     * Add checkout fields
     */
    public function add_checkout_fields( $checkout ) {
        if ( ! class_exists( 'HubSpot_Sync_Milli_Checkout_Fields' ) ) {
            return;
        }
        
        $checkout_fields = new HubSpot_Sync_Milli_Checkout_Fields( $this->settings );
        $checkout_fields->render_fields( $checkout );
    }
    
    /**
     * Save checkout fields
     */
    public function save_checkout_fields( $order_id ) {
        if ( ! class_exists( 'HubSpot_Sync_Milli_Checkout_Fields' ) ) {
            return;
        }
        
        $checkout_fields = new HubSpot_Sync_Milli_Checkout_Fields( $this->settings );
        $checkout_fields->save_fields( $order_id );
    }
    
    /**
     * Add order actions
     */
    public function add_order_actions( $actions ) {
        $actions['hubspot_sync_milli_sync'] = __( 'Sync to HubSpot', 'hubspot-sync-milli' );
        $actions['hubspot_sync_milli_sync_devices'] = __( 'Sync Devices to HubSpot', 'hubspot-sync-milli' );
        return $actions;
    }
    
    /**
     * Manual sync order
     */
    public function manual_sync_order( $order ) {
        if ( ! $this->should_sync() ) {
            return;
        }
        
        $order_id = $order->get_id();
        $this->process_sync( $order_id );
        
        // Add admin notice
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-success"><p>';
            echo esc_html__( 'Order synced to HubSpot successfully.', 'hubspot-sync-milli' );
            echo '</p></div>';
        } );
    }
    
    /**
     * Manual sync devices for an order
     */
    public function manual_sync_devices( $order ) {
        if ( ! $this->should_sync() ) {
            return;
        }
        
        $order_id = $order->get_id();
        $this->log_debug( "Manual device sync triggered for order {$order_id}" );
        
        // Get sync manager and sync devices
        $sync_manager = new HubSpot_Sync_Milli_Sync_Manager( $this->settings, $this->hubspot_client );
        $result = $sync_manager->sync_order_devices( $order );
        
        // Add admin notice based on result
        if ( $result && $result['success'] ) {
            add_action( 'admin_notices', function() use ( $result ) {
                echo '<div class="notice notice-success"><p>';
                echo esc_html( sprintf( __( 'Devices synced successfully: %s', 'hubspot-sync-milli' ), $result['message'] ) );
                echo '</p></div>';
            } );
            $this->log_debug( "Manual device sync completed successfully for order {$order_id}" );
        } else {
            add_action( 'admin_notices', function() use ( $result ) {
                echo '<div class="notice notice-error"><p>';
                echo esc_html( sprintf( __( 'Device sync failed: %s', 'hubspot-sync-milli' ), $result['message'] ?? 'Unknown error' ) );
                echo '</p></div>';
            } );
            $this->log_error( "Manual device sync failed for order {$order_id}: " . ( $result['message'] ?? 'Unknown error' ) );
        }
    }
    
    /**
     * Schedule sync
     */
    private function schedule_sync( $order_id, $trigger = 'manual' ) {
        if ( ! wp_next_scheduled( 'hubspot_sync_milli_cron', array( $order_id, $trigger ) ) ) {
            wp_schedule_single_event( time() + 60, 'hubspot_sync_milli_cron', array( $order_id, $trigger ) );
        }
    }
    
    /**
     * Process sync
     */
    public function process_sync( $order_id, $trigger = 'manual' ) {
        if ( ! $this->should_sync() ) {
            return;
        }
        
        if ( ! class_exists( 'HubSpot_Sync_Milli_Sync_Manager' ) ) {
            return;
        }
        
        $sync_manager = new HubSpot_Sync_Milli_Sync_Manager( $this->settings, $this->hubspot_client );
        $result = $sync_manager->sync_order( $order_id, $trigger );
        
        if ( $this->settings['debug_logging'] ?? false ) {
            $this->log_debug( "Sync completed for order {$order_id}: " . wp_json_encode( $result ) );
        }
        
        return $result;
    }
    
    /**
     * Check if sync should run
     */
    private function should_sync() {
        return ! empty( $this->settings['api_token'] ) && ! empty( $this->hubspot_client );
    }
    
    /**
     * Test HubSpot connection
     */
    public function test_connection() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'hubspot_sync_milli_test' ) ) {
            wp_die( 'Invalid nonce' );
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions' );
        }
        
        $api_token = sanitize_text_field( $_POST['api_token'] ?? '' );
        
        if ( empty( $api_token ) ) {
            wp_send_json_error( 'API token is required' );
        }
        
        // Test API connection
        if ( ! class_exists( 'HubSpot_Sync_Milli_HubSpot_API' ) ) {
            wp_send_json_error( 'HubSpot API class not found' );
        }
        
        $api = new HubSpot_Sync_Milli_HubSpot_API( array( 'api_token' => $api_token ) );
        $result = $api->test_connection();
        
        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result['message'] ?? 'Connection failed' );
        }
    }
    
    /**
     * Enqueue admin scripts
     */
    public function admin_scripts( $hook_suffix ) {
        if ( 'settings_page_hubspot-sync-milli' !== $hook_suffix ) {
            return;
        }
        
        wp_enqueue_script(
            'hubspot-sync-milli-admin',
            HUBSPOT_SYNC_MILLI_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            HUBSPOT_SYNC_MILLI_VERSION,
            true
        );
        
        wp_localize_script( 'hubspot-sync-milli-admin', 'hubspotSyncMilli', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'hubspot_sync_milli_test' ),
            'strings' => array(
                'testing' => __( 'Testing connection...', 'hubspot-sync-milli' ),
                'success' => __( 'Connection successful!', 'hubspot-sync-milli' ),
                'error' => __( 'Connection failed.', 'hubspot-sync-milli' ),
            )
        ) );
        
        wp_enqueue_style(
            'hubspot-sync-milli-admin',
            HUBSPOT_SYNC_MILLI_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            HUBSPOT_SYNC_MILLI_VERSION
        );
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function frontend_scripts() {
        if ( ! is_checkout() ) {
            return;
        }
        
        wp_enqueue_script(
            'hubspot-sync-milli-checkout',
            HUBSPOT_SYNC_MILLI_PLUGIN_URL . 'assets/js/checkout.js',
            array( 'jquery' ),
            HUBSPOT_SYNC_MILLI_VERSION,
            true
        );
        
        wp_enqueue_style(
            'hubspot-sync-milli-checkout',
            HUBSPOT_SYNC_MILLI_PLUGIN_URL . 'assets/css/checkout.css',
            array(),
            HUBSPOT_SYNC_MILLI_VERSION
        );
    }
    
    /**
     * Handle device sync hook
     */
    public function update_hubspot_device_data( $date_range = null ) {
        $this->log_debug( 'Starting device sync process' );
        
        // Get sync manager
        $sync_manager = new HubSpot_Sync_Milli_Sync_Manager( $this->settings, $this->hubspot_client );
        
        // Determine date range (default to last 2 days like old plugin)
        if ( ! $date_range ) {
            $date_range = array(
                'start' => date( 'Y-m-d', strtotime( '-2 days' ) ),
                'end' => date( 'Y-m-d' )
            );
        }
        
        // Get orders with serial numbers from the specified date range
        $orders = wc_get_orders( array(
            'status' => array( 'wc-processing', 'wc-completed', 'wc-on-hold' ),
            'date_created' => $date_range['start'] . '...' . $date_range['end'],
            'meta_key' => 'serial_numbers',
            'meta_compare' => 'EXISTS',
            'limit' => -1
        ) );
        
        $this->log_debug( sprintf( 'Found %d orders with serial numbers for device sync', count( $orders ) ) );
        
        if ( empty( $orders ) ) {
            $this->log_debug( 'No orders with serial numbers found for sync' );
            return;
        }
        
        $synced_orders = 0;
        $error_count = 0;
        
        foreach ( $orders as $order ) {
            try {
                $result = $sync_manager->sync_order_devices( $order );
                
                if ( $result && $result['success'] ) {
                    $synced_orders++;
                    $this->log_debug( sprintf( 'Successfully synced devices for order %d', $order->get_id() ) );
                } else if ( $result ) {
                    $error_count++;
                    $this->log_error( sprintf( 'Device sync failed for order %d: %s', $order->get_id(), $result['message'] ) );
                }
                
                // Rate limiting delay
                usleep( 100000 ); // 100ms
                
            } catch ( Exception $e ) {
                $error_count++;
                $this->log_error( sprintf( 'Device sync exception for order %d: %s', $order->get_id(), $e->getMessage() ) );
            }
        }
        
        $this->log_debug( sprintf( 'Device sync completed. Success: %d, Errors: %d', $synced_orders, $error_count ) );
    }
    
    /**
     * Update device company association when company is linked to order
     */
    public function update_device_company_association( $order_id, $company_id ) {
        $sync_manager = new HubSpot_Sync_Milli_Sync_Manager( $this->settings, $this->hubspot_client );
        return $sync_manager->update_device_company_association( $order_id, $company_id );
    }
    
    /**
     * Process a single serial number for an order
     * This hook allows external systems to add serial numbers to orders
     */
    public function process_single_serial_number( $order_id, $serial_number ) {
        if ( ! $this->should_sync() ) {
            return;
        }
        
        $this->log_debug( "Processing single serial number {$serial_number} for order {$order_id}" );
        
        $sync_manager = new HubSpot_Sync_Milli_Sync_Manager( $this->settings, $this->hubspot_client );
        
        $result = $sync_manager->update_serial_number( $order_id, $serial_number );
        
        if ( $result ) {
            $this->log_debug( "Successfully processed serial number {$serial_number} for order {$order_id}" );
        } else {
            $this->log_error( "Failed to process serial number {$serial_number} for order {$order_id}" );
        }
        
        return $result;
    }
    
    /**
     * Handle when order items are added (hook for external systems)
     */
    public function on_order_item_added( $item_id, $item, $order_id ) {
        // This hook can be used by external systems to trigger serial number processing
        // when new items are added to orders
        
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }
        
        $product_name = $item->get_name();
        
        // Check if this is a Milli product that needs serial number processing
        if ( stripos( $product_name, 'Milli Vaginal Dilator' ) !== false ) {
            $this->log_debug( "Milli product added to order {$order_id}, item {$item_id}" );
            
            // Hook for external systems to add serial numbers
            do_action( 'hubspot_sync_milli_milli_product_added', $order_id, $item_id, $product_name );
        }
    }
    
    /**
     * Batch update serial numbers from external data
     * This method can be called by external systems or cron jobs
     */
    public function batch_update_serial_numbers( $serial_numbers_data ) {
        if ( ! $this->should_sync() ) {
            return false;
        }
        
        $this->log_debug( 'Starting batch serial number update' );
        
        $sync_manager = new HubSpot_Sync_Milli_Sync_Manager( $this->settings, $this->hubspot_client );
        $result = $sync_manager->process_serial_numbers( $serial_numbers_data );
        
        if ( $result ) {
            $this->log_debug( 'Batch serial number update completed successfully' );
        } else {
            $this->log_error( 'Batch serial number update failed' );
        }
        
        return $result;
    }
    
    /**
     * Log error message
     */
    public function log_error( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[HubSpot Sync - Milli] ERROR: ' . $message );
        }
    }
    
    /**
     * Log debug message
     */
    public function log_debug( $message ) {
        if ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || ( $this->settings['debug_logging'] ?? false ) ) {
            error_log( '[HubSpot Sync - Milli] DEBUG: ' . $message );
        }
    }
    
    /**
     * Get plugin settings
     */
    public function get_settings() {
        return $this->settings;
    }
    
    /**
     * Get HubSpot client
     */
    public function get_hubspot_client() {
        return $this->hubspot_client;
    }
}