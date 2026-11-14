<?php
defined('ABSPATH') || exit;

/** Missing fields use legacy content; intentionally cleared fields remain empty. */
function bch_field($name,$fallback='',$post_id=0) {
    $post_id=$post_id?:get_queried_object_id();
    if (!$post_id || !metadata_exists('post',$post_id,$name)) return $fallback;
    return function_exists('get_field')?get_field($name,$post_id):get_post_meta($post_id,$name,true);
}
function bch_home_field($name,$fallback='') { return bch_field('bch_home_'.$name,$fallback,(int)get_option('page_on_front')); }
function bch_page_content() {
    if (metadata_exists('post',get_the_ID(),'bch_page_body')) {
        // Sanitize stored HTML before WordPress renders blocks/shortcodes (e.g. CF7).
        $value=function_exists('get_field')?get_field('bch_page_body',get_the_ID(),false):get_post_meta(get_the_ID(),'bch_page_body',true);
        echo apply_filters('the_content',wp_kses_post($value));
    } else the_content();
}
function bch_acf_field($name,$label,$type='text',$extra=[]) {
    $field=['key'=>'field_'.$name,'name'=>$name,'label'=>$label,'type'=>$type];
    if ($type==='image') $field+=['return_format'=>'id','preview_size'=>'medium','library'=>'all'];
    if ($type==='textarea') $field+=['rows'=>3,'new_lines'=>''];
    if ($type==='wysiwyg') $field+=['tabs'=>'all','toolbar'=>'basic','media_upload'=>1];
    return array_merge($field,$extra);
}
function bch_acf_groups() {
    $f='bch_acf_field';
    $groups=[];
    $groups[]=['key'=>'group_bch_payment','title'=>'Bảo Châu — Tài khoản nhận cọc & mã QR','fields'=>[
        $f('bch_payment_bank','Tên ngân hàng'),
        $f('bch_payment_account','Số tài khoản','text',['instructions'=>'Nhập dạng chữ để giữ số 0 ở đầu.']),
        $f('bch_payment_holder','Tên chủ tài khoản'),
        $f('bch_payment_qr','Mã QR chuyển khoản','image',['instructions'=>'Tải ảnh QR đúng tài khoản ở trên. Khách nhập số tiền cọc và mã đặt phòng hiển thị trên đơn.','mime_types'=>'jpg,jpeg,png,webp']),
        $f('bch_payment_live','Mở nhận giữ chỗ','true_false',['ui'=>1,'default_value'=>0,'instructions'=>'Bật sau khi nhập đủ ngân hàng, số tài khoản và chủ tài khoản. Khách sạn xác nhận cọc thủ công.']),
    ],'location'=>[[['param'=>'page','operator'=>'==','value'=>(string)get_option('bch_booking_page')]]]];
    $common=[];
    foreach (['page-gioi-thieu.php','page-phong-nghi.php','page-tien-ich.php','page-lien-he.php'] as $template) $common[]=[['param'=>'page_template','operator'=>'==','value'=>$template]];
    $groups[]=['key'=>'group_bch_page','title'=>'Bảo Châu — Nội dung trang','fields'=>[
        $f('bch_page_eyebrow','Dòng nhãn phía trên tiêu đề'),
        $f('bch_page_title','Tiêu đề hiển thị','text',['instructions'=>'Để trống để dùng tên trang WordPress.']),
        $f('bch_page_banner','Ảnh đầu trang','image'),
        $f('bch_page_body','Nội dung chính','wysiwyg',['instructions'=>'Nội dung này được hiển thị trên trang thay cho ô soạn thảo mặc định.']),
    ],'location'=>$common,'hide_on_screen'=>['the_content']];
    $home=[
        $f('bch_home_tab_hero','Banner chính','tab'),
        $f('bch_home_eyebrow','Nhãn banner'),$f('bch_home_title','Tiêu đề banner','textarea'),
        $f('bch_home_text','Mô tả banner','textarea'),$f('bch_home_image','Ảnh banner','image'),
        $f('bch_home_cta_label','Chữ nút trên banner'),$f('bch_home_cta_url','Liên kết nút','url',['instructions'=>'Để trống để mở trang Phòng nghỉ.']),
        $f('bch_home_tab_intro','Giới thiệu','tab'),$f('bch_home_intro_label','Nhãn giới thiệu'),
        $f('bch_home_intro_title','Tiêu đề giới thiệu','textarea'),$f('bch_home_intro','Nội dung giới thiệu','textarea'),
        $f('bch_home_tab_rooms','Phòng nổi bật','tab'),$f('bch_home_rooms_label','Nhãn phòng nghỉ'),
        $f('bch_home_rooms_title','Tiêu đề danh sách phòng'),$f('bch_home_rooms_note','Ghi chú bên cạnh tiêu đề'),
        $f('bch_home_tab_services','Tiện ích','tab'),$f('bch_home_services_label','Nhãn tiện ích'),
        $f('bch_home_services_title','Tiêu đề tiện ích'),
        $f('bch_home_services','Danh sách tiện ích','textarea',['rows'=>6,'instructions'=>'Mỗi dòng: Tên tiện ích | Mô tả ngắn. Chỉ nhập tiện ích khách sạn thực tế cung cấp.']),
        $f('bch_home_tab_contact','Liên hệ cuối trang','tab'),$f('bch_home_contact_label','Nhãn liên hệ'),
        $f('bch_home_contact_title','Tiêu đề liên hệ','textarea'),$f('bch_home_contact','Thông tin liên hệ','textarea'),
        $f('bch_home_contact_photo','Ảnh khu vực liên hệ','image'),$f('bch_home_contact_cta','Chữ nút đặt phòng'),
    ];
    $groups[]=['key'=>'group_bch_home','title'=>'Bảo Châu — Thiết kế trang chủ','fields'=>$home,'location'=>[[['param'=>'page_type','operator'=>'==','value'=>'front_page']]],'hide_on_screen'=>['the_content']];
    $groups[]=['key'=>'group_bch_about','title'=>'Bảo Châu — Ảnh & nút giới thiệu','fields'=>[
        $f('bch_about_photo','Ảnh giới thiệu','image'),$f('bch_about_cta_label','Chữ nút'),$f('bch_about_cta_url','Liên kết nút','url',['instructions'=>'Để trống để mở trang Phòng nghỉ.']),
    ],'location'=>[[['param'=>'page_template','operator'=>'==','value'=>'page-gioi-thieu.php']]]];
    $services=[];
    for ($i=1;$i<=6;$i++) {
        $services[]=$f('bch_service_tab_'.$i,'Tiện ích '.$i,'tab');
        $services[]=$f('bch_service_'.$i.'_title','Tên tiện ích '.$i,'text',['instructions'=>'Để trống để ẩn thẻ tiện ích này.']);
        $services[]=$f('bch_service_'.$i.'_text','Mô tả','textarea');
        $services[]=$f('bch_service_'.$i.'_image','Ảnh tiện ích','image');
    }
    $groups[]=['key'=>'group_bch_services','title'=>'Bảo Châu — 6 thẻ tiện ích (ACF miễn phí)','fields'=>$services,'location'=>[[['param'=>'page_template','operator'=>'==','value'=>'page-tien-ich.php']]]];
    $groups[]=['key'=>'group_bch_contact','title'=>'Bảo Châu — Thông tin liên hệ','fields'=>[
        $f('bch_contact_address','Địa chỉ','textarea'),$f('bch_contact_phone','Điện thoại lễ tân','textarea',['instructions'=>'Mỗi số một dòng hoặc ngăn cách bằng dấu chấm phẩy.']),
        $f('bch_contact_email','Email','email'),$f('bch_contact_map','Liên kết Google Maps','url'),
        $f('bch_contact_side_title','Tiêu đề khung tư vấn'),$f('bch_contact_side_text','Nội dung tư vấn','textarea'),
        $f('bch_contact_button','Chữ nút đặt phòng'),
    ],'location'=>[[['param'=>'page_template','operator'=>'==','value'=>'page-lien-he.php']]]];
    foreach ($groups as &$group) $group+=['position'=>'normal','style'=>'default','active'=>true,'show_in_rest'=>0];
    return $groups;
}
add_action('acf/init',function () { foreach (bch_acf_groups() as $group) acf_add_local_field_group($group); });

