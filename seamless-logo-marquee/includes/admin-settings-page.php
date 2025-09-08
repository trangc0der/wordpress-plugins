<?php
// Chặn truy cập trực tiếp
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Thêm một trang cài đặt con vào menu "Logos".
 */
function slm_add_admin_menu() {
    add_submenu_page(
        'edit.php?post_type=logo_item', // Slug của menu cha (Logos CPT)
        'Cài đặt Slider',               // Tiêu đề trang
        'Cài đặt',                      // Tên trong menu
        'manage_options',               // Quyền truy cập
        'slm_settings',                 // Slug của trang cài đặt
        'slm_options_page_html'         // Hàm để render HTML của trang
    );
}
add_action( 'admin_menu', 'slm_add_admin_menu' );

/**
 * Đăng ký các trường cài đặt bằng Settings API của WordPress.
 */
function slm_settings_init() {
    // Đăng ký một nhóm cài đặt
    register_setting( 'slm_settings_group', 'slm_options' );

    // Tạo một section (nhóm các trường)
    add_settings_section(
        'slm_general_section',
        'Tùy chỉnh chung',
        null,
        'slm_settings'
    );

    // Thêm các trường vào section
    add_settings_field('slm_speed', 'Tốc độ', 'slm_field_callback', 'slm_settings', 'slm_general_section', ['id' => 'speed', 'type' => 'number', 'default' => 1, 'desc' => 'Số lớn hơn = nhanh hơn']);
    add_settings_field('slm_direction', 'Hướng chạy', 'slm_field_callback', 'slm_settings', 'slm_general_section', ['id' => 'direction', 'type' => 'select', 'options' => ['rtl' => 'Phải qua Trái', 'ltr' => 'Trái qua Phải']]);
    add_settings_field('slm_layout', 'Bố cục', 'slm_field_callback', 'slm_settings', 'slm_general_section', ['id' => 'layout', 'type' => 'select', 'options' => ['container' => 'Trong khung (Container)', 'full-width' => 'Toàn màn hình (Full-width)']]);
    add_settings_field('slm_logo_height', 'Chiều cao Logo (px)', 'slm_field_callback', 'slm_settings', 'slm_general_section', ['id' => 'logo_height', 'type' => 'number', 'default' => 40, 'desc' => 'Đơn vị pixels']);
    add_settings_field('slm_hover_effect', 'Hiệu ứng Hover', 'slm_field_callback', 'slm_settings', 'slm_general_section', ['id' => 'hover_effect', 'type' => 'select', 'options' => ['none' => 'Không có', 'scale' => 'Phóng to (Scale Up)']]);
}
add_action( 'admin_init', 'slm_settings_init' );

/**
 * Hàm chung để render các loại trường (input, select).
 */
function slm_field_callback( $args ) {
    $options = get_option( 'slm_options' );
    $value = isset( $options[$args['id']] ) ? $options[$args['id']] : (isset($args['default']) ? $args['default'] : '');

    switch ( $args['type'] ) {
        case 'number':
            echo '<input type="number" id="' . esc_attr($args['id']) . '" name="slm_options[' . esc_attr($args['id']) . ']" value="' . esc_attr( $value ) . '">';
            break;
        case 'select':
            echo '<select id="' . esc_attr($args['id']) . '" name="slm_options[' . esc_attr($args['id']) . ']">';
            foreach ( $args['options'] as $key => $label ) {
                echo '<option value="' . esc_attr( $key ) . '" ' . selected( $value, $key, false ) . '>' . esc_html( $label ) . '</option>';
            }
            echo '</select>';
            break;
    }
     if (isset($args['desc'])) {
        echo '<p class="description">' . esc_html($args['desc']) . '</p>';
    }
}

/**
 * Hàm render HTML cho toàn bộ trang cài đặt.
 */
function slm_options_page_html() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'slm_settings_group' );
            do_settings_sections( 'slm_settings' );
            submit_button( 'Lưu thay đổi' );
            ?>
        </form>
    </div>
    <?php
}