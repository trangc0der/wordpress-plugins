<?php
/**
 * Plugin Name: MB Flash Sale Pro
 * Description: Flash Sale tabs/slider, per-variation quota, CSV import/export, roles/caps, WP-CLI, multi-vendor, multi-currency, A/B test, reminders, REST API, system status.
 * Author: MB Văn Trang
 * Version: 1.0.0-pro
 * Text Domain: mdfs-rt
 */
if ( ! defined('ABSPATH') ) exit;
define('MDFS_RT_VER','1.0.0-pro');
define('MDFS_RT_PATH', plugin_dir_path(__FILE__));
define('MDFS_RT_URL', plugin_dir_url(__FILE__));

add_action('init', function(){ load_plugin_textdomain('mdfs-rt', false, dirname(plugin_basename(__FILE__)).'/languages'); });

$modules = array(
  'inc/compat.php','inc/helpers.php','admin/roles.php','admin/slots-cpt.php',
  'admin/csv.php','admin/system-status.php','public/shortcode-tabs.php','public/shortcode-shopee.php',
  'public/shortcode-slider.php','public/dynamic-pricing.php','public/api.php',
  'inc/vendors.php','inc/abtest.php','inc/cron.php','inc/performance.php','inc/cli.php',
  'admin/reports-advanced.php','public/reports-rest.php','public/shortcode-campaign.php'
);
foreach ($modules as $mod){
  $path = MDFS_RT_PATH . $mod;
  if ( file_exists($path) ) { require_once $path; }
  else { error_log('[MDFS-RT] Optional module not found: '.$mod); }
}


add_action('wp_enqueue_scripts', function(){
  wp_enqueue_style('mdfs-rt-skin', MDFS_RT_URL.'assets/mdfs-skin.css', array(), MDFS_RT_VER);
});

register_activation_hook(__FILE__, ['MDFS_RT_Roles','activate']);
register_deactivation_hook(__FILE__, ['MDFS_RT_Roles','deactivate']);
