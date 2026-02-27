<?php

namespace NEBF\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles admin notices.
 *
 * Uses session-based storage to persist notices
 * across redirects if needed.
 */
class NoticeService {

    /**
     * Add a notice.
     *
     * @param string $message
     * @param string $type success|error|warning|info
     */
    public function add(string $message, string $type = 'success'): void {
        $_SESSION['nebf_notices'][] = [
            'message' => $message,
            'type'    => $type,
        ];
    }

    /**
     * Display and clear notices.
     */
    public function display(): void {

        if (empty($_SESSION['nebf_notices'])) {
            return;
        }

        foreach ($_SESSION['nebf_notices'] as $notice) {

            printf(
                '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
                esc_attr($notice['type']),
                esc_html($notice['message'])
            );
        }

        unset($_SESSION['nebf_notices'  ]);
    }
}
