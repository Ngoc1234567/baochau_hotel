<?php
defined('ABSPATH') || exit;

function bch_stay($data) {
    $type=$data['stay_type']??'overnight';
    if (!in_array($type,['overnight','hourly'],true)) throw new InvalidArgumentException('Loại lưu trú không hợp lệ.');
    if ($type==='overnight') {
        $days=bch_dates($data['in'],$data['out']);
        return ['type'=>$type,'in'=>$data['in'],'out'=>$data['out'],'days'=>$days,'start'=>$data['in'].' 13:00:00','end'=>$data['out'].' 12:00:00'];
    }
    $tz=new DateTimeZone('Asia/Ho_Chi_Minh');
    $start=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',(string)($data['start_time']??''),$tz);
    $end=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',(string)($data['end_time']??''),$tz);
    if (!$start || !$end || $start->format('Y-m-d\TH:i')!==$data['start_time'] || $end->format('Y-m-d\TH:i')!==$data['end_time'] || $start->format('Y-m-d')<wp_date('Y-m-d',null,$tz) || $end<=$start || $end->getTimestamp()-$start->getTimestamp()>30*86400) throw new InvalidArgumentException('Chọn giờ nhận/trả hợp lệ, giờ trả sau giờ nhận; tối đa 30 ngày.');
    $days=[];
    for ($d=$start->setTime(0,0);$d<$end;$d=$d->modify('+1 day')) $days[]=$d->format('Y-m-d');
    return ['type'=>$type,'in'=>$start->format('Y-m-d'),'out'=>$end->format('Y-m-d'),'days'=>$days,'start'=>$start->format('Y-m-d H:i:s'),'end'=>$end->format('Y-m-d H:i:s')];
}

function bch_stay_price($room,$stay,$guests) {
    if ($stay['type']==='overnight') return bch_price($room,$stay['days'],$guests);
    if (!is_numeric($guests) || (int)$guests!=$guests || $guests<1 || $guests>$room->capacity) throw new InvalidArgumentException('Số khách vượt sức chứa hoặc không hợp lệ.');
    if (array_intersect($stay['days'],json_decode($room->closed,true)?:[])) throw new InvalidArgumentException('Phòng đóng bán trong thời gian đã chọn.');
    $base=(int)($room->hourly_price??0);
    if ($base<=0) throw new InvalidArgumentException('Hạng phòng chưa có giá nghỉ giờ.');
    $tz=new DateTimeZone('Asia/Ho_Chi_Minh');
    $seconds=(new DateTimeImmutable($stay['end'],$tz))->getTimestamp()-(new DateTimeImmutable($stay['start'],$tz))->getTimestamp();
    $hours=max(2,(int)ceil($seconds/3600));
    $extra=max(0,$hours-2)*30000;
    $total=$base+$extra;
    return ['total'=>$total,'deposit'=>(int)round($total*.3),'hours'=>$hours,'minutes'=>(int)($seconds/60),'nightly'=>['base_2_hours'=>$base,'billed_hours'=>$hours,'extra_hour_price'=>30000,'extra_total'=>$extra]];
}

/** Local hotel datetimes; end is exclusive, so adjacent stays do not overlap. */
function bch_overlap_rows($start,$end,$room_id=null) {
    global $wpdb;
    $where=" WHERE COALESCE(starts_at,CONCAT(checkin,' 13:00:00'))<%s AND COALESCE(ends_at,CONCAT(checkout,' 12:00:00'))>%s AND (status IN ('confirmed','checked_in') OR (status='pending' AND expires_at>UTC_TIMESTAMP()))";
    $args=[$end,$start];
    if ($room_id!==null) { $where.=' AND room_id=%d'; $args[]=$room_id; }
    $rows=$wpdb->get_results($wpdb->prepare('SELECT *, COALESCE(starts_at,CONCAT(checkin,\' 13:00:00\')) AS occupied_start, COALESCE(ends_at,CONCAT(checkout,\' 12:00:00\')) AS occupied_end FROM '.bch_table('bookings').$where,...$args));
    if ($wpdb->last_error) throw new RuntimeException('Không đọc được lịch phòng.');
    return $rows;
}
function bch_peak_occupancy($rows,$start=null,$end=null) {
    $events=[];
    foreach ($rows as $row) {
        $a=$start?max($start,$row->occupied_start):$row->occupied_start;
        $b=$end?min($end,$row->occupied_end):$row->occupied_end;
        if ($a>=$b) continue;
        $events[$a]=($events[$a]??0)+1; $events[$b]=($events[$b]??0)-1;
    }
    ksort($events); $used=0; $peak=0;
    foreach ($events as $delta) { $used+=$delta; $peak=max($peak,$used); }
    return $peak;
}
function bch_available_interval($room,$start,$end) {
    return max(0,(int)$room->quantity-bch_peak_occupancy(bch_overlap_rows($start,$end,$room->id),$start,$end));
}
function bch_stay_allocation($room,$stay,$guests) {
    if ($stay['type']==='overnight') return bch_allocation($room,$stay['days'],$guests);
    bch_stay_price($room,$stay,$guests);
    if (bch_available_interval($room,$stay['start'],$stay['end'])>0) return $room;
    // Hourly price is retained only for the explicitly configured upgrade pair.
    $pair=get_option('bch_upgrade_pair',[]);
    if (!empty(bch_settings()['free_upgrade']) && (int)$room->quantity>0 && (int)($pair['from']??0)===(int)$room->id) {
        $target=bch_room((int)($pair['to']??0));
        if ($target && $guests<=$target->capacity && !array_intersect($stay['days'],json_decode($target->closed,true)?:[]) && bch_available_interval($target,$stay['start'],$stay['end'])>0) return $target;
    }
    throw new RuntimeException('Hết phòng trong khung giờ đã chọn. Vui lòng đổi giờ hoặc chọn hạng phòng khác.');
}
function bch_stay_label($booking) {
    if (($booking->stay_type??'overnight')==='hourly') return 'Nghỉ giờ: '.substr($booking->starts_at,0,16).' → '.substr($booking->ends_at,0,16).' (giờ Việt Nam)';
    return 'Ngày đêm: '.$booking->checkin.' 13:00 → '.$booking->checkout.' 12:00';
}
