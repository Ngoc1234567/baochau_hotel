<?php
defined('ABSPATH') || exit;
add_action('after_setup_theme',function () { add_theme_support('title-tag'); add_theme_support('custom-logo'); add_theme_support('post-thumbnails'); add_theme_support('html5',['search-form','gallery','caption','style','script']); register_nav_menus(['primary'=>'Menu chính']); });
add_action('wp_enqueue_scripts',function () { wp_enqueue_style('baochau',get_stylesheet_uri(),[],'1.0.1'); wp_enqueue_script('baochau',get_template_directory_uri().'/assets/site.js',[],'1.0.0',true); });
add_action('customize_register',function ($c) {
    $c->add_section('bch_brand',['title'=>'Bảo Châu — Nội dung trang chủ','priority'=>30]);
    foreach (['hero_title'=>['Tiêu đề banner','Một chốn an yên.\nMột kỳ nghỉ trọn vẹn.'],'hero_text'=>['Mô tả banner','Gác lại nhịp sống vội vã, tận hưởng những ngày thảnh thơi trong không gian ấm áp của Bảo Châu.'],'intro'=>['Lời giới thiệu','Một căn phòng dễ chịu, một giấc ngủ ngon và những khoảnh khắc dành riêng cho bạn. Bảo Châu chào đón mỗi chuyến đi bằng sự gần gũi và chu đáo.'],'contact'=>['Thông tin liên hệ','Địa chỉ, điện thoại và email sẽ được khách sạn cập nhật trước khi mở đặt phòng.'],'services'=>['Tiện ích (mỗi dòng: Tên | Mô tả)',"Bữa sáng | Khởi đầu ngày mới thật nhẹ nhàng.\nKhông gian xanh | Một góc nhỏ để chậm lại.\nWi-Fi | Giữ kết nối trong suốt kỳ nghỉ.\nLễ tân | Đồng hành cùng từng chuyến đi."]] as $id=>$f) {
        $c->add_setting('bch_'.$id,['default'=>$f[1],'sanitize_callback'=>'sanitize_textarea_field']);
        $c->add_control('bch_'.$id,['label'=>$f[0],'section'=>'bch_brand','type'=>'textarea']);
    }
    $c->add_setting('bch_hero_image',['sanitize_callback'=>'esc_url_raw']);
    $c->add_control(new WP_Customize_Image_Control($c,'bch_hero_image',['label'=>'Ảnh banner có quyền sử dụng','section'=>'bch_brand']));
    $c->add_setting('bch_demo',['default'=>true,'sanitize_callback'=>function ($v) { return (bool)$v; }]);
    $c->add_control('bch_demo',['label'=>'Hiện nhãn demo (chỉ tắt sau khi thay nội dung thật)','section'=>'bch_brand','type'=>'checkbox']);
});
function bch_theme_booking($args=[]) { return function_exists('bch_url')?bch_url($args):home_url('/#phong'); }
function bch_theme_image($room=null) { $images=$room?(json_decode($room->images,true)?:[]):[]; return $images[0]??get_template_directory_uri().'/assets/room.svg'; }
add_filter('template_include',function ($template) { return isset($_GET['room_detail'])?get_template_directory().'/room.php':$template; });
