<?php
/**
 * @wordpress-plugin
 * Plugin Name:       Seamless Logo Marquee
 * Plugin URI:        https://github.com/trangc0der/seamless-logo-marquee
 * GitHub Plugin URI: https://github.com/trangc0der/seamless-logo-marquee
 * Description:       Tạo một slider logo chạy liền mạch với khu vực quản lý logo riêng và shortcode.
 * Version:           1.0.0
 * Author:            trangc0der
 * Author URI:        https://github.com/trangc0der
 * Update URI:        false
 * Text Domain:       slm
 * Requires PHP:      7.4
 * Requires at least: 6.0
 */

// Chặn truy cập trực tiếp
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Định nghĩa các hằng số đường dẫn để dễ sử dụng
define( 'SLM_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'SLM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Gọi các tệp cần thiết
require_once SLM_PLUGIN_PATH . 'includes/enqueue-assets.php';
require_once SLM_PLUGIN_PATH . 'includes/cpt-logo.php';
require_once SLM_PLUGIN_PATH . 'includes/shortcode-logo-marquee.php';
require_once SLM_PLUGIN_PATH . 'includes/admin-settings-page.php';