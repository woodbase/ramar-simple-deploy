<?php

function nebf_sync_stock_and_price() {

    $products = nebf_api_request('products');
    if (is_wp_error($products)) return;

    foreach ($products as $bf) {

        $existing = get_posts([
            'post_type' => 'product',
            'meta_key'  => '_nebf_product_id',
            'meta_value'=> $bf['id'],
            'numberposts'=> 1
        ]);

        if (!$existing) continue;

        $product = wc_get_product($existing[0]->ID);

        $product->set_regular_price($bf['price']);
        $product->set_stock_quantity($bf['stock']);
        $product->set_stock_status(
            $bf['stock'] > 0 ? 'instock' : 'outofstock'
        );

        $product->save();
    }
}
