<?php
if ( ! defined('ABSPATH') ) exit;

class MDFS_RT_Roles{
  public static function activate(){
    $caps = ['mdfs_manage','mdfs_edit_prices','mdfs_view_analytics','mdfs_preview','mdfs_vendor','mdfs_import','mdfs_export','mdfs_cli','mdfs_abtest'];
    $admin = get_role('administrator'); if($admin){ foreach($caps as $c){ $admin->add_cap($c); } }
    add_role('mdfs_marketer','Flash Sale Marketer',[ 'read'=>true,'mdfs_manage'=>true,'mdfs_view_analytics'=>true,'mdfs_export'=>true ]);
    add_role('mdfs_qa','Flash Sale QA',[ 'read'=>true, 'mdfs_preview'=>true ]);
  }
  public static function deactivate(){}
}
function mdfs_rt_can_edit_price(){ return current_user_can('mdfs_edit_prices') || current_user_can('administrator'); }
