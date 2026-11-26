<?php /* Template Name: Bảo Châu — Tiện ích */
get_header();
$amenity_icons = [
    1 => '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M12 6v12M7 9l2-2 2 2M7 15l2 2 2-2M15 7h2M15 17h2"/>',
    2 => '<path d="M3 18v3M21 18v3M3 12h18v6H3zM5 12V5h14v7M7 12V9h4v3M13 12V9h4v3"/>',
    3 => '<rect x="3" y="9" width="18" height="12" rx="2"/><path d="M9 23h6M12 21v2M6 4a10 10 0 0 1 12 0M9 6a5 5 0 0 1 6 0"/><circle cx="12" cy="8" r=".5"/>',
    4 => '<path d="M5 21V7a4 4 0 0 1 8 0M10 10h6a3 3 0 0 0-6 0ZM10 14v1M13 14v1M16 14v1M10 18v1M13 18v1M16 18v1M3 21h5"/>',
    5 => '<path d="M4 14v-3a8 8 0 0 1 16 0v3M4 12H3v6h4v-6H4ZM20 12h1v6h-4v-6h3ZM20 18v1a3 3 0 0 1-3 3h-5"/><path d="M10 21h4"/>',
    6 => '<circle cx="14" cy="4" r="2"/><path d="m7 12 3-4 4 1 3 4h3M13 10l-2 6-5 5M11 16l5 5M5 8h3M3 5h5"/>',
];
?>
<main id="main" class="wrap page-main content-page"><?php while (have_posts()): the_post(); bch_page_heading('TẬN HƯỞNG KỲ NGHỈ'); ?><?php ob_start(); bch_page_content(); $amenities_body=ob_get_clean(); if (trim($amenities_body)): ?><div class="amenities-page-content editor-content"><?php echo $amenities_body; ?></div><?php endif; ?>
<div class="acf-service-grid">
<?php for ($i=1;$i<=6;$i++):
    $title=bch_field('bch_service_'.$i.'_title');
    if (!$title) continue;
    $items=array_filter(array_map('trim',preg_split('/\R/u',(string)bch_field('bch_service_'.$i.'_text'))));
?>
<article class="acf-service-card">
<?php if ($image=absint(bch_field('bch_service_'.$i.'_image'))) echo wp_get_attachment_image($image,'medium_large',false,['loading'=>'lazy']); ?>
<div><span class="amenity-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" focusable="false"><?php echo $amenity_icons[$i]; ?></svg></span><h2><?php echo esc_html($title); ?></h2>
<?php if ($items): ?><ul class="amenities-details"><?php foreach ($items as $item): ?><li><?php echo esc_html($item); ?></li><?php endforeach; ?></ul><?php endif; ?>
</div></article>
<?php endfor; ?>
</div>
<?php get_template_part('template-parts/location'); ?>
<?php bch_page_demo_note(); endwhile; ?><a class="button" href="<?php echo esc_url(bch_page_url('lien-he')); ?>">Liên hệ để được tư vấn</a></main><?php get_footer(); ?>
