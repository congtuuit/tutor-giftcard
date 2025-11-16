<?php

/**
 * Template: Giftcard Single Course Card
 * Biến nhận: $course_data
 */
if (empty($course_data)) return;

$course_id  = $course_data['id'];
$thumb      = $course_data['thumb'];
$link       = $course_data['link'];
$title      = $course_data['title'];
$author     = $course_data['author'];

$enrolled   = $course_data['enrolled'];
$lessons    = $course_data['lessons'];
$duration   = $course_data['duration'];
$price      = $course_data['price'] ?? null;
?>

<div class="tg-course-card" style="
    border:1px solid #e5e7eb;
    border-radius:10px;
    overflow:hidden;
    background:#fff;
    transition:all .2s ease;
    box-shadow:0 1px 3px rgba(0,0,0,0.05);
    position:relative;">

    <!-- Thumbnail -->
    <a href="<?php echo esc_url($link); ?>" target="_blank" style="display:block;">
        <img src="<?php echo esc_url($thumb); ?>"
            alt="<?php echo esc_attr($title); ?>"
            style="width:100%;height:150px;object-fit:cover;">
    </a>

    <!-- Content -->
    <div style="padding:12px 15px;">

        <a href="<?php echo esc_url($link); ?>" target="_blank"
            style="text-decoration:none;color:#111;">
            <h3 style="
                margin:0 0 8px;
                font-size:17px;
                font-weight:600;
                line-height:1.4;
                display:-webkit-box;
                -webkit-line-clamp:2;
                -webkit-box-orient:vertical;
                overflow:hidden;">
                <?php echo esc_html($title); ?>
            </h3>
        </a>

        <div style="font-size:13px;color:#555;margin-top:4px;display:flex;flex-direction:column;gap:4px;">

            <div style="display:flex;align-items:center;gap:6px;">
                <span class="dashicons dashicons-groups" style="font-size:14px;"></span>
                <?php echo $enrolled; ?> học viên
            </div>

            <div style="display:flex;align-items:center;gap:6px;">
                <span class="dashicons dashicons-welcome-learn-more" style="font-size:14px;"></span>
                <?php echo $lessons; ?> bài học
            </div>

            <div style="display:flex;align-items:center;gap:6px;">
                <span class="dashicons dashicons-clock" style="font-size:14px;"></span>
                <?php echo $duration ?: 'Đang cập nhật'; ?>
            </div>

            <div style="display:flex;align-items:center;gap:6px;">
                <span class="dashicons dashicons-tag" style="font-size:14px;"></span>
                <?php
                if (empty($price)) {
                    echo 'Miễn phí';
                } else {
                    echo "Giá:" . wp_kses_post($price);
                }
                ?>
            </div>


        </div>
    </div>

    <!-- Checkbox -->
    <div style="
        padding:12px 15px;
        border-top:1px solid #eee;
        background:#fafafa;
        text-align:center;">
        <label style="display:flex;align-items:center;justify-content:center;gap:8px;cursor:pointer;font-weight:600;">
            <input
                data-title="<?php echo esc_html($title); ?>"
                data-link="<?php echo esc_url($link); ?>"
                data-thumb="<?php echo esc_url($thumb); ?>"
                type="checkbox"
                class="tg-course-checkbox"
                value="<?php echo $course_id; ?>"
                style="width:18px;height:18px;">
            <span>Chọn khóa học</span>
        </label>
    </div>

</div>