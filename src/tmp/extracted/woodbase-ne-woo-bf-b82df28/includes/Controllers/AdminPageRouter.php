<?php

namespace NEBF\Controllers;

if (!defined('ABSPATH')) exit;

/**
 * Handles routing of admin tabs and renders the correct controller
 */
class AdminPageRouter {

    public function handle(): void
    {
        // Determine which tab to show
        $tab = $_GET['tab'] ?? 'dashboard';

        // Render header + tabs (always visible)
        $this->render_header_tabs($tab);

        // Route to the proper controller
        switch ($tab) {
            case 'products':
                $controller = new ProductsController();
                break;

            case 'settings':
                $controller = new SettingsController();
                break;

            default:
                $controller = new DashboardController();
                break;
        }

        // Execute the controller
        $controller->handle();
    }

    /**
     * Renders the page header and nav tabs
     */
    private function render_header_tabs(string $active_tab): void
    {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Nordic Equilibro - BeautyFort integration', 'nebf-mvc'); ?></h1>

            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_url(admin_url('admin.php?page=nebf-mvc&tab=dashboard')); ?>"
                   class="nav-tab <?php echo $active_tab === 'dashboard' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Dashboard', 'nebf-mvc'); ?>
                </a>

                <a href="<?php echo esc_url(admin_url('admin.php?page=nebf-mvc&tab=products')); ?>"
                   class="nav-tab <?php echo $active_tab === 'products' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Products', 'nebf-mvc'); ?>
                </a>

                <a href="<?php echo esc_url(admin_url('admin.php?page=nebf-mvc&tab=settings')); ?>"
                   class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Settings', 'nebf-mvc'); ?>
                </a>
            </h2>
        </div>
        <?php
    }
}
