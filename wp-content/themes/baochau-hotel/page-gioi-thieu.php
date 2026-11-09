<?php /* Template Name: Bảo Châu — Giới thiệu */
get_header(); ?>
<main id="main" class="wrap page-main content-page"><?php while (have_posts()): the_post(); bch_page_heading('CÂU CHUYỆN BẢO CHÂU'); ?>
<div class="about-page-layout"><div class="editor-content"><?php the_content(); bch_page_demo_note(); ?><a class="button" href="<?php echo esc_url(bch_page_url('phong-nghi')); ?>">Khám phá phòng nghỉ →</a></div><figure class="about-page-photo"><?php $photo=absint(get_theme_mod('bch_contact_photo')); if ($photo): echo wp_get_attachment_image($photo,'large',false,['loading'=>'lazy']); ?><figcaption><?php echo esc_html(wp_get_attachment_caption($photo)); ?></figcaption><?php else: ?><img src="<?php echo esc_url(get_template_directory_uri().'/assets/retreat.svg'); ?>" alt="Hình minh họa không gian nghỉ dưỡng"><?php endif; ?></figure></div>
<?php endwhile; ?></main><?php get_footer(); ?>
