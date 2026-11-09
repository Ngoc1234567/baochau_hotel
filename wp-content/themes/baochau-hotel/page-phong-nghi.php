<?php /* Template Name: Bảo Châu — Phòng nghỉ */
get_header(); ?>
<main id="main" class="wrap page-main content-page"><?php while (have_posts()): the_post(); bch_page_heading('KHÔNG GIAN NGHỈ NGƠI'); ?><div class="editor-content"><?php the_content(); ?></div><?php endwhile; ?>
<?php echo bch_search_form(); ?><div class="room-grid page-room-list">
<?php foreach (bch_rooms() as $room): $link=add_query_arg('room_detail',$room->id,bch_page_url('phong-nghi')); ?>
<article class="room-card"><a href="<?php echo esc_url($link); ?>"><img src="<?php echo esc_url(bch_theme_image($room)); ?>" alt="<?php echo esc_attr($room->name.(!(json_decode($room->images,true)?:[])?' — hình minh họa':'')); ?>" loading="lazy"></a><h2><?php echo esc_html($room->name); ?></h2><p><?php echo esc_html($room->area.' m² · '.$room->capacity.' khách · '.$room->bed); ?></p><p class="price"><small>Từ</small> <?php echo esc_html(bch_money($room->price)); ?> <small>/ đêm</small></p><div class="card-actions"><a href="<?php echo esc_url($link); ?>">Xem chi tiết</a><a class="button" href="<?php echo esc_url($link.'#room-reservation'); ?>">Đặt phòng</a></div></article>
<?php endforeach; ?></div><?php bch_page_demo_note(); ?></main><?php get_footer(); ?>
