<?php
// Chặn truy cập trực tiếp
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Hàm xử lý shortcode [logo_marquee].
 */
function slm_logo_marquee_shortcode_handler( $atts ) {
    // Lấy các cài đặt đã lưu từ database
    $options = get_option('slm_options');

    // Thiết lập giá trị mặc định nếu chưa có cài đặt
    $defaults = [
        'speed'         => 1,
        'direction'     => 'rtl', // right-to-left
        'layout'        => 'container',
        'logo_height'   => 40,
        'hover_effect'  => 'none',
    ];
    $settings = wp_parse_args($options, $defaults);

    // Truyền dữ liệu cài đặt từ PHP sang JavaScript
    wp_enqueue_style( 'slm-style' );
    wp_enqueue_script( 'slm-script' );
    wp_localize_script( 'slm-script', 'slm_params', [
        'speed'     => floatval($settings['speed']),
        'direction' => $settings['direction'],
    ]);

    // Truy vấn logo
    $logo_query = new WP_Query([
        'post_type'      => 'logo_item',
        'posts_per_page' => -1,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
    ]);

    ob_start();

    if ( $logo_query->have_posts() ) :
        $container_id = 'marquee-container-' . uniqid();
        $track_id = 'logo-track-' . uniqid();
        
        // Thêm các class tùy chỉnh vào container
        $container_classes = [
            'marquee-container',
            'layout--' . esc_attr($settings['layout']),
            'hover--' . esc_attr($settings['hover_effect']),
        ];
    ?>
    <style>
        #<?php echo $container_id; ?> .logo-wrapper img {
            height: <?php echo intval($settings['logo_height']); ?>px;
        }
    </style>
    <div id="<?php echo $container_id; ?>" class="<?php echo implode(' ', $container_classes); ?>  with-magnifying-glass">
        <div class="magnifying-glass-zone"></div>
        <div class="marquee-mask-left"></div>
        <div class="marquee-mask-right"></div>
        <div class="marquee-track" id="<?php echo $track_id; ?>">
            <div class="logo-slide">
                <?php while ( $logo_query->have_posts() ) : $logo_query->the_post(); ?>
                    <?php if ( has_post_thumbnail() ) : ?>
                        <div class="logo-wrapper">
                            <?php the_post_thumbnail( 'full', array( 'class' => 'logo', 'alt' => get_the_title() ) ); ?>
                        </div>
                    <?php endif; ?>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
    <?php
    else :
        echo '<p>Chưa có logo nào được thêm vào.</p>';
    endif;

    wp_reset_postdata();
    return ob_get_clean();
}
// Xóa shortcode cũ đi và đăng ký lại để đảm bảo không bị cache
if (shortcode_exists('logo_marquee')) {
    remove_shortcode('logo_marquee');
}
add_shortcode( 'logo_marquee', 'slm_logo_marquee_shortcode_handler' );