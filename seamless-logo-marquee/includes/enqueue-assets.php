<?php
// Chặn truy cập trực tiếp
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Đăng ký các file CSS và JS của plugin.
 */
function slm_register_assets() {
    // Đăng ký file CSS
    wp_register_style(
        'slm-style',
        SLM_PLUGIN_URL . 'assets/style.css',
        array(),
        '1.0.0'
    );

    // Đăng ký file JS
    wp_register_script(
        'slm-script',
        SLM_PLUGIN_URL . 'assets/main.js',
        array(),
        '1.0.0',
        true // true = tải ở footer
    );
}
add_action( 'wp_enqueue_scripts', 'slm_register_assets' );