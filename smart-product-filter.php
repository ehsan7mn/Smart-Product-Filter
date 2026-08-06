<?php
/**
 * Plugin Name: Smart Product Filter
 * Plugin URI:  https://persiantik.net
 * Description: فیلتر محصولات ووکامرس با AJAX - شامل مرتب‌سازی، فیلتر تاکسونومی، ویژگی‌ها و قیمت
 * Version:     1.2.3
 * Author:      احسان مهدی‌زاده
 * Author URI:  https://persiantik.net
 * Text Domain: smart-product-filter
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 */

defined( 'ABSPATH' ) || exit;

// Constants
define( 'SPF_VERSION',   '1.2.3' );
define( 'SPF_FILE',      __FILE__ );
define( 'SPF_PATH',      plugin_dir_path( __FILE__ ) );
define( 'SPF_URL',       plugin_dir_url( __FILE__ ) );
define( 'SPF_ASSETS',    SPF_URL  . 'assets/' );

// Check WooCommerce is active
add_action( 'plugins_loaded', 'spf_init' );
function spf_init() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="error"><p>Smart Product Filter نیاز به WooCommerce دارد.</p></div>';
        });
        return;
    }

    require_once SPF_PATH . 'includes/class-admin-settings.php';
    require_once SPF_PATH . 'includes/class-filter-query.php';
    require_once SPF_PATH . 'includes/class-filter-ajax.php';
    require_once SPF_PATH . 'includes/class-filter-frontend.php';

    SPF_Admin_Settings::init();
    SPF_Filter_Ajax::init();
    SPF_Filter_Frontend::init();
}
