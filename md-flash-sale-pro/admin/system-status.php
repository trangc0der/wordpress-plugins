<?php
if ( ! defined('ABSPATH') ) exit;
add_action('admin_menu', function(){
  add_submenu_page('edit.php?post_type=mdfs_slot','System Status','System Status','manage_options','mdfs_status','mdfs_rt_status_page');
});
function mdfs_rt_status_page(){
  $cron = wp_next_scheduled('mdfs_rt_cron_tick');
  $logfile = @file_exists(MDFS_RT_PATH.'logs/mdfs.log') ? esc_html(file_get_contents(MDFS_RT_PATH.'logs/mdfs.log')) : 'No logs';
  echo '<div class="wrap"><h1>System Status</h1>';
  echo '<table class="widefat"><tr><th>Version</th><td>'.esc_html(MDFS_RT_VER).'</td></tr>';
  echo '<tr><th>Woo</th><td>'.(class_exists('WooCommerce')?'Yes':'No').'</td></tr>';
  echo '<tr><th>Next Cron</th><td>'.($cron?date_i18n('Y-m-d H:i:s',$cron):'—').'</td></tr></table>';
  echo '<h2>Logs</h2><textarea style="width:100%;height:240px" readonly>'.$logfile.'</textarea></div>';
}
