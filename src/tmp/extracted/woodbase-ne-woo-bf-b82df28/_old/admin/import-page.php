<?php

add_action('admin_notices', function () {
    if (!isset($_GET['nebf_import'])) return;

    $result = nebf_import_products();

    if (is_wp_error($result)) {
        echo '<div class="error"><p>'.$result->get_error_message().'</p></div>';
    } else {
        echo '<div class="updated"><p>'.$result.' produkter importerade som utkast.</p></div>';
    }
});
