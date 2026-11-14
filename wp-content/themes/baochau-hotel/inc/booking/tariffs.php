<?php
defined('ABSPATH') || exit;

/** Hotel-supplied tariff. Inventory and room details must be supplied separately. */
function bch_tariffs() {
    return [
        ['name'=>'Phòng 1 giường đơn', 'bed'=>'1 giường đơn', 'capacity'=>1, 'price'=>300000, 'hourly'=>150000],
        ['name'=>'Phòng 1 giường đôi', 'bed'=>'1 giường đôi', 'capacity'=>2, 'price'=>400000, 'hourly'=>200000],
        ['name'=>'Phòng 2 giường đơn', 'bed'=>'2 giường đơn', 'capacity'=>2, 'price'=>500000, 'hourly'=>200000],
        ['name'=>'Phòng 1 giường đôi + 1 giường đơn', 'bed'=>'1 giường đôi + 1 giường đơn', 'capacity'=>3, 'price'=>600000, 'hourly'=>230000],
        ['name'=>'Phòng 2 giường đôi', 'bed'=>'2 giường đôi', 'capacity'=>4, 'price'=>700000, 'hourly'=>250000],
    ];
}

function bch_stay_note() {
    return 'Nghỉ ngày đêm: nhận phòng từ 13:00, trả phòng trước hoặc đúng 12:00 ngày trả. Giá tính cho 1 phòng mỗi đêm, trong sức chứa quy định.';
}

function bch_tariff_panel() {
    $nights=max(1,min(30,absint($_GET['estimate_nights']??1)));
    $rooms=bch_rooms();
    ?>
    <section class="hotel-tariffs" id="bang-gia" aria-labelledby="tariff-title">
        <p class="eyebrow">KHÁCH SẠN BẢO CHÂU</p>
        <h2 id="tariff-title">Bảng giá phòng</h2>
        <p><?php echo esc_html(bch_stay_note()); ?></p>
        <form method="get" action="#bang-gia" class="tariff-estimate">
            <?php if (!get_option('permalink_structure')): ?><input type="hidden" name="page_id" value="<?php echo esc_attr(get_queried_object_id()); ?>"><?php endif; ?>
            <label>Tính thử số đêm <input type="number" name="estimate_nights" min="1" max="30" value="<?php echo esc_attr($nights); ?>" required></label>
            <button type="submit">Xem tiền phòng</button>
        </form>
        <div class="tariff-scroll" role="region" aria-label="Giá nghỉ ngày đêm" tabindex="0">
            <table class="tariff-table">
                <caption>Giá cho 1 phòng · VNĐ</caption>
                <thead><tr><th scope="col">Loại phòng</th><th scope="col">Số người tối đa</th><th scope="col">Ngày đêm</th><th scope="col">Tạm tính <?php echo (int)$nights; ?> đêm</th></tr></thead>
                <tbody><?php foreach (bch_tariffs() as $tariff):
                    if (!array_filter($rooms,fn($room)=>$room->name===$tariff['name'])) continue;
                    $price=$tariff['price']; $hourly=$tariff['hourly'];
                    foreach ($rooms as $room) if ($room->name===$tariff['name']) { $price=(int)$room->price; $hourly=(int)$room->hourly_price; break; }
                    ?><tr><th scope="row"><?php echo esc_html($tariff['name']); ?></th><td><?php echo (int)$tariff['capacity']; ?></td><td><?php echo esc_html(bch_money($price)); ?></td><td><strong><?php echo esc_html(bch_money($price*$nights)); ?></strong></td></tr>
                <?php endforeach; ?></tbody>
            </table>
        </div>
        <p class="tariff-footnote">Tạm tính theo giá cơ bản, chưa giữ phòng. Giá theo ngày cụ thể và phòng trống được kiểm tra khi đặt ngày đêm. Khoản cọc hiện tại là 30% tổng tiền phòng.</p>
        <p><a class="button" href="#thong-tin-dat-phong">Đặt phòng qua đêm ↓</a></p>
        <section class="hourly-tariffs" id="nghi-theo-gio" aria-labelledby="hourly-title">
            <h3 id="hourly-title">Nghỉ theo giờ</h3>
            <div class="tariff-scroll" role="region" aria-label="Bảng giá nghỉ theo giờ" tabindex="0">
                <table class="tariff-table">
                    <caption>Giá 2 giờ đầu cho 1 phòng · VNĐ</caption>
                    <thead><tr><th scope="col">Loại phòng</th><th scope="col">Giá 2 giờ đầu</th><th scope="col">Thời gian</th></tr></thead>
                    <tbody><?php foreach ($rooms as $room): ?>
                        <tr><th scope="row"><?php echo esc_html($room->name); ?></th><td><strong><?php echo esc_html((int)$room->hourly_price>0?bch_money($room->hourly_price):'Liên hệ'); ?></strong></td><td>02 giờ</td></tr>
                    <?php endforeach; ?></tbody>
                </table>
            </div>
            <div class="hourly-contact"><div><p>Giá áp dụng cho 2 giờ đầu. Sau đó thu thêm <strong>30.000đ/giờ</strong>, giờ lẻ làm tròn lên giờ tiếp theo. Vui lòng liên hệ lễ tân để kiểm tra phòng và giờ nhận/trả. Website hiện chỉ nhận đặt ngày đêm.</p></div><a class="button" href="<?php echo esc_url(bch_page_url('lien-he')); ?>">Liên hệ đặt phòng theo giờ →</a></div>
        </section>
        <div class="tariff-contact-note">
            <p><em>* Giá phòng trên chưa có VAT; Quý khách có nhu cầu lấy hóa đơn GTGT, xin vui lòng báo cho Lễ tân khi làm thủ tục nhận phòng.</em></p>
            <p><strong>Địa chỉ:</strong> Số nhà 31, tổ dân phố Trần Can, phường Điện Biên Phủ, tỉnh Điện Biên.</p>
            <p><strong>Điện thoại lễ tân:</strong> <a href="tel:02153828963">02153.828.963</a>; <a href="tel:0888599968">0888.5999.68</a>; <a href="tel:0912565671">0912.56.56.71</a>.</p>
            <p><em>Trân trọng cảm ơn quý khách!</em></p>
        </div>
    </section>
    <?php
}
