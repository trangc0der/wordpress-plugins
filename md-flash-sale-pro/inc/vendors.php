<?php
if ( ! defined('ABSPATH') ) exit;
add_filter('parse_query', function($q){
  if( is_admin() && $q->get('post_type')==='mdfs_slot' && !current_user_can('manage_options') ){
    $q->set('author', get_current_user_id() );
  }
});
