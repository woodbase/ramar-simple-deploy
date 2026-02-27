<?php
/**
 * Plugin Name: NE BeautyFort Woo
 * Description: BeautyFort WooCommerce integration.
 * Version: 1.0.0
 * Author: Nordic Equilibro
 * Text Domain: nebf-mvc
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!session_id()) {
    session_start();
}

define('NEBF_MVC_PATH', plugin_dir_path(__FILE__));
define('NEBF_MVC_URL', plugin_dir_url(__FILE__));
define('NEBF_MVC_VERSION', '1.0.0');

/**
 * Load plugin text domain for translations.
 */
function nebf_mvc_load_textdomain() {
    load_plugin_textdomain(
        'nebf-mvc',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}
add_action('plugins_loaded', 'nebf_mvc_load_textdomain');

/**
 * Load autoloader
 */
require_once NEBF_MVC_PATH . 'includes/Core/Autoloader.php';

/**
 * Bootstrap plugin
 */
function nebf_mvc_boot() {
    $plugin = new NEBF\Core\Plugin();
    $plugin->init();
}
nebf_mvc_boot();
