<?php
/**
 * Plugin Name: Bảo Châu Booking
 * Description: Đặt phòng trực tiếp, cọc chuyển khoản 30%, quản lý tồn và hết hạn giữ chỗ.
 * Version: 1.0.0
 * Requires PHP: 8.0
 * Requires at least: 6.5
 * License: GPL-2.0-or-later
 */
defined('ABSPATH') || exit;
require_once __DIR__ . '/includes/domain.php';
require_once __DIR__ . '/includes/store.php';
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/public.php';
register_activation_hook(__FILE__, 'bch_install');
register_deactivation_hook(__FILE__, function () { wp_clear_scheduled_hook('bch_expire'); });
add_filter('cron_schedules', function ($s) { $s['bch_minute'] = ['interval'=>60,'display'=>'Mỗi phút']; return $s; });
add_action('plugins_loaded', function () {
    if (get_option('bch_schema') !== '1') bch_install();
    if (!wp_next_scheduled('bch_expire')) wp_schedule_event(time(), 'bch_minute', 'bch_expire');
});
add_action('bch_expire', 'bch_expire');
function bch_settings() { return wp_parse_args(get_option('bch_settings', []), ['bank'=>'','holder'=>'','account'=>'','hours'=>2,'live'=>0,'mail'=>0,'contact'=>'Vui lòng cập nhật thông tin lễ tân','policy'=>BCH_POLICY]); }
function bch_ready() { $s=bch_settings(); return $s['live'] && $s['bank'] && $s['holder'] && $s['account']; }
function bch_money($n) { return number_format((int)$n, 0, ',', '.') . ' ₫'; }
function bch_url($args=[]) { return add_query_arg($args, get_permalink((int)get_option('bch_booking_page')) ?: home_url('/')); }
function bch_status($s) { return ['pending'=>'Chờ cọc','confirmed'=>'Đã xác nhận','expired'=>'Hết hạn','cancelled'=>'Đã hủy'][$s] ?? $s; }
const BCH_POLICY = 'Quý khách cần chuyển khoản cọc 30% tổng tiền phòng trước thời hạn hiển thị. Đặt phòng được xác nhận sau khi khách sạn đối chiếu khoản cọc. Nếu đến hạn mà đơn vẫn chờ cọc, hệ thống sẽ giải phóng phòng. Nếu đã chuyển khoản nhưng chưa nhận xác nhận, vui lòng liên hệ lễ tân và cung cấp mã đặt phòng.';
