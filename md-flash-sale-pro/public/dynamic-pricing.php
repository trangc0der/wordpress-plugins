<?php
if ( ! defined('ABSPATH') ) exit;

class MDFS_RT_DynamicPricing {
  private $map=array();
  public function __construct(){
    add_action('init',array($this,'collect'),20);
    add_filter('woocommerce_product_get_sale_price',array($this,'sale'),10,2);
    add_filter('woocommerce_product_get_price',array($this,'price'),10,2);
    add_filter('woocommerce_product_is_on_sale',array($this,'on_sale'),10,2);
  }
  public function collect(){
    $now=current_time('timestamp');
    $q=new WP_Query(array('post_type'=>'mdfs_slot','post_status'=>'publish','posts_per_page'=>-1,'meta_query'=>array(
      array('key'=>'_mdfs_time_from','value'=>$now,'compare'=>'<=','type'=>'NUMERIC'),
      array('key'=>'_mdfs_time_to','value'=>$now,'compare'=>'>=','type'=>'NUMERIC'),
    )));
    $map=array();
    foreach($q->posts as $slot){
      $rows=get_post_meta($slot->ID,'_mdfs_products',true); if(!is_array($rows)) $rows=array();
      foreach($rows as $r){ $pid=intval($r['product_id']??0); $vid=intval($r['variation_id']??0); $price=$r['sale_price']??''; if($pid && $price!==''){ $key=$vid? $pid.':'.$vid : $pid; $map[$key]=$price; } }
    }
    $this->map=$map;
  }
  private function key_for($product){ if($product->is_type('variation')){ return $product->get_parent_id().':'.$product->get_id(); } return $product->get_id(); }
  public function sale($p,$product){ $k=$this->key_for($product); return isset($this->map[$k])?$this->map[$k]:$p; }
  public function price($p,$product){ $k=$this->key_for($product); return isset($this->map[$k])?$this->map[$k]:$p; }
  public function on_sale($b,$product){ $k=$this->key_for($product); return isset($this->map[$k])?true:$b; }
}
new MDFS_RT_DynamicPricing();
