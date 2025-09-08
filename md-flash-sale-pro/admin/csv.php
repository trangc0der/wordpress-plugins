<?php
if ( ! defined('ABSPATH') ) exit;
add_action('admin_menu', function(){
  add_submenu_page('edit.php?post_type=mdfs_slot','CSV Import/Export','CSV Import/Export','mdfs_import','mdfs_csv', 'mdfs_rt_csv_page');
});
function mdfs_rt_csv_page(){
  if(!current_user_can('mdfs_import')){ wp_die('No permission'); }
  if(isset($_POST['mdfs_export'])){ mdfs_rt_csv_export(); return; }
  if(isset($_POST['mdfs_import']) && !empty($_FILES['mdfs_csv']['tmp_name'])){
    check_admin_referer('mdfs_csv'); $res = mdfs_rt_csv_import($_FILES['mdfs_csv']['tmp_name']);
    echo '<div class="updated"><p>Imported: '.intval($res['ok']).' / Errors: '.intval($res['err']).'</p></div>';
  } ?>
  <div class="wrap"><h1>CSV Import/Export</h1>
    <p>Định dạng: <code>slot_id,product_id,variation_id,sale_price,quota,remain,badges</code></p>
    <h2>Import</h2>
    <form method="post" enctype="multipart/form-data">
      <?php wp_nonce_field('mdfs_csv'); ?>
      <input type="file" name="mdfs_csv" accept=".csv" required>
      <button class="button button-primary" name="mdfs_import" value="1">Import CSV</button>
    </form>
    <hr/><h2>Export</h2>
    <form method="post">
      <label>Slot ID: <input type="number" name="slot_id" required></label>
      <button class="button" name="mdfs_export" value="1">Export CSV</button>
    </form>
  </div><?php
}
function mdfs_rt_csv_export(){
  $slot_id = (int)($_POST['slot_id'] ?? 0);
  $rows = get_post_meta($slot_id,'_mdfs_products',true); if(!is_array($rows)) $rows=array();
  $remain_map = (array) get_post_meta($slot_id,'_mdfs_quota_remaining',true);
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="mdfs-slot-'.$slot_id.'.csv"');
  $out = fopen('php://output','w');
  fputcsv($out, ['slot_id','product_id','variation_id','sale_price','quota','remain','badges']);
  foreach($rows as $r){
    $key = !empty($r['variation_id']) ? $r['product_id'].':'.$r['variation_id'] : $r['product_id'];
    $remain = isset($remain_map[$key]) ? $remain_map[$key] : ($r['quota'] ?? 0);
    fputcsv($out, [$slot_id, $r['product_id'] ?? 0, $r['variation_id'] ?? 0, $r['sale_price'] ?? '', $r['quota'] ?? 0, $remain, $r['badges'] ?? '']);
  }
  fclose($out); exit;
}
function mdfs_rt_csv_import($path){
  $ok=0; $err=0;
  if(($h=fopen($path,'r'))!==false){
    $header = fgetcsv($h);
    while(($row=fgetcsv($h))!==false){
      list($slot_id,$pid,$vid,$price,$quota,$remain,$badges) = array_pad($row,7,'');
      $slot_id=(int)$slot_id; $pid=(int)$pid; $vid=(int)$vid; $quota=(int)$quota; $remain=(int)$remain;
      if(!$slot_id || !$pid){ $err++; continue; }
      $rows = get_post_meta($slot_id,'_mdfs_products',true); if(!is_array($rows)) $rows=array();
      $remain_map = (array) get_post_meta($slot_id,'_mdfs_quota_remaining',true);
      $found=false;
      foreach($rows as &$r){
        if( (int)($r['product_id']??0)===$pid && (int)($r['variation_id']??0)===$vid ){
          $r['sale_price']=$price; $r['quota']=$quota; $r['badges']=$badges; $found=true; break;
        }
      }
      if(!$found){ $rows[] = ['product_id'=>$pid,'variation_id'=>$vid,'sale_price'=>$price,'quota'=>$quota,'badges'=>$badges]; }
      $key = $vid ? ($pid.':'.$vid) : $pid; $remain_map[$key]=$remain or $quota; if(!$remain) $remain_map[$key]=$quota;
      update_post_meta($slot_id,'_mdfs_products',$rows);
      update_post_meta($slot_id,'_mdfs_quota_remaining',$remain_map);
      $ok++;
    }
    fclose($h);
  }
  return ['ok'=>$ok,'err'=>$err];
}
