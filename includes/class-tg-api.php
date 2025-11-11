<?php
if ( ! defined('ABSPATH') ) exit;

class TG_API {
    public static function init(){
        add_action('rest_api_init', function(){
            register_rest_route('tutor-giftcard/v1', '/redeem', [
                'methods' => 'POST',
                'callback' => [__CLASS__, 'handle_redeem'],
                'permission_callback' => function(){ return is_user_logged_in(); }
            ]);
        });
    }

    public static function handle_redeem(WP_REST_Request $req){
        $user_id = get_current_user_id();
        $code = sanitize_text_field($req->get_param('code'));
        $selected = $req->get_param('selected_ids') ?? [];
        if (!is_array($selected)) $selected = [$selected];
        $selected = array_map('intval', $selected);

        // find card in user's meta
        $cards = get_user_meta($user_id, 'tg_user_giftcards', true) ?: [];
        $card_index = null;
        foreach ($cards as $i=>$c) if ($c['code'] === $code) { $card_index = $i; break; }
        if ($card_index === null) return new WP_REST_Response(['success'=>false,'data'=>'Không tìm thấy thẻ.'], 400);

        $card = $cards[$card_index];

        // Check active, expiry
        $now = current_time('mysql');
        if ($card['status'] !== 'active') return new WP_REST_Response(['success'=>false,'data'=>'Thẻ không active.'], 400);
        if (!empty($card['expires_at']) && $now > $card['expires_at']) return new WP_REST_Response(['success'=>false,'data'=>'Thẻ đã hết hạn.'], 400);

        $max = intval($card['max_courses'] ?? 1);
        if (count($selected) > $max) return new WP_REST_Response(['success'=>false,'data'=>'Chọn quá số lượng cho phép.'], 400);

        // Ensure selected not in exclude
        $exclude = array_filter(array_map('intval', explode(',', $card['meta']['exclude_course_list'] ?? '')));
        foreach ($selected as $id) if (in_array($id, $exclude)) return new WP_REST_Response(['success'=>false,'data'=>'Có khóa học không hợp lệ.'], 400);

        // Check used count vs usage_limit_per_user
        if (!empty($card['usage_limit_per_user']) && ($card['used_count'] + count($selected) ) > intval($card['usage_limit_per_user'])){
            return new WP_REST_Response(['success'=>false,'data'=>'Vượt giới hạn sử dụng của thẻ.'], 400);
        }

        // Prepare payload to your external API
        $external_url = get_option('tg_external_api_url', '');
        if (empty($external_url)){
            return new WP_REST_Response(['success'=>false,'data'=>'External API not configured.'], 500);
        }

        $payload = [
            'user_id' => $user_id,
            'code' => $code,
            'selected_course_ids' => $selected
        ];

        // call external API (wp_remote_post)
        $resp = wp_remote_post($external_url, [
            'headers' => ['Content-Type'=>'application/json'],
            'body' => wp_json_encode($payload),
            'timeout' => 15
        ]);

        if (is_wp_error($resp)){
            return new WP_REST_Response(['success'=>false,'data'=>'Lỗi gọi API: '.$resp->get_error_message()], 500);
        }

        $status_code = wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);
        $body_decoded = json_decode($body, true);

        if ($status_code !== 200){
            return new WP_REST_Response(['success'=>false,'data'=>'External API error: '.$body], 500);
        }

        // If external API says success -> update user meta used_count etc
        // Assume external API responds { success: true }
        if (is_array($body_decoded) && !empty($body_decoded['success'])){
            // update used_count and possibly mark card used if fully used
            $cards[$card_index]['used_count'] = intval($cards[$card_index]['used_count']) + count($selected);
            if (!empty($cards[$card_index]['usage_limit_per_user']) && $cards[$card_index]['used_count'] >= intval($cards[$card_index]['usage_limit_per_user'])){
                $cards[$card_index]['status'] = 'used';
            }
            update_user_meta($user_id, 'tg_user_giftcards', $cards);
            return new WP_REST_Response(['success'=>true,'data'=>'Đổi quà thành công.'], 200);
        } else {
            return new WP_REST_Response(['success'=>false,'data'=>'External API returned failure. '. $body], 500);
        }
    }
}
