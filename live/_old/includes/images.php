<?php

function nebf_attach_image($url, $product_id) {

    if (has_post_thumbnail($product_id)) return;

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $attachment_id = media_sideload_image($url, $product_id, null, 'id');

    if (!is_wp_error($attachment_id)) {
        set_post_thumbnail($product_id, $attachment_id);
    }
}
