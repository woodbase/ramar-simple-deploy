<?php

namespace NEBF\Services;

use WC_Product_Simple;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles syncing products to WooCommerce.
 */
class ProductSyncService
{

    /**
     * Sync a single product to WooCommerce.
     *
     * @param array $data
     * @return int WooCommerce product ID
     */
    public function sync(array $data): int
    {

        // Check if product already exists by SKU
        $existing_id = wc_get_product_id_by_sku($data['sku']);

        if ($existing_id) {
            $product = wc_get_product($existing_id);
        } else {
            $product = new WC_Product_Simple();
        }

        $product->set_name($data['name']);
        $product->set_sku($data['sku']);
        $product->set_regular_price($data['price']);
        $product->set_catalog_visibility(
            $data['visible'] ? 'visible' : 'hidden'
        );

        return $product->save();
    }

    /**
     * Sync a BeautyFort product payload into WooCommerce.
     *
     * @param array $product_data
     * @param float $sale_price
     * @return int|\WP_Error
     */
    public function sync_beautyfort_product(array $product_data, float $sale_price)
    {
        if (!class_exists('WC_Product_Simple')) {
            return new \WP_Error('no_wc', __('WooCommerce is not active.', 'nebf-mvc'));
        }

        $sku = sanitize_text_field((string) ($product_data['sku'] ?? ''));
        $name = sanitize_text_field((string) ($product_data['fullname'] ?? $product_data['name'] ?? ''));
        $bf_id = sanitize_text_field((string) ($product_data['bf_id'] ?? $product_data['id'] ?? ''));
        $stock = (int) ($product_data['stock_level'] ?? 0);
        $brand = sanitize_text_field((string) ($product_data['brand'] ?? ''));
        $cost_price = (float) ($product_data['price'] ?? 0);
        $sale_price = $sale_price > 0 ? $sale_price : $cost_price;

        $image_url = esc_url_raw((string) ($product_data['high_res_image_url'] ?? $product_data['thumbnail_url'] ?? ''));
        $description = wp_kses_post((string) ($product_data['description'] ?? ''));

        if ($sku === '' || $name === '') {
            return new \WP_Error('missing_required_data', __('Missing SKU or product name.', 'nebf-mvc'));
        }

        $existing_id = wc_get_product_id_by_sku($sku);
        $wc_product = $existing_id ? wc_get_product($existing_id) : new WC_Product_Simple();

        if (!$wc_product) {
            $wc_product = new WC_Product_Simple();
        }

        $wc_product->set_name($name);
        $wc_product->set_sku($sku);
        $wc_product->set_description($description);
        $wc_product->set_regular_price((string) $sale_price);
        $wc_product->set_price((string) $sale_price);
        $wc_product->set_sale_price((string) $cost_price);
        $wc_product->set_date_on_sale_from(strtotime('-1 day'));
        $wc_product->set_date_on_sale_to(strtotime('-1 day'));
        $wc_product->set_manage_stock(true);
        $wc_product->set_stock_quantity($stock);
        $wc_product->set_stock_status($stock > 0 ? 'instock' : 'outofstock');
        $wc_product->set_catalog_visibility('hidden');
        $wc_product->set_status('draft');

        $product_id = (int) $wc_product->save();

        if ($product_id <= 0) {
            return new \WP_Error('save_failed', __('Failed to save WooCommerce product.', 'nebf-mvc'));
        }

        if ($bf_id !== '') {
            update_post_meta($product_id, '_beautyfort_id', $bf_id);
        }
        update_post_meta($product_id, '_nebf_cost_price', $cost_price);
        update_post_meta($product_id, '_nebf_synced', 1);

        $this->attach_image_from_url($image_url, $product_id);
        $this->set_brand_attribute($product_id, $brand);

        return $product_id;
    }

    /**
     * Sync multiple products.
     *
     * @param array $products
     */
    public function sync_multiple(array $products)
    {
        foreach ($products as $product) {
            $this->sync($product);
        }
    }

    private function attach_image_from_url(string $image_url, int $product_id): void
    {
        if ($image_url === '' || $product_id <= 0) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $existing = get_posts([
            'post_type'   => 'attachment',
            'meta_key'    => '_nebf_image_url',
            'meta_value'  => $image_url,
            'fields'      => 'ids',
            'numberposts' => 1,
        ]);

        if (!empty($existing[0])) {
            set_post_thumbnail($product_id, (int) $existing[0]);
            return;
        }

        $tmp_file = download_url(urldecode($image_url));
        if (is_wp_error($tmp_file)) {
            return;
        }

        $ext = 'jpg';
        $info = @getimagesize($tmp_file);
        if (is_array($info) && !empty($info['mime'])) {
            if ($info['mime'] === 'image/png') {
                $ext = 'png';
            } elseif ($info['mime'] === 'image/gif') {
                $ext = 'gif';
            }
        }

        $file_array = [
            'name'     => 'product-' . $product_id . '.' . $ext,
            'tmp_name' => $tmp_file,
        ];

        $attachment_id = media_handle_sideload($file_array, $product_id);
        if (is_wp_error($attachment_id)) {
            @unlink($tmp_file);
            return;
        }

        update_post_meta((int) $attachment_id, '_nebf_image_url', $image_url);
        set_post_thumbnail($product_id, (int) $attachment_id);
    }

    private function set_brand_attribute(int $product_id, string $brand_name): void
    {
        $brand_name = trim(sanitize_text_field($brand_name));
        if ($product_id <= 0 || $brand_name === '') {
            return;
        }

        $attribute_slug = 'brand';
        $taxonomy = wc_attribute_taxonomy_name($attribute_slug);

        if (!taxonomy_exists($taxonomy)) {
            if (!wc_attribute_taxonomy_id_by_name($attribute_slug)) {
                wc_create_attribute([
                    'name'         => 'Brand',
                    'slug'         => $attribute_slug,
                    'type'         => 'select',
                    'order_by'     => 'menu_order',
                    'has_archives' => true,
                ]);
                delete_transient('wc_attribute_taxonomies');
            }

            if (!taxonomy_exists($taxonomy)) {
                register_taxonomy($taxonomy, ['product'], ['hierarchical' => false]);
            }
        }

        if (!term_exists($brand_name, $taxonomy)) {
            wp_insert_term($brand_name, $taxonomy);
        }

        wp_set_object_terms($product_id, [$brand_name], $taxonomy, false);

        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }

        $attributes = $product->get_attributes();

        $attribute = new \WC_Product_Attribute();
        $attribute->set_id((int) wc_attribute_taxonomy_id_by_name($attribute_slug));
        $attribute->set_name($taxonomy);
        $attribute->set_options(array_map('intval', wp_get_object_terms($product_id, $taxonomy, ['fields' => 'ids'])));
        $attribute->set_position(0);
        $attribute->set_visible(true);
        $attribute->set_variation(false);

        $attributes[$taxonomy] = $attribute;
        $product->set_attributes($attributes);
        $product->save();
    }
}
