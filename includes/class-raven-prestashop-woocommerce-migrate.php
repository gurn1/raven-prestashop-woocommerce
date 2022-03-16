<?php
/**
 *  Main class for Raven Prestashop to WooCommerce Migration Tool
 * 
 * @package raven-prestashop-woocommerce
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

class Raven_PrestaShop_WooCommerce_Migrate {

    /**
     *  Plugin version
     */
    public $version = '1.0.0';

    /**
     * Set option name
     */
    public $option_name = 'rpw-migration-options';

    /**
     * Get DB version
     */
    public $prestashop_version = '';

    /**
     * Products processor.
     *
     * @var import_products
     */
	public $import_products = null;

    /**
     * Customers processor.
     *
     * @var import_customers
     */
	public $import_customers = null;

    /**
     * Orders processor.
     *
     * @var import_orders
     */
	public $import_orders = null;

    /**
	 * The single instance of the class.
	 *
	 * @since 1.0.0
	 */
	protected static $_instance = null;

    /**
     * Main Instance.
     *
     * Ensures only one instance is loaded or can be loaded.
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
     *  Constructor
     */
    public function __construct() {
        $this->define_constants();
        $this->hooks();
        $this->includes();
    }

    /**
     * Define constants
     */
    private function define_constants() {

        define( 'RPW_ABSPATH', dirname( RPW_PLUGIN_FILE ) . '/' );
        define( 'RPW_URL', plugin_dir_url( RPW_PLUGIN_FILE ) );
        define( 'RPW_VERSION', $this->version );
        define( 'RPW_DOMAIN', 'raven-prestashop-woo-migration');

    }

    /**
     * Include the required core files
     * 
     * @since 1.0.0
     */
    public function includes() {
        
        // Admin files
        include_once RPW_ABSPATH . 'admin/class-rpw-admin.php';
        
        // Prestashop connection 
        include_once RPW_ABSPATH . 'includes/class-rpw-import-data.php';

        // Process imported data for products 
        include_once RPW_ABSPATH . 'includes/class-rpw-product-processor.php';

        // Process imported data for customers
        include_once RPW_ABSPATH . 'includes/class-rpw-customer-processor.php';

        // Process imported data for order
        include_once RPW_ABSPATH . 'includes/class-rpw-order-processor.php';

        // Tools
        include_once RPW_ABSPATH . 'includes/class-rpw-tools.php';
    }

    /**
     * Hooks
     * 
     * @since 1.0.0
     */
    public function hooks() {

        // Register poststatuses
        add_action( 'init', array($this, 'register_post_status') );

        // Unhook Woocommerce emails
        add_action( 'woocommerce_email', array($this, 'unhook_woocommerce_emails' ) );
    }

    /**
     * Register poststatuses
     * 
     * @since 1.0.0
     */
    public function register_post_status() {

        // Register a sold poststatus if one isn't already registered
        if( ! array_key_exists('raven-sold', get_post_stati()) ) {
            register_post_status( 'raven-sold', array(
                'label'                     => _x( 'Sold', RPW_DOMAIN ),
                'public'                    => true,
                'internal'                  => true,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'exclude_from_search'       => true,
                'label_count'               => _n_noop( 'Sold <span class="count">(%s)</span>', 'Sold <span class="count">(%s)</span>', RPW_DOMAIN )
            ) );
        }
        
    }

    /**
     * Unhook and remove WooCommerce default emails.
     * 
     * @since 1.0.0
     */
    public function unhook_woocommerce_emails( $email_class ) {

            // Hooks for sending emails during store events
            remove_action( 'woocommerce_low_stock_notification', array( $email_class, 'low_stock' ) );
            remove_action( 'woocommerce_no_stock_notification', array( $email_class, 'no_stock' ) );
            remove_action( 'woocommerce_product_on_backorder_notification', array( $email_class, 'backorder' ) );
            
            // New order emails
            remove_action( 'woocommerce_order_status_pending_to_processing_notification', array( $email_class->emails['WC_Email_New_Order'], 'trigger' ) );
            remove_action( 'woocommerce_order_status_pending_to_completed_notification', array( $email_class->emails['WC_Email_New_Order'], 'trigger' ) );
            remove_action( 'woocommerce_order_status_pending_to_on-hold_notification', array( $email_class->emails['WC_Email_New_Order'], 'trigger' ) );
            remove_action( 'woocommerce_order_status_failed_to_processing_notification', array( $email_class->emails['WC_Email_New_Order'], 'trigger' ) );
            remove_action( 'woocommerce_order_status_failed_to_completed_notification', array( $email_class->emails['WC_Email_New_Order'], 'trigger' ) );
            remove_action( 'woocommerce_order_status_failed_to_on-hold_notification', array( $email_class->emails['WC_Email_New_Order'], 'trigger' ) );
            
            // Processing order emails
            remove_action( 'woocommerce_order_status_pending_to_processing_notification', array( $email_class->emails['WC_Email_Customer_Processing_Order'], 'trigger' ) );
            remove_action( 'woocommerce_order_status_pending_to_on-hold_notification', array( $email_class->emails['WC_Email_Customer_Processing_Order'], 'trigger' ) );
            
            // Completed order emails
            remove_action( 'woocommerce_order_status_completed_notification', array( $email_class->emails['WC_Email_Customer_Completed_Order'], 'trigger' ) );
                
            // Note emails
            remove_action( 'woocommerce_new_customer_note_notification', array( $email_class->emails['WC_Email_Customer_Note'], 'trigger' ) );
    }

    /**
     * Get plugin options
     * 
     * @since 1.0.0
     */
    public function get_plugin_options() {
        return get_option($this->option_name);
    }

    /**
     * Get all the term metas corresponding to a meta key
     * 
     * @since 1.0.0
     */
    public function get_term_metas_by_metakey($meta_key) {
        global $wpdb;
        $metas = array();
        
        $sql = "SELECT term_id, meta_value FROM {$wpdb->termmeta} WHERE meta_key = '$meta_key'";
        $results = $wpdb->get_results($sql);
        foreach ( $results as $result ) {
            $metas[$result->meta_value] = $result->term_id;
        }
        ksort($metas);
        return $metas;
    }

    /**
     * Returns the imported post ID corresponding to a meta key and value
     *
     * @since 1.0.0
     */
    public function get_wp_post_id_from_meta($meta_key, $meta_value) {
        global $wpdb;

        $sql = "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '$meta_key' AND meta_value = '$meta_value' LIMIT 1";
        $post_id = $wpdb->get_var($sql);
        return $post_id;
    }

     /**
     * Get a Post ID from its GUID
     * 
     * @since 1.0.0
     */
    public function get_post_id_from_guid($guid) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid=%s", $guid));
    }

    /**
     * Returns the imported posts mapped with their PrestaShop ID
     *
     * @since 1.0.0
     */
    public function get_imported_ps_posts($meta_key = '_rpw_old_post_id') {
        global $wpdb;
        $posts = array();

        $sql = "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '$meta_key'";
        $results = $wpdb->get_results($sql);
        foreach ( $results as $result ) {
            $posts[$result->meta_value] = $result->post_id;
        }
        ksort($posts);
        return $posts;
    }

    /**
     * Get user id by old id
     * 
     * @since 1.0.0
     */
    public function get_user_id_from_meta($meta_key, $meta_value) {
        global $wpdb;

        $sql = "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '$meta_key' AND meta_value = '$meta_value' LIMIT 1";
        $user_id = $wpdb->get_var($sql);
        return $user_id;
    }

    /**
     * Fixes an issue where images were not attached to the product gallery meta
     * 
     * @since 1.0.0
     */
    public function products_image_gallery_fix() {

        $query = get_posts( array(
            'posts_per_page'	=> -1,
            'post_type'			=> 'product'
        ) );
    
        if($query) {
            foreach( $query as $post ) {
                $image_ids = array();
    
                $images = get_posts( array(
                    'post_type'			=> 'attachment',
                    'posts_per_page'	=> -1,
                    'post_parent'		=> $post->ID,
                    'orderby'			=> 'date'
                ) );
    
                $main_image = get_post_meta($post->ID, '_thumbnail_id', true); 
    
                if($images) {
                    foreach( $images as $image ) {
                        $image_ids[] = $image->ID;
                    }
                }
    
                if(($key = array_search($main_image, $image_ids)) !== false ) {
                    unset($image_ids[$key]);
                }
    
                //update_post_meta($post->ID, '_product_image_gallery', implode(",",$image_ids));
            }
        }
    }

    /**
     * Strip html from content and excerpt
     * 
     * @since 1.0.0
     */
    public function products_content_remove_html() {
        $query = get_posts( array(
            'posts_per_page'	=> -1,
            'post_type'			=> 'product',
            'exclude'			=> array(5082),
        ) );
    
        if($query) {
            foreach( $query as $post ) {
                $current_content = $post->post_content;
                $current_excerpt = $post->post_excerpt;
    
                $new_content = strip_tags($current_content);
                $new_excerpt = strip_tags($current_excerpt);
    
                wp_update_post( array(
                    'ID'			=> $post->ID,
                    'post_content'	=> $new_content,
                    'post_excerpt'	=> $new_excerpt
                ) );
            }
        }
    }

    /**
     * 
     */
    public function the_prestashop_version() {
        $import = new RPW_prestashop_import(); 
        return $import->get_prestashop_version();
    }

    /**
     * Import product data
     * 
     * @since 1.0.0
     */
    public function import_products() {
        return RPW_product_processor::instance();
    }

    /**
     * Import customers data
     * 
     * @since 1.0.0
     */
    public function import_customers() {
        return RPW_customer_processor::instance();
    }

    /**
     * Import order data
     * 
     * @since 1.0.0
     */
    public function import_orders() {
        return RPW_order_processor::instance();
    }

 }