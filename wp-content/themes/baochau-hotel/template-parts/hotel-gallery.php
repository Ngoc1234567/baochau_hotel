<?php
defined('ABSPATH') || exit;
$gallery_images=[];
for ($i=1;$i<=6;$i++) {
    $image_id=absint(bch_home_field('gallery_'.$i));
    if (!$image_id || !wp_attachment_is_image($image_id)) continue;
    $url=wp_get_attachment_image_url($image_id,'full');
    if ($url) $gallery_images[]=['id'=>$image_id,'url'=>$url];
}
if (!$gallery_images) return;
?>
<section class="section hotel-gallery-section" aria-labelledby="hotel-gallery-title">
    <div class="wrap" data-hotel-gallery>
        <div class="section-head"><div><h2 id="hotel-gallery-title">Hình ảnh khách sạn</h2><p class="hotel-gallery-intro">Không gian thực tế tại Bảo Châu.</p></div></div>
        <div class="hotel-gallery-grid">
        <?php foreach ($gallery_images as $index=>$photo):
            $caption=wp_get_attachment_caption($photo['id']);
            $alt=get_post_meta($photo['id'],'_wp_attachment_image_alt',true)?:($caption?:'Không gian khách sạn Bảo Châu — ảnh '.($index+1));
        ?>
            <figure class="hotel-gallery-item">
                <a data-hotel-photo href="<?php echo esc_url($photo['url']); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr('Phóng to: '.$alt); ?>" data-caption="<?php echo esc_attr($caption); ?>">
                    <?php echo wp_get_attachment_image($photo['id'],'large',false,['alt'=>$alt,'loading'=>'lazy','sizes'=>'(max-width: 680px) calc((100vw - 44px)/2), (max-width: 1333px) 29vw, 384px']); ?>
                    <span class="hotel-gallery-zoom" aria-hidden="true">⤢</span>
                </a>
                <?php if ($caption): ?><figcaption><?php echo esc_html($caption); ?></figcaption><?php endif; ?>
            </figure>
        <?php endforeach; ?>
        </div>
    </div>
</section>
