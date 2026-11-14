<?php
defined('ABSPATH') || exit;
require_once __DIR__ . '/domain.php';
require_once __DIR__ . '/tariffs.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/stays.php';
require_once __DIR__ . '/upgrades.php';
require_once __DIR__ . '/availability.php';
require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/counter.php';
require_once __DIR__ . '/public.php';
add_filter('cron_schedules', function ($s) { $s['bch_minute'] = ['interval'=>60,'display'=>'Mỗi phút']; return $s; });
add_action('init', function () {
    if (get_option('bch_schema') !== '5') bch_install();
    if (!wp_next_scheduled('bch_expire')) wp_schedule_event(time(), 'bch_minute', 'bch_expire');
});
add_action('bch_expire', 'bch_expire');
function bch_settings() {
    $s=wp_parse_args(get_option('bch_settings', []), ['bank'=>'','holder'=>'','account'=>'','qr_url'=>'','hours'=>2,'free_upgrade'=>0,'live'=>0,'mail'=>0,'contact'=>'Vui lòng cập nhật thông tin lễ tân','policy'=>BCH_POLICY]);
    $page=(int)get_option('bch_booking_page');
    foreach (['bank','holder','account','live'] as $key) {
        $field='bch_payment_'.$key;
        if ($page && metadata_exists('post',$page,$field)) {
            $value=get_post_meta($page,$field,true);
            $s[$key]=$key==='live'?(int)(bool)$value:sanitize_text_field($value);
        }
    }
    if ($page && metadata_exists('post',$page,'bch_payment_qr')) {
        $id=absint(get_post_meta($page,'bch_payment_qr',true));
        $s['qr_url']=$id && wp_attachment_is_image($id)?(wp_get_attachment_image_url($id,'full')?:''):'';
    }
    return $s;
}
function bch_ready() { $s=bch_settings(); return $s['live'] && $s['bank'] && $s['holder'] && $s['account']; }
function bch_money($n) { return number_format((int)$n, 0, ',', '.') . ' ₫'; }
function bch_url($args=[]) { return add_query_arg($args, get_permalink((int)get_option('bch_booking_page')) ?: home_url('/')); }
function bch_status($s) { return ['pending'=>'Chờ cọc','confirmed'=>'Đã xác nhận','checked_in'=>'Đã nhận phòng','expired'=>'Hết hạn','cancelled'=>'Đã hủy'][$s] ?? $s; }
const BCH_POLICY = 'Quý khách cần chuyển khoản cọc 30% tổng tiền phòng trước thời hạn hiển thị. Đặt phòng được xác nhận sau khi khách sạn đối chiếu khoản cọc. Nếu đến hạn mà đơn vẫn chờ cọc, hệ thống sẽ giải phóng phòng. Nếu đã chuyển khoản nhưng chưa nhận xác nhận, vui lòng liên hệ lễ tân và cung cấp mã đặt phòng.';
