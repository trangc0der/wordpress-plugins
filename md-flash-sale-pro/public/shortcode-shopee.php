<?php
if ( ! defined('ABSPATH') ) exit;

// Lấy danh sách slot hôm nay
function mdfs_shopee_get_slots_today(){
  $now   = current_time('timestamp');
  $start = strtotime('today 00:00:00', $now);
  $end   = strtotime('today 23:59:59', $now);

  $q = new WP_Query(array(
    'post_type'      => 'mdfs_slot',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'meta_value_num',
    'meta_key'       => '_mdfs_time_from',
    'order'          => 'ASC',
    'meta_query'     => array(
      array(
        'key'     => '_mdfs_time_from',
        'value'   => $start,
        'compare' => '>=',
        'type'    => 'NUMERIC'
      ),
      array(
        'key'     => '_mdfs_time_to',
        'value'   => $end,
        'compare' => '<=',
        'type'    => 'NUMERIC'
      )
    )
  ));

  return $q->posts;
}

add_shortcode('md_flash_sale_shopee', function($atts=array()){
  if ( ! class_exists('WooCommerce') ) return '';

  $a = shortcode_atts(array(
    'layout'  => 'grid', // grid | slider | list
    'columns' => 6       // số cột khi grid
  ), $atts, 'md_flash_sale_shopee');

  $slots = mdfs_shopee_get_slots_today();
  if(empty($slots)) return '';

  ob_start(); ?>
  <div class="mdfs-shopee-wrap">
    <div class="mdfs-shopee-head">
      <div class="title">FLASH SALE</div>
      <div class="countdown" id="mdfs-shopee-countdown">
        <span class="timebox">00</span>:<span class="timebox">00</span>:<span class="timebox">00</span>
      </div>
    </div>

    <div class="mdfs-shopee-slots">
      <?php foreach($slots as $i=>$slot):
        $from   = (int)get_post_meta($slot->ID,'_mdfs_time_from',true);
        $to     = (int)get_post_meta($slot->ID,'_mdfs_time_to',true);
        $active = ($from <= current_time('timestamp') && $to >= current_time('timestamp')) ? 'active' : '';
      ?>
        <button class="slot-time <?php echo $active; ?>" data-slot="#slot-<?php echo $slot->ID; ?>" data-end="<?php echo esc_attr($to); ?>">
          <?php echo date('H:i',$from); ?><br>
          <small><?php echo $active?'Đang diễn ra':'Sắp diễn ra'; ?></small>
        </button>
      <?php endforeach; ?>
    </div>

    <div class="mdfs-shopee-bodies">
      <?php foreach($slots as $i=>$slot):
        $rows = get_post_meta($slot->ID,'_mdfs_products',true); if(!is_array($rows)) $rows=array();
        $remain_map = (array)get_post_meta($slot->ID,'_mdfs_quota_remaining',true);

        $ids = array();
        foreach($rows as $r){ $ids[] = intval($r['variation_id'] ?: $r['product_id']); }
        if(empty($ids)) continue;

        $q = new WP_Query(array(
          'post_type'      => 'product',
          'post__in'       => $ids,
          'orderby'        => 'post__in',
          'posts_per_page' => -1
        ));
      ?>
      <div class="slot-body <?php echo $i===0?'active':''; ?>" id="slot-<?php echo $slot->ID; ?>">
        <?php if($thumb_id=(int)get_post_meta($slot->ID,'_mdfs_thumbnail_id',true)): ?>
          <div class="banner"><?php echo wp_get_attachment_image($thumb_id,'large'); ?></div>
        <?php endif; ?>

        <?php if($a['layout']==='slider'): ?>
          <div class="mdfs-slider">
            <div class="mdfs-track">
              <?php while($q->have_posts()): $q->the_post(); global $post;
                $pobj = wc_get_product($post->ID);
                $reg  = (float) $pobj->get_regular_price();
                $sale = (float) ($pobj->get_sale_price()!=='' ? $pobj->get_sale_price() : $reg);
                $disc = ($reg>0 && $sale<$reg) ? round((1-($sale/$reg))*100) : 0;

                // ✅ lấy thumb ưu tiên
                $prod_id = $pobj->get_id();
                $row     = current(array_filter($rows,function($x) use($prod_id){ 
                  return intval($x['variation_id'] ?: $x['product_id']) === $prod_id; 
                }));
                if(!empty($row['thumb'])){
                  $thumb = esc_url($row['thumb']);
                } else {
                  $thumb = get_the_post_thumbnail_url($post->ID,'woocommerce_thumbnail');
                  if(empty($thumb)) $thumb = wc_placeholder_img_src();
                }
              ?>
              <a class="card mdfs-item" href="<?php the_permalink(); ?>">
                <div class="thumb">
                  <img src="<?php echo $thumb; ?>" alt="<?php the_title_attribute(); ?>" loading="lazy" />
                  <?php if($disc>0): ?><div class="ribbon">-<?php echo $disc; ?>%</div><?php endif; ?>
                </div>
                <div class="meta">
                  <div class="name"><?php the_title(); ?></div>
                  <div class="price"><span class="sale"><?php echo wc_price($sale); ?></span><?php if($reg>$sale): ?><span class="reg"><?php echo wc_price($reg); ?></span><?php endif; ?></div>
                </div>
              </a>
              <?php endwhile; wp_reset_postdata(); ?>
            </div>
          </div>

        <?php else: ?>
          <div class="grid layout-<?php echo esc_attr($a['layout']); ?>" style="grid-template-columns:repeat(<?php echo intval($a['columns']); ?>,1fr)">
            <?php while($q->have_posts()): $q->the_post(); global $post;
              $pobj = wc_get_product($post->ID);
              $reg  = (float) $pobj->get_regular_price();
              $sale = (float) ($pobj->get_sale_price()!=='' ? $pobj->get_sale_price() : $reg);
              $disc = ($reg>0 && $sale<$reg) ? round((1-($sale/$reg))*100) : 0;

              // ✅ lấy thumb ưu tiên
              $prod_id = $pobj->get_id();
              $row     = current(array_filter($rows,function($x) use($prod_id){ 
                return intval($x['variation_id'] ?: $x['product_id']) === $prod_id; 
              }));
              if(!empty($row['thumb'])){
                $thumb = esc_url($row['thumb']);
              } else {
                $thumb = get_the_post_thumbnail_url($post->ID,'woocommerce_thumbnail');
                if(empty($thumb)) $thumb = wc_placeholder_img_src();
              }
            ?>
            <a class="card" href="<?php the_permalink(); ?>">
              <div class="thumb">
                <img src="<?php echo $thumb; ?>" alt="<?php the_title_attribute(); ?>" loading="lazy" />
                <?php if($disc>0): ?><div class="ribbon">-<?php echo $disc; ?>%</div><?php endif; ?>
              </div>
              <div class="meta">
                <div class="name"><?php the_title(); ?></div>
                <div class="price"><span class="sale"><?php echo wc_price($sale); ?></span><?php if($reg>$sale): ?><span class="reg"><?php echo wc_price($reg); ?></span><?php endif; ?></div>
              </div>
            </a>
            <?php endwhile; wp_reset_postdata(); ?>
          </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <script>
  (function(){
    var root=document.querySelector('.mdfs-shopee-wrap');
    if(!root) return;

    // Switch slot
    root.querySelectorAll('.slot-time').forEach(function(btn){
      btn.addEventListener('click',function(){
        root.querySelectorAll('.slot-time').forEach(x=>x.classList.remove('active'));
        btn.classList.add('active');
        var target=btn.getAttribute('data-slot');
        root.querySelectorAll('.slot-body').forEach(x=>x.classList.remove('active'));
        var pane=root.querySelector(target); if(pane) pane.classList.add('active');
        var to=parseInt(btn.getAttribute('data-end')||'0',10);
        var cd=root.querySelector('#mdfs-shopee-countdown');
        if(cd&&to>0){ cd.setAttribute('data-end',String(to)); }
      });
    });

    // Countdown
    function tick(){
      var cd=root.querySelector('#mdfs-shopee-countdown');
      if(!cd) return;
      var to=parseInt(cd.getAttribute('data-end')||'0',10);
      var now=Math.floor(Date.now()/1000);
      var r=Math.max(0,to-now);
      var h=('0'+Math.floor(r/3600)).slice(-2),
          m=('0'+Math.floor((r%3600)/60)).slice(-2),
          s=('0'+(r%60)).slice(-2);
      var b=cd.querySelectorAll('.timebox');
      if(b.length>=3){ b[0].textContent=h; b[1].textContent=m; b[2].textContent=s; }
      setTimeout(tick,1000);
    } tick();
  })();
  </script>
  <?php
  return ob_get_clean();
});
