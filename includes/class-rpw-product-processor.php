<?php
/**
 *  Process data for products
 * 
 * @package raven-prestashop-woocommerce
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

class RPW_product_processor extends Raven_PrestaShop_WooCommerce_Migrate {

    /**
	 * The single instance of the class.
	 *
	 * @since 1.0.0
	 */
	protected static $_instance = null;

    public $progressbar;
	public $default_language = 1;				// Default language ID
	public $current_language = 1;				// Current language ID
	public $default_country = 0;				// Default country
    public $imported_categories = array();      // Imported product categories
    public $prestashop_version = '';         // Get DB version
    public static $url = '';                    // Set the url
    public static $archive_id = 0;
    public static $timeout  = 40;

    const USER_AGENT = 'Mozilla/5.0 AppleWebKit (KHTML, like Gecko) Chrome/ Safari/'; // the default "WooCommerce..." user agent is rejected with some NGINX config

    /**
     * Constructor
     * 
     * @since 1.0.0
     */
    public function __construct() {
        $options = $this->get_plugin_options();
        self::$url = isset($options['url']) ? $options['url'] : '';
        self::$archive_id = isset($options['archive-id']) ? $options['archive-id'] : '';

        $this->prestashop_version = $this->the_prestashop_version();
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
     * Store the mapping of the imported product categories
     * 
     * @since 1.0.0
     */
    public function get_imported_categories($lang) {
        $this->imported_categories[$lang] = $this->get_term_metas_by_metakey('_rpw_old_category_id');
    }

    /**
     * Get the imported products
     * 
     * @since 1.0.0
     */
    public function get_imported_products() {
        return $this->get_imported_ps_posts($meta_key = '_rpw_old_product_id');
    }

    /**
     * Clean the cache
     * 
     * @since 1.0.0
     */
    public function clean_cache($terms = array(), $taxonomy = 'category') {
        delete_option($taxonomy . '_children');
        clean_term_cache($terms, $taxonomy);
    }

    /**
     * Import Product Categories
     * 
     * @since 1.0.0
     */
    public function import_product_categories() {
        $cat_count          = 0;
        $imported_cat_count = 0;
        $terms              = array();
        $taxonomy           = 'product_cat';

        $connection = new RPW_prestashop_import();
        $query      = $connection->get_the_categories();
        $cat_count  = count($query);

        // Set the list of previously imported categories
        $this->get_imported_categories($this->current_language);

        if($query) {
            foreach($query as $term) {
                if ( ! array_key_exists($term['id_category'], $this->imported_categories[$this->current_language]) ) {
                    $new_cat_id = $this->import_product_category($term);

                    if ( ! empty($new_cat_id) ) {
                        $imported_cat_count++;
                        $terms[] = $new_cat_id;
                        $old_terms[$term['id_category']] = $new_cat_id;
                    }
                }
            }
        }

        // Set the list of imported categories
		$this->get_imported_categories($this->current_language);

        if ( ! empty($terms) ) {
            update_option('rpw_categories_added', $old_terms);
            wp_update_term_count_now($terms, $taxonomy);
            $this->clean_cache($terms, $taxonomy);
        }

        return array('categories_found' => $cat_count, 'categories_imported' => $imported_cat_count);

    }

    /**
     * Import Product Category
     * 
     * @since 1.0.0
     */
    public function import_product_category($category) {
        $new_cat_id = 0;
		$taxonomy = 'product_cat';

        // to use later version_compare($this->prestashop_version, '1.5', '<=')

        // Don't include the home category
        if( in_array($category['name'], ['Home', 'Root']) || $category['is_root_category'] == 1 ) {
            return;
        }

        // Check if the category is already imported
        if ( array_key_exists($category['id_category'], $this->imported_categories[$this->current_language]) ) {
            return $this->imported_categories[$this->current_language][$category['id_category']]; // Do not import already imported category
        }

        // Date
		$date = $category['date'];

        $args = array(
            'description'   => $category['description'],
            //'slug'        => $category['link_rewrite'],
        );

        // Parent category ID
        if( array_key_exists($category['id_parent'], $this->imported_categories[$this->current_language] ) ) {
            $parent_cat_id = $this->imported_categories[$this->current_language][$category['id_parent']];
            $args['parent'] = $parent_cat_id; 
        }

        // Meta SEO
        $meta = array(
            'link_rewrite'      => sanitize_text_field( $category['link_rewrite'] ),
            'meta_title'        => sanitize_text_field( $category['meta_title'] ),
            'meta_keywords'     => sanitize_text_field( $category['meta_keywords']),
            'meta_description'  => sanitize_text_field( $category['meta_description'] )
        );

        $new_term = wp_insert_term($category['name'], $taxonomy, $args);

        if( ! is_wp_error($new_term) ) {
            $new_cat_id = $new_term['term_id'];
            $this->imported_categories[$this->current_language][$category['id_category']] = $new_category_id;

            add_term_meta($new_cat_id, '_rpw_old_category_id', $category['id_category'], true);
            add_term_meta($new_cat_id, '_product_cat_order', $category['position']);
            add_term_meta($new_cat_id, '_product_cat_meta', $meta);

            // Category ordering
            if ( function_exists('wc_set_term_order') ) {
                wc_set_term_order($new_cat_id, $category['position'], $taxonomy);
            }
        }

        return $new_cat_id;
    }

    /**
     * Import products
     * 
     * @since 1.0.0
     */
    public function import_products() {
        $product_count            = 0;
        $imported_product_count   = 0;

        set_time_limit(300);

        // Check for categories before proceeding
        if( ! get_option('rpw_categories_added') ) {
            return 'No categories found';
        }

        $options        = $this->get_plugin_options();
        $connection     = new RPW_prestashop_import();
        $query          = $connection->get_products();
        $product_count  = count($query);

        $imported_products = $this->get_imported_products();

        if( $query ) {
            foreach($query as $product) {
                if( ! in_array($product['id_product'], array_keys($imported_products)) && $imported_product_count < 100 ) {
                    $new_post_id = $this->import_product($product);

                    if( ! is_wp_error($new_post_id) ) {
                        $imported_product_count++;
                        $imported_products[$product['id_product']] = $new_post_id;
                    }
                }
            }
            update_option('rpw_products_added', $imported_products);

            // Add a date stamp to options
            $options['date_products_added'] = date('Y-m-d H:i:s');
            update_option($this->option_name, $options);
        }
        
        return array('products_found' => $product_count, 'products_imported' => $imported_product_count);
    }

    /**
     * Import product
     * 
     * @since 1.0.0
     */
    public function import_product($product) {
        $product_medias     = array();
        $post_media         = array();
        $connection         = new RPW_prestashop_import();
        $date               = $product['date_add'];

        // Product Images
        $images = $connection->get_product_images($product['id_product']);

        if($images) {
            foreach($images as $image) {
                $image_name = ! empty($image['legend']) ? $image['legend'] : $product['name'];
                $image_filenames = $this->build_image_filenames($image['id_image'], $product['id_product']);

                foreach($image_filenames as $key => $image_filename ) {
                    $media_id = $this->import_media($image_name, $image_filename, $date, array(), $image['id_image']);

                    if( $media_id !== false ) {
                        break;
                    }
                }
                
                if( $media_id !== false ) {
                    $product_medias[] = $media_id;
                }
            }
        }

        // Product Categories
        $categories_ids = array();
        $categories = $connection->get_product_categories($product['id_product']);

        $old_terms = $this->get_term_metas_by_metakey('_rpw_old_category_id');
        if($categories) {
            foreach($categories as $category) {
                if ( array_key_exists($category,  $old_terms) ) {
					$categories_ids[] = $old_terms[$category];
				}
            }
        }

        // Get the content
        $content = isset($product['description'])? utf8_encode($product['description']) : '';
		$excerpt = isset($product['description_short'])? utf8_encode($product['description_short']) : '';

        // Status
        if( ($product['active'] == 1) && ($product['available_for_order'] == 1) ) {
            $status = 'publish';
            $stock_status = 'instock';
        } else {
            $status = 'raven-sold';
            $stock_status = 'outofstock';
        }
        
        // Set product gallery
        $media_ids = array();
        foreach($product_medias as $media_id) {
            $media_ids[] = $media_id;
        }

        // Prepare images
        $main_image = $media_ids[0];
        unset($media_ids[0]);
        $gallery = implode(',', $media_ids);

        // Get quantity
        if( ($product['quantity'] == 0 && $product['available_for_order'] == 1) || ($product['quantity'] >= 1) ) {
            $quantity = 1;
        } else {
            $quantity = 0;
        }

        // Check for archive, if so check for product category match
        if( self::$archive_id != 0 && $product['id_category_default'] == self::$archive_id ) {
            $quantity = 0;
        }

        $args = array(
            'ID'            => $product['id_product'],
            'name'          => utf8_encode($product['name']),
            //'slug'          => $product['slug'],
            'date'          => $date,
            'status'        => $status,
            'stock_status'  => $stock_status,
            'content'       => strip_tags($content),
            'excerpt'       => strip_tags($excerpt),
            'SKU'           => utf8_encode($product['reference']),
            'price'         => (float) $product['price'],
            'sale_price'    => '',
            'sale_from'     => '',
            'sale_to'       => '',
            'quantity'      => $quantity,
            'weight'        => (float) $product['weight'],
            'length'        => (float) $product['depth'],
            'height'        => (float) $product['height'],
            'width'         => (float) $product['width'],
            'categories_ids'  => $categories_ids,
            'tag_ids'       => '',
            'main_image'    => $main_image, // featured image
            'gallery'       => $gallery
        );

        $new_product_id = $this->set_product_data($args);

        foreach($product_medias as $media_id) {
            // Assign media to product ID
            $attachment = get_post($media_id);
            if( ! empty($attachment) && ($attachment->post_type == 'attachment') ) {
                $attachment->post_parent = $new_product_id;
                $attachment->post_date = $date;
                wp_update_post($attachment);
            }
        }

        // Add additional meta data
        $meta = array(
            '_rpw_old_product_id'       => $product['id_product'],
            '_rpw_old_slug'             => $product['slug'],
            '_product_image_gallery'    => $gallery
        );

        foreach($meta as $key => $value) {
            add_post_meta($new_product_id, $key, $value, true); 
        }
    }
    
    /**
     * Build image filenames
     * 
     * @since 1.0.0
     */
    public function build_image_filenames($image_id, $product_id = '') {
        $filenames  = array();
        $subdirs    = str_split(strval($image_id));
        $subdir     = implode('/', $subdirs);

        $filenames[] = untrailingslashit(self::$url) . '/img/p/' . $subdir . '/' . $image_id . '.jpg';
		$filenames[] = untrailingslashit(self::$url) . '/img/p/' . $product_id . '-' . $image_id . '.jpg';

        return $filenames;
    }

    /**
     * Import a media
     *
     * @since 1.0.0
     */
    public function import_media($name, $filename, $date='', $options=array(), $image_id = 0, $image_caption='') {
        
        // Check if the media is already imported
        $attachment_id = $this->get_wp_post_id_from_meta('_rpw_old_image_id', $image_id);
        
        if( ! $attachment_id ) {

            if( empty($date) || ($date == '0000-00-00 00:00:00') ) {
                $date = date('Y-m-d H:i:s');
            }

            $filename = urldecode($filename); // for filenames with spaces or accents

            $filetype = wp_check_filetype($filename);
        
            if( empty($filetype['type']) || ($filetype['type'] == 'text/html') ) { // Unrecognized file type
                return false;
            }

            // Upload the file from the PrestaShop web site to WordPress upload dir
            if( preg_match('/^http/', $filename) ) {
                $old_filename = $filename;
            } elseif( preg_match('#^/img#', $filename) ) {
                $old_filename = untrailingslashit(self::$url) . $filename;
            } else {
                $old_filename = untrailingslashit(self::$url) . '/img/' . $filename;
            }

            // Get the upload path
            $upload_path = $this->upload_dir($filename, $date);
            
            // Make sure we have an uploads directory.
            if( ! wp_mkdir_p($upload_path) ) {
                return false;
            }

            $new_filename = $filename;
            $basename = basename($new_filename);
            $extension = substr(strrchr($basename, '.'), 1);
            $basename_without_extension = preg_replace('/(\.[^.]+)$/', '', $basename);
            $post_title = $name;

            $name = utf8_encode($name); 
            $new_full_filename = $upload_path . '/' . $this->format_filename($basename_without_extension . '-' . $name) . '.' . $extension;
            $basename = basename($new_filename);
            $extension = substr(strrchr($basename, '.'), 1);
            $basename_without_extension = preg_replace('/(\.[^.]+)$/', '', $basename);
            $post_title = $name;
    
            // GUID
            $upload_dir = wp_upload_dir();
            $guid = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $new_full_filename);
            $attachment_id = $this->get_post_id_from_guid($guid);

            if( empty($attachment_id) ) {
                $upload_file = $this->copy_file($old_filename, $new_full_filename);
                // Image Alt
                $image_alt = '';
                if( ! empty($name) ) {
                    $image_alt = wp_strip_all_tags(stripslashes($name), true);
                }

                $attachment_id = $this->insert_attachment($post_title, $basename, $new_full_filename, $guid, $date, $filetype['type'], $image_alt, $image_id, $image_caption);
            }
        }

        return $attachment_id;
    }

    /**
     * Format a filename
     * 
     * @since 3.7.3
     * 
     * @param string $filename Filename
     * @return string Formated filename
     */
    public function format_filename($filename) {
        $filename = RPW_tools::convert_to_latin($filename);
        $filename = preg_replace('/%.{2}/', '', $filename); // Remove the encoded characters
        $filename = sanitize_file_name($filename);
        return $filename;
    }

    /**
     * Save the attachment and generates its metadata
     * 
     * @since 1.0.0
     */
    public function insert_attachment($attachment_title, $basename, $new_full_filename, $guid, $date, $filetype, $image_alt = '', $image_id = 0, $image_caption='') {
        $post_name = sanitize_title($attachment_title);
        
        // If the attachment does not exist yet, insert it in the database
        $attachment_id = 0;
        $attachment = $this->get_attachment_from_name($post_name);
        if( $attachment ) {
            $attached_file = basename(get_attached_file($attachment->ID));
            if( $attached_file == $basename ) { // Check if the filename is the same (in case where the legend is not unique)
                $attachment_id = $attachment->ID;
            }
        }
        
        if( $attachment_id == 0 ) {
            $attachment_data = array(
                'guid'				=> $guid, 
                'post_date'			=> $date,
                'post_mime_type'	=> $filetype,
                'post_name'			=> $post_name,
                'post_title'		=> $attachment_title,
                'post_status'		=> 'inherit',
                'post_content'		=> '',
                'post_excerpt'		=> $image_caption,
            );
            $attachment_id = wp_insert_attachment($attachment_data, $new_full_filename);
            if( ! empty($image_id) ) {
                add_post_meta($attachment_id, '_rpw_old_image_id', $image_id, true);
            } else {
                add_post_meta($attachment_id, '_rpw_imported', 1, true); // To delete the imported attachments
            }
        }
        
        if( ! empty($attachment_id) ) {
            if( preg_match('/(image|audio|video)/', $filetype) ) { // Image, audio or video				
                // you must first include the image.php file
                // for the function wp_generate_attachment_metadata() to work
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attach_data = wp_generate_attachment_metadata( $attachment_id, $new_full_filename );
                wp_update_attachment_metadata($attachment_id, $attach_data);

                // Image Alt
                if( ! empty($image_alt) ) {
                    update_post_meta($attachment_id, '_wp_attachment_image_alt', addslashes($image_alt)); // update_post_meta expects slashed
                }
            }
            return $attachment_id;
        } else {
            return false;
        }
    }

    /**
     * Determine the media upload directory
     * 
     * @since 1.0.0
     */
    public function upload_dir($filename, $date) {
        $upload_dir = wp_upload_dir(strftime('%Y/%m', strtotime($date)));
        $use_yearmonth_folders = get_option('uploads_use_yearmonth_folders');
        
        if( $use_yearmonth_folders ) {
            $upload_path = $upload_dir['path'];
        } else {
            $short_filename = $filename;
            $short_filename = preg_replace('#^' . preg_quote(self::$url) . '#', '', $short_filename);
            $short_filename = preg_replace('#.*img/#', '/', $short_filename);
            if( strpos($short_filename, '/') != 0 ) {
                $short_filename = '/' . $short_filename; // Add a slash before the filename
            }
            $upload_path = $upload_dir['basedir'] . untrailingslashit(dirname($short_filename));
        }

        return $upload_path;
    }

    /**
     * Check if the attachment exists in the database
     *
     * @since 1.0.0
     */
    private function get_attachment_from_name($name) {
        $name = preg_replace('/\.[^.]+$/', '', basename($name));
        $r = array(
            'name'			=> $name,
            'post_type'		=> 'attachment',
            'numberposts'	=> 1,
        );
        $posts_array = get_posts($r);
        if( is_array($posts_array) && (count($posts_array) > 0) ) {
            return $posts_array[0];
        }
        else {
            return false;
        }
    }

    /**
     * Get content form http source
     * 
     * @since 1.0.0
     */
    public function get_http_content($source) {
        $content = false;
        $source = str_replace( " ", "%20", $source );
        $source = str_replace( "&amp;", "&", $source );

        $response = wp_remote_get($source, array(
            'timeout'		=> self::$timeout,
            'sslverify'		=> false,
            'user-agent'	=> self::USER_AGENT,
        )); // Uses WooCommerce HTTP API
        
        if ( is_wp_error($response) ) {
            trigger_error($response->get_error_message(), E_USER_WARNING);
        } elseif ( $response['response']['code'] != 200 ) {
            trigger_error($response['response']['message'], E_USER_WARNING);
        } else {
            $content_type = wp_remote_retrieve_header($response, 'content-type');
            if ( preg_match('/^text/', $content_type) ) {
                // Not a media
                trigger_error('Not a media', E_USER_WARNING);
            } else {
                $content = wp_remote_retrieve_body($response);
            }
        }
        return $content;

    }

    /**
     * Copy file
     * 
     * @since 1.0.0
     */
    public function copy_file($source, $destination) {
        $result = false;

        if(file_exists($destination) && filesize($destination) > 0) {
            return true;
        }

        $file_content = $this->get_http_content($source);

        if($file_content !== false) {
            $result = (file_put_contents($destination, $file_content) !== false);
        }

        return $result;
    }

    /**
     * Process the post content
     *
     * @param string $content Post content
     * @param array $post_media Post medias
     * @return string Processed post content
     */
    public function process_content($content) {
        
        if ( !empty($content) ) {
            $content = str_replace(array("\r", "\n"), array('', ' '), $content);
            
            // Replace page breaks
            $content = preg_replace("#<hr([^>]*?)class=\"system-pagebreak\"(.*?)/>#", "<!--nextpage-->", $content);
            
            // Replace media URLs with the new URLs
            //$content = $this->process_content_media_links($content, $post_media);
        }

        return $content;
    }

    /**
     * Set Product data
     * 
     * @since 1.0.0
     */
    public function set_product_data($args) {
        $product = new WC_Product_Simple();
        
        // Primary data
        $product->set_name($args['name']);
        //$product->set_slug($args['slug']);
        $product->set_status($args['status']);
        $product->set_date_created($args['date']);
        $product->set_catalog_visibility('visible');
        $product->set_virtual('no');
        $product->set_downloadable('no');

        // Content
        $product->set_description($args['content']);
        $product->set_short_description($args['excerpt']);

        // SKU
        $product->set_sku($args['SKU']);

        // Pricing
        $product->set_price($args['price']);
        $product->set_regular_price($args['price']);
        //$product->set_sale_price();
        //$product->set_date_on_sale_from();
        //$product->set_date_on_sale_to();

        // Stock management
        $product->set_manage_stock(true);
        $product->set_stock_quantity($args['quantity']);
        $product->set_stock_status($args['stock_status']);

        $product->set_backorders('no');
        $product->set_sold_individually(false);

        // Shipping
        $product->set_weight(floatval($args['weight']));
        //$product->set_length(floatval($args['length']));
        //$product->set_height(floatval($args['height']));
        //$product->set_width(floatval($args['width']));

        // Reviews
        $product->set_reviews_allowed(false);

        // Taxonomies
        $product->set_category_ids($args['categories_ids']);
        //$product->set_tag_ids($args['tag_ids']);

        // Images
        $product->set_image_id($args['main_image']); // main image (featured)
        $product->set_gallery_image_ids($args['gallery']);
        $product->save();

        return $product->get_id();
    }
}

function rpw_testing_products() {
    if( current_user_can('manage_options') ) {
        $connection = new RPW_product_processor();
        //$connection->import_products();
    }
}
add_action('rpw_after_database_test', 'rpw_testing_products' );