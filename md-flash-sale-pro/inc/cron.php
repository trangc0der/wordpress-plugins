<?php
if ( ! defined('ABSPATH') ) exit;
add_action('mdfs_rt_cron_tick','mdfs_rt_cron_tick_cb');
if( ! wp_next_scheduled('mdfs_rt_cron_tick') ){ wp_schedule_event(time()+120,'minute','mdfs_rt_cron_tick'); }
add_filter('cron_schedules', function($s){ $s['minute']=array('interval'=>60,'display'=>'Every Minute'); return $s; });

function mdfs_rt_cron_tick_cb(){
  $now=current_time('timestamp');
  $q=new WP_Query(array('post_type'=>'mdfs_slot','post_status'=>'publish','posts_per_page'=>-1,'meta_query'=>array(
    array('key'=>'_mdfs_time_from','value'=>$now+15*60,'compare'=>'<=','type'=>'NUMERIC'),
    array('key'=>'_mdfs_time_from','value'=>$now,'compare'=>'>','type'=>'NUMERIC'),
  )));
  foreach($q->posts as $slot){
    $sent = get_post_meta($slot->ID,'_mdfs_notify_sent',true);
    if($sent) continue;
    $subscribers = (array) get_post_meta($slot->ID,'_mdfs_subscribers',true);
    foreach($subscribers as $email){
      wp_mail($email, 'Flash Sale sắp bắt đầu', 'Slot sẽ mở lúc '.date_i18n('H:i d/m/Y', (int)get_post_meta($slot->ID,'_mdfs_time_from',true)));
    }
    update_post_meta($slot->ID,'_mdfs_notify_sent',1);
  }
  $queue = get_option('mdfs_rt_webhook_queue', array());
  $newq = array();
  foreach($queue as $item){
    $url = esc_url_raw($item['url'] ?? ''); if(!$url) continue;
    $body = wp_json_encode($item['payload'] ?? array());
    $res = wp_remote_post($url, array('timeout'=>10,'body'=>$body,'headers'=>array('Content-Type'=>'application/json')));
    if(is_wp_error($res) || wp_remote_retrieve_response_code($res)>=300){
      $item['attempts'] = ($item['attempts'] ?? 0) + 1;
      if($item['attempts'] < 5){ $newq[] = $item; }
      else { mdfs_rt_logger('Webhook dropped', $item); }
    }
  }
  update_option('mdfs_rt_webhook_queue',$newq,false);
}
