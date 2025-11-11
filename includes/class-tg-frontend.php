<?php
if ( ! defined('ABSPATH') ) exit;

class TG_Frontend {
    public static function init(){
        add_action('template_redirect', [__CLASS__, 'maybe_render_redeem_page']);
        add_action('wp_ajax_tg_claim_code', [__CLASS__, 'ajax_claim_code']);
        add_action('wp_ajax_nopriv_tg_claim_code', [__CLASS__, 'ajax_claim_code']);
    }

    public static function maybe_render_redeem_page(){
        if (is_page('redeem-giftcard')) {
            add_filter('the_content', [__CLASS__, 'render_redeem_content']);
        }
    }

    public static function render_redeem_content($content){
        if (!is_user_logged_in()) return '<p>Vui lòng đăng nhập để đổi quà.</p>';
        $code = isset($_GET['tg_code']) ? sanitize_text_field($_GET['tg_code']) : '';
        if (!$code) return '<p>Thiếu code thẻ.</p>';

        $user_id = get_current_user_id();
        $cards = get_user_meta($user_id, 'tg_user_giftcards', true) ?: [];
        $card = null;
        foreach ($cards as $c) if ($c['code'] === $code) { $card = $c; break; }
        if (!$card) return '<p>Không tìm thấy thẻ này trong tài khoản của bạn.</p>';
        if ($card['status'] !== 'active') return '<p>Thẻ không thể sử dụng (trạng thái: '.esc_html($card['status']).').</p>';

        // Build WP_Query args for courses based on $card['meta']
        $meta = $card['meta'] ?? [];
        $post_type = apply_filters('tg_course_post_type', 'courses'); // configurable filter
        $excluded = array_filter(array_map('trim', explode(',', $meta['exclude_course_list'] ?? '')));

        if (!empty($meta['fixed_course_list'])) {
            $ids = array_filter(array_map('intval', explode(',', $meta['fixed_course_list'])));
            $args = ['post_type'=>$post_type,'post__in'=>$ids,'posts_per_page'=>-1];
        } else {
            $args = ['post_type'=>$post_type,'posts_per_page'=>-1, 'post__not_in' => $excluded];
            if (!empty($meta['max_price']) && !$meta['allow_any_course']){
                // common Tutor LMS price stored in meta like '_price' or 'course_price' - plugin user may configure via filter
                $price_meta_key = apply_filters('tg_course_price_meta_key', '_price');
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
        ob_start();
        ?>
        <div id="tg-redeem-app" data-code="<?php echo esc_attr($card['code']); ?>" data-max="<?php echo esc_attr($card['max_courses'] ?? 1); ?>">
            <h2>Đổi quà: <?php echo esc_html($card['title']); ?></h2>
            <p><?php echo nl2br(esc_html($card['description'] ?? '')); ?></p>
            <div class="tg-course-list">
                <?php if ($q->have_posts()): while($q->have_posts()): $q->the_post();
                    $pid = get_the_ID();
                    $price = get_post_meta($pid, apply_filters('tg_course_price_meta_key', '_price'), true);
                ?>
                <div class="tg-course-item" data-id="<?php echo $pid; ?>">
                    <label>
                        <input type="checkbox" class="tg-course-checkbox" value="<?php echo $pid; ?>"/>
                        <?php the_title(); ?> — <?php echo esc_html($price ? $price : 'Free'); ?>
                    </label>
                </div>
                <?php endwhile; wp_reset_postdata(); else: ?>
                    <p>Không có khóa học phù hợp với thẻ này.</p>
                <?php endif; ?>
            </div>

            <h3>Đang chọn</h3>
            <div id="tg-selected-list"></div>

            <button id="tg-confirm-redeem" disabled>Xác nhận đổi</button>
            <div id="tg-redeem-result"></div>
        </div>
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
