<?php
if (!defined('ABSPATH')) exit;

/** @var array $products */
$products    = $products ?? [];
$items       = is_array($products['items'] ?? null) ? $products['items'] : [];
$page        = $page ?? 1;
$total_pages = $total_pages ?? 1;
$search_term = $search_term ?? '';
$filters     = $filters ?? ['brand' => '', 'collection' => '', 'status' => ''];

// --- Prepare dropdown options ---
$brands      = array_unique(array_filter(array_column($items, 'brand')));
sort($brands);
$collections = array_unique(array_filter(array_column($items, 'collection')));
sort($collections);

$nebf_scalar = static function ($value): string {
    if (is_scalar($value)) {
        return (string) $value;
    }

    if ($value === null) {
        return '';
    }

    if (is_array($value)) {
        $parts = [];
        array_walk_recursive($value, static function ($item) use (&$parts) {
            if (is_scalar($item)) {
                $parts[] = (string) $item;
            }
        });
        return implode(', ', $parts);
    }

    if (is_object($value) && method_exists($value, '__toString')) {
        return (string) $value;
    }

    return '';
};

$nebf_display = static function ($value, string $fallback = '—') use ($nebf_scalar): string {
    $text = trim($nebf_scalar($value));
    return $text !== '' ? $text : $fallback;
};

$nebf_float = static function ($value, float $default = 0.0) use ($nebf_scalar): float {
    if (is_numeric($value)) {
        return (float) $value;
    }

    $text = trim($nebf_scalar($value));
    return is_numeric($text) ? (float) $text : $default;
};
?>

<?php $this->notices->display(); ?>

<!-- ========================= -->
<!-- FILTER FORM -->
<form id="nebf-products-filter-form" class="nebf-products-filter-form" method="get">
    <input type="hidden" name="page" value="nebf-mvc">
    <input type="hidden" name="tab" value="products">
    <input class="nebf-products-filter-form__per-page" type="number" name="per_page" value="<?= esc_attr($_GET['per_page'] ?? 20) ?>" min="1" max="500">
    <input id="nebf-products-search" type="search" name="s" placeholder="<?php _e('Search name / SKU / brand', 'nebf'); ?>" value="<?= esc_attr($search_term); ?>">

    <select name="brand">
        <option value=""><?php _e('All brands', 'nebf'); ?></option>
        <?php foreach ($brands as $b): ?>
            <option value="<?= esc_attr($b); ?>" <?= selected($filters['brand'], $b, false); ?>><?= esc_html($b); ?></option>
        <?php endforeach; ?>
    </select>

    <select name="collection">
        <option value=""><?php _e('All collections', 'nebf'); ?></option>
        <?php foreach ($collections as $c): ?>
            <option value="<?= esc_attr($c); ?>" <?= selected($filters['collection'], $c, false); ?>><?= esc_html($c); ?></option>
        <?php endforeach; ?>
    </select>

    <select name="status">
        <option value=""><?php _e('All status', 'nebf'); ?></option>
        <option value="imported" <?= selected($filters['status'], 'imported', false); ?>><?php _e('Imported', 'nebf'); ?></option>
        <option value="not_imported" <?= selected($filters['status'], 'not_imported', false); ?>><?php _e('Not imported', 'nebf'); ?></option>
    </select>

    <button class="button button-primary"><?php _e('Update', 'nebf'); ?></button>
    <button id="nebf-select-all" type="button" class="button"><?php _e('Select all', 'nebf'); ?></button>
    <button id="nebf-reset-search" type="button" class="button"><?php _e('Reset search', 'nebf'); ?></button>
</form>

