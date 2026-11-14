<?php
defined('ABSPATH') || exit;

function bch_admin_counter() {
    if (!current_user_can('bch_manage_bookings')) return;
    [$today,$tomorrow]=bch_form_dates();
    $type=sanitize_key($_POST['stay_type']??$_GET['stay_type']??'overnight');
    if (!in_array($type,['overnight','hourly'],true)) $type='overnight';
    $now=new DateTimeImmutable('now',new DateTimeZone('Asia/Ho_Chi_Minh'));
    $data=['stay_type'=>$type,'start_time'=>$now->format('Y-m-d\TH:i'),'end_time'=>$now->modify('+2 hours')->format('Y-m-d\TH:i'),'name'=>'','phone'=>'','email'=>'','in'=>$today,'out'=>$tomorrow,'guests'=>2,'room'=>0,'initial_status'=>'checked_in'];
    echo '<div class="wrap"><h1>Tạo đơn tại quầy</h1><p>Dành cho khách đặt trực tiếp với lễ tân. Kiểm tra phòng và tiền trước khi lưu; sử dụng chung tồn phòng với website.</p>';
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        check_admin_referer('bch_counter');
        foreach (['name','phone','email','in','out','initial_status','key','token','paid','start_time','end_time'] as $key) $data[$key]=sanitize_text_field(wp_unslash($_POST[$key]??''));
        foreach (['room','guests','allocated_room','quoted','payment_verified','upgrade_consent'] as $key) $data[$key]=absint($_POST[$key]??0);
        try {
            if (($_POST['counter_step']??'')==='create') {
                $booking=bch_hold($data,true);
                echo '<div class="notice notice-success"><p>Đã tạo đơn <strong>'.esc_html($booking->code).'</strong> · '.esc_html(bch_status($booking->status)).'.</p></div>';
                echo '<p>Khách: '.esc_html($booking->name).' · Phòng: '.esc_html($booking->room_name).'</p><p>'.esc_html(bch_upgrade_description($booking)).'</p><p>Tổng: '.esc_html(bch_money($booking->total)).' · Đã thu: '.esc_html(bch_money($booking->status==='pending'?0:((int)$booking->deposit+(int)$booking->balance_paid))).'</p>';
                echo '<p><a class="button button-primary" href="'.esc_url(admin_url('admin.php?page=bch-bookings')).'">Xem đơn đặt phòng</a> <a class="button" href="'.esc_url(admin_url('admin.php?page=bch-counter')).'">Tạo đơn khác</a></p></div>';
                return;
            }
            if (!$data['name'] || strlen($data['name'])>190 || !preg_match('/^[+0-9 ()-]{8,25}$/',$data['phone']) || ($data['email']!=='' && !is_email($data['email']))) throw new InvalidArgumentException('Kiểm tra tên, số điện thoại và email (nếu có).');
            if (!in_array($data['initial_status'],['pending','confirmed','checked_in'],true)) throw new InvalidArgumentException('Chọn trạng thái hợp lệ.');
            $stay=bch_stay($data);
            $data['in']=$stay['in']; $data['out']=$stay['out'];
            if ($data['initial_status']==='checked_in' && $data['in']!==$today) throw new InvalidArgumentException('Khách đến ở ngay cần có ngày nhận là hôm nay. Với đặt trước, chọn Chờ cọc hoặc Đã thu cọc.');
            $days=$stay['days']; $room=bch_room($data['room']);
            if (!$room) throw new RuntimeException('Chọn hạng phòng còn kinh doanh.');
            $quote=bch_stay_price($room,$stay,$data['guests']);
            $allocated=bch_stay_allocation($room,$stay,$data['guests']);
            $upgraded=(int)$room->id!==(int)$allocated->id;
            $required=$data['initial_status']==='checked_in'?$quote['total']:($data['initial_status']==='confirmed'?$quote['deposit']:0);
            echo '<h2>Kiểm tra và xác nhận</h2><p>Khách: <strong>'.esc_html($data['name']).'</strong> · '.esc_html($data['phone']).'</p><p>'.esc_html($stay['start'].' → '.$stay['end']).' · '.esc_html($data['guests']).' khách · '.($type==='hourly'?esc_html($quote['hours']).' giờ tính tiền (tối thiểu 2 giờ, làm tròn lên)':count($days).' đêm').'</p><p>Loại khách chọn: '.esc_html($room->name).'<br>Phòng bố trí: <strong>'.esc_html($allocated->name).'</strong></p><p>Tổng tiền: <strong>'.esc_html(bch_money($quote['total'])).'</strong><br>Trạng thái: '.esc_html(bch_status($data['initial_status'])).'<br>Cần thu: <strong>'.esc_html(bch_money($required)).'</strong></p>';
            if ($type==='hourly') echo '<p>Giá 2 giờ đầu: '.esc_html(bch_money($quote['nightly']['base_2_hours'])).' · Tiền giờ thêm (30.000đ/giờ): '.esc_html(bch_money($quote['nightly']['extra_total'])).'</p>';
            if ($data['initial_status']==='pending') echo '<p>Chưa thu tiền. Hạn giữ chỗ: '.esc_html(bch_settings()['hours']).' giờ từ khi lưu đơn.</p>';
            echo '<form method="post">'; wp_nonce_field('bch_counter');
            $hidden=array_intersect_key($data,array_flip(['name','phone','email','in','out','room','guests','initial_status','stay_type','start_time','end_time']));
            $hidden=array_merge($hidden,['counter_step'=>'create','allocated_room'=>$allocated->id,'quoted'=>$quote['total'],'key'=>bin2hex(random_bytes(32)),'token'=>bin2hex(random_bytes(32))]);
            foreach ($hidden as $key=>$value) echo '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr($value).'">';
            if ($upgraded) echo '<p><label><input type="checkbox" name="upgrade_consent" value="1" required> Khách đã đồng ý đổi sang '.esc_html($allocated->name).', giữ giá và sức chứa của '.esc_html($room->name).'.</label></p>';
            echo '<p><label>Số tiền thực tế đã thu (VNĐ)<br><input type="number" name="paid" min="0" step="1" value="'.esc_attr($required).'" required></label></p>';
            if ($required>0) echo '<p><label><input type="checkbox" name="payment_verified" value="1" required> Tôi xác nhận đã thực thu đủ '.esc_html(bch_money($required)).'.</label></p>';
            echo '<p>Đơn chỉ được tạo sau khi bấm lưu. Hệ thống sẽ kiểm tra lại giá và tồn phòng.</p><button class="button button-primary">Lưu đơn tại quầy</button></form><hr>';
        } catch (Throwable $e) { bch_admin_error($e); }
    }
    echo '<p><a class="button" href="'.esc_url(admin_url('admin.php?page=bch-counter&stay_type=overnight')).'">Nghỉ ngày đêm</a> <a class="button" href="'.esc_url(admin_url('admin.php?page=bch-counter&stay_type=hourly')).'">Nghỉ giờ</a></p>';
    echo '<h2>'.($type==='hourly'?'Khách nghỉ giờ':'Khách nghỉ ngày đêm').'</h2>';
    if ($type==='hourly') echo '<p>Giá 2 giờ đầu theo loại phòng. Sau đó cộng 30.000đ/giờ, giờ lẻ làm tròn lên. Ví dụ 2 giờ 30 phút tính 3 giờ. Giờ nhận/trả dùng để kiểm tra trùng phòng.</p>';
    echo '<form method="post"><input type="hidden" name="stay_type" value="'.esc_attr($type).'">'; wp_nonce_field('bch_counter');
    echo '<input type="hidden" name="counter_step" value="preview">';
    foreach (['name'=>['Họ tên','text'],'phone'=>['Số điện thoại','tel'],'email'=>['Email (không bắt buộc)','email'],'in'=>['Ngày nhận phòng','date'],'out'=>['Ngày trả phòng','date'],'guests'=>['Số khách','number']] as $key=>$field) {
        if ($type==='hourly' && in_array($key,['in','out'],true)) continue;
        $limits=$field[1]==='date'?' min="'.esc_attr($today).'"':($key==='guests'?' min="1" max="10"':'');
        echo '<p><label>'.esc_html($field[0]).'<br><input class="regular-text" name="'.esc_attr($key).'" type="'.esc_attr($field[1]).'" value="'.esc_attr($data[$key]).'"'.$limits.($key==='email'?'':' required').'></label></p>';
    }
    if ($type==='hourly') foreach (['start_time'=>'Giờ nhận phòng','end_time'=>'Giờ trả dự kiến'] as $key=>$label) echo '<p><label>'.esc_html($label).'<br><input type="datetime-local" name="'.esc_attr($key).'" value="'.esc_attr($data[$key]).'" min="'.esc_attr($today).'T00:00" required></label></p>';
    echo '<p><label>Loại phòng khách chọn<br><select name="room" required><option value="">Chọn loại phòng</option>';
    foreach (bch_rooms() as $room) if ((int)$room->quantity>0 && ($type!=='hourly' || (int)$room->hourly_price>0)) echo '<option value="'.esc_attr($room->id).'" '.selected($data['room'],$room->id,false).'>'.esc_html($room->name.' · '.$room->capacity.' khách · '.bch_money($type==='hourly'?$room->hourly_price:$room->price).($type==='hourly'?'/2 giờ đầu':'/đêm')).'</option>';
    echo '</select></label></p><p><label>Tình trạng đặt trực tiếp<br><select name="initial_status">';
    foreach (['checked_in'=>'Khách đến ở ngay — đã thanh toán đủ','confirmed'=>'Đặt trước tại quầy — đã thu cọc 30%','pending'=>'Đặt trước tại quầy — chưa thu cọc'] as $value=>$label) echo '<option value="'.esc_attr($value).'" '.selected($data['initial_status'],$value,false).'>'.esc_html($label).'</option>';
    echo '</select></label></p><button class="button button-primary">Kiểm tra phòng & tính tiền</button></form></div>';
}
