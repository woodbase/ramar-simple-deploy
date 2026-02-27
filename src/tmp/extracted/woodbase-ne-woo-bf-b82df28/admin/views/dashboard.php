<?php
$this->notices->display();

$last_sync_raw = $last_sync ?? '';
$last_sync_timestamp = 0;
$last_sync_display = __('Never', 'nebf-mvc');

if ($last_sync_raw !== '' && $last_sync_raw !== null) {
    // Preferred format: Unix timestamp stored in option.
    if (is_numeric($last_sync_raw)) {
        $last_sync_timestamp = (int) $last_sync_raw;
    } else {
        // Backward compatibility for older stored "Y-m-d H:i:s" values.
        $last_sync_str = trim((string) $last_sync_raw);
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $last_sync_str, wp_timezone());
        if ($dt instanceof \DateTimeImmutable) {
            $last_sync_timestamp = $dt->getTimestamp();
        } else {
            $fallback = strtotime($last_sync_str);
            $last_sync_timestamp = $fallback ? (int) $fallback : 0;
        }
    }

    if ($last_sync_timestamp) {
        // Server-side fallback; client-side script will reformat in browser-local time.
        $last_sync_display = wp_date('d-m-Y H:i', $last_sync_timestamp, wp_timezone());
    } else {
        $last_sync_display = (string) $last_sync_raw;
    }
}

$web_lookup_status = is_array($web_lookup_status ?? null) ? $web_lookup_status : [];
$default_web_lookup_enabled = !empty($default_web_lookup_enabled);
?>

<div class="nebf-dashboard-cards">

    <div class="nebf-card">
        <h2><?php esc_html_e('Total Products', 'nebf-mvc'); ?></h2>
        <p class="nebf-number">
            <span class="nebf-value-text"><?php echo esc_html($total_products ?? 0); ?></span>
            <span class="nebf-value-spinner dashicons dashicons-update" aria-hidden="true"></span>
        </p>
    </div>

    <div class="nebf-card">
        <h2><?php esc_html_e('Synced to WooCommerce', 'nebf-mvc'); ?></h2>
        <p class="nebf-number">
            <span class="nebf-value-text"><?php echo esc_html($synced_products ?? 0); ?></span>
            <span class="nebf-value-spinner dashicons dashicons-update" aria-hidden="true"></span>
        </p>
    </div>

    <div class="nebf-card">
        <h2><?php esc_html_e('Not Synced', 'nebf-mvc'); ?></h2>
        <p class="nebf-number">
            <span class="nebf-value-text"><?php echo esc_html($unsynced_products ?? 0); ?></span>
            <span class="nebf-value-spinner dashicons dashicons-update" aria-hidden="true"></span>
        </p>
    </div>

    <div class="nebf-card">
        <h2><?php esc_html_e('Last Sync', 'nebf-mvc'); ?></h2>
        <p class="nebf-number nebf-number--date">
            <span
                class="nebf-value-text"
                id="nebf-last-sync-text"
                data-timestamp="<?php echo esc_attr((string) $last_sync_timestamp); ?>"><?php echo esc_html($last_sync_display); ?></span>
            <span class="nebf-value-spinner dashicons dashicons-update" aria-hidden="true"></span>
        </p>
    </div>

</div>

