<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('MDFS_RT_Api')) {

    class MDFS_RT_Api {

        public function __construct() {
            /** AJAX cho admin */
            add_action('wp_ajax_mdfs_search_products', [$this, 'search_products']);
            add_action('wp_ajax_mdfs_get_product_price', [$this, 'get_product_price']);
            add_action('wp_ajax_mdfs_load_products_modal', [$this, 'load_products_modal']);

            /** REST API cho frontend */
            add_action('rest_api_init', [$this, 'register_rest_routes']);

            /** Validate quota khi add to cart */
            add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_quota_before_cart'], 10, 3);
        }

        /* ===========================
         * AJAX FUNCTIONS (ADMIN)
         * =========================== */

        public function search_products() {
            if (!current_user_can('edit_posts')) wp_send_json_error(['message' => 'Permission denied']);
            check_ajax_referer('mdfs_admin', 'nonce');

            $term = isset($_GET['q']) ? wc_clean(wp_unslash($_GET['q'])) : '';
            $results = [];

            if ($term) {
                global $wpdb;

                // Search by title
                $ids_title = $wpdb->get_col($wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts}
                     WHERE post_type IN ('product','product_variation')
                       AND post_status IN ('publish','private')
                       AND post_title LIKE %s
                     LIMIT 20",
                    '%' . $wpdb->esc_like($term) . '%'
                ));
                foreach ($ids_title as $pid) {
                    $results[] = [
                        'id'   => $pid,
                        'text' => get_the_title($pid) . ' (#' . $pid . ')',
                    ];
                }

                // Search by SKU
                $ids_sku = $wpdb->get_col($wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->postmeta}
                     WHERE meta_key='_sku'
                       AND meta_value LIKE %s
                     LIMIT 20",
                    '%' . $wpdb->esc_like($term) . '%'
                ));
                foreach ($ids_sku as $pid) {
                    $results[] = [
                        'id'   => $pid,
                        'text' => get_the_title($pid) . ' (#' . $pid . ')',
                    ];
                }
            }

            wp_send_json($results);
        }

        public function get_product_price() {
            if (!current_user_can('edit_posts')) wp_send_json_error(['message' => 'Permission denied']);
            check_ajax_referer('mdfs_admin', 'nonce');

            $product_id = absint($_GET['product_id'] ?? 0);
            if (!$product_id) wp_send_json_error(['message' => 'Missing product_id']);

            $product = wc_get_product($product_id);
            if (!$product) wp_send_json_error(['message' => 'Invalid product']);

            wp_send_json_success([
                'price' => $product->get_regular_price(),
                'sale'  => $product->get_sale_price(),
            ]);
        }

        public function load_products_modal() {
            if (!current_user_can('edit_posts')) wp_send_json_error(['message' => 'Permission denied']);
            check_ajax_referer('mdfs_admin', 'nonce');

            $ids = isset($_POST['ids']) ? array_map('intval', (array)$_POST['ids']) : [];
            $rows = [];

            foreach ($ids as $pid) {
                $product = wc_get_product($pid);
                if (!$product) continue;

                $rows[] = [
                    'id'    => $pid,
                    'title' => $product->get_name(),
                    'sku'   => $product->get_sku(),
                    'price' => $product->get_regular_price(),
                    'sale'  => $product->get_sale_price(),
                    'type'  => $product->get_type(),
                ];
            }

            wp_send_json_success($rows);
        }

        /* ===========================
         * REST API
         * =========================== */

        public function register_rest_routes() {
            register_rest_route('mdfs/v1', '/active-slots', [
                'methods'  => 'GET',
                'callback' => [$this, 'rest_active_slots'],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route('mdfs/v1', '/quota', [
                'methods'  => 'GET',
                'callback' => [$this, 'rest_quota_check'],
                'args'     => [
                    'product_id' => [
                        'required' => true,
                        'type'     => 'integer',
                    ],
                ],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route('mdfs/v1', '/slot-products', [
                'methods'  => 'GET',
                'callback' => [$this, 'rest_slot_products'],
                'args'     => [
                    'slot_id' => [
                        'required' => true,
                        'type'     => 'integer',
                    ],
                ],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route('mdfs/v1', '/slot-detail', [
                'methods'  => 'GET',
                'callback' => [$this, 'rest_slot_detail'],
                'args'     => [
                    'slot_id' => [
                        'required' => true,
                        'type'     => 'integer',
                    ],
                ],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route('mdfs/v1', '/active-slot-detail', [
                'methods'  => 'GET',
                'callback' => [$this, 'rest_active_slot_detail'],
                'permission_callback' => '__return_true',
            ]);
        }

        public function rest_active_slots() {
            $now = current_time('timestamp', true);

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

            $slots = [];
            foreach ($q->posts as $slot) {
                $slots[] = [
                    'id'    => $slot->ID,
                    'title' => get_the_title($slot->ID),
                    'from'  => (int) get_post_meta($slot->ID, '_mdfs_time_from', true),
                    'to'    => (int) get_post_meta($slot->ID, '_mdfs_time_to', true),
                ];
            }

            return rest_ensure_response($slots);
        }

        public function rest_quota_check(WP_REST_Request $request) {
            $product_id = absint($request->get_param('product_id'));
            return $this->get_quota_status($product_id);
        }

        public function rest_slot_products(WP_REST_Request $request) {
            $slot_id = absint($request->get_param('slot_id'));
            if (!$slot_id) return new WP_Error('invalid_slot', 'Missing slot_id', ['status' => 400]);

            return $this->build_slot_response($slot_id);
        }

        public function rest_slot_detail(WP_REST_Request $request) {
            $slot_id = absint($request->get_param('slot_id'));
            if (!$slot_id) return new WP_Error('invalid_slot', 'Missing slot_id', ['status' => 400]);

            return $this->build_slot_response($slot_id, true);
        }

        public function rest_active_slot_detail() {
            $now = current_time('timestamp', true);

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

            if (!$q->have_posts()) {
                return rest_ensure_response(['status' => 'no_active_slot', 'slot' => null, 'products' => []]);
            }

            $slot = $q->posts[0];
            return $this->build_slot_response($slot->ID, true);
        }

        /* ===========================
         * HOOKS
         * =========================== */

        public function validate_quota_before_cart($passed, $product_id, $quantity) {
            $quota = $this->get_quota_status($product_id);

            if ($quota['status'] !== 'ok') {
                wc_add_notice(__('Sản phẩm này không nằm trong Flash Sale đang chạy.', 'mdfs'), 'error');
                return false;
            }

            if ($quantity > $quota['quota']['remain']) {
                wc_add_notice(sprintf(
                    __('Chỉ còn %d sản phẩm Flash Sale cho sản phẩm này.', 'mdfs'),
                    $quota['quota']['remain']
                ), 'error');
                return false;
            }

            return $passed;
        }

        /* ===========================
         * HELPERS
         * =========================== */

        private function get_quota_status($product_id) {
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

    if (!$q->have_posts()) {
        return ['status' => 'no_active_slot'];
    }

    $slot_id = $q->posts[0]->ID;
    $from    = (int) get_post_meta($slot_id, '_mdfs_time_from', true);
    $to      = (int) get_post_meta($slot_id, '_mdfs_time_to', true);
    $rows    = get_post_meta($slot_id, '_mdfs_products', true);
    $rows    = is_array($rows) ? $rows : [];

    $sold_map = $this->build_sold_map($slot_id, $from, $to);

    $pobj = wc_get_product($product_id);
    if (!$pobj) {
        return ['status' => 'not_found'];
    }

    $parent_id   = $pobj->is_type('variation') ? $pobj->get_parent_id() : 0;
    $children_ids = $pobj->is_type('variable') ? $pobj->get_children() : [];

    foreach ($rows as $row) {
        $pid = (int)($row['product_id'] ?? 0);   // product cha
        $vid = (int)($row['variation_id'] ?? 0); // variation
        $check_id = $vid ?: $pid;

        // ---- LOGIC MATCH ----
        if (
            (int)$product_id === (int)$check_id ||             // match trực tiếp
            (int)$product_id === (int)$pid ||                  // product_id là cha
            ($parent_id && $parent_id === (int)$pid) ||        // variation match cha
            (!empty($children_ids) && in_array($check_id, $children_ids)) // cha match con
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



        private function build_sold_map($slot_from, $slot_to) {
            global $wpdb;

            $orders = $wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE post_type='shop_order'
                   AND post_status IN ('wc-processing','wc-completed')
                   AND post_date >= %s
                   AND post_date <= %s",
                gmdate('Y-m-d H:i:s', $slot_from),
                gmdate('Y-m-d H:i:s', $slot_to)
            ));

            $map = [];
            if (empty($orders)) return $map;

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

        private function build_slot_response($slot_id, $include_meta = false) {
            $from = (int) get_post_meta($slot_id, '_mdfs_time_from', true);
            $to   = (int) get_post_meta($slot_id, '_mdfs_time_to', true);
            $rows = get_post_meta($slot_id, '_mdfs_products', true);
            $rows = is_array($rows) ? $rows : [];

            $sold_map = $this->build_sold_map($from, $to);

            $products = [];
            foreach ($rows as $row) {
                $pid = (int)($row['product_id'] ?? 0);
                $vid = (int)($row['variation_id'] ?? 0);
                $check_id = $vid ?: $pid;
                if (!$check_id) continue;

                $quota  = (int)($row['quota'] ?? 0);
                $sold   = $sold_map[$check_id] ?? 0;
                $remain = max(0, $quota - $sold);

                $p = wc_get_product($check_id);
                if (!$p) continue;

                $products[] = [
                    'id'      => $check_id,
                    'pid'     => $pid,
                    'vid'     => $vid,
                    'name'    => $p->get_name(),
                    'sku'     => $p->get_sku(),
                    'regular' => (float)($row['regular_price'] ?? $p->get_regular_price()),
                    'sale'    => (float)($row['sale_price'] ?? $p->get_price()),
                    'quota'   => $quota,
                    'sold'    => $sold,
                    'remain'  => $remain,
                    'thumb'   => $row['thumb'] ?: get_the_post_thumbnail_url($check_id, 'woocommerce_thumbnail'),
                    'badge'   => $row['badges'] ?? '',
                ];
            }

            if ($include_meta) {
                return rest_ensure_response([
                    'id'        => $slot_id,
                    'title'     => get_the_title($slot_id),
                    'from'      => $from,
                    'to'        => $to,
                    'cols'      => (int)get_post_meta($slot_id, '_mdfs_cols', true),
                    'view_all'  => get_post_meta($slot_id, '_mdfs_view_all', true),
                    'thumbnail' => ($t = (int)get_post_meta($slot_id, '_mdfs_thumbnail_id', true)) ? wp_get_attachment_url($t) : '',
                    'content'   => get_post_meta($slot_id, '_mdfs_content', true),
                    'products'  => $products,
                ]);
            }

            return rest_ensure_response([
                'slot_id'  => $slot_id,
                'products' => $products,
            ]);
        }
    }

    new MDFS_RT_Api();
}
