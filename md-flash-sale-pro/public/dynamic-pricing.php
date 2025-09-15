<?php
if ( ! defined('ABSPATH') ) exit;

class MDFS_RT_DynamicPricing {
    private $map = [];

    public function __construct(){
        add_action('init', [$this, 'collect'], 20);

        // Ghi đè giá
        add_filter('woocommerce_product_get_sale_price', [$this, 'sale'], 20, 2);
        add_filter('woocommerce_product_get_price',      [$this, 'price'], 20, 2);
        add_filter('woocommerce_product_is_on_sale',     [$this, 'on_sale'], 20, 2);

        // Ghi đè hiển thị giá của product cha variable
        add_filter('woocommerce_variable_price_html',    [$this, 'variable_price_html'], 20, 2);

        // Clear cache khi update slot
        add_action('save_post_mdfs_slot', [$this, 'clear_cache']);
    }

    /**
     * Thu thập dữ liệu flash sale -> cache map
     */
    public function collect(){
        if ( is_admin() ) return; // chỉ chạy frontend

        $cache_key = 'mdfs_dynamic_pricing_map';
        $cached = get_transient($cache_key);
        if ( $cached !== false ) {
            $this->map = $cached;
            return;
        }

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
        foreach ( $q->posts as $slot ) {
            $rows = get_post_meta($slot->ID, '_mdfs_products', true);
            if ( ! is_array($rows) ) $rows = [];

            $remain_map = (array) get_post_meta($slot->ID, '_mdfs_quota_remaining', true);

            foreach ( $rows as $r ) {
                $pid   = intval($r['product_id'] ?? 0);
                $vid   = intval($r['variation_id'] ?? 0);
                $price = $r['sale_price'] ?? '';
                $quota = isset($remain_map[$vid ? ($pid . ':' . $vid) : (string) $pid]) 
                           ? intval($remain_map[$vid ? ($pid . ':' . $vid) : (string) $pid]) 
                           : intval($r['quota'] ?? 0);

                if ( $pid && $price !== '' && $quota > 0 ) {
                    $key = $vid ? ($pid . ':' . $vid) : (string) $pid;
                    $map[$key] = wc_format_decimal($price);
                }
            }
        }
        wp_reset_postdata();

        $this->map = $map;
        set_transient($cache_key, $map, 30); // cache 30 giây
    }

    private function key_for($product){
        if ( $product->is_type('variation') ) {
            return $product->get_parent_id() . ':' . $product->get_id();
        }
        return (string) $product->get_id();
    }

    public function sale($price, $product){
        $key = $this->key_for($product);
        return isset($this->map[$key]) ? $this->map[$key] : $price;
    }

    public function price($price, $product){
        $key = $this->key_for($product);
        return isset($this->map[$key]) ? $this->map[$key] : $price;
    }

    public function on_sale($on_sale, $product){
        $key = $this->key_for($product);
        return isset($this->map[$key]) ? true : $on_sale;
    }

    /**
     * Override hiển thị giá của product cha variable
     */
    public function variable_price_html($price_html, $product){
        $children = $product->get_children();
        $sale_prices = [];

        foreach($children as $vid){
            $var = wc_get_product($vid);
            if(!$var) continue;
            $k = $this->key_for($var);
            if(isset($this->map[$k])){
                $sale_prices[] = $this->map[$k];
            }
        }

        if(!empty($sale_prices)){
            $min = min($sale_prices);
            $max = max($sale_prices);
            if($min == $max){
                return wc_price($min);
            }
            return wc_price($min) . ' - ' . wc_price($max);
        }

        return $price_html;
    }

    /**
     * Xóa cache khi lưu slot
     */
    public function clear_cache(){
        delete_transient('mdfs_dynamic_pricing_map');
    }
}

new MDFS_RT_DynamicPricing();