<div class="nebf-dashboard-actions">
    <form id="nebf-dashboard-sync-form" class="nebf-dashboard-sync-form" method="post">
        <?php wp_nonce_field('nebf_sync_products'); ?>
        <input type="hidden" name="nebf_sync_all" value="1">
        <div class="nebf-sync-options">
            <label class="nebf-sync-options__label">
                <input
                    id="nebf_sync_web_price_lookup"
                    type="checkbox"
                    name="nebf_sync_web_price_lookup"
                    value="1"
                    <?php checked($default_web_lookup_enabled); ?>>
                <span><?php esc_html_e('Run web price lookup while loading products', 'nebf-mvc'); ?></span>
            </label>
            <p class="description nebf-sync-options__hint">
                <?php esc_html_e('Tip: Price lookup now runs in background batches to reduce server load.', 'nebf-mvc'); ?>
            </p>
            <div id="nebf_sync_web_price_warning" class="notice notice-warning inline nebf-sync-options__warning" hidden>
                <p><?php esc_html_e('Price lookup is enabled. Results will be fetched in background after products are loaded.', 'nebf-mvc'); ?></p>
            </div>
            <?php if (!empty($web_lookup_status['in_progress'])): ?>
                <div class="notice notice-info inline nebf-sync-options__status">
                    <p>
                        <?php
                        echo esc_html(sprintf(
                            __('Web price lookup is running in background: %1$d/%2$d processed (%3$d pending).', 'nebf-mvc'),
                            (int) ($web_lookup_status['processed'] ?? 0),
                            (int) ($web_lookup_status['total'] ?? 0),
                            (int) ($web_lookup_status['pending'] ?? 0)
                        ));
                        ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        <div class="nebf-dashboard-actions__controls">
            <button
                type="button"
                id="nebf-sync-load-btn"
                class="button button-primary"
                data-label-loading="<?php echo esc_attr__('Loading products...', 'nebf-mvc'); ?>">
                <span class="nebf-btn-label"><?php esc_html_e('Load Products from BeautyFort', 'nebf-mvc'); ?></span>
                <span class="nebf-btn-spinner dashicons dashicons-update" aria-hidden="true"></span>
            </button>

            <a href="<?php echo esc_url(add_query_arg([
                            'page' => 'nebf-mvc',
                            'tab'  => 'products'
                        ], admin_url('admin.php'))); ?>"
                class="button">
                <?php esc_html_e('Manage Products', 'nebf-mvc'); ?>
            </a>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var syncForm = document.getElementById('nebf-dashboard-sync-form');
        var syncButton = document.getElementById('nebf-sync-load-btn');
        var numbers = document.querySelectorAll('.nebf-number');
        var lastSyncText = document.getElementById('nebf-last-sync-text');
        var webLookupCheckbox = document.getElementById('nebf_sync_web_price_lookup');
        var webLookupWarning = document.getElementById('nebf_sync_web_price_warning');

        function formatLocalDateTime(unixSeconds) {
            var d = new Date(unixSeconds * 1000);
            if (isNaN(d.getTime())) {
                return null;
            }

            var dd = String(d.getDate()).padStart(2, '0');
            var mm = String(d.getMonth() + 1).padStart(2, '0');
            var yyyy = d.getFullYear();
            var hh = String(d.getHours()).padStart(2, '0');
            var min = String(d.getMinutes()).padStart(2, '0');

            return dd + '-' + mm + '-' + yyyy + ' ' + hh + ':' + min;
        }

        if (!syncForm || !syncButton) {
            return;
        }

        if (webLookupCheckbox && webLookupWarning) {
            var toggleLookupWarning = function() {
                webLookupWarning.hidden = !webLookupCheckbox.checked;
            };

            webLookupCheckbox.addEventListener('change', toggleLookupWarning);
            toggleLookupWarning();
        }

        if (lastSyncText) {
            var ts = parseInt(lastSyncText.getAttribute('data-timestamp') || '0', 10);
            if (ts > 0) {
                var localText = formatLocalDateTime(ts);
                if (localText) {
                    lastSyncText.textContent = localText;
                }
            }
        }

        syncButton.addEventListener('click', function() {
            var label = syncButton.querySelector('.nebf-btn-label');
            var loadingLabel = syncButton.getAttribute('data-label-loading');

            syncButton.classList.add('is-loading');
            syncButton.setAttribute('aria-busy', 'true');
            syncButton.setAttribute('aria-disabled', 'true');

            if (label && loadingLabel) {
                label.textContent = loadingLabel;
            }

            numbers.forEach(function(node) {
                node.classList.remove('is-updated');
                node.classList.add('is-loading');
            });

            // Let the browser paint loading state before navigation.
            setTimeout(function() {
                syncForm.submit();
            }, 120);
        });

        numbers.forEach(function(node) {
            node.classList.add('is-updated');
            setTimeout(function() {
                node.classList.remove('is-updated');
            }, 700);
        });
    });
</script>