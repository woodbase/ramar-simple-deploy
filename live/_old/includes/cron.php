<?php

add_action('nebf_hourly_sync', 'nebf_sync_stock_and_price');

register_activation_hook(__FILE__, function () {
    if (!wp_next_scheduled('nebf_hourly_sync')) {
        wp_schedule_event(time(), 'hourly', 'nebf_hourly_sync');
    }
});

register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('nebf_hourly_sync');
});
