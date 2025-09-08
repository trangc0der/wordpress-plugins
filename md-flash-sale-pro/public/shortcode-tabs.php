<?php
if ( ! defined('ABSPATH') ) exit;

function mdfs_rt_get_slots_day($offset=0){
  $now=current_time('timestamp');
  $start=strtotime(($offset?'+1 day':'today').' 00:00:00',$now);
  $end=strtotime(($offset?'+1 day':'today').' 23:59:59',$now);
  $q=new WP_Query(array('post_type'=>'mdfs_slot','post_status'=>'publish','posts_per_page'=>-1,'orderby'=>'meta_value_num','meta_key'=>'_mdfs_time_from','order'=>'ASC','meta_query'=>array(
    array('key'=>'_mdfs_time_from','value'=>$start,'compare'=>'>=','type'=>'NUMERIC'),
    array('key'=>'_mdfs_time_to','value'=>$end,'compare'=>'<=','type'=>'NUMERIC'),
  )));
  return $q->posts;
}

add_shortcode('md_flash_sale_tabs', function($atts=array()){
  if( ! class_exists('WooCommerce') ) return '';
  $today=mdfs_rt_get_slots_day(0); $tomorrow=mdfs_rt_get_slots_day(1);
  ob_start(); ?>
  <?php
  $content = get_post_meta($slot->ID,'_mdfs_content',true);
  if ( $content ) {
    // Dùng the_content filter để auto-embed, xử lý shortcode… như post thường
    $html = apply_filters('the_content', $content);
    echo '<div class="mdfs-slot-content">'.$html.'</div>';
  }
  ?>
  <div class="mdfs-tabs-wrap">
    <div class="mdfs-section-head">
      <div class="mdfs-title"><div class="bolt">⚡</div><span>FLASH SALE</span>
        <div class="mdfs-countdown" id="mdfs-head-countdown"><div class="mdfs-timebox">00</div><div class="mdfs-timebox">00</div><div class="mdfs-timebox">00</div></div>
      </div>
      <div class="mdfs-cta"><a class="mdfs-seeall" href="<?php echo esc_url( wc_get_page_permalink('shop') ); ?>">Xem tất cả »</a></div>
    </div>
    <div class="mdfs-tabs-head"><button class="mdfs-tab active" data-tab="#mdfs-today">Hôm nay</button><button class="mdfs-tab" data-tab="#mdfs-tomorrow">Ngày mai</button></div>
    <?php foreach(array('today'=>$today,'tomorrow'=>$tomorrow) as $key=>$slots): ?>
      <div class="mdfs-day-content <?php echo $key==='today'?'active':''; ?>" id="mdfs-<?php echo $key; ?>">
        <div class="mdfs-tabs-head">
          <?php foreach($slots as $i=>$slot):
            if(get_post_meta($slot->ID,'_mdfs_test_mode',true) && !current_user_can('mdfs_preview')) continue;
            $from=(int)get_post_meta($slot->ID,'_mdfs_time_from',true); $to=(int)get_post_meta($slot->ID,'_mdfs_time_to',true); ?>
            <button class="mdfs-tab <?php echo $i===0?'active':''; ?>" data-tab="#slot-<?php echo $key.'-'.$slot->ID; ?>" data-end="<?php echo esc_attr($to); ?>"><?php echo esc_html(date('H:i',$from)); ?></button>
          <?php endforeach; if(empty($slots)) echo '<em>Chưa có slot</em>'; ?>
        </div>
        <div class="mdfs-tabs-body">
          <?php foreach($slots as $i=>$slot):
            if(get_post_meta($slot->ID,'_mdfs_test_mode',true) && !current_user_can('mdfs_preview')) continue;
            $rows=get_post_meta($slot->ID,'_mdfs_products',true); if(!is_array($rows)) $rows=array();
            $remain_map=(array)get_post_meta($slot->ID,'_mdfs_quota_remaining',true);
            $ids=array(); foreach($rows as $r){ $ids[]=intval($r['variation_id'] ?: $r['product_id']); }
            if(empty($ids)) continue;
            $q=new WP_Query(array('post_type'=>'product','post__in'=>$ids,'orderby'=>'post__in','posts_per_page'=>-1));
          ?>
          <div class="mdfs-tab-content <?php echo $i===0?'active':''; ?>" id="slot-<?php echo $key.'-'.$slot->ID; ?>" data-end="<?php echo esc_attr((int)get_post_meta($slot->ID,'_mdfs_time_to',true)); ?>">
            <div class="mdfs-grid">
              <?php while($q->have_posts()): $q->the_post(); global $post;
                $pobj = wc_get_product($post->ID);
                $is_var = $pobj && $pobj->is_type('variation');
                $pid = $is_var? $pobj->get_parent_id() : $pobj->get_id();
                $vid = $is_var? $pobj->get_id() : 0;
                $reg = (float) $pobj->get_regular_price();
                $sale = (float) ($pobj->get_sale_price()!=='' ? $pobj->get_sale_price() : $reg);
                foreach($rows as $_r){
                  $match = ((int)($_r['variation_id']??0) === $vid) || ( !$vid && (int)($_r['product_id']??0)===$pid );
                  if($match && $_r['sale_price']!==''){ $sale = (float) $_r['sale_price']; $reg = $reg ?: $sale; break; }
                }
                $disc = ($reg>0 && $sale<$reg) ? max(0, min(99, round((1-($sale/$reg))*100))) : 0;
                $quota=0; $keyRemain = $vid? $pid.':'.$vid : $pid;
                foreach($rows as $_r){ $m=((int)($_r['variation_id']??0) === $vid) || (!$vid && (int)($_r['product_id']??0)===$pid); if($m){ $quota=(int)$_r['quota']; break; } }
                $remain = isset($remain_map[$keyRemain]) ? (int)$remain_map[$keyRemain] : $quota;
                $sold = max(0, $quota-$remain); $pct = $quota>0 ? min(100,round($sold*100/$quota)) : 0;
              ?>
              <a class="mdfs-card" href="<?php the_permalink(); ?>">
                <div class="mdfs-thumb"><?php echo get_the_post_thumbnail($post->ID,'woocommerce_thumbnail', ['loading'=>'lazy']); ?>
                  <div class="mdfs-badge">Yêu thích</div><?php if($disc>0): ?><div class="mdfs-ribbon">-<?php echo $disc; ?>%</div><?php endif; ?><div class="mdfs-nine-tag">99</div></div>
                <div class="mdfs-meta"><div class="mdfs-name"><?php the_title(); ?></div>
                  <div class="mdfs-price"><div class="mdfs-sale"><?php echo wc_price($sale); ?></div><?php if($reg && $sale < $reg): ?><div class="mdfs-regular"><?php echo wc_price($reg); ?></div><?php endif; ?></div>
                  <?php if($quota>0): ?><div class="mdfs-progress mdfs--shopee"><span style="width:<?php echo esc_attr($pct); ?>%"></span><div class="mdfs-chip"><?php echo $pct>=100?'HẾT HÀNG':'ĐANG BÁN CHẠY'; ?></div></div><?php endif; ?>
                </div>
              </a>
              <?php endwhile; wp_reset_postdata(); ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <script>
  (function(){
    document.addEventListener('click',function(e){
      var b=e.target.closest('.mdfs-tab'); if(!b) return;
      var wrap=b.closest('.mdfs-tabs-wrap'); var target=b.getAttribute('data-tab'); if(!target) return;
      var scope=b.parentElement; scope.querySelectorAll('.mdfs-tab').forEach(function(x){x.classList.remove('active')});
      b.classList.add('active');
      if(target.startsWith('#mdfs-')){ wrap.querySelectorAll('.mdfs-day-content').forEach(function(x){x.classList.remove('active')}); var day=wrap.querySelector(target); if(day){day.classList.add('active');} }
      else { var dayblock=b.closest('.mdfs-day-content'); dayblock.querySelectorAll('.mdfs-tab-content').forEach(function(x){x.classList.remove('active')}); var pane=dayblock.querySelector(target); if(pane){pane.classList.add('active');} var to=parseInt(b.getAttribute('data-end')||'0',10); var head=document.getElementById('mdfs-head-countdown'); if(head&&to>0){ head.setAttribute('data-end',String(to)); } }
    });
    function tick(){ var head=document.getElementById('mdfs-head-countdown'); if(!head){ setTimeout(tick,1000); return; } var to=parseInt(head.getAttribute('data-end')||'0',10); var now=Math.floor(Date.now()/1000); var r=Math.max(0,to-now); var h=('0'+Math.floor(r/3600)).slice(-2),m=('0'+Math.floor((r%3600)/60)).slice(-2),s=('0'+(r%60)).slice(-2); var b=head.querySelectorAll('.mdfs-timebox'); if(b.length>=3){ b[0].textContent=h; b[1].textContent=m; b[2].textContent=s; } setTimeout(tick,1000);} tick();
  })();
  </script>
  <?php return ob_get_clean();
});
