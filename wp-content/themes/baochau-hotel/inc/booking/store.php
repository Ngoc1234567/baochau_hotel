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
 hourly_price bigint(20) NOT NULL DEFAULT 0,
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
 requested_room_id bigint(20) unsigned NOT NULL DEFAULT 0,
 requested_room_name varchar(190) NOT NULL DEFAULT '',
 upgrade_accepted tinyint(1) NOT NULL DEFAULT 0,
 checkin date NOT NULL,
 checkout date NOT NULL,
 stay_type varchar(20) NOT NULL DEFAULT 'overnight',
 starts_at datetime DEFAULT NULL,
 ends_at datetime DEFAULT NULL,
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
 checked_in_at datetime DEFAULT NULL,
 checked_in_by bigint(20) unsigned DEFAULT NULL,
 balance_paid bigint(20) NOT NULL DEFAULT 0,
 source varchar(20) NOT NULL DEFAULT 'website',
 created_by bigint(20) unsigned DEFAULT NULL,
 deleted_at datetime DEFAULT NULL,
 deleted_by bigint(20) unsigned DEFAULT NULL,
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
        foreach (bch_tariffs() as $d) {
            $d['hourly_price']=$d['hourly'];
            unset($d['hourly']);
            $wpdb->insert($r,array_merge($d,['quantity'=>0,'area'=>0,'rates'=>'{}','closed'=>'[]','images'=>'[]','description'=>'Ảnh, diện tích và tiện nghi đang được cập nhật. Vui lòng liên hệ lễ tân để biết thêm thông tin.','amenities'=>'']));
        }
        update_option('bch_seeded',1);
    }
    if (!get_option('bch_booking_page')) update_option('bch_booking_page',wp_insert_post(['post_title'=>'Đặt phòng','post_name'=>'dat-phong','post_content'=>'[baochau_booking]','post_status'=>'publish','post_type'=>'page']));
    if (!get_option('bch_policy_page')) update_option('bch_policy_page',wp_insert_post(['post_title'=>'Chính sách đặt cọc','post_name'=>'chinh-sach-dat-coc','post_content'=>'[baochau_policy]','post_status'=>'publish','post_type'=>'page']));
    get_role('administrator')->add_cap('bch_manage_bookings');
    add_role('bch_reception','Lễ tân',['read'=>true,'bch_manage_bookings'=>true]);
    if ($wpdb->last_error) throw new RuntimeException('Không cập nhật được cấu trúc đặt phòng.');
    if (!get_option('bch_hourly_prices_v1')) {
        foreach (bch_tariffs() as $tariff) {
            if ($wpdb->query($wpdb->prepare("UPDATE $r SET hourly_price=%d WHERE name=%s AND hourly_price=0",$tariff['hourly'],$tariff['name']))===false) throw new RuntimeException('Không cập nhật được giá nghỉ giờ.');
        }
        update_option('bch_hourly_prices_v1',1);
    }
    update_option('bch_schema','6');
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
function bch_rooms($include_retired=false) { global $wpdb; $rows=$wpdb->get_results('SELECT * FROM '.bch_table('rooms').' ORDER BY price,id'); $retired=array_map('intval',array_merge(get_option('bch_retired_rooms',[]),get_option('bch_deleted_rooms',[]))); return $include_retired ? $rows : array_values(array_filter($rows,fn($r)=>!in_array((int)$r->id,$retired,true))); }
function bch_room($id,$include_retired=false) { if (!$include_retired && in_array((int)$id,array_map('intval',array_merge(get_option('bch_retired_rooms',[]),get_option('bch_deleted_rooms',[]))),true)) return null; global $wpdb; return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.bch_table('rooms').' WHERE id=%d',$id)); }
function bch_available($room,$days) {
    if (!$days) return 0;
    $start=reset($days).' 13:00:00';
    $end=(new DateTimeImmutable(end($days)))->modify('+1 day')->format('Y-m-d').' 12:00:00';
    return bch_available_interval($room,$start,$end);
}
function bch_customer_phone($phone) {
    $phone=preg_replace('/\D/','',$phone);
    if (str_starts_with($phone,'0084')) $phone='0'.substr($phone,4);
    elseif (str_starts_with($phone,'84')) $phone='0'.substr($phone,2);
    return $phone;
}
function bch_duplicate_booking($email,$phone,$in,$out) {
    $start=strlen($in)===10?$in.' 13:00:00':$in;
    $end=strlen($out)===10?$out.' 12:00:00':$out;
    $rows=bch_overlap_rows($start,$end);
    foreach ($rows as $row) if ((trim($email)!=='' && strcasecmp(trim($row->email),trim($email))===0) || bch_customer_phone($row->phone)===bch_customer_phone($phone)) return $row;
    return null;
}
function bch_owned_booking($row) {
    $token=$_COOKIE['bch_booking_access']??'';
    return is_string($token) && preg_match('/^[a-f0-9]{64}$/',$token) && hash_equals($row->token_hash,hash('sha256',$token));
}
function bch_hold($data,$at_counter=false) {
    if ($at_counter && !current_user_can('bch_manage_bookings')) throw new RuntimeException('Bạn không có quyền tạo đơn tại quầy.');
    if (!$at_counter && ($data['stay_type']??'overnight')!=='overnight') throw new InvalidArgumentException('Nghỉ giờ chỉ được tạo tại quầy.');
    $stay=bch_stay($data);
    $data['in']=$stay['in']; $data['out']=$stay['out'];
    $initial_status=$at_counter?($data['initial_status']??'checked_in'):'pending';
    if (!in_array($initial_status,['pending','confirmed','checked_in'],true)) throw new InvalidArgumentException('Trạng thái tạo đơn không hợp lệ.');
    if ($at_counter && $initial_status==='checked_in' && $data['in']!==wp_date('Y-m-d',null,new DateTimeZone('Asia/Ho_Chi_Minh'))) throw new InvalidArgumentException('Đơn nhận phòng ngay phải có ngày nhận là hôm nay.');
    if ($at_counter && $initial_status==='checked_in' && $stay['type']==='hourly') {
        $local_now=wp_date('Y-m-d H:i:s',null,new DateTimeZone('Asia/Ho_Chi_Minh'));
        if ($stay['start']>$local_now || $stay['end']<=$local_now) throw new InvalidArgumentException('Nhận phòng ngay cần khung giờ đang diễn ra. Chọn đặt trước nếu khách chưa đến.');
    }
    if (!$at_counter && !bch_ready()) throw new RuntimeException('Chưa mở nhận giữ chỗ. Vui lòng liên hệ khách sạn.');
    $days=$stay['days'];
    if (!preg_match('/^[a-f0-9]{64}$/',$data['key']) || !preg_match('/^[a-f0-9]{64}$/',$data['token'])) throw new InvalidArgumentException('Phiên giữ chỗ không hợp lệ.');
    if (!$data['name'] || strlen($data['name'])>190 || ((!$at_counter || $data['email']!=='') && !is_email($data['email'])) || strlen($data['email'])>190 || !preg_match('/^[+0-9 ()-]{8,25}$/',$data['phone'])) throw new InvalidArgumentException('Vui lòng nhập đúng họ tên, điện thoại và email.');
    $data['room']=(int)$data['room'];
    $allocated_id=(int)($data['allocated_room']??$data['room']);
    $accepted=!empty($data['upgrade_consent']);
    $fingerprint=hash('sha256',wp_json_encode([$data['room'],$data['in'],$data['out'],$data['guests'],$data['name'],$data['phone'],$data['email'],$allocated_id,$accepted]));
    $fingerprint=hash('sha256',$fingerprint.'|'.$stay['type'].'|'.$stay['start'].'|'.$stay['end']);
    if ($at_counter) $fingerprint=hash('sha256',$fingerprint.'|counter|'.get_current_user_id().'|'.$initial_status.'|'.($data['paid']??'').'|'.(!empty($data['payment_verified'])?'1':'0'));
    return bch_atomic(function () use ($data,$days,$fingerprint,$allocated_id,$accepted,$at_counter,$initial_status,$stay) {
        global $wpdb; $b=bch_table('bookings');
        bch_expire_rows();
        $existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM $b WHERE request_key=%s",$data['key']));
        if ($existing) {
            if (!hash_equals($existing->fingerprint,$fingerprint) || !hash_equals($existing->token_hash,hash('sha256',$data['token']))) throw new RuntimeException('Yêu cầu đã dùng cho thông tin khác. Hãy tìm phòng lại.');
            return $existing;
        }
        $duplicate=bch_duplicate_booking($data['email'],$data['phone'],$stay['start'],$stay['end']);
        if ($duplicate) {
            if (!$at_counter && bch_owned_booking($duplicate)) return $duplicate;
            throw new RuntimeException('Thông tin liên hệ này đã có đơn cho kỳ nghỉ trùng ngày. Vui lòng mở đơn trên trình duyệt đã đặt hoặc liên hệ lễ tân để kiểm tra; không cần tạo đơn hay chuyển cọc thêm.');
        }
        $room=bch_room($data['room']);
        if (!$room) throw new InvalidArgumentException('Không tìm thấy hạng phòng.');
        $quote=bch_stay_price($room,$stay,$data['guests']);
        if ($quote['total'] !== (int)$data['quoted']) throw new RuntimeException('Giá đã thay đổi. Vui lòng tìm phòng lại để xem giá mới.');
        $allocated=bch_stay_allocation($room,$stay,$data['guests']);
        $upgraded=(int)$allocated->id!==(int)$room->id;
        if ((int)$allocated->id!==$allocated_id) throw new RuntimeException('Tình trạng phòng đã thay đổi. Vui lòng kiểm tra phòng lại để xem và đồng ý phương án bố trí mới.');
        if ($upgraded && !$accepted) throw new RuntimeException('Vui lòng đồng ý đổi sang phòng lớn hơn, giữ nguyên giá trước khi giữ chỗ.');
        $paid=$at_counter?($data['paid']??null):0;
        $required_paid=$initial_status==='checked_in'?$quote['total']:($initial_status==='confirmed'?$quote['deposit']:0);
        if ($at_counter && (!is_numeric($paid) || (float)$paid!==(float)$required_paid)) throw new InvalidArgumentException('Số tiền đã thu phải bằng '.bch_money($required_paid).' cho trạng thái đã chọn.');
        if ($at_counter && $required_paid>0 && empty($data['payment_verified'])) throw new RuntimeException('Vui lòng xác nhận đã thực thu số tiền trên.');
        $s=bch_settings(); $code='BC'.strtoupper(bin2hex(random_bytes(6)));
        $now=strtotime($wpdb->get_var('SELECT UTC_TIMESTAMP()').' UTC');
        $ok=$wpdb->insert($b,['code'=>$code,'token_hash'=>hash('sha256',$data['token']),'request_key'=>$data['key'],'fingerprint'=>$fingerprint,'room_id'=>$allocated->id,'room_name'=>$allocated->name,'requested_room_id'=>$room->id,'requested_room_name'=>$room->name,'upgrade_accepted'=>$upgraded?1:0,'checkin'=>$data['in'],'checkout'=>$data['out'],'stay_type'=>$stay['type'],'starts_at'=>$stay['start'],'ends_at'=>$stay['end'],'guests'=>$data['guests'],'name'=>$data['name'],'phone'=>$data['phone'],'email'=>$data['email'],'total'=>$quote['total'],'deposit'=>($at_counter && $initial_status==='checked_in'?0:$quote['deposit']),'nightly'=>wp_json_encode($quote['nightly']),'bank'=>wp_json_encode(array_intersect_key($s,array_flip(['bank','holder','account','qr_url']))),'source'=>$at_counter?'counter':'website','created_by'=>$at_counter?get_current_user_id():null,'status'=>$initial_status,'confirmed_at'=>$initial_status!=='pending'?gmdate('Y-m-d H:i:s',$now):null,'confirmed_by'=>$initial_status!=='pending'?get_current_user_id():null,'checked_in_at'=>$initial_status==='checked_in'?gmdate('Y-m-d H:i:s',$now):null,'checked_in_by'=>$initial_status==='checked_in'?get_current_user_id():null,'balance_paid'=>$initial_status==='checked_in'?$quote['total']:0,'expires_at'=>gmdate('Y-m-d H:i:s',$now+max(1,min(48,(int)$s['hours']))*3600),'created_at'=>gmdate('Y-m-d H:i:s',$now)]);
        if (!$ok) throw new RuntimeException('Không lưu được đơn. Vui lòng thử lại.');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $b WHERE id=%d",$wpdb->insert_id));
    });
}
function bch_transition($id,$action,$balance_verified=false) {
    return bch_atomic(function () use ($id,$action,$balance_verified) {
        global $wpdb; bch_expire_rows(); $b=bch_table('bookings');
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $b WHERE id=%d",$id));
        if (!$row || !empty($row->deleted_at)) throw new RuntimeException('Không tìm thấy đơn hoặc đơn đang ở thùng rác.');
        if ($action==='confirm' && $row->status==='pending') $changes=['status'=>'confirmed','confirmed_at'=>gmdate('Y-m-d H:i:s'),'confirmed_by'=>get_current_user_id()];
        elseif ($action==='checkin' && $row->status==='confirmed') {
            if (!$balance_verified) throw new RuntimeException('Cần xác nhận đã thu đủ số tiền phòng còn lại.');
            if (!current_user_can('bch_manage_bookings')) throw new RuntimeException('Bạn không có quyền xác nhận nhận phòng.');
            $changes=['status'=>'checked_in','checked_in_at'=>gmdate('Y-m-d H:i:s'),'checked_in_by'=>get_current_user_id(),'balance_paid'=>max(0,(int)$row->total-(int)$row->deposit)];
        }
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
function bch_booking_trash($id,$action) {
    if (!current_user_can('bch_manage_bookings')) throw new RuntimeException('Bạn không có quyền quản lý đơn.');
    if (!in_array($action,['trash','restore'],true)) throw new InvalidArgumentException('Thao tác không hợp lệ.');
    return bch_atomic(function () use ($id,$action) {
        global $wpdb; bch_expire_rows(); $table=bch_table('bookings');
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d",$id));
        if (!$row) throw new RuntimeException('Không tìm thấy đơn.');
        if (!in_array($row->status,['cancelled','expired'],true)) throw new RuntimeException('Chỉ xóa đơn đã hủy hoặc hết hạn. Không xóa đơn đang giữ chỗ hoặc đã nhận phòng.');
        if (($action==='trash' && $row->deleted_at) || ($action==='restore' && !$row->deleted_at)) return $row;
        $changes=$action==='trash'?['deleted_at'=>gmdate('Y-m-d H:i:s'),'deleted_by'=>get_current_user_id()]:['deleted_at'=>null,'deleted_by'=>null];
        if ($wpdb->update($table,$changes,['id'=>$id])===false) throw new RuntimeException('Không cập nhật được thùng rác.');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d",$id));
    });
}
function bch_balance_due($r) { return max(0,(int)$r->total-(int)$r->deposit-(int)($r->balance_paid??0)); }
function bch_receipt_text($r) {
    $bank=json_decode($r->bank,true); $tz=new DateTimeZone('Asia/Ho_Chi_Minh');
    $upgrade=bch_upgrade_description($r);
    $stay_text=bch_stay_label($r);
    if ($r->status==='checked_in') return "$upgrade\nMã: $r->code\nTrạng thái: Đã nhận phòng\nPhòng: $r->room_name\n$stay_text\nTổng tiền phòng: ".bch_money($r->total)."\nĐã thu đủ tiền phòng.\nThu khi nhận phòng: ".bch_money($r->balance_paid)."\nCòn lại: ".bch_money(bch_balance_due($r))."\nNhận phòng lúc: ".wp_date('d/m/Y H:i',strtotime($r->checked_in_at.' UTC'),$tz)." (giờ Việt Nam)\n".bch_settings()['contact'];
    return "$upgrade\nMã: $r->code\nTrạng thái: ".bch_status($r->status)."\nPhòng: $r->room_name\n$stay_text\nTổng: ".bch_money($r->total)."\nCọc 30%: ".bch_money($r->deposit)."\nCòn lại: ".bch_money(bch_balance_due($r))."\nNgân hàng: {$bank['bank']}\nChủ tài khoản: {$bank['holder']}\nSố tài khoản: {$bank['account']}\nNội dung chuyển khoản: $r->code\nHạn giữ chỗ: ".wp_date('d/m/Y H:i',strtotime($r->expires_at.' UTC'),$tz)." (giờ Việt Nam)\n".BCH_POLICY."\n".bch_settings()['contact'];
}
function bch_mail($r) {
    if (!bch_settings()['mail'] || !is_email($r->email)) return;
    global $wpdb;
    $sent=wp_mail($r->email,'Đặt phòng '.$r->code.' — '.bch_status($r->status),bch_receipt_text($r));
    $wpdb->update(bch_table('bookings'),['mail_sent'=>$sent?1:0],['id'=>$r->id]);
}
