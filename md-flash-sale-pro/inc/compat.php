<?php
if ( ! defined('ABSPATH') ) exit;
if ( ! function_exists('wp_timezone') ) {
  function wp_timezone(){
    $tzstring = get_option('timezone_string');
    if ($tzstring) { try { return new DateTimeZone($tzstring); } catch(Exception $e){} }
    return new DateTimeZone('UTC');
  }
}
