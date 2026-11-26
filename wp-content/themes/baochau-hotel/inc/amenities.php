<?php
defined('ABSPATH') || exit;

/** Apply the approved amenities copy once; subsequent editor changes are preserved. */
function bch_update_amenities_content() {
    if (get_option('bch_amenities_content_version') === '2026-11-26') return;
    $page = get_page_by_path('tien-ich');
    if (!$page) return;

    $cards = [
        ['Di chuyển & tầm nhìn', "Thang máy di chuyển êm ái.\nTầm nhìn thoáng rộng, ngắm cảnh phố thị."],
        ['Phòng nghỉ thoải mái', "Chăn, ga, gối cotton thoáng mát.\nĐiều hòa hai chiều.\nRèm cản sáng cho giấc ngủ trọn vẹn."],
        ['Kết nối & giải trí', "Wi-Fi tốc độ cao.\nSmart TV kết nối mượt mà."],
        ['Tiện nghi mỗi ngày', "Vòi sen mưa nóng lạnh, máy sấy tóc.\nẤm đun siêu tốc.\nTrà và nước suối miễn phí mỗi ngày."],
        ['Dịch vụ hỗ trợ', "Tư vấn địa điểm ăn uống ngon, đậm hương vị Tây Bắc.\nThuê xe máy/ô tô giao tận sảnh.\nHỗ trợ đặt vé và tour tham quan.\nGiặt là lấy trong ngày."],
        ['Dạo bộ khám phá phố thị', "1 phút đến Quảng trường.\n5 phút đến phố ẩm thực/chợ đêm.\n8 phút đến đồi A1.\n10 phút đến chợ trung tâm.\n12 phút đến hầm Đờ Cát."],
    ];

    // Keep the previous page and service fields available for recovery.
    if (!metadata_exists('post', $page->ID, '_bch_amenities_before_20261126')) {
        $backup = ['post_content' => $page->post_content, 'fields' => []];
        $names = ['bch_page_body'];
        for ($i = 1; $i <= 6; $i++) {
            foreach (['title', 'text', 'image'] as $part) $names[] = 'bch_service_'.$i.'_'.$part;
        }
        foreach ($names as $name) $backup['fields'][$name] = get_post_meta($page->ID, $name, false);
        if (!add_post_meta($page->ID, '_bch_amenities_before_20261126', $backup, true)) return;
    }

    $intro = '';
    $result = wp_update_post(['ID' => $page->ID, 'post_content' => $intro], true);
    if (is_wp_error($result)) return;
    $set = function ($name, $value) use ($page) {
        update_post_meta($page->ID, $name, $value);
        update_post_meta($page->ID, '_'.$name, 'field_'.$name);
    };
    $set('bch_page_body', $intro);
    foreach ($cards as $index => $card) {
        $prefix = 'bch_service_'.($index + 1).'_';
        $set($prefix.'title', $card[0]);
        $set($prefix.'text', $card[1]);
        $set($prefix.'image', '');
    }
    update_option('bch_amenities_content_version', '2026-11-26');
}
add_action('init', 'bch_update_amenities_content', 30);

function bch_remove_amenities_intro() {
    if (get_option('bch_amenities_intro_removed')) return;
    $page = get_page_by_path('tien-ich');
    if (!$page) return;
    $intro = '<p>Tận hưởng kỳ nghỉ thoải mái tại Bảo Châu với tiện nghi chu đáo, dịch vụ hỗ trợ thuận tiện và những điểm đến chỉ cách vài phút đi bộ.</p>';
    $result = wp_update_post(['ID' => $page->ID, 'post_content' => str_replace($intro, '', $page->post_content)], true);
    if (is_wp_error($result)) return;
    $body = get_post_meta($page->ID, 'bch_page_body', true);
    update_post_meta($page->ID, 'bch_page_body', str_replace($intro, '', $body));
    update_option('bch_amenities_intro_removed', 1);
}
add_action('init', 'bch_remove_amenities_intro', 31);
