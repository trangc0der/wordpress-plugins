<?php
if ( ! defined('ABSPATH') ) exit;

function mdfs_rt_collect_active_products(){
  $now = current_time('timestamp');
  $q = new WP_Query(array(
    'post_type'      => 'mdfs_slot',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'meta_query'     => array(
      array('key'=>'_mdfs_time_from','value'=>$now,'compare'=>'<=','type'=>'NUMERIC'),
      array('key'=>'_mdfs_time_to','value'=>$now,'compare'=>'>=','type'=>'NUMERIC'),
    ),
  ));
  $map = array();
  foreach ($q->posts as $slot){
    if(get_post_meta($slot->ID,'_mdfs_test_mode',true) && !current_user_can('mdfs_preview')) continue;
    $to   = (int) get_post_meta($slot->ID,'_mdfs_time_to',true);
    $rows = get_post_meta($slot->ID,'_mdfs_products',true); if(!is_array($rows)) $rows=array();
    $remain_map = (array) get_post_meta($slot->ID,'_mdfs_quota_remaining',true);
    foreach ($rows as $r){
      $pid = intval($r['product_id'] ?? 0); $vid = intval($r['variation_id'] ?? 0);
      if(!$pid) continue;
      $key = $vid ? $pid.':'.$vid : $pid;
      $row = array(
        'slot_id'=>$slot->ID,
        'slot_end'=>$to,
        'pid'=>$pid,
        'vid'=>$vid,
        'price'=>$r['sale_price'] ?? '',
        'quota'=>(int)($r['quota'] ?? 0),
        'remain'=>(int)($remain_map[$key] ?? ($r['quota'] ?? 0)),
        'thumb'=>$r['thumb'] ?? '' // 👈 thêm thumb vào map
      );
      if( ! isset($map[$key]) ){ 
        $map[$key] = $row; 
      } else {
        $best = $map[$key];
        if( $to < $best['slot_end'] || ($to==$best['slot_end'] && $row['price']!=='' && $row['price'] < $best['price']) ){
          $map[$key] = $row;
        }
      }
    }
  }
  return $map;
}

