<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Lấy các slot trong ngày
 */
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
      'relation' => 'AND',
      array(
        'key'     => '_mdfs_time_from',
        'value'   => $end,
        'compare' => '<=',
        'type'    => 'NUMERIC'
      ),
      array(
        'key'     => '_mdfs_time_to',
        'value'   => $start,
        'compare' => '>=',
        'type'    => 'NUMERIC'
      ),
    )
  ));

  return $q->posts;
}


add_shortcode('md_flash_sale_shopee', function($atts=array()){
  if ( ! class_exists('WooCommerce') ) return '';

  $a = shortcode_atts(array(
    'layout'  => 'grid', // grid | slider
    'columns' => 6
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
        <button class="slot-time <?php echo $active; ?>" 
                data-slot="#slot-<?php echo $slot->ID; ?>" 
                data-end="<?php echo esc_attr($to); ?>">
          <?php echo date('H:i',$from); ?><br>
          <small><?php echo $active?'Đang diễn ra':'Sắp diễn ra'; ?></small>
        </button>
      <?php endforeach; ?>
    </div>

    <div class="mdfs-shopee-bodies">
      <?php foreach($slots as $i=>$slot):
        $rows = get_post_meta($slot->ID,'_mdfs_products',true); 
        if(!is_array($rows)) $rows=array();
        $remain_map = (array)get_post_meta($slot->ID,'_mdfs_quota_remaining',true);

        $ids = array_map(function($r){ return intval($r['variation_id'] ?: $r['product_id']); }, $rows);
        if(empty($ids)) continue;

        $q = new WP_Query(array(
          'post_type'      => array('product','product_variation'),
          'post__in'       => $ids,
          'orderby'        => 'post__in',
          'posts_per_page' => -1
        ));
      ?>
      <div class="slot-body <?php echo $i===0?'active':''; ?>" id="slot-<?php echo $slot->ID; ?>">
        <?php if($thumb_id=(int)get_post_meta($slot->ID,'_mdfs_thumbnail_id',true)): ?>
          <div class="banner"><?php echo wp_get_attachment_image($thumb_id,'large'); ?></div>
        <?php endif; ?>

        <div class="<?php echo $a['layout']==='slider'?'mdfs-slider':'grid'; ?>" 
             style="<?php echo $a['layout']==='grid'?'grid-template-columns:repeat('.intval($a['columns']).',1fr)':''; ?>">

          <?php while($q->have_posts()): $q->the_post(); global $post;
            $pobj = wc_get_product($post->ID);
            if(!$pobj) continue;

            $prod_id = $pobj->get_id();
            $row = current(array_filter($rows,function($x) use($prod_id){ 
              return intval($x['variation_id'] ?: $x['product_id']) === $prod_id; 
            }));

            $reg  = (float) $pobj->get_regular_price();
            $sale = isset($row['sale_price']) && $row['sale_price']!=='' ? (float)$row['sale_price'] : $reg;
            $disc = ($reg>0 && $sale<$reg) ? round((1-($sale/$reg))*100) : 0;

            // quota còn lại
            $quota = isset($row['quota']) ? (int)$row['quota'] : 0;
            $remain_key = $row['variation_id'] ? $row['product_id'].':'.$row['variation_id'] : (string)$row['product_id'];
            $remain = isset($remain_map[$remain_key]) ? (int)$remain_map[$remain_key] : $quota;
            $sold   = max(0,$quota-$remain);
            $percent= ($quota>0)? round(($sold/$quota)*100):0;

            // ảnh
            $thumb = !empty($row['thumb']) ? esc_url($row['thumb']) : get_the_post_thumbnail_url($post->ID,'woocommerce_thumbnail');
            if(empty($thumb)) $thumb = wc_placeholder_img_src();
          ?>
          <a class="card mdfs-item" href="<?php the_permalink(); ?>">
            <div class="thumb">
              <img src="<?php echo $thumb; ?>" alt="<?php the_title_attribute(); ?>" loading="lazy" />
              <?php if($disc>0): ?><div class="ribbon">-<?php echo $disc; ?>%</div><?php endif; ?>
            </div>
            <div class="meta">
              <div class="name"><?php the_title(); ?></div>
              <div class="price">
                <span class="sale"><?php echo wc_price($sale); ?></span>
                <?php if($reg>$sale): ?><span class="reg"><?php echo wc_price($reg); ?></span><?php endif; ?>
              </div>
              <?php if($quota>0): ?>
              <div class="progress">
                <div class="bar" style="width:<?php echo $percent; ?>%"></div>
                <span class="text"><?php echo $remain>0?"Còn $remain":"Hết hàng"; ?></span>
              </div>
              <?php endif; ?>
            </div>
          </a>
          <?php endwhile; wp_reset_postdata(); ?>
        </div>
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

    // đồng bộ timezone WordPress
    var phpNow = <?php echo (int) current_time('timestamp'); ?>;
    var jsNow  = Math.floor(Date.now()/1000);
    var offset = phpNow - jsNow;
    
    function tick(){
      var cd=root.querySelector('#mdfs-shopee-countdown'); if(!cd) return;
      var to=parseInt(cd.getAttribute('data-end')||'0',10);
      var now=Math.floor(Date.now()/1000)+offset;
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

  <style>
  .mdfs-shopee-wrap{margin:20px 0;}
  .mdfs-shopee-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;}
  .mdfs-shopee-slots{margin-bottom:15px;}
  .slot-time{margin-right:5px;padding:6px 10px;border:1px solid #ddd;background:#fff;cursor:pointer;}
  .slot-time.active{background:#f33;color:#fff;}
  .slot-body{display:none;}
  .slot-body.active{display:block;}
  .card{display:block;background:#fff;border:1px solid #eee;border-radius:6px;overflow:hidden;margin:5px;padding:10px;text-align:center;}
  .thumb img{max-width:100%;height:auto;}
  .ribbon{position:absolute;top:5px;right:5px;background:#f33;color:#fff;padding:2px 6px;font-size:12px;border-radius:3px;}
  .price{margin-top:5px;}
  .price .sale{color:#f33;font-weight:bold;margin-right:5px;}
  .price .reg{text-decoration:line-through;color:#888;font-size:12px;}
  .progress{position:relative;height:14px;background:var(--mdfs-accent);border-radius:7px;overflow:hidden;margin-top:6px;}
  .progress .bar{height:100%;background:rgba(238,77,45,.12);}
  .progress .text{position:absolute;top:0;left:0;width:100%;height:100%;font-size:11px;line-height:14px;text-align:center;color:#fff;}
  </style>
  <?php
  return ob_get_clean();
});
