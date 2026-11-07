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
/* Painel da reunião: tela cheia (esconde o menu e pede tela cheia ao navegador) */
document.querySelectorAll('[data-tela-cheia]').forEach(function (b) {
  b.addEventListener('click', function () {
    var on = document.body.classList.toggle('pn-cheia');
    b.textContent = on ? 'Sair da tela cheia' : 'Tela cheia';
    try {
      if (on && document.documentElement.requestFullscreen) { document.documentElement.requestFullscreen(); }
      else if (!on && document.fullscreenElement) { document.exitFullscreen(); }
    } catch (e) {}
  });
});
/* Mensagens: ao abrir o WhatsApp, registra o envio sem sair da página */
document.addEventListener('click', function (e) {
  var a = e.target.closest('[data-msg-envio]');
  if (!a) return;
  var f = new FormData();
  f.append('acao', 'enviado'); f.append('cliente', a.getAttribute('data-cliente'));
  f.append('seg', a.getAttribute('data-seg')); f.append('modelo', a.getAttribute('data-modelo'));
  f.append('csrf_token', a.getAttribute('data-csrf'));
  try { navigator.sendBeacon('mensagens.php', f); } catch (err) {}
  var item = a.closest('.msg-item');
  if (item) { item.classList.add('is-enviado'); }
  a.textContent = 'Aberto ✓ (marcado como enviado)';
  var c = document.querySelector('[data-msg-hoje]');
  if (c) { c.textContent = String(parseInt(c.textContent, 10) + 1); }
});
/* Botão que precisa de confirmação dentro de um formulário */
document.addEventListener('click', function (e) {
  var b = e.target.closest('[data-confirm-click]');
  if (b && !window.confirm(b.getAttribute('data-confirm-click'))) { e.preventDefault(); }
});
/* Central: relógio */
(function () {
  var r = document.querySelector('[data-relogio]');
  if (!r) return;
  function tic() { var d = new Date(); r.textContent = d.toLocaleTimeString('pt-BR'); }
  tic(); setInterval(tic, 1000);
})();
/* Central: cérebro do negócio (esfera 3D girando com categorias, produtos e o que sai junto) */
(function () {
  var cv = document.querySelector('[data-cerebro]');
  if (!cv || !cv.getContext) return;
  var dados;
  try { dados = JSON.parse(cv.getAttribute('data-cerebro')); } catch (e) { return; }
  var ctx = cv.getContext('2d'), W = 0, H = 0, R = 0, dpr = Math.min(window.devicePixelRatio || 1, 2);
  var nos = [], idx = {}, links = [], poeira = [], ang = 0, mouse = null;
  var maxC = Math.max.apply(null, dados.cats.map(function (c) { return c.v; }).concat([1]));
  var maxP = Math.max.apply(null, dados.prods.map(function (p) { return p.v; }).concat([1]));
  var nc = dados.cats.length;
  // categorias distribuídas na esfera (espiral de Fibonacci)
  dados.cats.forEach(function (c, i) {
    var y = 1 - (i + 0.5) / nc * 2, rr = Math.sqrt(1 - y * y), t = i * 2.39996;
    var n = { nome: c.n, v: c.v, x: Math.cos(t) * rr * 0.62, y: y * 0.62, z: Math.sin(t) * rr * 0.62, tam: 5 + 13 * Math.sqrt(c.v / maxC), cat: true };
    nos.push(n); idx['c:' + c.n] = n;
  });
  // produtos ao redor da sua categoria
  dados.prods.forEach(function (p, i) {
    var c = idx['c:' + p.c] || nos[0], a = i * 2.1, b = i * 1.3, d = 0.32 + (i % 3) * 0.06;
    var x = c.x + Math.cos(a) * Math.sin(b) * d, y = c.y + Math.cos(b) * d * 0.8, z = c.z + Math.sin(a) * Math.sin(b) * d;
    var m = Math.sqrt(x * x + y * y + z * z) || 1, k = Math.min(1, 0.98 / m);
    var n = { nome: p.n, v: p.v, x: x * k, y: y * k, z: z * k, tam: 2 + 6 * Math.sqrt(p.v / maxP), cat: false, pai: c };
    nos.push(n); idx['p:' + p.n] = n;
  });
  var maxL = 1;
  dados.links.forEach(function (l) { maxL = Math.max(maxL, l[2]); });
  dados.links.forEach(function (l) {
    var a = idx['p:' + l[0]], b = idx['p:' + l[1]];
    if (a && b) links.push({ a: a, b: b, f: l[2] / maxL, t: Math.random() });
  });
  for (var i = 0; i < 260; i++) {
    var y = Math.random() * 2 - 1, rr = Math.sqrt(1 - y * y), t = Math.random() * 6.283;
    poeira.push({ x: Math.cos(t) * rr * 1.05, y: y * 1.05, z: Math.sin(t) * rr * 1.05 });
  }
  function tamanho() {
    var b = cv.getBoundingClientRect();
    W = b.width; H = b.height; cv.width = W * dpr; cv.height = H * dpr; ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    R = Math.min(W, H) * 0.42;
  }
  function proj(p) {
    var c = Math.cos(ang), s = Math.sin(ang), x = p.x * c - p.z * s, z = p.x * s + p.z * c;
    var y = p.y * Math.cos(0.35) - z * Math.sin(0.35), z2 = p.y * Math.sin(0.35) + z * Math.cos(0.35);
    var e = 1.9 / (2.6 - z2);
    return { x: W / 2 + x * R * e, y: H / 2 + y * R * e, z: z2, e: e };
  }
  function moeda(v) { return 'R$ ' + Math.round(v).toLocaleString('pt-BR'); }
  function quadro() {
    ctx.clearRect(0, 0, W, H);
    var g = ctx.createRadialGradient(W / 2, H / 2, 0, W / 2, H / 2, R * 1.3);
    g.addColorStop(0, 'rgba(79,209,255,0.10)'); g.addColorStop(1, 'rgba(79,209,255,0)');
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
    poeira.forEach(function (p) {
      var q = proj(p); ctx.fillStyle = 'rgba(140,200,255,' + (0.15 + (q.z + 1) * 0.18) + ')';
      ctx.fillRect(q.x, q.y, 1.3, 1.3);
    });
    nos.forEach(function (n) { n.q = proj(n); });
    // ligação de cada produto à sua categoria
    ctx.lineWidth = 0.6;
    nos.forEach(function (n) {
      if (n.cat) return;
      ctx.strokeStyle = 'rgba(79,209,255,' + (0.08 + (n.q.z + 1) * 0.08) + ')';
      ctx.beginPath(); ctx.moveTo(n.q.x, n.q.y); ctx.lineTo(n.pai.q.x, n.pai.q.y); ctx.stroke();
    });
    // o que sai junto: linhas douradas com pulso andando
    links.forEach(function (l) {
      ctx.strokeStyle = 'rgba(227,194,127,' + (0.12 + l.f * 0.45) + ')'; ctx.lineWidth = 0.5 + l.f * 1.8;
      ctx.beginPath(); ctx.moveTo(l.a.q.x, l.a.q.y); ctx.lineTo(l.b.q.x, l.b.q.y); ctx.stroke();
      l.t = (l.t + 0.004 + l.f * 0.01) % 1;
      var px = l.a.q.x + (l.b.q.x - l.a.q.x) * l.t, py = l.a.q.y + (l.b.q.y - l.a.q.y) * l.t;
      ctx.fillStyle = 'rgba(255,230,170,0.9)'; ctx.beginPath(); ctx.arc(px, py, 1.6, 0, 6.283); ctx.fill();
    });
    var hover = null;
    nos.slice().sort(function (a, b) { return a.q.z - b.q.z; }).forEach(function (n) {
      var r = n.tam * n.q.e, a = 0.45 + (n.q.z + 1) * 0.27;
      var gg = ctx.createRadialGradient(n.q.x, n.q.y, 0, n.q.x, n.q.y, r * 2.6);
      gg.addColorStop(0, n.cat ? 'rgba(79,209,255,' + a + ')' : 'rgba(227,194,127,' + a + ')');
      gg.addColorStop(1, 'rgba(0,0,0,0)');
      ctx.fillStyle = gg; ctx.beginPath(); ctx.arc(n.q.x, n.q.y, r * 2.6, 0, 6.283); ctx.fill();
      ctx.fillStyle = n.cat ? 'rgba(200,240,255,' + a + ')' : 'rgba(255,235,190,' + a + ')';
      ctx.beginPath(); ctx.arc(n.q.x, n.q.y, r, 0, 6.283); ctx.fill();
      if (mouse && Math.hypot(mouse.x - n.q.x, mouse.y - n.q.y) < Math.max(8, r + 4)) hover = n;
      if (n.cat && n.q.z > -0.5) {
        ctx.fillStyle = 'rgba(200,240,255,' + (0.5 + (n.q.z + 1) * 0.25) + ')';
        ctx.font = '600 ' + Math.round(9 + 2 * n.q.e) + 'px ui-monospace,Menlo,Consolas,monospace';
        ctx.fillText(n.nome.toUpperCase(), n.q.x + r + 6, n.q.y + 4);
      }
    });
    if (hover) {
      var t = hover.nome + ' · ' + moeda(hover.v);
      ctx.font = '600 13px ui-monospace,Menlo,Consolas,monospace';
      var w = ctx.measureText(t).width + 16;
      ctx.fillStyle = 'rgba(6,14,26,0.92)'; ctx.strokeStyle = 'rgba(79,209,255,0.7)';
      ctx.fillRect(hover.q.x + 10, hover.q.y - 30, w, 24); ctx.strokeRect(hover.q.x + 10, hover.q.y - 30, w, 24);
      ctx.fillStyle = '#E8F6FF'; ctx.fillText(t, hover.q.x + 18, hover.q.y - 13);
    }
    ang += mouse ? 0.0012 : 0.0035;
    requestAnimationFrame(quadro);
  }
  cv.addEventListener('mousemove', function (e) { var b = cv.getBoundingClientRect(); mouse = { x: e.clientX - b.left, y: e.clientY - b.top }; });
  cv.addEventListener('mouseleave', function () { mouse = null; });
  window.addEventListener('resize', tamanho);
  tamanho(); requestAnimationFrame(quadro);
})();
