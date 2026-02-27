<?php

namespace NEBF\Controllers;

if (!defined('ABSPATH')) exit;

/**
 * Controller for Settings tab
 */
class SettingsController extends AbstractAdminController
{
    private const ALLOWED_CURRENCIES = ['SEK', 'NOK', 'DKK', 'EUR', 'GBP', 'USD'];

    public function handle(): void
    {
        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('nebf_save_settings')) {

            if (isset($_POST['nebf_username'])) {
                update_option('nebf_username', sanitize_text_field($_POST['nebf_username']));
            }

            if (isset($_POST['nebf_api_key'])) {
                update_option('nebf_api_key', sanitize_text_field($_POST['nebf_api_key']));
            }

            // Checkbox may be unset if unchecked
            $separate = isset($_POST['nebf_separate_brand']) ? 1 : 0;
            update_option('nebf_separate_brand', $separate);
            $enable_web_lookup = isset($_POST['nebf_enable_web_price_lookup']) ? 1 : 0;
            update_option('nebf_enable_web_price_lookup', $enable_web_lookup);


            if (isset($_POST['nebf_margin_type'])) {
                $margin_type = sanitize_key((string) $_POST['nebf_margin_type']);
                if (!in_array($margin_type, ['percent', 'fixed'], true)) {
                    $margin_type = 'percent';
                }
                update_option('nebf_margin_type', $margin_type);
            }

            if (isset($_POST['nebf_margin_value'])) {
                $margin_value = (float) $_POST['nebf_margin_value'];
                update_option('nebf_margin_value', max(0, $margin_value));
            }

            if (isset($_POST['nebf_currency'])) {
                $currency = strtoupper(sanitize_text_field((string) $_POST['nebf_currency']));
                if (!in_array($currency, self::ALLOWED_CURRENCIES, true)) {
                    $currency = 'SEK';
                }

                update_option('nebf_currency', $currency);
            }

            // Feedback notice
            add_action('admin_notices', function () {
                echo '<div class="notice notice-success is-dismissible"><p>'
                    . esc_html__('Settings saved successfully.', 'nebf-mvc') . '</p></div>';
            });
        }

        if (!get_option('nebf_currency')) {
            update_option('nebf_currency', 'SEK');
        }

        $this->render('settings');
    }
}
