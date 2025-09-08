<?php
if ( ! defined('ABSPATH') ) exit;
add_action('rest_api_init', function(){
  register_rest_route('mdfs/v1','/slots/active', array(
    'methods'=>'GET','permission_callback'=>'__return_true','callback'=>function(){ 
      $now=current_time('timestamp');
      $q=new WP_Query(array('post_type'=>'mdfs_slot','post_status'=>'publish','posts_per_page'=>-1,'meta_query'=>array(
        array('key'=>'_mdfs_time_from','value'=>$now,'compare'=>'<=','type'=>'NUMERIC'),
        array('key'=>'_mdfs_time_to','value'=>$now,'compare'=>'>=','type'=>'NUMERIC'),
      )));
      $data=array();
      foreach($q->posts as $p){
        $rows=get_post_meta($p->ID,'_mdfs_products',true); if(!is_array($rows)) $rows=array();
        $remain_map=(array)get_post_meta($p->ID,'_mdfs_quota_remaining',true);
        $total=0; $remain=0;
        foreach($rows as $row){ $qta=(int)($row['quota']??0); $total+=$qta; $key = !empty($row['variation_id']) ? ($row['product_id'].':'.$row['variation_id']) : $row['product_id']; $remain += (int)($remain_map[$key] ?? $qta); }
        $data[] = array('id'=>$p->ID,'title'=>get_the_title($p),'from'=>(int)get_post_meta($p->ID,'_mdfs_time_from',true),'to'=>(int)get_post_meta($p->ID,'_mdfs_time_to',true),'products_count'=>count($rows),'quota_total'=>$total,'quota_remain'=>$remain);
      }
      return rest_ensure_response($data);
    }
  ));
});
