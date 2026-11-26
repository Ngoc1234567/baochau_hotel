const menuToggle = document.querySelector('.menu-toggle');
document.querySelectorAll('[data-room-gallery]').forEach(gallery => {
  const main = gallery.querySelector('[data-gallery-main]');
  const full = gallery.querySelector('.room-photo-large');
  const thumbnails = [...gallery.querySelectorAll('[data-gallery-thumb]')];
  thumbnails.forEach((thumb, index) => {
    thumb.addEventListener('click', event => {
      event.preventDefault();
      main.src = thumb.href;
      main.alt = thumb.querySelector('img').alt;
      full.href = thumb.href;
      thumbnails.forEach(item => item.setAttribute('aria-current', String(item === thumb)));
      gallery.querySelector('.room-gallery-status').textContent = `Ảnh ${index + 1} / ${thumbnails.length}`;
    });
  });
});
const navigation = document.querySelector('#site-nav');
function setMenuOpen(open) {
  menuToggle?.setAttribute('aria-expanded', String(open));
  menuToggle?.setAttribute('aria-label', open ? 'Đóng menu điều hướng' : 'Mở menu điều hướng');
  if (menuToggle) menuToggle.textContent = open ? 'Đóng ×' : 'Menu ☰';
  navigation?.classList.toggle('open', open);
}
menuToggle?.addEventListener('click', () => {
  setMenuOpen(menuToggle.getAttribute('aria-expanded') !== 'true');
});
navigation?.addEventListener('click', event => {
  if (event.target.closest('a')) setMenuOpen(false);
});
document.addEventListener('keydown', event => {
  if (event.key === 'Escape' && menuToggle?.getAttribute('aria-expanded') === 'true') {
    setMenuOpen(false);
    menuToggle.focus();
  }
});
document.addEventListener('click', event => {
  if (!event.target.closest('.site-header')) setMenuOpen(false);
});
window.matchMedia('(max-width:980px)').addEventListener('change', () => setMenuOpen(false));
document.querySelectorAll('.booking-search, .reservation-form').forEach(form => {
  const checkin = form.querySelector('[name=in]');
  const checkout = form.querySelector('[name=out]');
  const update = () => {
    if (!checkin.value) return;
    const next = new Date(checkin.value + 'T12:00:00');
    next.setDate(next.getDate() + 1);
    const day = `${next.getFullYear()}-${String(next.getMonth()+1).padStart(2,'0')}-${String(next.getDate()).padStart(2,'0')}`;
    checkout.min = day;
    if (!checkout.value || checkout.value <= checkin.value) checkout.value = day;
  };
  checkin.addEventListener('change', update);
  update();
});

// Reveal homepage content once, with a short stagger within each group.
(() => {
  const scope = document.querySelector('.home-main');
  if (!scope) return;
  const preference = window.matchMedia('(prefers-reduced-motion: reduce)');
  scope.classList.add('home-motion');
  if (preference.matches || !('IntersectionObserver' in window)) return;

  const targets = [...scope.querySelectorAll('.home-search-band h2, .booking-search, .intro > div, .section-head, .inventory-summary, .room-card, .services > .wrap > .eyebrow, .services > .wrap > h2, .service, .hotel-photo, .contact-art, .contact-copy')];
  const reveal = target => {
    target.classList.add('is-revealed');
    observer.unobserve(target);
  };
  const observer = new IntersectionObserver(entries => {
    entries.forEach(({ target, isIntersecting }) => {
      if (isIntersecting) reveal(target);
    });
  }, { threshold: 0.08 });
  targets.forEach(target => {
    const siblings = [...target.parentElement.children];
    target.style.setProperty('--reveal-delay', `${(siblings.indexOf(target) % 4) * 70}ms`);
    target.classList.add('home-reveal');
    observer.observe(target);
  });
  scope.addEventListener('focusin', event => {
    const target = event.target.closest('.home-reveal');
    if (target) reveal(target);
  });
  preference.addEventListener('change', ({ matches }) => {
    if (matches) {
      targets.forEach(reveal);
      observer.disconnect();
    }
  });
})();

