<?php
if (!defined('ABSPATH')) {
    exit();
}

/**
 * Lấy slot đang chạy gần nhất + sản phẩm bên trong
 */
function mdfs_rt_collect_active_slot()
{
    $now = current_time('timestamp'); // WP timezone

    $slot_q = new WP_Query([
        'post_type' => 'mdfs_slot',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => '_mdfs_time_from',
                'value' => $now,
                'compare' => '<=',
                'type' => 'NUMERIC',
            ],
            [
                'key' => '_mdfs_time_to',
                'value' => $now,
                'compare' => '>=',
                'type' => 'NUMERIC',
            ],
        ],
    ]);

    if (!$slot_q->have_posts()) {
        return ['slot' => null, 'products' => [], 'deadline' => 0];
    }

    // Slot có deadline gần nhất
    $nearest_end = 0;
    $active_slot = null;
    foreach ($slot_q->posts as $slot) {
        $t = (int) get_post_meta($slot->ID, '_mdfs_time_to', true);
        if ($t > $now && ($nearest_end === 0 || $t < $nearest_end)) {
            $nearest_end = $t;
            $active_slot = $slot;
        }
    }
    if (!$active_slot) {
        return ['slot' => null, 'products' => [], 'deadline' => 0];
    }

    $slot_id = $active_slot->ID;
    $only_instock = (bool) get_post_meta($slot_id, '_mdfs_only_instock', true);
    $hide_oos     = (bool) get_post_meta($slot_id, '_mdfs_hide_oos', true);

    $rows = get_post_meta($slot_id, '_mdfs_products', true);
    if (!is_array($rows)) {
        $rows = [];
    }

    $map = [];
    foreach ($rows as $r) {
        $pid = (int) ($r['variation_id'] ?: $r['product_id']);
        if (!$pid) continue;

        $quota = (int) ($r['quota'] ?? 0);

        // Nếu bật chỉ hiển thị còn hàng và quota = 0 -> bỏ
        if ($only_instock && $quota <= 0) continue;

        $map[$pid] = [
            'slot_id'       => $slot_id,
            'slot_end'      => $nearest_end,
            'pid'           => (int) ($r['product_id'] ?? 0),
            'vid'           => (int) ($r['variation_id'] ?? 0),
            'sale_price'    => $r['sale_price'] ?? '',
            'regular_price' => $r['regular_price'] ?? '',
            'quota'         => $quota,
            'thumb'         => $r['thumb'] ?? '',
            'badge'         => $r['badges'] ?? '',
            'hide_oos'      => $hide_oos, // ✅ lưu cờ hide
        ];
    }

    return ['slot' => $active_slot, 'products' => $map, 'deadline' => $nearest_end];
}

/**
 * Tính số lượng đã bán của tất cả products trong slot (có cache)
 */
function mdfs_rt_get_sold_map($slot_id, $slot_from, $slot_to)
{
    $cache_key = 'mdfs_sold_map_' . $slot_id;
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }

    global $wpdb;
    $orders = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
         WHERE post_type = 'shop_order'
           AND post_status IN ('wc-processing','wc-completed')
           AND post_date >= %s
           AND post_date <= %s",
            gmdate('Y-m-d H:i:s', $slot_from),
            gmdate('Y-m-d H:i:s', $slot_to)
        )
    );

    $sold_map = [];
    if (!empty($orders)) {
        foreach ($orders as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) continue;

            foreach ($order->get_items() as $item) {
                $pid = $item->get_product_id();
                $vid = $item->get_variation_id();
                $key = $vid ?: $pid;

                if (!isset($sold_map[$key])) $sold_map[$key] = 0;
                $sold_map[$key] += (int) $item->get_quantity();
            }
        }
    }

    // Lưu cache 2 phút
    set_transient($cache_key, $sold_map, 120);

    return $sold_map;
}

/**
 * Shortcode slider
 */
