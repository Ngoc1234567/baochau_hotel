<?php
defined('ABSPATH') || exit;

/** The amenities editor stores the walking destinations as a service card. */
function bch_amenities_walk_card($post_id) {
    for ($i=1;$i<=6;$i++) {
        $title=(string)bch_field('bch_service_'.$i.'_title','',$post_id);
        if (sanitize_title($title)!=='dao-bo-kham-pha-pho-thi') continue;
        $items=array_filter(array_map('trim',preg_split('/\R/u',(string)bch_field('bch_service_'.$i.'_text','',$post_id))));
        $html='<h2>'.esc_html($title).'</h2>';
        if ($image=absint(bch_field('bch_service_'.$i.'_image','',$post_id))) {
            $html.=wp_get_attachment_image($image,'large',false,['loading'=>'lazy']);
        }
        if ($items) {
            $html.='<ul>';
            foreach ($items as $item) $html.='<li>'.esc_html($item).'</li>';
            $html.='</ul>';
        }
        return $html;
    }
    return '';
}

/** Split the editor section without changing the saved page content. */
function bch_split_amenities_walk($html) {
    preg_match_all('/<h([12])\b[^>]*>.*?<\/h\1\s*>/is',$html,$headings,PREG_OFFSET_CAPTURE);
    foreach ($headings[0] as $index=>$heading) {
        $title=html_entity_decode(wp_strip_all_tags($heading[0]),ENT_QUOTES,'UTF-8');
        $title=preg_replace('/[\s\x{00a0}]+/u',' ',trim($title));
        if (sanitize_title($title)!=='dao-bo-kham-pha-pho-thi') continue;
        $start=$heading[1];
        $end=$headings[0][$index+1][1]??strlen($html);
        return [substr($html,0,$start).substr($html,$end),substr($html,$start,$end-$start)];
    }
    return [$html,''];
}

/** Use the same item order for the editor's image controls and the public list. */
function bch_amenities_walk_items($html) {
    preg_match_all('/<li\b[^>]*>(.*?)<\/li\s*>/is',$html,$items);
    return $items[1];
}

function bch_amenities_walk_images($html,$post_id) {
    $index=0;
    return preg_replace_callback('/<li\b([^>]*)>(.*?)<\/li\s*>/is',function ($match) use (&$index,$post_id) {
        $image=absint(bch_field('bch_walk_image_'.++$index,'',$post_id));
        $photo=$image?wp_get_attachment_image($image,'large',false,[
            'class'=>'amenities-walk-image','loading'=>'lazy',
            'sizes'=>'(max-width: 420px) 38vw, (max-width: 680px) 180px, (max-width: 980px) 200px, 220px',
        ]):'';
        // Existing inline photos stay attached to their item until replaced in the controls.
        $content=$photo?preg_replace('/<img\b[^>]*>/is','',$match[2]):$match[2];
        if (!preg_match('/<[a-z]/i',$content) && preg_match('/^(\d+)\s+phút\s+đến\s+(.+?)[.!]?\s*$/iu',trim(html_entity_decode($content,ENT_QUOTES,'UTF-8')),$destination)) {
            $content='<p class="amenities-walk-destination">'.esc_html($destination[2]).'</p><span class="amenities-walk-time"><strong>'.esc_html($destination[1]).'</strong><span>phút đi bộ</span></span>';
        }
        return '<li'.$match[1].'><div class="amenities-walk-copy">'.$content.'</div>'.$photo.'</li>';
    },$html);
}
