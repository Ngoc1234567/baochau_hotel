<?php
defined('ABSPATH') || exit;

/** Only the explicitly configured pair may receive a free upgrade. */
function bch_upgrade_room($requested,$days,$guests) {
    if (empty(bch_settings()['free_upgrade']) || (int)$requested->quantity<1) return null;
    $pair=get_option('bch_upgrade_pair',[]);
    if ((int)($pair['from']??0)!==(int)$requested->id) return null;
    // Validate the purchased capacity and closed dates, not the larger capacity.
    bch_price($requested,$days,$guests);
    if (bch_available($requested,$days)>0) return null;
    $target=bch_room((int)($pair['to']??0));
    if (!$target || (int)$target->id===(int)$requested->id || $guests>$target->capacity) return null;
    if (array_intersect($days,json_decode($target->closed,true)?:[])) return null;
    return bch_available($target,$days)>0 ? $target : null;
}

/** Preview only; the same decision is repeated under the inventory lock on hold. */
function bch_allocation($requested,$days,$guests) {
    bch_price($requested,$days,$guests);
    if (bch_available($requested,$days)>0) return $requested;
    $target=bch_upgrade_room($requested,$days,$guests);
    if ($target) return $target;
    throw new RuntimeException('Hạng phòng đã hết chỗ trong kỳ ở này. Vui lòng chọn ngày khác hoặc liên hệ lễ tân để được hỗ trợ.');
}

function bch_upgrade_description($booking) {
    if (empty($booking->upgrade_accepted)) return '';
    return 'Khách chọn: '.$booking->requested_room_name.'. Bố trí: '.$booking->room_name.'. Nâng hạng miễn phí, giữ giá và sức chứa của loại khách chọn; khách đã đồng ý.';
}

function bch_upgrade_notice($room,$target,$quote,$guests) {
    echo '<section class="notice upgrade-offer" role="status"><h3>Đổi sang phòng lớn hơn, không phụ thu</h3><p>'.esc_html($room->name).' đã hết trong ngày bạn chọn. Khách sạn có thể bố trí <strong>'.esc_html($target->name).'</strong>, giữ giá của '.esc_html($room->name).' cho tối đa '.esc_html($room->capacity).' khách.</p><p>Tổng kỳ ở: <strong>'.esc_html(bch_money($quote['total'])).'</strong> · Cọc 30%: '.esc_html(bch_money($quote['deposit'])).'. Chưa giữ phòng; tình trạng phòng sẽ được kiểm tra lại khi bạn xác nhận.</p><a href="'.esc_url(bch_url()).'">Chọn ngày hoặc hạng phòng khác →</a></section>';
}
