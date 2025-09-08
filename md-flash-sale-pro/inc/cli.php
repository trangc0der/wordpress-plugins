<?php
if ( ! defined('ABSPATH') ) exit;
if ( defined('WP_CLI') && WP_CLI ){
  class MDFS_RT_CLI {
    public function slots__list($args,$assoc){
      $q = new WP_Query(array('post_type'=>'mdfs_slot','post_status'=>array('publish','pending','draft'),'posts_per_page'=>-1));
      foreach($q->posts as $p){ WP_CLI::line("#{$p->ID} {$p->post_title}  from=".get_post_meta($p->ID,'_mdfs_time_from',true)." to=".get_post_meta($p->ID,'_mdfs_time_to',true)); }
    }
    public function slots__clone($args,$assoc){
      list($slot_id) = $args; $slot_id = (int)$slot_id;
      $p = get_post($slot_id); if(!$p) { WP_CLI::error('Slot not found'); return; }
      $new_id = wp_insert_post(array('post_type'=>'mdfs_slot','post_status'=>'draft','post_title'=>$p->post_title.' (Clone)'));
      foreach(get_post_meta($slot_id) as $k=>$vals){ if(strpos($k,'_mdfs_')===0){ update_post_meta($new_id,$k, maybe_unserialize($vals[0])); } }
      WP_CLI::success('Cloned to #'.$new_id);
    }
    public function analytics__rollup($args,$assoc){
      $buf = get_option('mdfs_events_buffer', array());
      WP_CLI::success('Buffered events: '.count($buf));
    }
  }
  WP_CLI::add_command('mdfs slots:list', [new MDFS_RT_CLI(),'slots__list']);
  WP_CLI::add_command('mdfs slots:clone', [new MDFS_RT_CLI(),'slots__clone']);
  WP_CLI::add_command('mdfs analytics:rollup', [new MDFS_RT_CLI(),'analytics__rollup']);
}
