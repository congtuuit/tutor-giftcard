<?php
if ( ! defined('ABSPATH') ) exit;

class TG_Shortcodes {
    public static function init(){
        add_shortcode('tutor_giftcards', [__CLASS__, 'render_user_giftcards']); // trang quà belong to user

        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

        add_shortcode( 'tg_course_selector', [__CLASS__, 'tg_course_selector_shortcode'] );

        add_shortcode('tg_course_giftcards', [__CLASS__, 'render_course_giftcards']);

    }

    public static function enqueue_assets(){
        wp_enqueue_style('tg-frontend-css', plugins_url('../assets/css/frontend.css', __FILE__), array(), "1.2");
        wp_enqueue_script('tg-frontend-js', plugins_url('../assets/js/frontend.js', __FILE__), ['jquery'], "1.0", true);
       
    }

    /**
     * Hiển thị danh sách thẻ quà tặng người dùng sở hữu
     */
    public static function render_user_giftcards($atts) {
        if (!is_user_logged_in()) {
            return '<p>Bạn cần <a href="' . wp_login_url(get_permalink()) . '">đăng nhập</a> để xem thẻ quà tặng.</p>';
        }

        $user_id = get_current_user_id();
        //$giftcards = TG_Utils::get_giftcards_by_user($user_id);
        $giftcards = TG_Utils::get_giftcards_by_user_include_tgid($user_id);

        if (empty($giftcards)) {
            return '<p>Bạn chưa có thẻ quà tặng nào.</p>';
        }

        ob_start();

        echo '<div><h1>Thẻ quà tặng của bạn</h1></div>';
        echo '<div class="tg-card-list" style="display:grid;gap:20px;">';

        foreach ($giftcards as $item) {
            $post_id = $item['post']->ID;

            $record_id = $item['record_id']; // ID trong bảng tg_giftcard_users
            $used      = $item['used'];
            $used_at   = $item['used_at'];

            $title        = get_the_title($post_id);
            $desc         = get_the_excerpt($post_id);
            $code         = get_post_meta($post_id, '_tg_gift_card_code', true);
            $expire_date  = get_post_meta($post_id, '_tg_expire_date', true);
            $conditions   = get_post_meta($post_id, '_tg_conditions', true);
            $status       = get_post_meta($post_id, '_tg_status', true) ?: 'active';

            // Kiểm tra hạn sử dụng
            $now = date('Y-m-d');
            if (!empty($expire_date) && $expire_date < $now) {
                $status = 'expired';
            }

            if ($used) {
                $status = 'Đã sử dụng';
            }

            $_expire_date = new DateTime($expire_date);

            // Link đổi quà
            $redeem_link = add_query_arg(
                [
                    'tg_code' => rawurlencode($code),
                    'tg_id'   => rawurlencode($post_id), // hoặc giá trị khác nếu cần
                    'tg_gcid' => rawurlencode($record_id), // ID trong bảng tg_user_giftcards
                ],
                site_url('/redeem-giftcard')
            );
            ?>
            <div class="tg-card <?php echo esc_attr($status); ?>" style="border:1px solid #e0e0e0;padding:20px;border-radius:12px;background:#fff;box-shadow:0 2px 6px rgba(0,0,0,0.05);margin-bottom:16px;">
                <h3 style="margin:0 0 12px;font-size:1.25rem;color:#333;"><?php echo esc_html($title); ?></h3>
                
                <?php if ($desc): ?>
                    <p style="margin:0 0 12px;color:#555;font-size:0.95rem;"><?php echo esc_html($desc); ?></p>
                <?php endif; ?>
                
                <ul style="list-style:none;padding:0;margin:0 0 16px;color:#555;font-size:0.95rem;">
                    <li><strong>Mã thẻ:</strong> <?php echo esc_html($code); ?></li>
                    <li><strong>Ngày hết hạn:</strong> <?php echo $_expire_date->format('d/m/Y') ?: 'Không giới hạn'; ?></li>
                    <li><strong>Điều kiện sử dụng:</strong> <?php echo $conditions ? esc_html($conditions) : 'Không có điều kiện đặc biệt.'; ?></li>
                </ul>

                <?php if ($status === 'active'): ?>
                    <a class="tg-btn" href="<?php echo esc_url($redeem_link); ?>" style="display:inline-block;background:#2f8f2f;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none;font-weight:500;transition:all 0.2s ease;">🎁 Đổi quà</a>
                <?php else: ?>
                    <button class="tg-btn" disabled style="background:#ccc;padding:10px 18px;border-radius:8px;color:#666;font-weight:500;border:none;">Hết hạn</button>
                <?php endif; ?>
            </div>

            <?php
        }

        echo '</div>';
        return ob_get_clean();
    }

