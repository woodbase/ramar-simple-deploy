<?php
/**
 * Shared page header + tabs partial
 * Expects $active_tab to be set ('dashboard', 'products', 'settings', etc.)
 */
?>
<div class="wrap">
    <h1><?php esc_html_e('Nordic Equilibro - BeautyFort integration', 'nebf-mvc'); ?></h1>

    <h2 class="nav-tab-wrapper">
        <?php
        $tabs = [
            'dashboard' => __('Dashboard', 'nebf-mvc'),
            'products'  => __('Products', 'nebf-mvc'),
            'settings'  => __('Settings', 'nebf-mvc'),
        ];

        foreach ($tabs as $tab => $label):
            $class = ($active_tab === $tab) ? 'nav-tab nav-tab-active' : 'nav-tab';
            $url = add_query_arg(['page' => 'nebf-mvc', 'tab' => $tab], admin_url('admin.php'));
        ?>
            <a class="<?php echo esc_attr($class); ?>" href="<?php echo esc_url($url); ?>">
                <?php echo esc_html($label); ?>
            </a>
        <?php endforeach; ?>
    </h2>
</div>
