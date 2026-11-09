<?php /* Template Name: Bảo Châu — Tiện ích */
get_header(); ?>
<main id="main" class="wrap page-main content-page"><?php while (have_posts()): the_post(); bch_page_heading('TẬN HƯỞNG KỲ NGHỈ'); ?><div class="amenities-page-content editor-content"><?php bch_page_content(); ?></div>
<div class="acf-service-grid"><?php for ($i=1;$i<=6;$i++): $title=bch_field('bch_service_'.$i.'_title'); if (!$title) continue; ?><article class="acf-service-card"><?php if ($image=absint(bch_field('bch_service_'.$i.'_image'))) echo wp_get_attachment_image($image,'medium_large',false,['loading'=>'lazy']); ?><div><h2><?php echo esc_html($title); ?></h2><p><?php echo nl2br(esc_html(bch_field('bch_service_'.$i.'_text'))); ?></p></div></article><?php endfor; ?></div>
<?php bch_page_demo_note(); endwhile; ?><a class="button" href="<?php echo esc_url(bch_page_url('lien-he')); ?>">Liên hệ để được tư vấn →</a></main><?php get_footer(); ?>
