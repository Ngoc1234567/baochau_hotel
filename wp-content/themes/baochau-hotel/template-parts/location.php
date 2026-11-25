<?php
defined('ABSPATH') || exit;
$amenities_page=get_page_by_path('tien-ich');
$amenities_page_id=$amenities_page?$amenities_page->ID:get_queried_object_id();
$contact_page=get_page_by_path('lien-he');
$hotel_address=$contact_page?bch_field('bch_contact_address','',$contact_page->ID):'';
$map_destination='Khách sạn Bảo Châu, '.($hotel_address?:'Trần Can, Mường Thanh, Điện Biên Phủ, Điện Biên');
$map_embed=add_query_arg(['q'=>$map_destination,'output'=>'embed','hl'=>'vi'],'https://www.google.com/maps');
$map_directions=add_query_arg(['api'=>'1','destination'=>$map_destination,'travelmode'=>'driving'],'https://www.google.com/maps/dir/');
$airport_directions=add_query_arg(['api'=>'1','origin'=>'Cảng hàng không Điện Biên, Điện Biên','destination'=>$map_destination,'travelmode'=>'driving'],'https://www.google.com/maps/dir/');
?>
<section class="amenities-location<?php if (!empty($args['home'])) echo ' wrap'; ?>" aria-labelledby="amenities-location-title">
<h2 id="amenities-location-title">Vị trí &amp; di chuyển</h2>
<div class="amenities-travel-grid">
<article><h3>Bãi đỗ xe</h3><p>Bãi đỗ xe rộng rãi, thoải mái ngay tại Quảng trường 7/5 (cách 50m), thuận tiện cho cả xe gia đình và xe du lịch đoàn</p></article>
<article><h3>Gần sân bay Điện Biên</h3><p><?php echo esc_html(bch_field('bch_airport_distance','Cách sân bay Điện Biên khoảng 10 km.',$amenities_page_id)); ?></p><a href="<?php echo esc_url($airport_directions); ?>" target="_blank" rel="noopener noreferrer">Xem đường đi từ sân bay</a></article>
</div>
<iframe class="amenities-map" src="<?php echo esc_url($map_embed); ?>" title="Bản đồ vị trí Khách sạn Bảo Châu, Điện Biên Phủ" width="1200" height="400" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
<a class="button" href="<?php echo esc_url($map_directions); ?>" target="_blank" rel="noopener noreferrer">Chỉ đường đến khách sạn</a>
</section>
