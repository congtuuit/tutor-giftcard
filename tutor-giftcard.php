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
require_once __DIR__ . '/includes/class-tg-api.php';
require_once __DIR__ . '/includes/class-tg-frontend.php';
require_once __DIR__ . '/includes/class-tg-shortcodes.php';

function tg_init_plugin() {
    new TG_Admin();
    TG_Shortcodes::init();
    TG_Frontend::init();
    TG_API::init();
}
add_action('plugins_loaded', 'tg_init_plugin');