add_shortcode('md_flash_sale_slider', function ($atts = []) {
    if (!class_exists('WooCommerce')) return '';

    $atts = shortcode_atts(
        [
            'limit'   => 60,
            'autoplay'=> '1',
            'speed'   => 4000,
        ],
        $atts,
        'md_flash_sale_slider'
    );

    $data     = mdfs_rt_collect_active_slot();
    $slot     = $data['slot'];
    $map      = $data['products'];
    $deadline = $data['deadline'];

    if (!$slot || empty($map)) return '';

    $slot_id   = $slot->ID;
    $cols      = (int) get_post_meta($slot_id, '_mdfs_cols', true) ?: 6;
    $view_all  = get_post_meta($slot_id, '_mdfs_view_all', true);
    $thumbnail = (int) get_post_meta($slot_id, '_mdfs_thumbnail_id', true);
    $content   = get_post_meta($slot_id, '_mdfs_content', true);

    $slot_from = (int) get_post_meta($slot_id, '_mdfs_time_from', true);
    $slot_to   = (int) get_post_meta($slot_id, '_mdfs_time_to', true);

    // ✅ Query sold count 1 lần + cache
    $sold_map = mdfs_rt_get_sold_map($slot_id, $slot_from, $slot_to);

    $ids = array_keys($map);
    $q = new WP_Query([
        'post_type' => ['product', 'product_variation'],
        'post__in' => $ids,
        'orderby' => 'post__in',
        'posts_per_page' => (int) $atts['limit'],
    ]);

    ob_start();
    ?>
    <div class="mdfs-tabs-wrap mdfs-slider-wrap cols-<?php echo esc_attr($cols); ?>">
        <div class="mdfs-section-head">
            <div class="mdfs-title">
                <div class="bolt">⚡</div><span>FLASH SALE</span>
                <?php if ($deadline): ?>
                <div class="mdfs-countdown" id="mdfs-slider-countdown" 
                     data-end="<?php echo esc_attr($deadline); ?>"
                     data-now="<?php echo esc_attr(current_time('timestamp')); ?>">
                    <div class="mdfs-timebox">00</div>
                    <div class="mdfs-timebox">00</div>
                    <div class="mdfs-timebox">00</div>
                </div>
                <?php endif; ?>
            </div>
            <div class="mdfs-cta">
                <?php if ($view_all): ?>
                    <a class="mdfs-seeall" href="<?php echo esc_url($view_all); ?>">Xem tất cả »</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($thumbnail): ?>
            <div class="mdfs-thumb"><?php echo wp_get_attachment_image($thumbnail, 'large'); ?></div>
        <?php endif; ?>
        <?php if ($content): ?>
            <div class="mdfs-content"><?php echo wp_kses_post($content); ?></div>
        <?php endif; ?>

        <div class="mdfs-slider">
            <button class="mdfs-nav prev" aria-label="prev">‹</button>
            <div class="mdfs-track" id="mdfs-track">
                <?php
                while ($q->have_posts()):
                    $q->the_post();
                    $p = wc_get_product(get_the_ID());
                    if (!$p) continue;

                    $id   = $p->get_id();
                    $data = $map[$id] ?? null;
                    if (!$data) continue;

                    $quota  = (int) $data['quota'];
                    $sold   = $sold_map[$id] ?? 0;
                    $remain = max(0, $quota - $sold);
                    $instock= $remain > 0;

                    // ✅ Nếu bật hide_oos và hết hàng thì bỏ luôn
                    if ($data['hide_oos'] && !$instock) continue;

                    $curr = $data['sale_price'] !== '' ? (float) $data['sale_price'] : $p->get_price();
                    $reg  = $data['regular_price'] !== '' ? (float) $data['regular_price'] : $p->get_regular_price();
                    $disc = $reg > 0 && $curr < $reg ? round((1 - $curr / $reg) * 100) : 0;

                    $thumb = $data['thumb'] ?: get_the_post_thumbnail_url($id, 'woocommerce_thumbnail');
                    if (!$thumb) $thumb = wc_placeholder_img_src();

                    $pct = $quota > 0 ? min(100, round(($sold * 100) / $quota)) : 0;

                    // Status text
                    if (!$instock) {
                        $status = 'ĐÃ BÁN HẾT';
                    } elseif ($sold == 0) {
                        $status = 'ĐANG BÁN CHẠY';
                    } elseif ($sold >= $quota / 3) {
                        $status = 'ĐÃ BÁN ' . $sold;
                    } elseif ($remain <= $quota / 3) {
                        $status = 'CHỈ CÒN ' . $remain;
                    } else {
                        $status = 'ĐANG BÁN CHẠY';
                    }
                    ?>
                <a class="mdfs-card mdfs-item <?php echo !$instock ? 'mdfs-outofstock' : ''; ?>" href="<?php the_permalink(); ?>">
                    <div class="mdfs-thumb">
                        <img src="<?php echo esc_url($thumb); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy" />
                        <?php if ($disc > 0): ?><div class="mdfs-ribbon">-<?php echo $disc; ?>%</div><?php endif; ?>
                    </div>
                    <div class="mdfs-meta">
                        <div class="mdfs-name"><?php the_title(); ?></div>
                        <div class="mdfs-price">
                            <div class="mdfs-sale"><?php echo wc_price($curr); ?></div>
                            <?php if ($reg && $curr < $reg): ?>
                                <div class="mdfs-regular"><?php echo wc_price($reg); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php if ($quota > 0): ?>
                            <div class="mdfs-progress mdfs--shopee">
                                <span style="width:<?php echo esc_attr($pct); ?>%"></span>
                                <div class="mdfs-chip"><?php echo esc_html($status); ?></div>
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
        var head=document.getElementById('mdfs-slider-countdown');
        if(!head) return;
        var to   = parseInt(head.getAttribute('data-end')||'0',10);
        var phpNow = parseInt(head.getAttribute('data-now')||'0',10);
        var jsNow  = Math.floor(Date.now()/1000);
        var offset = phpNow - jsNow;

        function tick(){
            var now = Math.floor(Date.now()/1000)+offset;
            var r   = Math.max(0,to-now);
            var h=('0'+Math.floor(r/3600)).slice(-2),
                m=('0'+Math.floor((r%3600)/60)).slice(-2),
                s=('0'+(r%60)).slice(-2);
            var b=head.querySelectorAll('.mdfs-timebox');
            if(b.length>=3){ b[0].textContent=h; b[1].textContent=m; b[2].textContent=s; }
            setTimeout(tick,1000);
        }
        tick();
    })();
    </script>
    <?php return ob_get_clean();
});
