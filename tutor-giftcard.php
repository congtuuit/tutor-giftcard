<?php
/*
Plugin Name: Tutor Gift Card
Description: Thẻ quà tặng tích hợp Tutor LMS — author: Tú Văn
Version: 0.1
Author: Tú Văn
Text Domain: tutor-giftcard
*/

if ( ! defined('ABSPATH') ) exit;

require_once __DIR__ . '/includes/class-tg-admin.php';
require_once __DIR__ . '/includes/class-tg-frontend.php';
require_once __DIR__ . '/includes/class-tg-redeem.php';
require_once __DIR__ . '/includes/class-tg-shortcodes.php';
require_once __DIR__ . '/includes/class-tg-utils.php';

define( 'TG_PATH', plugin_dir_path( __FILE__ ) );

function tg_init_plugin() {
    new TG_Admin();
    new TG_Utils();
    TG_Shortcodes::init();
    TG_Frontend::init();
    TG_Redeem::init();
}
add_action('plugins_loaded', 'tg_init_plugin');

register_activation_hook( __FILE__, function() {
    TG_Utils::maybe_create_table();

    TG_Admin::register_giftcard_post_type();
    
    flush_rewrite_rules();
});

