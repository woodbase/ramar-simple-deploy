<?php

namespace NEBF\Core;

use NEBF\Controllers\AdminMenuController;
use NEBF\Services\WebPriceLookupQueueService;

if (!defined('ABSPATH')) exit;

/**
 * Main plugin class.
 */
class Plugin
{

    /**
     * Initialize plugin.
     */
    public function init(): void
    {
        // Register admin menu
        $admin_menu = new AdminMenuController();
        $admin_menu->register_hooks();
        add_action(WebPriceLookupQueueService::HOOK, [new WebPriceLookupQueueService(), 'process_batch']);

        // Enqueue admin assets (CSS/JS)
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    /**
     * Enqueue CSS/JS for admin pages
     */
    public function enqueue_admin_assets(): void
    {
        $screen = get_current_screen();

        // Only load for our plugin page
        if ($screen && str_contains($screen->id, 'nebf-mvc')) {
            wp_enqueue_style(
                'nebf-admin',
                NEBF_MVC_URL . 'assets/css/admin.css',
                [],
                NEBF_MVC_VERSION
            );


            // Om du senare vill ha JS:
            // wp_enqueue_script(
            //     'nebf-admin-js',
            //     NEBF_MVC_URL . 'admin/assets/js/admin.js',
            //     ['jquery'],
            //     NEBF_MVC_VERSION,
            //     true
            // );
        }
    }
}
