<?php

namespace NEBF\Services;

if (!defined('ABSPATH')) exit;

/**
 * Runs web price lookup in small WP-Cron batches to avoid long admin requests.
 */
class WebPriceLookupQueueService
{
    public const HOOK = 'nebf_process_web_price_lookup_batch';

    private const OPTION_QUEUE = 'nebf_web_price_lookup_queue';
    private const OPTION_STATUS = 'nebf_web_price_lookup_status';
    private const LOCK_KEY = 'nebf_web_price_lookup_lock';
    private const LOCK_TTL = 55;

    /**
     * Add products to lookup queue and ensure a cron batch is scheduled.
     *
     * @param array<int,array<string,mixed>> $products
     * @return array{enqueued:int,pending:int,total:int}
     */
    public function enqueue_products(array $products): array
    {
        $queue = $this->get_queue();
        $existing = array_fill_keys($queue, true);
        $enqueued = 0;

        foreach ($products as $product) {
            if (!is_array($product)) {
                continue;
            }

            $bf_id = sanitize_text_field((string) ($product['bf_id'] ?? ''));
            if ($bf_id === '' || isset($existing[$bf_id])) {
                continue;
            }

            $queue[] = $bf_id;
            $existing[$bf_id] = true;
            $enqueued++;
        }

        $status = $this->get_status();
        if (!$status['in_progress']) {
            $status['processed'] = 0;
            $status['total'] = 0;
            $status['started_at'] = gmdate('c');
            $status['finished_at'] = '';
        }

        $status['total'] += $enqueued;
        $status['pending'] = count($queue);
        $status['in_progress'] = $status['pending'] > 0;
        $status['updated_at'] = gmdate('c');

        $this->save_queue($queue);
        $this->save_status($status);

        if ($status['pending'] > 0) {
            $this->schedule_next_batch(2);
        }

        return [
            'enqueued' => $enqueued,
            'pending' => $status['pending'],
            'total' => $status['total'],
        ];
    }

    public function process_batch(): void
    {
        if (get_transient(self::LOCK_KEY)) {
            return;
        }

        set_transient(self::LOCK_KEY, '1', self::LOCK_TTL);

        $queue = $this->get_queue();
        $status = $this->get_status();

        if (empty($queue)) {
            $status['pending'] = 0;
            $status['in_progress'] = false;
            $status['finished_at'] = gmdate('c');
            $status['updated_at'] = gmdate('c');
            $this->save_status($status);
            delete_transient(self::LOCK_KEY);
            return;
        }

        $batch_size = (int) apply_filters('nebf_web_price_lookup_batch_size', 4);
        $batch_size = max(1, min(20, $batch_size));

        $all_products = get_option('nebf_beautyfort_products', []);
        if (!is_array($all_products)) {
            $all_products = [];
        }

        $lookup_service = new WebPriceLookupService();
        $processed_now = 0;
        $updated_any = false;

        while ($processed_now < $batch_size && !empty($queue)) {
            $bf_id = (string) array_shift($queue);
            $processed_now++;

            if (!isset($all_products[$bf_id]) || !is_array($all_products[$bf_id])) {
                continue;
            }

            $all_products[$bf_id]['web_price_lookup'] = $lookup_service->lookup_for_product($all_products[$bf_id]);
            $updated_any = true;
        }

        if ($updated_any) {
            update_option('nebf_beautyfort_products', $all_products);
        }

        $status['processed'] += $processed_now;
        $status['pending'] = count($queue);
        $status['in_progress'] = $status['pending'] > 0;
        $status['updated_at'] = gmdate('c');
        if (!$status['in_progress']) {
            $status['finished_at'] = gmdate('c');
        }

        $this->save_queue($queue);
        $this->save_status($status);

        delete_transient(self::LOCK_KEY);

        if ($status['in_progress']) {
            $this->schedule_next_batch(8);
        }
    }

    /**
     * @return array{in_progress:bool,total:int,processed:int,pending:int,started_at:string,updated_at:string,finished_at:string}
     */
    public function get_status(): array
    {
        $raw = get_option(self::OPTION_STATUS, []);
        if (!is_array($raw)) {
            $raw = [];
        }

        return [
            'in_progress' => !empty($raw['in_progress']),
            'total' => max(0, (int) ($raw['total'] ?? 0)),
            'processed' => max(0, (int) ($raw['processed'] ?? 0)),
            'pending' => max(0, (int) ($raw['pending'] ?? 0)),
            'started_at' => sanitize_text_field((string) ($raw['started_at'] ?? '')),
            'updated_at' => sanitize_text_field((string) ($raw['updated_at'] ?? '')),
            'finished_at' => sanitize_text_field((string) ($raw['finished_at'] ?? '')),
        ];
    }

    private function schedule_next_batch(int $delay_seconds): void
    {
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_single_event(time() + max(1, $delay_seconds), self::HOOK);
        }
    }

    /**
     * @return array<int,string>
     */
    private function get_queue(): array
    {
        $queue = get_option(self::OPTION_QUEUE, []);
        if (!is_array($queue)) {
            return [];
        }

        $queue = array_values(array_filter(array_map(
            static fn($id) => sanitize_text_field((string) $id),
            $queue
        )));

        return array_values(array_unique($queue));
    }

    /**
     * @param array<int,string> $queue
     */
    private function save_queue(array $queue): void
    {
        update_option(self::OPTION_QUEUE, array_values($queue), false);
    }

    /**
     * @param array<string,mixed> $status
     */
    private function save_status(array $status): void
    {
        update_option(self::OPTION_STATUS, $status, false);
    }
}
