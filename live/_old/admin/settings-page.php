<?php
function nebf_render_settings_tab()
{
    $last_fetch       = get_option('nebf_last_fetch');
    $last_fetch_count = get_option('nebf_last_fetch_count');

    // Visa notices efter redirect
    if (isset($_GET['nebf_notice'])) {
        $type = $_GET['nebf_notice'] === 'success'
            ? 'notice-success'
            : 'notice-error';

        $message = '';

        if (isset($_GET['imported'])) {
            $message = intval($_GET['imported']) . ' produkter importerades.';
        }

        if (isset($_GET['message'])) {
            $message = sanitize_text_field($_GET['message']);
        }

        echo '<div class="notice ' . esc_attr($type) . '"><p>' .
            esc_html($message) .
            '</p></div>';
    }
    ?>

    <form method="post" action="options.php">
        <?php settings_fields('nebf_settings_group'); ?>

        <table class="form-table">

            <tr>
                <th>API Username</th>
                <td>
                    <input type="text"
                           name="nebf_api_username"
                           value="<?php echo esc_attr(get_option('nebf_api_username')); ?>"
                           class="regular-text">
                </td>
            </tr>

            <tr>
                <th>API Secret</th>
                <td>
                    <input type="password"
                           name="nebf_api_secret"
                           value="<?php echo esc_attr(get_option('nebf_api_secret')); ?>"
                           class="regular-text">
                </td>
            </tr>

            <tr>
                <th scope="row">Produktnamn</th>
                <td>
                    <label>
                        <input type="checkbox"
                               name="nebf_strip_brand_from_name"
                               value="1"
                               <?php checked(1, get_option('nebf_strip_brand_from_name', 1)); ?>>
                        Ta bort varumärke från produktnamn vid import
                    </label>
                    <p class="description">
                        Om markerad tas varumärket bort från början av produktnamnet
                        (t.ex. <em>Maria Åkerberg – Shea Balm</em> → <em>Shea Balm</em>).
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">Test mode</th>
                <td>
                    <label>
                        <input type="checkbox"
                               name="nebf_api_testmode"
                               value="1"
                               <?php checked(get_option('nebf_api_testmode'), '1'); ?>>
                        Använd testläge (sandbox)
                    </label>
                </td>
            </tr>

        </table>

        <?php submit_button('Spara inställningar'); ?>
    </form>

    <hr>

    <form method="post">
        <?php wp_nonce_field('nebf_import_nonce'); ?>
        <input type="hidden" name="nebf_action" value="import_products">
        <input type="submit"
               class="button button-primary"
               value="Hämta produkter från BeautyFort">
    </form>

    <?php if ($last_fetch): ?>
        <p>
            Senast hämtad data:
            <?php echo esc_html($last_fetch); ?>
            (<?php echo intval($last_fetch_count); ?> produkter)
        </p>
    <?php endif; ?>

    <form method="post" style="margin-top:1em;">
        <?php wp_nonce_field('nebf_test_api_nonce'); ?>
        <input type="hidden" name="nebf_action" value="test_api">
        <input type="submit"
               class="button"
               value="Testa API-anslutning">
    </form>

<?php
}
