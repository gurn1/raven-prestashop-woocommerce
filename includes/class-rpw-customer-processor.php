<?php
/**
 *  Process data for orders
 * 
 * @package raven-prestashop-woocommerce
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

class RPW_customer_processor extends Raven_PrestaShop_WooCommerce_Migrate {

    /**
	 * The single instance of the class.
	 *
	 * @since 1.0.0
	 */
	protected static $_instance = null;

    /**
     * Construct
     * 
     * @since 1.0.0
     */
    public function __construct() {
    }
    
    /**
	 * Main Instance.
	 *
	 * Ensures only one instance of WooCommerce is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
     * Get users
     * 
     * @since 1.0.0
     */
    public function import_users() {
        $users_count = 0;
        $imported_user_count = 0;

        $options    = $this->get_plugin_options();
        $connection = new RPW_prestashop_import();
        $query      = $connection->get_customers();
        $users_count = count($query);

        if($query) {
            foreach($query as $user) {
                $new_user_id = $this->import_user($user);  
                  
                if( ! is_wp_error($new_user_id) ) {
                    $imported_user_count++;
                    $imported_users[$user['id_customer']] = $new_user_id;
                }
            }
            update_option('rpw_users_added', $imported_users);
            
            // Add a date stamp to options
            $options['date_users_added'] = date('Y-m-d H:i:s');
            update_option($this->option_name, $options);

        }

        return array('users_found' => $users_count, 'users_imported' => $imported_user_count);

    }

    /**
     * Import user
     * 
     * @since 1.0.0
     */
    public function import_user($user) {
        $date = $user['date_add'];
        $password = wp_generate_password( 12, false );

        $userdata = array(
            'user_pass'         => $password,
            'user_login'        => $user['email'],
            //'user_nicename'     => '',
            'user_email'        => $user['email'],
            //'display_name'      => '',
            //'nickname'          => '',
            'first_name'        => $user['firstname'],
            'last_name'         => $user['lastname'],
            'user_registered'   => $date, // (string) Date the user registered. Format is 'Y-m-d H:i:s'.
            'role'              => 'customer'
        );

        $new_user_id = wp_insert_user($userdata);

        if( ! is_wp_error($new_user_id) ) {
            $connection     = new RPW_prestashop_import();
            $addresses      = $connection->get_customers_addresses($user['id_customer']);
            $address_count  = count($addresses);
            $address        = $addresses[0];
            
            // Update woocommerce customer data
            $customer       = new WC_customer( $new_user_id );                
            $country        = $connection->get_country_iso($address['id_country']);
            $country_iso    = $country['iso_code'];

            if( ! $address['phone'] ) {
                $phone = $address['phone_mobile'];
            } else {
                $phone = $address['phone'];
            }

            $customer->set_billing_first_name( $address['firstname'] );
            $customer->set_billing_last_name( $address['lastname'] );
            $customer->set_billing_country( $country_iso );
            $customer->set_billing_city( $address['city'] );
            $customer->set_billing_postcode( $address['postcode'] );
            // $customer->set_billing_state( $user[''] ); not used for 1.5
            $customer->set_billing_phone( $phone );
            $customer->set_billing_email( $address['email'] );
            $customer->set_billing_company( $address['company'] );
            $customer->set_billing_address_1( $address['address1'] );
            $customer->set_billing_address_2( $address['address2'] );
            $customer->set_shipping_first_name( $address['firstname'] );
            $customer->set_shipping_last_name( $address['lastname'] );
            $customer->set_shipping_company( $address['company'] );
            $customer->set_shipping_address_1( $address['address1'] );
            $customer->set_shipping_address_2( $address['address2'] );
            $customer->set_shipping_city( $address['city'] );
            $customer->set_shipping_postcode( $address['postcode'] );
            $customer->set_shipping_country( $country_iso );
            // $customer->set_shipping_state(); not used for 1.5
            $customer->set_shipping_phone( $phone );
            
            $customer->save();

            // Add user meta
            $meta = array(
                '_rpw_old_user_id'      => $user['id_customer'],
                '_rpw_gender'           => $user['id_gender'],
                '_rpw_birthday'         => $user['birthday'],
                '_rpw_other'            => $address['other']  
            );

            foreach($meta as $key => $value ) {
                add_user_meta( $new_user_id, $key, $value, true );
            }
        }
    }
}

function rpw_testing_users() {
    if( current_user_can('manage_options') ) {
        $connection = new RPW_customer_processor();
        //$connection->import_users();
    }
}
add_action('rpw_after_database_test', 'rpw_testing_users' );