/** One-time seed, never overwrite data saved by an editor. */
function bch_seed_acf_pages() {
    if (!function_exists('update_field')) return;
    $set=function ($id,$name,$value) { if ($id && !metadata_exists('post',$id,$name)) update_field('field_'.$name,$value,$id); };
    $home=(int)get_option('page_on_front');
    $values=[
        'eyebrow'=>'BẢO CHÂU HOTEL · CHÀO ĐÓN BẠN','title'=>str_replace('\\n',"\n",get_theme_mod('bch_hero_title','Một chốn an yên.\nMột kỳ nghỉ trọn vẹn.')),
        'text'=>get_theme_mod('bch_hero_text','Gác lại nhịp sống vội vã, tận hưởng những ngày thảnh thơi trong không gian ấm áp của Bảo Châu.'),
        'cta_label'=>'Khám phá phòng nghỉ ↗','intro_label'=>'CHẠM VÀO SỰ THẢNH THƠI','intro_title'=>"Nơi những điều giản dị\nlàm nên kỳ nghỉ đẹp.",
        'intro'=>get_theme_mod('bch_intro','Một căn phòng dễ chịu, một giấc ngủ ngon và những khoảnh khắc dành riêng cho bạn. Bảo Châu chào đón mỗi chuyến đi bằng sự gần gũi và chu đáo.'),
        'rooms_label'=>'KHÔNG GIAN CỦA RIÊNG BẠN','rooms_title'=>'Chọn một nơi để nghỉ ngơi.','rooms_note'=>'Ấm áp. Tinh tế. Vừa đủ.',
        'services_label'=>'TẬN HƯỞNG TỪNG KHOẢNH KHẮC','services_title'=>'Những tiện nghi cho ngày thảnh thơi.',
        'services'=>get_theme_mod('bch_services',"Bữa sáng | Khởi đầu ngày mới thật nhẹ nhàng.\nKhông gian xanh | Một góc nhỏ để chậm lại.\nWi-Fi | Giữ kết nối trong suốt kỳ nghỉ.\nLễ tân | Đồng hành cùng từng chuyến đi."),
        'contact_label'=>'HẸN GẶP BẠN TẠI BẢO CHÂU','contact_title'=>"Kỳ nghỉ tiếp theo,\nbắt đầu từ đây.",
        'contact'=>get_theme_mod('bch_contact','Địa chỉ, điện thoại và email sẽ được khách sạn cập nhật trước khi mở đặt phòng.'),
        'contact_photo'=>(int)get_theme_mod('bch_contact_photo'),'contact_cta'=>'Lên kế hoạch kỳ nghỉ ↗',
    ];
    $hero_url=get_theme_mod('bch_hero_image');
    if ($hero_url && ($image_id=attachment_url_to_postid($hero_url))) $values['image']=$image_id;
    foreach ($values as $name=>$value) $set($home,'bch_home_'.$name,$value);
    foreach (['gioi-thieu'=>'CÂU CHUYỆN BẢO CHÂU','phong-nghi'=>'KHÔNG GIAN NGHỈ NGƠI','tien-ich'=>'TẬN HƯỞNG KỲ NGHỈ','lien-he'=>'KẾT NỐI VỚI BẢO CHÂU'] as $slug=>$label) {
        $page=get_page_by_path($slug); if (!$page) continue;
        $set($page->ID,'bch_page_eyebrow',$label); $set($page->ID,'bch_page_body',$page->post_content);
        if ($slug==='gioi-thieu') { $set($page->ID,'bch_about_photo',(int)get_theme_mod('bch_contact_photo')); $set($page->ID,'bch_about_cta_label','Khám phá phòng nghỉ →'); }
        if ($slug==='lien-he') {
            $set($page->ID,'bch_contact_side_title','Bạn đã chọn được ngày nghỉ?');
            $set($page->ID,'bch_contact_side_text','Kiểm tra phòng trống và tổng tiền trước khi giữ chỗ. Khách sạn xác nhận sau khi đối chiếu tiền cọc.');
            $set($page->ID,'bch_contact_button','Đặt phòng →');
        }
    }
    update_option('bch_acf_pages_version','1');
}
add_action('admin_init',function () { if (current_user_can('manage_options') && function_exists('update_field') && get_option('bch_acf_pages_version')!=='1') bch_seed_acf_pages(); });
