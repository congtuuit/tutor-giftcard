<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TG_Utils {

    public static function get_table_name(){
        global $wpdb;

        $table_name = $wpdb->prefix . 'tg_giftcard_user';

        return $table_name;
    }

    /**
     * Khởi tạo bảng giftcard-user nếu chưa tồn tại
     */
    public static function maybe_create_table() {
        global $wpdb;

        $table_name = TG_Utils::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        // Kiểm tra bảng đã tồn tại chưa
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) != $table_name ) {
            $sql = "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                giftcard_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                used TINYINT(1) NOT NULL DEFAULT 0,
                used_at DATETIME NULL,
                UNIQUE KEY gift_user (giftcard_id, user_id),
                INDEX user_idx (user_id),
                INDEX gift_idx (giftcard_id)
            ) $charset_collate;";

            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );
        }
    }


    public static function ensure_my_giftcard_page_exists() {
        // slug cần tạo
        $slug = 'my-gift-card';
        $title = 'Thẻ quà tặng của tôi';

        // Kiểm tra xem page đã tồn tại chưa
        $existing = get_page_by_path($slug, OBJECT, 'page');
        if ($existing) {
            return $existing->ID; // Đã có page rồi
        }

        // Nếu chưa tồn tại → tạo mới
        $page_id = wp_insert_post([
            'post_title'   => $title,
            'post_name'    => $slug,
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '[tutor_giftcards]', // có thể gắn shortcode để render nội dung sau này
        ]);

        return $page_id;
    }

    public static function ensure_giftcard_claim_page_exists() {
        // slug cần tạo
        $slug = 'redeem-giftcard';
        $title = 'Đổi thẻ quà tặng';

        // Kiểm tra xem page đã tồn tại chưa
        $existing = get_page_by_path($slug, OBJECT, 'page');
        if ($existing) {
            return $existing->ID; // Đã có page rồi
        }

        // Nếu chưa tồn tại → tạo mới
        $page_id = wp_insert_post([
            'post_title'   => $title,
            'post_name'    => $slug,
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '[tutor_giftcard_claim]', // có thể gắn shortcode để render nội dung sau này
        ]);

        return $page_id;
    }
    

    /**
     * Lấy tất cả dữ liệu gift card dưới dạng object theo ID
     */
    public static function get_giftcard_by_id( $post_id ) {
        if ( empty( $post_id ) ) return null;

        $fields = [
            'id'                  => $post_id,
            'gift_card_code'      => get_post_meta( $post_id, '_tg_gift_card_code', true ),
            'status'              => get_post_meta( $post_id, '_tg_status', true ),
            'expire_date'         => get_post_meta( $post_id, '_tg_expire_date', true ),
            'limit_per_user'      => intval( get_post_meta( $post_id, '_tg_limit_per_user', true ) ),
            'max_amount'          => floatval( get_post_meta( $post_id, '_tg_max_amount', true ) ),
            'allow_all_courses'   => get_post_meta( $post_id, '_tg_allow_all_courses', true ) === '1',
            'specific_courses'    => self::get_ids_array(get_post_meta( $post_id, '_tg_specific_courses', true )),
            'excluded_courses'    => self::get_ids_array(get_post_meta( $post_id, '_tg_excluded_courses', true )),
            'max_courses'         => intval( get_post_meta( $post_id, '_tg_max_courses', true ) ),
        ];

        return (object) $fields;
    }

    /**
     * Kiểm tra gift card còn active và chưa hết hạn
     */
    public static function is_valid( $post_id ) {
        $giftcard = self::get_giftcard_by_id( $post_id );
        if ( ! $giftcard ) return false;

        if ( $giftcard->status !== 'active' ) return false;

        if ( ! empty( $giftcard->expire_date ) ) {
            $today = date('Y-m-d');
            if ( $giftcard->expire_date < $today ) return false;
        }

        return true;
    }

    /**
     * Kiểm tra gift card có áp dụng cho khóa học nhất định
     */
    public static function is_applicable_to_course( $post_id, $course_id ) {
        $giftcard = self::get_giftcard_by_id( $post_id );
        if ( ! $giftcard ) return false;

        // Nếu áp dụng cho tất cả khóa học
        if ( $giftcard->allow_all_courses ) return true;

        // Nếu khóa học nằm trong danh sách exclude
        if ( in_array( $course_id, $giftcard->excluded_courses ) ) return false;

        // Nếu danh sách cụ thể rỗng => không áp dụng
        if ( empty( $giftcard->specific_courses ) ) return false;

        return in_array( $course_id, $giftcard->specific_courses );
    }

    /**
     * Convert string "1,2,3" thành array [1,2,3]
     */
    private static function get_ids_array( $str ) {
        if ( empty( $str ) ) return [];
        $arr = explode( ',', $str );
        return array_map( 'intval', $arr );
    }

    /**
     * Lấy số lượng tối đa khóa học có thể nhận
     */
    public static function get_max_courses( $post_id ) {
        $giftcard = self::get_giftcard_by_id( $post_id );
        return $giftcard ? $giftcard->max_courses : 0;
    }

    /**
     * Lấy danh sách user ID đã gán cho gift card
     * @return int[]
     */
    public static function get_assigned_users( $giftcard_id ) {
        global $wpdb;

        $giftcard_id = (int) $giftcard_id;

        $table_name = TG_Utils::get_table_name();

        $results = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT user_id FROM {$table_name} WHERE giftcard_id = %d",
                $giftcard_id
            )
        );

        return array_map('intval', $results);
    }

    /**
     * Kiểm tra user có được gán giftcard hay không (dùng query trực tiếp)
     *
     * @param int $user_id
     * @param int $giftcard_id
     * @return array
     */
    public static function validate_user_giftcard( $user_id, $giftcard_id, $record_id ) {
        global $wpdb;

        $user_id     = (int) $user_id;
        $giftcard_id = (int) $giftcard_id;
        $record_id   = (int) $record_id;

        if ($user_id <= 0) {
            return [
                'valid'   => false,
                'message' => 'User ID không hợp lệ.'
            ];
        }

        if ($giftcard_id <= 0) {
            return [
                'valid'   => false,
                'message' => 'Giftcard ID không hợp lệ.'
            ];
        }

        if ($record_id <= 0) {
            return [
                'valid'   => false,
                'message' => 'Record ID không hợp lệ.'
            ];
        }

        // Check user tồn tại
        if (!get_user_by('id', $user_id)) {
            return [
                'valid'   => false,
                'message' => 'User không tồn tại.'
            ];
        }

        // Tên bảng
        $table = TG_Utils::get_table_name();

        // Truy vấn xem bản ghi theo record_id có khớp user_id, giftcard_id và chưa dùng không
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(1)
                FROM {$table}
                WHERE id = %d
                AND giftcard_id = %d
                AND user_id = %d
                AND (used IS NULL OR used = 0)
                LIMIT 1",
                $record_id,
                $giftcard_id,
                $user_id
            )
        );

        if (!$exists) {
            return [
                'valid'   => false,
                'message' => 'User không có quyền sử dụng giftcard này hoặc giftcard đã được dùng.'
            ];
        }

        return [
            'valid'   => true,
            'message' => 'Hợp lệ.'
        ];
    }



    /**
     * Gán user cho gift card
     * @return bool true nếu gán thành công, false nếu đã gán
     */
    public static function assign_user_to_giftcard( $giftcard_id, $user_id ) {
        global $wpdb;

        $giftcard_id = (int) $giftcard_id;
        $user_id     = (int) $user_id;

        $table_name = TG_Utils::get_table_name();

        $inserted = $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$table_name} (giftcard_id, user_id) VALUES (%d, %d)",
                $giftcard_id, $user_id
            )
        );

        return $inserted > 0;
    }

    /**
     * Hủy gán user khỏi gift card
     */
    public static function remove_user_from_giftcard( $giftcard_id, $user_id ) {
        global $wpdb;

        $giftcard_id = (int) $giftcard_id;
        $user_id     = (int) $user_id;

        $table_name = TG_Utils::get_table_name();

        $wpdb->delete(
            $table_name,
            [
                'giftcard_id' => $giftcard_id,
                'user_id'     => $user_id,
            ],
            ['%d', '%d']
        );
    }

    /**
     * Đánh dấu giftcard đã sử dụng dựa trên ID của bảng
     *
     * @param int $record_id  ID của bản ghi trong bảng giftcard_users
     * @return array ['success' => bool, 'message' => string]
     */
    public static function mark_giftcard_used_by_id($record_id) {
        global $wpdb;

        $record_id = (int) $record_id;
        if ($record_id <= 0) {
            return [
                'success' => false,
                'message' => 'ID không hợp lệ.'
            ];
        }

        $table = TG_Utils::get_table_name();

        // Kiểm tra bản ghi tồn tại và chưa dùng
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(1)
                FROM {$table}
                WHERE id = %d
                AND (used IS NULL OR used = 0)
                LIMIT 1",
                $record_id
            )
        );

        if (!$exists) {
            return [
                'success' => false,
                'message' => 'Giftcard đã được dùng hoặc bản ghi không tồn tại.'
            ];
        }

        // Cập nhật trạng thái used = 1, used_at = NOW()
        $updated = $wpdb->update(
            $table,
            [
                'used'    => 1,
                'used_at' => current_time('mysql', 1), // UTC
            ],
            [
                'id' => $record_id
            ],
            [
                '%d', // used
                '%s'  // used_at
            ],
            [
                '%d'  // id
            ]
        );

        if ($updated === false) {
            return [
                'success' => false,
                'message' => 'Lỗi khi cập nhật giftcard.'
            ];
        }

        return [
            'success' => true,
            'message' => 'Giftcard đã được đánh dấu sử dụng thành công.'
        ];
    }


    /**
     * Lấy danh sách giftcard (ID) mà user này được gán
     * @return int[]
     */
    public static function get_giftcards_ids_by_user( $user_id ) {
        global $wpdb;

        $user_id = (int) $user_id;

        $table_name = TG_Utils::get_table_name();

        $results = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT giftcard_id FROM {$table_name} WHERE user_id = %d",
                $user_id
            )
        );

        return array_map('intval', $results);
    }

    /**
     * Lấy danh sách giftcard (WP_Post) mà user này được gán
     * @return WP_Post[]
     */
    public static function get_giftcards_by_user( $user_id ) {
        $ids = self::get_giftcards_ids_by_user($user_id);

        if ( empty($ids) ) return [];

        return get_posts([
            'post_type'   => 'tutor_giftcard',
            'post_status' => 'publish',
            'numberposts' => -1,
            'post__in'    => $ids,
            'orderby'     => 'post_date',
            'order'       => 'DESC',
        ]);
    }

    public static function get_giftcards_by_user_include_tgid($user_id) {
        global $wpdb;

        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return [];
        }

        $table = TG_Utils::get_table_name();

        // Lấy các bản ghi giftcard của user
        $records = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id AS record_id, giftcard_id, used, used_at
                FROM {$table}
                WHERE user_id = %d
                ORDER BY assigned_at DESC",
                $user_id
            ),
            ARRAY_A
        );

        if (empty($records)) {
            return [];
        }

        // Lấy tất cả post_ids từ bảng liên kết
        $giftcard_ids = wp_list_pluck($records, 'giftcard_id');

        // Lấy posts
        $posts = get_posts([
            'post_type'   => 'tutor_giftcard',
            'post_status' => 'publish',
            'numberposts' => -1,
            'post__in'    => $giftcard_ids,
            'orderby'     => 'post_date',
            'order'       => 'DESC',
        ]);

        // Map post với thông tin bảng liên kết
        $results = [];
        foreach ($posts as $post) {
            // Tìm bản ghi tương ứng trong bảng
            $record = array_filter($records, function($r) use ($post) {
                return $r['giftcard_id'] == $post->ID;
            });

            $record = array_shift($record); // Lấy bản ghi đầu tiên

            $results[] = [
                'post'      => $post,
                'record_id' => $record['record_id'] ?? null,
                'used'      => $record['used'] ?? 0,
                'used_at'   => $record['used_at'] ?? null,
            ];
        }

        return $results;
    }


    /**
     * Lấy thông tin bản ghi giftcard dựa trên user_id, giftcard_id và record_id
     *
     * @param int $user_id
     * @param int $giftcard_id
     * @param int $record_id
     * @return array|null  Trả về mảng dữ liệu hoặc null nếu không tìm thấy
     */
    public static function get_user_giftcard_record($user_id, $giftcard_id, $record_id) {
        global $wpdb;

        $user_id     = (int) $user_id;
        $giftcard_id = (int) $giftcard_id;
        $record_id   = (int) $record_id;

        if ($user_id <= 0 || $giftcard_id <= 0 || $record_id <= 0) {
            return null;
        }

        $table = TG_Utils::get_table_name();

        $record = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id AS record_id, user_id, giftcard_id, used, used_at, assigned_at
                FROM {$table}
                WHERE id = %d
                AND giftcard_id = %d
                AND user_id = %d
                LIMIT 1",
                $record_id,
                $giftcard_id,
                $user_id
            ),
            ARRAY_A
        );

        return $record ?: null;
    }



    public static function prepare_in_clause( array $arr ) {
		$escaped = array_map(
			function( $value ) {
				global $wpdb;
				$escaped_value = null;
				if ( is_int( $value ) ) {
					$escaped_value = $wpdb->prepare( '%d', $value );
				} else if( is_float( $value ) ) {
					list( $whole, $decimal ) = explode( '.', $value );
					$expression = '%.'. strlen( $decimal ) . 'f';
					$escaped_value = $wpdb->prepare( $expression, $value );
				} else {
					$escaped_value = $wpdb->prepare( '%s', $value );
				}
				return $escaped_value;
			},
			$arr
		);
	
		return implode( ',', $escaped );
	}

    public static function get_bundle_courses($course_ids)
    {
        if (0 === count($course_ids)) {
            return array();
        }

        global $wpdb;
        $in_clause = self::prepare_in_clause($course_ids);
        $courses = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * from {$wpdb->posts}
				WHERE post_type=%s
				AND ID IN({$in_clause})
				",
                "courses" // post_type Tutor\Models\CourseModel::POST_TYPE
            )
        );

        return $courses;
    }

    // Load template file from plugin or theme override
    // Example: TG_Utils::load_template('templates/redeem-grid.php', $data);
    // Example: TG_Utils::load_template('templates/redeem-grid.php', ['course_data' => $course_data ]);
    public static function load_template($rel_path, $data = []) {

        if (!empty($data) && is_array($data)) {
            extract($data, EXTR_SKIP); // tạo $course_data
        }

        $plugin_path = TG_PATH . $rel_path;
        if (file_exists($plugin_path)) {
            include $plugin_path;
        } else {
            echo '<p>Template not found: '.esc_html($rel_path).'</p>';
        }
    }

    public static function get_asset_url($rel_path) {
        return TG_URL . 'assets/' . ltrim($rel_path, '/');
    }

    //=============================================================================================
    // tg_giftcard_course table methods
    // NEW TABLE FOR GIFT CARD AND COURSES RELATIONSHIP
    public static function tg_create_giftcard_course_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'tg_giftcard_course';
        $charset_collate = $wpdb->get_charset_collate();

        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) != $table_name ) {
            $sql = "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                giftcard_id BIGINT UNSIGNED NOT NULL,
                course_id BIGINT UNSIGNED NOT NULL,
                product_id BIGINT UNSIGNED DEFAULT 0,
                PRIMARY KEY (id),
                UNIQUE KEY gift_course (giftcard_id, course_id),
                INDEX gift_idx (giftcard_id),
                INDEX course_idx (course_id)
            ) $charset_collate;";

            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );
        }
    }

    /**
     * Lấy các giftcard-course record theo course_id
     *
     * @param int|array $course_id
     * @return array
     */
    public static function get_giftcard_courses_by_course($course_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tg_giftcard_course';

        if (empty($course_id)) return [];

        if (is_array($course_id)) {
            $placeholders = implode(',', array_fill(0, count($course_id), '%d'));
            $query = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE course_id IN ($placeholders)",
                ...$course_id
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE course_id = %d",
                $course_id
            );
        }

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Lấy các giftcard-course record theo product_id
     *
     * @param int|array $product_id
     * @return array
     */
    public static function get_giftcard_courses_by_product($product_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tg_giftcard_course';

        if (empty($product_id)) return [];

        if (is_array($product_id)) {
            $placeholders = implode(',', array_fill(0, count($product_id), '%d'));
            $query = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE product_id IN ($placeholders)",
                ...$product_id
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE product_id = %d",
                $product_id
            );
        }

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Sinh dữ liệu giftcard-course
     *
     * @param int $giftcard_id
     * @param array $course_ids
     * @param array $product_ids
     * @return int  Số bản ghi đã insert thành công
     */
    public static function create_giftcard_courses($giftcard_id, $course_ids = [], $product_ids = []) {
        global $wpdb;

        $giftcard_id = (int) $giftcard_id;
        if ($giftcard_id <= 0 || empty($course_ids) || empty($product_ids)) {
            return 0;
        }

        $table = $wpdb->prefix . 'tg_giftcard_course';
        $inserted_count = 0;

        foreach ($course_ids as $index => $course_id) {
            $course_id = (int) $course_id;
            $product_id = isset($product_ids[$index]) ? (int) $product_ids[$index] : 0;

            if ($course_id <= 0) continue;

            // Dùng INSERT ... ON DUPLICATE KEY để tránh trùng
            $result = $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$table} (giftcard_id, course_id, product_id) 
                     VALUES (%d, %d, %d)
                     ON DUPLICATE KEY UPDATE product_id = VALUES(product_id)",
                    $giftcard_id,
                    $course_id,
                    $product_id
                )
            );

            if ($result !== false) {
                $inserted_count++;
            }
        }

        return $inserted_count;
    }

    /**
     * Xóa tất cả record theo giftcard_id
     *
     * @param int $giftcard_id
     * @return int  Số bản ghi đã xóa
     */
    public static function delete_giftcard_courses_by_giftcard($giftcard_id) {
        global $wpdb;

        $giftcard_id = (int) $giftcard_id;
        if ($giftcard_id <= 0) {
            return 0;
        }

        $table = $wpdb->prefix . 'tg_giftcard_course';

        $deleted = $wpdb->delete(
            $table,
            ['giftcard_id' => $giftcard_id],
            ['%d']
        );

        return $deleted;
    }

    /**
     * Lấy tất cả record theo giftcard_id
     *
     * @param int $giftcard_id
     * @return array  Mảng kết quả dạng ARRAY_A
     */
    public static function get_giftcard_courses_by_giftcard($giftcard_id) {
        global $wpdb;

        $giftcard_id = (int) $giftcard_id;
        if ($giftcard_id <= 0) {
            return [];
        }

        $table = $wpdb->prefix . 'tg_giftcard_course';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE giftcard_id = %d",
                $giftcard_id
            ),
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * Lấy danh sách product_id theo danh sách course_ids
     *
     * @param array $course_ids
     * @return array
     */
    public static function get_products_by_courses($course_ids = []) {
        if (empty($course_ids) || !is_array($course_ids)) {
            return [];
        }

        $result = [];

        foreach ($course_ids as $course_id) {
            $course_id  = (int) $course_id;
            if ($course_id <= 0) continue;

            $product_id = (int) get_post_meta($course_id, '_tutor_course_product_id', true);

            $result[] = [
                'course_id'  => $course_id,
                'product_id' => $product_id,
            ];
        }

        return $result;
    }

    /**
     * Lấy danh sách product_id theo nhiều course_id, tối ưu query
     *
     * @param array $course_ids
     * @return array
     */
    public static function get_products_by_courses_bulk($course_ids = []) {
        if (empty($course_ids) || !is_array($course_ids)) return [];

        $course_ids = array_map('intval', $course_ids);
        $result = [];

        // Load cache meta cho tất cả course_ids một lần
        update_postmeta_cache($course_ids);

        foreach ($course_ids as $course_id) {
            $product_id = (int) get_post_meta($course_id, '_tutor_course_product_id', true);
            $result[] = [
                'course_id'  => $course_id,
                'product_id' => $product_id,
            ];
        }

        return $result;
    }

}
