<?php
defined('ABSPATH') || exit;

function bch_room_state($room,$days,$guests=1) {
    if ($guests<1 || $guests>(int)$room->capacity) return ['status'=>'unsuitable','left'=>0,'upgrade'=>null,'quote'=>null];
    if ((int)$room->quantity<1 || array_intersect($days,json_decode($room->closed,true)?:[])) return ['status'=>'closed','left'=>0,'upgrade'=>null,'quote'=>null];
    $quote=bch_price($room,$days,$guests);
    $left=bch_available($room,$days);
    $upgrade=$left?null:bch_upgrade_room($room,$days,$guests);
    return ['status'=>$left?'available':'full','left'=>$left,'upgrade'=>$upgrade,'quote'=>$quote];
}

function bch_stock_badge($room,$state=null) {
    if ($state===null) {
        $in=sanitize_text_field(wp_unslash($_GET['in']??''));
        $out=sanitize_text_field(wp_unslash($_GET['out']??''));
        if (!$in && !$out) {
            echo '<p class="stock-badge stock-unknown">Chọn ngày để xem còn phòng / hết phòng</p>';
            return;
        }
        try { $state=bch_room_state($room,bch_dates($in,$out),absint($_GET['guests']??1)); }
        catch (Throwable $e) { echo '<p class="stock-badge stock-unknown">Vui lòng chọn ngày nhận và trả hợp lệ</p>'; return; }
    }
    $labels=['available'=>'Còn '.$state['left'].' / '.$room->quantity.' phòng','full'=>'Hết phòng trong kỳ ở đã chọn','closed'=>'Tạm ngừng bán trong kỳ ở đã chọn','unsuitable'=>'Không đủ sức chứa cho số khách đã chọn'];
    echo '<p class="stock-badge stock-'.esc_attr($state['status']).'">'.esc_html($labels[$state['status']]).'</p>';
    if ($state['upgrade']) echo '<p class="stock-upgrade">Có thể đổi sang '.esc_html($state['upgrade']->name).' miễn phí, cần bạn đồng ý.</p>';
}

function bch_availability_card($room,$state,$in,$out,$guests,$nights) {
    $bookable=$state['left']>0 || $state['upgrade'];
    $link=bch_url(['room'=>$room->id,'in'=>$in,'out'=>$out,'guests'=>$guests]);
    echo '<article class="room-card"><img loading="lazy" src="'.esc_url(bch_theme_image($room)).'" alt="'.esc_attr($room->name).'"><h3>'.esc_html($room->name).'</h3><p>'.esc_html($room->capacity).' khách · '.esc_html($room->bed).'</p>';
    bch_stock_badge($room,$state);
    if ($state['quote']) echo '<p class="price">'.esc_html(bch_money($state['quote']['total'])).' <small>/ '.(int)$nights.' đêm</small></p><p>Cọc 30%: '.esc_html(bch_money($state['quote']['deposit'])).'</p>';
    echo '<div class="card-actions">';
    if ($bookable) echo '<a class="button" href="'.esc_url($link).'">'.($state['upgrade']?'Xem phương án đổi phòng →':'Đặt phòng →').'</a>';
    else echo '<button type="button" disabled>'.($state['status']==='closed'?'Tạm ngừng bán':'Hết phòng').'</button><a href="'.esc_url(bch_url()).'">Chọn ngày khác</a>';
    echo '</div></article>';
}
