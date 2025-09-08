<?php
if ( ! defined('ABSPATH') ) exit;

function mdfs_rt_logger($msg, $context=array()){
  $dir = MDFS_RT_PATH.'logs/'; if(!file_exists($dir)) @mkdir($dir);
  $line = '['.gmdate('c').'] '.$msg;
  if(!empty($context)) $line .= ' '.wp_json_encode($context);
  $line .= PHP_EOL;
  @file_put_contents($dir.'mdfs.log',$line,FILE_APPEND);
}

function mdfs_rt_get_option($k,$d=null){ $v=get_option('mdfs_rt_'.$k,null); return $v===null?$d:$v; }
function mdfs_rt_set_option($k,$v){ update_option('mdfs_rt_'.$k,$v,false); }

function mdfs_rt_convert_to_display_currency($price){ return $price; }
