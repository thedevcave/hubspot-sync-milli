<?php
/**
 * Plugin Update Checker
 *
 * Handles automatic updates from GitHub releases
 *
 * @package HubSpot_Sync_Milli
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HubSpot_Sync_Milli_Updater {
    
    /**
     * GitHub repository owner
     */
    private $github_user = 'thedevcave';
    
    /**
     * GitHub repository name
     */
    private $github_repo = 'hubspot-sync-milli';
    
    /**
     * Plugin file path
     */
    private $plugin_file;
    
    /**
     * Plugin slug
     */
    private $plugin_slug;
    
    /**
     * Current plugin version
     */
    private $current_version;
    
    /**
     * Constructor
     */
    public function __construct( $plugin_file ) {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = plugin_basename( $plugin_file );
        $this->current_version = HUBSPOT_SYNC_MILLI_VERSION;
        
        add_action( 'init', array( $this, 'init' ) );
    }
    
    /**
     * Initialize update checker
     */
    public function init() {
        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
        add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
        add_filter( 'upgrader_source_selection', array( $this, 'fix_source_dir' ), 10, 3 );
    }
    
    /**
     * Check for plugin updates
     */
    public function check_for_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }
        
        // Get latest release info
        $latest_version = $this->get_latest_release();
        
        if ( ! $latest_version ) {
            return $transient;
        }
        
        // Compare versions
        if ( version_compare( $this->current_version, $latest_version['version'], '<' ) ) {
            $plugin_data = get_plugin_data( $this->plugin_file );
            
            $transient->response[ $this->plugin_slug ] = (object) array(
                'slug' => dirname( $this->plugin_slug ),
                'plugin' => $this->plugin_slug,
                'new_version' => $latest_version['version'],
                'url' => $latest_version['details_url'],
                'package' => $latest_version['download_url'],
                'tested' => $plugin_data['Tested up to'] ?? '',
                'requires_php' => $plugin_data['Requires PHP'] ?? '',
            );
        }
        
        return $transient;
    }
    
    /**
     * Get plugin info for update screen
     */
    public function plugin_info( $result, $action, $args ) {
        if ( $action !== 'plugin_information' || $args->slug !== dirname( $this->plugin_slug ) ) {
            return $result;
        }
        
        $latest_version = $this->get_latest_release();
        if ( ! $latest_version ) {
            return $result;
        }
        
        $plugin_data = get_plugin_data( $this->plugin_file );
        
        return (object) array(
            'slug' => dirname( $this->plugin_slug ),
            'name' => $plugin_data['Name'],
            'version' => $latest_version['version'],
            'author' => $plugin_data['Author'],
            'homepage' => $plugin_data['Plugin URI'],
            'requires' => $plugin_data['Requires at least'] ?? '',
            'tested' => $plugin_data['Tested up to'] ?? '',
            'requires_php' => $plugin_data['Requires PHP'] ?? '',
            'download_link' => $latest_version['download_url'],
            'sections' => array(
                'description' => $plugin_data['Description'],
                'installation' => 'Upload the plugin files to the `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen directly.',
                'changelog' => $this->format_changelog( $latest_version ),
            ),
            'banners' => array(),
            'icons' => array(),
        );
    }
    
    /**
     * Fix source directory during update
     * Ensures the plugin folder is named correctly regardless of how GitHub packages it
     */
    public function fix_source_dir( $source, $remote_source, $upgrader ) {
        global $wp_filesystem;
        
        // Only process updates for this plugin
        if ( ! isset( $upgrader->skin->plugin ) || $upgrader->skin->plugin !== $this->plugin_slug ) {
            return $source;
        }
        
        $this->log_debug( "Fixing source directory for update. Source: {$source}" );
        
        // Expected plugin folder name (without version numbers)
        $expected_folder = dirname( $this->plugin_slug ); // 'hubspot-sync-milli'
        $corrected_source = $remote_source . '/' . $expected_folder . '/';
        
        // Check if source is already named correctly
        if ( basename( rtrim( $source, '/' ) ) === $expected_folder ) {
            $this->log_debug( "Source directory already correctly named: {$source}" );
            return $source;
        }
        
        // Check if the corrected path already exists (avoid conflicts)
        if ( $wp_filesystem->exists( $corrected_source ) ) {
            $this->log_debug( "Corrected source path already exists, removing old version: {$corrected_source}" );
            $wp_filesystem->delete( $corrected_source, true );
        }
        
        // Move to correct folder name
        if ( $wp_filesystem->move( $source, $corrected_source ) ) {
            $this->log_debug( "Successfully moved source to: {$corrected_source}" );
            
            // Verify the main plugin file exists in the correct location
            $main_plugin_file = $corrected_source . 'hubspot-sync-milli.php';
            if ( ! $wp_filesystem->exists( $main_plugin_file ) ) {
                $this->log_error( "Main plugin file not found after move: {$main_plugin_file}" );
                return $source; // Fall back to original source
            }
            
            return $corrected_source;
        } else {
            $this->log_error( "Failed to move source directory from {$source} to {$corrected_source}" );
            return $source;
        }
    }
    
    /**
     * Get latest release from GitHub
     */
    private function get_latest_release() {
        // Check cache first
        $cache_key = 'hubspot_sync_milli_latest_release';
        $cached = get_transient( $cache_key );
        
        if ( $cached !== false ) {
            return $cached;
        }
        
        // Fetch from GitHub API
        $api_url = "https://api.github.com/repos/{$this->github_user}/{$this->github_repo}/releases/latest";
        
        $response = wp_remote_get( $api_url, array(
            'timeout' => 30,
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url()
            )
        ) );
        
        if ( is_wp_error( $response ) ) {
            $this->log_error( 'Update check failed: ' . $response->get_error_message() );
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            $this->log_error( "Update check failed with response code: {$response_code}" );
            return false;
        }
        
        $body = wp_remote_retrieve_body( $response );
        $release_data = json_decode( $body, true );
        
        if ( ! $release_data || ! isset( $release_data['tag_name'] ) ) {
            $this->log_error( 'Invalid release data received from GitHub' );
            return false;
        }
        
        // Find the plugin zip asset
        $download_url = null;
        if ( isset( $release_data['assets'] ) && is_array( $release_data['assets'] ) ) {
            foreach ( $release_data['assets'] as $asset ) {
                if ( $asset['name'] === 'hubspot-sync-milli.zip' ) {
                    $download_url = $asset['browser_download_url'];
                    break;
                }
            }
        }
        
        if ( ! $download_url ) {
            $this->log_error( 'Plugin zip file not found in release assets' );
            return false;
        }
        
        $latest_version = array(
            'version' => ltrim( $release_data['tag_name'], 'v' ),
            'download_url' => $download_url,
            'details_url' => $release_data['html_url'],
            'release_notes' => $release_data['body'] ?? '',
            'published_at' => $release_data['published_at'] ?? '',
        );
        
        // Cache for 12 hours
        set_transient( $cache_key, $latest_version, 12 * HOUR_IN_SECONDS );
        
        $this->log_debug( "Latest release found: {$latest_version['version']}" );
        
        return $latest_version;
    }
    
    /**
     * Format changelog for plugin info
     */
    private function format_changelog( $latest_version ) {
        $changelog = "<h3>Version {$latest_version['version']}</h3>";
        
        if ( ! empty( $latest_version['release_notes'] ) ) {
            $changelog .= '<p>' . esc_html( $latest_version['release_notes'] ) . '</p>';
        }
        
        if ( ! empty( $latest_version['published_at'] ) ) {
            $changelog .= '<p><em>Released: ' . date( 'F j, Y', strtotime( $latest_version['published_at'] ) ) . '</em></p>';
        }
        
        return $changelog;
    }
    
    /**
     * Log debug message
     */
    private function log_debug( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[HubSpot Sync - Milli Updater] DEBUG: ' . $message );
        }
    }
    
    /**
     * Log error message
     */
    private function log_error( $message ) {
        error_log( '[HubSpot Sync - Milli Updater] ERROR: ' . $message );
    }
}