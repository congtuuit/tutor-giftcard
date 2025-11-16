<?php
if ( ! defined('ABSPATH') ) exit;

class TG_Frontend {
    public static function init(){
        //add_action('template_redirect', [__CLASS__, 'maybe_render_redeem_page']);
        add_action('wp_ajax_tg_claim_code', [__CLASS__, 'ajax_claim_code']);
        add_action('wp_ajax_nopriv_tg_claim_code', [__CLASS__, 'ajax_claim_code']);
        add_action('init', [ __CLASS__, 'maybe_create_my_giftcard_page' ]);

        // Hook vào danh sách bottom nav của Tutor LMS
        add_filter( 'tutor_dashboard/bottom_nav_items', [__CLASS__ ,'tg_add_giftcard_menu'] );
    }

    public function maybe_create_my_giftcard_page() {
        // chỉ chạy khi trong admin (tránh người dùng thường truy cập gây check DB liên tục)
        if (is_admin()) {
            //TG_Utils::ensure_my_giftcard_page_exists();
            //TG_Utils::ensure_giftcard_claim_page_exists();

            // Tạo bảng tg_giftcard_courses nếu chưa tồn tại
            //TG_Utils::tg_create_giftcard_course_table();
        }
    }

    // Menu ở trang front end sau khi đăng nhập
    public function tg_add_giftcard_menu( $nav_items ) {

        // Thêm menu "Thẻ quà tặng" trước mục Settings (hoặc bạn có thể tùy chỉnh vị trí)
        $new_item = array(
            'giftcard' => array(
                'title' => __( 'Thẻ quà tặng của tôi', 'tg' ),
                'icon'  => 'tutor-icon-badge-star', // hoặc chọn icon khác, có sẵn trong Tutor icon pack
                'url'   => site_url( '/my-gift-card' ), // đường dẫn trang của bạn
            ),
        );

        // Bạn có thể chèn vào trước 'settings' nếu muốn
        $position = array_search( 'settings', array_keys( $nav_items ) );
        if ( $position !== false ) {
            $before  = array_slice( $nav_items, 0, $position, true );
            $after   = array_slice( $nav_items, $position, null, true );
            $nav_items = $before + $new_item + $after;
        } else {
            $nav_items['giftcard'] = $new_item['giftcard'];
        }

        return $nav_items;
    }


    // UI FRONT END
    // Render trang claim quà
    public static function maybe_render_redeem_page(){
        if (is_page('redeem-giftcard')) {
            add_filter('the_content', [__CLASS__, 'render_redeem_content']);
        }
    }

