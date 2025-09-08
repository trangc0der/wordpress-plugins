<?php
if ( ! defined('ABSPATH') ) exit;

add_shortcode('md_flash_sale_campaign', function($atts=array()){
  if ( ! class_exists('WooCommerce') ) return '';
  $a = shortcode_atts(array(
    'id' => 0,
    // optional
    'show_countdown' => '1',
    'show_banner'    => '1',
    'autoplay'       => '1',   // chỉ áp dụng khi slot kiểu slider
    'speed'          => '4000', // ms
    'layout'         => '',    // grid | list | slider
    'columns'        => ''     // số cột hiển thị
  ), $atts, 'md_flash_sale_campaign');

  $slot_id = intval($a['id']);

  if (!$slot_id && isset($_GET['id'])) {
    $slot_id = intval($_GET['id']);
  }
  if(!$slot_id){
      $now = current_time('timestamp');
      $slots = get_posts(array(
          'post_type'      => 'mdfs_slot',
          'post_status'    => 'publish',
          'posts_per_page' => -1,
          'meta_query'     => array(
              array(
                  'key'     => '_mdfs_time_from',
                  'value'   => $now,
                  'compare' => '<=',
                  'type'    => 'NUMERIC'
              ),
              array(
                  'key'     => '_mdfs_time_to',
                  'value'   => $now,
                  'compare' => '>=',
                  'type'    => 'NUMERIC'
              )
          )
      ));
      
      if(empty($slots)) return '';

      ob_start();
      foreach($slots as $slot){
          // Gọi lại chính hàm render 1 campaign
          echo do_shortcode('[md_flash_sale_campaign id="'.$slot->ID.'"]');
      }
      return ob_get_clean();
  }
  
  if(!$slot_id) return '';

  $slot = get_post($slot_id);
  if( ! $slot || $slot->post_type !== 'mdfs_slot') return '';

  // Test mode: chỉ admin/QA mới xem
  if( get_post_meta($slot_id,'_mdfs_test_mode',true) && ! current_user_can('mdfs_preview') ){
    return '';
  }

  $type  = get_post_meta($slot_id,'_mdfs_type',true); if(!$type) $type = 'grid';
  $cols  = (int) get_post_meta($slot_id,'_mdfs_cols',true); if(!$cols) $cols = 6;

  // Ưu tiên giá trị shortcode nếu có
  if ( !empty($a['layout']) ) {
      $type = sanitize_text_field($a['layout']);
  }
  if ( !empty($a['columns']) ) {
      $cols = max(1, intval($a['columns']));
  }

  $to    = (int) get_post_meta($slot_id,'_mdfs_time_to',true);
  $view  = get_post_meta($slot_id,'_mdfs_view_all',true); if(!$view) $view = wc_get_page_permalink('shop');

  $thumb_id = (int) get_post_meta($slot_id,'_mdfs_thumbnail_id',true);
  $content  = get_post_meta($slot_id,'_mdfs_content',true);

  // Lấy sản phẩm của slot
  $rows = get_post_meta($slot_id,'_mdfs_products',true); if(!is_array($rows)) $rows=array();
  $remain_map = (array) get_post_meta($slot_id,'_mdfs_quota_remaining',true);

  $ids = array();
  foreach($rows as $r){ $ids[] = intval( !empty($r['variation_id']) ? $r['variation_id'] : $r['product_id'] ); }
  $ids = array_values(array_filter(array_unique($ids)));
  if(empty($ids)) return '';

  $q = new WP_Query(array(
    'post_type'      => 'product',
    'post__in'       => $ids,
    'orderby'        => 'post__in',
    'posts_per_page' => -1
  ));

  ob_start(); ?>
  <div class="mdfs-tabs-wrap mdfs-campaign" id="mdfs-campaign-<?php echo esc_attr($slot_id); ?>">
    <div class="mdfs-section-head">
      <div class="mdfs-title">
        <div class="bolt">⚡</div><span>FLASH SALE</span>
        <?php if( intval($a['show_countdown']) && $to>0 ): ?>
          <div class="mdfs-countdown" data-end="<?php echo esc_attr($to); ?>">
            <div class="mdfs-timebox">00</div><div class="mdfs-timebox">00</div><div class="mdfs-timebox">00</div>
          </div>
        <?php endif; ?>
      </div>
      <div class="mdfs-cta"><a class="mdfs-seeall" href="<?php echo esc_url($view); ?>">Xem tất cả »</a></div>
    </div>

    <?php if( intval($a['show_banner']) && $thumb_id ): ?>
      <div class="mdfs-slot-banner">
        <?php echo wp_get_attachment_image($thumb_id, 'large', false, array('class'=>'mdfs-slot-thumb')); ?>
      </div>
    <?php endif; ?>

    <?php if( $content ){ echo '<div class="mdfs-slot-content">'.apply_filters('the_content',$content).'</div>'; } ?>

    <?php if( $type === 'slider' ): ?>
      <div class="mdfs-slider">
        <button class="mdfs-nav prev" aria-label="prev">‹</button>
        <div class="mdfs-track mdfs-c-track">
          <?php while($q->have_posts()): $q->the_post(); global $post;
            $pobj = wc_get_product($post->ID);
            $is_var = $pobj && $pobj->is_type('variation');
            $pid = $is_var ? $pobj->get_parent_id() : $pobj->get_id();
            $vid = $is_var ? $pobj->get_id() : 0;

            $reg  = (float) $pobj->get_regular_price();
            $sale = (float) ($pobj->get_sale_price() !== '' ? $pobj->get_sale_price() : $reg);
            foreach($rows as $_r){
              $match = ((int)($_r['variation_id']??0) === $vid) || (!$vid && (int)($_r['product_id']??0)===$pid);
              if($match && $_r['sale_price']!==''){ $sale = (float) $_r['sale_price']; $reg = $reg ?: $sale; break; }
            }
            $disc = ($reg>0 && $sale<$reg) ? max(0,min(99, round((1-($sale/$reg))*100))) : 0;
            $quota=0; foreach($rows as $_r){ $m=((int)($_r['variation_id']??0) === $vid) || (!$vid && (int)($_r['product_id']??0)===$pid); if($m){ $quota=(int)$_r['quota']; break; } }
            $keyRemain = $vid? $pid.':'.$vid : $pid;
            $remain = isset($remain_map[$keyRemain]) ? (int)$remain_map[$keyRemain] : $quota;
            $sold = max(0, $quota-$remain); $pct = $quota>0 ? min(100,round($sold*100/$quota)) : 0;
          ?>
          <a class="mdfs-card mdfs-item" href="<?php the_permalink(); ?>">
            <div class="mdfs-thumb">
              <?php echo get_the_post_thumbnail($post->ID,'woocommerce_thumbnail',['loading'=>'lazy']); ?>
              <div class="mdfs-badge">Yêu thích</div><?php if($disc>0): ?><div class="mdfs-ribbon">-<?php echo $disc; ?>%</div><?php endif; ?><div class="mdfs-nine-tag">99</div>
            </div>
            <div class="mdfs-meta">
              <div class="mdfs-name"><?php the_title(); ?></div>
              <div class="mdfs-price"><div class="mdfs-sale"><?php echo wc_price($sale); ?></div><?php if($reg && $sale<$reg): ?><div class="mdfs-regular"><?php echo wc_price($reg); ?></div><?php endif; ?></div>
              <?php if($quota>0): ?><div class="mdfs-progress mdfs--shopee"><span style="width:<?php echo esc_attr($pct); ?>%"></span><div class="mdfs-chip"><?php echo $pct>=100?'HẾT HÀNG':'ĐANG BÁN CHẠY'; ?></div></div><?php endif; ?>
            </div>
          </a>
          <?php endwhile; wp_reset_postdata(); ?>
        </div>
        <button class="mdfs-nav next" aria-label="next">›</button>
      </div>
    <?php else: ?>
      <div class="mdfs-grid" style="grid-template-columns:repeat(<?php echo esc_attr($cols); ?>,1fr)">
        <?php while($q->have_posts()): $q->the_post(); global $post;
          $pobj = wc_get_product($post->ID);
          $is_var = $pobj && $pobj->is_type('variation');
          $pid = $is_var ? $pobj->get_parent_id() : $pobj->get_id();
          $vid = $is_var ? $pobj->get_id() : 0;

          $reg  = (float) $pobj->get_regular_price();
          $sale = (float) ($pobj->get_sale_price() !== '' ? $pobj->get_sale_price() : $reg);
          foreach($rows as $_r){
            $match = ((int)($_r['variation_id']??0) === $vid) || (!$vid && (int)($_r['product_id']??0)===$pid);
            if($match && $_r['sale_price']!==''){ $sale = (float) $_r['sale_price']; $reg = $reg ?: $sale; break; }
          }
          $disc = ($reg>0 && $sale<$reg) ? max(0,min(99, round((1-($sale/$reg))*100))) : 0;
          $quota=0; foreach($rows as $_r){ $m=((int)($_r['variation_id']??0) === $vid) || (!$vid && (int)($_r['product_id']??0)===$pid); if($m){ $quota=(int)$_r['quota']; break; } }
          $keyRemain = $vid? $pid.':'.$vid : $pid;
          $remain = isset($remain_map[$keyRemain]) ? (int)$remain_map[$keyRemain] : $quota;
          $sold = max(0, $quota-$remain); $pct = $quota>0 ? min(100,round($sold*100/$quota)) : 0;
        ?>
        <a class="mdfs-card" href="<?php the_permalink(); ?>">
          <div class="mdfs-thumb">
            <?php echo get_the_post_thumbnail($post->ID,'woocommerce_thumbnail',['loading'=>'lazy']); ?>
            <div class="mdfs-badge">Yêu thích</div><?php if($disc>0): ?><div class="mdfs-ribbon">-<?php echo $disc; ?>%</div><?php endif; ?><div class="mdfs-nine-tag">99</div>
          </div>
          <div class="mdfs-meta">
            <div class="mdfs-name"><?php the_title(); ?></div>
            <div class="mdfs-price"><div class="mdfs-sale"><?php echo wc_price($sale); ?></div><?php if($reg && $sale<$reg): ?><div class="mdfs-regular"><?php echo wc_price($reg); ?></div><?php endif; ?></div>
            <?php if($quota>0): ?><div class="mdfs-progress mdfs--shopee"><span style="width:<?php echo esc_attr($pct); ?>%"></span><div class="mdfs-chip"><?php echo $pct>=100?'HẾT HÀNG':'ĐANG BÁN CHẠY'; ?></div></div><?php endif; ?>
          </div>
        </a>
        <?php endwhile; wp_reset_postdata(); ?>
      </div>
    <?php endif; ?>
  </div>

  <script>
  (function(){
    var root = document.getElementById('mdfs-campaign-<?php echo esc_js($slot_id); ?>');
    if(!root) return;

    // Countdown
    var cd = root.querySelector('.mdfs-countdown');
    function tick(){
      if(!cd) return;
      var to = parseInt(cd.getAttribute('data-end')||'0',10);
      var now = Math.floor(Date.now()/1000);
      var r = Math.max(0, to-now);
      var h=('0'+Math.floor(r/3600)).slice(-2), m=('0'+Math.floor((r%3600)/60)).slice(-2), s=('0'+(r%60)).slice(-2);
      var b=cd.querySelectorAll('.mdfs-timebox'); if(b.length>=3){ b[0].textContent=h; b[1].textContent=m; b[2].textContent=s; }
      setTimeout(tick,1000);
    } tick();

    // Slider controls (nếu có)
    var track = root.querySelector('.mdfs-c-track');
    if(track){
      var prev = root.querySelector('.mdfs-nav.prev'), next = root.querySelector('.mdfs-nav.next');
      function step(){ var it=track.querySelector('.mdfs-item'); return it ? (it.getBoundingClientRect().width + 14) : 240; }
      prev && prev.addEventListener('click', function(){ track.scrollBy({left:-step(),behavior:'smooth'}); });
      next && next.addEventListener('click', function(){ track.scrollBy({left: step(),behavior:'smooth'}); });
      var autoplay = <?php echo intval($a['autoplay']) ? 1 : 0; ?>;
      var speed    = <?php echo max(1000, intval($a['speed'])); ?>;
      if(autoplay){ setInterval(function(){ if(!document.hidden){ track.scrollBy({left:step(),behavior:'smooth'}); } }, speed); }
    }
  })();
  </script>
  <?php
  return ob_get_clean();
});
