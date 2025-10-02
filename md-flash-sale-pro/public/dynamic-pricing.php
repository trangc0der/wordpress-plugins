<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('MDFS_RT_DynamicPricing')) {

    class MDFS_RT_DynamicPricing {
        private $map = [];

        public function __construct() {
            add_action('init', [$this, 'collect'], 20);

            // Override giá sản phẩm
            add_filter('woocommerce_product_get_regular_price', [$this, 'get_regular_price'], 9999, 2);
            add_filter('woocommerce_product_get_sale_price', [$this, 'get_sale_price'], 9999, 2);
            add_filter('woocommerce_product_get_price', [$this, 'get_price'], 9999, 2);
            add_filter('woocommerce_product_is_on_sale', [$this, 'is_on_sale'], 9999, 2);

            // Hiển thị giá
            add_filter('woocommerce_get_price_html', [$this, 'force_price_html'], 9999, 2);
            add_filter('woocommerce_available_variation', [$this, 'variation_json'], 9999, 3);

            // Giỏ hàng
            add_action('woocommerce_before_calculate_totals', [$this, 'apply_cart_price'], 20);
            add_filter('woocommerce_cart_item_price', [$this, 'cart_item_price_html'], 9999, 3);
        }

        /**
         * Thu thập danh sách sản phẩm flash-sale đang hoạt động
         */
        public function collect() {
            $now = current_time('timestamp');

            $q = new WP_Query([
                'post_type'      => 'mdfs_slot',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'meta_query'     => [
                    [
                        'key'     => '_mdfs_time_from',
                        'value'   => $now,
                        'compare' => '<=',
                        'type'    => 'NUMERIC',
                    ],
                    [
                        'key'     => '_mdfs_time_to',
                        'value'   => $now,
                        'compare' => '>=',
                        'type'    => 'NUMERIC',
                    ],
                ],
            ]);

            $map = [];

            foreach ($q->posts as $slot) {
                $rows = get_post_meta($slot->ID, '_mdfs_products', true);
                if (!is_array($rows)) continue;

                $from     = (int) get_post_meta($slot->ID, '_mdfs_time_from', true);
                $to       = (int) get_post_meta($slot->ID, '_mdfs_time_to', true);
                $sold_map = $this->build_sold_map($from, $to);

                foreach ($rows as $r) {
                    $pid   = intval($r['product_id'] ?? 0);
                    $vid   = intval($r['variation_id'] ?? 0);
                    $sale  = $r['sale_price'] ?? '';
                    $quota = (int)($r['quota'] ?? 0);

                    if (!$pid || $sale === '') continue;

                    $check_id = $vid ?: $pid;
                    $sold     = $sold_map[$check_id] ?? 0;
                    $remain   = max(0, $quota - $sold);

                    if ($quota > 0 && $remain <= 0) continue; // hết hàng

                    if ($vid) {
                        $map[$vid] = floatval($sale);
                    } else {
                        $map[$pid] = floatval($sale);
                    }
                }
            }

            $this->map = $map;
        }

        private function key_for($product) {
            return $product->get_id();
        }

        /**
         * Giá gốc
         */
        public function get_regular_price($price, $product) {
            $id = $this->key_for($product);

            if (isset($this->map[$id])) {
                $orig = $product->get_regular_price();
                if ($orig !== '') return $orig;
            }
            return $price;
        }

        /**
         * Giá khuyến mãi
         */
        public function get_sale_price($price, $product) {
            $id = $this->key_for($product);
            return isset($this->map[$id]) ? $this->map[$id] : $price;
        }

        /**
         * Giá cuối cùng
         */
        public function get_price($price, $product) {
            $id = $this->key_for($product);
            return isset($this->map[$id]) ? $this->map[$id] : $price;
        }

        /**
         * Đánh dấu sản phẩm đang giảm giá
         */
        public function is_on_sale($on_sale, $product) {
            $id = $this->key_for($product);
            return isset($this->map[$id]) ? true : $on_sale;
        }

        /**
         * Bắt buộc hiển thị giá trong chi tiết sản phẩm
         */
        public function force_price_html($price_html, $product) {
            $id = $this->key_for($product);

            if (isset($this->map[$id])) {
                $sale = $this->map[$id];
                $reg  = $product->get_regular_price();

                if ($reg && $sale < $reg) {
                    $price_html  = '<del>' . wc_price($reg) . '</del> ';
                    $price_html .= '<ins>' . wc_price($sale) . '</ins>';
                } else {
                    $price_html = '<ins>' . wc_price($sale) . '</ins>';
                }
            }

            return $price_html;
        }

        /**
         * Render giá flash-sale trong JSON biến thể
         */
        public function variation_json($data, $product, $variation) {
            $id = $variation->get_id();

            if (isset($this->map[$id])) {
                $sale = $this->map[$id];
                $reg  = $variation->get_regular_price();

                if ($reg && $sale < $reg) {
                    $data['price_html']  = '<del>' . wc_price($reg) . '</del> ';
                    $data['price_html'] .= '<ins>' . wc_price($sale) . '</ins>';
                } else {
                    $data['price_html'] = '<ins>' . wc_price($sale) . '</ins>';
                }

                $data['display_price']         = wc_get_price_to_display($variation, ['price' => $sale]);
                $data['display_regular_price'] = $reg ? wc_get_price_to_display($variation, ['price' => $reg]) : $sale;
            }

            return $data;
        }

        /**
         * Áp dụng giá vào giỏ hàng
         */
        public function apply_cart_price($cart) {
            if (is_admin() && !defined('DOING_AJAX')) return;
            if (empty($this->map)) return;

            foreach ($cart->get_cart() as $cart_item) {
                $product = $cart_item['data'];
                $id      = $this->key_for($product);

                if (isset($this->map[$id])) {
                    $cart_item['data']->set_price($this->map[$id]);
                }
            }
        }

        /**
         * Hiển thị giá trong mini-cart
         */
        public function cart_item_price_html($price_html, $cart_item, $cart_item_key) {
            $product = $cart_item['data'];
            $id      = $this->key_for($product);

            if (isset($this->map[$id])) {
                $sale = $this->map[$id];
                $reg  = $product->get_regular_price();

                if ($reg && $sale < $reg) {
                    $price_html  = '<del>' . wc_price($reg) . '</del> ';
                    $price_html .= '<ins>' . wc_price($sale) . '</ins>';
                } else {
                    $price_html = '<ins>' . wc_price($sale) . '</ins>';
                }
            }

            return $price_html;
        }

        /**
         * Tính số sold từ order (processing/completed)
         */
        private function build_sold_map($from, $to) {
            global $wpdb;

            $orders = $wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE post_type='shop_order'
                   AND post_status IN ('wc-processing','wc-completed')
                   AND post_date >= %s
                   AND post_date <= %s",
                gmdate('Y-m-d H:i:s', $from),
                gmdate('Y-m-d H:i:s', $to)
            ));

            $map = [];
            foreach ($orders as $order_id) {
                $order = wc_get_order($order_id);
                if (!$order) continue;

                foreach ($order->get_items() as $item) {
                    $pid = $item->get_product_id();
                    $vid = $item->get_variation_id();
                    $check_id = $vid ?: $pid;

                    if (!isset($map[$check_id])) $map[$check_id] = 0;
                    $map[$check_id] += (int)$item->get_quantity();
                }
            }

            return $map;
        }

        /**
         * Hàm quota giống API
         */
        public function get_quota_status($product_id) {
            $now = current_time('timestamp');

            $q = new WP_Query([
                'post_type'      => 'mdfs_slot',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'orderby'        => 'meta_value_num',
                'order'          => 'ASC',
                'meta_key'       => '_mdfs_time_to',
                'meta_query'     => [
                    [
                        'key'     => '_mdfs_time_from',
                        'value'   => $now,
                        'compare' => '<=',
                        'type'    => 'NUMERIC',
                    ],
                    [
                        'key'     => '_mdfs_time_to',
                        'value'   => $now,
                        'compare' => '>=',
                        'type'    => 'NUMERIC',
                    ],
                ],
            ]);

            if (!$q->have_posts()) return ['status' => 'no_active_slot'];

            $slot_id = $q->posts[0]->ID;
            $from    = (int) get_post_meta($slot_id, '_mdfs_time_from', true);
            $to      = (int) get_post_meta($slot_id, '_mdfs_time_to', true);
            $rows    = get_post_meta($slot_id, '_mdfs_products', true);
            $rows    = is_array($rows) ? $rows : [];

            $sold_map = $this->build_sold_map($from, $to);

            $pobj = wc_get_product($product_id);
            $parent_id = $pobj && $pobj->is_type('variation') ? $pobj->get_parent_id() : 0;

            foreach ($rows as $row) {
                $pid = (int)($row['product_id'] ?? 0);
                $vid = (int)($row['variation_id'] ?? 0);
                $check_id = $vid ?: $pid;

                if (
                    (int)$product_id === (int)$check_id ||   // match ID
                    (int)$product_id === (int)$pid ||        // match product cha
                    ($parent_id && $parent_id === (int)$pid) // variation add nhưng slot lưu cha
                ) {
                    $total  = (int)($row['quota'] ?? 0);
                    $sold   = $sold_map[$check_id] ?? 0;
                    $remain = max(0, $total - $sold);

                    return [
                        'status'     => 'ok',
                        'product_id' => $product_id,
                        'slot_id'    => $slot_id,
                        'quota'      => [
                            'total'  => $total,
                            'sold'   => $sold,
                            'remain' => $remain,
                        ],
                    ];
                }
            }

            return ['status' => 'not_in_slot'];
        }
    }
}

new MDFS_RT_DynamicPricing();