    // UI FRONT END
    // Trang claim quà
    public static function render_redeem_content($content){
        if (!is_user_logged_in()) return '<p>Vui lòng đăng nhập để đổi quà.</p>';

        $code = isset($_GET['tg_code']) ? sanitize_text_field($_GET['tg_code']) : '';
        $giftcard_id = isset($_GET['tg_id']) ? intval($_GET['tg_id']) : 0;

        if (!$code || !$giftcard_id) return '<p>Thiếu thông tin thẻ.</p>';

        $user_id = get_current_user_id();

        // Lấy giftcard theo ID
        $post = get_post($giftcard_id);
        if (!$post || $post->post_type !== 'tutor_giftcard') return '<p>Không tìm thấy thẻ.</p>';

        $post_code = get_post_meta($giftcard_id, '_tg_gift_card_code', true);
        if ($post_code !== $code) return '<p>Code thẻ không đúng.</p>';

        // Kiểm tra user có được gán thẻ này
        $assigned_users = TG_Utils::get_assigned_users($giftcard_id);
        if (!in_array($user_id, $assigned_users)) return '<p>Bạn không có quyền sử dụng thẻ này.</p>';

        // Lấy meta giftcard
        $status = get_post_meta($giftcard_id, '_tg_status', true) ?: 'active';
        $expire_date = get_post_meta($giftcard_id, '_tg_expire_date', true);
        $max_courses = get_post_meta($giftcard_id, '_tg_max_courses', true) ?: 1;
        $meta = [
            'fixed_course_list' => get_post_meta($giftcard_id, '_tg_specific_courses', true),
            'exclude_course_list' => get_post_meta($giftcard_id, '_tg_excluded_courses', true),
            'allow_any_course' => get_post_meta($giftcard_id, '_tg_allow_all_courses', true),
            'max_price' => get_post_meta($giftcard_id, '_tg_max_amount', true),
        ];

        // Kiểm tra hạn sử dụng
        $now = date('Y-m-d');
        if (!empty($expire_date) && $expire_date < $now) $status = 'expired';
        if ($status !== 'active') return '<p>Thẻ không thể sử dụng (trạng thái: '.esc_html($status).').</p>';

        // Build query courses
        $post_type = apply_filters('tg_course_post_type', 'courses');
        $excluded = array_filter(array_map('intval', explode(',', $meta['exclude_course_list'] ?? '')));

        if (!empty($meta['fixed_course_list'])) {
            $ids = array_filter(array_map('intval', explode(',', $meta['fixed_course_list'])));
            $args = ['post_type'=>$post_type,'post__in'=>$ids,'posts_per_page'=>-1];
        } else {
            $args = ['post_type'=>$post_type,'posts_per_page'=>-1,'post__not_in'=>$excluded];
            if (!empty($meta['max_price']) && !$meta['allow_any_course']){
                $price_meta_key = apply_filters('tg_course_price_meta_key','_price');
                $args['meta_query'] = [
                    [
                        'key' => $price_meta_key,
                        'value' => $meta['max_price'],
                        'compare' => '<=',
                        'type' => 'NUMERIC'
                    ]
                ];
            }
        }

        $q = new WP_Query($args);

        ob_start(); ?>
        <div id="tg-redeem-app" data-code="<?php echo esc_attr($code); ?>" data-max="<?php echo esc_attr($max_courses); ?>">
            <h1>Đổi quà: <?php echo esc_html($post->post_title); ?></h2>
            <p><?php echo nl2br(esc_html($post->post_excerpt)); ?></p>

            <div class="tg-course-list" style="display:grid;gap:16px;">
            <?php if($q->have_posts()): while($q->have_posts()): $q->the_post();
                $pid = get_the_ID();
                $price = get_post_meta($pid, apply_filters('tg_course_price_meta_key','_price'), true);
                $thumbnail = get_the_post_thumbnail_url($pid, 'medium') ?: 'https://placehold.co/150x150';
                $link = get_permalink($pid);
            ?>
                <div class="tg-course-item" data-id="<?php echo esc_attr($pid); ?>" style="border:1px solid #ddd;padding:10px;border-radius:6px;display:flex;gap:10px;align-items:center;">
                    <div><input style="height: 25px; width: 25px; cursor: pointer;" type="checkbox" class="tg-course-checkbox" value="<?php echo esc_attr($pid); ?>"/></div>    

                    <div class="tg-course-thumb" style="flex-shrink:0;">
                        <a href="<?php echo esc_url($link); ?>" target="_blank">
                            <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php the_title_attribute(); ?>" style="width:80px;height:80px;object-fit:cover;border-radius:6px;">
                        </a>
                    </div>
                    <div class="tg-course-info" style="flex:1;">
                        <label style="display:flex;align-items:center;gap:6px;">
                            
                            <a href="<?php echo esc_url($link); ?>" target="_blank" style="font-weight:600;text-decoration:none;color:#333;">
                                <?php the_title(); ?>
                            </a>
                        </label>
                        <div class="tg-course-price" style="margin-top:4px;font-size:0.9em;color:#666;">
                            <?php echo esc_html($price ? number_format($price,0,',','.') . ' VNĐ' : 'Free'); ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; wp_reset_postdata(); else: ?>
                    <p>Không có khóa học phù hợp với thẻ này.</p>
                <?php endif; ?>
            </div>

            <h3 style="margin-top: 20px;">Đang chọn (<span id="tg-selected-count">0</span>/<?php echo esc_html($max_courses); ?>)</h3>
            <div id="tg-selected-list" style="margin-bottom:10px;"></div>

            <button id="tg-confirm-redeem" disabled style="cursor: pointer; padding:10px 16px;background:#2f8f2f;color:#fff;border:none;border-radius:6px;">Xác nhận đổi</button>
            <div id="tg-redeem-result" style="margin-top:12px;"></div>
        </div>

        <script>
        jQuery(document).ready(function($){
            const maxCourses = parseInt($('#tg-redeem-app').data('max'));
            $('.tg-course-checkbox').on('change', function(){
                let selected = $('.tg-course-checkbox:checked');
                if(selected.length > maxCourses){
                    $(this).prop('checked', false);
                    alert('Bạn chỉ được chọn tối đa ' + maxCourses + ' khóa học.');
                    return;
                }
                $('#tg-selected-count').text(selected.length);
                let list = '';
                selected.each(function(){ list += '<div>'+$(this).parent().text()+'</div>'; });
                $('#tg-selected-list').html(list);
                $('#tg-confirm-redeem').prop('disabled', selected.length === 0);
            });
        });
        </script>
        <?php

        return $content . ob_get_clean();
    }


    // Simple AJAX claim example (optional — but we will prefer REST endpoint)
    public static function ajax_claim_code(){
        check_ajax_referer('tg_frontend_nonce', 'nonce');
        if (!is_user_logged_in()){
            wp_send_json_error('Bạn cần đăng nhập.');
        }
        $code = sanitize_text_field($_POST['tg_code'] ?? '');
        if (!$code) wp_send_json_error('Thiếu code.');
        $user_id = get_current_user_id();
        $cards = get_user_meta($user_id, 'tg_user_giftcards', true) ?: [];

        // TODO: you may want to validate code against a global list or external API.
        // For demo: we just create a user-giftcard from a template if template_id provided by admin
        // This simplified example expects admin created a template with slug==code -> find it.
        $template = get_page_by_title($code, OBJECT, 'tg_card');
        if (!$template) {
            wp_send_json_error('Không tìm thấy template tương ứng với code.');
        }

        $meta = get_post_meta($template->ID, '_tg_card_meta', true);
        $newCard = [
            'code' => $code,
            'template_id' => $template->ID,
            'title' => $template->post_title,
            'description' => $template->post_content,
            'status' => 'active',
            'claimed_at' => current_time('mysql'),
            'expires_at' => !empty($meta['end_date']) ? $meta['end_date'] : '',
            'usage_limit_per_user' => $meta['usage_limit_per_user'] ?? '',
            'used_count' => 0,
            'meta' => $meta,
            'max_courses' => $meta['max_courses'] ?? 1,
        ];

        // Prevent double claim same code per user
        foreach ($cards as $c) if ($c['code'] === $code) wp_send_json_error('Bạn đã claim code này trước đó.');

        $cards[] = $newCard;
        update_user_meta($user_id, 'tg_user_giftcards', $cards);
        wp_send_json_success('Claim thành công.');
    }
}
