<?php
if ( ! defined('ABSPATH') ) exit;

class TG_Shortcodes {
    public static function init(){
        add_shortcode('tutor_giftcards', [__CLASS__, 'render_user_giftcards']);
        add_shortcode('tutor_giftcard_claim', [__CLASS__, 'render_claim_form']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function enqueue_assets(){
        wp_enqueue_style('tg-frontend-css', plugins_url('../assets/css/frontend.css', __FILE__));
        wp_enqueue_script('tg-frontend-js', plugins_url('../assets/js/frontend.js', __FILE__), ['jquery'], false, true);
        wp_localize_script('tg-frontend-js', 'TG_Ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => rest_url('tutor-giftcard/v1/'),
            'nonce'    => wp_create_nonce('tg_frontend_nonce'),
        ]);
    }

    /**
     * Hiển thị danh sách thẻ quà tặng người dùng sở hữu
     */
    public static function render_user_giftcards($atts){
        if (!is_user_logged_in()) {
            return '<p>Bạn cần đăng nhập để xem thẻ quà tặng.</p>';
        }

        $user_id = get_current_user_id();

        // Các thẻ user đã claim (lưu ID thẻ trong user_meta)
        $user_cards = get_user_meta($user_id, '_tg_user_cards', true);
        if (empty($user_cards) || !is_array($user_cards)) {
            return '<p>Bạn chưa có thẻ quà tặng nào.</p>';
        }

        $args = [
            'post_type' => 'tutor_giftcard',
            'post__in'  => $user_cards,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        $query = new WP_Query($args);

        if (!$query->have_posts()) {
            return '<p>Không có thẻ quà tặng nào hợp lệ.</p>';
        }

        ob_start();
        echo '<div class="tg-card-list">';
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();

            $code           = get_post_meta($post_id, '_tg_gift_card_code', true);
            $status         = get_post_meta($post_id, '_tg_status', true);
            $expire_date    = get_post_meta($post_id, '_tg_expire_date', true);
            $limit_user     = get_post_meta($post_id, '_tg_limit_per_user', true);
            $max_amount     = get_post_meta($post_id, '_tg_max_amount', true);
            $allow_all      = get_post_meta($post_id, '_tg_allow_all_courses', true);
            $specific       = get_post_meta($post_id, '_tg_specific_courses', true);
            $excluded       = get_post_meta($post_id, '_tg_excluded_courses', true);
            $max_courses    = get_post_meta($post_id, '_tg_max_courses', true) ?: 1;

            // Kiểm tra hạn sử dụng
            $now = date('Y-m-d');
            if (!empty($expire_date) && $expire_date < $now) {
                $status = 'expired';
            }

            // Hiển thị
            ?>
            <div class="tg-card <?php echo esc_attr($status); ?>">
                <h3><?php echo esc_html(get_the_title()); ?></h3>
                <p class="tg-desc"><?php echo esc_html(get_the_excerpt()); ?></p>

                <ul class="tg-meta">
                    <li><strong>Mã thẻ:</strong> <?php echo esc_html($code); ?></li>
                    <li><strong>Trạng thái:</strong> <?php echo $status === 'active' ? 'Kích hoạt' : ($status === 'expired' ? 'Hết hạn' : 'Tạm dừng'); ?></li>
                    <li><strong>Ngày hết hạn:</strong> <?php echo $expire_date ?: 'Không giới hạn'; ?></li>
                    <li><strong>Giới hạn mỗi user:</strong> <?php echo $limit_user ?: 'Không giới hạn'; ?></li>
                    <li><strong>Giá khóa học tối đa:</strong> <?php echo $max_amount ? number_format($max_amount, 0, ',', '.') . ' VNĐ' : 'Không giới hạn'; ?></li>
                    <li><strong>Số khóa học tối đa được đổi:</strong> <?php echo intval($max_courses); ?></li>
                </ul>

                <?php if ($status === 'active'): ?>
                    <?php
                    $redeem_link = add_query_arg('tg_code', rawurlencode($code), site_url('/redeem-giftcard'));
                    ?>
                    <a class="tg-btn" href="<?php echo esc_url($redeem_link); ?>">🎁 Đổi quà</a>
                <?php else: ?>
                    <button class="tg-btn disabled" disabled>Không thể đổi</button>
                <?php endif; ?>
            </div>
            <?php
        }
        echo '</div>';
        wp_reset_postdata();

        return ob_get_clean();
    }

    /**
     * Form claim thẻ quà tặng bằng code
     */
    public static function render_claim_form($atts){
        if (!is_user_logged_in()) {
            return '<p>Bạn cần đăng nhập để claim thẻ.</p>';
        }

        ob_start(); ?>
        <form id="tg-claim-form" class="tg-claim-form">
            <label>Nhập mã thẻ quà tặng:</label>
            <input type="text" name="tg_code" required placeholder="Nhập mã thẻ..." />
            <button type="submit">Claim thẻ</button>
            <div id="tg-claim-result"></div>
        </form>
        <?php
        return ob_get_clean();
    }
}
