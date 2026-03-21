<?php
/**
 * HubSpot API wrapper class
 *
 * @package HubSpot_Sync_Milli
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HubSpot_Sync_Milli_HubSpot_API {
    
    /**
     * API settings
     */
    private $settings;
    
    /**
     * HubSpot client
     */
    private $client;
    
    /**
     * Constructor
     */
    public function __construct( $settings ) {
        $this->settings = $settings;
        $this->init_client();
    }
    
    /**
     * Initialize HubSpot client
     */
    private function init_client() {
        if ( empty( $this->settings['api_token'] ) ) {
            return;
        }
        
        if ( class_exists( 'HubSpot\Factory' ) ) {
            try {
                $this->client = HubSpot\Factory::createWithAccessToken( $this->settings['api_token'] );
            } catch ( Exception $e ) {
                error_log( 'HubSpot API Client Error: ' . $e->getMessage() );
            }
        }
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        if ( ! $this->client ) {
            return array(
                'success' => false,
                'message' => __( 'Could not initialize HubSpot client. Check your API token.', 'hubspot-sync-milli' )
            );
        }
        
        try {
            // Try to get account info
            $account_info = $this->client->settings()->users()->usersApi()->getPage();
            
            return array(
                'success' => true,
                'message' => __( 'Connection successful!', 'hubspot-sync-milli' ),
                'data' => array(
                    'portal_id' => $account_info->getResults()[0]->getPortalId() ?? 'Unknown'
                )
            );
            
        } catch ( Exception $e ) {
            return array(
                'success' => false,
                'message' => sprintf( __( 'Connection failed: %s', 'hubspot-sync-milli' ), $e->getMessage() )
            );
        }
    }
    
    /**
     * Search for contact by email
     */
    public function search_contact( $email ) {
        if ( ! $this->client ) {
            return null;
        }
        
        try {
            $filter = new \HubSpot\Client\Crm\Contacts\Model\Filter();
            $filter->setOperator( 'EQ' )
                   ->setPropertyName( 'email' )
                   ->setValue( $email );
                   
            $filter_group = new \HubSpot\Client\Crm\Contacts\Model\FilterGroup();
            $filter_group->setFilters( [$filter] );
            
            $search_request = new \HubSpot\Client\Crm\Contacts\Model\PublicObjectSearchRequest();
            $search_request->setFilterGroups( [$filter_group] );
            
            $results = $this->client->crm()->contacts()->searchApi()->doSearch( $search_request );
            $contacts = $results->getResults();
            
            return ! empty( $contacts ) ? $contacts[0] : null;
            
        } catch ( Exception $e ) {
            error_log( 'HubSpot Search Contact Error: ' . $e->getMessage() );
            return null;
        }
    }
    
    /**
     * Create or update contact
     */
    public function upsert_contact( $contact_data, $contact_id = null ) {
        if ( ! $this->client ) {
            return false;
        }
        
        try {
            if ( $contact_id ) {
                // Update existing contact
                $contact_input = new \HubSpot\Client\Crm\Contacts\Model\SimplePublicObjectInput();
                $contact_input->setProperties( $contact_data );
                
                $contact = $this->client->crm()->contacts()->basicApi()->update( $contact_id, $contact_input );
            } else {
                // Create new contact
                $contact_input = new \HubSpot\Client\Crm\Contacts\Model\SimplePublicObjectInputForCreate();
                $contact_input->setProperties( $contact_data );
                
                $contact = $this->client->crm()->contacts()->basicApi()->create( $contact_input );
            }
            
            return $contact;
            
        } catch ( Exception $e ) {
            error_log( 'HubSpot Upsert Contact Error: ' . $e->getMessage() );
            return false;
        }
    }
    
    /**
     * Search for deal by unique cart ID
     */
    public function search_deal( $unique_cart_id ) {
        if ( ! $this->client ) {
            return null;
        }
        
        try {
            $filter = new \HubSpot\Client\Crm\Deals\Model\Filter();
            $filter->setOperator( 'EQ' )
                   ->setPropertyName( 'woocommerce_unique_cart_id' )
                   ->setValue( $unique_cart_id );
                   
            $filter_group = new \HubSpot\Client\Crm\Deals\Model\FilterGroup();
            $filter_group->setFilters( [$filter] );
            
            $search_request = new \HubSpot\Client\Crm\Deals\Model\PublicObjectSearchRequest();
            $search_request->setFilterGroups( [$filter_group] );
            
            $results = $this->client->crm()->deals()->searchApi()->doSearch( $search_request );
            $deals = $results->getResults();
            
            return ! empty( $deals ) ? $deals[0] : null;
            
        } catch ( Exception $e ) {
            error_log( 'HubSpot Search Deal Error: ' . $e->getMessage() );
            return null;
        }
    }
    
    /**
     * Create or update deal
     */
    public function upsert_deal( $deal_data, $deal_id = null, $associations = array() ) {
        if ( ! $this->client ) {
            return false;
        }
        
        try {
            if ( $deal_id ) {
                // Update existing deal
                $deal_input = new \HubSpot\Client\Crm\Deals\Model\SimplePublicObjectInput();
                $deal_input->setProperties( $deal_data );
                
                $deal = $this->client->crm()->deals()->basicApi()->update( $deal_id, $deal_input );
            } else {
                // Create new deal
                $deal_input = new \HubSpot\Client\Crm\Deals\Model\SimplePublicObjectInputForCreate();
                $deal_input->setProperties( $deal_data );
                
                if ( ! empty( $associations ) ) {
                    $deal_input->setAssociations( $associations );
                }
                
                $deal = $this->client->crm()->deals()->basicApi()->create( $deal_input );
            }
            
            return $deal;
            
        } catch ( Exception $e ) {
            error_log( 'HubSpot Upsert Deal Error: ' . $e->getMessage() );
            return false;
        }
    }
    
    /**
     * Search for company by name
     */
    public function search_company( $company_name ) {
        if ( ! $this->client ) {
            return null;
        }
        
        try {
            $filter = new \HubSpot\Client\Crm\Companies\Model\Filter();
            $filter->setOperator( 'EQ' )
                   ->setPropertyName( 'name' )
                   ->setValue( $company_name );
                   
            $filter_group = new \HubSpot\Client\Crm\Companies\Model\FilterGroup();
            $filter_group->setFilters( [$filter] );
            
            $search_request = new \HubSpot\Client\Crm\Companies\Model\PublicObjectSearchRequest();
            $search_request->setFilterGroups( [$filter_group] );
            
            $results = $this->client->crm()->companies()->searchApi()->doSearch( $search_request );
            $companies = $results->getResults();
            
            return ! empty( $companies ) ? $companies[0] : null;
            
        } catch ( Exception $e ) {
            error_log( 'HubSpot Search Company Error: ' . $e->getMessage() );
            return null;
        }
    }
    
    /**
     * Create company
     */
    public function create_company( $company_data ) {
        if ( ! $this->client ) {
            return false;
        }
        
        try {
            $company_input = new \HubSpot\Client\Crm\Companies\Model\SimplePublicObjectInputForCreate();
            $company_input->setProperties( $company_data );
            
            $company = $this->client->crm()->companies()->basicApi()->create( $company_input );
            return $company;
            
        } catch ( Exception $e ) {
            error_log( 'HubSpot Create Company Error: ' . $e->getMessage() );
            return false;
        }
    }
    
    /**
     * Create device custom object
     */
    public function create_device( $device_properties, $associations = array() ) {
        if ( ! $this->client ) {
            return false;
        }
        
        try {
            $device_input = new \HubSpot\Client\Crm\Objects\Model\SimplePublicObjectInputForCreate();
            $device_input->setProperties( $device_properties );
            
            // Add associations if provided
            if ( ! empty( $associations ) ) {
                $association_objects = array();
                
                foreach ( $associations as $association ) {
                    $association_obj = new \HubSpot\Client\Crm\Objects\Model\PublicAssociationsForObject();
                    
                    $to_object = new \HubSpot\Client\Crm\Objects\Model\PublicObjectId();
                    $to_object->setId( $association['to']['id'] );
                    $association_obj->setTo( $to_object );
                    
                    $association_types = array();
                    foreach ( $association['types'] as $type ) {
                        $association_spec = new \HubSpot\Client\Crm\Objects\Model\AssociationSpec();
                        $association_spec->setAssociationCategory( $type['associationCategory'] );
                        $association_spec->setAssociationTypeId( $type['associationTypeId'] );
                        $association_types[] = $association_spec;
                    }
                    
                    $association_obj->setTypes( $association_types );
                    $association_objects[] = $association_obj;
                }
                
                $device_input->setAssociations( $association_objects );
            }
            
            // Create device using custom object type 'devices'
            $device = $this->client->crm()->objects()->basicApi()->create( 'devices', $device_input );
            return $device;
            
        } catch ( Exception $e ) {
            error_log( 'HubSpot Create Device Error: ' . $e->getMessage() );
            return false;
        }
    }
    
    /**
     * Get device by serial number
     */
    public function get_device_by_serial( $serial_number ) {
        if ( ! $this->client ) {
            return false;
        }
        
        try {
            $search_request = new \HubSpot\Client\Crm\Objects\Model\PublicObjectSearchRequest();
            
            // Create filter for serial_numbers property
            $filter = new \HubSpot\Client\Crm\Objects\Model\Filter();
            $filter->setOperator( 'EQ' );
            $filter->setPropertyName( 'serial_numbers' );
            $filter->setValue( $serial_number );
            
            $filter_group = new \HubSpot\Client\Crm\Objects\Model\FilterGroup();
            $filter_group->setFilters( [ $filter ] );
            
            $search_request->setFilterGroups( [ $filter_group ] );
            $search_request->setProperties( [ 'serial_numbers' ] );
            $search_request->setLimit( 1 );
            
            $response = $this->client->crm()->objects()->searchApi()->doSearch( 'devices', $search_request );
            
            if ( $response->getResults() && count( $response->getResults() ) > 0 ) {
                return $response->getResults()[0];
            }
            
            return false;
            
        } catch ( Exception $e ) {
            error_log( 'HubSpot Get Device Error: ' . $e->getMessage() );
            return false;
        }
    }
    
    /**
     * Create association between device and other object
     */
    public function create_device_association( $device_id, $target_id, $target_type, $association_type_id ) {
        if ( ! $this->client ) {
            return false;
        }
        
        try {
            $association_spec = new \HubSpot\Client\Crm\Associations\V4\Model\PublicAssociationMultiPost();
            $association_spec->setFromObjectType( 'devices' );
            $association_spec->setToObjectType( $target_type );
            $association_spec->setFromObjectId( $device_id );
            $association_spec->setToObjectId( $target_id );
            $association_spec->setAssociationTypeId( $association_type_id );
            
            $result = $this->client->crm()->associations()->v4()->basicApi()->create( $association_spec );
            return $result;
            
        } catch ( Exception $e ) {
            error_log( 'HubSpot Create Device Association Error: ' . $e->getMessage() );
            return false;
        }
    }
    
    /**
     * Get client instance
     */
    public function get_client() {
        return $this->client;
    }
}