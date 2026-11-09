<?php
 defined('ABSPATH') || exit;
$room=function_exists('bch_room')?bch_room(absint($_GET['room_detail']??0)):null;
if (!$room) { status_header(404); get_header(); echo '<main id="main" class="wrap page-main"><h1>Không tìm thấy hạng phòng</h1><a href="'.esc_url(bch_page_url('phong-nghi')).'">Xem các hạng phòng</a></main>'; get_footer(); return; }
$images=array_values(array_filter(json_decode($room->images,true)?:[]));
$illustration=!$images;
if (!$images) $images=[bch_theme_image()];
$amenities=array_filter(array_map('trim',explode(',',$room->amenities)));
get_header(); ?>
<main id="main" class="wrap page-main room-detail-page">
<nav class="room-breadcrumb" aria-label="Đường dẫn"><a href="<?php echo esc_url(home_url('/')); ?>">Trang chủ</a><span aria-hidden="true">/</span><a href="<?php echo esc_url(bch_page_url('phong-nghi')); ?>">Phòng nghỉ</a><span aria-hidden="true">/</span><span aria-current="page"><?php echo esc_html($room->name); ?></span></nav>
<div class="room-detail-top">
<section class="room-photo-panel" aria-label="Thư viện ảnh phòng" data-room-gallery>
<a class="room-photo-large" href="<?php echo esc_url($images[0]); ?>" target="_blank" rel="noopener" aria-label="Mở ảnh phòng kích thước đầy đủ"><img data-gallery-main src="<?php echo esc_url($images[0]); ?>" alt="<?php echo esc_attr($room->name.($illustration?' — hình minh họa':' — ảnh 1')); ?>" fetchpriority="high"></a>
<?php if (count($images)>1): ?><div class="room-thumbnails" aria-label="Chọn ảnh phòng"><?php foreach ($images as $index=>$url): ?><a href="<?php echo esc_url($url); ?>" data-gallery-thumb aria-label="Xem ảnh <?php echo (int)$index+1; ?>" aria-current="<?php echo $index===0?'true':'false'; ?>"><img src="<?php echo esc_url($url); ?>" alt="<?php echo esc_attr($room->name.' — ảnh '.($index+1)); ?>" loading="lazy"></a><?php endforeach; ?></div><?php endif; ?>
<p class="room-photo-caption"><?php echo $illustration?'Hình minh họa · Ảnh thực tế của hạng phòng đang được cập nhật.':'Chọn ảnh nhỏ để xem. Bấm ảnh lớn để mở kích thước đầy đủ.'; ?></p>
<p class="room-gallery-status screen-reader-text" aria-live="polite"></p>
<div class="room-photo-note"><span aria-hidden="true">◇</span><div><strong>Đặt trực tiếp với Bảo Châu</strong><p>Kiểm tra phòng trống theo ngày. Xem đầy đủ tiền phòng và cọc 30% trước khi giữ chỗ.</p></div></div>
</section>
<section class="room-information" aria-labelledby="room-name">
<p class="eyebrow">BẢO CHÂU HOTEL <?php if (get_theme_mod('bch_demo',true)) echo '· DEMO'; ?></p>
<h1 id="room-name"><?php echo esc_html($room->name); ?></h1>
<ul class="room-facts"><li><strong><?php echo esc_html($room->capacity); ?></strong><span>Khách tối đa</span></li><li><strong><?php echo esc_html($room->area); ?> m²</strong><span>Diện tích</span></li><li><strong><?php echo esc_html($room->bed); ?></strong><span>Loại giường</span></li></ul>
<h2 class="room-amenities-heading">Tiện nghi trong phòng</h2><ul class="room-amenities"><?php foreach ($amenities as $amenity) echo '<li><span aria-hidden="true">✓</span> '.esc_html($amenity).'</li>'; ?></ul>
<div class="room-rate"><span>Giá từ <?php if (get_theme_mod('bch_demo',true)) echo '· minh họa'; ?></span><p><strong><?php echo esc_html(bch_money($room->price)); ?></strong> / đêm</p><small>Giá từng đêm và tình trạng phòng được kiểm tra khi chọn ngày ở.</small></div>
<?php if ((int)$room->quantity>0): ?>
<?php if (!bch_ready()): ?><p class="room-demo-notice">Bản demo: bạn có thể kiểm tra giá; chưa nhận giữ chỗ thật.</p><?php endif; ?>
<div id="room-reservation"><?php bch_reservation_form(['room'=>$room->id],false,true); ?></div>
<?php else: ?><p class="notice">Hạng phòng hiện tạm ngừng nhận đặt. Vui lòng chọn phòng khác hoặc liên hệ lễ tân.</p><?php endif; ?>
</section>
</div>
<section class="room-description" aria-labelledby="room-description-title"><h2 id="room-description-title">Mô tả chi tiết</h2><div class="room-description-grid"><div><p><?php echo nl2br(esc_html($room->description)); ?></p><h3>Thông tin lưu trú</h3><p>Phòng dành cho tối đa <?php echo esc_html($room->capacity); ?> khách, diện tích <?php echo esc_html($room->area); ?> m², <?php echo esc_html($room->bed); ?>. Vui lòng tính tất cả người lưu trú vào số khách.</p></div><div><h3>Chính sách đặt phòng</h3><p>Cọc 30% tổng tiền phòng qua chuyển khoản. Thời hạn giữ chỗ: <?php echo esc_html(bch_settings()['hours']); ?> giờ. Đặt phòng được xác nhận khi khách sạn đối chiếu đủ cọc.</p><a href="<?php echo esc_url(get_permalink(get_option('bch_policy_page'))); ?>">Xem đầy đủ chính sách đặt cọc →</a></div></div></section>
<?php $related=array_slice(array_values(array_filter(bch_rooms(),function ($r) use ($room) { return $r->id!==$room->id && (int)$r->quantity>0; })),0,3); if ($related): ?>
<section class="room-related" aria-labelledby="related-title"><div class="section-head"><div><p class="eyebrow">THÊM LỰA CHỌN CHO KỲ NGHỈ</p><h2 id="related-title">Phòng tương tự</h2></div><a href="<?php echo esc_url(bch_page_url('phong-nghi')); ?>">Tất cả hạng phòng →</a></div><div class="room-grid"><?php foreach ($related as $other): $link=add_query_arg('room_detail',$other->id,bch_page_url('phong-nghi')); ?>
<article class="room-card"><a href="<?php echo esc_url($link); ?>"><img src="<?php echo esc_url(bch_theme_image($other)); ?>" alt="<?php echo esc_attr($other->name.(!(json_decode($other->images,true)?:[])?' — hình minh họa':'')); ?>" loading="lazy"></a><h3><?php echo esc_html($other->name); ?></h3><p><?php echo esc_html($other->capacity.' khách · '.$other->area.' m²'); ?></p><p class="price"><small>Từ</small> <?php echo esc_html(bch_money($other->price)); ?> <small>/ đêm</small></p><div class="card-actions"><a class="button" href="<?php echo esc_url($link); ?>">Đặt phòng →</a></div></article>
<?php endforeach; ?></div></section><?php endif; ?>
</main><?php get_footer(); ?>
