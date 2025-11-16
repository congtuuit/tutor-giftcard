<?php

/**
 * TG Redeem - tách UI, enqueue, AJAX/REST endpoints
 * Place this file into a plugin or include it in theme's functions.php
 * File structure suggestion:
 *  - tg-redeem/
 *    - tg-redeem.php   <- this file (main)
 *    - templates/
 *        - redeem-grid.php
 *        - redeem-selected.php
 *    - assets/
 *        - js/
 *            - tg-redeem.js
 *        - css/
 *            - tg-redeem.css
 *
 * Tùy ý di chuyển; templates có thể override bằng locate_template trong theme.
 */

if (!defined('ABSPATH')) exit;

class TG_Redeem
{
    public static function init()
    {

        // View detail
        add_filter('template_include', function ($template) {

            if (is_singular('tutor_giftcard')) {

                $custom = plugin_dir_path(__FILE__) . '../templates/single-tutor_giftcard.php';

                if (file_exists($custom)) {
                    return $custom;
                }
            }

            return $template;
        });


        add_action('template_redirect', [__CLASS__, 'maybe_render_redeem_page']);

        add_shortcode('tg_redeem', [__CLASS__, 'render_redeem_content']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

        // API frontend JS can be added here if needed
        add_action('wp_ajax_tg_filter_courses', [__CLASS__, 'tg_filter_courses']);
        add_action('wp_ajax_nopriv_tg_filter_courses', [__CLASS__, 'tg_filter_courses']);

        // Cho user đã đăng nhập
        add_action('wp_ajax_redeem_course', [__CLASS__, 'tg_redeem_course_ajax_handler']);

        // Cho user không đăng nhập (nếu cần)
        add_action('wp_ajax_nopriv_redeem_course', [__CLASS__, 'tg_redeem_course_ajax_handler']);


        // Enroll courses when order is completed
        add_action('woocommerce_order_status_completed', [__CLASS__, 'enroll_gift_courses']);
    }

    // Enroll courses associated with giftcards in the order
    public function enroll_gift_courses($order_id)
    {
        if (!$order_id) return;

        $order = wc_get_order($order_id);
        if (!$order) return;

        $user_id = $order->get_user_id();
        if (!$user_id) return;

        $product_ids = array_map(
            fn($item) => $item->get_product_id(),
            $order->get_items()
        );

        foreach ($product_ids as $product_id) {

            // Lấy tất cả thẻ quà tặng liên kết với sản phẩm này
            $giftcard_posts = TG_Utils::get_giftcard_courses_by_product($product_id);

            foreach ($giftcard_posts as $post) {
                $giftcard_id = $post->ID;

                // Gán thẻ quà tặng cho user
                TG_Utils::assign_user_to_giftcard($giftcard_id, $user_id);
            }
        }
    }


    #region Frontend APIs
    function tg_filter_courses()
    {

        $paged   = intval($_POST['page'] ?? 1);
        $search  = sanitize_text_field($_POST['search'] ?? '');
        $perpage = 10;

        $tg_giftcard = json_decode(wp_unslash($_POST['tg_giftcard'] ?? '{}'), true);
        $giftcard_id     = intval($tg_giftcard['id'] ?? 0);
        $giftcard_code   = sanitize_text_field($tg_giftcard['code'] ?? '');

        // validate giftcard here if needed
        if (!$giftcard_code || !$giftcard_id) {
            wp_send_json([
                'html'      => '<p>Không tìm thấy khóa học.</p>',
                'max_page'  => 0,
                'current'   => $paged,
            ]);
        }

        // validate giftcard
        $post = get_post($giftcard_id);
        if (!$post || $post->post_type !== 'tutor_giftcard') {
            wp_send_json([
                'html'      => '<p>Không tìm thấy khóa học.</p>',
                'max_page'  => 0,
                'current'   => $paged,
            ]);
        }

        // Validate giftcard code
        $post_code = get_post_meta($giftcard_id, '_tg_gift_card_code', true);
        if ($post_code !== $giftcard_code) {
            wp_send_json([
                'html'      => '<p>Không tìm thấy khóa học.</p>',
                'max_page'  => 0,
                'current'   => $paged,
            ]);
        }

        // Kiểm tra user có được gán thẻ này
        $user_id = get_current_user_id();
        $assigned_users = TG_Utils::get_assigned_users($giftcard_id);
        if (!in_array($user_id, $assigned_users)) {
            wp_send_json([
                'html'      => '<p>Bạn không có quyền sử dụng thẻ này.</p>',
                'max_page'  => 0,
                'current'   => $paged,
            ]);
        }

        // Validate giftcard status
        $status = get_post_meta($giftcard_id, '_tg_status', true) ?: 'active';
        $expire_date = get_post_meta($giftcard_id, '_tg_expire_date', true);
        $now = date('Y-m-d');
        if (!empty($expire_date) && $expire_date < $now) $status = 'expired';
        if ($status !== 'active') {
            wp_send_json([
                'html'      => '<p>Thẻ không thể sử dụng (trạng thái: ' . esc_html($status) . ').</p>',
                'max_page'  => 0,
                'current'   => $paged,
            ]);
        }

        $allow_any_course = get_post_meta($giftcard_id, '_tg_allow_all_courses', true);
        $max_course_price = get_post_meta($giftcard_id, '_tg_max_amount', true);
        $fixed_course_list = get_post_meta($giftcard_id, '_tg_specific_courses', true);
        $exclude_course_list = get_post_meta($giftcard_id, '_tg_excluded_courses', true);

        $args = [
            'post_type'      => 'courses',
            'posts_per_page' => $perpage,
            'paged'          => $paged,
            's'              => $search,
            'post_status'    => 'publish',
        ];

        $isPriceFilterApplied = false;
        if (!empty($fixed_course_list)) {
            $ids = array_filter(array_map('intval', explode(',', $fixed_course_list)));
            $args['post__in'] = $ids;
        } else {
            $excluded = array_filter(array_map('intval', explode(',', $exclude_course_list ?? '')));
            if ($excluded) {
                $args['post__not_in'] = $excluded;
            }
            if (!empty($max_course_price) && !$allow_any_course) {
                $args = [
                    'post_type'      => 'courses',
                    'posts_per_page' => $perpage,
                    'paged'          => $paged,
                    's'              => $search,
                    'post_status'    => 'publish',
                    'meta_query'     => [
                        [
                            'key'     => '_tutor_course_product_id',
                            'compare' => 'EXISTS',
                        ],
                    ],
                ];

                $isPriceFilterApplied = true;
            }
        }

        $query = new WP_Query($args);

        ob_start();

        if ($query->have_posts()) {
            while ($query->have_posts()):
                $query->the_post();
                $cid = get_the_ID();

                if ($isPriceFilterApplied) {

                    // Lấy product_id
                    $product_id = get_post_meta($cid, '_tutor_course_product_id', true);
                    if (!$product_id) continue;

                    // Lấy giá WooCommerce
                    $price = floatval(get_post_meta($product_id, '_price', true));

                    // Lọc theo khoảng giá
                    $minPrice = 0;
                    $maxPrice = floatval($max_course_price);
                    if ($price < $minPrice || $price > $maxPrice) continue;
                }

                //get_course_price

                $course_data = [
                    'id'       => $cid,
                    'thumb'    => get_tutor_course_thumbnail_src('medium_large', $cid),
                    'link'     => get_permalink($cid),
                    'title'    => get_the_title($cid),
                    'author'   => get_the_author(),
                    'enrolled' => (function () use ($cid) {
                        $n = tutor_utils()->count_enrolled_users_by_course($cid) ?? 0;
                        if ($n < 100) return $n * 3;
                        if ($n > 100) return $n * 2;
                        return $n;
                    })(),
                    'price' => tutor_utils()->get_course_price($cid),
                    'lessons'  => tutor_utils()->get_tutor_course_total_lessons($cid) ?? 0,
                    'duration' => get_tutor_option('enable_course_duration')
                        ? get_tutor_course_duration_context($cid)
                        : null
                ];

                TG_Utils::load_template('/templates/course-card.php', [
                    'course_data' => $course_data
                ]);

            endwhile;
        } else {
            echo '<p>Không tìm thấy khóa học.</p>';
        }

        $html = ob_get_clean();

        wp_send_json([
            'html'      => $html,
            'max_page'  => $query->max_num_pages,
            'current'   => $paged,
        ]);
    }
    #endregion

    public static function enqueue_assets()
    {
        $version = '1.2.9';
        wp_register_style('tg-redeem-css', plugins_url('assets/css/tg-redeem.css?v=' . $version, dirname(__FILE__)));
        wp_enqueue_style('tg-redeem-css');

        wp_register_script('tg-redeem-js', plugins_url('../assets/js/tg-redeem.js', __FILE__), ['jquery'], $version, true);
        wp_localize_script('tg-redeem-js', 'TG_REDEEM', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => esc_url_raw(rest_url('tg/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'max' => 1,
        ]);

        wp_enqueue_script('tg-redeem-js');
    }


    public static function maybe_render_redeem_page()
    {
        if (is_page('redeem-giftcard')) {
            add_filter('the_content', [__CLASS__, 'render_redeem_content']);
        }
    }

    // Main shortcode: now loads templates and prints a container only
    public static function render_redeem_content($content)
    {
        if (!is_user_logged_in()) return '<p>Vui lòng đăng nhập để đổi quà.</p>';

        $code = isset($_GET['tg_code']) ? sanitize_text_field($_GET['tg_code']) : '';
        $giftcard_id = isset($_GET['tg_id']) ? intval($_GET['tg_id']) : 0;
        $tg_gcid = isset($_GET['tg_gcid']) ? intval($_GET['tg_gcid']) : 0; // record ID trong bảng tg_user_giftcards

        if (!$code || !$giftcard_id) return '<p>Thiếu thông tin thẻ.</p>';

        $user_id = get_current_user_id();

        // Lấy giftcard theo ID
        $post = get_post($giftcard_id);
        if (!$post || $post->post_type !== 'tutor_giftcard') return '<p>Không tìm thấy thẻ.</p>';

        $post_code = get_post_meta($giftcard_id, '_tg_gift_card_code', true);
        if ($post_code !== $code) return '<p>Code thẻ không đúng.</p>';

        // Kiểm tra user có được gán thẻ này
        // TODO upgrade: check theo record ID trong bảng tg_user_giftcards
        $existed = TG_Utils::get_user_giftcard_record($user_id, $giftcard_id, $tg_gcid);
        if (!$existed) return '<p>Bạn không có quyền sử dụng thẻ này.</p>';

        // Lấy meta giftcard
        $status = get_post_meta($giftcard_id, '_tg_status', true) ?: 'active';
        $expire_date = get_post_meta($giftcard_id, '_tg_expire_date', true);
        $max_courses = get_post_meta($giftcard_id, '_tg_max_courses', true) ?: 1;
        $meta = [
            'fixed_course_list' => get_post_meta($giftcard_id, '_tg_specific_courses', true),
            'exclude_course_list' => get_post_meta($giftcard_id, '_tg_excluded_courses', true),
            'allow_any_course' => get_post_meta($giftcard_id, '_tg_allow_all_courses', true),
            'max_course_price' => get_post_meta($giftcard_id, '_tg_max_amount', true),
        ];

        // Kiểm tra hạn sử dụng
        $now = date('Y-m-d');
        if (!empty($expire_date) && $expire_date < $now) $status = 'expired';
        if ($status !== 'active') return '<p>Thẻ không thể sử dụng (trạng thái: ' . esc_html($status) . ').</p>';

        // Pass minimal data to JS and templates
        $gift_data = [
            'id' => $tg_gcid,
            'giftcard_id' => $giftcard_id,
            'code' => $code,
            'max_courses' => intval($max_courses),
            'meta' => $meta,
            'post' => $post,
        ];

        ob_start();
        // render main container; templates will be loaded by PHP here so they can be overridden in theme
?>
        <div id="tg-redeem-app" data-status="<?php echo $existed['used']; ?>" data-id="<?php echo esc_attr($tg_gcid); ?>" data-code="<?php echo esc_attr($code); ?>" data-giftcard="<?php echo esc_attr($giftcard_id); ?>" data-max="<?php echo esc_attr($max_courses); ?>">
            <div class="tg-redeem-columns" style="display:grid;grid-template-columns: 2fr 1fr;gap:20px;align-items:start;">
                <div class="tg-redeem-grid-wrap">
                    <?php self::load_template('templates/redeem-grid.php', $gift_data); ?>
                </div>
                <div class="tg-redeem-selected-wrap" style="">
                    <?php self::load_template('templates/redeem-selected.php', $gift_data); ?>
                </div>
            </div>
        </div>
<?php
        return $content . ob_get_clean();
    }

    private static function load_template($rel_path, $gift_data = [])
    {

        // Convert array keys into variables for template
        if (!empty($gift_data) && is_array($gift_data)) {
            extract(['gift_data' => $gift_data]);
        }

        // Allow theme override
        $theme_path = locate_template($rel_path);
        if ($theme_path) {
            include $theme_path;
            return;
        }

        $plugin_path = TG_PATH . $rel_path;
        if (file_exists($plugin_path)) {
            include $plugin_path;
        } else {
            echo '<p>Template not found: ' . esc_html($rel_path) . '</p>';
        }
    }

    public function tg_redeem_course_ajax_handler()
    {

        $record_id   = intval($_POST['record_id'] ?? 0);      // ID trong bảng tg_giftcard_users
        $giftcard_id = intval($_POST['id'] ?? 0);               // ID thẻ quà tặng
        $giftcode    = sanitize_text_field($_POST['giftcode'] ?? '');
        $selected_ids = isset($_POST['selected_ids']) && is_array($_POST['selected_ids'])
            ? array_map('intval', $_POST['selected_ids'])
            : [];

        // Validate
        if (!$record_id || !$giftcard_id || empty($giftcode)) {
            wp_send_json_error([
                'message' => 'Thiếu giftcard ID hoặc giftcode.'
            ], 400);
        }

        if (empty($selected_ids)) {
            wp_send_json_error([
                'message' => 'Chưa chọn khóa học nào.'
            ], 400);
        }

        $user_id = get_current_user_id();
        $check = TG_Utils::validate_user_giftcard($user_id, $giftcard_id, $record_id);
        if (!$check['valid']) {
            wp_send_json_error([
                'message' => $check['message']
            ], 400);
        }

        $max_course_price = get_post_meta($giftcard_id, '_tg_max_amount', true);
        $fixed_course_list = get_post_meta($giftcard_id, '_tg_specific_courses', true);
        $exclude_course_list = get_post_meta($giftcard_id, '_tg_excluded_courses', true);
        $allow_any_course = get_post_meta($giftcard_id, '_tg_allow_all_courses', true);
        $max_courses = get_post_meta($giftcard_id, '_tg_max_courses', true) ?: 1;

        if (count($selected_ids) > intval($max_courses)) {
            wp_send_json_error([
                'message' => 'Chỉ được chọn tối đa ' . intval($max_courses) . ' khóa học.'
            ], 400);
        }

        if (!empty($fixed_course_list)) {
            $allowed_ids = array_filter(array_map('intval', explode(',', $fixed_course_list)));
            foreach ($selected_ids as $cid) {
                if (!in_array($cid, $allowed_ids)) {
                    wp_send_json_error([
                        'message' => 'Khóa học ID ' . esc_html($cid) . ' không được phép chọn.'
                    ], 400);
                }
            }
        } else {
            $excluded = array_filter(array_map('intval', explode(',', $exclude_course_list ?? '')));
            foreach ($selected_ids as $cid) {
                if (in_array($cid, $excluded)) {
                    wp_send_json_error([
                        'message' => 'Khóa học ID ' . esc_html($cid) . ' không được phép chọn.'
                    ], 400);
                }

                if (!$allow_any_course && !empty($max_course_price)) {
                    // TODO: check course price                    
                }
            }
        }

        // Enroll user vào các khóa đã chọn
        foreach ($selected_ids as $course_id) {
            tutor_utils()->do_enroll($course_id, 0, $user_id);
        }

        // Cập nhật trạng thái thẻ đã sử dụng nếu cần
        TG_Utils::mark_giftcard_used_by_id($record_id);

        // Nếu thành công
        wp_send_json_success([
            'message'      => 'Redeem thành công!',
            'giftcard_id'  => $giftcard_id,
            'giftcode'     => $giftcode,
            'selected_ids' => $selected_ids
        ]);
    }
}
