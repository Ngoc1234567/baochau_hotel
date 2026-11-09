<?php
defined('ABSPATH') || exit;
function bch_page_url($slug) {
    $page=get_page_by_path($slug);
    return $page?get_permalink($page):home_url('/'.$slug.'/');
}
function bch_create_site_pages() {
    $intro=get_theme_mod('bch_intro','Bảo Châu chào đón mỗi chuyến đi bằng sự gần gũi và chu đáo. Một căn phòng dễ chịu, một giấc ngủ ngon và những khoảnh khắc dành riêng cho bạn.');
    $contact=get_theme_mod('bch_contact','Địa chỉ, điện thoại và email sẽ được khách sạn cập nhật trước khi mở đặt phòng.');
    $services='';
    foreach (explode("\n",get_theme_mod('bch_services',"Bữa sáng | Khởi đầu ngày mới thật nhẹ nhàng.\nKhông gian xanh | Một góc nhỏ để chậm lại.\nWi-Fi | Giữ kết nối trong suốt kỳ nghỉ.\nLễ tân | Đồng hành cùng từng chuyến đi.")) as $line) {
        $item=explode('|',$line,2); $services.='<h2>'.esc_html(trim($item[0])).'</h2><p>'.esc_html(trim($item[1]??'')).'</p>';
    }
    $definitions=[
        'trang-chu'=>['Trang chủ','', 'default'],
        'gioi-thieu'=>['Giới thiệu','<h2>Một chốn an yên, một kỳ nghỉ trọn vẹn.</h2><p>'.nl2br(esc_html($intro)).'</p>','page-gioi-thieu.php'],
        'phong-nghi'=>['Phòng nghỉ','<p>Khám phá các hạng phòng và chọn không gian phù hợp với kỳ nghỉ của bạn. Chọn ngày lưu trú để xem giá và phòng trống.</p>','page-phong-nghi.php'],
        'tien-ich'=>['Tiện ích',$services,'page-tien-ich.php'],
        'lien-he'=>['Liên hệ','<h2>Liên hệ với Bảo Châu</h2><p>'.nl2br(esc_html($contact)).'</p>','page-lien-he.php'],
    ];
    foreach ($definitions as $slug=>$definition) {
        $page=get_page_by_path($slug);
        $id=$page?$page->ID:wp_insert_post(['post_title'=>$definition[0],'post_name'=>$slug,'post_content'=>$definition[1],'post_status'=>'publish','post_type'=>'page'],true);
        if (is_wp_error($id)) throw new RuntimeException($id->get_error_message());
        update_post_meta($id,'_wp_page_template',$definition[2]);
        if ($slug==='trang-chu') { update_option('show_on_front','page'); update_option('page_on_front',$id); }
    }
    foreach (['bch_booking_page'=>'page-dat-phong.php','bch_policy_page'=>'page-chinh-sach.php'] as $option=>$template) {
        if ($id=(int)get_option($option)) {
            update_post_meta($id,'_wp_page_template',$template);
            if (in_array(trim(get_post_field('post_content',$id)),['[baochau_booking]','[baochau_policy]'],true)) wp_update_post(['ID'=>$id,'post_content'=>'']);
        }
    }
    update_option('bch_site_pages_version','1');
}
add_action('after_switch_theme','bch_create_site_pages');
add_action('admin_init',function () { if (current_user_can('manage_options') && get_option('bch_site_pages_version')!=='1') bch_create_site_pages(); });
function bch_page_heading($eyebrow) {
    $eyebrow=bch_field('bch_page_eyebrow',$eyebrow);
    $title=bch_field('bch_page_title')?:get_the_title();
    echo '<header class="content-page-heading"><p class="eyebrow">'.esc_html($eyebrow).'</p><h1>'.esc_html($title).'</h1></header>';
    if ($banner=absint(bch_field('bch_page_banner'))) echo wp_get_attachment_image($banner,'large',false,['class'=>'acf-page-banner','loading'=>'eager']);
}
function bch_page_demo_note() {
    if (get_theme_mod('bch_demo',true)) echo '<p class="notice">Nội dung demo — thông tin và tiện ích cần được khách sạn cập nhật trước khi vận hành.</p>';
}
