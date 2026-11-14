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
    add_submenu_page('bch-bookings','Đơn đặt phòng','Đơn đặt phòng','bch_manage_bookings','bch-bookings','bch_admin_bookings');
    add_submenu_page('bch-bookings','Tạo đơn tại quầy','Tạo đơn tại quầy','bch_manage_bookings','bch-counter','bch_admin_counter');
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
        $s['free_upgrade']=isset($_POST['free_upgrade'])?1:0;
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
    foreach (['free_upgrade'=>'Cho phép nâng hạng miễn phí khi hết phòng: 1 giường đôi → 2 giường đôi (khách phải đồng ý)','live'=>'Mở nhận giữ chỗ thật (đã kiểm tra ngân hàng và tồn phòng)','mail'=>'Gửi email (đã cấu hình SMTP và thử nhận thư)'] as $k=>$label) echo '<p><label><input type="checkbox" name="'.esc_attr($k).'" value="1" '.checked($s[$k],1,false).'>'.esc_html($label).'</label></p>';
    submit_button(); echo '</form></div>';
}
function bch_admin_rooms() {
    if (!current_user_can('manage_options')) return;
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        check_admin_referer('bch_rooms');
        try {
            if (isset($_POST['room_action'])) {
                $action=sanitize_key($_POST['room_action']);
                if (!in_array($action,['trash','restore'],true)) throw new RuntimeException('Thao tác không hợp lệ.');
                bch_atomic(function () use ($action) {
                    $id=absint($_POST['id']??0);
                    if (!bch_room($id,true)) throw new RuntimeException('Không tìm thấy hạng phòng.');
                    $deleted=array_map('intval',get_option('bch_deleted_rooms',[]));
                    if ($action==='trash') $deleted[]=$id;
                    else $deleted=array_diff($deleted,[$id]);
                    update_option('bch_deleted_rooms',array_values(array_unique($deleted)));
                });
                echo '<div class="notice notice-success"><p>'.($action==='trash'?'Đã chuyển hạng phòng vào thùng rác. Đơn cũ được giữ nguyên.':'Đã khôi phục hạng phòng.').'</p></div>';
            } else {
            $data=[];
            foreach (['name','bed','amenities'] as $k) $data[$k]=sanitize_text_field(wp_unslash($_POST[$k]??''));
            $data['description']=sanitize_textarea_field(wp_unslash($_POST['description']??''));
            foreach (['quantity','capacity','area','price','hourly_price'] as $k) $data[$k]=absint($_POST[$k]??0);
            if (!$data['name'] || !$data['capacity'] || !$data['price'] || $data['quantity']>10000 || $data['price']>100000000 || $data['hourly_price']>100000000) throw new RuntimeException('Kiểm tra tên, sức chứa, số phòng và giá (tối đa 100 triệu/đêm).');
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
                    if (!bch_room($id,true) || in_array($id,array_map('intval',get_option('bch_deleted_rooms',[])),true)) throw new RuntimeException('Hạng phòng không tồn tại hoặc đang trong thùng rác.');
                    // A reduction must not invalidate any existing occupied night.
                    $bookings=bch_overlap_rows('1000-01-01 00:00:00','9999-12-31 23:59:59',$id);
                    if (bch_peak_occupancy($bookings)>$data['quantity']) throw new RuntimeException('Số phòng mới thấp hơn số phòng đang được giữ/đã xác nhận.');
                    $ok=$wpdb->update($table,$data,['id'=>$id]);
                } else $ok=$wpdb->insert($table,$data);
                if ($ok===false) throw new RuntimeException('Không lưu được hạng phòng.');
            });
            echo '<div class="notice notice-success"><p>Đã lưu hạng phòng.</p></div>';
            }
        } catch (Throwable $e) { bch_admin_error($e); }
    }
    echo '<div class="wrap"><h1>Hạng phòng</h1><p>Chọn 3–5 ảnh cho mỗi hạng phòng từ thư viện hoặc tải ảnh mới. Ảnh đầu tiên là ảnh đại diện. Không có ảnh sẽ dùng hình minh họa.</p><p><a href="?page=bch-rooms">+ Thêm hạng phòng</a></p>';
    $all_rooms=bch_rooms(true);
    $deleted=array_map('intval',get_option('bch_deleted_rooms',[]));
    $trash=($_GET['view']??'')==='trash';
    $rooms=array_values(array_filter($all_rooms,fn($room)=>in_array((int)$room->id,$deleted,true)===$trash));
    echo '<p><a href="'.esc_url(admin_url('admin.php?page=bch-rooms')).'">Danh sách hạng phòng</a> | <a href="'.esc_url(admin_url('admin.php?page=bch-rooms&view=trash')).'">Thùng rác ('.count($deleted).')</a></p>';
    $retired=array_map('intval',get_option('bch_retired_rooms',[]));
    $current=array_filter($all_rooms,fn($room)=>!in_array((int)$room->id,array_merge($retired,$deleted),true));
    echo '<p><strong>'.count($current).' hạng phòng · '.array_sum(array_map(fn($room)=>(int)$room->quantity,$current)).' phòng tổng cộng</strong> (không tính hạng đã lưu trữ).</p>';
    echo '<div style="overflow-x:auto"><table class="widefat striped"><caption class="screen-reader-text">Danh sách hạng phòng Bảo Châu</caption><thead><tr><th scope="col">Hạng phòng</th><th scope="col">Loại giường</th><th scope="col">Số phòng</th><th scope="col">Sức chứa / phòng</th><th scope="col">Giá / đêm</th><th scope="col">Trạng thái</th><th scope="col">Thao tác</th></tr></thead><tbody>';
    foreach ($rooms as $room) {
        $url=add_query_arg(['page'=>'bch-rooms','edit'=>$room->id],admin_url('admin.php')).'#bch-room-editor';
        $archived=in_array((int)$room->id,$retired,true);
        $status=$trash?'Trong thùng rác':($archived?'Đã lưu trữ':((int)$room->quantity>0?'Đang kinh doanh':'Tạm ngừng bán'));
        echo '<tr><th scope="row"><a href="'.esc_url($url).'">'.esc_html($room->name).'</a></th><td>'.esc_html($room->bed).'</td><td>'.esc_html($room->quantity).'</td><td>'.esc_html($room->capacity).' khách</td><td style="white-space:nowrap">'.esc_html(bch_money($room->price)).'</td><td>'.esc_html($status).'</td><td><a class="button button-small" href="'.esc_url($url).'" aria-label="'.esc_attr('Sửa '.$room->name).'">Sửa</a>';
        echo '<form method="post" action="'.esc_url(admin_url('admin.php?page=bch-rooms'.($trash?'&view=trash':''))).'" style="display:inline-block;margin-left:6px">';
        wp_nonce_field('bch_rooms');
        echo '<input type="hidden" name="id" value="'.esc_attr($room->id).'"><button class="button button-small" type="submit" name="room_action" value="'.($trash?'restore':'trash').'" aria-label="'.esc_attr(($trash?'Khôi phục ':'Xóa ').$room->name).'">'.($trash?'Khôi phục':'Xóa').'</button></form></td></tr>';
    }
    if (!$rooms) echo '<tr><td colspan="7">Chưa có hạng phòng. Thêm hạng phòng bằng biểu mẫu bên dưới.</td></tr>';
    echo '</tbody></table></div>';
    if ($trash) { echo '</div>'; return; }
    $r=bch_room(absint($_GET['edit']??0),true);
    if ($r && in_array((int)$r->id,$deleted,true)) { echo '<p>Hạng phòng ở trong thùng rác. Hãy khôi phục trước khi sửa.</p></div>'; return; }
    if ($r && in_array((int)$r->id,array_map('intval',get_option('bch_retired_rooms',[])),true)) echo '<p class="notice notice-info">Hạng demo đã lưu trữ, không hiển thị và không nhận đơn mới. Dữ liệu được giữ để đối chiếu đơn cũ.</p>';
    echo '<hr><h2 id="bch-room-editor">'.($r?'Sửa hạng phòng':'Thêm hạng phòng').'</h2><form method="post">'; wp_nonce_field('bch_rooms');
    echo '<input type="hidden" name="id" value="'.esc_attr($r->id??0).'">';
    foreach (['name'=>'Tên hạng','quantity'=>'Số phòng thực tế (0 để ngừng bán)','capacity'=>'Sức chứa','area'=>'Diện tích m²','bed'=>'Loại giường','price'=>'Giá mặc định / đêm (VND)','hourly_price'=>'Giá nghỉ giờ / 2 giờ đầu (VND, 0 để ngừng bán giờ)','amenities'=>'Tiện nghi (cách nhau bằng dấu phẩy)'] as $k=>$label) bch_admin_input($k,$label,$r->$k??'',in_array($k,['quantity','capacity','area','price','hourly_price'])?'number':'text');
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
            $trash_action=in_array($action,['trash','restore'],true);
            $r=$trash_action?bch_booking_trash(absint($_POST['id']??0),$action):bch_transition(absint($_POST['id']??0),$action,!empty($_POST['balance_verified']));
            if (!$r) throw new RuntimeException('Trạng thái không cho phép thao tác. Đơn hết hạn không được khôi phục; hãy kiểm tra tồn trước khi tạo đơn mới.');
            if (!$trash_action) bch_mail($r); echo '<div class="notice notice-success"><p>Đã cập nhật đơn.</p></div>';
        } catch (Throwable $e) { bch_admin_error($e); }
    }
    bch_expire(); global $wpdb;
    $page=max(1,absint($_GET['paged']??1)); $filter=sanitize_key($_GET['status']??'');
    $trash=($_GET['view']??'')==='trash';
    $where=' WHERE deleted_at IS '.($trash?'NOT NULL':'NULL');
    if (in_array($filter,['pending','confirmed','checked_in','expired','cancelled'],true)) $where.=$wpdb->prepare(' AND status=%s',$filter);
    $rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.bch_table('bookings').$where.' ORDER BY id DESC LIMIT 30 OFFSET %d',($page-1)*30));
    echo '<div class="wrap"><h1>Đơn đặt phòng</h1><p><a class="button button-primary" href="'.esc_url(admin_url('admin.php?page=bch-counter')).'">+ Tạo đơn tại quầy</a></p><p>Thời gian Việt Nam. Kiểm tra chuyển khoản thực tế trước khi xác nhận. Email = kết quả gửi tới hệ thống mail, không bảo đảm thư đã đến hộp thư.</p><p><a href="?page=bch-bookings">Tất cả</a> ';
    foreach (['pending','confirmed','checked_in','expired','cancelled'] as $s) echo ' | <a href="?page=bch-bookings&status='.esc_attr($s).'">'.esc_html(bch_status($s)).'</a>';
    echo ' | <a href="?page=bch-bookings&view=trash">Thùng rác</a>';
    echo '</p><table class="widefat striped"><thead><tr><th>Đơn / khách</th><th>Lưu trú</th><th>Tiền / trạng thái</th><th>Thao tác</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr><td><strong>'.esc_html($r->code).'</strong><br>'.esc_html(($r->source??'website')==='counter'?'Tại quầy · User #'.$r->created_by:'Website').'<br>Tạo lúc '.esc_html(wp_date('d/m/Y H:i',strtotime($r->created_at.' UTC'),new DateTimeZone('Asia/Ho_Chi_Minh'))).'<br>'.esc_html($r->name).'<br>'.esc_html($r->phone).'<br>'.esc_html($r->email).'</td><td>'.esc_html($r->room_name).'<br>'.esc_html(bch_upgrade_description($r)).'<br>'.esc_html(bch_stay_label($r)).'<br>'.esc_html($r->guests).' khách</td><td>'.esc_html(bch_status($r->status)).'<br>Tổng '.esc_html(bch_money($r->total)).'<br>Cọc '.esc_html(bch_money($r->deposit)).'<br>Hạn: '.esc_html(wp_date('d/m/Y H:i',strtotime($r->expires_at.' UTC'),new DateTimeZone('Asia/Ho_Chi_Minh'))).'<br>Email: '.($r->mail_sent?'đã gửi':'chưa gửi / lỗi');
        if ($r->confirmed_at) echo '<br>Xác nhận: '.esc_html(wp_date('d/m/Y H:i',strtotime($r->confirmed_at.' UTC'),new DateTimeZone('Asia/Ho_Chi_Minh'))).' · User #'.esc_html($r->confirmed_by);
        if ($r->status==='checked_in') echo '<br><strong>Đã thu đủ tiền phòng</strong><br>Thu khi nhận phòng: '.esc_html(bch_money($r->balance_paid)).'<br>Nhận phòng: '.esc_html(wp_date('d/m/Y H:i',strtotime($r->checked_in_at.' UTC'),new DateTimeZone('Asia/Ho_Chi_Minh'))).' · User #'.esc_html($r->checked_in_by);
        echo '<br>Còn lại sau cọc: '.esc_html(bch_money(bch_balance_due($r)));
        echo '</td><td>';
        if (!$trash && in_array($r->status,['pending','confirmed'],true)) {
            echo '<form method="post">'; wp_nonce_field('bch_booking_action'); echo '<input type="hidden" name="id" value="'.esc_attr($r->id).'">';
            if ($r->status==='pending') echo '<p><label><input type="checkbox" name="verified" value="1"> Đã đối chiếu đủ '.esc_html(bch_money($r->deposit)).'</label></p><button class="button button-primary" name="action" value="confirm">Xác nhận cọc</button> ';
            if ($r->status==='confirmed') echo '<p><label><input type="checkbox" name="balance_verified" value="1"> Đã thu đủ tiền còn lại: <strong>'.esc_html(bch_money(bch_balance_due($r))).'</strong></label></p><button class="button button-primary" name="action" value="checkin">Xác nhận đã nhận phòng</button> ';
            echo '<button class="button" name="action" value="cancel">Hủy đơn</button></form>';
        }
        if ($trash || in_array($r->status,['cancelled','expired'],true)) {
            echo '<form method="post">'; wp_nonce_field('bch_booking_action');
            echo '<input type="hidden" name="id" value="'.esc_attr($r->id).'"><button class="button" name="action" value="'.($trash?'restore':'trash').'">'.($trash?'Khôi phục':'Xóa').'</button></form>';
        } elseif (!$trash) echo '<p class="description">Chỉ xóa đơn đã hủy hoặc hết hạn.</p>';
        echo '</td></tr>';
    }
    echo '</tbody></table><p>';
    if ($page>1) echo '<a href="'.esc_url(add_query_arg('paged',$page-1)).'">← Trang trước</a> ';
    if (count($rows)===30) echo '<a href="'.esc_url(add_query_arg('paged',$page+1)).'">Trang sau →</a>';
    echo '</p></div>';
}
