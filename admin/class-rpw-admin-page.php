<?php
/**
 * Admin controls for Raven Prestashop to WooCommerce Migration Tool
 * 
 * @class   rpw_admin
 * @package raven-prestashop-woocommerce
 * @since   1.0.0 
 */

defined( 'ABSPATH' ) || exit;

class RPW_admin_page extends RPW_admin {

    /**
     * Set the ID
     */
    public $ID = 'rpw_migration';

    /**
     * Set the name
     */
    public $name = 'Prestashop to WooCommerce Migration';

    /**
     * Set the page
     */
    public $page = 'rpw-migration';

    /**
     * Plugin options
     */
    public $plugin_options;


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

        // register page to settings api
        add_action( 'admin_init', array( $this, 'page') );

        // create menu link
        add_action( 'admin_menu', array( $this, 'create_menu_link' ) );

    }

    /**
     * Options
     * 
     * @since 1.0.0
     */
    public function options() {
        $this->plugin_options = array(
            'hostname'  => 'localhost',
            'database'  => '',
            'username'  => '',
            'password'  => '',
            'prefix'    => 'ps_'
        );

        $options = get_option($this->option_name);

        if( is_array($options) ) {
            $this->plugin_options = array_merge($this->plugin_options, $options);
        }

        return $this->plugin_options;
    }

    /**
     *  Create the Menu page
     * 
     * @since 1.0.0
	 */
    public function create_menu_link() {
        // add top level menu page
        add_submenu_page( 
            'tools.php',
            $this->name, 
            $this->name, 
            'manage_options', 
            $this->page, 
            array( $this, 'callback'),
            99
        );
    }

    /**
     * Register page and sections
     * 
     * @since 1.0.0
     */
    public function page() {
        // register a new setting
		register_setting( $this->ID, $this->option_name );

    }

    /**
     * Create the page content
     *
     * @since 1.0.0
     */
    public function callback() {
        $data = $this->options();

        include RPW_ABSPATH . 'templates/admin/display-page.php';
    }

}

return new RPW_admin_page();