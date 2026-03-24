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
    
    /**
     * Search for deals with flexible filtering
     */
    public function search_deals( $search_params ) {
        if ( ! $this->client ) {
            return array( 'success' => false, 'message' => 'No HubSpot client' );
        }
        
        try {
            $filter_groups = array();
            
            if ( isset( $search_params['filters'] ) ) {
                $filters = array();
                foreach ( $search_params['filters'] as $filter_data ) {
                    $filter = new \HubSpot\Client\Crm\Deals\Model\Filter();
                    $filter->setOperator( $filter_data['operator'] )
                           ->setPropertyName( $filter_data['propertyName'] )
                           ->setValue( $filter_data['value'] );
                    $filters[] = $filter;
                }
                
                $filter_group = new \HubSpot\Client\Crm\Deals\Model\FilterGroup();
                $filter_group->setFilters( $filters );
                $filter_groups[] = $filter_group;
            }
            
            $search_request = new \HubSpot\Client\Crm\Deals\Model\PublicObjectSearchRequest();
            $search_request->setFilterGroups( $filter_groups );
            
            if ( isset( $search_params['properties'] ) ) {
                $search_request->setProperties( $search_params['properties'] );
            }
            
            $results = $this->client->crm()->deals()->searchApi()->doSearch( $search_request );
            $deals = $results->getResults();
            
            $formatted_deals = array();
            foreach ( $deals as $deal ) {
                $properties = $deal->getProperties();
                $formatted_deals[] = array(
                    'id' => $deal->getId(),
                    'properties' => $properties
                );
            }
            
            return array( 
                'success' => true, 
                'deals' => $formatted_deals 
            );
            
        } catch ( Exception $e ) {
            error_log( 'HubSpot Search Deals Error: ' . $e->getMessage() );
            return array( 
                'success' => false, 
                'message' => $e->getMessage() 
            );
        }
    }
    
    /**
     * Create or update contact
     */
    public function create_or_update_contact( $email, $contact_data ) {
        if ( ! $this->client ) {
            return array( 'success' => false, 'message' => 'No HubSpot client' );
        }
        
        try {
            // First, search for existing contact
            $existing_contact = $this->search_contact( $email );
            
            if ( $existing_contact ) {
                // Update existing contact
                $result = $this->upsert_contact( $contact_data, $existing_contact->getId() );
                return array( 
                    'success' => true, 
                    'contact_id' => $existing_contact->getId(),
                    'is_update' => true
                );
            } else {
                // Create new contact
                $result = $this->upsert_contact( $contact_data );
                if ( $result ) {
                    return array( 
                        'success' => true, 
                        'contact_id' => $result->getId(),
                        'is_update' => false
                    );
                }
            }
            
            return array( 'success' => false, 'message' => 'Failed to create/update contact' );
            
        } catch ( Exception $e ) {
            error_log( 'HubSpot Create/Update Contact Error: ' . $e->getMessage() );
            return array( 
                'success' => false, 
                'message' => $e->getMessage() 
            );
        }
    }
    
    /**
     * Create deal
     */
    public function create_deal( $deal_data ) {
        if ( ! $this->client ) {
            return array( 'success' => false, 'message' => 'No HubSpot client' );
        }
        
        try {
            $result = $this->upsert_deal( $deal_data );
            if ( $result ) {
                return array( 
                    'success' => true, 
                    'deal_id' => $result->getId() 
                );
            }
            
            return array( 'success' => false, 'message' => 'Failed to create deal' );
            
        } catch ( Exception $e ) {
            error_log( 'HubSpot Create Deal Error: ' . $e->getMessage() );
            return array( 
                'success' => false, 
                'message' => $e->getMessage() 
            );
        }
    }
    
    /**
     * Update deal
     */
    public function update_deal( $deal_id, $deal_data ) {
        if ( ! $this->client ) {
            return array( 'success' => false, 'message' => 'No HubSpot client' );
        }
        
        try {
            $result = $this->upsert_deal( $deal_data, $deal_id );
            return array( 'success' => true, 'deal_id' => $deal_id );
            
        } catch ( Exception $e ) {
            error_log( 'HubSpot Update Deal Error: ' . $e->getMessage() );
            return array( 
                'success' => false, 
                'message' => $e->getMessage() 
            );
        }
    }
    
    /**
     * Create association between objects
     */
    public function create_association( $from_object_type, $from_id, $to_object_type, $to_id, $association_type ) {
        if ( ! $this->client ) {
            return array( 'success' => false, 'message' => 'No HubSpot client' );
        }
        
        try {
            $association_spec = new \HubSpot\Client\Crm\Associations\V4\Model\AssociationSpec();
            $association_spec->setAssociationCategory( \HubSpot\Client\Crm\Associations\V4\Model\AssociationSpec::ASSOCIATION_CATEGORY_HUBSPOT_DEFINED )
                            ->setAssociationTypeId( $this->get_association_type_id( $association_type ) );
                            
            $batch_input = new \HubSpot\Client\Crm\Associations\V4\Model\BatchInputPublicAssociationMultiPost();
            $batch_input->setFrom( array( 'id' => $from_id ) )
                       ->setTo( array( 'id' => $to_id ) )
                       ->setTypes( array( $association_spec ) );
                       
            $this->client->crm()->associations()->v4()->batchApi()->create( 
                $from_object_type,
                $to_object_type, 
                array( $batch_input )
            );
            
            return array( 'success' => true );
            
        } catch ( Exception $e ) {
            error_log( 'HubSpot Create Association Error: ' . $e->getMessage() );
            return array( 
                'success' => false, 
                'message' => $e->getMessage() 
            );
        }
    }
    
    /**
     * Get association type ID for common association types
     */
    private function get_association_type_id( $association_type ) {
        $association_map = array(
            'deal_to_contact' => 3,
            'contact_to_deal' => 4,
            'deal_to_company' => 5,
            'company_to_deal' => 6
        );
        
        return $association_map[ $association_type ] ?? 3;
    }
}