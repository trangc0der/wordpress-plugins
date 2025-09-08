<?php
if ( ! defined('ABSPATH') ) exit;
function mdfs_rt_abtest_choose_winner($slot_id){
  $views = (int) get_post_meta($slot_id,'_mdfs_view',true);
  $click = (int) get_post_meta($slot_id,'_mdfs_click',true);
  $ctr = $views? $click/$views : 0;
  $group = get_post_meta($slot_id,'_mdfs_ab_group',true);
  if($group==='B' && $ctr>0.1){ update_post_meta($slot_id,'_mdfs_type','slider'); update_post_meta($slot_id,'_mdfs_ab_winner','B'); }
  else { update_post_meta($slot_id,'_mdfs_ab_winner','A'); }
}
