<?php
/**
 * Admin controls for Raven Prestashop to WooCommerce Migration Tool
 * 
 * @class   rpw_admin
 * @package raven-prestashop-woocommerce
 * @since   1.0.0 
 */

defined( 'ABSPATH' ) || exit;

class RPW_admin extends Raven_PrestaShop_WooCommerce_Migrate {

    /**
     * Constructor
     */
    public function __construct() {
        $this->includes();
    }

    /**
     * Include the required core files
     * 
     * @since 1.0.0
     */
    public function includes() {
        // Admin files
        include_once RPW_ABSPATH . 'admin/class-rpw-admin-page.php';

        // Admin ajax calls
        include_once RPW_ABSPATH . 'admin/class-rpw-admin-ajax.php';
    }

    /**
	 * Get Ajax URL.
	 *
	 * @since 1.0.0
	 */
	public function ajax_url() {
		return admin_url( 'admin-ajax.php', 'relative' );
	}

    /**
     *  Validate form data
     * 
     * @since 1.0.0
     */
    public function validate_form_data($data) {
        $output = array();

        if( is_array($data) ) {

            foreach($data as $key => $field) {

                if( $field != null ) {
                    $output[$key] = sanitize_text_field($field);
                }

            }

           return $output; 
        }
    }

    /**
     * Save options
     * 
     * @since 1.0.0
     */
    public function save_options($data) {
        $data = $this->validate_form_data($data);

        $options = get_option($this->option_name);

        if( is_array($options) ) {
            $data = array_merge($options, $data);
        }

        update_option( $this->option_name, $data );
    }

}

return new RPW_admin();