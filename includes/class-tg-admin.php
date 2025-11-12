<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TG_Admin {

    public function __construct() {
        // Đăng ký custom post type
        add_action( 'init', [ $this, 'register_giftcard_post_type' ] );

        // Thêm metabox cho GiftCard
        add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] );

        // Lưu dữ liệu metabox
        add_action( 'save_post_tutor_giftcard', [ $this, 'save_meta_boxes' ] );

        // Cột hiển thị trong admin list
        add_filter( 'manage_tutor_giftcard_posts_columns', [ $this, 'set_custom_columns' ] );
        add_action( 'manage_tutor_giftcard_posts_custom_column', [ $this, 'render_custom_columns' ], 10, 2 );
   
        wp_enqueue_style( 'select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css' );
        wp_enqueue_script( 'select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], null, true );
        
        
    }

    /**
     * Đăng ký post type Gift Card
     */
    public function register_giftcard_post_type() {
        $labels = [
            'name'               => __( 'Thẻ quà tặng', 'tutor-giftcard' ),
            'singular_name'      => __( 'Thẻ quà tặng', 'tutor-giftcard' ),
            'add_new'            => __( 'Thêm thẻ mới', 'tutor-giftcard' ),
            'add_new_item'       => __( 'Thêm thẻ quà tặng', 'tutor-giftcard' ),
            'edit_item'          => __( 'Sửa thẻ quà tặng', 'tutor-giftcard' ),
            'new_item'           => __( 'Thẻ mới', 'tutor-giftcard' ),
            'view_item'          => __( 'Xem thẻ quà tặng', 'tutor-giftcard' ),
            'search_items'       => __( 'Tìm thẻ quà tặng', 'tutor-giftcard' ),
            'not_found'          => __( 'Không có thẻ nào', 'tutor-giftcard' ),
            'menu_name'          => __( 'Thẻ Quà Tặng', 'tutor-giftcard' ),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'menu_icon' => 'dashicons-heart',
            'supports'           => ['title', 'editor'],
            'has_archive'        => false,
            'rewrite'            => false,
        ];

        register_post_type( 'tutor_giftcard', $args );
    }

    /**
     * Thêm meta box cho Gift Card
     */
    public function register_meta_boxes() {
        add_meta_box(
            'tg_giftcard_meta',
            __( 'Thông tin thẻ quà tặng', 'tutor-giftcard' ),
            [ $this, 'render_meta_box' ],
            'tutor_giftcard',
            'normal',
            'high'
        );
    }

    /**
     * Hiển thị nội dung meta box
     */
    public function render_meta_box( $post ) {
        $course_selection_component = plugin_dir_path( __FILE__ ) . '../components/course-selection.php';
        if ( file_exists( $course_selection_component ) ) {
            include $course_selection_component;
        }

        wp_nonce_field( 'tg_save_giftcard_meta', 'tg_giftcard_nonce' );

        $fields = [
            'gift_card_code'   => get_post_meta( $post->ID, '_tg_gift_card_code', true ),
            'status'           => get_post_meta( $post->ID, '_tg_status', true ),
            'expire_date'      => get_post_meta( $post->ID, '_tg_expire_date', true ),
            'limit_per_user'   => get_post_meta( $post->ID, '_tg_limit_per_user', true ),
            'max_amount'       => get_post_meta( $post->ID, '_tg_max_amount', true ),
            'specific_courses' => get_post_meta( $post->ID, '_tg_specific_courses', true ),
            'excluded_courses' => get_post_meta( $post->ID, '_tg_excluded_courses', true ),
            'allow_all_courses'=> get_post_meta( $post->ID, '_tg_allow_all_courses', true ),
            'max_courses'      => get_post_meta( $post->ID, '_tg_max_courses', true ),
        ];
        ?>

        <style>
            .tg-meta-section {
                background: #f9fafc;
                border: 1px solid #e2e4e7;
                border-radius: 6px;
                margin-bottom: 15px;
                padding: 10px 15px;
            }
            .tg-meta-section h3 {
                margin-top: 0;
                color: #1d2327;
                border-bottom: 1px solid #dcdfe4;
                padding-bottom: 5px;
            }
            .tg-meta-table th {
                width: 200px;
                vertical-align: top;
                padding-top: 10px;
            }
            .tg-meta-table input[type="text"],
            .tg-meta-table input[type="number"],
            .tg-meta-table input[type="date"] {
                width: 100%;
                max-width: 320px;
            }
            .tg-meta-table input[type="checkbox"] {
                transform: scale(1.2);
                margin-right: 6px;
            }

            .select2-container--default .select2-selection--multiple .select2-selection__choice,
                .select2-container--default .select2-selection--single .select2-selection__rendered {
                    max-width: 100%;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }

                .select2-results__option {
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }

                .select2-results__option[title]:hover::after {
                    content: attr(title);
                    position: absolute;
                    background: #333;
                    color: #fff;
                    padding: 4px 8px;
                    border-radius: 4px;
                    white-space: normal;
                    z-index: 9999;
                }


            @media (max-width: 782px) {
                .tg-meta-table th { width: auto; display: block; }
                .tg-meta-table td { display: block; }
            }
        </style>

        <!-- 🧾 Thông tin cơ bản -->
        <div class="tg-meta-section">
            <h3>🧾 Thông tin cơ bản</h3>
            <table class="form-table tg-meta-table">
                <tr>
                    <th><label for="tg_gift_card_code">Mã thẻ</label></th>
                    <td><input type="text" name="tg_gift_card_code" value="<?php echo esc_attr($fields['gift_card_code']); ?>" placeholder="VD: ABC123"></td>
                </tr>
                <tr>
                    <th><label for="tg_status">Trạng thái</label></th>
                    <td>
                        <select name="tg_status" id="tg_status">
                            <option value="active" <?php selected($fields['status'], 'active'); ?>>Kích hoạt</option>
                            <option value="inactive" <?php selected($fields['status'], 'inactive'); ?>>Tạm dừng</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="tg_expire_date">Ngày hết hạn</label></th>
                    <td><input type="date" name="tg_expire_date" value="<?php echo esc_attr($fields['expire_date']); ?>"></td>
                </tr>
                <tr>
                    <th><label for="tg_limit_per_user">Giới hạn / user</label></th>
                    <td><input type="number" name="tg_limit_per_user" value="<?php echo esc_attr($fields['limit_per_user']); ?>" placeholder="0 = không giới hạn"></td>
                </tr>
            </table>
        </div>

        <!-- 🎯 Điều kiện áp dụng -->
        <div class="tg-meta-section">
            <h3>🎯 Điều kiện áp dụng</h3>
            <table class="form-table tg-meta-table">
                <tr>
                    <th><label for="tg_allow_all_courses">Áp dụng cho tất cả khóa học</label></th>
                    <td><label><input type="checkbox" name="tg_allow_all_courses" value="1" <?php checked($fields['allow_all_courses'], '1'); ?>> Có, áp dụng toàn bộ</label></td>
                </tr>
                <tr>
                    <th><label for="tg_max_amount">Giới hạn giá khóa học (VNĐ)</label></th>
                    <td><input type="number" name="tg_max_amount" value="<?php echo esc_attr($fields['max_amount']); ?>" placeholder="0 = không giới hạn"></td>
                </tr>
               
                <tr>
                    <th colspan="2" style="padding: 10px 0;">
                        <div style="display: flex; flex-direction: column; gap: 6px;">
                            <label for="tg_specific_courses" style="font-weight: 600; margin-bottom: 2px;">
                                Danh sách khóa học cố định
                            </label>
                            <?php
                                // Chuỗi shortcode bạn muốn gọi
                                $shortcode_string = '[tg_course_selector field_name="tg_specific_courses"]';

                                // Gọi hàm do_shortcode() để xử lý chuỗi và lấy kết quả HTML
                                $course_selector_html = do_shortcode( $shortcode_string );

                                // In HTML ra màn hình
                                echo $course_selector_html;

                                // Ví dụ đầy đủ nếu bạn muốn truyền thêm tham số 'selected'
                                /*
                                $selected_ids = "10,25,30"; 
                                $shortcode_with_selected = '[tg_course_selector field_name="tg_specific_courses" selected="' . esc_attr($selected_ids) . '"]';
                                echo do_shortcode( $shortcode_with_selected );
                                */
                            ?>
                        </div>
                    </th>
                </tr>

                <tr>
                   <th colspan="2" style="padding: 10px 0;">
                    <div style="display: flex; flex-direction: column; gap: 6px;">
                        <label for="tg_excluded_courses" style="font-weight: 600; margin-bottom: 2px;">
                            Khóa học không áp dụng
                        </label>
                        <?php
                            // Chuỗi shortcode bạn muốn gọi
                            $shortcode_string = '[tg_course_selector field_name="tg_excluded_courses"]';

                            // Gọi hàm do_shortcode() để xử lý chuỗi và lấy kết quả HTML
                            $course_selector_html = do_shortcode( $shortcode_string );

                            // In HTML ra màn hình
                            echo $course_selector_html;

                            // Ví dụ đầy đủ nếu bạn muốn truyền thêm tham số 'selected'
                            /*
                            $selected_ids = "10,25,30"; 
                            $shortcode_with_selected = '[tg_course_selector field_name="tg_excluded_courses" selected="' . esc_attr($selected_ids) . '"]';
                            echo do_shortcode( $shortcode_with_selected );
                            */
                        ?>
                        </div>

                    </th>
                </tr>

            </table>
        </div>

        <!-- 🎁 Giới hạn khóa học -->
        <div class="tg-meta-section">
            <h3>🎁 Giới hạn khóa học có thể nhận</h3>
            <table class="form-table tg-meta-table">
                <tr>
                    <th><label for="tg_max_courses">Số lượng tối đa</label></th>
                    <td><input type="number" name="tg_max_courses" value="<?php echo esc_attr($fields['max_courses'] ?: 1); ?>"></td>
                </tr>
            </table>
        </div>


        <script>
            jQuery(document).ready(function($){
                $('.tg-course-select').select2({
                    placeholder: 'Chọn khóa học...',
                    allowClear: true,
                    width: '100%',
                    language: {
                        noResults: function() { return 'Không tìm thấy khóa học nào'; }
                    }
                    templateResult: function (data) {
                        if (!data.id) return data.text;
                        return $('<span title="' + data.text + '">' + data.text + '</span>');
                    },
                    templateSelection: function (data) {
                        return $('<span title="' + data.text + '">' + data.text + '</span>');
                    }
                });
            });
        </script>
        <?php
    }


    /**
     * Lưu dữ liệu meta box
     */
    public function save_meta_boxes( $post_id ) {
        if ( ! isset( $_POST['tg_giftcard_nonce'] ) || ! wp_verify_nonce( $_POST['tg_giftcard_nonce'], 'tg_save_giftcard_meta' ) ) {
            return;
        }

        $fields = [
            '_tg_gift_card_code' => sanitize_text_field( $_POST['tg_gift_card_code'] ?? '' ),
            '_tg_status' => sanitize_text_field( $_POST['tg_status'] ?? '' ),
            '_tg_expire_date' => sanitize_text_field( $_POST['tg_expire_date'] ?? '' ),
            '_tg_limit_per_user' => intval( $_POST['tg_limit_per_user'] ?? 0 ),
            '_tg_max_amount' => floatval( $_POST['tg_max_amount'] ?? 0 ),
            '_tg_allow_all_courses' => isset( $_POST['tg_allow_all_courses'] ) ? '1' : '0',
            '_tg_specific_courses' => sanitize_text_field( $_POST['tg_specific_courses'] ?? '' ),
            '_tg_excluded_courses' => sanitize_text_field( $_POST['tg_excluded_courses'] ?? '' ),
            '_tg_max_courses' => intval( $_POST['tg_max_courses'] ?? 1 ),
        ];

        foreach ( $fields as $key => $value ) {
            update_post_meta( $post_id, $key, $value );
        }
    }

    /**
     * Hiển thị cột trong admin list
     */
    public function set_custom_columns( $columns ) {
        $new = [];
        foreach ( $columns as $key => $title ) {
            if ( $key == 'date' ) {
                $new['status'] = 'Trạng thái';
                $new['expire'] = 'Hết hạn';
                $new['limit'] = 'Giới hạn / user';
            }
            $new[$key] = $title;
        }
        return $new;
    }

    public function render_custom_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'status':
                $_status = get_post_meta( $post_id, '_tg_status', true );
                echo esc_html( $_status == "active" ? "Hoạt động" : $_status );
                break;
            case 'expire':
                echo esc_html( get_post_meta( $post_id, '_tg_expire_date', true ) );
                break;
            case 'limit':
                echo esc_html( get_post_meta( $post_id, '_tg_limit_per_user', true ) );
                break;
        }
    }
}
