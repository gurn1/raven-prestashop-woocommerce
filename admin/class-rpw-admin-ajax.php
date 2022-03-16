<?php
/**
 * Admin ajax class
 * 
 * @class   rpw_admin_ajax
 * @package raven-prestashop-woocommerce
 * @since   1.0.0 
 */

defined( 'ABSPATH' ) || exit;

class RPW_admin_ajax extends RPW_admin {

    /**
     * Set the ID
     */
    public $ID = 'rpw_migration';

    /**
     * Set the page
     */
    public $page = 'rpw-migration';

    /**
     * Constructor
     */
    public function __construct() {
        $this->hooks();
    }

    /**
     * Hooks
     * 
     * @since 1.0.0
     */
    public function hooks() {
        // enqueue scripts
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        // Test prestashop connection 
	    add_action( 'wp_ajax_rpw_test_prestashop_connection', array( $this, 'test_prestashop_connection' ) );

        // Get product categories
        add_action( 'wp_ajax_rpw_get_product_categories', array( $this, 'get_product_categories') );
        // Get products
        add_action( 'wp_ajax_rpw_get_the_products', array( $this, 'get_the_products') );
        // Get users
        add_action( 'wp_ajax_rpw_get_the_users', array( $this, 'get_the_users') );
        // Get orders
        add_action( 'wp_ajax_rpw_get_the_orders', array( $this, 'get_the_orders') );
    }

    /**
     * Enqueue scripts
     * 
     * @since 1.0.0
     */
    public function enqueue_scripts() {
        if( isset( $_GET['page'] ) && $_GET['page'] == $this->page ) {
            // enqueue plugins ajax file
            wp_enqueue_script( $this->ID, RPW_URL . 'assets/js/rpw-admin-ajax.js', array(), $this->version, true );
            wp_localize_script( $this->ID, 'rpwObject', array(
				'ajaxurl'   => $this->ajax_url(),
                'loaderURL' => RPW_URL . 'assets/images/loader.png'
			));
        }
    }

    /**
     * Test connection 
     * 
     * @since 1.0.0
     * @ajax
     */
    public function test_prestashop_connection() {
        $nonce = isset($_POST['security']) ? $_POST['security'] : '';
        $form_data = isset($_POST['form_data']) ? $_POST['form_data'] : array();

        if( ! wp_verify_nonce( $nonce, $this->ID.'-options' ) ) {
            echo 'Failed security check' ;
            exit;
        }

        parse_str($form_data, $form_data);

        $data = $this->save_options($form_data);

        $connection = new RPW_prestashop_import();
        $message = $connection->test_connection();

        echo $message;
        exit;
    }

    /**
     * Get Categories
     * 
     * @since 1.0.0
     */
    public function get_product_categories() {
        $connection = new RPW_product_processor();
        $migrate_categories = $connection->import_product_categories();
        $message = sprintf('Imported %s, of %s found', 
            $migrate_categories['categories_imported'],
            $migrate_categories['categories_found']
        );

        echo json_encode($message);
        exit;
    }

    /**
     * Get the products
     * 
     * @since 1.0.0
     */
    public function get_the_products() {
        $connection = new RPW_product_processor();
        $migrate_products = $connection->import_products();
        $message = sprintf('Imported %s, of %s found', 
            $migrate_products['products_imported'],
            $migrate_products['products_found']
        );
        
        echo json_encode($message);
        exit;
    }

    /**
     * Get the users
     * 
     * @since 1.0.0
     */
    public function get_the_users() {
        $connection = new RPW_customer_processor();
        $migrate_users = $connection->import_users();
        $message = sprintf('Imported %s, of %s found', 
            $migrate_users['users_imported'],
            $migrate_users['users_found']
        );

        echo json_encode($message);
        exit;
    }

    /**
     * Get the orders
     * 
     * @since 1.0.0
     */
    public function get_the_orders() {
        $connection = new RPW_order_processor();
        $migrate_orders = $connection->import_orders();
        
        if(isset($migrate_orders['error'])) {
            $message = $migrate_orders['error'];
        } else {
            $message = sprintf('Imported %s, of %s found', 
                $migrate_orders['orders_imported'],
                $migrate_orders['orders_found']
            );
        }

        echo json_encode($message);
        exit;
    }

}

return new RPW_admin_ajax();