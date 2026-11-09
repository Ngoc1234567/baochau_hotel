<?php
/** Pure business rules; no WordPress dependencies. */
function bch_dates($in, $out, $today = null) {
    $tz = new DateTimeZone('Asia/Ho_Chi_Minh');
    $a = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$in, $tz);
    $b = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$out, $tz);
    if (!$a || !$b || $a->format('Y-m-d') !== $in || $b->format('Y-m-d') !== $out || $in < ($today ?? (new DateTimeImmutable('now',$tz))->format('Y-m-d')) || $b <= $a || $a->diff($b)->days > 30) throw new InvalidArgumentException('Chọn ngày hợp lệ, tối đa 30 đêm; ngày trả phải sau ngày nhận.');
    $days=[];
    while ($a < $b) { $days[]=$a->format('Y-m-d'); $a=$a->modify('+1 day'); }
    return $days;
}
function bch_price($room, $days, $guests) {
    if (!is_numeric($guests) || (int)$guests != $guests || $guests < 1 || $guests > $room->capacity) throw new InvalidArgumentException('Số khách vượt sức chứa hoặc không hợp lệ.');
    $rates = json_decode($room->rates, true) ?: [];
    $closed = json_decode($room->closed, true) ?: [];
    $total=0; $nightly=[];
    foreach ($days as $day) {
        if (in_array($day,$closed,true)) throw new InvalidArgumentException('Hạng phòng đóng bán trong khoảng ngày đã chọn.');
        $price=(int)($rates[$day] ?? $room->price);
        if ($price <= 0) throw new InvalidArgumentException('Giá phòng chưa được cấu hình.');
        $nightly[$day]=$price; $total += $price;
    }
    return ['total'=>$total,'deposit'=>(int)round($total * 0.3),'nightly'=>$nightly];
}
