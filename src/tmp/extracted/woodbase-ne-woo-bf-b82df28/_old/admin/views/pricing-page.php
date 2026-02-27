<?php
if (!defined('ABSPATH')) exit;

function nebf_render_pricing_tab()
{

    $settings = get_option('nebf_pricing_settings', [
        'default_type'  => 'percent',
        'default_value' => 30,
        'rounding'      => 'none',
    ]);

    settings_errors('nebf_pricing');
?>

    <h2>Pricing Settings</h2>

    <form method="post">
        <?php wp_nonce_field('nebf_pricing_nonce'); ?>

        <table class="form-table">
            <tr>
                <th>Global Margin</th>
                <td>
                    <select name="default_type">
                        <option value="percent" <?php selected($settings['default_type'], 'percent'); ?>>
                            Percent (%)
                        </option>
                        <option value="fixed" <?php selected($settings['default_type'], 'fixed'); ?>>
                            Fixed (SEK)
                        </option>
                    </select>

                    <input type="number"
                        step="0.01"
                        name="default_value"
                        value="<?php echo esc_attr($settings['default_value']); ?>">
                </td>
            </tr>

            <tr>
                <th>Rounding</th>
                <td>
                    <select name="rounding">
                        <option value="none" <?php selected($settings['rounding'], 'none'); ?>>None</option>
                        <option value="99" <?php selected($settings['rounding'], '99'); ?>>99-ending</option>
                        <option value="9" <?php selected($settings['rounding'], '9'); ?>>9-ending</option>
                        <option value="whole" <?php selected($settings['rounding'], 'whole'); ?>>Whole number</option>
                    </select>
                </td>
            </tr>
        </table>

        <?php submit_button('Save Pricing Settings', 'primary', 'nebf_save_pricing'); ?>
    </form>

    <hr>

    <form method="post">
        <?php submit_button('Recalculate All Products', 'secondary', 'nebf_recalculate_all'); ?>
    </form>

<?php
}
