<?php

/**
 * Settings view for BeautyFort integration
 * Variables available: none
 */

if (!defined('ABSPATH')) exit;
?>

<div class="nebf-settings">

    <h2><?php esc_html_e('Plugin Settings', 'nebf-mvc'); ?></h2>

    <p><?php esc_html_e('Configure your credentials and product name preferences for the Nordic Equilibro - BeautyFort integration.', 'nebf-mvc'); ?></p>

    <form method="post">
        <?php wp_nonce_field('nebf_save_settings'); ?>

        <?php $selected_currency = get_option('nebf_currency', 'SEK'); ?>

        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="nebf_username"><?php esc_html_e('Username', 'nebf-mvc'); ?></label>
                    </th>
                    <td>
                        <input name="nebf_username" type="text" id="nebf_username"
                            value="<?php echo esc_attr(get_option('nebf_username', '')); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e('Your BeautyFort username.', 'nebf-mvc'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="nebf_api_key"><?php esc_html_e('API Key', 'nebf-mvc'); ?></label>
                    </th>
                    <td>
                        <input name="nebf_api_key" type="password" id="nebf_api_key"
                            value="<?php echo esc_attr(get_option('nebf_api_key', '')); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e('Your BeautyFort API key.', 'nebf-mvc'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e('Separate Brand from Product Name', 'nebf-mvc'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="nebf_separate_brand"
                                value="1" <?php checked(get_option('nebf_separate_brand', 0), 1); ?>>
                            <?php esc_html_e('Enable to store brand separately and not prepend it to product names.', 'nebf-mvc'); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="nebf_currency"><?php esc_html_e('Currency', 'nebf-mvc'); ?></label>
                    </th>
                    <td>
                        <select name="nebf_currency" id="nebf_currency">
                            <option value="SEK" <?php selected($selected_currency, 'SEK'); ?>>SEK</option>
                            <option value="NOK" <?php selected($selected_currency, 'NOK'); ?>>NOK</option>
                            <option value="DKK" <?php selected($selected_currency, 'DKK'); ?>>DKK</option>
                            <option value="EUR" <?php selected($selected_currency, 'EUR'); ?>>EUR</option>
                            <option value="GBP" <?php selected($selected_currency, 'GBP'); ?>>GBP</option>
                            <option value="USD" <?php selected($selected_currency, 'USD'); ?>>USD</option>
                        </select>
                        <p class="description"><?php esc_html_e('Select the currency used by your BeautyFort account. Default is SEK.', 'nebf-mvc'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e('Web Price Lookup', 'nebf-mvc'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="nebf_enable_web_price_lookup"
                                value="1" <?php checked(get_option('nebf_enable_web_price_lookup', 0), 1); ?>>
                            <?php esc_html_e('Enable external web price lookup in product Extra info.', 'nebf-mvc'); ?>
                        </label>
                        <p class="description"><?php esc_html_e("When enabled, prices are fetched when you open the Products tab and shown in each product's Extra info section.", 'nebf-mvc'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="nebf_margin_type"><?php esc_html_e('Profit Margin Type', 'nebf-mvc'); ?></label>
                    </th>
                    <td>
                        <select name="nebf_margin_type" id="nebf_margin_type">
                            <option value="percent" <?php selected(get_option('nebf_margin_type', 'percent'), 'percent'); ?>>
                                <?php esc_html_e('Percent (%)', 'nebf-mvc'); ?>
                            </option>
                            <option value="fixed" <?php selected(get_option('nebf_margin_type', 'percent'), 'fixed'); ?>>
                                <?php esc_html_e('Fixed Amount', 'nebf-mvc'); ?>
                            </option>
                        </select>
                        <p class="description"><?php esc_html_e('Choose how profit is added on top of cost price.', 'nebf-mvc'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="nebf_margin_value"><?php esc_html_e('Profit Margin Value', 'nebf-mvc'); ?></label>
                    </th>
                    <td>
                        <input name="nebf_margin_value" type="number" step="0.01" min="0" id="nebf_margin_value"
                            value="<?php echo esc_attr((string) get_option('nebf_margin_value', '0')); ?>" class="small-text">
                        <p class="description"><?php echo esc_html(sprintf(__('Example: 25 means +25%% in Percent mode, or +25 %s in Fixed Amount mode.', 'nebf-mvc'), $selected_currency)); ?></p>
                    </td>
                </tr>

            </tbody>
        </table>

        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e('Save Changes', 'nebf-mvc'); ?>">
        </p>

    </form>

</div>
