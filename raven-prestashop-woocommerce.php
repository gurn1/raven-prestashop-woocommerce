<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @since             1.0.0
 * @package           Raven_Prestashop_WooCommerce_Migrate
 *
 * @wordpress-plugin
 * Plugin Name:       Raven Prestashop to WooCommerce Migration Tool
 * Description:       A plugin to migrate Prestashop to WooCommerce
 * Version:           1.0.0
 * Author:            Raven Designs
 * Text Domain:       raven-prestashop-woocommerce
 * WC tested up to:   5.0
 */

 defined( 'ABSPATH' ) || exit;

 if ( ! defined( 'RPW_PLUGIN_FILE' ) ) {
	define( 'RPW_PLUGIN_FILE', __FILE__ );
}

// Include main class
if( ! class_exists( 'Raven_PrestaShop_WooCommerce_Migrate' ) ) {
	include_once dirname( RPW_PLUGIN_FILE ) . '/includes/class-raven-prestashop-woocommerce-migrate.php';
}

/**
 *  Returns the main instance
 * 
 * @since 1.0.0
 */
function Raven_PrestaShop_WooCommerce_Migrate() {
	return Raven_PrestaShop_WooCommerce_Migrate::instance();
}

Raven_PrestaShop_WooCommerce_Migrate();