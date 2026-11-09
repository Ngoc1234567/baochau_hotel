<!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class(); ?>><?php wp_body_open(); ?>
<a class="skip" href="#main">Bỏ qua menu, đến nội dung</a>
<header class="site-header"><div class="wrap header-inner">
<?php if (has_custom_logo()): the_custom_logo(); else: ?><a class="brand" href="<?php echo esc_url(home_url('/')); ?>"><span class="brand-symbol" aria-hidden="true">B</span><span><strong>BẢO CHÂU</strong><small>HOTEL · VIETNAM</small></span></a><?php endif; ?>
<button class="menu-toggle" type="button" aria-label="Mở menu điều hướng" aria-expanded="false" aria-controls="site-nav">Menu ☰</button>
<nav class="nav" id="site-nav" aria-label="Menu chính">
<?php if (has_nav_menu('primary')): wp_nav_menu(['theme_location'=>'primary','container'=>false]); else: ?>
<?php foreach (['trang-chu'=>'Trang chủ','gioi-thieu'=>'Giới thiệu','phong-nghi'=>'Phòng nghỉ','tien-ich'=>'Tiện ích','lien-he'=>'Liên hệ'] as $slug=>$label): ?><a href="<?php echo esc_url(bch_page_url($slug)); ?>" <?php if (is_page($slug) || ($slug==='trang-chu' && is_front_page())) echo 'aria-current="page"'; ?>><?php echo esc_html($label); ?></a><?php endforeach; ?>
<?php endif; ?><a class="button" href="<?php echo esc_url(bch_theme_booking()); ?>">Đặt phòng ↗</a></nav></div></header>
