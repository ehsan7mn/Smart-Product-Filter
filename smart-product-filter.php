<?php
/**
 * Plugin Name: Smart Product Filter
 * Plugin URI:  https://persiantik.net
 * Description: فیلتر محصولات ووکامرس با AJAX - شامل مرتب‌سازی، فیلتر تاکسونومی، ویژگی‌ها و قیمت
 * Version:     1.2.11
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
define( 'SPF_VERSION',   '1.2.11' );
define( 'SPF_FILE',      __FILE__ );
define( 'SPF_PATH',      plugin_dir_path( __FILE__ ) );
define( 'SPF_URL',       plugin_dir_url( __FILE__ ) );
define( 'SPF_ASSETS',    SPF_URL  . 'assets/' );

// Plugin Update Checker — آپدیت از GitHub
require_once SPF_PATH . 'plugin-update-checker/plugin-update-checker.php';

add_action( 'plugins_loaded', 'spf_init_update_checker', 5 );
function spf_init_update_checker() {
    $update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/ehsan7mn/Smart-Product-Filter/',
        __FILE__,
        'smart-product-filter'
    );

    $update_checker->getVcsApi()->enableReleaseAssets();

    $token = apply_filters(
        'spf_github_update_token',
        defined( 'PTIK_GITHUB_TOKEN' ) ? PTIK_GITHUB_TOKEN : ''
    );

    if ( $token ) {
        $update_checker->setAuthentication( $token );
    }
}

// Check WooCommerce is active
add_action( 'plugins_loaded', 'spf_init' );
function spf_init() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="error"><p>Smart Product Filter نیاز به WooCommerce دارد.</p></div>';
        });
        return;
    }

    require_once SPF_PATH . 'includes/class-loaders.php';
    require_once SPF_PATH . 'includes/class-admin-settings.php';
    require_once SPF_PATH . 'includes/class-filter-query.php';
    require_once SPF_PATH . 'includes/class-filter-ajax.php';
    require_once SPF_PATH . 'includes/class-filter-frontend.php';

    SPF_Admin_Settings::init();
    SPF_Filter_Query::init();
    SPF_Filter_Ajax::init();
    SPF_Filter_Frontend::init();
}
