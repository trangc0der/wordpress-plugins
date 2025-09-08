<?php
// Chặn truy cập trực tiếp
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Đăng ký Custom Post Type cho Logo.
 */
function slm_register_logo_post_type() {
    $labels = array(
        'name'                  => _x( 'Logos', 'Post Type General Name', 'text_domain' ),
        'singular_name'         => _x( 'Logo', 'Post Type Singular Name', 'text_domain' ),
        'menu_name'             => __( 'Logos', 'text_domain' ),
        'archives'              => __( 'Logo Archives', 'text_domain' ),
        'add_new_item'          => __( 'Thêm Logo Mới', 'text_domain' ),
        'add_new'               => __( 'Thêm Mới', 'text_domain' ),
    );
    $args = array(
        'label'                 => __( 'Logo', 'text_domain' ),
        'description'           => __( 'Quản lý các logo của đối tác', 'text_domain' ),
        'labels'                => $labels,
        'supports'              => array( 'title', 'thumbnail', 'page-attributes' ), // Hỗ trợ tiêu đề và ảnh đại diện
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 20,
        'menu_icon'             => 'dashicons-images-alt2', // Icon trong menu
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => false,
        'can_export'            => true,
        'has_archive'           => false,
        'exclude_from_search'   => true,
        'publicly_queryable'    => false,
        'capability_type'       => 'post',
        'show_in_rest'          => true, // Hỗ trợ Gutenberg
    );
    register_post_type( 'logo_item', $args );
}
add_action( 'init', 'slm_register_logo_post_type', 0 );