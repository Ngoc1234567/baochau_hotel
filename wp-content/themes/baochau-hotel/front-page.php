<?php
// Homepage stock is for tonight, in the hotel's timezone.
if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE',true);
nocache_headers();
[$stock_in,$stock_out]=bch_form_dates();
$stock_days=bch_dates($stock_in,$stock_out);
get_header();
$acf_hero=absint(bch_home_field('image'));
$hero_url=$acf_hero?wp_get_attachment_image_url($acf_hero,'full'):(get_theme_mod('bch_hero_image')?:get_template_directory_uri().'/assets/retreat.svg');
$hero_is_artwork = $acf_hero === 30 || (!trim((string)bch_home_field('eyebrow')) && !trim((string)bch_home_field('title')) && !trim((string)bch_home_field('text')));
$hero_alt=$acf_hero?(get_post_meta($acf_hero,'_wp_attachment_image_alt',true)?:'Không gian khách sạn Bảo Châu'):'Không gian nghỉ dưỡng';
?>
<main id="main" class="home-main">
<section class="hero<?php if ($hero_is_artwork) echo ' hero--artwork'; ?>"><?php if ($hero_is_artwork): ?><h1 class="screen-reader-text"><?php echo esc_html(get_bloginfo('name')); ?></h1><?php endif; ?><img class="hero-image" src="<?php echo esc_url($hero_url); ?>" alt="<?php echo esc_attr($hero_alt); ?>"><div class="wrap hero-content"><p class="eyebrow"><?php echo esc_html(bch_home_field('eyebrow','BẢO CHÂU HOTEL · CHÀO ĐÓN BẠN')); ?></p><h1><?php echo nl2br(esc_html(str_replace('\\n',"\n",bch_home_field('title','Một chốn an yên.\nMột kỳ nghỉ trọn vẹn.')))); ?></h1><p><?php echo esc_html(bch_home_field('text','Gác lại nhịp sống vội vã, tận hưởng những ngày thảnh thơi trong không gian ấm áp của Bảo Châu.')); ?></p><div class="hero-links"><a class="button light" href="<?php echo esc_url(bch_home_field('cta_url')?:bch_page_url('phong-nghi')); ?>"><?php echo esc_html(bch_home_field('cta_label','Khám phá phòng nghỉ ↗')); ?></a></div></div></section>
<section class="home-search-band" aria-labelledby="home-search-title"><div class="wrap search-shell"><h2 id="home-search-title">Tìm phòng trống</h2><?php echo bch_search_form(); ?></div></section>

<section class="section wrap home-rooms" id="phong"><div class="section-head"><div><p class="eyebrow"><?php echo esc_html(bch_home_field('rooms_label','KHÔNG GIAN CỦA RIÊNG BẠN')); ?></p><h2><?php echo esc_html(bch_home_field('rooms_title','Chọn một nơi để nghỉ ngơi.')); ?></h2></div><span><?php echo esc_html(bch_home_field('rooms_note','Ấm áp. Tinh tế. Vừa đủ.')); ?></span></div><div class="room-grid">
<?php if (function_exists('bch_rooms')) foreach (bch_rooms() as $room):
    $stock_guests=min(2,(int)$room->capacity);
    $stock=bch_room_state($room,$stock_days,$stock_guests);
    $stock_link=add_query_arg(['room_detail'=>$room->id,'in'=>$stock_in,'out'=>$stock_out,'guests'=>$stock_guests],bch_page_url('phong-nghi'));
    if ($stock['upgrade']) $stock_link=bch_url(['room'=>$room->id,'in'=>$stock_in,'out'=>$stock_out,'guests'=>$stock_guests]);
?>
<article class="room-card room-card--compact"><h3><?php echo esc_html($room->name); ?></h3><a class="room-card-photo" href="<?php echo esc_url($stock_link); ?>"><img loading="lazy" src="<?php echo esc_url(bch_theme_image($room)); ?>" alt="<?php echo esc_attr($room->name); ?><?php echo empty(json_decode($room->images,true))?'':''; ?>"></a><p class="room-card-guests">Tối đa <?php echo esc_html($room->capacity); ?> khách</p><?php bch_stock_badge($room,$stock); ?><p class="price"><small>Từ</small> <?php echo esc_html(bch_money($stock['quote']['total']??$room->price)); ?> <small>/ đêm</small></p><div class="card-actions"><?php if ($stock['left']>0 || $stock['upgrade']): ?><a class="button" href="<?php echo esc_url(bch_booking_room_url($room,$stock_in,$stock_out,$stock_guests)); ?>"><?php echo $stock['upgrade']?'Xem phương án đổi phòng →':'Đặt phòng →'; ?></a><?php else: ?><button type="button" disabled><?php echo $stock['status']==='closed'?'Tạm ngừng bán':'Hết phòng'; ?></button><a href="<?php echo esc_url(bch_url()); ?>">Chọn ngày khác</a><?php endif; ?></div></article>
<?php endforeach; ?></div><p class="room-price-note"><strong>*Lưu ý:</strong> Giá phòng áp dụng cho ngày thường, có thể điều chỉnh vào dịp cuối tuần hoặc mùa cao điểm. Vui lòng chọn ngày để xem giá chính xác.</p></section>
<div class="home-location-band" id="tien-ich"><?php get_template_part('template-parts/location',null,['home'=>true]); ?></div>
<section class="contact-section" id="lien-he">
<?php $contact_photo=absint(bch_home_field('contact_photo',get_theme_mod('bch_contact_photo'))); if ($contact_photo && wp_attachment_is_image($contact_photo)): ?>
<figure class="hotel-photo"><?php echo wp_get_attachment_image($contact_photo,'large',false,['class'=>'contact-photo','loading'=>'lazy','sizes'=>'(max-width: 680px) 100vw, 52vw']); ?><figcaption><?php echo esc_html(wp_get_attachment_caption($contact_photo)); ?></figcaption></figure>
<?php else: ?><img class="contact-art" loading="lazy" src="<?php echo esc_url(get_template_directory_uri().'/assets/retreat.svg'); ?>" alt="Khu vườn và hồ nước"><?php endif; ?>
<div class="contact-copy"><p class="eyebrow"><?php echo esc_html(bch_home_field('contact_label','HẸN GẶP BẠN TẠI BẢO CHÂU')); ?></p><h2><?php echo nl2br(esc_html(bch_home_field('contact_title','Kỳ nghỉ tiếp theo, bắt đầu từ đây.'))); ?></h2><p><?php echo nl2br(esc_html(bch_home_field('contact','Địa chỉ, điện thoại và email sẽ được khách sạn cập nhật trước khi mở đặt phòng.'))); ?></p><a class="button light" href="<?php echo esc_url(bch_theme_booking()); ?>"><?php echo esc_html(bch_home_field('contact_cta','Lên kế hoạch kỳ nghỉ ↗')); ?></a></div></section>
</main><?php get_footer(); ?>
