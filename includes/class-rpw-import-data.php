<?php
/**
 *  Main class for Raven Prestashop to WooCommerce Migration Tool
 * 
 * @package raven-prestashop-woocommerce
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

class RPW_prestashop_import extends Raven_PrestaShop_WooCommerce_Migrate {

    static $prefix = '';

    public $default_language = 1;				// Default language ID
	public $current_language = 1;				// Current language ID
	public $prestashop_version = '';			// PrestaShop DB version
	public $default_country = 0;				// Default country

    /**
     * Construct
     * 
     * @since 1.0.0
     */
    public function __construct() {
        $options = $this->get_plugin_options();
        self::$prefix = isset($options['prefix']) ? $options['prefix'] : '';
    }

    /**
     * Create Connection
     * 
     * @since 1.0.0
     */
    public function create_connection() {
        $options = $this->get_plugin_options();
        if( ! $options ) {
            return;
        }
        return new mysqli($options['hostname'], $options['username'], $options['password'], $options['database']);
    }

    /**
     * Test Connection
     * 
     * @since 1.0.0
     */
    public function test_connection() {
        $mysqli = $this->create_connection();
        $message = 'Connected Successfully "' . $mysqli->host_info . '"';

        if( ! $mysqli ) {
            $message = 'Parameters missing';
        }

        if( $mysqli->connect_errno ) {
            $message = sprintf('Connection Failed $s', $mysqli->connect_error);
        }

        $mysqli->close();

        return $message;
    }

    /**
     * Get Configuration
     * 
     * @since 1.0.0
     */
    public function get_configuration() {
        $prefix = self::$prefix;
        $mysqli = $this->create_connection();
        $config = array();

        $sql = "SELECT name, value
            FROM ${prefix}configuration
            ORDER BY id_configuration
        ";

        if($mysqli) {
            $result = $mysqli->query($sql);
            foreach ( $result as $row ) {
                if ( !isset($config[$row['name']]) ) {
                    $config[$row['name']] = $row['value'];
                }
            }

            $mysqli->close();
        }
        return $config;
    }

    /**
     * Get prestashop version
     * 
     * @since 1.0.0
     */
    protected function get_prestashop_version() {
        $config = $this->get_configuration();
        $version = '0';

        if($config) {
            if ( isset($config['PS_VERSION_DB']) ) {
                $version = $config['PS_VERSION_DB'];
            } elseif ( ! $this->column_exists('product', 'location') ) {
                $version = '1.0';
            } elseif ( ! $this->column_exists('orders', 'total_products_wt') ) {
                $version = '1.2';
            } elseif ( ! $this->table_exists('cms_category') ) {
                $version = '1.3';
            } elseif ( ! $this->table_exists('stock_available') ) {
                $version = '1.4';
            } else {
                $version = '1.5';
            }
        }

        return $version;
    }

    /**
     * Get product by id
     * 
     * @since 1.0.0
     */
    public function get_product($product_id) {
        $prefix = self::$prefix;
        $mysqli = $this->create_connection();
        $result = array();

        $sql = "SELECT p.id_product, p.id_category_default, p.quantity, p.price, p.reference, p.weight, p.out_of_stock, p.active, p.date_add, p.date_upd, pl.id_product, pl.name, pl.description, pl.description_short, pl.link_rewrite 
            FROM ${prefix}product p 
            INNER JOIN ${prefix}product_lang pl ON pl.id_product = p.id_product AND pl.id_lang = 1
            WHERE p.id_product = '$product_id'
        ";

        if($mysqli) {
            $result = $mysqli->query($sql);
            $mysqli->close();
        }
        return $result;
    }

    /**
     * Get product data
     * 
     * @since 1.0.0
     */
    public function get_products() {
        $prefix = self::$prefix;
        $mysqli = $this->create_connection();
        $products = array();

        $sql = "SELECT p.id_product, p.id_category_default, p.quantity, p.price, p.reference, p.weight, p.height, p.depth, p.width, p.out_of_stock, p.active, p.date_add, p.date_upd, pl.id_product, pl.name, pl.description, pl.description_short, pl.link_rewrite, p.available_for_order 
            FROM ${prefix}product p 
            INNER JOIN ${prefix}product_lang AS pl ON pl.id_product = p.id_product AND pl.id_lang = 1
        ";

        if($mysqli) {
            $result = $mysqli->query($sql);

            if ($result->num_rows > 0) {
                foreach($result as $row) {
                    $products[] = $row;
                }
            }
            $mysqli->close();
        }
        return $products;
    }

    /**
     * Get Product count
     * 
     * @since 1.0.0
     */
    public function get_products_count() {
        $prefix = self::$prefix;
        $mysqli = $this->create_connection();
        $row    = 0;

        $sql = "SELECT COUNT(*) AS c FROM ${prefix}product p";


        if( $mysqli && $result = $mysqli->query($sql) ) {
            $row = $result->fetch_assoc();
            $mysqli->close();

            return (int) $row["c"];
        }
    }
  
    /**
     * Get product images
     * 
     * @since 1.0.0
     */
    public function get_product_images($product_id) {
        $prefix = self::$prefix;
        $mysqli = $this->create_connection();
        $result = array();

        $sql = "SELECT i.id_image, i.position, i.cover, il.legend
            FROM ${prefix}image i LEFT JOIN ${prefix}image_lang il ON il.id_image = i.id_image AND il.id_lang = 1
            WHERE i.id_product = '$product_id'
            ORDER BY i.cover DESC, i.position
        ";

        if($mysqli) {
            $result = $mysqli->query($sql);
            $mysqli->close();
        }
        return $result;
    }

    /**
     * Get product categories
     * 
     * @since 1.0.0
     */
    public function get_product_categories($product_id) {
        $prefix = self::$prefix;
        $mysqli = $this->create_connection();
        $categories = array();

        $sql = "SELECT cp.id_category
            FROM ${prefix}category_product cp
            WHERE cp.id_product = $product_id
        ";

        if($mysqli) {
            $result = $mysqli->query($sql);

            if ($result->num_rows > 0) {
                foreach($result as $row) {
                    $categories[] = $row['id_category'];
                }
            }

            $mysqli->close();
        }
        return $categories;
    }

    /**
     * Get product attributes
     * 
     * @since 1.0.0
     */
    public function get_product_attributes($product_id) {
        $prefix = self::$prefix;
        $mysqli = $this->create_connection();
        $result = array();
        
        $sql = "SELECT pa.id_product_attribute, pa.reference, pa.supplier_reference, pa.location, pa.ean13, pa.price, pa.quantity, pa.weight
            FROM ${prefix}product_attribute pa
            WHERE pa.id_product = $product_id
            ";

        if($mysqli) {
            $result = $mysqli->query($sql);
            $mysqli->close();
        }
        return $result;
    }

    /**
     * Get the root Category
     * 
     * @since 1.0.0
     */
    public function get_root_category() {
        $prefix = self::$prefix;
        $mysqli = $this->create_connection();
        $result = array();

        $sql = "SELECT c.id_category, c.level_depth
            FROM ${prefix}category c
            WHERE c.is_root_cateory = 1
            LIMIT 1
        ";

        if($mysqli) {
            $result = $mysqli->query($sql);
            $mysqli->close();
        }
        return $result;
    }

    /**
     * Get the categories
     * 
     * @since 1.0.0
     */
    public function get_the_categories() {
        $prefix = self::$prefix;
        $mysqli = $this->create_connection();
        $categories = array();

        $sql = "SELECT DISTINCT c.id_category, c.date_add, c.position, c.id_parent, c.position,
                                cl.name, cl.description, cl.link_rewrite, cl.meta_description, cl.meta_keywords, cl.meta_title
            FROM ${prefix}category c
            LEFT JOIN ${prefix}category_lang AS cl ON cl.id_category = c.id_category AND cl.id_lang = 1
            WHERE c.active = 1
            ORDER BY c.level_depth, c.position
        ";

        if($mysqli) {
            $result = $mysqli->query($sql);

            if ($result->num_rows > 0) {
                foreach($result as $row) {
                    $categories[] = $row;
                }
            }

            $mysqli->close();
        }
        return $categories;
    }

    /**
     * Count categories
     * 
     * @since 1.0.0
     */
    public function get_the_categories_count() {
        $prefix = self::$prefix;
        $mysqli = $this->create_connection();
        $count  = 0;

        $sql = "SELECT COUNT(*) AS C from ${prefix}category";

        if($mysqli) {            
            $result = $mysqli->query($sql);
            $count = $result->fetch_assoc();
            
            $mysqli->close();
        }
        return (int) $count["C"];
    }

    /**
     * Get orders
     * 
     * @since 1.0.0
     */
    Public function get_shop_orders() {
        $prefix = self::$prefix;
        $mysqli = $this->create_connection();
        $orders = array();

        $sql = "SELECT DISTINCT o.id_order, o.id_carrier, o.id_lang, o.id_customer, o.id_currency, o.id_address_delivery, o.id_address_invoice, o.payment, o.gift_message, o.total_discounts, o.total_paid, o.total_products, o.total_products_wt, o.total_shipping, o.invoice_number, o.delivery_number, o.invoice_date, o.delivery_date, o.date_add
            FROM ${prefix}orders o
            ORDER BY o.id_order
        ";

        if($mysqli) {
            $result = $mysqli->query($sql);

            if ($result->num_rows > 0) {
                foreach($result as $row) {
                    $orders[] = $row;
                }
            }

            $mysqli->close();
        }
        return $orders;
    }

    /**
     * Get order product details
     * 
     * @since 1.0.0
     */
    public function get_shop_order_products($order_id) {
        $prefix = self::$prefix;
        $mysqli = $this->create_connection();
        $products = array();

        $sql = "SELECT DISTINCT od.id_order_detail, od.id_order, od.product_id, od.product_name, od.product_quantity, od.product_quantity_return, od.product_price, od.product_weight
            FROM ${prefix}order_detail od
            WHERE od.id_order = $order_id
            ORDER BY od.id_order_detail
        ";
        
        if($mysqli) {
            $result = $mysqli->query($sql);

            if ($result->num_rows > 0) {
                foreach($result as $row) {
                    $products[] = $row;
                }
            }

            $mysqli->close();
        }
        return $products;
    }

    /**
     * Get order messages
     * 
     * @since 1.0.0
     */
    public function get_shop_order_messages($order_id) {
        $prefix = self::$prefix;
        $mysqli = $this->create_connection();
        $messages = array();

        $sql = "SELECT m.id_message, m.id_order, m.message, m.private, m.date_add
            FROM ${prefix}message m
            where m.id_order = $order_id
            ORDER BY m.id_message
        ";
        
        if($mysqli) {
            $result = $mysqli->query($sql);

            if ($result->num_rows > 0) {
                foreach($result as $row) {
                    $messages[] = $row;
                }
            }

            $mysqli->close();
        }
        return $messages;
    }

    /**
     * Get order billing and delivery address
     * 
     * @since 1.0.0
     */
    public function get_shop_order_address($address_id) {
        $prefix = self::$prefix;
        $mysqli = $this->create_connection();
        $row    = array();

        $sql = "SELECT DISTINCT a.id_address, a.id_country, a.id_state, a.id_customer, a.alias, a.company, a.lastname, a.firstname, a.address1, a.address2, a.postcode, a.city, a.other, a.phone, a.phone_mobile, a.date_add, c.iso_code
            FROM ${prefix}address a
            LEFT JOIN ${prefix}country c ON a.id_country = c.id_country
            WHERE a.id_address = $address_id
        ";

        if($mysqli) {
            $result = $mysqli->query($sql);
            $row = $result->fetch_assoc();
            $mysqli->close();
        }
        return $row;
    }

    /**
     * Count order
     * 
     * @since 1.0.0
     */
    public function get_shop_order_count() {
        $prefix = self::$prefix;
        $mysqli = $this->create_connection();
        $count  = 0;

        $sql = "SELECT COUNT(*) AS O from ${prefix}orders";

        if($mysqli) {
            $result = $mysqli->query($sql);
            $count  = $result->fetch_assoc();

            $mysqli->close();
        }
        return (int) $count["O"];
    }

    /**
     * Get customers
     * 
     * @since 1.0.0
     */
    public function get_customers() {
        $prefix = self::$prefix;
        $mysqli = $this->create_connection();
        $users  = array();

        $sql = "SELECT DISTINCT c.id_customer, c.id_gender, c.firstname, c.lastname, c.email, c.passwd, c.birthday, c.active, c.date_add
            FROM ${prefix}customer c
            LEFT JOIN ${prefix}address AS a ON a.id_customer = c.id_customer
            ORDER BY c.id_customer
        ";

        if($mysqli) {
            $result = $mysqli->query($sql);

            if ($result->num_rows > 0) {
                foreach($result as $row) {
                    $users[] = $row;
                }
            }

            $mysqli->close();
        }
        return $users;
    }

    /**
     * Get customer
     * 
     * @since 1.0.0
     */
    public function get_customer($customer_id) {
        $prefix = self::$prefix;
        $mysqli = $this->create_connection();
        $users  = array();

        $sql = "SELECT c.id_customer, c.id_gender, c.firstname, c.lastname, c.email, c.passwd, c.birthday, c.active, c.date_add
            FROM ${prefix}customer c
            WHERE c.id_customer = $customer_id
        ";
        
        if($mysqli) {
            $result = $mysqli->query($sql);
            $row = $result->fetch_assoc();
            $mysqli->close();
        }
        
        return $row;
    }

    /**
     * Get customer addresses
     * 
     * @since 1.0.0
     */
    public function get_customers_addresses($customer_id) {
        $prefix = self::$prefix;
        $mysqli = $this->create_connection();
        $addresses = array();
        
        $sql = "SELECT a.id_address, a.id_country, a.id_state, a.id_customer, a.alias, a.company, a.lastname, a.firstname, a.address1, a.address2, a.postcode, a.city, a.other, a.phone, a.phone_mobile, a.date_add
            FROM ${prefix}address a
            WHERE a.id_customer = $customer_id
            ORDER BY a.date_add DESC      
        ";

        if($mysqli) {
            $result = $mysqli->query($sql);

            if ($result->num_rows > 0) {
                foreach($result as $row) {
                    $addresses[] = $row;
                }
            }
            $row = $result->fetch_assoc();
            $mysqli->close();
        }
        return $addresses;

    }

    /**
     * Count customers
     * 
     * @since 1.0.0
     */
    public function get_customers_count() {
        $prefix = self::$prefix;
        $mysqli = $this->create_connection();
        $count  = 0;

        $sql = "SELECT COUNT(*) AS C from ${prefix}customer";

        if($mysqli) {
            $result = $mysqli->query($sql);
            $count  = $result->fetch_assoc();

            $mysqli->close();
        }
        return (int) $count["C"];
    }

    /**
     * Get country ISO
     * 
     * @since 1.0.0
     */
    public function get_country_iso($country_id) {
        $prefix = self::$prefix;
        $mysqli = $this->create_connection();
        $row    = array();

        $sql = "SELECT c.id_zone, c.iso_code, c.zip_code_format
            FROM ${prefix}country c
            WHERE c.id_country = $country_id
        ";

        if($mysqli) {
            $result = $mysqli->query($sql);
            $row = $result->fetch_assoc();
            $mysqli->close();
        }
        return $row;

    }

}