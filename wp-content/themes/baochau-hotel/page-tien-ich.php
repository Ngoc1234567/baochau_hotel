<?php /* Template Name: Bảo Châu — Tiện ích */
get_header();
$contact_page=get_page_by_path('lien-he');
$hotel_address=$contact_page?bch_field('bch_contact_address','',$contact_page->ID):'';
$map_destination='Khách sạn Bảo Châu, '.($hotel_address?:'Trần Can, Mường Thanh, Điện Biên Phủ, Điện Biên');
$map_embed=add_query_arg(['q'=>$map_destination,'output'=>'embed','hl'=>'vi'],'https://www.google.com/maps');
$map_directions=add_query_arg(['api'=>'1','destination'=>$map_destination,'travelmode'=>'driving'],'https://www.google.com/maps/dir/');
$airport_directions=add_query_arg(['api'=>'1','origin'=>'Cảng hàng không Điện Biên, Điện Biên','destination'=>$map_destination,'travelmode'=>'driving'],'https://www.google.com/maps/dir/');
?>
<main id="main" class="wrap page-main content-page"><?php while (have_posts()): the_post(); bch_page_heading('TẬN HƯỞNG KỲ NGHỈ'); ?><div class="amenities-page-content editor-content"><?php bch_page_content(); ?></div>
<div class="acf-service-grid"><?php for ($i=1;$i<=6;$i++): $title=bch_field('bch_service_'.$i.'_title'); if (!$title) continue; ?><article class="acf-service-card"><?php if ($image=absint(bch_field('bch_service_'.$i.'_image'))) echo wp_get_attachment_image($image,'medium_large',false,['loading'=>'lazy']); ?><div><h2><?php echo esc_html($title); ?></h2><p><?php echo nl2br(esc_html(bch_field('bch_service_'.$i.'_text'))); ?></p></div></article><?php endfor; ?></div>
<section class="amenities-location" aria-labelledby="amenities-location-title">
<h2 id="amenities-location-title">Vị trí &amp; di chuyển</h2>
<div class="amenities-travel-grid">
<article><h3>Bãi đỗ xe</h3><p>Bãi đỗ xe rộng rãi, thoải mái ngay tại Quảng trường 7/5 (cách 50m), thuận tiện cho cả xe gia đình và xe du lịch đoàn</p></article>
<article><h3>Gần sân bay Điện Biên</h3><p><?php echo esc_html(bch_field('bch_airport_distance','Cách sân bay Điện Biên khoảng 10 km.')); ?></p><a href="<?php echo esc_url($airport_directions); ?>" target="_blank" rel="noopener noreferrer">Xem đường đi từ sân bay ↗</a></article>
</div>
<iframe class="amenities-map" src="<?php echo esc_url($map_embed); ?>" title="Bản đồ vị trí Khách sạn Bảo Châu, Điện Biên Phủ" width="1200" height="400" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
<a class="button" href="<?php echo esc_url($map_directions); ?>" target="_blank" rel="noopener noreferrer">Chỉ đường đến khách sạn ↗</a>
</section>
<?php bch_page_demo_note(); endwhile; ?><a class="button" href="<?php echo esc_url(bch_page_url('lien-he')); ?>">Liên hệ để được tư vấn →</a></main><?php get_footer(); ?>
