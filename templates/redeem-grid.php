<?php
// Từ extract bạn chỉ có biến $gift_data
// Không có $giftcard_id, $code, $meta, $post nếu bạn không extract sâu hơn.
// $gift_data = [
//             'giftcard_id' => $giftcard_id,
//             'code' => $code,
//             'max_courses' => intval($max_courses),
//             'meta' => $meta,
//             'post' => $post,
//         ];
?>
<div class="tg-info-box">
    <h3 class="tg-info-heading">Thông tin Thẻ quà tặng</h3>

    <div class="tg-info-row">
        <strong>Thẻ quà tặng:</strong>
        <?php echo esc_html($gift_data['post']->post_title ?? ''); ?>
    </div>

    <div class="tg-info-row">
        <strong>Số khóa học được chọn:</strong>
        <?php echo intval($gift_data['max_courses'] ?? 1); ?>
    </div>

    <?php if (!empty($gift_data['meta']['fixed_course_list'])): ?>
        <div class="tg-hidden-info">
            <strong>Danh sách khóa học cố định:</strong>
            <?php echo esc_html($gift_data['meta']['fixed_course_list']); ?>
        </div>
    <?php endif; ?>
</div>

<div class="tg-search-panel">
    <input type="search" id="tg-search-input" placeholder="Tìm khóa học..." />
    <button id="tg-search-btn" class="button button-primary" style="display: flex; gap: 5px;">
        <span style="margin: auto;" class="dashicons dashicons-search"></span>
        <span>Tìm kiếm</span>
    </button>
</div>

<div class="m-auto tg-loading-spinner" id="tg-loading-spinner" style="display:none;flex-direction:column;align-items:center;gap:10px;margin-top:30px;">
    <div class="tg-spinner" role="status" aria-live="polite" aria-label="Đang tải"></div>
    <div>Đang tải...</div>
</div>

<div id="tg-course-grid" class="tg-course-grid" style="

overflow: auto;
    max-height: 500px;
display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:20px;">
</div>

<div id="tgPaginationPlaceholder"></div>

<script>
    // Truyền dữ liệu sang JS
    window.TG_GIFTCARD = {
        id: <?php echo intval($gift_data['giftcard_id'] ?? 0); ?>,
        code: "<?php echo esc_js($gift_data['code'] ?? ''); ?>",
        max: <?php echo intval($gift_data['max_courses'] ?? 1); ?>,
        allowAny: <?php echo intval($gift_data['meta']['allow_any_course'] ?? 0); ?>,
        fixedCourses: <?php echo wp_json_encode($gift_data['meta']['fixed_course_list'] ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>,
        excludedCourses: <?php echo wp_json_encode($gift_data['meta']['excluded_course_list'] ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>,
        maxPrice: <?php echo floatval($gift_data['meta']['max_course_price'] ?? 0); ?>,
    };
</script>