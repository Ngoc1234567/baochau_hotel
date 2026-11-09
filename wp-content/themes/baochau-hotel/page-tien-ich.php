<?php /* Template Name: Bảo Châu — Tiện ích */
get_header(); ?>
<main id="main" class="wrap page-main content-page"><?php while (have_posts()): the_post(); bch_page_heading('TẬN HƯỞNG KỲ NGHỈ'); ?><div class="amenities-page-content editor-content"><?php the_content(); ?></div><?php bch_page_demo_note(); endwhile; ?><a class="button" href="<?php echo esc_url(bch_page_url('lien-he')); ?>">Liên hệ để được tư vấn →</a></main><?php get_footer(); ?>
