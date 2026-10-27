<?php
/**
 * Tela de revelação (para projetar/compartilhar a tela).
 * Os ganhadores vêm do banco; a animação é só apresentação.
 */
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/admin_layout.php';
sorteio_session_start();
$pdo = admin_db_or_die();
$user = sorteio_require_login();

$id = (int) ($_GET['id'] ?? 0);
$raffle = sorteio_load_raffle_for_user($pdo, $id, $user);
if (!$raffle) { http_response_code(404); exit('Sorteio não encontrado.'); }
$result = sorteio_get_result($pdo, $id);
if (!$result) {
    sorteio_flash('erro', 'Este sorteio ainda não foi realizado.');
    sorteio_redirect('sorteio.php?id=' . $id);
}

// Nomes dos participantes considerados (só o primeiro nome) para o efeito visual
$names = [];
if ($result['considered']) {
    $in = implode(',', array_fill(0, count($result['considered']), '?'));
    $st = $pdo->prepare("SELECT name FROM raffle_participants WHERE raffle_id = ? AND id IN ($in) ORDER BY id");
    $st->execute(array_merge([$id], $result['considered']));
    $names = array_values(array_unique(array_map('sorteio_first_name', $st->fetchAll(PDO::FETCH_COLUMN))));
}
$winners = array_map(fn($w) => [
    'position' => (int) $w['position'],
    'name' => $w['name'],
    'whatsapp' => sorteio_mask_whatsapp($w['whatsapp']),
], $result['winners']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Resultado · <?= h($raffle['title']) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="/sorteio/assets/sorteio.css?v=1">
</head>
<body class="reveal">
  <a class="reveal-back" href="sorteio.php?id=<?= $id ?>">← voltar ao painel</a>
  <div class="reveal-inner">
    <img src="/sorteio/assets/logo-destrava.png" alt="Destrava" class="reveal-logo">
    <?php if ($raffle['organizer']): ?><p class="reveal-org"><?= h($raffle['organizer']) ?></p><?php endif; ?>
    <h1 class="reveal-title"><?= h($raffle['title']) ?></h1>
    <p class="reveal-prize">Prêmio: <?= h($raffle['prize_name']) ?></p>
    <p class="reveal-meta"><?= (int) $result['eligible_count'] ?> participantes · sorteio nº <?= (int) $result['id'] ?> · <?= h(sorteio_date($result['drawn_at'])) ?><?= (int) $raffle['is_test'] ? ' · TESTE' : '' ?></p>

    <div class="reveal-stage">
      <div class="reveal-spin" id="spin">&nbsp;</div>
    </div>
    <button class="btn btn-gold btn-large" id="next">Revelar 1º ganhador</button>
    <ol class="reveal-list" id="list"></ol>
  </div>

<script>
(function () {
  // Resultado oficial, já gravado no banco. Nada é sorteado aqui.
  var winners = <?= json_encode($winners, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var names = <?= json_encode($names, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var spin = document.getElementById('spin');
  var btn = document.getElementById('next');
  var list = document.getElementById('list');
  var idx = 0, busy = false;
  if (!names.length) names = ['...'];

  function reveal() {
    if (busy || idx >= winners.length) return;
    busy = true; btn.disabled = true;
    var w = winners[idx], k = idx * 7, start = Date.now(), dur = 2600;
    spin.classList.remove('is-winner');
    (function tick() {
      var t = Date.now() - start;
      if (t < dur) {
        spin.textContent = names[k++ % names.length];   // efeito visual, em sequência
        setTimeout(tick, 60 + Math.pow(t / dur, 3) * 240);
        return;
      }
      spin.textContent = w.name;
      spin.classList.add('is-winner');
      var li = document.createElement('li');
      li.innerHTML = '<span class="pos"></span><span class="nm"></span><span class="wa"></span>';
      li.querySelector('.pos').textContent = w.position + 'º';
      li.querySelector('.nm').textContent = w.name;
      li.querySelector('.wa').textContent = 'WhatsApp ' + w.whatsapp;
      list.appendChild(li);
      idx++; busy = false; btn.disabled = false;
      if (idx < winners.length) btn.textContent = 'Revelar ' + (idx + 1) + 'º ganhador';
      else { btn.textContent = 'Todos os ganhadores revelados'; btn.disabled = true; }
    })();
  }
  btn.addEventListener('click', reveal);
})();
</script>
</body>
</html>