add_shortcode('md_flash_sale_slider', function($atts=array()){
  if ( ! class_exists('WooCommerce') ) return '';
  $atts = shortcode_atts(array(
    'limit'=>60,
    'view_all'=>home_url('/flash_sale/'),
    'autoplay'=>'1',
    'speed'=>4000
  ), $atts, 'md_flash_sale_slider');

  $map = mdfs_rt_collect_active_products();
  if (empty($map)) return '';

  $ids = array(); 
  foreach($map as $k=>$r){ $ids[] = $r['vid'] ? $r['vid'] : $r['pid']; }

  $q = new WP_Query(array(
    'post_type'=>'product',
    'post__in'=>$ids,
    'orderby'=>'post__in',
    'posts_per_page'=>(int)$atts['limit']
  ));

  $nearest_end = 0; 
  foreach($map as $r){ 
    $t=$r['slot_end']; 
    if(!$nearest_end || ($t && $t<$nearest_end)) $nearest_end=$t; 
  }

  ob_start(); ?>
  <div class="mdfs-tabs-wrap mdfs-slider-wrap">
    <div class="mdfs-section-head">
      <div class="mdfs-title"><div class="bolt">⚡</div><span>FLASH SALE</span>
        <div class="mdfs-countdown" id="mdfs-slider-countdown" data-end="<?php echo esc_attr($nearest_end); ?>"><div class="mdfs-timebox">00</div><div class="mdfs-timebox">00</div><div class="mdfs-timebox">00</div></div>
      </div>
      <div class="mdfs-cta"><a class="mdfs-seeall" href="<?php echo esc_url($atts['view_all']); ?>">Xem tất cả »</a></div>
    </div>
    <div class="mdfs-slider">
      <button class="mdfs-nav prev" aria-label="prev">‹</button>
      <div class="mdfs-track" id="mdfs-track">
        <?php while($q->have_posts()): $q->the_post(); global $post;
          $pobj = wc_get_product($post->ID); $is_var=$pobj && $pobj->is_type('variation');
          $pid = $is_var? $pobj->get_parent_id() : $pobj->get_id();
          $vid = $is_var? $pobj->get_id() : 0;
          $key = $vid? $pid.':'.$vid : $pid;
          $reg = (float)$pobj->get_regular_price();
          $sale = isset($map[$key]['price']) && $map[$key]['price']!=='' ? (float)$map[$key]['price'] : ((float)$pobj->get_sale_price() ?: $reg);
          $disc = ($reg>0 && $sale<$reg) ? max(0,min(99, round((1-($sale/$reg))*100))) : 0;
          $quota = (int)($map[$key]['quota'] ?? 0);
          $remain= (int)($map[$key]['remain'] ?? $quota);
          $sold = max(0,$quota-$remain); $pct=$quota>0?min(100,round($sold*100/$quota)):0;

          // ✅ Thumbnail: ưu tiên thumb riêng, fallback sản phẩm, cuối cùng placeholder
          if (!empty($map[$key]['thumb'])) {
            $thumb = esc_url($map[$key]['thumb']);
          } else {
            $thumb = get_the_post_thumbnail_url($post->ID,'woocommerce_thumbnail');
            if (empty($thumb)) {
              $thumb = wc_placeholder_img_src();
            }
          }
        ?>
        <a class="mdfs-card mdfs-item" href="<?php the_permalink(); ?>">
          <div class="mdfs-thumb">
            <img src="<?php echo $thumb; ?>" alt="<?php the_title_attribute(); ?>" loading="lazy" />
            <div class="mdfs-badge">Yêu thích</div>
            <?php if($disc>0): ?><div class="mdfs-ribbon">-<?php echo $disc; ?>%</div><?php endif; ?>
            <div class="mdfs-nine-tag">99</div>
          </div>
          <div class="mdfs-meta">
            <div class="mdfs-name"><?php the_title(); ?></div>
            <div class="mdfs-price">
              <div class="mdfs-sale"><?php echo wc_price($sale); ?></div>
              <?php if($reg && $sale < $reg): ?><div class="mdfs-regular"><?php echo wc_price($reg); ?></div><?php endif; ?>
            </div>
            <?php if($quota>0): ?>
              <div class="mdfs-progress mdfs--shopee">
                <span style="width:<?php echo esc_attr($pct); ?>%"></span>
                <div class="mdfs-chip"><?php echo $pct>=100?'HẾT HÀNG':'ĐANG BÁN CHẠY'; ?></div>
              </div>
            <?php endif; ?>
          </div>
        </a>
        <?php endwhile; wp_reset_postdata(); ?>
      </div>
      <button class="mdfs-nav next" aria-label="next">›</button>
    </div>
  </div>
  <script>
  (function(){
    var wrap=document.querySelector('.mdfs-slider-wrap'); if(!wrap) return;
    var track=wrap.querySelector('#mdfs-track');
    var prev=wrap.querySelector('.mdfs-nav.prev'), next=wrap.querySelector('.mdfs-nav.next');
    function step(){ var it=track.querySelector('.mdfs-item'); return it? it.getBoundingClientRect().width + 14 : 240; }
    prev && prev.addEventListener('click',function(){ track.scrollBy({left:-step(),behavior:'smooth'}); });
    next && next.addEventListener('click',function(){ track.scrollBy({left: step(),behavior:'smooth'}); });
    var head=document.getElementById('mdfs-slider-countdown');
    function tick(){ if(!head) return; var to=parseInt(head.getAttribute('data-end')||'0',10);
      var now=Math.floor(Date.now()/1000); var r=Math.max(0,to-now);
      var b=head.querySelectorAll('.mdfs-timebox'); 
      var h=('0'+Math.floor(r/3600)).slice(-2),m=('0'+Math.floor((r%3600)/60)).slice(-2),s=('0'+(r%60)).slice(-2);
      if(b.length>=3){ b[0].textContent=h; b[1].textContent=m; b[2].textContent=s; } setTimeout(tick,1000); } tick();
    var autoplay = <?php echo intval($atts['autoplay']) ? 1 : 0; ?>;
    var speed    = <?php echo max(1000, intval($atts['speed'])); ?>;
    if(autoplay){ setInterval(function(){ if(!document.hidden){ track.scrollBy({left:step(),behavior:'smooth'}); } }, speed); }
  })();
  </script>
  <?php return ob_get_clean();
});
