<?php /* Template Name: Bảo Châu — Chính sách đặt cọc */
get_header(); ?>
<main id="main" class="wrap page-main content-page">
<?php while (have_posts()): the_post(); bch_page_heading('THÔNG TIN ĐẶT PHÒNG'); ?>
<div class="editor-content"><?php if (metadata_exists('post',get_the_ID(),'bch_page_body')) bch_page_content(); ?></div>
<?php endwhile; ?>
</main><?php get_footer(); ?>
