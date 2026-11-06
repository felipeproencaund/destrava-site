/* Castelo: confirmação antes de excluir e menu no celular (sem script dentro do HTML, por causa do CSP). */
document.addEventListener('submit', function (e) {
  var msg = e.target.getAttribute('data-confirm');
  if (msg && !window.confirm(msg)) { e.preventDefault(); }
});
document.addEventListener('click', function (e) {
  var b = e.target.closest('[data-abre-menu]');
  if (b) { document.body.classList.toggle('menu-aberto'); return; }
  if (document.body.classList.contains('menu-aberto') && !e.target.closest('#castelo-lado')) {
    document.body.classList.remove('menu-aberto');
  }
});
document.querySelectorAll('[data-pct]').forEach(function (el) { el.style.width = el.getAttribute('data-pct') + '%'; });