<!-- ========================= -->
<!-- SYNC FORM -->
<form method="POST">
    <?php wp_nonce_field('nebf_sync_selected_products'); ?>
    <div class="nebf-products-actions nebf-products-actions--top">
        <button type="submit" name="nebf_sync_selected" class="button button-primary">
            <?php _e('Sync to WooCommerce', 'nebf'); ?>
        </button>
    </div>

    <!-- ========================= -->
    <!-- PRODUCTS TABLE -->
    <table class="widefat striped nebf-products-table">
        <thead>
            <tr>
                <th><?php _e('Select', 'nebf'); ?></th>
                <th scope="col" aria-sort="none">
                    <button type="button" class="button-link nebf-sort-trigger" data-sort-key="status" data-sort-type="number">
                        <?php _e('Status', 'nebf'); ?> <span class="nebf-sort-indicator" aria-hidden="true">↕</span>
                    </button>
                </th>
                <th><?php _e('Image', 'nebf'); ?></th>
                <th scope="col" aria-sort="none">
                    <button type="button" class="button-link nebf-sort-trigger" data-sort-key="name" data-sort-type="text">
                        <?php _e('Name', 'nebf'); ?> <span class="nebf-sort-indicator" aria-hidden="true">↕</span>
                    </button>
                </th>
                <th scope="col" aria-sort="none">
                    <button type="button" class="button-link nebf-sort-trigger" data-sort-key="sku" data-sort-type="text">
                        <?php _e('SKU', 'nebf'); ?> <span class="nebf-sort-indicator" aria-hidden="true">↕</span>
                    </button>
                </th>
                <th scope="col" aria-sort="none">
                    <button type="button" class="button-link nebf-sort-trigger" data-sort-key="brand" data-sort-type="text">
                        <?php _e('Brand', 'nebf'); ?> <span class="nebf-sort-indicator" aria-hidden="true">↕</span>
                    </button>
                </th>
                <th scope="col" aria-sort="none">
                    <button type="button" class="button-link nebf-sort-trigger" data-sort-key="collection" data-sort-type="text">
                        <?php _e('Collection', 'nebf'); ?> <span class="nebf-sort-indicator" aria-hidden="true">↕</span>
                    </button>
                </th>
                <th scope="col" aria-sort="none">
                    <button type="button" class="button-link nebf-sort-trigger" data-sort-key="cost" data-sort-type="number">
                        <?php _e('Cost price', 'nebf'); ?> <span class="nebf-sort-indicator" aria-hidden="true">↕</span>
                    </button>
                </th>
                <th scope="col" aria-sort="none">
                    <button type="button" class="button-link nebf-sort-trigger" data-sort-key="sale" data-sort-type="number">
                        <?php _e('Sale price', 'nebf'); ?> <span class="nebf-sort-indicator" aria-hidden="true">↕</span>
                    </button>
                </th>
                <th scope="col" aria-sort="none">
                    <button type="button" class="button-link nebf-sort-trigger" data-sort-key="margin" data-sort-type="number">
                        <?php _e('Margin %', 'nebf'); ?> <span class="nebf-sort-indicator" aria-hidden="true">↕</span>
                    </button>
                </th>
                <th scope="col" aria-sort="none">
                    <button type="button" class="button-link nebf-sort-trigger" data-sort-key="stock" data-sort-type="number">
                        <?php _e('Stock', 'nebf'); ?> <span class="nebf-sort-indicator" aria-hidden="true">↕</span>
                    </button>
                </th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $i => $product):
                $accordion_id = 'acc-' . $i;

                $cost_price = $nebf_float($product['price'] ?? 0);
                $sale_price = $nebf_float($product['sale_price'] ?? $cost_price); // fallback
                $calc_margin = $cost_price > 0 ? round((($sale_price - $cost_price) / $cost_price) * 100, 2) : 0;
                $stock_value = $nebf_scalar($product['stock_level'] ?? null);
                $stock_sort_value = is_numeric($stock_value) ? (string) (float) $stock_value : strtolower(trim($stock_value));

                $is_imported = !empty($product['synced'] ?? false);
            ?>
                <tr class="product-row" data-accordion="<?= esc_attr($accordion_id); ?>">
                    <td>
                        <input type="checkbox" name="selected_products[]" value="<?= esc_attr($product['bf_id']); ?>">
                        <input type="hidden" name="sale_prices[<?= esc_attr($product['bf_id']); ?>]" value="<?= esc_attr($sale_price); ?>">
                    </td>
                    <td data-sort-key="status" data-sort-value="<?= esc_attr($is_imported ? '1' : '0'); ?>">
                        <?php if ($is_imported): ?>
                            <span class="nebf-status-icon nebf-status-icon--synced" role="img" aria-label="<?php esc_attr_e('Synced', 'nebf'); ?>">
                                <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1.12 14.29-3.54-3.54 1.41-1.41 2.13 2.12 4.95-4.95 1.41 1.42-6.36 6.36z"></path>
                                </svg>
                            </span>
                        <?php else: ?>
                            <span class="nebf-status-icon nebf-status-icon--not-synced" role="img" aria-label="<?php esc_attr_e('Not synced', 'nebf'); ?>">
                                <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 13.59L15.59 17 12 13.41 8.41 17 7 15.59 10.59 12 7 8.41 8.41 7 12 10.59 15.59 7 17 8.41 13.41 12 17 15.59z"></path>
                                </svg>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td><?= !empty($product['thumbnail_url']) ? '<img src="' . esc_url($product['thumbnail_url']) . '" width="50">' : '—'; ?></td>
                    <td data-sort-key="name" data-sort-value="<?= esc_attr(strtolower($nebf_scalar($product['fullname'] ?? null))); ?>"><?= esc_html($nebf_display($product['fullname'] ?? null)); ?></td>
                    <td data-sort-key="sku" data-sort-value="<?= esc_attr(strtolower($nebf_scalar($product['sku'] ?? null))); ?>"><?= esc_html($nebf_display($product['sku'] ?? null)); ?></td>
                    <td data-sort-key="brand" data-sort-value="<?= esc_attr(strtolower($nebf_scalar($product['brand'] ?? null))); ?>"><?= esc_html($nebf_display($product['brand'] ?? null)); ?></td>
                    <td data-sort-key="collection" data-sort-value="<?= esc_attr(strtolower($nebf_scalar($product['collection'] ?? null))); ?>"><?= esc_html($nebf_display($product['collection'] ?? null)); ?></td>
                    <td data-sort-key="cost" data-sort-value="<?= esc_attr((string) $cost_price); ?>"><?= function_exists('wc_price') ? wc_price($cost_price) : esc_html($cost_price); ?></td>
                    <td data-sort-key="sale" data-sort-value="<?= esc_attr((string) $sale_price); ?>"><strong><?= function_exists('wc_price') ? wc_price($sale_price) : esc_html($sale_price); ?></strong></td>
                    <td data-sort-key="margin" data-sort-value="<?= esc_attr((string) $calc_margin); ?>"><?= esc_html($calc_margin); ?>%</td>
                    <td data-sort-key="stock" data-sort-value="<?= esc_attr($stock_sort_value); ?>"><?= esc_html($nebf_display($product['stock_level'] ?? null)); ?></td>
                    <td class="nebf-expand"><span class="dashicons dashicons-arrow-down-alt2"></span></td>
                </tr>

                <!-- Accordion row -->
                <tr id="<?= esc_attr($accordion_id); ?>" class="accordion-row" style="display:none;">
                    <td colspan="12" style="background:#f6f7f7; padding:16px;">
                        <table class="widefat striped" style="margin:0;">
                            <tbody>
                                <tr>
                                    <th><?php _e('Description', 'nebf'); ?></th>
                                    <td><?= esc_html($nebf_display($product['description'] ?? null, '')); ?></td>
                                </tr>
                                <tr>
                                    <th><?php _e('Barcode', 'nebf'); ?></th>
                                    <td><?= esc_html($nebf_display($product['barcode'] ?? null, '')); ?></td>
                                </tr>
                                <tr>
                                    <th><?php _e('Size', 'nebf'); ?></th>
                                    <td><?= esc_html($nebf_display($product['size'] ?? null, '')); ?></td>
                                </tr>
                                <tr>
                                    <th><?php _e('Type', 'nebf'); ?></th>
                                    <td><?= esc_html($nebf_display($product['type'] ?? null, '')); ?></td>
                                </tr>
                                <tr>
                                    <th><?php _e('BF ID', 'nebf'); ?></th>
                                    <td><?= esc_html($nebf_display($product['bf_id'] ?? null, '')); ?></td>
                                </tr>
                                <tr>
                                    <th><?php _e('Extra info', 'nebf'); ?></th>
                                    <td>
                                        <?php
                                        $web_prices = $product['web_price_lookup']['matches'] ?? [];
                                        $web_error = trim((string) ($product['web_price_lookup']['error'] ?? ''));
                                        if (!empty($web_prices) && is_array($web_prices)):
                                        ?>
                                            <ul class="nebf-extra-web-prices">
                                                <?php foreach ($web_prices as $match): ?>
                                                    <li>
                                                        <strong><?= esc_html((string) ($match['price'] ?? '')); ?></strong>
                                                        <?php if (!empty($match['source'])): ?>
                                                            — <?= esc_html((string) $match['source']); ?>
                                                        <?php endif; ?>
                                                        <?php if (!empty($match['url'])): ?>
                                                            (<a href="<?= esc_url((string) $match['url']); ?>" target="_blank" rel="noopener noreferrer"><?php _e('Open', 'nebf'); ?></a>)
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php elseif ($web_error !== ''): ?>
                                            <?= esc_html($web_error); ?>
                                        <?php else: ?>
                                            <?= esc_html__('No external web prices found.', 'nebf-mvc'); ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="nebf-products-actions nebf-products-actions--bottom">
        <button type="submit" name="nebf_sync_selected" class="button button-primary">
            <?php _e('Sync to WooCommerce', 'nebf'); ?>
        </button>
    </div>
