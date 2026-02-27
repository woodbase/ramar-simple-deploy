<?php

/**
 * Plugin Name: Nordic Equilibro – Beauty Fort WooCommerce Integration
 * Description: Internal integration to sync products from Beauty Fort to WooCommerce.
 * Version: 0.0.4
 * Author: Nordic Equilibro
 * Text Domain: nordic-equilibro-beautyfort
 */

if (!defined('ABSPATH')) exit;

// ==========================
// LOAD ALL PHP FILES
// ==========================
$base_path = plugin_dir_path(__FILE__);

require_once $base_path . 'includes/helpers.php';
require_once $base_path . 'includes/api-client.php';
require_once $base_path . 'includes/product-import.php';
require_once $base_path . 'includes/stock-price-sync.php';
require_once $base_path . 'includes/images.php';
require_once $base_path . 'includes/cron.php';
require_once $base_path . 'includes/class-pricing-engine.php';
require_once $base_path . 'includes/class-markup-calculator.php';
require_once $base_path . 'includes/sync-to-woo.php';

require_once $base_path . 'admin/admin-control-page.php';
require_once $base_path . 'admin/import-page.php';
require_once $base_path . 'admin/products-page.php';
require_once $base_path . 'admin/settings-action.php';

// ==========================
// ADMIN MENU
// ==========================
add_action('admin_menu', function () {
    add_submenu_page(
        'woocommerce',
        'Nordic Equilibro – Beauty Fort',
        'Nordic Equilibro',
        'manage_woocommerce',
        'nordic-equilibro-beautyfort',
        'nebf_admin_page'
    );
});

// ==========================
// ENQUEUE ADMIN JS + CSS
// ==========================
add_action('admin_enqueue_scripts', function ($hook) {

    if ($hook !== 'woocommerce_page_nordic-equilibro-beautyfort') return;

    $plugin_url = plugin_dir_url(__FILE__);

    // Admin JS for products tab
    wp_enqueue_script(
        'nebf-products-tab',
        $plugin_url . 'js/ne-beauty-woo-admin.js',
        ['jquery'],
        '1.1',
        true
    );

    // Admin CSS
    wp_enqueue_style(
        'nebf-admin-css',
        $plugin_url . 'css/nebf-style.css',
        [],
        '1.0'
    );
});

// ==========================
// DEFAULT SETTINGS
// ==========================
register_activation_hook(__FILE__, 'nebf_set_default_pricing_settings');

/**
 * Set default pricing settings on plugin activation.
 */
function nebf_set_default_pricing_settings()
{
    if (!get_option('nebf_pricing_settings')) {
        update_option('nebf_pricing_settings', [
            'default_type'  => 'percent',
            'default_value' => 30,
            'rounding'      => '99',
        ]);
    }
}

// ==========================
// AJAX: Save inline price or margin
// ==========================
add_action('wp_ajax_nebf_save_inline_price', 'nebf_save_inline_price');

/**
 * Handles inline price or margin updates from admin.
 */
function nebf_save_inline_price()
{
    $bf_id = sanitize_text_field($_POST['bf_id']);
    $value = floatval($_POST['value']);
    $type  = sanitize_text_field($_POST['type']);

    if (!$bf_id) wp_send_json_error();

    if ($type === 'price') {
        // Save overridden price for the product
        update_option('nebf_price_override_' . $bf_id, $value);

        wp_send_json_success([
            'formatted_price' => function_exists('wc_price') ? wc_price($value) : $value
        ]);

    } elseif ($type === 'margin') {
        // Save overridden margin for the product
        update_option('nebf_margin_override_' . $bf_id, $value);

        wp_send_json_success([
            'margin' => $value
        ]);
    }

    wp_send_json_error();
}

// ==========================
// MATERIAL ICONS
// ==========================
/**
 * Enqueue Material Icons for admin pages.
 */
function nebf_enqueue_material_icons($hook) {

    if (strpos($hook, 'nordic_equilibro_beautyfort') === false) {
        return;
    }

    wp_enqueue_style(
        'nebf-material-icons',
        'https://fonts.googleapis.com/css2?family=Material+Icons',
        [],
        null
    );
}
add_action('admin_enqueue_scripts', 'nebf_enqueue_material_icons');

function nebf_admin_icon_styles() {
    ?>
    <style>
        .nebf-icon-spin {
            animation: nebf-spin 1s linear infinite;
        }

        @keyframes nebf-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
    <?php
}
add_action('admin_head', 'nebf_admin_icon_styles');
