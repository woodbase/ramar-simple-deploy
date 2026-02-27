<?php

if (!defined('ABSPATH')) exit;

function nebf_import_products()
{
    error_log('NEBF: Starting product import from BeautyFort API');

    // Hämta data från BeautyFort
    $rows = nebf_api_request_stockfile();

    if (is_wp_error($rows)) {
        error_log('NEBF: API returned WP_Error');
        return $rows;
    }

    if (empty($rows)) {
        error_log('NEBF: No rows returned from API');
        return new WP_Error('nebf_empty', 'Inga produkter inlästa!');
    }

    $products = [];
    $skipped  = 0;

    foreach ($rows as $row) {
        $row = json_decode(json_encode($row), true);

        if (empty($row['StockCode'])) {
            $skipped++;
            continue;
        }

        $bf_id = sanitize_text_field($row['StockCode']);

        $stock = 0;
        if (isset($row['StockLevel'])) {
            if (is_numeric($row['StockLevel'])) {
                $stock = (int)$row['StockLevel'];
            } elseif (is_array($row['StockLevel']) && isset($row['StockLevel']['Available'])) {
                $stock = (int)$row['StockLevel']['Available'];
            }
        }

        $raw_name = $row['FullName'] ?? '';
        $brand    = $row['Brand'] ?? '';
        $clean_name = nebf_clean_product_name($raw_name, $brand);

        $products[$bf_id] = [
            'bf_id' => $bf_id,
            'sku'   => $bf_id,
            'stock_level' => $stock,
            'price' => isset($row['Price']) ? (float)$row['Price'] : 0,

            // Produktdata
            'barcode' => $row['Barcode'] ?? '',
            'brand'   => $brand,
            'category' => $row['Category'] ?? '',
            'collection' => $row['Collection'] ?? '',
            'description' => $row['Description'] ?? '',
            'fullname' => $clean_name,
            'rawname'  => $raw_name,
            'gender'   => $row['Gender'] ?? '',
            'size'     => $row['Size'] ?? '',
            'type'     => $row['Type'] ?? '',

            // Media
            'high_res_image_url' => $row['HighResImageUrl'] ?? '',
            'thumbnail_url'    => $row['ThumbnailImageUrl'] ?? '',
            'image_last_updated' => $row['ImageLastUpdated'] ?? '',

            // Historik
            'last_purchased_date' => $row['LastPurchasedDate'] ?? '',
            'last_purchased_price' => $row['LastPurchasedPrice'] ?? '',
            'your_rating'       => $row['YourRating'] ?? '',
            'your_stock_code'   => $row['YourStockCode'] ?? '',

            // Alltid rådata
            'raw' => $row,
        ];
    }

    // --- Spara alltid i DB ---
    update_option('nebf_beautyfort_products', $products);

    error_log('NEBF: Cached products in DB: ' . count($products));
    error_log('NEBF: Skipped products: ' . $skipped);
    error_log('NEBF: Total rows from API: ' . count($rows));

    return [
        'cached'  => count($products),
        'skipped' => $skipped,
        'total'   => count($rows),
    ];
}
