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
