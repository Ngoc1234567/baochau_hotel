<?php
defined('ABSPATH') || exit;
function bch_table($name) { global $wpdb; return $wpdb->prefix . 'bch_' . $name; }
function bch_install() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset=$wpdb->get_charset_collate(); $r=bch_table('rooms'); $b=bch_table('bookings');
    dbDelta("CREATE TABLE $r (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 name varchar(190) NOT NULL,
 quantity int(11) NOT NULL DEFAULT 1,
 capacity int(11) NOT NULL DEFAULT 2,
 area int(11) NOT NULL DEFAULT 25,
 bed varchar(190) NOT NULL DEFAULT '',
 price bigint(20) NOT NULL DEFAULT 0,
 rates longtext NOT NULL,
 closed longtext NOT NULL,
 images longtext NOT NULL,
 description longtext NOT NULL,
 amenities text NOT NULL,
 PRIMARY KEY  (id)
) ENGINE=InnoDB $charset;");
    dbDelta("CREATE TABLE $b (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 code varchar(32) NOT NULL,
 token_hash varchar(64) NOT NULL,
 request_key varchar(64) NOT NULL,
 fingerprint varchar(64) NOT NULL,
 room_id bigint(20) unsigned NOT NULL,
 room_name varchar(190) NOT NULL,
 checkin date NOT NULL,
 checkout date NOT NULL,
 guests int(11) NOT NULL,
 name varchar(190) NOT NULL,
 phone varchar(40) NOT NULL,
 email varchar(190) NOT NULL,
 total bigint(20) NOT NULL,
 deposit bigint(20) NOT NULL,
 bank text NOT NULL,
 nightly longtext NOT NULL,
 status varchar(20) NOT NULL DEFAULT 'pending',
 expires_at datetime NOT NULL,
 created_at datetime NOT NULL,
 confirmed_at datetime DEFAULT NULL,
 confirmed_by bigint(20) unsigned DEFAULT NULL,
 mail_sent tinyint(1) NOT NULL DEFAULT 0,
 PRIMARY KEY  (id),
 UNIQUE KEY request_key (request_key),
 UNIQUE KEY code (code),
 KEY availability (room_id,status,checkin,checkout)
) ENGINE=InnoDB $charset;");
    foreach ([$r,$b] as $table) {
        $status=$wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name=%s',$table));
        if (!$status || strtolower($status->Engine)!=='innodb') wp_die('Bảng đặt phòng cần InnoDB. Hãy kiểm tra database trước khi kích hoạt.');
    }
    if (!get_option('bch_seeded')) {
        foreach ([['Superior Garden',650000,2,25,'1 giường đôi'],['Deluxe Balcony',950000,2,32,'1 giường King'],['Family Suite',1450000,4,45,'2 giường đôi']] as $d) {
            $wpdb->insert($r,['name'=>$d[0],'price'=>$d[1],'capacity'=>$d[2],'area'=>$d[3],'bed'=>$d[4],'quantity'=>3,'rates'=>'{}','closed'=>'[]','images'=>'[]','description'=>'Không gian nghỉ ngơi thoáng đãng với sắc gỗ ấm áp. Nội dung và giá minh họa, cần thay bằng thông tin khách sạn thực tế.','amenities'=>'Wi-Fi, Điều hòa, Phòng tắm riêng, Bàn làm việc']);
        }
        update_option('bch_seeded',1);
    }
    if (!get_option('bch_booking_page')) update_option('bch_booking_page',wp_insert_post(['post_title'=>'Đặt phòng','post_name'=>'dat-phong','post_content'=>'[baochau_booking]','post_status'=>'publish','post_type'=>'page']));
    if (!get_option('bch_policy_page')) update_option('bch_policy_page',wp_insert_post(['post_title'=>'Chính sách đặt cọc','post_name'=>'chinh-sach-dat-coc','post_content'=>'[baochau_policy]','post_status'=>'publish','post_type'=>'page']));
    get_role('administrator')->add_cap('bch_manage_bookings');
    add_role('bch_reception','Lễ tân',['read'=>true,'bch_manage_bookings'=>true]);
    update_option('bch_schema','1');
}
/** All inventory writes share this DB advisory lock and transaction. */
function bch_atomic($fn) {
    global $wpdb; $key='bch_' . md5(DB_NAME . $wpdb->prefix);
    if ((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)',$key)) !== 1) throw new RuntimeException('Hệ thống đang bận, vui lòng thử lại.');
    try {
        if ($wpdb->query('START TRANSACTION')===false) throw new RuntimeException('Không thể mở giao dịch.');
        $result=$fn();
        if ($wpdb->query('COMMIT')===false) throw new RuntimeException('Không thể lưu giao dịch.');
        return $result;
    } catch (Throwable $e) { $wpdb->query('ROLLBACK'); throw $e; }
    finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$key)); }
}
function bch_expire_rows() { global $wpdb; if ($wpdb->query('UPDATE '.bch_table('bookings')." SET status='expired' WHERE status='pending' AND expires_at <= UTC_TIMESTAMP()")===false) throw new RuntimeException('Không cập nhật được hạn giữ chỗ.'); }
function bch_expire() { try { bch_atomic('bch_expire_rows'); } catch (Throwable $e) { error_log('BCH: expiration failed'); } }
function bch_rooms() { global $wpdb; return $wpdb->get_results('SELECT * FROM '.bch_table('rooms').' ORDER BY price,id'); }
function bch_room($id) { global $wpdb; return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.bch_table('rooms').' WHERE id=%d',$id)); }
function bch_available($room,$days) {
    global $wpdb; $min=(int)$room->quantity;
    foreach ($days as $day) {
        $used=$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.bch_table('bookings')." WHERE room_id=%d AND checkin<=%s AND checkout>%s AND (status='confirmed' OR (status='pending' AND expires_at>UTC_TIMESTAMP()))",$room->id,$day,$day));
        if ($wpdb->last_error) throw new RuntimeException('Không đọc được tồn phòng.');
        $min=min($min,$room->quantity-(int)$used);
    }
    return max(0,$min);
}
function bch_hold($data) {
    if (!bch_ready()) throw new RuntimeException('Chưa mở nhận giữ chỗ. Vui lòng liên hệ khách sạn.');
    $days=bch_dates($data['in'],$data['out']);
    if (!preg_match('/^[a-f0-9]{64}$/',$data['key']) || !preg_match('/^[a-f0-9]{64}$/',$data['token'])) throw new InvalidArgumentException('Phiên giữ chỗ không hợp lệ.');
    if (!$data['name'] || strlen($data['name'])>190 || !is_email($data['email']) || strlen($data['email'])>190 || !preg_match('/^[+0-9 ()-]{8,25}$/',$data['phone'])) throw new InvalidArgumentException('Vui lòng nhập đúng họ tên, điện thoại và email.');
    $fingerprint=hash('sha256',wp_json_encode([$data['room'],$data['in'],$data['out'],$data['guests'],$data['name'],$data['phone'],$data['email']]));
    return bch_atomic(function () use ($data,$days,$fingerprint) {
        global $wpdb; $b=bch_table('bookings');
        bch_expire_rows();
        $existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM $b WHERE request_key=%s",$data['key']));
        if ($existing) {
            if (!hash_equals($existing->fingerprint,$fingerprint) || !hash_equals($existing->token_hash,hash('sha256',$data['token']))) throw new RuntimeException('Yêu cầu đã dùng cho thông tin khác. Hãy tìm phòng lại.');
            return $existing;
        }
        $room=bch_room($data['room']);
        if (!$room) throw new InvalidArgumentException('Không tìm thấy hạng phòng.');
        $quote=bch_price($room,$days,$data['guests']);
        if ($quote['total'] !== (int)$data['quoted']) throw new RuntimeException('Giá đã thay đổi. Vui lòng tìm phòng lại để xem giá mới.');
        if (bch_available($room,$days)<1) throw new RuntimeException('Phòng vừa hết chỗ. Vui lòng chọn hạng khác.');
        $s=bch_settings(); $code='BC'.strtoupper(bin2hex(random_bytes(6)));
        $now=strtotime($wpdb->get_var('SELECT UTC_TIMESTAMP()').' UTC');
        $ok=$wpdb->insert($b,['code'=>$code,'token_hash'=>hash('sha256',$data['token']),'request_key'=>$data['key'],'fingerprint'=>$fingerprint,'room_id'=>$room->id,'room_name'=>$room->name,'checkin'=>$data['in'],'checkout'=>$data['out'],'guests'=>$data['guests'],'name'=>$data['name'],'phone'=>$data['phone'],'email'=>$data['email'],'total'=>$quote['total'],'deposit'=>$quote['deposit'],'nightly'=>wp_json_encode($quote['nightly']),'bank'=>wp_json_encode(array_intersect_key($s,array_flip(['bank','holder','account','qr_url']))),'status'=>'pending','expires_at'=>gmdate('Y-m-d H:i:s',$now+max(1,min(48,(int)$s['hours']))*3600),'created_at'=>gmdate('Y-m-d H:i:s',$now)]);
        if (!$ok) throw new RuntimeException('Không lưu được đơn. Vui lòng thử lại.');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $b WHERE id=%d",$wpdb->insert_id));
    });
}
function bch_transition($id,$action) {
    return bch_atomic(function () use ($id,$action) {
        global $wpdb; bch_expire_rows(); $b=bch_table('bookings');
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $b WHERE id=%d",$id));
        if (!$row) throw new RuntimeException('Không tìm thấy đơn.');
        if ($action==='confirm' && $row->status==='pending') $changes=['status'=>'confirmed','confirmed_at'=>gmdate('Y-m-d H:i:s'),'confirmed_by'=>get_current_user_id()];
        elseif ($action==='cancel' && in_array($row->status,['pending','confirmed'],true)) $changes=['status'=>'cancelled'];
        else return false;
        if ($action==='confirm') {
            $updated=$wpdb->query($wpdb->prepare("UPDATE $b SET status='confirmed', confirmed_at=UTC_TIMESTAMP(), confirmed_by=%d WHERE id=%d AND status='pending' AND expires_at>UTC_TIMESTAMP()",get_current_user_id(),$id));
            if ($updated===0) { bch_expire_rows(); return false; }
        } else $updated=$wpdb->update($b,$changes,['id'=>$id]);
        if ($updated===false) throw new RuntimeException('Không cập nhật được đơn.');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $b WHERE id=%d",$id));
    });
}
function bch_receipt_text($r) {
    $bank=json_decode($r->bank,true); $tz=new DateTimeZone('Asia/Ho_Chi_Minh');
    return "Mã: $r->code\nTrạng thái: ".bch_status($r->status)."\nPhòng: $r->room_name\nNgày ở: $r->checkin → $r->checkout\nTổng: ".bch_money($r->total)."\nCọc 30%: ".bch_money($r->deposit)."\nCòn lại: ".bch_money($r->total-$r->deposit)."\nNgân hàng: {$bank['bank']}\nChủ tài khoản: {$bank['holder']}\nSố tài khoản: {$bank['account']}\nNội dung chuyển khoản: $r->code\nHạn giữ chỗ: ".wp_date('d/m/Y H:i',strtotime($r->expires_at.' UTC'),$tz)." (giờ Việt Nam)\n".BCH_POLICY."\n".bch_settings()['contact'];
}
function bch_mail($r) {
    if (!bch_settings()['mail']) return;
    global $wpdb;
    $sent=wp_mail($r->email,'Đặt phòng '.$r->code.' — '.bch_status($r->status),bch_receipt_text($r));
    $wpdb->update(bch_table('bookings'),['mail_sent'=>$sent?1:0],['id'=>$r->id]);
}
