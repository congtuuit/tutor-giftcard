<?php
if (! defined('ABSPATH')) {
    exit;
}

class TG_Admin
{

    public function __construct()
    {
        // Đăng ký custom post type
        add_action('init', [$this, 'register_giftcard_post_type']);

        // Thêm metabox cho GiftCard
        add_action('add_meta_boxes', [$this, 'register_meta_boxes']);

        // Lưu dữ liệu metabox
        add_action('save_post_tutor_giftcard', [$this, 'save_meta_boxes']);

        // Cột hiển thị trong admin list
        add_filter('manage_tutor_giftcard_posts_columns', [$this, 'set_custom_columns']);
        add_action('manage_tutor_giftcard_posts_custom_column', [$this, 'render_custom_columns'], 10, 2);

        wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], null, true);


        add_action('wp_ajax_tg_search_users', function () {
            $term = sanitize_text_field($_GET['q'] ?? '');
            $args = [
                'search'         => '*' . esc_attr($term) . '*',
                'search_columns' => ['user_login', 'user_email', 'display_name'],
                'number'         => 20, // giới hạn 20 user
            ];
            $users = get_users($args);

            $results = [];
            foreach ($users as $user) {
                $results[] = [
                    'id' => $user->ID,
                    'text' => $user->display_name . ' (' . $user->user_email . ')'
                ];
            }

            wp_send_json(['results' => $results]);
        });
    }

    /**
     * Đăng ký post type Gift Card
     */
    public function register_giftcard_post_type()
    {
        $labels = [
            'name'               => __('Thẻ quà tặng', 'tutor-giftcard'),
            'singular_name'      => __('Thẻ quà tặng', 'tutor-giftcard'),
            'add_new'            => __('Thêm thẻ mới', 'tutor-giftcard'),
            'add_new_item'       => __('Thêm thẻ quà tặng', 'tutor-giftcard'),
            'edit_item'          => __('Sửa thẻ quà tặng', 'tutor-giftcard'),
            'new_item'           => __('Thẻ mới', 'tutor-giftcard'),
            'view_item'          => __('Xem thẻ quà tặng', 'tutor-giftcard'),
            'search_items'       => __('Tìm thẻ quà tặng', 'tutor-giftcard'),
            'not_found'          => __('Không có thẻ nào', 'tutor-giftcard'),
            'menu_name'          => __('Thẻ Quà Tặng', 'tutor-giftcard'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_rest' => true,
            'has_archive'        => false,
            'menu_icon' => 'dashicons-heart',
            'supports'           => ['title', 'editor'],
            //'rewrite'            => false,
            'rewrite' => [
            'slug' => 'gift-card',  // <--- đường dẫn mong muốn
            'with_front' => false
        ],
        ];

        register_post_type('tutor_giftcard', $args);

        add_action('admin_menu', function () {
            add_submenu_page(
                'edit.php?post_type=tutor_giftcard', // parent menu
                'Gán thẻ cho user',                  // page title
                'Gán thẻ cho user',                  // menu title
                'manage_options',                    // capability
                'tg-giftcard-user',                  // slug
                [TG_Admin::class, 'render_giftcard_user_page'] // callback
            );
        });
    }

    /**
     * Thêm meta box cho Gift Card
     */
    public function register_meta_boxes()
    {
        add_meta_box(
            'tg_giftcard_meta',
            __('Thông tin thẻ quà tặng', 'tutor-giftcard'),
            [$this, 'render_meta_box'],
            'tutor_giftcard',
            'normal',
            'high'
        );
    }

    /**
     * Hiển thị nội dung meta box
     */
    public function render_meta_box($post)
    {
        $course_selection_component = plugin_dir_path(__FILE__) . '../components/course-selection.php';
        if (file_exists($course_selection_component)) {
            include $course_selection_component;
        }

        wp_nonce_field('tg_save_giftcard_meta', 'tg_giftcard_nonce');
        $fields = (array) TG_Utils::get_giftcard_by_id($post->ID);


        $specific_courses = is_array($fields['specific_courses'])
            ? $fields['specific_courses']
            : ($fields['specific_courses'] ? explode(',', $fields['specific_courses']) : []);


        $excluded_courses = is_array($fields['excluded_courses'])
            ? $fields['excluded_courses']
            : ($fields['excluded_courses'] ? explode(',', $fields['excluded_courses']) : []);


        $apply_for = TG_Utils::get_giftcard_courses_by_giftcard($post->ID);
        $apply_course_ids = array_map(function($item) {
            return $item['course_id'];
        }, $apply_for);
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

            .select2-container {
                max-width: 600px !important;
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
                .tg-meta-table th {
                    width: auto;
                    display: block;
                }

                .tg-meta-table td {
                    display: block;
                }
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
                <tr style="display: none;">
                    <th><label for="tg_limit_per_user">Giới hạn sử dụng trên 1 khách hàng</label></th>
                    <td><input type="number" name="tg_limit_per_user" value="<?php echo esc_attr($fields['limit_per_user']); ?>" placeholder="0 = không giới hạn"></td>
                </tr>


                <tr>
                    <th colspan="2" style="padding: 10px 0;">
                        <div style="display: flex; flex-direction: column; gap: 6px;">
                            <label for="tg_apply_for" style="font-weight: 600; margin-bottom: 2px;">
                                Áp dụng khi mua khóa học
                            </label>
                            <?php
                            $selected_ids_str = implode(',', $apply_course_ids); // "393679,393642,393761,17799"
                            $shortcode_string = '[tg_course_selector field_name="tg_apply_for" selected_ids="' . esc_attr($selected_ids_str) . '"]';
                            echo do_shortcode($shortcode_string);
                            ?>
                        </div>
                    </th>
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
                            $selected_ids_str = implode(',', $specific_courses); // "393679,393642,393761,17799"
                            $shortcode_string = '[tg_course_selector field_name="tg_specific_courses" selected_ids="' . esc_attr($selected_ids_str) . '"]';
                            echo do_shortcode($shortcode_string);
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
                            $selected_ids_str = implode(',', $excluded_courses); // "393679,393642,393761,17799"
                            $shortcode_string = '[tg_course_selector field_name="tg_excluded_courses" selected_ids="' . esc_attr($selected_ids_str) . '"]';
                            echo do_shortcode($shortcode_string);
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
            jQuery(document).ready(function($) {
                $('.tg-course-select').select2({
                    placeholder: 'Chọn khóa học...',
                    allowClear: true,
                    width: '100%',
                    language: {
                        noResults: function() {
                            return 'Không tìm thấy khóa học nào';
                        }
                    }
                });
            });
        </script>
    <?php
    }


    /**
     * Lưu dữ liệu meta box
     */
    public function save_meta_boxes($post_id)
    {
        if (
            ! isset($_POST['tg_giftcard_nonce'])
            || ! wp_verify_nonce($_POST['tg_giftcard_nonce'], 'tg_save_giftcard_meta')
        ) {
            return;
        }

        // Lấy danh sách ID từ select2 (nếu có)
        $specific_courses = isset($_POST['tg_specific_courses'])
            ? array_map('intval', (array) $_POST['tg_specific_courses'])
            : [];

        $excluded_courses = isset($_POST['tg_excluded_courses'])
            ? array_map('intval', (array) $_POST['tg_excluded_courses'])
            : [];

        $apply_for = isset($_POST['tg_apply_for'])
            ? array_map('intval', (array) $_POST['tg_apply_for'])
            : [];

        $fields = [
            '_tg_gift_card_code'    => sanitize_text_field($_POST['tg_gift_card_code'] ?? ''),
            '_tg_status'            => sanitize_text_field($_POST['tg_status'] ?? ''),
            '_tg_expire_date'       => sanitize_text_field($_POST['tg_expire_date'] ?? ''),
            '_tg_limit_per_user'    => intval($_POST['tg_limit_per_user'] ?? 0),
            '_tg_max_amount'        => floatval($_POST['tg_max_amount'] ?? 0),
            '_tg_allow_all_courses' => isset($_POST['tg_allow_all_courses']) ? '1' : '0',
            '_tg_specific_courses'  => implode(',', $specific_courses), // chỉ lưu ID
            '_tg_excluded_courses'  => implode(',', $excluded_courses),
            '_tg_max_courses'       => intval($_POST['tg_max_courses'] ?? 1),
        ];

        // Log ra file để debug nếu cần
        $log_file = WP_CONTENT_DIR . '/tg-debug-log.txt';
        $log_data = date('Y-m-d H:i:s') . " - SAVING FIELDS:\n" . print_r($fields, true) . "\n";
        file_put_contents($log_file, $log_data, FILE_APPEND);

        // Cập nhật meta
        foreach ($fields as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }

        // Xóa các bản ghi cũ trong tg_giftcard_courses
        TG_Utils::delete_giftcard_courses_by_giftcard($post_id);

        // Lấy danh sách sản phẩm từ các khóa học được chọn
        $list = TG_Utils::get_products_by_courses($apply_for);
        // Chuẩn bị mảng course_ids và product_ids để insert
        $course_ids  = [];
        $product_ids = [];
        
        foreach ($list as $item) {
            $course_ids[]  = $item['course_id'];
            $product_ids[] = $item['product_id'];
        }

        // Gọi hàm tạo bản ghi trong tg_giftcard_courses
        TG_Utils::create_giftcard_courses($post_id, $course_ids, $product_ids);
    }



    /**
     * Hiển thị cột trong admin list
     */
    public function set_custom_columns($columns)
    {
        $new = [];
        foreach ($columns as $key => $title) {
            if ($key == 'date') {
                $new['status'] = 'Trạng thái';
                $new['expire'] = 'Hết hạn';
                $new['limit'] = 'Giới hạn / user';
            }
            $new[$key] = $title;
        }
        return $new;
    }

    public function render_custom_columns($column, $post_id)
    {
        switch ($column) {
            case 'status':
                $_status = get_post_meta($post_id, '_tg_status', true);
                echo esc_html($_status == "active" ? "Hoạt động" : $_status);
                break;
            case 'expire':
                echo esc_html(get_post_meta($post_id, '_tg_expire_date', true));
                break;
            case 'limit':
                echo esc_html(get_post_meta($post_id, '_tg_limit_per_user', true));
                break;
        }
    }

    public static function render_giftcard_user_page()
    {

        // Xử lý gán user
        if (isset($_POST['tg_assign']) && check_admin_referer('tg_assign_user_action', 'tg_assign_user_nonce')) {
            $giftcard_id = intval($_POST['giftcard_id']);
            $user_id     = intval($_POST['user_id']);

            if (TG_Utils::assign_user_to_giftcard($giftcard_id, $user_id)) {
                echo '<div class="notice notice-success"><p>Đã gán thẻ thành công!</p></div>';
            } else {
                echo '<div class="notice notice-warning"><p>User này đã có thẻ rồi.</p></div>';
            }
        }

        // Xử lý xóa user
        if (isset($_POST['tg_remove']) && check_admin_referer('tg_remove_user_action', 'tg_remove_user_nonce')) {
            $giftcard_id = intval($_POST['giftcard_id']);
            $user_id     = intval($_POST['user_id']);
            TG_Utils::remove_user_from_giftcard($giftcard_id, $user_id);
            echo '<div class="notice notice-success"><p>Đã xóa user khỏi thẻ.</p></div>';
        }

        // Lấy danh sách gift card
        $giftcards = get_posts(['post_type' => 'tutor_giftcard', 'numberposts' => -1]);

    ?>
        <h2>Gán thẻ cho user</h2>
        <form method="post" style="
            margin-bottom: 30px; 
            padding: 20px; 
            background: #fff; 
            border: 1px solid #ddd; 
            border-radius: 8px; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            display: flex; 
            flex-wrap: wrap; 
            align-items: center; 
            gap: 15px;
        ">
            <?php wp_nonce_field('tg_assign_user_action', 'tg_assign_user_nonce'); ?>

            <div style="flex: 1; min-width: 220px;">
                <label style="
                    display: block; 
                    font-weight: 600; 
                    margin-bottom: 6px; 
                    color: #23282d;
                ">
                    Chọn GiftCard
                </label>
                <select name="giftcard_id" required style="
                    width: 100%; 
                    border-radius: 6px; 
                    padding: 6px 10px; 
                    border: 1px solid #ccc;
                    min-height: 36px;
                ">
                    <?php foreach ($giftcards as $gift): ?>
                        <option value="<?php echo $gift->ID; ?>"><?php echo esc_html($gift->post_title); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="flex: 1; min-width: 220px;">
                <label style="
                    display: block; 
                    font-weight: 600; 
                    margin-bottom: 6px; 
                    color: #23282d;
                ">
                    Chọn người dùng
                </label>
                <select name="user_id" class="tg-user-select" style="
                    width: 100%; 
                    border-radius: 6px; 
                    padding: 6px 10px; 
                    border: 1px solid #ccc;
                    min-height: 36px;
                " required></select>
            </div>

            <div style="align-self: flex-end;">
                <button type="submit" name="tg_assign" class="button button-primary" style="
                    height: 36px; 
                    line-height: 34px; 
                    padding: 0 20px; 
                    border-radius: 6px;
                ">
                    Gán
                </button>
            </div>
        </form>

        <h3>Danh sách user đã được gán thẻ</h3>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th>Thẻ quà tặng</th>
                    <th>Khách hàng</th>
                    <th>Email</th>
                    <th>Ngày hết hạn</th> <!-- thêm cột -->
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($giftcards as $gift) {
                    $assigned_users = TG_Utils::get_assigned_users($gift->ID);
                    $expire_date = get_post_meta($gift->ID, '_tg_expire_date', true); // lấy ngày hết hạn
                    foreach ($assigned_users as $uid) {
                        $user = get_userdata($uid);
                        if (!$user) continue;
                        echo '<tr>';
                        echo '<td>' . esc_html($gift->post_title) . '</td>';
                        echo '<td>' . esc_html($user->display_name) . '</td>';
                        echo '<td>' . esc_html($user->user_email) . '</td>';
                        echo '<td>' . esc_html($expire_date ? date('d/m/Y', strtotime($expire_date)) : '-') . '</td>'; // hiển thị dd/mm/yyyy hoặc "-" nếu rỗng
                        echo '<td>
                        <form method="post" style="display:inline;">
                            ' . wp_nonce_field('tg_remove_user_action', 'tg_remove_user_nonce', true, false) . '
                            <input type="hidden" name="giftcard_id" value="' . $gift->ID . '">
                            <input type="hidden" name="user_id" value="' . $uid . '">
                            <button type="submit" name="tg_remove" class="button">Xóa</button>
                        </form>
                    </td>';
                        echo '</tr>';
                    }
                }
                ?>
            </tbody>

        </table>

        <script>
            jQuery(document).ready(function($) {
                $('.tg-user-select').select2({
                    placeholder: 'Chọn user...',
                    allowClear: true,
                    width: '100%',
                    ajax: {
                        url: ajaxurl,
                        dataType: 'json',
                        delay: 250,
                        data: function(params) {
                            return {
                                action: 'tg_search_users',
                                q: params.term
                            };
                        },
                        processResults: function(data) {
                            return {
                                results: data.results
                            };
                        },
                        cache: true
                    },
                    minimumInputLength: 1,
                });
            });
        </script>

        <style>
            .tg-select2-dropdown .select2-results__options li {
                max-height: 20em;
                /* khoảng 20 dòng */
                overflow-y: auto;
            }
        </style>
<?php
    }
}
