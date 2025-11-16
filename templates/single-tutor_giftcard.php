<?php
/**
 * Template: Chi tiết Giftcard (single-tutor_giftcard.php)
 */

get_header();

global $post;
$giftcard_id = $post->ID;

// Lấy thông tin post
$title       = get_the_title($giftcard_id);
$content     = apply_filters('the_content', $post->post_content);
$thumbnail   = get_the_post_thumbnail_url($giftcard_id, 'large');

// Lấy meta
$giftcard_code          = get_post_meta($giftcard_id, '_tg_gift_card_code', true);
$max_courses            = intval(get_post_meta($giftcard_id, '_tg_max_courses', true));
$expired_at             = get_post_meta($giftcard_id, '_tg_expire_date', true);
$max_amount             = floatval(get_post_meta($giftcard_id, '_tg_max_amount', true));

$allow_all              = get_post_meta($giftcard_id, '_tg_allow_all_courses', true);
$specific_courses       = get_post_meta($giftcard_id, '_tg_specific_courses', true);
$excluded_courses       = get_post_meta($giftcard_id, '_tg_excluded_courses', true);

?>

<style>
.tg-giftcard-container {
    max-width: 820px;
    margin: 40px auto;
    padding: 28px;
    border-radius: 16px;
    background: #fff;
    border: 1px solid #ddd;
    box-shadow: 0 6px 16px rgba(0,0,0,0.08);
    font-family: Arial, sans-serif;
}

.tg-giftcard-header {
    text-align: center;
    margin-bottom: 20px;
}

.tg-giftcard-header img {
    max-width: 100%;
    border-radius: 12px;
    margin-bottom: 20px;
}

.tg-section-title {
    margin-top: 25px;
    margin-bottom: 12px;
    font-size: 20px;
    font-weight: bold;
}

.tg-giftcard-meta, .tg-usage-rules {
    padding: 18px;
    background: #f8f8f8;
    border-radius: 12px;
    margin-bottom: 24px;
}

.tg-giftcard-meta p, .tg-usage-rules p {
    margin: 8px 0;
    font-size: 16px;
}

.tg-highlight {
    padding: 16px;
    background: #e5f3ff;
    border-left: 4px solid #2196F3;
    border-radius: 8px;
    margin: 20px 0;
}

.tg-marketing-box {
    padding: 20px;
    background: #fff8e1;
    border-left: 4px solid #fcb900;
    border-radius: 10px;
    margin-bottom: 25px;
}

.tg-marketing-box p {
    margin: 6px 0;
    font-size: 17px;
    font-weight: 500;
}

.tg-giftcard-content {
    line-height: 1.6;
    font-size: 17px;
    margin-bottom: 30px;
}
</style>

<div class="tg-giftcard-container">

    <div class="tg-giftcard-header">
        <h1><?php echo esc_html($title); ?></h1>

        <?php if ($thumbnail): ?>
            <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo esc_attr($title); ?>">
        <?php endif; ?>
    </div>

    <!-- MÔ TẢ MARKETING CỦA BÀI VIẾT -->
    <div class="tg-giftcard-content">
        <?php echo $content; ?>
    </div>

    <h2 class="tg-section-title">🎁 Thông tin thẻ quà tặng</h2>
    <div class="tg-giftcard-meta">
        <?php if ($giftcard_code): ?>
            <p><strong>Mã thẻ:</strong> <?php echo esc_html($giftcard_code); ?></p>
        <?php endif; ?>


        <?php if ($expired_at): ?>
            <p><strong>Hạn sử dụng:</strong> <?php echo esc_html($expired_at); ?></p>
        <?php else: ?>
            <p><strong>Hạn sử dụng:</strong> Không giới hạn</p>
        <?php endif; ?>

        
    </div>


    <h2 class="tg-section-title">📌 Quyền lợi & Điều kiện sử dụng</h2>

    <div class="tg-usage-rules">
        <?php
        // 1. Cho phép chọn bất kỳ khóa học
        if ($allow_all == '1') {
            echo '<p><strong>✔ Thẻ này cho phép bạn chọn bất kỳ khóa học nào bạn yêu thích.</strong></p>';
            echo '<p>Giới hạn tối đa: <strong>' . ($max_courses ?: 'Không giới hạn') . ' khóa</strong>.</p>';

            if ($max_amount > 0) {
                echo '<p>Mỗi khóa cần có giá <strong>nhỏ hơn ' . number_format($max_amount) . 'đ</strong>.</p>';
            }
        }

        // 2. Nếu có danh sách khóa học cụ thể
        elseif (!empty($specific_courses)) {
            echo '<p><strong>✔ Thẻ này áp dụng cho danh sách khóa học giới hạn.</strong></p>';
            echo '<p>Bạn có thể chọn tối đa <strong>' . ($max_courses ?: 'Không giới hạn') . ' khóa</strong>.</p>';
        }

        // 3. Nếu có danh sách khóa học bị excluded
        elseif (!empty($excluded_courses)) {
            echo '<p><strong>✔ Thẻ này áp dụng cho tất cả khóa học ngoại trừ một số khóa bị loại trừ.</strong></p>';
            echo '<p>Giới hạn tối đa <strong>' . ($max_courses ?: 'Không giới hạn') . ' khóa</strong>.</p>';
        }

        // 4. Mặc định không có gì
        else {
            echo '<p><strong>✔ Thẻ áp dụng linh hoạt tùy theo chương trình khuyến mãi.</strong></p>';
        }
        ?>
    </div>

</div>

<?php get_footer(); ?>
