<?php
if (!defined('ABSPATH')) exit;

/**
 * Hjälpfunktioner för Beauty Fort
 */

/**
 * Statusikon för produkt
 */
function nebf_get_status_icon($status)
{
    switch ($status) {
        case 'publish':
            return '<span class="dashicons dashicons-yes-alt" style="color: #46b450;" title="Publicerad"></span>';
        case 'draft':
            return '<span class="dashicons dashicons-edit" style="color: #ffb900;" title="Utkast"></span>';
        default:
            return '<span class="dashicons dashicons-warning" style="color: #dc3232;" title="Okänd status"></span>';
    }
}

/**
 * Thumbnail för produkt
 */
function nebf_get_product_thumbnail($product, $size = 'thumbnail')
{
    $image_id = $product->get_image_id();

    if ($image_id) {
        return wp_get_attachment_image($image_id, $size, false, [
            'style' => 'width:50px;height:auto;border-radius:4px;'
        ]);
    }

    return '<span class="dashicons dashicons-format-image" style="font-size:32px;color:#ccc;"></span>';
}

/**
 * Renderar produktdetaljer
 */
function nebf_render_product_details($product)
{
?>
    <div style="padding:16px; background:#f9f9f9; border-left:4px solid #2271b1;">
        <h3><?php echo esc_html($product->get_name()); ?></h3>

        <p><strong>Status:</strong> <?php echo esc_html($product->get_status()); ?></p>
        <p><strong>SKU:</strong> <?php echo esc_html($product->get_sku()); ?></p>
        <p><strong>Beskrivning:</strong><br><?php echo wp_kses_post($product->get_description() ?: 'Ingen beskrivning'); ?></p>
        <p><strong>Kort beskrivning:</strong><br><?php echo wp_kses_post($product->get_short_description() ?: '–'); ?></p>
        <p><strong>Lager:</strong>
            <?php
            echo $product->get_manage_stock()
                ? intval($product->get_stock_quantity())
                : 'Ej lagerstyrd';
            ?>
        </p>
        <p><strong>Pris:</strong> <?php echo wc_price($product->get_price()); ?></p>

        <p><strong>Attribut:</strong><br>
            <?php
            $attributes = $product->get_attributes();
            if ($attributes) {
                echo '<ul>';
                foreach ($attributes as $attr) {
                    echo '<li><strong>' .
                        esc_html(wc_attribute_label($attr->get_name())) .
                        ':</strong> ' .
                        esc_html(implode(', ', $attr->get_options())) .
                        '</li>';
                }
                echo '</ul>';
            } else {
                echo 'Inga attribut';
            }
            ?>
        </p>

        <p>
            <a href="<?php echo esc_url(get_edit_post_link($product->get_id())); ?>"
                class="button button-secondary">
                Öppna i WooCommerce
            </a>
        </p>
    </div>
<?php
}

/**
 * Hämtar produkter från WooCommerce
 */
function nebf_get_wc_products($args = [])
{
    $products = wc_get_products(array_merge([
        'limit'    => 200,
        'status'   => ['publish', 'draft'],
        'orderby'  => 'date',
        'order'    => 'DESC',
        'meta_query' => [
            [
                'key'     => '_nebf_product_id',
                'compare' => 'EXISTS',
            ]
        ]
    ], $args));

    $data = [];
    foreach ($products as $product) {
        $data[] = [
            'id'         => $product->get_id(),
            'name'       => $product->get_name(),
            'sku'        => $product->get_sku(),
            'price'      => $product->get_price(),
            'stock'      => $product->get_stock_quantity(),
            'status'     => $product->get_status(),
            'image'      => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
            'type'       => $product->get_type(),
            'weight'     => $product->get_weight(),
            'categories' => wc_get_product_category_list($product->get_id()),
            'permalink'  => get_edit_post_link($product->get_id()),
            'raw'        => $product, // för detaljvisning
        ];
    }

    return $data;
}

/**
 * Rensar produktnamn från varumärke
 */
function nebf_clean_product_name($name, $brand)
{
    $strip = get_option('nebf_strip_brand_from_name', 1);

    if (!$strip || !$brand || !$name) return $name;

    $patterns = [
        '/^' . preg_quote($brand, '/') . '\s*[-–]?\s*/i',
    ];

    return trim(preg_replace($patterns, '', $name));
}

/**
 * Formaterar ett fält (sträng eller array)
 */
function nebf_format_field($field)
{
    if (empty($field)) return '—';
    if (is_array($field)) {
        return implode('<br>', array_map('esc_html', $field));
    }
    return esc_html($field);
}

/**
 * Genererar sorteringslänk för tabellkolumner
 */
function nebf_sort_link($column, $current_orderby, $current_order, $label)
{
    $order = 'ASC';
    $arrow = ' ⇅';

    if ($current_orderby === $column) {
        if ($current_order === 'ASC') {
            $order = 'DESC';
            $arrow = ' ▲';
        } else {
            $order = 'ASC';
            $arrow = ' ▼';
        }
    }

    $url = add_query_arg([
        'orderby'    => $column,
        'order'      => $order,
        'paged'      => 1,
        'per_page'   => $_GET['per_page'] ?? 100,
        's'          => $_GET['s'] ?? '',
        'brand'      => $_GET['brand'] ?? '',
        'collection' => $_GET['collection'] ?? '',
        'status'     => $_GET['status'] ?? '',
    ]);

    return '<a href="' . esc_url($url) . '">' . esc_html($label) . $arrow . '</a>';
}

function nebf_get_cached_products() {
    $products = get_option('nebf_beautyfort_products', []);
    return is_array($products) ? $products : [];
}

/**
 * Render Material Icon
 */
function nebf_icon($name, $class = '', $size = 20) {

    $style = "font-size: {$size}px; vertical-align: middle;";

    return sprintf(
        '<span class="material-icons %s" style="%s">%s</span>',
        esc_attr($class),
        esc_attr($style),
        esc_html($name)
    );
}

function nebf_sync_status_icon($is_imported) {

    if ($is_imported) {
        return '<span title="Synkad till WooCommerce">'
            . nebf_icon('check_circle', 'nebf-icon-success')
            . '</span>';
    }

    return '<span title="Inte synkad till WooCommerce">'
        . nebf_icon('radio_button_unchecked', 'nebf-icon-muted')
        . '</span>';
}
