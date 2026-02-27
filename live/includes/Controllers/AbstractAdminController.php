<?php

namespace NEBF\Controllers;

use NEBF\Services\NoticeService;

if (!defined('ABSPATH')) exit;

/**
 * Base controller for admin pages
 */
abstract class AbstractAdminController {

    /** @var NoticeService */
    protected $notices;

    public function __construct()
    {
        // Initialize notice service
        $this->notices = new NoticeService();
    }

    /**
     * Render a view
     *
     * @param string $view
     * @param array $data
     */
    protected function render(string $view, array $data = []): void
    {
        // Extract variables for the view
        extract($data);

        // Include view
        include NEBF_MVC_PATH . 'admin/views/' . $view . '.php';
    }
}
