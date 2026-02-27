<?php

namespace NEBF\Services;

if (!defined('ABSPATH')) exit;

/**
 * Looks up publicly visible web prices for BeautyFort products.
 */
class WebPriceLookupService
{
    private const CACHE_TTL = 12 * HOUR_IN_SECONDS;

    /**
     * @param array $product
     * @return array{query:string,matches:array<int,array{price:string,url:string,source:string,title:string}>,fetched_at:string,error:string}
     */
    public function lookup_for_product(array $product): array
    {
        $cache_key = $this->build_cache_key($product);
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $query = $this->build_query($product);
        if ($query === '') {
            return $this->default_result(__('No searchable product data found.', 'nebf-mvc'));
        }

        $result = $this->fetch_prices_from_duckduckgo($query);
        set_transient($cache_key, $result, self::CACHE_TTL);

        return $result;
    }

    private function fetch_prices_from_duckduckgo(string $query): array
    {
        $url = add_query_arg([
            'q' => $query . ' pris',
            'kl' => 'se-sv',
        ], 'https://duckduckgo.com/html/');

        $response = wp_remote_get($url, [
            'timeout' => 5,
            'redirection' => 3,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (compatible; NEBF price lookup)',
            ],
        ]);

        if (is_wp_error($response)) {
            return $this->default_result($response->get_error_message(), $query);
        }

        $html = (string) wp_remote_retrieve_body($response);
        if ($html === '') {
            return $this->default_result(__('No response body from search provider.', 'nebf-mvc'), $query);
        }

        $matches = [];
        if (preg_match_all('/<a[^>]*class="result__a"[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/si', $html, $anchors, PREG_SET_ORDER)) {
            foreach ($anchors as $anchor) {
                $rawUrl = html_entity_decode(wp_strip_all_tags((string) ($anchor[1] ?? '')));
                $title = trim(wp_strip_all_tags((string) ($anchor[2] ?? '')));

                $price = $this->extract_price_candidate($title);
                if ($price === '') {
                    continue;
                }

                $matches[] = [
                    'price' => $price,
                    'url' => esc_url_raw($rawUrl),
                    'source' => $this->extract_domain($rawUrl),
                    'title' => $title,
                ];

                if (count($matches) >= 3) {
                    break;
                }
            }
        }

        return [
            'query' => $query,
            'matches' => $matches,
            'fetched_at' => gmdate('c'),
            'error' => '',
        ];
    }

    private function extract_price_candidate(string $text): string
    {
        $text = html_entity_decode($text);

        if (preg_match('/\b\d{1,5}(?:[\.,]\d{2})?\s?(?:kr|sek)\b/i', $text, $match)) {
            return trim($match[0]);
        }

        if (preg_match('/\b(?:kr|sek)\s?\d{1,5}(?:[\.,]\d{2})?\b/i', $text, $match)) {
            return trim($match[0]);
        }

        return '';
    }

    private function extract_domain(string $url): string
    {
        $host = (string) wp_parse_url($url, PHP_URL_HOST);
        if ($host === '') {
            return '';
        }

        return preg_replace('/^www\./', '', strtolower($host));
    }

    private function build_query(array $product): string
    {
        $parts = [
            sanitize_text_field((string) ($product['brand'] ?? '')),
            sanitize_text_field((string) ($product['fullname'] ?? '')),
            sanitize_text_field((string) ($product['barcode'] ?? '')),
            sanitize_text_field((string) ($product['sku'] ?? '')),
        ];

        $parts = array_filter(array_unique(array_map('trim', $parts)));
        return trim(implode(' ', $parts));
    }

    private function build_cache_key(array $product): string
    {
        $identifier = (string) ($product['bf_id'] ?? $product['sku'] ?? wp_json_encode($product));
        return 'nebf_web_price_' . md5($identifier);
    }

    private function default_result(string $error, string $query = ''): array
    {
        return [
            'query' => $query,
            'matches' => [],
            'fetched_at' => gmdate('c'),
            'error' => $error,
        ];
    }
}
