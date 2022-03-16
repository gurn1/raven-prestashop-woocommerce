<?php
/**
 *  Process data for orders
 * 
 * @package raven-prestashop-woocommerce
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

class RPW_order_processor extends Raven_PrestaShop_WooCommerce_Migrate {

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
        $options = $this->get_plugin_options();
    }

    /**
     * Get the imported orders
     * 
     * @since 1.0.0
     */
    public function get_imported_orders() {
        return $this->get_imported_ps_posts($meta_key = '_rpw_old_order_id');
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
     * Get product orders
     * 
     * @since 1.0.0
     */
    public function import_orders() {
        $order_count = 0;
        $imported_order_count = 0;

        // Check everything else uploaded before proceeding
        if( ! get_option('rpw_categories_added') || ! get_option('rpw_products_added') || ! get_option('rpw_users_added') ) {
            return array('error' => 'Error: Please check categories, products, and users have been migrated before adding orders');
        }

        $options    = $this->get_plugin_options();
        $connection = new RPW_prestashop_import();
        $query      = $connection->get_shop_orders();
        $order_count = count($query);

        $imported_orders = $this->get_imported_orders();

        if($query) {
            foreach($query as $order) {
                if( ! in_array($order['id_order'], array_keys($imported_orders)) ) {
                    $new_order_id = $this->import_order($order);
                    
                    if( ! is_wp_error($new_order_id) ) {
                        $imported_order_count++;
                        $imported_orders[$order['id_order']] = $new_order_id;
                    }
                }
            }
            update_option('rpw_orders_added', $imported_orders);

            // Add a date stamp to options
            $options['date_orders_added'] = date('Y-m-d H:i:s');
            update_option($this->option_name, $options);
        }
        
        return array('orders_found' => $order_count, 'orders_imported' => $imported_order_count);
    }

    /**
     * Import order
     * 
     * @since 1.0.0
     */
    public function import_order($order) {
        $connection = new RPW_prestashop_import();

        $date = $order['date_add'];
        $status = 'wc-completed';
        $created_via = 'migration';

        // Get customer
        $old_customer = $connection->get_customer( $order['id_customer'] );
        $email_address = isset($old_customer['email']) ? $old_customer['email'] : '';
        $customer_id = $this->get_user_id_from_meta('_rpw_old_user_id', $order['id_customer']);

        // Get addresses
        $billing_address = $connection->get_shop_order_address($order['id_address_invoice']);
        $billing_address['email'] = $email_address;

        if( $order['id_address_invoice'] != $order['id_address_delivery']) {
            $shipping_address = $connection->get_shop_order_address($order['id_address_delivery']);
            $shipping_address['email'] = $email_address;
        } else {
            $shipping_address = $billing_address;
        }

        // format addresses
        $new_billing_address = $this->format_address($billing_address);
        $new_shipping_address = $this->format_address($shipping_address);

        // Get products
        $products = $connection->get_shop_order_products($order['id_order']); 

        // Get order messages
        $order_notes = $connection->get_shop_order_messages($order['id_order']);
    
        $args = array(
            'ID'                => $order['id_order'],
            'created_via'       => $created_via,
            'date'              => $date,
            'status'            => $status,
            'customer_id'       => $customer_id,
            'billing_address'   => $new_billing_address,
            'shipping_address'  => $new_shipping_address,
            'total_shipping'    => (float) $order['total_shipping'],
            'total_discounts'   => (float) $order['total_discounts'],
            'total_paid'        => (float) $order['total_paid'],
            'products'          => $products,
            'order_notes'       => $order_notes
        );

        $new_order_id = $this->set_order_data($args);
        
        // Post Meta
        $meta = array(
            '_rpw_old_order_id'       => $order['id_order'],
        );

        foreach($meta as $key => $value) {
            add_post_meta($new_order_id, $key, $value, true); 
        }
    }

    /**
     * Set Order data
     * 
     * @since 1.0.0
     */
    public function set_order_data($args) {
        $order = new WC_Order();

        $order->set_created_via( $args['created_via'] );
        $order->set_status( $args['status'] );
        $order->set_date_created( $args['date'] );
        $order->set_date_completed( $args['date'] );
        $order->set_date_paid( $args['date'] );
        
        // Customer
        $order->set_customer_id( $args['customer_id'] );
        $order->set_address( $args['billing_address'], 'billing');
        $order->set_address( $args['shipping_address'], 'shipping');

        // Products
        if($args['products']) {
            foreach( $args['products'] as $product ) {
                // check postmeta for matching old post id, and return new post id
                $new_post_id = $this->get_wp_post_id_from_meta('_rpw_old_product_id', $product['product_id']);

                if( $new_post_id ) {
                    $listed_product = wc_get_product($new_post_id);
                    $item = new WC_Order_Item_Product();

                    $item->set_props( array(
                        'name'          => $listed_product->get_name(),
                        'product_id'    => $listed_product->get_id(),
                        'quantity'      => (int) $product['product_quantity'],
                        'total'         => (float) $product['product_price'] 
                    ) );

                    // Add a qunatity to the item before the order saves. It will be removed upon order save
                    update_post_meta( $listed_product->get_id(), '_stock', 1);
                } else {
                    $item = new WC_Order_Item_Fee();

                    $item->set_props( array(
                        'name'          => utf8_encode($product['product_name']),
                        'amount'        => (float) $product['product_price'],
                        'total'         => (float) $product['product_price']
                    ) );
                }

                $item->save();

                // Add Fee item to the order
                $order->add_item( $item );
            }
        }
    
        // Shipping
        $order->set_shipping_total( (float) $args['total_shipping'] );

        // Pricing
        $order->set_currency( get_woocommerce_currency() );
        $order->set_discount_total( (float) $args['total_discounts'] );
        $order->set_total( (float) $args['total_paid'] );

        $order->save();

        // Customer note
        if($args['order_notes']) {
            foreach( $args['order_notes'] as $order_note ) {
                $this->add_order_note( $order->get_id(), $order_note);
            }
        }

        return $order->get_id();
    }

    /**
     * Format order addresses
     * 
     * @since 1.0.0
     */
    public function format_address($address){

        // Set the phone number
        if( ! $address['phone'] ) {
            $phone = $address['phone_mobile'];
        } else {
            $phone = $address['phone'];
        }

        return array(
            'first_name'        => $address['firstname'],
            'last_name'         => $address['lastname'],
            'company'           => $address['company'],
            'email'             => $address['email'],
            'phone'             => $phone,
            'address_1'         => $address['address1'],
            'address_2'         => $address['address2'],
            'city'              => $address['city'],
            //'state'             => $address[''],
            'postcode'          => $address['postcode'],
            'country'           => $address['iso_code'],
        );
    }

    /**
     * Add order note
     * 
     * @since 1.0.0
     */
    public function add_order_note( $order_id, $note_data ) {
        if ( ! $order_id ) {
			return 0;
		}
		
		$comment_author        = __( 'WooCommerce', 'woocommerce' );
		$comment_author_email  = strtolower( __( 'WooCommerce', 'woocommerce' ) ) . '@';
		$comment_author_email .= isset( $_SERVER['HTTP_HOST'] ) ? str_replace( 'www.', '', sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) : 'noreply.com'; // WPCS: input var ok.
		$comment_author_email  = sanitize_email( $comment_author_email );
		
		$commentdata = array(
            'comment_post_ID'      => $order_id,
            'comment_author'       => $comment_author,
            'comment_author_email' => $comment_author_email,
            'comment_author_url'   => '',
            'comment_content'      => utf8_encode($note_data['message']),
            'comment_agent'        => 'WooCommerce',
            'comment_type'         => 'order_note',
            'comment_parent'       => 0,
            'comment_approved'     => 1,
            'comment_date'         => $note_data['date_add'],
            'comment_date_gmt'     => $note_data['date_add']
		);

		$comment_id = wp_insert_comment( $commentdata );

		if ( ! $note_data['private'] ) {
			add_comment_meta( $comment_id, 'is_customer_note', 1 );
		}

		return $comment_id;
	}

}

function rpw_testing_orders() {
    if( current_user_can('manage_options') ) {
        $connection = new RPW_order_processor();
        //$connection->import_orders();
    }
}
add_action('rpw_after_database_test', 'rpw_testing_orders' );