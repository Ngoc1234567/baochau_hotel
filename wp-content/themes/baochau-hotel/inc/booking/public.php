<?php
defined('ABSPATH') || exit;
add_action('template_redirect',function () {
    if (is_page((int)get_option('bch_booking_page')) || isset($_GET['room_detail'])) {
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE',true);
        nocache_headers(); header('Referrer-Policy: no-referrer'); header('X-Robots-Tag: noindex, nofollow');
    }
});
add_action('init',function () {
    add_shortcode('baochau_booking','bch_booking_screen');
    add_shortcode('baochau_policy',function () { return '<p>'.esc_html(BCH_POLICY).'</p><p>'.nl2br(esc_html(bch_settings()['policy'])).'</p><p>Thời gian giữ chỗ: '.esc_html(bch_settings()['hours']).' giờ. Giao dịch đến muộn: liên hệ lễ tân; đơn hết hạn không tự khôi phục.</p>'; });
});
function bch_form_dates($in='',$out='') {
    $tz=new DateTimeZone('Asia/Ho_Chi_Minh');
    $now=new DateTimeImmutable('today',$tz);
    $today=$now->format('Y-m-d');
    if ($in==='') $in=$today;
    $start=DateTimeImmutable::createFromFormat('!Y-m-d',$in,$tz);
    $next=($start && $start->format('Y-m-d')===$in ? $start : $now)->modify('+1 day')->format('Y-m-d');
    if ($out==='') $out=$next;
    return [$in,$out,$today,$next];
}
function bch_search_form($in='',$out='',$guests=2) {
    [$in,$out,$today,$next]=bch_form_dates($in,$out);
    ob_start(); ?>
    <form class="booking-search" method="get" action="<?php echo esc_url(bch_url()); ?>">
        <?php if (!get_option('permalink_structure')): ?><input type="hidden" name="page_id" value="<?php echo esc_attr(get_option('bch_booking_page')); ?>"><?php endif; ?>
        <label>Ngày nhận phòng<input type="date" name="in" min="<?php echo esc_attr($today); ?>" value="<?php echo esc_attr($in); ?>" required></label>
        <label>Ngày trả phòng<input type="date" name="out" min="<?php echo esc_attr($next); ?>" value="<?php echo esc_attr($out); ?>" required></label>
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
            $data=[]; foreach (['in','out','key','token','name','phone','email'] as $k) $data[$k]=sanitize_text_field(wp_unslash($_POST[$k === 'name' ? 'guest_name' : $k]??''));
            foreach (['room','guests','quoted'] as $k) $data[$k]=absint($_POST[$k === 'name' ? 'guest_name' : $k]??0);
            if (empty($_POST['agree'])) throw new RuntimeException('Vui lòng đồng ý chính sách đặt cọc.');
            // Soft abuse limit; inventory correctness is enforced inside the DB transaction.
            $rate='bch_rate_'.hash('sha256',$_SERVER['REMOTE_ADDR']??''); $count=(int)get_transient($rate);
            if ($count>=20) throw new RuntimeException('Bạn gửi quá nhiều yêu cầu. Vui lòng thử lại sau 10 phút.');
            set_transient($rate,$count+1,600);
            $r=bch_hold($data);
            if (!$r->mail_sent) bch_mail($r);
            bch_receipt($r); echo '</div>'; return ob_get_clean();
        }
        if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['bch_preview'])) {
            if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce']??'')),'bch_preview')) throw new RuntimeException('Phiên đã hết hạn. Vui lòng tải lại trang.');
            $in=sanitize_text_field(wp_unslash($_POST['in']??'')); $out=sanitize_text_field(wp_unslash($_POST['out']??'')); $guests=absint($_POST['guests']??0);
            $days=bch_dates($in,$out); $room=bch_room(absint($_POST['room']??0));
            if (!$room) throw new RuntimeException('Vui lòng chọn hạng phòng.');
            $quote=bch_price($room,$days,$guests);
            if (!bch_available($room,$days)) throw new RuntimeException('Hạng phòng này đã hết chỗ trong ngày bạn chọn. Vui lòng đổi hạng phòng hoặc ngày lưu trú.');
            $guest=[]; foreach (['name','phone','email'] as $key) $guest[$key]=sanitize_text_field(wp_unslash($_POST[$key === 'name' ? 'guest_name' : $key]??''));
            if (!$guest['name'] || strlen($guest['name'])>190 || !is_email($guest['email']) || strlen($guest['email'])>190 || !preg_match('/^[+0-9 ()-]{8,25}$/',$guest['phone'])) throw new RuntimeException('Vui lòng nhập đúng họ tên, số điện thoại và email để tiếp tục.');
            echo '<p class="booking-step">BƯỚC 2 / 2 · KIỂM TRA & GIỮ CHỖ</p>';
            bch_checkout($room,$quote,$in,$out,$guests,$guest);
            bch_reservation_form(array_merge($guest,['in'=>$in,'out'=>$out,'guests'=>$guests,'room'=>$room->id]),true);
            echo '</div>'; return ob_get_clean();
        }
        $in=sanitize_text_field(wp_unslash($_GET['in']??'')); $out=sanitize_text_field(wp_unslash($_GET['out']??'')); $guests=absint($_GET['guests']??2);
        if (!$in && !$out) {
            bch_reservation_form(['room'=>absint($_GET['room']??0)]);
            echo '</div>'; return ob_get_clean();
        }
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
                    $link=add_query_arg(['room_detail'=>$room->id,'in'=>$in,'out'=>$out,'guests'=>$guests],bch_page_url('phong-nghi'));
                    echo '<article class="room-card"><a href="'.esc_url($link).'"><img loading="lazy" src="'.esc_url(bch_theme_image($room)).'" alt="'.esc_attr($room->name).'"></a><p class="eyebrow">PHÒNG NGHỈ'.(get_theme_mod('bch_demo',true)?' · DEMO':'').'</p><h3>'.esc_html($room->name).'</h3><p>'.esc_html($room->area).' m² · '.esc_html($room->capacity).' khách · '.esc_html($room->bed).'</p><p>Còn '.esc_html($left).' phòng</p><p class="price">'.esc_html(bch_money($quote['total'])).' <small>/ '.count($days).' đêm</small></p><p>Cọc 30%: '.esc_html(bch_money($quote['deposit'])).'</p><div class="card-actions"><a class="button" href="'.esc_url($link).'">Đặt phòng →</a></div></article>';
                }
                echo '</div>'; if (!$found) echo '<p role="status">Không còn phòng phù hợp. Hãy đổi ngày hoặc số khách.</p>';
            }
        } else echo '<p>Chọn ngày để kiểm tra phòng trống và tổng tiền cho toàn bộ kỳ nghỉ.</p>';
    } catch (Throwable $e) {
        echo '<div class="notice error" role="alert">'.esc_html($e->getMessage()).'</div><p><a href="'.esc_url(bch_url()).'">Quay lại tìm phòng</a></p>';
        if (isset($_POST['bch_preview'])) {
            $values=[]; foreach (['name','phone','email','in','out','guests','room'] as $key) $values[$key]=sanitize_text_field(wp_unslash($_POST[$key === 'name' ? 'guest_name' : $key]??''));
            bch_reservation_form($values);
        }
    }
    echo '</div>'; return ob_get_clean();
}
function bch_checkout($room,$q,$in,$out,$guests,$guest=[]) {
    echo '<div class="checkout"><section><p class="eyebrow">KỲ NGHỈ CỦA BẠN</p><h2>'.esc_html($room->name).'</h2><p>'.esc_html($in.' → '.$out).' · '.esc_html($guests).' khách</p><details><summary>Chi tiết giá từng đêm</summary><ul>';
    foreach ($q['nightly'] as $day=>$price) echo '<li>'.esc_html($day).' — '.esc_html(bch_money($price)).'</li>';
    echo '</ul></details><p>Tổng tiền phòng <strong>'.esc_html(bch_money($q['total'])).'</strong></p><p>Cọc 30% <strong>'.esc_html(bch_money($q['deposit'])).'</strong></p><p>Còn lại '.esc_html(bch_money($q['total']-$q['deposit'])).'</p><p>Thời hạn giữ chỗ: '.esc_html(bch_settings()['hours']).' giờ kể từ khi tạo đơn.</p></section><form method="post" class="guest-form">';
    wp_nonce_field('bch_hold');
    $fields=['bch_hold'=>1,'room'=>$room->id,'in'=>$in,'out'=>$out,'guests'=>$guests,'quoted'=>$q['total'],'key'=>bin2hex(random_bytes(32)),'token'=>bin2hex(random_bytes(32))];
    foreach ($fields as $k=>$v) echo '<input type="hidden" name="'.esc_attr($k).'" value="'.esc_attr($v).'">';
    echo '<h2>Thông tin khách</h2><label>Họ tên<input name="guest_name" autocomplete="name" maxlength="190" value="'.esc_attr($guest['name']??'').'" required></label><label>Số điện thoại<input type="tel" name="phone" autocomplete="tel" maxlength="25" value="'.esc_attr($guest['phone']??'').'" required></label><label>Email<input type="email" name="email" autocomplete="email" maxlength="190" value="'.esc_attr($guest['email']??'').'" required></label><p>'.esc_html(BCH_POLICY).'</p><label class="consent"><input type="checkbox" name="agree" required> Tôi đồng ý <a href="'.esc_url(get_permalink(get_option('bch_policy_page'))).'">chính sách đặt cọc</a>.</label><button '.disabled(bch_ready(),false,false).'>Giữ chỗ · Cọc '.esc_html(bch_money($q['deposit'])).'</button></form></div>';
}
function bch_reservation_form($values=[],$collapsed=false,$compact=false) {
    $v=wp_parse_args($values,['name'=>'','phone'=>'','email'=>'','in'=>'','out'=>'','guests'=>2,'room'=>0]);
    $fixed_room=$compact?bch_room(absint($v['room'])):null;
    if ($fixed_room) $v['guests']=min((int)$fixed_room->capacity,max(1,(int)$v['guests']));
    [$v['in'],$v['out'],$today,$next]=bch_form_dates($v['in'],$v['out']);
    if ($collapsed) echo '<details class="booking-edit"><summary>Thay đổi ngày ở hoặc hạng phòng</summary>';
    ?>
    <div class="reservation-layout<?php echo $compact?' reservation-compact':''; ?>">
        <section class="reservation-main" aria-label="Thông tin đặt phòng">
            <?php if (!$compact): ?>
            <p class="booking-step">BƯỚC 1 / 2 · THÔNG TIN ĐẶT PHÒNG</p>
            <h2>Kỳ nghỉ của bạn tại Bảo Châu</h2>
            <p class="reservation-intro">Điền thông tin và chọn hạng phòng bạn yêu thích. Chúng tôi sẽ kiểm tra phòng trống và tính tổng tiền cho kỳ nghỉ trước khi bạn xác nhận giữ chỗ.</p>
            <?php else: ?><h2>Đặt phòng này</h2><?php endif; ?>
            <form method="post" action="<?php echo esc_url(bch_url()); ?>" class="reservation-form">
                <?php wp_nonce_field('bch_preview'); ?>
                <div class="reservation-fields">
                    <label>Họ và tên <span aria-hidden="true">*</span><input name="guest_name" autocomplete="name" maxlength="190" placeholder="Nhập họ và tên" value="<?php echo esc_attr($v['name']); ?>" required></label>
                    <label>Số điện thoại <span aria-hidden="true">*</span><input name="phone" type="tel" autocomplete="tel" maxlength="25" placeholder="Số điện thoại liên hệ" value="<?php echo esc_attr($v['phone']); ?>" required></label>
                    <label>Ngày nhận phòng <span aria-hidden="true">*</span><input type="date" name="in" min="<?php echo esc_attr($today); ?>" value="<?php echo esc_attr($v['in']); ?>" required></label>
                    <label>Ngày trả phòng <span aria-hidden="true">*</span><input type="date" name="out" min="<?php echo esc_attr($next); ?>" value="<?php echo esc_attr($v['out']); ?>" required></label>
                    <label>Số khách <span aria-hidden="true">*</span><select name="guests"><?php for ($i=1;$i<=($fixed_room?(int)$fixed_room->capacity:10);$i++) echo '<option value="'.$i.'" '.selected($v['guests'],$i,false).'>'.$i.' khách</option>'; ?></select></label>
                    <?php if ($fixed_room): ?><input type="hidden" name="room" value="<?php echo esc_attr($fixed_room->id); ?>"><?php else: ?>
                    <label>Chọn hạng phòng <span aria-hidden="true">*</span><select name="room" required><option value="">Chọn phòng bạn yêu thích</option><?php foreach (bch_rooms() as $r) { if ((int)$r->quantity<1) continue; echo '<option value="'.esc_attr($r->id).'" '.selected($v['room'],$r->id,false).'>'.esc_html($r->name.' · tối đa '.$r->capacity.' khách').'</option>'; } ?></select></label>
                    <?php endif; ?>
                    <label class="reservation-wide">Email <span aria-hidden="true">*</span><input name="email" type="email" autocomplete="email" maxlength="190" placeholder="Email nhận thông tin đặt phòng" value="<?php echo esc_attr($v['email']); ?>" required></label>
                </div>
                <p class="reservation-help">Các trường có dấu * là bắt buộc. Mỗi yêu cầu đặt 1 phòng. Bước này chưa tạo đơn hoặc giữ phòng.</p>
                <button type="submit" name="bch_preview" value="1">Kiểm tra phòng & xem giá <span aria-hidden="true">→</span></button>
            </form>
        </section>
        <?php if (!$compact): ?><aside class="reservation-aside" aria-label="Hướng dẫn đặt phòng">
            <p class="eyebrow">ĐẶT TRỰC TIẾP VỚI KHÁCH SẠN</p>
            <h3>An tâm cho<br>mỗi kỳ nghỉ.</h3>
            <ol class="reservation-guide"><li><strong>Chọn kỳ nghỉ</strong><span>Ngày lưu trú, số khách và hạng phòng phù hợp.</span></li><li><strong>Kiểm tra & giữ chỗ</strong><span>Xem tổng tiền và khoản cọc 30% trước khi gửi.</span></li><li><strong>Xác nhận đặt phòng</strong><span>Khách sạn xác nhận sau khi đối chiếu đủ tiền cọc.</span></li></ol>
            <div class="reservation-support"><strong>Bạn cần hỗ trợ?</strong><p><?php echo esc_html(bch_settings()['contact']); ?></p><a href="<?php echo esc_url(get_permalink(get_option('bch_policy_page'))); ?>">Xem chính sách đặt cọc →</a></div>
        </aside><?php endif; ?>
    </div>
    <?php
    if ($collapsed) echo '</details>';
}
function bch_receipt($r) {
    $pending=$r->status==='pending' && strtotime($r->expires_at.' UTC')>time();
    $expired=$r->status==='pending' && !$pending;
    echo '<section class="receipt"><p class="eyebrow">THÔNG TIN GIỮ CHỖ</p><h2>'.esc_html(bch_status($expired?'expired':$r->status)).'</h2><p>Mã đặt phòng: <strong>'.esc_html($r->code).'</strong></p><p>'.esc_html($r->room_name).' · '.esc_html($r->checkin.' → '.$r->checkout).' · '.esc_html($r->guests).' khách</p><p>Tổng tiền phòng: <strong>'.esc_html(bch_money($r->total)).'</strong></p>';
    if ($pending) {
        $bank=json_decode($r->bank,true)?:[];
        echo '<div class="manual-deposit"><h3>Chuyển khoản cọc để xác nhận phòng</h3><p>Vui lòng chuyển khoản theo thông tin bên dưới. Khách sạn sẽ kiểm tra và xác nhận thủ công sau khi nhận đủ tiền cọc.</p><dl>';
        foreach (['Ngân hàng'=>$bank['bank']??'','Số tài khoản'=>$bank['account']??'','Chủ tài khoản'=>$bank['holder']??'','Số tiền cọc (30%)'=>bch_money($r->deposit),'Nội dung chuyển khoản'=>$r->code] as $label=>$value) echo '<dt>'.esc_html($label).'</dt><dd>'.esc_html($value).'</dd>';
        echo '</dl>';
        if (!empty($bank['qr_url'])) echo '<figure class="deposit-qr"><a href="'.esc_url($bank['qr_url']).'" target="_blank" rel="noopener"><img src="'.esc_url($bank['qr_url']).'" alt="Mã QR tài khoản nhận cọc" width="320" height="320"></a><figcaption>Quét QR hoặc bấm ảnh để mở và lưu. Nhập đúng số tiền cọc và mã đặt phòng ở trên.</figcaption></figure>';
        echo '<p>Chuyển khoản trước <strong>'.esc_html(wp_date('d/m/Y H:i',strtotime($r->expires_at.' UTC'),new DateTimeZone('Asia/Ho_Chi_Minh'))).'</strong> (giờ Việt Nam).</p><p>Đơn đang chờ nhận cọc. Lưu lại mã đặt phòng để liên hệ lễ tân.</p></div>';
    }
    if ($expired || in_array($r->status,['expired','cancelled'],true)) echo '<p class="notice">Không chuyển khoản cho đơn này. Nếu đã chuyển tiền, liên hệ lễ tân để kiểm tra; không tự khôi phục đơn.</p>';
    echo '<p>Còn lại sau khi thanh toán đủ cọc: '.esc_html(bch_money($r->total-$r->deposit)).'</p><p>'.esc_html(BCH_POLICY).'</p><p>'.esc_html(bch_settings()['contact']).'</p>';
    echo '</section>';
}
