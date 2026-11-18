/* global wp */
(() => {
    const panel = document.querySelector('[data-payment-qr]');
    if (!panel || !window.wp?.media) return;
    const input = panel.querySelector('[name=qr_id]');
    const preview = panel.querySelector('[data-qr-preview]');
    const remove = panel.querySelector('[data-qr-remove]');
    const status = panel.querySelector('[data-qr-status]');
    status.setAttribute('aria-live', 'polite');
    let frame;
    panel.querySelector('[data-qr-select]').addEventListener('click', () => {
        if (!frame) {
            frame = wp.media({title: 'Chọn mã QR nhận tiền cọc', button: {text: 'Sử dụng mã QR này'}, library: {type: 'image'}, multiple: false});
            frame.on('open', () => {
                const selection = frame.state().get('selection');
                selection.reset();
                if (input.value && input.value !== '0') selection.add(wp.media.attachment(input.value));
            });
            frame.on('select', () => {
                const image = frame.state().get('selection').first()?.toJSON();
                if (!image || image.type !== 'image' || !image.url) return;
                input.value = image.id;
                preview.src = image.url;
                preview.hidden = remove.hidden = false;
                status.textContent = 'Đã chọn mã QR. Bấm Lưu thay đổi để áp dụng.';
            });
        }
        frame.open();
    });
    remove.addEventListener('click', () => {
        input.value = '0';
        preview.removeAttribute('src');
        preview.hidden = remove.hidden = true;
        status.textContent = 'Đã gỡ mã QR. Bấm Lưu thay đổi để áp dụng.';
        panel.querySelector('[data-qr-select]').focus();
    });
})();
