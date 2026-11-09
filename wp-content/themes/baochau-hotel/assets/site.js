document.querySelector('.menu-toggle')?.addEventListener('click', function () {
  const open = this.getAttribute('aria-expanded') !== 'true';
  this.setAttribute('aria-expanded', String(open));
  document.querySelector('#site-nav')?.classList.toggle('open', open);
});
document.querySelectorAll('.booking-search').forEach(form => {
  const checkin = form.querySelector('[name=in]');
  const checkout = form.querySelector('[name=out]');
  const update = () => {
    if (!checkin.value) return;
    const next = new Date(checkin.value + 'T12:00:00');
    next.setDate(next.getDate() + 1);
    const day = `${next.getFullYear()}-${String(next.getMonth()+1).padStart(2,'0')}-${String(next.getDate()).padStart(2,'0')}`;
    checkout.min = day;
    if (checkout.value && checkout.value <= checkin.value) checkout.value = day;
  };
  checkin.addEventListener('change', update);
  update();
});
