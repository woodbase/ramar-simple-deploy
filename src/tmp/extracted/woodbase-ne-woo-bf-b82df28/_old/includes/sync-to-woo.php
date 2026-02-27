<?php
/**
 * BeautyFort → WooCommerce Sync
 *
 * Handles synchronization of a single BeautyFort product
 * into WooCommerce as a simple product.
 *
 * Features:
 * - Creates or updates a product based on SKU
 * - Calculates selling price based on margin
 * - Sets the original cost price as sale price (hidden)
 * - Syncs stock quantity
 * - Downloads and attaches the featured image
 * - Creates and assigns Brand attribute
 * - Stores cost price as custom meta (_nebf_cost_price)
 * - Marks product as synced in the system
 *
 * Notes:
 * - Products are created as draft and hidden in catalog by default
 * - Original BeautyFort price is stored in sale_price field but inactive
 */

if (!defined('ABSPATH')) exit;

/**
 * Sync a single BeautyFort product to WooCommerce.
 *
 * @param array $product BeautyFort product array.
 * @param float $sale_price Our calculated selling price.
 * @return bool|WP_Error True on success, WP_Error if WooCommerce not active
 */
function nebf_sync_product_to_woo($product, $sale_price) {

    // Make sure WooCommerce is active
    if (!class_exists('WC_Product_Simple')) {
        return new WP_Error('no_wc', 'WooCommerce is not active');
    }
$test_mode = get_option('nebf_api_testmode', '0') === '1';
    /* ------------------------------------------------------------
     * 1. Extract and sanitize product data
     * ------------------------------------------------------------ */
    $sku        = sanitize_text_field($product['sku'] ?? '');
    $name       = sanitize_text_field($product['fullname'] ?? '');
    $cost_price = floatval($product['price'] ?? 0);
    $stock      = intval($product['stock_level'] ?? 0);
    if ($test_mode) {
    $image_url = $product['thumbnail_url'] ?? '';
} else {
    $image_url  = esc_url_raw($product['high_res_image_url'] ?? $product['thumbnail_url']);
}
    $brand      = sanitize_text_field($product['brand'] ?? '');

    if (empty($sku) || empty($name)) {
        return false;
    }

    /* ------------------------------------------------------------
     * 2. Set our selling price
     * ------------------------------------------------------------ */
    $sale_price = floatval($sale_price);

    /* ------------------------------------------------------------
     * 3. Check if product already exists in WooCommerce (by SKU)
     * ------------------------------------------------------------ */
    $existing_id = wc_get_product_id_by_sku($sku);

    if ($existing_id) {
        $wc_product = wc_get_product($existing_id);
    } else {
        $wc_product = new WC_Product_Simple();
    }

    /* ------------------------------------------------------------
     * 4. Set WooCommerce product data
     * ------------------------------------------------------------ */
    // Set product title and SKU
    $wc_product->set_name($name);
    $wc_product->set_sku($sku);

    // Set our actual selling price
    $wc_product->set_regular_price($sale_price);
    $wc_product->set_price($sale_price);

    // Store the original BeautyFort price in the sale price field
    // but schedule it in the past so it is not active
    $wc_product->set_sale_price($cost_price);
    $wc_product->set_date_on_sale_from(strtotime('-1 day'));
    $wc_product->set_date_on_sale_to(strtotime('-1 day'));

    // Manage stock
    $wc_product->set_manage_stock(true);
    $wc_product->set_stock_quantity($stock);
    $wc_product->set_stock_status($stock > 0 ? 'instock' : 'outofstock');

    // Make product hidden and save as draft
    $wc_product->set_catalog_visibility('hidden');
    $wc_product->set_status('draft');

    // Save product and get ID
    $product_id = $wc_product->save();

    /* ------------------------------------------------------------
     * 5. Attach featured image
     * ------------------------------------------------------------ */
    nebf_attach_image_from_url($image_url, $product_id);

    /* ------------------------------------------------------------
     * 6. Assign Brand attribute
     * ------------------------------------------------------------ */
    nebf_set_brand_attribute($product_id, $brand);

    /* ------------------------------------------------------------
     * 7. Store cost price as custom meta
     * ------------------------------------------------------------ */
    update_post_meta($product_id, '_nebf_cost_price', $cost_price);

    // Mark product as synced
    update_post_meta($product_id, '_nebf_synced', 1);

    return true;
}

