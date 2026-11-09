<?php
defined('ABSPATH') || exit;
add_action('template_redirect',function () {
    if (is_page((int)get_option('bch_booking_page'))) {
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE',true);
        nocache_headers(); header('Referrer-Policy: no-referrer'); header('X-Robots-Tag: noindex, nofollow');
    }
});
add_action('init',function () {
    add_shortcode('baochau_booking','bch_booking_screen');
    add_shortcode('baochau_policy',function () { return '<p>'.esc_html(BCH_POLICY).'</p><p>'.nl2br(esc_html(bch_settings()['policy'])).'</p><p>Thời gian giữ chỗ: '.esc_html(bch_settings()['hours']).' giờ. Giao dịch đến muộn: liên hệ lễ tân; đơn hết hạn không tự khôi phục.</p>'; });
});
function bch_search_form($in='',$out='',$guests=2) {
    $today=wp_date('Y-m-d',null,new DateTimeZone('Asia/Ho_Chi_Minh'));
    ob_start(); ?>
    <form class="booking-search" method="get" action="<?php echo esc_url(bch_url()); ?>">
        <?php if (!get_option('permalink_structure')): ?><input type="hidden" name="page_id" value="<?php echo esc_attr(get_option('bch_booking_page')); ?>"><?php endif; ?>
        <label>Ngày nhận phòng<input type="date" name="in" min="<?php echo esc_attr($today); ?>" value="<?php echo esc_attr($in); ?>" required></label>
        <label>Ngày trả phòng<input type="date" name="out" min="<?php echo esc_attr($today); ?>" value="<?php echo esc_attr($out); ?>" required></label>
        <label>Số khách<select name="guests"><?php for ($i=1;$i<=10;$i++) echo '<option value="'.$i.'" '.selected($guests,$i,false).'>'.$i.' khách</option>'; ?></select></label>
        <button type="submit">Tìm phòng trống <span aria-hidden="true">↗</span></button>
    </form>
    <?php return ob_get_clean();
}
function bch_booking_screen() {
    ob_start();
    echo '<div class="booking-flow">';
    if (!bch_ready()) echo '<p class="notice">Bản demo — chưa mở nhận giữ chỗ thật. Bạn có thể tìm phòng và xem giá minh họa.</p>';
    try {
        if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['bch_hold'])) {
            if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce']??'')),'bch_hold')) throw new RuntimeException('Phiên đã hết hạn. Vui lòng tải lại trang.');
            $data=[]; foreach (['in','out','key','token','name','phone','email'] as $k) $data[$k]=sanitize_text_field(wp_unslash($_POST[$k]??''));
            foreach (['room','guests','quoted'] as $k) $data[$k]=absint($_POST[$k]??0);
            if (empty($_POST['agree'])) throw new RuntimeException('Vui lòng đồng ý chính sách đặt cọc.');
            // Soft abuse limit; inventory correctness is enforced inside the DB transaction.
            $rate='bch_rate_'.hash('sha256',$_SERVER['REMOTE_ADDR']??''); $count=(int)get_transient($rate);
            if ($count>=20) throw new RuntimeException('Bạn gửi quá nhiều yêu cầu. Vui lòng thử lại sau 10 phút.');
            set_transient($rate,$count+1,600);
            $r=bch_hold($data);
            if (!$r->mail_sent) bch_mail($r);
            bch_receipt($r); echo '</div>'; return ob_get_clean();
        }
        $in=sanitize_text_field(wp_unslash($_GET['in']??'')); $out=sanitize_text_field(wp_unslash($_GET['out']??'')); $guests=absint($_GET['guests']??2);
        echo bch_search_form($in,$out,$guests);
        if ($in || $out) {
            $days=bch_dates($in,$out); $selected=absint($_GET['room']??0);
            if ($selected) {
                $room=bch_room($selected); if (!$room) throw new RuntimeException('Không tìm thấy phòng.');
                $quote=bch_price($room,$days,$guests);
                if (!bch_available($room,$days)) throw new RuntimeException('Hạng phòng đã hết chỗ.');
                bch_checkout($room,$quote,$in,$out,$guests);
            } else {
                echo '<h2>Phòng dành cho kỳ nghỉ của bạn</h2><p>'.esc_html(count($days)).' đêm · '.esc_html($guests).' khách · Giá cho 1 phòng</p><div class="room-grid">'; $found=0;
                foreach (bch_rooms() as $room) {
                    try { $quote=bch_price($room,$days,$guests); } catch (InvalidArgumentException $e) { continue; }
                    $left=bch_available($room,$days); if (!$left) continue; $found++;
                    echo '<article class="room-card"><h3>'.esc_html($room->name).'</h3><p>'.esc_html($room->area).' m² · '.esc_html($room->bed).'</p><p>Còn '.esc_html($left).' phòng</p><strong>'.esc_html(bch_money($quote['total'])).' / '.count($days).' đêm</strong><p>Cọc 30%: '.esc_html(bch_money($quote['deposit'])).'</p><a class="button" href="'.esc_url(bch_url(['in'=>$in,'out'=>$out,'guests'=>$guests,'room'=>$room->id])).'">Chọn phòng →</a></article>';
                }
                echo '</div>'; if (!$found) echo '<p role="status">Không còn phòng phù hợp. Hãy đổi ngày hoặc số khách.</p>';
            }
        } else echo '<p>Chọn ngày để kiểm tra phòng trống và tổng tiền cho toàn bộ kỳ nghỉ.</p>';
    } catch (Throwable $e) {
        echo '<div class="notice error" role="alert">'.esc_html($e->getMessage()).'</div><p><a href="'.esc_url(bch_url()).'">Quay lại tìm phòng</a></p>';
    }
    echo '</div>'; return ob_get_clean();
}
function bch_checkout($room,$q,$in,$out,$guests) {
    echo '<div class="checkout"><section><p class="eyebrow">KỲ NGHỈ CỦA BẠN</p><h2>'.esc_html($room->name).'</h2><p>'.esc_html($in.' → '.$out).' · '.esc_html($guests).' khách</p><details><summary>Chi tiết giá từng đêm</summary><ul>';
    foreach ($q['nightly'] as $day=>$price) echo '<li>'.esc_html($day).' — '.esc_html(bch_money($price)).'</li>';
    echo '</ul></details><p>Tổng tiền phòng <strong>'.esc_html(bch_money($q['total'])).'</strong></p><p>Cọc 30% <strong>'.esc_html(bch_money($q['deposit'])).'</strong></p><p>Còn lại '.esc_html(bch_money($q['total']-$q['deposit'])).'</p><p>Thời hạn giữ chỗ: '.esc_html(bch_settings()['hours']).' giờ kể từ khi tạo đơn.</p></section><form method="post" class="guest-form">';
    wp_nonce_field('bch_hold');
    $fields=['bch_hold'=>1,'room'=>$room->id,'in'=>$in,'out'=>$out,'guests'=>$guests,'quoted'=>$q['total'],'key'=>bin2hex(random_bytes(32)),'token'=>bin2hex(random_bytes(32))];
    foreach ($fields as $k=>$v) echo '<input type="hidden" name="'.esc_attr($k).'" value="'.esc_attr($v).'">';
    echo '<h2>Thông tin khách</h2><label>Họ tên<input name="name" autocomplete="name" maxlength="190" required></label><label>Số điện thoại<input type="tel" name="phone" autocomplete="tel" maxlength="25" required></label><label>Email<input type="email" name="email" autocomplete="email" maxlength="190" required></label><p>'.esc_html(BCH_POLICY).'</p><label class="consent"><input type="checkbox" name="agree" required> Tôi đồng ý <a href="'.esc_url(get_permalink(get_option('bch_policy_page'))).'">chính sách đặt cọc</a>.</label><button '.disabled(bch_ready(),false,false).'>Giữ chỗ · Cọc '.esc_html(bch_money($q['deposit'])).'</button></form></div>';
}
function bch_receipt($r) {
    echo '<section class="receipt"><p class="eyebrow">THÔNG TIN GIỮ CHỖ</p><h2>'.esc_html(bch_status($r->status)).'</h2><p>Lưu lại nội dung này và mã đặt phòng để liên hệ lễ tân.</p><pre>'.esc_html(bch_receipt_text($r)).'</pre>';
    if (in_array($r->status,['expired','cancelled'],true)) echo '<p class="notice">Không chuyển khoản cho đơn này. Nếu đã chuyển tiền, liên hệ lễ tân để kiểm tra; không tự khôi phục đơn.</p>';
    echo '</section>';
}
