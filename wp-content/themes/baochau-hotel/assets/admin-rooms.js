/* global wp */
(() => {
    'use strict';
    const input = document.querySelector('textarea[name="images"]');
    if (!input || !window.wp || !wp.media) return;
    let urls = [...new Set(input.value.split(/\r?\n/).map(s => s.trim()).filter(Boolean))];
    const original = input.closest('p');
    const editor = document.createElement('section');
    editor.className = 'bch-image-editor';
    editor.setAttribute('aria-label', 'Ảnh hạng phòng');
    editor.innerHTML = '<h3>Ảnh hạng phòng</h3><p>Nên chọn 3–5 ảnh, tối đa 5 ảnh. Ảnh đầu tiên là ảnh đại diện.</p><div class="bch-image-list"></div><button type="button" class="button button-secondary bch-add-images">Chọn / tải ảnh lên</button><p class="bch-image-status" role="status" aria-live="polite"></p>';
    original.before(editor);
    original.hidden = true;
    const list = editor.querySelector('.bch-image-list');
    const add = editor.querySelector('.bch-add-images');
    const status = editor.querySelector('.bch-image-status');
    function render(focusIndex) {
        input.value = urls.join('\n');
        list.replaceChildren();
        urls.forEach((url, i) => {
            const card = document.createElement('div');
            card.className = 'bch-image-card';
            const img = document.createElement('img');
            img.src = url;
            img.alt = `Ảnh phòng ${i + 1}`;
            const label = document.createElement('strong');
            label.textContent = i === 0 ? '1 · Ảnh đại diện' : `Ảnh ${i + 1}`;
            card.append(img, label);
            const actions = document.createElement('div');
            function button(text, disabled, action) {
                const b = document.createElement('button');
                b.type = 'button'; b.className = 'button'; b.textContent = text;
                b.disabled = disabled;
                b.setAttribute('aria-label', `${text} ảnh ${i + 1}`);
                b.addEventListener('click', action); actions.append(b);
            }
            button('←', i === 0, () => {
                [urls[i - 1], urls[i]] = [urls[i], urls[i - 1]];
                render(i - 1);
            });
            button('→', i === urls.length - 1, () => {
                [urls[i + 1], urls[i]] = [urls[i], urls[i + 1]];
                render(i + 1);
            });
            button('Gỡ', false, () => { urls.splice(i, 1); render(Math.min(i, urls.length - 1)); });
            card.append(actions); list.append(card);
        });
        add.disabled = urls.length >= 5;
        status.textContent = `Đã chọn ${urls.length}/5 ảnh. Bấm Lưu thay đổi để lưu ảnh của phòng.`;
        if (focusIndex !== undefined) {
            const target = list.children[focusIndex];
            (target ? target.querySelector('button:not(:disabled)') : add).focus();
        }
    }
    let frame;
    add.addEventListener('click', () => {
        if (!frame) {
            frame = wp.media({title: 'Chọn ảnh hạng phòng (tối đa 5 ảnh)', button: {text: 'Thêm ảnh vào phòng'}, library: {type: 'image'}, multiple: true});
            frame.on('open', () => frame.state().get('selection').reset());
            frame.on('select', () => {
                const selected = frame.state().get('selection').toJSON().filter(item => item.type === 'image' && item.url).map(item => item.url);
                const combined = [...new Set([...urls, ...selected])];
                if (combined.length > 5) {
                    status.textContent = `Bạn đã chọn quá 5 ảnh. Hãy chọn thêm tối đa ${Math.max(0, 5 - urls.length)} ảnh hoặc gỡ ảnh cũ.`;
                    add.focus(); return;
                }
                urls = combined; render();
                (add.disabled ? list.querySelector('button:not(:disabled)') : add).focus();
            });
        }
        frame.open();
    });
    render();
})();
