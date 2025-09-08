<?php
if (!defined('ABSPATH')) {
    exit();
}

if (!class_exists('MDFS_RT_Slots_CPT')) {
    class MDFS_RT_Slots_CPT
    {
        public function __construct()
        {
            add_action('init', [$this, 'register_cpt']);
            add_filter('use_block_editor_for_post_type', [$this, 'disable_block_editor'], 10, 2);

            add_action('add_meta_boxes_mdfs_slot', [$this, 'meta_boxes']);
            add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
            add_action('save_post_mdfs_slot', [$this, 'save_meta']);

            add_action('wp_ajax_mdfs_search_products', [$this, 'ajax_search_products']);
            add_action('wp_ajax_mdfs_get_product_price', [$this, 'ajax_get_product_price']);

            add_action('wp_ajax_mdfs_load_products_modal', [$this, 'ajax_load_products_modal']);
        }

        /** ----- CPT ----- */
        public function register_cpt()
        {
            if (post_type_exists('mdfs_slot')) {
                return;
            }

            register_post_type('mdfs_slot', [
                'labels' => [
                    'name' => __('Chiến dịch Flash Sale', 'mdfs-rt'),
                    'singular_name' => __('Chiến dịch Flash Sale', 'mdfs-rt'),
                    'add_new' => __('Thêm chiến dịch', 'mdfs-rt'),
                    'add_new_item' => __('Thêm mới chiến dịch', 'mdfs-rt'),
                    'edit_item' => __('Chỉnh sửa chiến dịch', 'mdfs-rt'),
                    'new_item' => __('Chiến dịch mới', 'mdfs-rt'),
                    'view_item' => __('Xem chiến dịch', 'mdfs-rt'),
                    'search_items' => __('Tìm chiến dịch', 'mdfs-rt'),
                    'not_found' => __('Không có chiến dịch', 'mdfs-rt'),
                    'not_found_in_trash' => __('Không có trong thùng rác', 'mdfs-rt'),
                ],
                'public' => false,
                'show_ui' => true,
                'menu_icon' => 'dashicons-clock',
                'supports' => ['title'],
                'show_in_menu' => true,
                'capability_type' => 'post',
                'map_meta_cap' => true,
                'show_in_rest' => false,
            ]);
        }

        public function disable_block_editor($use, $post_type)
        {
            if ($post_type === 'mdfs_slot') {
                return false;
            }
            return $use;
        }

        /** ----- Assets ----- */
        public function admin_assets($hook)
        {
            if ($hook !== 'post.php' && $hook !== 'post-new.php') {
                return;
            }
            $screen = get_current_screen();
            if (!$screen || $screen->post_type !== 'mdfs_slot') {
                return;
            }

            wp_enqueue_script('jquery');
            if (function_exists('wp_enqueue_editor')) {
                wp_enqueue_editor();
            }
            wp_enqueue_media();

            $plugin_url = plugin_dir_url(dirname(__FILE__)); // lấy URL plugin gốc

            // Enqueue Select2 nếu theme/host đã đăng ký sẵn
            if (wp_style_is('select2', 'registered')) {
                wp_enqueue_style('select2', $plugin_url . '/assets/css/select2.css', [], '1.0');
                //wp_enqueue_style('select2');
            }
            if (wp_script_is('select2', 'registered')) {
                wp_enqueue_script('select2', $plugin_url . '/assets/js/select2.full.min.js', ['jquery'], '1.0', true);
                //wp_enqueue_script('select2');
            }
            wp_enqueue_style('select2', $plugin_url . '/assets/css/select2.css', [], '1.0');
            wp_enqueue_script('select2', $plugin_url . '/assets/js/select2.full.min.js', ['jquery'], '1.0', true);

            // CSS riêng cho admin
            wp_enqueue_style('mdfs-admin', $plugin_url . 'assets/slots-cpt.css', [], '1.0');

            // JS riêng cho admin
            wp_enqueue_script('mdfs-admin', $plugin_url . 'assets/slots-cpt.js', ['jquery'], '1.0', true);

            // Localize biến MDFS_ADMIN cho JS
            wp_localize_script('mdfs-admin', 'MDFS_ADMIN', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mdfs_admin'),
            ]);
        }

        /** ----- Metaboxes ----- */
        public function meta_boxes()
        {
            add_meta_box('mdfs_time', __('Thời gian & Hiển thị', 'mdfs-rt'), [$this, 'box_time'], 'mdfs_slot', 'normal', 'high');

            add_meta_box('mdfs_products', __('Sản phẩm & Giá khuyến mãi (quota riêng)', 'mdfs-rt'), [$this, 'box_products'], 'mdfs_slot', 'normal', 'default');

            add_meta_box('mdfs_voucher', __('Voucher / Combo', 'mdfs-rt'), [$this, 'box_voucher'], 'mdfs_slot', 'side', 'default');

            add_meta_box('mdfs_thumbnail', __('Thumbnail Flash Sale', 'mdfs-rt'), [$this, 'box_thumbnail'], 'mdfs_slot', 'side', 'default');

            add_meta_box('mdfs_content', __('Nội dung (Editor)', 'mdfs-rt'), [$this, 'box_content'], 'mdfs_slot', 'normal', 'high');

            add_meta_box('mdfs_shortcodes', __('Tùy chọn nâng cao', 'mdfs-rt'), [$this, 'box_shortcodes'], 'mdfs_slot', 'side', 'low');
        }

        /** Time & Display box */
        public function box_time($post)
        {
            wp_nonce_field('mdfs_save_meta', 'mdfs_nonce');

            $from = (int) get_post_meta($post->ID, '_mdfs_time_from', true);
            $to = (int) get_post_meta($post->ID, '_mdfs_time_to', true);
            $limit = (int) get_post_meta($post->ID, '_mdfs_limit_per_customer', true);
            if (!$limit) {
                $limit = 0;
            }

            $type = get_post_meta($post->ID, '_mdfs_type', true);
            if (!$type) {
                $type = 'slider';
            }
            $cols = (int) get_post_meta($post->ID, '_mdfs_cols', true);
            if (!$cols) {
                $cols = 6;
            }

            $view_all = (string) get_post_meta($post->ID, '_mdfs_view_all', true);
            $only_instock = (int) get_post_meta($post->ID, '_mdfs_only_instock', true);
            $test_mode = (int) get_post_meta($post->ID, '_mdfs_test_mode', true);
            $ab_group = get_post_meta($post->ID, '_mdfs_ab_group', true);
            if (!$ab_group) {
                $ab_group = 'A';
            }

            $from_val = $from ? date('Y-m-d\TH:i', $from) : '';
            $to_val = $to ? date('Y-m-d\TH:i', $to) : '';
            ?>
    <div class="mdfs-row mdfs-grid-2">
      <label><strong><?php _e('Bắt đầu:', 'mdfs-rt'); ?></strong></label>
      <input type="datetime-local" name="mdfs_time_from" value="<?php echo esc_attr($from_val); ?>" />
    </div>
    <div class="mdfs-row mdfs-grid-2">
      <label><strong><?php _e('Kết thúc:', 'mdfs-rt'); ?></strong></label>
      <input type="datetime-local" name="mdfs_time_to" value="<?php echo esc_attr($to_val); ?>" />
    </div>
    <div class="mdfs-row mdfs-grid-2">
      <label><?php _e('Giới hạn mỗi khách/slot:', 'mdfs-rt'); ?></label>
      <input type="number" name="mdfs_limit_per_customer" value="<?php echo esc_attr($limit); ?>" min="0" />
    </div>

    <div class="mdfs-row mdfs-grid-2">
      <label><?php _e('Kiểu hiển thị:', 'mdfs-rt'); ?></label>
      <select name="mdfs_type">
        <option value="slider" <?php selected($type, 'slider'); ?>>Slider</option>
        <option value="grid"   <?php selected($type, 'grid'); ?>>Grid</option>
      </select>
    </div>

    <div class="mdfs-row mdfs-grid-2">
      <label><?php _e('Cột (grid):', 'mdfs-rt'); ?></label>
      <input type="number" name="mdfs_cols" value="<?php echo esc_attr($cols); ?>" min="2" max="8" />
    </div>

    <div class="mdfs-row mdfs-grid-2">
      <label><?php _e('Link "Xem tất cả":', 'mdfs-rt'); ?></label>
      <input type="url" name="mdfs_view_all" value="<?php echo esc_attr($view_all); ?>" placeholder="<?php echo esc_attr(home_url('/flash_sale/')); ?>" />
    </div>

    <div class="mdfs-row">
      <label class="mdfs-inline">
        <input type="checkbox" name="mdfs_only_instock" value="1" <?php checked($only_instock, 1); ?> />
        <span><?php _e('Chỉ hiển thị sản phẩm còn hàng', 'mdfs-rt'); ?></span>
      </label>
    </div>
    <div class="mdfs-row">
      <label class="mdfs-inline">
        <input type="checkbox" name="mdfs_test_mode" value="1" <?php checked($test_mode, 1); ?> />
        <span><?php _e('Test mode (chỉ admin/QA nhìn thấy)', 'mdfs-rt'); ?></span>
      </label>
    </div>
    <div class="mdfs-row mdfs-grid-2">
      <label><?php _e('A/B group:', 'mdfs-rt'); ?></label>
      <select name="mdfs_ab_group">
        <option value="A" <?php selected($ab_group, 'A'); ?>>A</option>
        <option value="B" <?php selected($ab_group, 'B'); ?>>B</option>
      </select>
    </div>
    <?php
        }

        /** Products & Prices box */
        public function box_products($post)
        {
            $rows = get_post_meta($post->ID, '_mdfs_products', true);
            if (!is_array($rows)) {
                $rows = [];
            }

            $remain_map = (array) get_post_meta($post->ID, '_mdfs_quota_remaining', true);
            ?>
    <div class="mdfs-hint">
      <?php _e('Chọn sản phẩm/biến thể, nhập Giá KM, Giá gốc (nếu muốn cố định), Quota & số lượng Còn lại. "Còn lại" là kho flash sale riêng – tự khoá khi 0.', 'mdfs-rt'); ?>
    </div>
<p>
  <button type="button" class="button button-secondary" id="mdfs-open-product-modal">
    + Chọn nhiều sản phẩm
  </button>
</p>
<div id="mdfs-product-modal" style="display:none;"></div>
    <div class="mdfs-table-wrap">
    <table class="mdfs-table" id="mdfs-products-table">
      <thead>
        <tr>
          <th><?php _e('Sản phẩm', 'mdfs-rt'); ?></th>
          <th class="mdfs-w80"><?php _e('Biến thể (ID)', 'mdfs-rt'); ?></th>
          <th class="mdfs-w100"><?php _e('Giá KM', 'mdfs-rt'); ?></th>
          <th class="mdfs-w80"><?php _e('Giá gốc', 'mdfs-rt'); ?></th>
          <th class="mdfs-w80"><?php _e('Quota', 'mdfs-rt'); ?></th>
          <th class="mdfs-w80"><?php _e('Còn lại', 'mdfs-rt'); ?></th>
          <th class="mdfs-w100"><?php _e('Badges', 'mdfs-rt'); ?></th>
          <th class="mdfs-w100"><?php _e('Thumbnail', 'mdfs-rt'); ?></th>
          <th class="mdfs-w80"></th>
        </tr>
      </thead>
      <tbody>
      <?php
      if (empty($rows)) {
          $rows[] = ['product_id' => 0, 'variation_id' => 0, 'sale_price' => '', 'regular_price' => '', 'quota' => '', 'badges' => ''];
      }
      foreach ($rows as $i => $r):

          $pid = (int) ($r['product_id'] ?? 0);
          $vid = (int) ($r['variation_id'] ?? 0);
          $text = $pid ? get_the_title($pid) : '';
          if ($vid) {
              $vobj = wc_get_product($vid);
              if ($vobj) {
                  $text = $vobj->get_formatted_name();
              }
          }
          $keyRemain = $vid ? $pid . ':' . $vid : (string) $pid;
          $remainVal = isset($remain_map[$keyRemain]) ? (int) $remain_map[$keyRemain] : (int) ($r['quota'] ?? 0);
          ?>
        <tr class="mdfs-row">
          <td>
            <select class="mdfs-product-select mdfs-select" name="mdfs_products[<?php echo esc_attr($i); ?>][product_id]" data-placeholder="<?php esc_attr_e('Gõ để tìm sản phẩm...', 'mdfs-rt'); ?>">
              <?php if ($pid): ?>
              <option value="<?php echo esc_attr($pid); ?>" selected="selected"><?php echo esc_html($text ?: '#' . $pid); ?></option>
              <?php endif; ?>
            </select>
          </td>
          <td><input type="number" class="small-text" name="mdfs_products[<?php echo esc_attr($i); ?>][variation_id]" value="<?php echo esc_attr($vid ?: 0); ?>" min="0"/></td>
          <td><input type="number" step="0.01" name="mdfs_products[<?php echo esc_attr($i); ?>][sale_price]" value="<?php echo esc_attr($r['sale_price'] ?? ''); ?>" /></td>
          <td><input type="number" step="0.01" name="mdfs_products[<?php echo esc_attr($i); ?>][regular_price]" value="<?php echo esc_attr($r['regular_price'] ?? ''); ?>" /></td>
          <td><input type="number" name="mdfs_products[<?php echo esc_attr($i); ?>][quota]" value="<?php echo esc_attr($r['quota'] ?? ''); ?>" min="0"/></td>
          <td><input type="number" name="mdfs_remain[<?php echo esc_attr($keyRemain); ?>]" value="<?php echo esc_attr($remainVal); ?>" min="0"/></td>
          <td><input type="text" name="mdfs_products[<?php echo esc_attr($i); ?>][badges]" value="<?php echo esc_attr($r['badges'] ?? ''); ?>" placeholder="<?php esc_attr_e('ví dụ: Mall|Yêu thích', 'mdfs-rt'); ?>" /></td>
          <td>
            <div class="mdfs-thumb-wrap">
              <input type="hidden" name="mdfs_products[<?php echo esc_attr($i); ?>][thumb]" class="mdfs-thumb" value="<?php echo esc_attr($r['thumb'] ?? ''); ?>" />
              <img src="<?php echo !empty($r['thumb']) ? esc_url($r['thumb']) : 'https://via.placeholder.com/60x60?text=+'; ?>" class="mdfs-thumb-preview" style="width:60px;height:60px;object-fit:cover;border:1px solid #ddd;" />
              <br/>
              <button type="button" class="button mdfs-upload-thumb">Tải ảnh lên</button>
              <button type="button" class="button mdfs-remove-thumb">Xóa</button>
            </div>
          </td>
          <td class="mdfs-actions">
            <button type="button" class="button mdfs-remove"> <?php _e('Xoá', 'mdfs-rt'); ?> </button>
          </td>
        </tr>
      <?php
      endforeach;
      ?>
      </tbody>
    </table>
    </div>
    <p><button type="button" class="button button-primary" id="mdfs-add-row">+ <?php _e('Thêm sản phẩm', 'mdfs-rt'); ?></button></p>
    <?php
        }

        /** Voucher / Combo (side) */
        public function box_voucher($post)
        {
            $voucher = get_post_meta($post->ID, '_mdfs_voucher', true);
            $combo = get_post_meta($post->ID, '_mdfs_combo', true);
            ?>
    <p><label><?php _e('Voucher:', 'mdfs-rt'); ?></label>
      <input type="text" class="widefat" name="mdfs_voucher" value="<?php echo esc_attr($voucher); ?>" placeholder="<?php esc_attr_e('Mã giảm giá (tuỳ chọn)', 'mdfs-rt'); ?>" />
    </p>
    <p><label><?php _e('Combo:', 'mdfs-rt'); ?></label>
      <textarea name="mdfs_combo" class="widefat" rows="3" placeholder="<?php esc_attr_e('Mô tả combo / ưu đãi kèm', 'mdfs-rt'); ?>"><?php echo esc_textarea($combo); ?></textarea>
    </p>
    <?php
        }

        /** Thumbnail upload (side) */
        public function box_thumbnail($post)
        {
            $thumb_id = (int) get_post_meta($post->ID, '_mdfs_thumbnail_id', true);
            $src = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'medium') : '';
            ?>
    <div id="mdfs-thumb-wrap">
      <?php if ($src): ?>
        <img id="mdfs-thumb-preview" src="<?php echo esc_url($src); ?>" style="max-width:100%;border-radius:8px;margin-bottom:8px;border:1px solid #eee" />
      <?php else: ?>
        <div id="mdfs-thumb-preview" style="width:100%;height:120px;background:#fafafa;border:1px dashed #ccc;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#999;margin-bottom:8px"><?php _e(
            'Chưa có ảnh',
            'mdfs-rt'
        ); ?></div>
      <?php endif; ?>
      <input type="hidden" name="mdfs_thumbnail_id" id="mdfs_thumbnail_id" value="<?php echo esc_attr($thumb_id); ?>" />
      <button type="button" class="button" id="mdfs-thumb-upload"><?php _e('Tải ảnh lên', 'mdfs-rt'); ?></button>
      <button type="button" class="button" id="mdfs-thumb-remove" <?php disabled(!$thumb_id); ?>><?php _e('Xoá', 'mdfs-rt'); ?></button>
    </div>
    <script>
    jQuery(function($){
      var frame;
      $('#mdfs-thumb-upload').on('click', function(e){
        e.preventDefault();
        if(frame){ frame.open(); return; }
        frame = wp.media({ title:'<?php echo esc_js(__('Chọn ảnh Thumbnail Flash Sale', 'mdfs-rt')); ?>', button:{ text:'<?php echo esc_js(__('Chọn ảnh này', 'mdfs-rt')); ?>' }, multiple:false });
        frame.on('select', function(){
          var att = frame.state().get('selection').first().toJSON();
          $('#mdfs_thumbnail_id').val(att.id);
          $('#mdfs-thumb-remove').prop('disabled', false);
          var url = (att.sizes && att.sizes.medium) ? att.sizes.medium.url : att.url;
          var $prev = $('#mdfs-thumb-preview');
          if($prev.is('img')) $prev.attr('src', url);
          else $prev.replaceWith('<img id="mdfs-thumb-preview" src="'+url+'" style="max-width:100%;border-radius:8px;margin-bottom:8px;border:1px solid #eee"/>');
        });
        frame.open();
      });
      $('#mdfs-thumb-remove').on('click', function(e){
        e.preventDefault();
        $('#mdfs_thumbnail_id').val('');
        $(this).prop('disabled', true);
        var $prev = $('#mdfs-thumb-preview');
        if($prev.is('img')){
          $prev.replaceWith('<div id="mdfs-thumb-preview" style="width:100%;height:120px;background:#fafafa;border:1px dashed #ccc;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#999;margin-bottom:8px"><?php echo esc_js(
              __('Chưa có ảnh', 'mdfs-rt')
          ); ?></div>');
        }
      });
    });
    </script>
    <?php
        }

        /** Nội dung (Editor) */
        public function box_content($post)
        {
            wp_nonce_field('mdfs_save_meta', 'mdfs_nonce');

            $content = get_post_meta($post->ID, '_mdfs_content', true);
            if (function_exists('wp_enqueue_editor')) {
                wp_enqueue_editor();
            }
            $editor_id = 'mdfs_content_' . $post->ID;

            echo '<div class="mdfs-editor-wrap">';
            if (function_exists('wp_editor')) {
                wp_editor($content, $editor_id, [
                    'textarea_name' => 'mdfs_content',
                    'textarea_rows' => 14,
                    'editor_height' => 280,
                    'media_buttons' => true,
                    'drag_drop_upload' => true,
                    'tinymce' => true,
                    'quicktags' => true,
                ]);
            } else {
                echo '<textarea name="mdfs_content" rows="14" style="width:100%;">' . esc_textarea($content) . '</textarea>';
            }
            echo '<p class="description">' . esc_html__('Nội dung này hiển thị trên tab/campaign. Slider trang chủ không hiển thị phần này.', 'mdfs-rt') . '</p>';
            echo '</div>';
        }

        /** Shortcodes hint (side) */
        public function box_shortcodes($post)
        {
            echo '<p><strong>' . __('Shortcode:', 'mdfs-rt') . '</strong><br/>';
            echo '<code>[md_flash_sale_tabs]</code> | <code>[md_flash_sale_slider]</code><br/>';
            echo '<code>[md_flash_sale_campaign id="' . esc_html($post->ID) . '"]</code>';
            echo '</p>';
        }

        /** ----- Save meta ----- */
        public function save_meta($post_id)
        {
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return;
            }
            if (!isset($_POST['mdfs_nonce']) || !wp_verify_nonce($_POST['mdfs_nonce'], 'mdfs_save_meta')) {
                return;
            }
            if (!current_user_can('edit_post', $post_id)) {
                return;
            }

            // time
            $from = isset($_POST['mdfs_time_from']) ? sanitize_text_field($_POST['mdfs_time_from']) : '';
            $to = isset($_POST['mdfs_time_to']) ? sanitize_text_field($_POST['mdfs_time_to']) : '';
            $from_ts = $from ? strtotime($from) : 0;
            $to_ts = $to ? strtotime($to) : 0;
            update_post_meta($post_id, '_mdfs_time_from', $from_ts);
            update_post_meta($post_id, '_mdfs_time_to', $to_ts);

            update_post_meta($post_id, '_mdfs_limit_per_customer', (int) ($_POST['mdfs_limit_per_customer'] ?? 0));
            update_post_meta($post_id, '_mdfs_type', sanitize_text_field($_POST['mdfs_type'] ?? 'slider'));
            update_post_meta($post_id, '_mdfs_cols', (int) ($_POST['mdfs_cols'] ?? 6));
            update_post_meta($post_id, '_mdfs_view_all', esc_url_raw($_POST['mdfs_view_all'] ?? ''));
            update_post_meta($post_id, '_mdfs_only_instock', isset($_POST['mdfs_only_instock']) ? 1 : 0);
            update_post_meta($post_id, '_mdfs_test_mode', isset($_POST['mdfs_test_mode']) ? 1 : 0);
            update_post_meta($post_id, '_mdfs_ab_group', sanitize_text_field($_POST['mdfs_ab_group'] ?? 'A'));

            // voucher/combo
            update_post_meta($post_id, '_mdfs_voucher', sanitize_text_field($_POST['mdfs_voucher'] ?? ''));
            update_post_meta($post_id, '_mdfs_combo', wp_kses_post($_POST['mdfs_combo'] ?? ''));

            // thumbnail
            update_post_meta($post_id, '_mdfs_thumbnail_id', (int) ($_POST['mdfs_thumbnail_id'] ?? 0));

            // editor content
            if (isset($_POST['mdfs_content'])) {
                update_post_meta($post_id, '_mdfs_content', wp_kses_post(wp_unslash($_POST['mdfs_content'])));
            }

            // products
            $rows = [];
            if (isset($_POST['mdfs_products']) && is_array($_POST['mdfs_products'])) {
                foreach ($_POST['mdfs_products'] as $r) {
                    $pid = isset($r['product_id']) ? (int) $r['product_id'] : 0;
                    if (!$pid) {
                        continue;
                    }
                    $rows[] = [
                        'product_id' => $pid,
                        'variation_id' => isset($r['variation_id']) ? (int) $r['variation_id'] : 0,
                        'sale_price' => isset($r['sale_price']) ? wc_format_decimal($r['sale_price']) : '',
                        'regular_price' => isset($r['regular_price']) ? wc_format_decimal($r['regular_price']) : '',
                        'quota' => isset($r['quota']) ? (int) $r['quota'] : 0,
                        'badges' => sanitize_text_field($r['badges'] ?? ''),
                        'thumb' => esc_url_raw($r['thumb'] ?? ''),
                    ];
                }
            }
            update_post_meta($post_id, '_mdfs_products', $rows);

            // remain map (key: pid or pid:vid)
            $remain_map = [];
            if (isset($_POST['mdfs_remain']) && is_array($_POST['mdfs_remain'])) {
                foreach ($_POST['mdfs_remain'] as $k => $v) {
                    $remain_map[sanitize_text_field($k)] = (int) $v;
                }
            } else {
                // tạo map mặc định nếu chưa có
                foreach ($rows as $r) {
                    $key = $r['variation_id'] ? $r['product_id'] . ':' . $r['variation_id'] : (string) $r['product_id'];
                    $remain_map[$key] = (int) $r['quota'];
                }
            }
            update_post_meta($post_id, '_mdfs_quota_remaining', $remain_map);
        }

        /** ----- AJAX product search (select2) ----- */
        public function ajax_search_products() {
    if (!current_user_can('edit_posts')) {
        wp_send_json([]);
    }
    check_ajax_referer('mdfs_admin', 'nonce');

    $term = isset($_GET['q']) ? wc_clean(wp_unslash($_GET['q'])) : '';
    $results = [];

    if ($term) {
        global $wpdb;

        // --- 1. Tìm theo title ---
        $ids_title = $wpdb->get_col(
            $wpdb->prepare(
                "
                SELECT ID FROM {$wpdb->posts}
                WHERE post_type = 'product'
                  AND post_status IN ('publish','private')
                  AND post_title LIKE %s
                LIMIT 20
            ",
                '%' . $wpdb->esc_like($term) . '%'
            )
        );

        foreach ($ids_title as $pid) {
            $product = wc_get_product($pid);
            if (!$product) continue;
			
            $results[] = [
                'id'   => $pid,
                'text' => $product->get_name() . ' (#' . $pid . ')',
            ];
        }

        // --- 2. Tìm theo SKU ---
        $ids_sku = $wpdb->get_col(
            $wpdb->prepare(
                "
                SELECT post_id FROM {$wpdb->postmeta}
                WHERE meta_key = '_sku'
                  AND meta_value LIKE %s
                LIMIT 10
            ",
                '%' . $wpdb->esc_like($term) . '%'
            )
        );

        foreach ($ids_sku as $pid) {
            if (in_array($pid, array_column($results, 'id'))) continue;

            $product = wc_get_product($pid);
            if (!$product) continue;
			
			$results[] = [
				'id'   => $pid,
				'text' => $product->get_name() . ' (#' . $pid . ')',
			];
        }

        // --- 3. Tìm variations ---
        $variations = wc_get_products([
            'status' => ['publish', 'private'],
            'limit'  => 10,
            'type'   => ['variation'],
            'search' => $term,
            'return' => 'objects',
        ]);

        foreach ($variations as $var) {
            $parent = $var->get_parent_id();
            $price  = $var->get_price();

            $results[] = [
                'id'   => $parent,
                'text' => $var->get_name(),
            ];
        }
    }

    // --- Loại bỏ trùng ID ---
    $seen = [];
    $out  = [];
    foreach ($results as $r) {
        if (isset($seen[$r['id']])) continue;
        $seen[$r['id']] = 1;
        $out[] = $r;
    }

    wp_send_json($out);
}

        public function ajax_load_products_modal()
        {
            if (!current_user_can('edit_posts')) {
                wp_send_json_error();
            }

            $paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
            $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
            $per_page = isset($_GET['per_page']) ? absint($_GET['per_page']) : 10;
            if ($per_page < 1) {
                $per_page = 10;
            }

            $args = [
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => $per_page,
                'paged' => $paged,
                's' => $search,
                'fields' => 'ids',
            ];

            $q = new WP_Query($args);

            ob_start();
            if ($q->have_posts()) {
                echo '<div class="mdfs-select-all"><label><input type="checkbox" id="mdfs-check-all"> Chọn tất cả trang này</label></div>';
                echo '<ul class="mdfs-modal-products">';
                foreach ($q->posts as $pid) {
                    $product = wc_get_product($pid);
                    $price = $product ? $product->get_regular_price() : '';
                    echo '<li>
                    <label>
                      <input type="checkbox" class="mdfs-modal-product"
                             value="' .
                        $pid .
                        '"
                             data-name="' .
                        esc_attr(get_the_title($pid)) .
                        '"
                             data-price="' .
                        $price .
                        '">
                      ' .
                        get_the_title($pid) .
                        ' (#' .
                        $pid .
                        ')
                    </label>
                  </li>';
                }
                echo '</ul>';

                // phân trang rút gọn + nút trước/sau
                if ($q->max_num_pages > 1) {
                    $total = $q->max_num_pages;
                    $current = $paged;
                    $range = 2;
                    $pages = [];

                    $pages[] = 1;
                    if ($current - $range > 2) {
                        $pages[] = '...';
                    }

                    for ($i = max(2, $current - $range); $i <= min($total - 1, $current + $range); $i++) {
                        $pages[] = $i;
                    }

                    if ($current + $range < $total - 1) {
                        $pages[] = '...';
                    }
                    if ($total > 1) {
                        $pages[] = $total;
                    }

                    echo '<div class="mdfs-modal-pagination">';
                    if ($current > 1) {
                        echo '<a href="#" class="mdfs-prev" data-page="' . ($current - 1) . '">« Trước</a>';
                    }
                    foreach ($pages as $p) {
                        if ($p === '...') {
                            echo '<span class="dots">...</span>';
                        } else {
                            $active = $p == $current ? 'class="active"' : '';
                            echo '<a href="#" class="mdfs-page" data-page="' . $p . '" ' . $active . '>' . $p . '</a>';
                        }
                    }
                    if ($current < $total) {
                        echo '<a href="#" class="mdfs-next" data-page="' . ($current + 1) . '">Sau »</a>';
                    }
                    echo '</div>';
                }
            } else {
                echo '<p>Không có sản phẩm nào.</p>';
            }

            $html = ob_get_clean();
            wp_send_json_success($html);
        }

        public function ajax_get_product_price()
        {
            if (!current_user_can('edit_posts')) {
                wp_send_json_error();
            }
            check_ajax_referer('mdfs_admin', 'nonce');

            $pid = isset($_GET['id']) ? absint($_GET['id']) : 0;
            if (!$pid) {
                wp_send_json_error();
            }

            $product = wc_get_product($pid);
            if (!$product) {
                wp_send_json_error();
            }

            $price = $product->get_regular_price();
            $sale = $product->get_sale_price();

            wp_send_json_success([
                'regular_price' => $price,
                'sale_price' => $sale,
            ]);
        }
    }
} // end class exists

// Bootstrap
if (!isset($GLOBALS['mdfs_rt_slots_cpt'])) {
    $GLOBALS['mdfs_rt_slots_cpt'] = new MDFS_RT_Slots_CPT();
}
