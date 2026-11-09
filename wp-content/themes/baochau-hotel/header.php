<!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class(); ?>><?php wp_body_open(); ?>
<a class="skip" href="#main">Bỏ qua menu, đến nội dung</a>
<header class="site-header"><div class="wrap header-inner">
<?php if (has_custom_logo()): the_custom_logo(); else: ?><a class="brand" href="<?php echo esc_url(home_url('/')); ?>"><span class="brand-symbol" aria-hidden="true">B</span><span><strong>BẢO CHÂU</strong><small>HOTEL · VIETNAM</small></span></a><?php endif; ?>
<button class="menu-toggle" aria-expanded="false" aria-controls="site-nav">Menu ☰</button>
<nav class="nav" id="site-nav" aria-label="Menu chính">
<?php if (has_nav_menu('primary')): wp_nav_menu(['theme_location'=>'primary','container'=>false]); else: ?>
<a href="<?php echo esc_url(home_url('/#gioi-thieu')); ?>">Câu chuyện</a><a href="<?php echo esc_url(home_url('/#phong')); ?>">Phòng nghỉ</a><a href="<?php echo esc_url(home_url('/#tien-ich')); ?>">Tiện ích</a><a href="<?php echo esc_url(home_url('/#lien-he')); ?>">Liên hệ</a>
<?php endif; ?><a class="button" href="<?php echo esc_url(bch_theme_booking()); ?>">Đặt phòng ↗</a></nav></div></header>
