<?php

namespace NEBF\Controllers;

use NEBF\Models\ProductRepository;

if (!defined('ABSPATH')) exit;

class ProductsController extends AbstractAdminController
{

    protected ProductRepository $repo;

    public function __construct()
    {
        parent::__construct();
        $this->repo = new ProductRepository();
    }

    public function handle(): void
    {
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            isset($_POST['nebf_sync_selected']) &&
            check_admin_referer('nebf_sync_selected_products')
        ) {
            $bf_ids = array_values(array_filter(array_map(
                'sanitize_text_field',
                (array) ($_POST['selected_products'] ?? [])
            )));

            $sale_prices = [];
            if (isset($_POST['sale_prices']) && is_array($_POST['sale_prices'])) {
                foreach ($_POST['sale_prices'] as $bf_id => $sale_price) {
                    $sale_prices[sanitize_text_field((string) $bf_id)] = (float) $sale_price;
                }
            }

            if (empty($bf_ids)) {
                $this->notices->add(__('Select at least one product to sync.', 'nebf-mvc'), 'warning');
            } else {
                $result = $this->repo->sync_products($bf_ids, $sale_prices);

                if (($result['synced'] ?? 0) > 0) {
                    update_option('nebf_last_sync', time());

                    $this->notices->add(
                        sprintf(
                            _n(
                                '%d product synced to WooCommerce.',
                                '%d products synced to WooCommerce.',
                                (int) $result['synced'],
                                'nebf-mvc'
                            ),
                            (int) $result['synced']
                        ),
                        'success'
                    );
                }

                if (($result['failed'] ?? 0) > 0) {
                    $this->notices->add(
                        sprintf(
                            _n(
                                '%d product could not be synced.',
                                '%d products could not be synced.',
                                (int) $result['failed'],
                                'nebf-mvc'
                            ),
                            (int) $result['failed']
                        ),
                        'error'
                    );
                }
            }
        }

        $page       = (int) ($_GET['paged'] ?? 1);
        $per_page   = max(1, min(500, (int) ($_GET['per_page'] ?? 20)));
        $search     = $_GET['s'] ?? '';
        $filters    = [
            'brand'      => $_GET['brand'] ?? '',
            'collection' => $_GET['collection'] ?? '',
            'status'     => $_GET['status'] ?? '',
        ];

        $products = $this->repo->get_paginated($page, $per_page, $search, $filters);

        $this->render('products', [
            'products'    => $products,
            'page'        => $products['page'] ?? $page,
            'total_pages' => $products['total_pages'] ?? 1,
            'search_term' => $search,
            'filters'     => $filters,
        ]);
    }
}