/**
 * Download and attach an external image to a WooCommerce product.
 *
 * Handles URLs without file extensions (like BeautyFort), forces JPG if needed,
 * and prevents duplicate downloads by storing original URL in attachment meta.
 *
 * @param string $image_url URL of the image to download.
 * @param int    $product_id WooCommerce product ID.
 */
function nebf_attach_image_from_url($image_url, $product_id) {

    if (empty($image_url)) return;

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    // Check if image has already been downloaded
    $existing = get_posts([
        'post_type'  => 'attachment',
        'meta_key'   => '_nebf_image_url',
        'meta_value' => $image_url,
        'fields'     => 'ids'
    ]);

    if (!empty($existing)) {
        set_post_thumbnail($product_id, $existing[0]);
        return;
    }

    // Decode URL and download image to temp file
    $decoded_url = urldecode($image_url);
    $tmp = download_url($decoded_url);

    if (is_wp_error($tmp)) {
        error_log('NEBF: download_url failed: ' . $tmp->get_error_message());
        return;
    }

    // Attempt to detect image type
    $mime = '';
    $ext  = 'jpg'; // default fallback

    $img_info = @getimagesize($tmp);
    if ($img_info && !empty($img_info['mime'])) {
        $mime = $img_info['mime'];
        switch ($mime) {
            case 'image/jpeg':
                $ext = 'jpg';
                break;
            case 'image/png':
                $ext = 'png';
                break;
            case 'image/gif':
                $ext = 'gif';
                break;
        }
    }

    // Prepare file array for sideload
    $file_array = [
        'name'     => 'product-' . $product_id . '.' . $ext,
        'tmp_name' => $tmp
    ];

    // Sideload the image
    $attachment_id = media_handle_sideload($file_array, $product_id);

    if (is_wp_error($attachment_id)) {
        @unlink($tmp); // Clean up temp file
        error_log('NEBF: media_handle_sideload failed: ' . $attachment_id->get_error_message());
        return;
    }

    // Store original URL as meta and set as featured image
    update_post_meta($attachment_id, '_nebf_image_url', $image_url);
    set_post_thumbnail($product_id, $attachment_id);
}


/**
 * Assign a global "Brand" attribute to a WooCommerce product.
 *
 * - Creates the global attribute "Brand" if it does not exist
 * - Creates the term (brand name) if missing
 * - Assigns the term to the product
 * - Makes the attribute visible on the product page
 *
 * Best practices for WooCommerce 8.x:
 * - Use 'pa_' prefix for product attributes
 * - Ensure taxonomy exists before assigning terms
 * - Update _product_attributes meta so WC can display it properly
 *
 * @param int    $product_id WooCommerce product ID
 * @param string $brand_name Brand name
 */
function nebf_set_brand_attribute($product_id, $brand_name) {
    if (empty($brand_name)) return;

    $taxonomy = 'pa_brand';

    // Ensure global attribute exists
    if (!taxonomy_exists($taxonomy)) {
        wc_create_attribute([
            'name'         => 'Brand',
            'slug'         => 'brand',
            'type'         => 'select',
            'order_by'     => 'menu_order',
            'has_archives' => true,
        ]);

        // Register the taxonomy immediately
        register_taxonomy(
            $taxonomy,
            ['product'],
            ['hierarchical' => false]
        );
    }

    // Ensure the brand term exists
    if (!term_exists($brand_name, $taxonomy)) {
        wp_insert_term($brand_name, $taxonomy);
    }

    // Assign term to product
    wp_set_object_terms($product_id, $brand_name, $taxonomy);

    // Make attribute visible on product page (front-end)
    $product_attributes = [
        $taxonomy => [
            'name'         => $taxonomy,
            'value'        => '',
            'position'     => 0,
            'is_visible'   => 1,
            'is_variation' => 0,
            'is_taxonomy'  => 1,
        ]
    ];

    update_post_meta($product_id, '_product_attributes', $product_attributes);
}

/**
 * Check if a SKU has already been synced to WooCommerce
 *
 * @param string $sku Product SKU
 * @return bool True if synced, false otherwise
 */
function nebf_is_product_synced($sku) {
    $product_id = wc_get_product_id_by_sku($sku);
    if (!$product_id) return false;

    return get_post_meta($product_id, '_nebf_synced', true) ? true : false;
}
