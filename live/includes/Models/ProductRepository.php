<?php

namespace NEBF\Models;

use NEBF\Services\ProductSyncService;
use NEBF\Services\WebPriceLookupService;

if (!defined('ABSPATH')) exit;

class ProductRepository
{
    private WebPriceLookupService $webPriceLookup;

    public function __construct()
    {
        $this->webPriceLookup = new WebPriceLookupService();
    }

    /**
     * Calculate sale price from cost and pricing settings.
     *
     * Modes:
     * - percent: sale = cost * (1 + margin/100)
     * - fixed:   sale = cost + margin
     */
    public function calculate_sale_price(array $product): float
    {
        $cost_price = (float) ($product['price'] ?? 0);
        $mode = get_option('nebf_margin_type', 'percent');
        $margin_value = (float) get_option('nebf_margin_value', 0);

        if ($mode === 'fixed') {
            return max(0, round($cost_price + $margin_value, 2));
        }

        return max(0, round($cost_price * (1 + ($margin_value / 100)), 2));
    }

    /**
     * Get paginated products with search & filters
     */
    public function get_paginated(
        int $page,
        int $per_page,
        string $search = '',
        array $filters = []
    ): array {

        $all_products = get_option('nebf_beautyfort_products', []);

        if (!is_array($all_products)) {
            $all_products = [];
        }

        // Convert associative array (bf_id => product)
        // to indexed array to make array_slice work correctly
        $products = array_values($all_products);

        // ---------------------------
        // Apply search + filters
        // ---------------------------

        $products = array_filter($products, function ($product) use ($search, $filters) {
            $bf_id = (string) ($product['bf_id'] ?? '');
            $sku = (string) ($product['sku'] ?? '');
            $synced_by_bf_id = false;

            if ($bf_id !== '') {
                $existing = get_posts([
                    'post_type'   => 'product',
                    'meta_key'    => '_beautyfort_id',
                    'meta_value'  => $bf_id,
                    'fields'      => 'ids',
                    'numberposts' => 1,
                ]);
                $synced_by_bf_id = !empty($existing);
            }

            $is_synced = $synced_by_bf_id || (!empty($sku) && (bool) wc_get_product_id_by_sku($sku));

            // Status filter
            if (!empty($filters['status'])) {
                if ($filters['status'] === 'imported' && !$is_synced) {
                    return false;
                }
                if ($filters['status'] === 'not_imported' && $is_synced) {
                    return false;
                }
            }

            // Brand filter
            if (
                !empty($filters['brand']) &&
                strcasecmp($product['brand'] ?? '', $filters['brand']) !== 0
            ) {
                return false;
            }

            // Collection filter
            if (
                !empty($filters['collection']) &&
                strcasecmp($product['collection'] ?? '', $filters['collection']) !== 0
            ) {
                return false;
            }

            // Search filter
            if (!empty($search)) {
                $haystack = strtolower(
                    ($product['fullname'] ?? '') . ' ' .
                        ($product['sku'] ?? '') . ' ' .
                        ($product['brand'] ?? '')
                );

                if (strpos($haystack, strtolower($search)) === false) {
                    return false;
                }
            }

            return true;
        });

        $products = array_values($products); // reindex after filter

        // ---------------------------
        // Pagination
        // ---------------------------

        $total_items = count($products);
        $total_pages = max(1, ceil($total_items / $per_page));

        if ($page < 1) {
            $page = 1;
        }

        if ($page > $total_pages) {
            $page = $total_pages;
        }

        $offset = ($page - 1) * $per_page;

        $items = array_slice($products, $offset, $per_page);

        // ---------------------------
        // Mark synced products
        // ---------------------------


        foreach ($items as &$item) {
            $bf_id = (string) ($item['bf_id'] ?? '');
            $sku = (string) ($item['sku'] ?? '');
            $synced_by_bf_id = false;

            if ($bf_id !== '') {
                $existing = get_posts([
                    'post_type'   => 'product',
                    'meta_key'    => '_beautyfort_id',
                    'meta_value'  => $bf_id,
                    'fields'      => 'ids',
                    'numberposts' => 1,
                ]);
                $synced_by_bf_id = !empty($existing);
            }

            $item['synced'] = $synced_by_bf_id || (!empty($sku) && (bool) wc_get_product_id_by_sku($sku));
            $item['sale_price'] = $this->calculate_sale_price($item);
            $lookup = $item['web_price_lookup'] ?? [];
            $item['web_price_lookup'] = is_array($lookup)
                ? $lookup
                : [
                    'query' => '',
                    'matches' => [],
                    'fetched_at' => '',
                    'error' => '',
                ];
        }

        unset($item);

        return [
            'items'       => $items,
            'page'        => $page,
            'total_pages' => $total_pages,
        ];
    }

    /**
     * Get product by BeautyFort ID
     */
    public function get_all(): array
    {
        $all = get_option('nebf_beautyfort_products', []);

        if (!is_array($all)) {
            return [];
        }

        return $all;
    }

    /**
     * Save products from API response into option storage.
     * Stored format: [bf_id => product_data]
     */
    public function save_products(array $products): void
    {
        $normalized = [];

        foreach ($products as $product) {
            if (!is_array($product)) {
                continue;
            }

            $bf_id = (string)($product['bf_id'] ?? $product['id'] ?? '');

            if ($bf_id === '') {
                continue;
            }

            $product['bf_id'] = $bf_id;
            $normalized[$bf_id] = $product;
        }

        update_option('nebf_beautyfort_products', $normalized);
    }

    /**
     * Get product by BeautyFort ID
     */
    public function get_by_bf_id(string $bf_id): ?array
    {
        $all = get_option('nebf_beautyfort_products', []);

        if (!isset($all[$bf_id])) {
            return null;
        }

        return $all[$bf_id];
    }

    /**
     * Sync multiple products
     */
    public function sync_products(array $bf_ids, array $sale_prices = []): array
    {
        $sync_service = new ProductSyncService();
        $synced = 0;
        $failed = 0;

        foreach ($bf_ids as $bf_id) {
            $bf_id = sanitize_text_field((string) $bf_id);
            if ($bf_id === '') {
                continue;
            }

            $product = $this->get_by_bf_id($bf_id);

            if (!$product) {
                $failed++;
                continue;
            }

            $sale_price = isset($sale_prices[$bf_id])
                ? (float) $sale_prices[$bf_id]
                : $this->calculate_sale_price($product);
            $result = $sync_service->sync_beautyfort_product($product, $sale_price);

            if (is_wp_error($result) || (int) $result <= 0) {
                $failed++;
                continue;
            }

            $synced++;
        }

        return [
            'synced' => $synced,
            'failed' => $failed,
        ];
    }
}
