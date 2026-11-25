<?php
defined('ABSPATH') || exit;
$contact_page=get_page_by_path('lien-he');
$contact_id=$contact_page?$contact_page->ID:0;
$hotline=$contact_id?bch_field('bch_floating_hotline','0888.5999.68',$contact_id):'0888.5999.68';
$phone=preg_replace('/[^0-9+]/','',(string)$hotline);
$zalo_phone=preg_replace('/^84/','0',ltrim($phone,'+'));
$zalo_url=($contact_id?bch_field('bch_floating_zalo','',$contact_id):'')?:($zalo_phone?'https://zalo.me/'.$zalo_phone:'');
if (!$phone && !$zalo_url) return;
?>
<nav class="bch-floating-contact" aria-label="Liên hệ nhanh">
    <?php if ($zalo_url): ?>
    <a class="bch-floating-button bch-floating-zalo" href="<?php echo esc_url($zalo_url); ?>" aria-label="Nhắn Zalo cho khách sạn" title="Nhắn Zalo cho khách sạn" target="_blank" rel="noopener noreferrer">
        <span class="bch-floating-icon" aria-hidden="true"><span class="bch-floating-zalo-text">Zalo</span></span>
    </a>
    <?php endif; if ($phone): ?>
    <a class="bch-floating-button bch-floating-phone" href="<?php echo esc_attr('tel:'.$phone); ?>" aria-label="<?php echo esc_attr('Gọi Hotline '.$hotline); ?>" title="<?php echo esc_attr('Hotline: '.$hotline); ?>">
        <span class="bch-floating-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M7.1 2.9 4.6 4.1c-.9.4-1.5 1.4-1.4 2.4.6 7.5 6.8 13.7 14.3 14.3 1 .1 2-.5 2.4-1.4l1.2-2.5c.3-.6.1-1.4-.5-1.8l-3.4-2.2c-.6-.4-1.3-.3-1.8.2l-1.4 1.4a11.4 11.4 0 0 1-4.5-4.5l1.4-1.4c.5-.5.6-1.2.2-1.8L8.9 3.4c-.4-.6-1.2-.8-1.8-.5z"/></svg></span>
    </a>
    <?php endif; ?>
</nav>
