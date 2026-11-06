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
/* Financeiro: mostra só as categorias da carteira e do tipo escolhidos */
document.querySelectorAll('[data-fin-form]').forEach(function (form) {
  var cart = form.querySelector('[data-fin-carteira]'), cat = form.querySelector('[data-fin-categoria]');
  if (!cart || !cat) return;
  var opts = Array.prototype.slice.call(cat.options);
  function filtra() {
    var tipo = (form.querySelector('input[name=tipo]:checked') || {}).value || 'saida';
    var primeira = null;
    opts.forEach(function (o) {
      var ok = o.getAttribute('data-carteira') === cart.value && o.getAttribute('data-tipo') === tipo;
      o.hidden = !ok; o.disabled = !ok;
      if (ok && !primeira) primeira = o;
    });
    if (!cat.selectedOptions.length || cat.selectedOptions[0].disabled) { cat.value = primeira ? primeira.value : ''; }
  }
  cart.addEventListener('change', filtra);
  form.querySelectorAll('input[name=tipo]').forEach(function (r) { r.addEventListener('change', filtra); });
  filtra();
});
/* Financeiro: anexar comprovante envia sozinho ao escolher o arquivo */
document.querySelectorAll('[data-envia-sozinho]').forEach(function (i) {
  i.addEventListener('change', function () { if (i.files.length) i.form.submit(); });
});
document.querySelectorAll('[data-envia-sozinho-select]').forEach(function (s) {
  s.addEventListener('change', function () { s.form.submit(); });
});