</form>

<!-- PAGINATION -->
<?php if ($total_pages > 1): ?>
    <div class="tablenav">
        <div class="tablenav-pages">
            <?= paginate_links([
                'base' => add_query_arg(array_merge($_GET, ['paged' => '%#%'])),
                'format' => '',
                'current' => $page,
                'total' => $total_pages,
                'prev_text' => '«',
                'next_text' => '»'
            ]) ?>
        </div>
    </div>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var form = document.getElementById('nebf-products-filter-form');
        var searchInput = document.getElementById('nebf-products-search');
        var selectAllButton = document.getElementById('nebf-select-all');
        var resetSearchButton = document.getElementById('nebf-reset-search');
        var table = document.querySelector('.nebf-products-table');
        var sortButtons = table ? table.querySelectorAll('.nebf-sort-trigger') : [];
        var defaultSelectAllText = <?php echo wp_json_encode(__('Select all', 'nebf')); ?>;
        var defaultUnselectAllText = <?php echo wp_json_encode(__('Unselect all', 'nebf')); ?>;

        if (!form || !searchInput) {
            return;
        }

        var debounceTimer = null;

        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() {
                form.submit();
            }, 350);
        });

        if (selectAllButton && table) {
            selectAllButton.addEventListener('click', function() {
                var checkboxes = table.querySelectorAll('input[type="checkbox"][name="selected_products[]"]');
                if (!checkboxes.length) {
                    return;
                }

                var allChecked = true;
                checkboxes.forEach(function(checkbox) {
                    if (!checkbox.checked) {
                        allChecked = false;
                    }
                });

                var shouldCheck = !allChecked;
                checkboxes.forEach(function(checkbox) {
                    checkbox.checked = shouldCheck;
                });

                selectAllButton.textContent = shouldCheck ? defaultUnselectAllText : defaultSelectAllText;
            });
        }

        if (resetSearchButton) {
            resetSearchButton.addEventListener('click', function() {
                form.querySelectorAll('input[name="s"], select[name="brand"], select[name="collection"], select[name="status"]').forEach(function(field) {
                    field.value = '';
                });
                form.submit();
            });
        }

        if (table) {
            var productRows = table.querySelectorAll('tr.product-row');
            var accordionRows = table.querySelectorAll('tr.accordion-row');
            var currentSort = {
                key: null,
                direction: 'asc'
            };

            if (sortButtons.length) {
                var tableBody = table.querySelector('tbody');

                var getSortValue = function(row, key) {
                    var cell = row.querySelector('[data-sort-key="' + key + '"]');
                    return cell ? (cell.getAttribute('data-sort-value') || '').trim() : '';
                };

                var sortTableRows = function(key, type, direction) {
                    if (!tableBody) {
                        return;
                    }

                    var rows = Array.from(tableBody.querySelectorAll('tr.product-row')).map(function(row, index) {
                        var accordion = row.nextElementSibling && row.nextElementSibling.classList.contains('accordion-row') ? row.nextElementSibling : null;
                        return {
                            row: row,
                            accordion: accordion,
                            index: index,
                            value: getSortValue(row, key)
                        };
                    });

                    rows.sort(function(a, b) {
                        var result = 0;

                        if (type === 'number') {
                            var aValue = parseFloat(a.value);
                            var bValue = parseFloat(b.value);
                            aValue = Number.isNaN(aValue) ? Number.NEGATIVE_INFINITY : aValue;
                            bValue = Number.isNaN(bValue) ? Number.NEGATIVE_INFINITY : bValue;
                            result = aValue - bValue;
                        } else {
                            result = a.value.localeCompare(b.value, undefined, {
                                numeric: true,
                                sensitivity: 'base'
                            });
                        }

                        if (result === 0) {
                            result = a.index - b.index;
                        }

                        return direction === 'asc' ? result : -result;
                    });

                    rows.forEach(function(entry) {
                        tableBody.appendChild(entry.row);
                        if (entry.accordion) {
                            tableBody.appendChild(entry.accordion);
                        }
                    });
                };

                sortButtons.forEach(function(button) {
                    button.addEventListener('click', function() {
                        var sortKey = button.getAttribute('data-sort-key');
                        var sortType = button.getAttribute('data-sort-type') || 'text';
                        var nextDirection = 'asc';

                        if (currentSort.key === sortKey && currentSort.direction === 'asc') {
                            nextDirection = 'desc';
                        }

                        currentSort = {
                            key: sortKey,
                            direction: nextDirection
                        };

                        sortButtons.forEach(function(otherButton) {
                            var columnHeader = otherButton.closest('th');
                            var indicator = otherButton.querySelector('.nebf-sort-indicator');
                            var isActive = otherButton === button;

                            if (columnHeader) {
                                columnHeader.setAttribute('aria-sort', isActive ? nextDirection : 'none');
                            }

                            if (indicator) {
                                indicator.textContent = !isActive ? '↕' : (nextDirection === 'asc' ? '↑' : '↓');
                            }
                        });

                        sortTableRows(sortKey, sortType, nextDirection);
                    });
                });
            }

            productRows.forEach(function(row) {
                row.addEventListener('click', function(event) {
                    var clickedInteractive = event.target.closest('input, button, a, select, textarea, label');
                    if (clickedInteractive && !event.target.closest('.nebf-expand')) {
                        return;
                    }

                    var accordionId = row.getAttribute('data-accordion');
                    if (!accordionId) {
                        return;
                    }

                    var accordion = document.getElementById(accordionId);
                    if (!accordion) {
                        return;
                    }

                    var isOpen = accordion.style.display !== 'none';

                    accordionRows.forEach(function(otherAccordion) {
                        otherAccordion.style.display = 'none';
                    });
                    productRows.forEach(function(otherRow) {
                        otherRow.classList.remove('is-open');
                    });

                    if (!isOpen) {
                        accordion.style.display = '';
                        row.classList.add('is-open');
                    }
                });
            });
        }
    });
</script>