// A native modal keeps keyboard focus inside the enlarged payment QR.
(() => {
  const links = document.querySelectorAll('[data-qr-zoom]');
  if (!links.length || typeof HTMLDialogElement === 'undefined' ||
      typeof HTMLDialogElement.prototype.showModal !== 'function') return;
  const dialog = document.createElement('dialog');
  dialog.className = 'qr-zoom-dialog';
  dialog.setAttribute('aria-labelledby', 'qr-zoom-title');
  dialog.innerHTML = '<div class="qr-zoom-header"><h2 id="qr-zoom-title">Mã QR chuyển khoản</h2><button type="button" aria-label="Đóng ảnh QR" autofocus>Đóng ×</button></div><img class="qr-zoom-image" alt=""><p>Bấm giữ hoặc mở ảnh gốc để lưu mã QR.</p><a target="_blank" rel="noopener">Mở ảnh gốc</a>';
  document.body.append(dialog);
  const preview = dialog.querySelector('img');
  const original = dialog.querySelector('a');
  let trigger;
  links.forEach(link => link.addEventListener('click', event => {
    if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    trigger = link;
    preview.src = link.href;
    preview.alt = link.querySelector('img').alt;
    original.href = link.href;
    dialog.showModal();
    document.documentElement.classList.add('qr-zoom-open');
  }));
  dialog.querySelector('button').addEventListener('click', () => dialog.close());
  dialog.addEventListener('click', event => {
    if (event.target !== dialog) return;
    const rect = dialog.getBoundingClientRect();
    if (event.clientX < rect.left || event.clientX > rect.right ||
        event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
  });
  dialog.addEventListener('close', () => {
    document.documentElement.classList.remove('qr-zoom-open');
    trigger?.focus({ preventScroll: true });
  });
})();


// Hotel photographs open as one keyboard- and touch-accessible collection.
(() => {
  const gallery = document.querySelector('[data-hotel-gallery]');
  if (!gallery || typeof HTMLDialogElement === 'undefined' ||
      typeof HTMLDialogElement.prototype.showModal !== 'function') return;
  const photos = [...gallery.querySelectorAll('[data-hotel-photo]')];
  if (!photos.length) return;
  const modal = document.createElement('dialog');
  modal.className = 'hotel-gallery-dialog';
  modal.setAttribute('aria-label', 'Hình ảnh khách sạn');
  modal.innerHTML = '<div class="hotel-gallery-toolbar"><span aria-live="polite" data-photo-count></span><button type="button" data-photo-close autofocus>Đóng ×</button></div><div class="hotel-gallery-stage"><button type="button" data-photo-prev aria-label="Ảnh trước">‹</button><img alt=""><button type="button" data-photo-next aria-label="Ảnh tiếp theo">›</button></div><p class="hotel-gallery-caption"></p>';
  document.body.append(modal);
  const image = modal.querySelector('img');
  const caption = modal.querySelector('.hotel-gallery-caption');
  const count = modal.querySelector('[data-photo-count]');
  let current = 0;
  let trigger;
  const show = index => {
    current = (index + photos.length) % photos.length;
    image.src = photos[current].href;
    image.alt = photos[current].querySelector('img').alt;
    caption.textContent = photos[current].dataset.caption || '';
    caption.hidden = !caption.textContent;
    count.textContent = `Ảnh ${current + 1} / ${photos.length}`;
  };
  photos.forEach((photo, index) => photo.addEventListener('click', event => {
    if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    trigger = photo;
    show(index);
    modal.showModal();
    document.documentElement.classList.add('hotel-gallery-open');
  }));
  modal.querySelector('[data-photo-close]').addEventListener('click', () => modal.close());
  const previous = modal.querySelector('[data-photo-prev]');
  const next = modal.querySelector('[data-photo-next]');
  previous.hidden = next.hidden = photos.length < 2;
  previous.addEventListener('click', () => show(current - 1));
  next.addEventListener('click', () => show(current + 1));
  modal.addEventListener('keydown', event => {
    if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
      event.preventDefault();
      show(current + (event.key === 'ArrowLeft' ? -1 : 1));
    }
  });
  let touch;
  image.addEventListener('touchstart', event => {
    touch = event.touches.length === 1 ? { x: event.touches[0].clientX, y: event.touches[0].clientY } : null;
  }, { passive: true });
  image.addEventListener('touchend', event => {
    if (!touch || event.touches.length || event.changedTouches.length !== 1) { touch = null; return; }
    const dx = event.changedTouches[0].clientX - touch.x;
    const dy = event.changedTouches[0].clientY - touch.y;
    if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) show(current + (dx < 0 ? 1 : -1));
    touch = null;
  }, { passive: true });
  image.addEventListener('touchcancel', () => { touch = null; }, { passive: true });
  modal.addEventListener('click', event => {
    if (event.target !== modal) return;
    const bounds = modal.getBoundingClientRect();
    if (event.clientX < bounds.left || event.clientX > bounds.right ||
        event.clientY < bounds.top || event.clientY > bounds.bottom) modal.close();
  });
  modal.addEventListener('close', () => {
    document.documentElement.classList.remove('hotel-gallery-open');
    trigger?.focus({ preventScroll: true });
  });
})();