    public function tg_get_all_courses(array $args = array() ): array {
        $default_args = array(
            'post_type'      => 'courses',
            'post_status'    => 'publish',
            'no_found_rows'  => true,
            'posts_per_page' => -1,
        );

        $query_args = wp_parse_args( $args, $default_args );
        $query = new \WP_Query( $query_args );

        $posts = $query->have_posts() ? $query->posts : [];
        wp_reset_postdata();

        return $posts;
    }

    /**
     * Hiển thị component chọn khóa học (multiple select) cho admin.
     *
     * @param string $field_name       Tên input (dùng cho thuộc tính 'name').
     * @param array  $selected_courses Array các Course ID đã được chọn.
     * @return void
     */
    public function tg_course_select_component( string $field_name, array $selected_courses = array() ): void {
        // Lấy tất cả khóa học
        $courses = $this->tg_get_all_courses();
        
        ?>

        <select 
            name="<?php echo esc_attr($field_name); ?>[]" 
            multiple 
            class="tg-course-select"
        >
            <?php if ( !empty($courses) ) : ?>
                <?php foreach ($courses as $course) :
                    $is_selected = in_array( (int)$course->ID, array_map('intval', $selected_courses) );
                ?>
                    <option value="<?php echo esc_attr($course->ID); ?>" <?php selected( $is_selected ); ?>>
                        <?php
                            $title = $course->post_title;
                            if ( mb_strlen($title) > 100 ) {
                                $title = mb_substr($title, 0, 100) . '…';
                            }
                            echo esc_html($title);
                            ?>
                    </option>
                <?php endforeach; ?>
                <?php else: ?>
                    <option value="">Không có khóa học nào</option>
                <?php endif; ?>
        </select>
        <p style="margin:3px 0 0 0; font-size:12px; color:#555;">
            Có thể gõ tên khóa học để tìm kiếm nhanh.
        </p>

        <?php
    }

    /**
     * Shortcode callback để hiển thị component chọn khóa học.
     *
     * Shortcode: [tg_course_selector]
     *
     * @param array $atts Các thuộc tính của shortcode.
     * @return string HTML của component.
     */
    public static function tg_course_selector_shortcode( $atts ) {
        $atts = shortcode_atts(
            array(
                'field_name'   => 'selected_courses', // Tên trường mặc định
                'selected_ids' => array(),            // Danh sách ID đã chọn (mảng)
            ),
            $atts,
            'tg_course_selector'
        );

        // Nếu là chuỗi, chuyển thành mảng int
        if (!empty($atts['selected_ids']) && is_string($atts['selected_ids'])) {
            $selected_courses_array = array_map('intval', explode(',', $atts['selected_ids']));
        } elseif (!empty($atts['selected_ids']) && is_array($atts['selected_ids'])) {
            $selected_courses_array = array_map('intval', $atts['selected_ids']);
        } else {
            $selected_courses_array = [];
        }

        // Chỉ để debug, bạn có thể xóa dòng này nếu không cần
        //echo json_encode($selected_courses_array);

        $instance = new self();

        // Bắt đầu buffer để lấy output HTML
        ob_start();
        $instance->tg_course_select_component($atts['field_name'], $selected_courses_array);

        return ob_get_clean();
    }

    

    public static function render_course_giftcards() {
        $course_id = get_the_ID();
        $giftcard_courses = TG_Utils::get_giftcard_courses_by_course($course_id);

        if (empty($giftcard_courses)) {
            return '';
        }

        $html = '<div class="tg-giftcard-wrapper">';
        $html .= '<h2 class="tg-giftcard-list-heading">🎁 Các Thẻ Quà Tặng Áp Dụng</h2>';

        foreach ($giftcard_courses as $rec) {
            $giftcard_id = (int) $rec['giftcard_id'];
            $post = get_post($giftcard_id);

            if (!$post) continue;

            // Lấy Mã code từ post meta (dữ liệu duy nhất có sẵn ngoài tiêu đề)
            $code = get_post_meta($giftcard_id, '_tg_code', true);
            $link = get_permalink($giftcard_id);

            $html .= '<div class="tg-giftcard-item">';
            $html .= '<h3 class="tg-giftcard-title"><a target="_blank" href="'.$link.'">' . esc_html($post->post_title) . '</a></h3>';
            
            if ($code) {
                $html .= '<div class="tg-giftcard-footer">';
                $html .= '<strong>Mã Áp Dụng:</strong> <span class="tg-giftcard-code">' . esc_html($code) . '</span>';
                // Thêm nút và sử dụng class .button .button-primary
                $html .= '<button class="tg-copy-btn button button-primary" data-code="' . esc_attr($code) . '">Sao chép Mã</button>';
                $html .= '</div>'; // .tg-giftcard-footer
            }

            $html .= "<span>Khi mua khóa học sẽ tặng kèm</span>"; // spacer

            $html .= '</div>'; // .tg-giftcard-item
        }

        $html .= '</div>'; // .tg-giftcard-wrapper

        return $html;
    }

}
