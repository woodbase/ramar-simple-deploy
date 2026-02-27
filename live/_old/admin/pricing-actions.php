<?php
if (!defined('ABSPATH')) exit;

/**
 * Hantera Pricing POST-actions
 */
function nebf_handle_pricing_actions()
{

    if (!isset($_GET['page']) || $_GET['page'] !== 'nordic-equilibro-beautyfort') {
        return;
    }

    if (!isset($_GET['tab']) || $_GET['tab'] !== 'pricing') {
        return;
    }

    // SAVE SETTINGS
    if (isset($_POST['nebf_save_pricing'])) {
        check_admin_referer('nebf_pricing_nonce');

        update_option('nebf_pricing_settings', [
            'default_type'  => sanitize_text_field($_POST['default_type']),
            'default_value' => floatval($_POST['default_value']),
            'rounding'      => sanitize_text_field($_POST['rounding']),
        ]);

        add_settings_error('nebf_pricing', 'saved', 'Pricing settings saved.', 'updated');
    }

    // RECALCULATE
    if (isset($_POST['nebf_recalculate_all'])) {
        NEBF_Pricing_Engine::recalculate_all_products();
        add_settings_error('nebf_pricing', 'recalculated', 'All product prices recalculated.', 'updated');
    }
}

add_action('admin_init', 'nebf_handle_pricing_actions');
