<?php

namespace NEBF\Controllers;

use NEBF\Models\ProductRepository;
use NEBF\Services\BeautyFortApiService;
use NEBF\Services\WebPriceLookupQueueService;

if (!defined('ABSPATH')) exit;

/**
 * Dashboard controller
 */
class DashboardController extends AbstractAdminController
{
    public function handle(): void
    {
        $repo = new ProductRepository();
        $queue_service = new WebPriceLookupQueueService();

        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            isset($_POST['nebf_sync_all']) &&
            check_admin_referer('nebf_sync_products')
        ) {
            $load_failed = false;

            $api_products = $this->fetch_products_from_api();
            if (is_wp_error($api_products)) {
                $load_failed = true;
                $debug = get_option('nebf_last_api_debug', []);
                $stage = is_array($debug) ? ($debug['stage'] ?? '') : '';

                $message = $api_products->get_error_message();
                if (is_string($stage) && $stage !== '') {
                    $message .= ' ' . sprintf(__('(Debug stage: %s)', 'nebf-mvc'), $stage);
                }
                $message .= ' ' . __('Raw API response saved in option: nebf_last_api_raw_response', 'nebf-mvc');
                $this->notices->add($message, 'error');

                if (is_array($debug)) {
                    $parts = [];
                    if (!empty($debug['http_code'])) {
                        $parts[] = 'HTTP ' . (int) $debug['http_code'];
                    }
                    if (!empty($debug['stage'])) {
                        $parts[] = 'stage=' . sanitize_text_field((string) $debug['stage']);
                    }
                    if (!empty($debug['body_preview']) && is_string($debug['body_preview'])) {
                        $preview = preg_replace('/\s+/', ' ', substr($debug['body_preview'], 0, 180));
                        $parts[] = 'preview=' . $preview;
                    }
                    if (!empty($parts)) {
                        $this->notices->add(
                            __('API debug: ', 'nebf-mvc') . implode(' | ', $parts),
                            'warning'
                        );
                    }
                }

                $api_products = [];
            }

            $loaded_count = count($api_products);
            update_option('nebf_last_sync', time());

            $run_web_price_lookup = isset($_POST['nebf_sync_web_price_lookup']) && $_POST['nebf_sync_web_price_lookup'] === '1';

            // Respect "Separate Brand" setting.
            $separate = (bool) get_option('nebf_separate_brand', 0);
            foreach ($api_products as &$product) {
                $brand = (string) ($product['brand'] ?? '');
                $name = (string) ($product['fullname'] ?? '');

                if ($separate && $brand !== '' && stripos($name, $brand) === 0) {
                    $name = trim(substr($name, strlen($brand)));
                }

                $product['brand'] = $brand;
                $product['fullname'] = $name;
            }
            unset($product);

            if ($loaded_count > 0) {
                $repo->save_products($api_products);

                if ($run_web_price_lookup) {
                    $queue_info = $queue_service->enqueue_products($api_products);
                    $this->notices->add(
                        sprintf(
                            __(
                                'Web price lookup runs in background batches to protect server performance. Added %1$d products to queue (%2$d pending).',
                                'nebf-mvc'
                            ),
                            (int) $queue_info['enqueued'],
                            (int) $queue_info['pending']
                        ),
                        'info'
                    );
                }

                $this->notices->add(
                    sprintf(
                        _n(
                            '%d product loaded from BeautyFort.',
                            '%d products loaded from BeautyFort.',
                            $loaded_count,
                            'nebf-mvc'
                        ),
                        $loaded_count
                    ),
                    'success'
                );
            } elseif (!$load_failed) {
                $this->notices->add(
                    __('No products were loaded. Check API credentials in Settings and try again.', 'nebf-mvc'),
                    'warning'
                );
            }
        }

        $all_products = $repo->get_all();
        $total_products = count($all_products);
        $synced = 0;

        foreach ($all_products as $product) {
            if (!is_array($product)) {
                continue;
            }
            if (!empty($product['sku']) && wc_get_product_id_by_sku((string) $product['sku'])) {
                $synced++;
            }
        }

