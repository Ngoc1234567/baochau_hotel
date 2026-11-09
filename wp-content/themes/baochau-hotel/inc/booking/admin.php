<?php
defined('ABSPATH') || exit;
add_action('admin_enqueue_scripts',function () {
    if (($_GET['page']??'')!=='bch-rooms' || !current_user_can('manage_options')) return;
    wp_enqueue_media();
    wp_enqueue_script('bch-room-images',get_template_directory_uri().'/assets/admin-rooms.js',['media-views'],'1.0.0',true);
    wp_enqueue_style('bch-room-images',get_template_directory_uri().'/assets/admin-rooms.css',[],'1.0.0');
});
function bch_admin_room_images($value) {
    $images=[];
    foreach (preg_split('/\R/',trim($value)) as $line) {
        if (!trim($line)) continue;
        $url=esc_url_raw(trim($line),['http','https']);
        if (!$url || !wp_parse_url($url,PHP_URL_HOST)) throw new RuntimeException('URL ảnh không hợp lệ.');
        $images[]=$url;
    }
    $images=array_values(array_unique($images));
    if (count($images)>5) throw new RuntimeException('Mỗi hạng phòng được chọn tối đa 5 ảnh.');
    return $images;
}
add_action('admin_menu',function () {
    add_menu_page('Bảo Châu','Bảo Châu','bch_manage_bookings','bch-bookings','bch_admin_bookings','dashicons-building',26);
    add_submenu_page('bch-bookings','Hạng phòng','Hạng phòng','manage_options','bch-rooms','bch_admin_rooms');
    add_submenu_page('bch-bookings','Cấu hình','Cấu hình','manage_options','bch-settings','bch_admin_settings');
});
function bch_admin_input($name,$label,$value,$type='text') {
    echo '<p><label>'.esc_html($label).'<br><input class="regular-text" type="'.esc_attr($type).'" name="'.esc_attr($name).'" value="'.esc_attr($value).'" required></label></p>';
}
function bch_admin_error($e) { echo '<div class="notice notice-error"><p>'.esc_html($e->getMessage()).'</p></div>'; }
function bch_admin_settings() {
    if (!current_user_can('manage_options')) return;
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        check_admin_referer('bch_settings');
        $s=[];
        foreach (['bank','holder','account','contact'] as $k) $s[$k]=sanitize_text_field(wp_unslash($_POST[$k]??''));
        $s['hours']=max(1,min(48,absint($_POST['hours']??2)));
        $s['policy']=sanitize_textarea_field(wp_unslash($_POST['policy']??BCH_POLICY));
        $s['live']=isset($_POST['live'])?1:0; $s['mail']=isset($_POST['mail'])?1:0;
        update_option('bch_settings',$s);
        echo '<div class="notice notice-success"><p>Đã lưu cấu hình.</p></div>';
    }
    $s=bch_settings();
    echo '<div class="wrap"><h1>Cấu hình đặt phòng</h1><p>Chỉ bật nhận giữ chỗ sau khi thay toàn bộ dữ liệu demo và kiểm tra tài khoản nhận tiền.</p><form method="post">'; wp_nonce_field('bch_settings');
    foreach (['bank'=>'Ngân hàng','holder'=>'Chủ tài khoản','account'=>'Số tài khoản','contact'=>'Liên hệ lễ tân'] as $k=>$label) {
        echo '<p><label>'.esc_html($label).'<br><input class="regular-text" name="'.esc_attr($k).'" value="'.esc_attr($s[$k]).'"></label></p>';
    }
    bch_admin_input('hours','Thời gian giữ chỗ (1–48 giờ; mặc định demo: 2)',$s['hours'],'number');
    echo '<p><label>Chính sách bổ sung<br><textarea name="policy" rows="6" cols="80">'.esc_textarea($s['policy']).'</textarea></label></p>';
    foreach (['live'=>'Mở nhận giữ chỗ thật (đã kiểm tra ngân hàng và tồn phòng)','mail'=>'Gửi email (đã cấu hình SMTP và thử nhận thư)'] as $k=>$label) echo '<p><label><input type="checkbox" name="'.esc_attr($k).'" value="1" '.checked($s[$k],1,false).'>'.esc_html($label).'</label></p>';
    submit_button(); echo '</form></div>';
}
function bch_admin_rooms() {
    if (!current_user_can('manage_options')) return;
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        check_admin_referer('bch_rooms');
        try {
            $data=[];
            foreach (['name','bed','amenities'] as $k) $data[$k]=sanitize_text_field(wp_unslash($_POST[$k]??''));
            $data['description']=sanitize_textarea_field(wp_unslash($_POST['description']??''));
            foreach (['quantity','capacity','area','price'] as $k) $data[$k]=absint($_POST[$k]??0);
            if (!$data['name'] || !$data['capacity'] || !$data['price'] || $data['quantity']>10000 || $data['price']>100000000) throw new RuntimeException('Kiểm tra tên, sức chứa, số phòng và giá (tối đa 100 triệu/đêm).');
            $rates=[];
            foreach (preg_split('/\R/',trim(wp_unslash($_POST['rates']??''))) as $line) {
                if (!$line) continue;
                if (!preg_match('/^(\d{4}-\d{2}-\d{2})=(\d+)$/',trim($line),$m) || (int)$m[2]<1 || (int)$m[2]>100000000 || !bch_valid_date($m[1])) throw new RuntimeException('Giá riêng: mỗi dòng YYYY-MM-DD=950000, ngày phải hợp lệ.');
                $rates[$m[1]]=(int)$m[2];
            }
            $closed=array_values(array_filter(array_map('trim',preg_split('/\R/',wp_unslash($_POST['closed']??'')))));
            foreach ($closed as $day) if (!bch_valid_date($day)) throw new RuntimeException('Ngày đóng bán phải có dạng YYYY-MM-DD hợp lệ.');
            $images=bch_admin_room_images(wp_unslash($_POST['images']??''));
            $data['rates']=wp_json_encode($rates); $data['closed']=wp_json_encode($closed); $data['images']=wp_json_encode($images);
            bch_atomic(function () use ($data) {
                global $wpdb; $id=absint($_POST['id']??0); $table=bch_table('rooms');
                if ($id) {
                    // A reduction must not invalidate any existing occupied night.
                    $bookings=$wpdb->get_results($wpdb->prepare('SELECT checkin,checkout FROM '.bch_table('bookings')." WHERE room_id=%d AND (status='confirmed' OR (status='pending' AND expires_at>UTC_TIMESTAMP()))",$id));
                    $counts=[];
                    foreach ($bookings as $b) for ($d=new DateTimeImmutable($b->checkin);$d<new DateTimeImmutable($b->checkout);$d=$d->modify('+1 day')) { $day=$d->format('Y-m-d'); $counts[$day]=($counts[$day]??0)+1; }
                    if ($counts && max($counts)>$data['quantity']) throw new RuntimeException('Số phòng mới thấp hơn số phòng đang được giữ/đã xác nhận.');
                    $ok=$wpdb->update($table,$data,['id'=>$id]);
                } else $ok=$wpdb->insert($table,$data);
                if ($ok===false) throw new RuntimeException('Không lưu được hạng phòng.');
            });
            echo '<div class="notice notice-success"><p>Đã lưu hạng phòng.</p></div>';
        } catch (Throwable $e) { bch_admin_error($e); }
    }
    echo '<div class="wrap"><h1>Hạng phòng</h1><p>Chọn 3–5 ảnh cho mỗi hạng phòng từ thư viện hoặc tải ảnh mới. Ảnh đầu tiên là ảnh đại diện. Không có ảnh sẽ dùng hình minh họa.</p><p><a href="?page=bch-rooms">+ Thêm hạng phòng</a></p>';
    foreach (bch_rooms() as $room) echo '<p><a href="'.esc_url(add_query_arg(['page'=>'bch-rooms','edit'=>$room->id],admin_url('admin.php'))).'">'.esc_html($room->name).'</a> — '.esc_html($room->quantity).' phòng · '.esc_html(bch_money($room->price)).'</p>';
    $r=bch_room(absint($_GET['edit']??0));
    echo '<hr><h2>'.($r?'Sửa hạng phòng':'Thêm hạng phòng').'</h2><form method="post">'; wp_nonce_field('bch_rooms');
    echo '<input type="hidden" name="id" value="'.esc_attr($r->id??0).'">';
    foreach (['name'=>'Tên hạng','quantity'=>'Số phòng thực tế (0 để ngừng bán)','capacity'=>'Sức chứa','area'=>'Diện tích m²','bed'=>'Loại giường','price'=>'Giá mặc định / đêm (VND)','amenities'=>'Tiện nghi (cách nhau bằng dấu phẩy)'] as $k=>$label) bch_admin_input($k,$label,$r->$k??'',in_array($k,['quantity','capacity','area','price'])?'number':'text');
    $rates=[]; foreach (json_decode($r->rates??'{}',true)?:[] as $date=>$price) $rates[]="$date=$price";
    foreach (['description'=>['Mô tả',$r->description??''],'rates'=>['Giá từng đêm: YYYY-MM-DD=950000',implode("\n",$rates)],'closed'=>['Ngày đóng bán: YYYY-MM-DD, mỗi dòng một ngày',implode("\n",json_decode($r->closed??'[]',true)?:[])],'images'=>['URL ảnh được phép sử dụng, mỗi dòng một URL',implode("\n",json_decode($r->images??'[]',true)?:[])]] as $k=>$field) echo '<p><label>'.esc_html($field[0]).'<br><textarea rows="5" cols="80" name="'.esc_attr($k).'">'.esc_textarea($field[1]).'</textarea></label></p>';
    submit_button(); echo '</form></div>';
}
function bch_valid_date($s) { $d=DateTimeImmutable::createFromFormat('!Y-m-d',$s); return $d && $d->format('Y-m-d')===$s; }
function bch_admin_bookings() {
    if (!current_user_can('bch_manage_bookings')) return;
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        check_admin_referer('bch_booking_action');
        try {
            $action=sanitize_key($_POST['action']??'');
            if ($action==='confirm' && empty($_POST['verified'])) throw new RuntimeException('Cần xác nhận đã đối chiếu và nhận đủ cọc.');
            $r=bch_transition(absint($_POST['id']??0),$action);
            if (!$r) throw new RuntimeException('Trạng thái không cho phép thao tác. Đơn hết hạn không được khôi phục; hãy kiểm tra tồn trước khi tạo đơn mới.');
            bch_mail($r); echo '<div class="notice notice-success"><p>Đã cập nhật đơn.</p></div>';
        } catch (Throwable $e) { bch_admin_error($e); }
    }
    bch_expire(); global $wpdb;
    $page=max(1,absint($_GET['paged']??1)); $filter=sanitize_key($_GET['status']??'');
    $where=in_array($filter,['pending','confirmed','expired','cancelled'],true)?$wpdb->prepare(' WHERE status=%s',$filter):'';
    $rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.bch_table('bookings').$where.' ORDER BY id DESC LIMIT 30 OFFSET %d',($page-1)*30));
    echo '<div class="wrap"><h1>Đơn đặt phòng</h1><p>Thời gian Việt Nam. Kiểm tra chuyển khoản thực tế trước khi xác nhận. Email = kết quả gửi tới hệ thống mail, không bảo đảm thư đã đến hộp thư.</p><p><a href="?page=bch-bookings">Tất cả</a> ';
    foreach (['pending','confirmed','expired','cancelled'] as $s) echo ' | <a href="?page=bch-bookings&status='.esc_attr($s).'">'.esc_html(bch_status($s)).'</a>';
    echo '</p><table class="widefat striped"><thead><tr><th>Đơn / khách</th><th>Lưu trú</th><th>Tiền / trạng thái</th><th>Thao tác</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr><td><strong>'.esc_html($r->code).'</strong><br>'.esc_html($r->name).'<br>'.esc_html($r->phone).'<br>'.esc_html($r->email).'</td><td>'.esc_html($r->room_name).'<br>'.esc_html($r->checkin.' → '.$r->checkout).'<br>'.esc_html($r->guests).' khách</td><td>'.esc_html(bch_status($r->status)).'<br>Tổng '.esc_html(bch_money($r->total)).'<br>Cọc '.esc_html(bch_money($r->deposit)).'<br>Hạn: '.esc_html(wp_date('d/m/Y H:i',strtotime($r->expires_at.' UTC'),new DateTimeZone('Asia/Ho_Chi_Minh'))).'<br>Email: '.($r->mail_sent?'đã gửi':'chưa gửi / lỗi');
        if ($r->confirmed_at) echo '<br>Xác nhận: '.esc_html(wp_date('d/m/Y H:i',strtotime($r->confirmed_at.' UTC'),new DateTimeZone('Asia/Ho_Chi_Minh'))).' · User #'.esc_html($r->confirmed_by);
        echo '</td><td>';
        if (in_array($r->status,['pending','confirmed'],true)) {
            echo '<form method="post">'; wp_nonce_field('bch_booking_action'); echo '<input type="hidden" name="id" value="'.esc_attr($r->id).'">';
            if ($r->status==='pending') echo '<p><label><input type="checkbox" name="verified" value="1"> Đã đối chiếu đủ '.esc_html(bch_money($r->deposit)).'</label></p><button class="button button-primary" name="action" value="confirm">Xác nhận cọc</button> ';
            echo '<button class="button" name="action" value="cancel">Hủy đơn</button></form>';
        }
        echo '</td></tr>';
    }
    echo '</tbody></table><p>';
    if ($page>1) echo '<a href="'.esc_url(add_query_arg('paged',$page-1)).'">← Trang trước</a> ';
    if (count($rows)===30) echo '<a href="'.esc_url(add_query_arg('paged',$page+1)).'">Trang sau →</a>';
    echo '</p></div>';
}
