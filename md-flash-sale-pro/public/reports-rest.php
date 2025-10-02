<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('MDFS_RT_Reports_Rest')) {

    class MDFS_RT_Reports_Rest {

        public function __construct() {
            add_action('rest_api_init', [$this, 'register_routes']);
        }

        public function register_routes() {
            $endpoints = [
                'summary'       => 'report_summary',
                'active-slot'   => 'report_active_slot',
                'top-slots'     => 'report_top_slots',
                'top-products'  => 'report_top_products',
                'low-stock'     => 'report_low_stock',
                'unused-slots'  => 'report_unused_slots',
                'export'        => 'report_export_csv',
                'trend'         => 'report_trend',
            ];
            foreach ($endpoints as $route => $cb) {
                register_rest_route('mdfs/v1', "/report/$route", [
                    'methods'  => 'GET',
                    'callback' => [$this, $cb],
                    'permission_callback' => [$this, 'can_view_reports'],
                    'args' => [
                        'from' => ['required' => false, 'type' => 'string'],
                        'to'   => ['required' => false, 'type' => 'string'],
                        'type' => ['required' => false, 'type' => 'string'],
                    ],
                ]);
            }
        }

        public function can_view_reports() {
            return current_user_can('manage_options');
        }

        private function parse_range($from, $to) {
            $from_ts = $from ? strtotime($from . ' 00:00:00') : strtotime('-30 days');
            $to_ts   = $to   ? strtotime($to . ' 23:59:59') : time();
            return [$from_ts, $to_ts];
        }

        private function get_orders_in_range($from_ts, $to_ts) {
            return wc_get_orders([
                'status' => ['completed', 'processing'],
                'limit'  => -1,
                'date_created' => gmdate('Y-m-d H:i:s', $from_ts) . '...' . gmdate('Y-m-d H:i:s', $to_ts),
                'return' => 'ids',
            ]);
        }

        /** Báo cáo tổng quan */
        public function report_summary(WP_REST_Request $req) {
            [$from_ts, $to_ts] = $this->parse_range($req['from'], $req['to']);
            $orders = $this->get_orders_in_range($from_ts, $to_ts);

            $revenue = 0; $items = 0;
            foreach ($orders as $oid) {
                $o = wc_get_order($oid);
                if (!$o) continue;
                $revenue += $o->get_total();
                $items   += $o->get_item_count();
            }

            return [
                'total_slots'    => wp_count_posts('mdfs_slot')->publish,
                'total_products' => wp_count_posts('product')->publish,
                'total_orders'   => count($orders),
                'total_items'    => $items,
                'total_revenue'  => $revenue,
            ];
        }

        /** Slot đang chạy */
        public function report_active_slot(WP_REST_Request $req) {
            $now = current_time('timestamp');
            $q = new WP_Query([
                'post_type'   => 'mdfs_slot',
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'meta_query' => [
                    ['key'=>'_mdfs_time_from','value'=>$now,'compare'=>'<=','type'=>'NUMERIC'],
                    ['key'=>'_mdfs_time_to','value'=>$now,'compare'=>'>=','type'=>'NUMERIC'],
                ],
            ]);
            if (!$q->have_posts()) return ['status'=>'no_active_slot'];

            $slot_id = $q->posts[0]->ID;
            $rows = get_post_meta($slot_id, '_mdfs_products', true) ?: [];

            $products = [];
            foreach ($rows as $row) {
                $pid   = (int)($row['variation_id'] ?: $row['product_id']);
                $quota = (int)($row['quota'] ?? 0);
                $sold  = 0; // có thể nối với sold_map
                $products[] = [
                    'id'    => $pid,
                    'title' => get_the_title($pid),
                    'quota' => $quota,
                    'sold'  => $sold,
                    'remain'=> max(0,$quota-$sold),
                ];
            }

            return [
                'status'   => 'ok',
                'slot_id'  => $slot_id,
                'title'    => get_the_title($slot_id),
                'from'     => (int)get_post_meta($slot_id,'_mdfs_time_from',true),
                'to'       => (int)get_post_meta($slot_id,'_mdfs_time_to',true),
                'products' => $products,
            ];
        }

        /** Top sản phẩm */
        public function report_top_products(WP_REST_Request $req) {
            [$from_ts, $to_ts] = $this->parse_range($req['from'], $req['to']);
            $orders = $this->get_orders_in_range($from_ts, $to_ts);

            $map = [];
            foreach ($orders as $oid) {
                $o = wc_get_order($oid);
                foreach ($o->get_items() as $it) {
                    $id = $it->get_variation_id() ?: $it->get_product_id();
                    $qty = $it->get_quantity();
                    $rev = $it->get_total();
                    if (!isset($map[$id])) {
                        $map[$id] = ['product_id'=>$id,'title'=>get_the_title($id),'sold'=>0,'revenue'=>0];
                    }
                    $map[$id]['sold']    += $qty;
                    $map[$id]['revenue'] += $rev;
                }
            }

            $arr = array_values($map);
            usort($arr, fn($a,$b)=>$b['revenue']<=>$a['revenue']);
            return $arr;
        }

        /** Top slots */
        public function report_top_slots(WP_REST_Request $req) {
            [$from_ts, $to_ts] = $this->parse_range($req['from'], $req['to']);
            $slots = get_posts(['post_type'=>'mdfs_slot','numberposts'=>-1]);
            $arr = [];
            foreach ($slots as $slot) {
                $rows = get_post_meta($slot->ID,'_mdfs_products',true) ?: [];
                $rev=0;
                foreach ($rows as $row) {
                    $pid=(int)($row['variation_id'] ?: $row['product_id']);
                    $prod=wc_get_product($pid);
                    if($prod) $rev += (float)$prod->get_price();
                }
                $arr[]=['slot_id'=>$slot->ID,'title'=>$slot->post_title,'revenue'=>$rev];
            }
            usort($arr,fn($a,$b)=>$b['revenue']<=>$a['revenue']);
            return $arr;
        }

        /** Low stock */
        public function report_low_stock(WP_REST_Request $req) {
            $slots = get_posts(['post_type'=>'mdfs_slot','numberposts'=>-1]);
            $alerts=[];
            foreach ($slots as $slot) {
                $rows = get_post_meta($slot->ID,'_mdfs_products',true) ?: [];
                foreach ($rows as $row) {
                    $pid=(int)($row['variation_id'] ?: $row['product_id']);
                    $quota=(int)($row['quota']??0);
                    $sold=0; $remain=max(0,$quota-$sold);
                    if($quota>0 && $remain<=5) {
                        $alerts[]=['slot_id'=>$slot->ID,'slot_title'=>$slot->post_title,'product_id'=>$pid,'title'=>get_the_title($pid),'remain'=>$remain];
                    }
                }
            }
            return $alerts;
        }

        /** Unused slots */
        public function report_unused_slots(WP_REST_Request $req) {
            $slots=get_posts(['post_type'=>'mdfs_slot','numberposts'=>-1]);
            $unused=[];
            foreach($slots as $slot){
                $rows=get_post_meta($slot->ID,'_mdfs_products',true);
                if(empty($rows)) $unused[]=['slot_id'=>$slot->ID,'title'=>$slot->post_title];
            }
            return $unused;
        }

        /** Xuất CSV */
        public function report_export_csv(WP_REST_Request $req) {
            $type=$req['type']?:'slots';
            $slots=get_posts(['post_type'=>'mdfs_slot','numberposts'=>-1]);
            $rows=[];
            foreach($slots as $slot){
                $prods=get_post_meta($slot->ID,'_mdfs_products',true)?:[];
                foreach($prods as $r){
                    $pid=(int)($r['variation_id'] ?: $r['product_id']);
                    $quota=(int)($r['quota']??0);
                    $sold=0;
                    $rows[]=[
                        'slot_id'=>$slot->ID,
                        'slot_title'=>$slot->post_title,
                        'product_id'=>$pid,
                        'product'=>get_the_title($pid),
                        'quota'=>$quota,
                        'sold'=>$sold,
                        'remain'=>max(0,$quota-$sold),
                    ];
                }
            }
            return $rows;
        }

        /** Revenue trend theo ngày */
        public function report_trend(WP_REST_Request $req) {
            [$from_ts,$to_ts]=$this->parse_range($req['from'],$req['to']);
            $orders=$this->get_orders_in_range($from_ts,$to_ts);
            $trend=[];
            foreach($orders as $oid){
                $o=wc_get_order($oid);
                $date=$o->get_date_created()->date('Y-m-d');
                if(!isset($trend[$date])) $trend[$date]=0;
                $trend[$date]+=$o->get_total();
            }
            ksort($trend);
            return ['labels'=>array_keys($trend),'revenue'=>array_values($trend)];
        }
    }

    new MDFS_RT_Reports_Rest();
}