        $this->render('dashboard', [
            'total_products'    => $total_products,
            'synced_products'   => $synced,
            'unsynced_products' => $total_products - $synced,
            'last_sync'         => get_option('nebf_last_sync'),
            'web_lookup_status' => $queue_service->get_status(),
            'default_web_lookup_enabled' => (bool) get_option('nebf_enable_web_price_lookup', 0),
        ]);
    }

    /**
     * Fetch and map products from BeautyFort API.
     *
     * @return array|\WP_Error
     */
    private function fetch_products_from_api()
    {
        $service = new BeautyFortApiService();
        $stock_xml = $service->request_stockfile();

        if (is_wp_error($stock_xml)) {
            return $stock_xml;
        }

        return $this->map_stock_xml_to_products($stock_xml);
    }

    /**
     * Map stock XML rows to the internal product schema.
     *
     * @param \SimpleXMLElement $stock_xml
     * @return array|\WP_Error
     */
    private function map_stock_xml_to_products(\SimpleXMLElement $stock_xml)
    {
        $products = [];

        foreach ($stock_xml as $item) {
            $row = $this->xml_node_to_array($item);
            if (!is_array($row)) {
                continue;
            }

            $mapped = $this->map_stock_item_to_product($row);
            if ($mapped === null) {
                continue;
            }

            $products[] = $mapped;
        }

        if (empty($products)) {
            return new \WP_Error('nebf_no_products', __('Stock file parsed, but no products were found.', 'nebf-mvc'));
        }

        return $products;
    }

    private function xml_node_to_array(\SimpleXMLElement $node): array
    {
        $json = wp_json_encode($node);
        if (!is_string($json) || $json === '') {
            return [];
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    private function map_stock_item_to_product(array $row): ?array
    {
        $value = static function (array $data, array $keys, string $default = ''): string {
            foreach ($keys as $key) {
                if (array_key_exists($key, $data) && !is_array($data[$key]) && !is_object($data[$key])) {
                    return (string) $data[$key];
                }
            }

            foreach ($data as $k => $v) {
                if (!is_string($k)) {
                    continue;
                }
                foreach ($keys as $candidate) {
                    if (strcasecmp($k, $candidate) === 0 && !is_array($v) && !is_object($v)) {
                        return (string) $v;
                    }
                }
            }

            return $default;
        };

        // Match legacy import behavior: StockCode is required.
        $bf_id = sanitize_text_field($value($row, ['StockCode']));
        if ($bf_id === '') {
            return null;
        }

        $stock_level = 0;
        if (isset($row['StockLevel'])) {
            if (is_numeric($row['StockLevel'])) {
                $stock_level = (int) $row['StockLevel'];
            } elseif (is_array($row['StockLevel']) && isset($row['StockLevel']['Available'])) {
                $stock_level = (int) $row['StockLevel']['Available'];
            }
        }

        $raw_name = $value($row, ['FullName']);
        $brand = $value($row, ['Brand']);
        $clean_name = $raw_name;
        if (function_exists('nebf_clean_product_name')) {
            $clean_name = (string) nebf_clean_product_name($raw_name, $brand);
        }

        return [
            'bf_id'                => $bf_id,
            'sku'                  => $bf_id,
            'stock_level'          => $stock_level,
            'price'                => (float) $value($row, ['Price'], '0'),
            'barcode'              => $value($row, ['Barcode']),
            'brand'                => $brand,
            'category'             => $value($row, ['Category']),
            'collection'           => $value($row, ['Collection']),
            'description'          => $value($row, ['Description']),
            'fullname'             => $clean_name,
            'rawname'              => $raw_name,
            'gender'               => $value($row, ['Gender']),
            'size'                 => $value($row, ['Size']),
            'type'                 => $value($row, ['Type']),
            'high_res_image_url'   => $value($row, ['HighResImageUrl']),
            'thumbnail_url'        => $value($row, ['ThumbnailImageUrl']),
            'image_last_updated'   => $value($row, ['ImageLastUpdated']),
            'last_purchased_date'  => $value($row, ['LastPurchasedDate']),
            'last_purchased_price' => $value($row, ['LastPurchasedPrice']),
            'your_rating'          => $value($row, ['YourRating']),
            'your_stock_code'      => $value($row, ['YourStockCode']),
            'break_bulk_reference' => $value($row, ['BreakBulkReference']),
            'raw'                  => $row,
        ];
    }
}
