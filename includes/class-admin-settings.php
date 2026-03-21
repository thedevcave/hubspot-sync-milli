<?php
/**
 * Admin settings class
 *
 * @package HubSpot_Sync_Milli
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HubSpot_Sync_Milli_Admin_Settings {
    
    /**
     * Settings array
     */
    private $settings;
    
    /**
     * Constructor
     */
    public function __construct( $settings ) {
        $this->settings = $settings;
    }
    
    /**
     * Render admin page
     */
    public function render_page() {
        // Get current tab from URL parameter
        $current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
        
        // Validate tab
        $valid_tabs = array( 'general', 'contact-sync', 'deal-sync', 'advanced' );
        if ( ! in_array( $current_tab, $valid_tabs ) ) {
            $current_tab = 'general';
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <?php settings_errors(); ?>
            
            <!-- Tab Navigation -->
            <div class="nav-tab-wrapper">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=hubspot-sync-milli&tab=general' ) ); ?>" 
                   class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'General', 'hubspot-sync-milli' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=hubspot-sync-milli&tab=contact-sync' ) ); ?>" 
                   class="nav-tab <?php echo $current_tab === 'contact-sync' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Contact Sync', 'hubspot-sync-milli' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=hubspot-sync-milli&tab=deal-sync' ) ); ?>" 
                   class="nav-tab <?php echo $current_tab === 'deal-sync' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Deal Sync', 'hubspot-sync-milli' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=hubspot-sync-milli&tab=advanced' ) ); ?>" 
                   class="nav-tab <?php echo $current_tab === 'advanced' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Advanced', 'hubspot-sync-milli' ); ?>
                </a>
            </div>
            
            <!-- Tab Content -->
            <form method="post" action="options.php">
                <?php
                settings_fields( 'hubspot_sync_milli_settings_group' );
                do_settings_sections( 'hubspot_sync_milli_settings_group' );
                
                // Add hidden field to preserve tab on redirect
                echo '<input type="hidden" name="_wp_http_referer" value="' . esc_attr( admin_url( 'admin.php?page=hubspot-sync-milli&tab=' . $current_tab ) ) . '" />';
                
                // Render current tab content
                switch ( $current_tab ) {
                    case 'contact-sync':
                        $this->render_contact_sync_tab();
                        break;
                    case 'deal-sync':
                        $this->render_deal_sync_tab();
                        break;
                    case 'advanced':
                        $this->render_advanced_tab();
                        break;
                    default:
                        $this->render_general_tab();
                        break;
                }
                ?>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render general settings tab
     */
    private function render_general_tab() {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="api_token"><?php esc_html_e( 'HubSpot API Token', 'hubspot-sync-milli' ); ?></label>
                </th>
                <td>
                    <input type="password" 
                           id="api_token" 
                           name="hubspot_sync_milli_settings[api_token]" 
                           value="<?php echo esc_attr( $this->settings['api_token'] ?? '' ); ?>" 
                           class="regular-text" 
                           autocomplete="new-password" />
                    <p class="description">
                        <?php esc_html_e( 'Enter your HubSpot private app access token.', 'hubspot-sync-milli' ); ?>
                        <a href="https://developers.hubspot.com/docs/api/private-apps" target="_blank"><?php esc_html_e( 'Learn how to create one.', 'hubspot-sync-milli' ); ?></a>
                    </p>
                    <button type="button" id="test-connection" class="button" <?php echo empty( $this->settings['api_token'] ) ? 'disabled' : ''; ?>>
                        <?php esc_html_e( 'Test Connection', 'hubspot-sync-milli' ); ?>
                    </button>
                    <span id="connection-status"></span>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="site_environment"><?php esc_html_e( 'Site Environment', 'hubspot-sync-milli' ); ?></label>
                </th>
                <td>
                    <select id="site_environment" name="hubspot_sync_milli_settings[site_environment]">
                        <option value="staging" <?php selected( $this->settings['site_environment'] ?? 'staging', 'staging' ); ?>><?php esc_html_e( 'Staging', 'hubspot-sync-milli' ); ?></option>
                        <option value="production" <?php selected( $this->settings['site_environment'] ?? '', 'production' ); ?>><?php esc_html_e( 'Production', 'hubspot-sync-milli' ); ?></option>
                        <option value="development" <?php selected( $this->settings['site_environment'] ?? '', 'development' ); ?>><?php esc_html_e( 'Development', 'hubspot-sync-milli' ); ?></option>
                    </select>
                    <p class="description"><?php esc_html_e( 'Select your site environment. This will be used as a prefix for deal names.', 'hubspot-sync-milli' ); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="site_prefix"><?php esc_html_e( 'Site Prefix', 'hubspot-sync-milli' ); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="site_prefix" 
                           name="hubspot_sync_milli_settings[site_prefix]" 
                           value="<?php echo esc_attr( $this->settings['site_prefix'] ?? '' ); ?>" 
                           class="regular-text" />
                    <p class="description"><?php esc_html_e( 'Optional custom prefix for deal names (e.g., "StagingTest", "Production").', 'hubspot-sync-milli' ); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="default_owner_id"><?php esc_html_e( 'Default HubSpot Owner ID', 'hubspot-sync-milli' ); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="default_owner_id" 
                           name="hubspot_sync_milli_settings[owner_id]" 
                           value="<?php echo esc_attr( $this->settings['owner_id'] ?? '' ); ?>" 
                           class="regular-text" />
                    <p class="description"><?php esc_html_e( 'Default owner ID for new contacts and deals.', 'hubspot-sync-milli' ); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render contact sync settings tab
     */
    private function render_contact_sync_tab() {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'Enable Contact Sync', 'hubspot-sync-milli' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="hubspot_sync_milli_settings[sync_contact_fields]" 
                               value="1" 
                               <?php checked( $this->settings['sync_contact_fields'] ?? true ); ?> />
                        <?php esc_html_e( 'Sync custom checkout fields to HubSpot contact properties', 'hubspot-sync-milli' ); ?>
                    </label>
                </td>
            </tr>
        </table>
        
        <h3><?php esc_html_e( 'Field Mapping', 'hubspot-sync-milli' ); ?></h3>
        <p><?php esc_html_e( 'Map WooCommerce checkout fields to HubSpot contact property internal names.', 'hubspot-sync-milli' ); ?></p>
        
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Checkout Field', 'hubspot-sync-milli' ); ?></th>
                    <th><?php esc_html_e( 'HubSpot Property Internal Name', 'hubspot-sync-milli' ); ?></th>
                    <th><?php esc_html_e( 'Description', 'hubspot-sync-milli' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $fields = array(
                    'acquisition_source' => array(
                        'label' => __( 'How did you hear about us?', 'hubspot-sync-milli' ),
                        'description' => __( 'Customer acquisition source', 'hubspot-sync-milli' ),
                        'default' => 'how_did_you_hear_about_us_consumer'
                    ),
                    'clinician_name' => array(
                        'label' => __( 'Clinician Name', 'hubspot-sync-milli' ),
                        'description' => __( 'Referring healthcare provider name', 'hubspot-sync-milli' ),
                        'default' => 'referring_clinician'
                    ),
                    'clinic_state' => array(
                        'label' => __( 'Clinic State', 'hubspot-sync-milli' ),
                        'description' => __( 'Provider/clinic state location', 'hubspot-sync-milli' ),
                        'default' => 'referring_state'
                    ),
                    'clinic_name' => array(
                        'label' => __( 'Clinic Name', 'hubspot-sync-milli' ),
                        'description' => __( 'Referring clinic/practice name', 'hubspot-sync-milli' ),
                        'default' => 'referring_clinic'
                    ),
                    'talked_to_provider' => array(
                        'label' => __( 'Talked to Provider', 'hubspot-sync-milli' ),
                        'description' => __( 'Has talked to healthcare provider (Yes/No)', 'hubspot-sync-milli' ),
                        'default' => 'have_you_talked_to_healthcare_provider'
                    ),
                    'provider_referred' => array(
                        'label' => __( 'Provider Referred', 'hubspot-sync-milli' ),
                        'description' => __( 'Was referred by provider (Yes/No)', 'hubspot-sync-milli' ),
                        'default' => 'did_your_provider_refer_you_to_milli_'
                    )
                );
                
                foreach ( $fields as $field_key => $field_data ) {
                    $value = $this->settings['contact_field_mapping'][ $field_key ] ?? $field_data['default'];
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html( $field_data['label'] ); ?></strong></td>
                        <td>
                            <input type="text" 
                                   name="hubspot_sync_milli_settings[contact_field_mapping][<?php echo esc_attr( $field_key ); ?>]" 
                                   value="<?php echo esc_attr( $value ); ?>" 
                                   class="regular-text" />
                        </td>
                        <td><?php echo esc_html( $field_data['description'] ); ?></td>
                    </tr>
                    <?php
                }
                ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Render deal sync settings tab
     */
    private function render_deal_sync_tab() {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'Enable Deal Sync', 'hubspot-sync-milli' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="hubspot_sync_milli_settings[sync_deal_fields]" 
                               value="1" 
                               <?php checked( $this->settings['sync_deal_fields'] ?? true ); ?> />
                        <?php esc_html_e( 'Sync WooCommerce orders to HubSpot deals', 'hubspot-sync-milli' ); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="deal_pipeline"><?php esc_html_e( 'Deal Pipeline ID', 'hubspot-sync-milli' ); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="deal_pipeline" 
                           name="hubspot_sync_milli_settings[deal_pipeline]" 
                           value="<?php echo esc_attr( $this->settings['deal_pipeline'] ?? '' ); ?>" 
                           class="regular-text" />
                    <p class="description"><?php esc_html_e( 'HubSpot deal pipeline ID or internal name.', 'hubspot-sync-milli' ); ?></p>
                </td>
            </tr>
        </table>
        
        <h3><?php esc_html_e( 'Deal Stage Mapping', 'hubspot-sync-milli' ); ?></h3>
        <p><?php esc_html_e( 'Map WooCommerce order statuses to HubSpot deal stages.', 'hubspot-sync-milli' ); ?></p>
        
        <table class="form-table">
            <?php
            $stages = array(
                'won' => __( 'Order Completed (Won)', 'hubspot-sync-milli' ),
                'processing' => __( 'Order Processing', 'hubspot-sync-milli' ),
                'lost' => __( 'Order Lost', 'hubspot-sync-milli' ),
                'cancelled' => __( 'Order Cancelled', 'hubspot-sync-milli' ),
                'refunded' => __( 'Order Refunded', 'hubspot-sync-milli' ),
                'failed' => __( 'Order Failed', 'hubspot-sync-milli' ),
                'abandoned' => __( 'Abandoned Cart', 'hubspot-sync-milli' )
            );
            
            foreach ( $stages as $stage_key => $stage_label ) {
                $value = $this->settings['deal_stages'][ $stage_key ] ?? '';
                ?>
                <tr>
                    <th scope="row">
                        <label for="deal_stage_<?php echo esc_attr( $stage_key ); ?>">
                            <?php echo esc_html( $stage_label ); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" 
                               id="deal_stage_<?php echo esc_attr( $stage_key ); ?>" 
                               name="hubspot_sync_milli_settings[deal_stages][<?php echo esc_attr( $stage_key ); ?>]" 
                               value="<?php echo esc_attr( $value ); ?>" 
                               class="regular-text" />
                    </td>
                </tr>
                <?php
            }
            ?>
        </table>
        <?php
    }
    
    /**
     * Render advanced settings tab
     */
    private function render_advanced_tab() {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'Sync Triggers', 'hubspot-sync-milli' ); ?></th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text"><?php esc_html_e( 'Order statuses that trigger sync', 'hubspot-sync-milli' ); ?></legend>
                        <?php
                        if ( function_exists( 'wc_get_order_statuses' ) ) {
                            $wc_statuses = wc_get_order_statuses();
                            $sync_statuses = $this->settings['sync_on_status_change'] ?? array( 'processing', 'completed' );
                            
                            foreach ( $wc_statuses as $status_key => $status_label ) {
                                $status_key = str_replace( 'wc-', '', $status_key );
                                ?>
                                <label>
                                    <input type="checkbox" 
                                           name="hubspot_sync_milli_settings[sync_on_status_change][]" 
                                           value="<?php echo esc_attr( $status_key ); ?>" 
                                           <?php checked( in_array( $status_key, $sync_statuses, true ) ); ?> />
                                    <?php echo esc_html( $status_label ); ?>
                                </label><br>
                                <?php
                            }
                        } else {
                            echo '<p>' . esc_html__( 'WooCommerce is not active. Install and activate WooCommerce to configure sync triggers.', 'hubspot-sync-milli' ) . '</p>';
                        }
                        ?>
                        <p class="description"><?php esc_html_e( 'Select which order status changes should trigger a sync to HubSpot.', 'hubspot-sync-milli' ); ?></p>
                    </fieldset>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="serial_numbers_folder_id"><?php esc_html_e( 'Serial Numbers Folder ID', 'hubspot-sync-milli' ); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="serial_numbers_folder_id" 
                           name="hubspot_sync_milli_settings[serial_numbers_folder_id]" 
                           value="<?php echo esc_attr( $this->settings['serial_numbers_folder_id'] ?? '' ); ?>" 
                           class="regular-text" />
                    <p class="description"><?php esc_html_e( 'HubSpot folder ID for storing serial number files.', 'hubspot-sync-milli' ); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php esc_html_e( 'Association IDs', 'hubspot-sync-milli' ); ?></th>
                <td>
                    <table class="widefat">
                        <tbody>
                            <tr>
                                <th><?php esc_html_e( 'Deal to Device', 'hubspot-sync-milli' ); ?></th>
                                <td>
                                    <input type="text" 
                                           name="hubspot_sync_milli_settings[association_ids][deal_to_device]" 
                                           value="<?php echo esc_attr( $this->settings['association_ids']['deal_to_device'] ?? '' ); ?>" 
                                           class="regular-text" />
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Contact to Device', 'hubspot-sync-milli' ); ?></th>
                                <td>
                                    <input type="text" 
                                           name="hubspot_sync_milli_settings[association_ids][contact_to_device]" 
                                           value="<?php echo esc_attr( $this->settings['association_ids']['contact_to_device'] ?? '' ); ?>" 
                                           class="regular-text" />
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Company to Device', 'hubspot-sync-milli' ); ?></th>
                                <td>
                                    <input type="text" 
                                           name="hubspot_sync_milli_settings[association_ids][company_to_device]" 
                                           value="<?php echo esc_attr( $this->settings['association_ids']['company_to_device'] ?? '' ); ?>" 
                                           class="regular-text" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="description"><?php esc_html_e( 'Custom object association type IDs in HubSpot.', 'hubspot-sync-milli' ); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label><?php esc_html_e( 'Debug Logging', 'hubspot-sync-milli' ); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="hubspot_sync_milli_settings[debug_logging]" 
                               value="1" 
                               <?php checked( $this->settings['debug_logging'] ?? false ); ?> />
                        <?php esc_html_e( 'Enable detailed debug logging', 'hubspot-sync-milli' ); ?>
                    </label>
                    <p class="description"><?php esc_html_e( 'Logs detailed information about sync operations for debugging purposes.', 'hubspot-sync-milli' ); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
}