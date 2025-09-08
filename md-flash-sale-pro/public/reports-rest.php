<?php
if ( ! defined('ABSPATH') ) exit;

add_action('rest_api_init', function () {
  register_rest_route('mdfs/v1', '/reports/summary', [
    'methods'  => 'GET',
    'permission_callback' => function(){ return current_user_can('mdfs_view_analytics') || current_user_can('manage_woocommerce'); },
    'callback' => 'mdfs_rt_reports_summary',
    'args'     => [
      'from' => ['required'=>true], // yyyy-mm-dd
      'to'   => ['required'=>true], // yyyy-mm-dd
    ]
  ]);
  register_rest_route('mdfs/v1', '/reports/export', [
    'methods'  => 'GET',
    'permission_callback' => function(){ return current_user_can('mdfs_view_analytics') || current_user_can('manage_woocommerce'); },
    'callback' => 'mdfs_rt_reports_export',
    'args'     => [
      'from' => ['required'=>true], 'to'=>['required'=>true], 'type'=>['required'=>true], // slots|products
    ]
  ]);
});

/** Build map: product/variation -> [slot_id, time_from, time_to] */
function mdfs_rt_slot_product_map() {
  $q = new WP_Query([
    'post_type' => 'mdfs_slot','post_status'=>'publish','posts_per_page'=>-1
  ]);
  $map = []; // key: product_id or "p:v" => array of slots to match by date
  foreach ($q->posts as $slot) {
    $from = (int) get_post_meta($slot->ID,'_mdfs_time_from',true);
    $to   = (int) get_post_meta($slot->ID,'_mdfs_time_to',true);
    $rows = get_post_meta($slot->ID,'_mdfs_products',true); if(!is_array($rows)) $rows=[];
    foreach($rows as $r){
      $pid = (int)($r['product_id'] ?? 0);
      $vid = (int)($r['variation_id'] ?? 0);
      if(!$pid) continue;
      $key = $vid ? "{$pid}:{$vid}" : (string)$pid;
      $map[$key][] = ['slot_id'=>$slot->ID,'from'=>$from,'to'=>$to];
    }
  }
  return $map;
}

function mdfs_rt_reports_summary( WP_REST_Request $req ){
  $from = sanitize_text_field($req->get_param('from'));
  $to   = sanitize_text_field($req->get_param('to'));
  $dt_from = strtotime($from.' 00:00:00');
  $dt_to   = strtotime($to.' 23:59:59');

  // Build slot-product map once
  $map = mdfs_rt_slot_product_map();

  // Query orders in range
  $stati = apply_filters('mdfs_rt_report_statuses', ['processing','completed']);
  $oq = new WC_Order_Query([
    'limit' => -1,
    'status'=> $stati,
    'date_created' => $from.'...'.$to,
    'return' => 'ids',
  ]);
  $order_ids = $oq->get_orders();

  $series = []; // 'Y-m-d' => revenue
  $slots  = []; // slot_id => metrics
  $prods  = []; // product_id or p:v => metrics

  foreach ($order_ids as $oid){
    $o = wc_get_order($oid); if(!$o) continue;
    $created = $o->get_date_created(); if(!$created) continue;
    $ts = $created->getTimestamp();
    $day = date('Y-m-d', $ts);

    foreach ( $o->get_items() as $item ){
      $prod = $item->get_product(); if(!$prod) continue;
      $is_var = $prod->is_type('variation');
      $pid = $is_var ? $prod->get_parent_id() : $prod->get_id();
      $vid = $is_var ? $prod->get_id() : 0;
      $key = $vid ? "{$pid}:{$vid}" : (string)$pid;

      // Find matching slot by order time
      $slot_id = 0;
      if (!empty($map[$key])){
        foreach($map[$key] as $s){
          if($ts >= (int)$s['from'] && $ts <= (int)$s['to']) { $slot_id = (int)$s['slot_id']; break; }
        }
      } elseif(!empty($map[(string)$pid])) {
        foreach($map[(string)$pid] as $s){
          if($ts >= (int)$s['from'] && $ts <= (int)$s['to']) { $slot_id = (int)$s['slot_id']; break; }
        }
      }
      if(!$slot_id) continue; // không thuộc flash sale

      $qty  = (float)$item->get_quantity();
      $line = (float)$o->get_item_total($item, true, true); // đã gồm thuế nếu cài đặt

      // timeseries
      $series[$day] = ($series[$day] ?? 0) + $line;

      // slot agg
      if(!isset($slots[$slot_id])) $slots[$slot_id] = ['slot_id'=>$slot_id,'title'=>get_the_title($slot_id),'orders'=>0,'items'=>0,'revenue'=>0.0];
      $slots[$slot_id]['orders']  += 1;          // đếm item-level ~ gần đúng số đơn có mặt hàng khuyến mãi
      $slots[$slot_id]['items']   += $qty;
      $slots[$slot_id]['revenue'] += $line;

      // product agg
      if(!isset($prods[$key])) $prods[$key] = ['key'=>$key,'pid'=>$pid,'vid'=>$vid,'name'=>$prod->get_name(),'qty'=>0,'revenue'=>0.0,'orders'=>0];
      $prods[$key]['qty']     += $qty;
      $prods[$key]['revenue'] += $line;
      $prods[$key]['orders']  += 1;
    }
  }

  ksort($series);
  // Normalize series
  $days=[]; $vals=[];
  $cursor = strtotime($from); $end = strtotime($to);
  while($cursor <= $end){
    $d = date('Y-m-d',$cursor);
    $days[] = $d;
    $vals[] = (float)($series[$d] ?? 0);
    $cursor = strtotime('+1 day', $cursor);
  }

  // Top lists
  usort($slots, function($a,$b){ return $b['revenue']<=>$a['revenue']; });
  $slots = array_values($slots);
  $prods = array_values($prods);
  usort($prods, function($a,$b){ return $b['revenue']<=>$a['revenue']; });

  return rest_ensure_response([
    'series' => ['labels'=>$days,'revenue'=>$vals],
    'slots'  => array_slice($slots, 0, 1000),
    'products'=> array_slice($prods, 0, 1000),
  ]);
}

function mdfs_rt_reports_export( WP_REST_Request $req ){
  $data = mdfs_rt_reports_summary($req);
  $arr  = $data->get_data();
  $type = sanitize_key($req->get_param('type'));
  $rows = [];
  if($type==='slots'){
    $rows[] = ['slot_id','title','orders','items','revenue'];
    foreach($arr['slots'] as $r){ $rows[] = [$r['slot_id'],$r['title'],$r['orders'],$r['items'],wc_format_decimal($r['revenue'],2)]; }
  } else {
    $rows[] = ['key','product_id','variation_id','name','orders','qty','revenue'];
    foreach($arr['products'] as $r){ $rows[] = [$r['key'],$r['pid'],$r['vid'],$r['name'],$r['orders'],$r['qty'],wc_format_decimal($r['revenue'],2)]; }
  }
  // stream CSV
  nocache_headers();
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="mdfs-report-'.$type.'-'.date('Ymd-His').'.csv"');
  $out = fopen('php://output','w');
  foreach($rows as $line) fputcsv($out,$line);
  fclose($out); exit;
}
