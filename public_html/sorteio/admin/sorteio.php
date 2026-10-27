<?php
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/admin_layout.php';
sorteio_session_start();
$pdo = admin_db_or_die();
$user = sorteio_require_login();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$raffle = sorteio_load_raffle_for_user($pdo, $id, $user);
if (!$raffle) { http_response_code(404); exit('Sorteio não encontrado.'); }
$self = 'sorteio.php?id=' . $id;

// ---- Ações (todas por POST + CSRF) -----------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!sorteio_csrf_ok()) { http_response_code(400); exit('Sessão expirada. Volte e tente de novo.'); }
    $action = (string) ($_POST['action'] ?? '');

    switch ($action) {
        case 'draw':
            $res = sorteio_execute_draw($id, $user);
            if ($res['ok']) {
                sorteio_redirect('revelar.php?id=' . $id);
            }
            sorteio_flash('erro', $res['error']);
            break;

        case 'open':
        case 'close':
            $new = $action === 'open' ? 'aberto' : 'encerrado';
            $pdo->prepare("UPDATE raffles SET status = ? WHERE id = ? AND status <> 'sorteado'")->execute([$new, $id]);
            sorteio_flash('ok', $action === 'open' ? 'Inscrições reabertas.' : 'Inscrições encerradas. Agora dá pra sortear.');
            break;

        case 'participant':
            $pid = (int) ($_POST['pid'] ?? 0);
            $to = (string) ($_POST['to'] ?? '');
            $map = ['inelegivel' => ['inelegivel', 0], 'cancelado' => ['cancelado', 0], 'participante' => ['participante', 1]];
            if (isset($map[$to]) && $raffle['status'] !== 'sorteado') {
                $pdo->prepare('UPDATE raffle_participants SET status = ?, eligible = ? WHERE id = ? AND raffle_id = ?')
                    ->execute([$map[$to][0], $map[$to][1], $pid, $id]);
                sorteio_flash('ok', 'Participante atualizado.');
            } else {
                sorteio_flash('erro', 'Depois do sorteio a lista de participantes fica travada.');
            }
            break;

        case 'delivery':
            $wid = (int) ($_POST['wid'] ?? 0);
            $delivered = !empty($_POST['delivered']) ? 1 : 0;
            $note = sorteio_clean((string) ($_POST['note'] ?? ''), 500);
            $pdo->prepare('UPDATE raffle_draw_winners w JOIN raffle_draws d ON d.id = w.draw_id
                           SET w.delivered = ?, w.delivered_at = IF(? = 1, COALESCE(w.delivered_at, NOW()), NULL), w.delivery_note = ?
                           WHERE w.id = ? AND d.raffle_id = ?')
                ->execute([$delivered, $delivered, $note ?: null, $wid, $id]);
            sorteio_flash('ok', 'Controle de entrega salvo.');
            break;

        case 'delete_test':
            if ((int) $raffle['is_test']) {
                $pdo->beginTransaction();
                $pdo->prepare('DELETE w FROM raffle_draw_winners w JOIN raffle_draws d ON d.id = w.draw_id WHERE d.raffle_id = ?')->execute([$id]);
                $pdo->prepare('DELETE FROM raffle_draws WHERE raffle_id = ?')->execute([$id]);
                $pdo->prepare('DELETE FROM raffle_participants WHERE raffle_id = ?')->execute([$id]);
                $pdo->prepare('DELETE FROM raffles WHERE id = ? AND is_test = 1')->execute([$id]);
                $pdo->commit();
                sorteio_flash('ok', 'Sorteio de teste apagado.');
                sorteio_redirect('index.php');
            }
            sorteio_flash('erro', 'Só sorteios de teste podem ser apagados.');
            break;
    }
    sorteio_redirect($self);
}

// ---- Dados -----------------------------------------------------
$st = $pdo->prepare('SELECT * FROM raffle_participants WHERE raffle_id = ? ORDER BY id');
$st->execute([$id]);
$participants = $st->fetchAll();
$nActive = count(array_filter($participants, fn($p) => $p['status'] === 'participante'));
$nEligible = count(array_filter($participants, fn($p) => $p['status'] === 'participante' && (int) $p['eligible'] === 1));
$result = $raffle['status'] === 'sorteado' ? sorteio_get_result($pdo, $id) : null;
$winnerIds = $result ? array_map(fn($w) => (int) $w['participant_id'], $result['winners']) : [];
$publicUrl = sorteio_base_url() . sorteio_public_path($raffle['slug']);
$canDraw = !$result && $nEligible >= (int) $raffle['winners_count'];

admin_header($raffle['title'], $user);
?>
<p class="back"><a href="index.php">← Sorteios</a></p>

<div class="page-head">
  <div>
    <h1><?= h($raffle['title']) ?> <?php if ((int) $raffle['is_test']): ?><span class="badge badge-test">TESTE</span><?php endif; ?></h1>
    <p class="muted">Prêmio: <strong><?= h($raffle['prize_name']) ?></strong> · <?= (int) $raffle['winners_count'] ?> ganhador<?= (int) $raffle['winners_count'] === 1 ? '' : 'es' ?><?php if ($user['role'] === 'destrava'): ?> · Cliente: <?= h($raffle['owner_name']) ?><?php endif; ?></p>
  </div>
  <span class="badge badge-<?= h($raffle['status']) ?>"><?= h(status_label($raffle['status'])) ?></span>
</div>

<section class="card">
  <h2 class="section-h">Link para divulgar</h2>
  <div class="link-row">
    <input type="text" readonly value="<?= h($publicUrl) ?>" id="public-url">
    <button type="button" class="btn btn-outline" onclick="navigator.clipboard.writeText(document.getElementById('public-url').value);this.textContent='Copiado!'">Copiar</button>
    <a class="btn btn-outline" href="<?= h($publicUrl) ?>" target="_blank" rel="noopener">Abrir</a>
  </div>
  <div class="stats">
    <div><strong><?= $nActive ?></strong><span>inscritos<?= $raffle['max_participants'] !== null ? ' de ' . (int) $raffle['max_participants'] : '' ?></span></div>
    <div><strong><?= $nEligible ?></strong><span>elegíveis</span></div>
    <div><strong><?= (int) $raffle['winners_count'] ?></strong><span>ganhadores</span></div>
  </div>
  <?php if (!$result): ?>
  <div class="actions">
    <a class="btn btn-outline" href="editar.php?id=<?= $id ?>">Editar</a>
    <form method="post" class="inline"><?= sorteio_csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
      <?php if ($raffle['status'] === 'aberto'): ?>
        <button name="action" value="close" class="btn btn-outline">Encerrar inscrições</button>
      <?php else: ?>
        <button name="action" value="open" class="btn btn-outline">Reabrir inscrições</button>
      <?php endif; ?>
    </form>
    <a class="btn btn-outline" href="exportar.php?id=<?= $id ?>">Baixar lista (CSV)</a>
  </div>
  <?php endif; ?>
</section>

<?php if ($result): ?>
<section class="card">
  <div class="page-head">
    <h2 class="section-h">Ganhadores</h2>
    <a class="btn btn-gold" href="revelar.php?id=<?= $id ?>">Tela de revelação</a>
  </div>
  <div class="winners-admin">
  <?php foreach ($result['winners'] as $w): $wa = preg_replace('/\D/', '', $w['whatsapp']); ?>
    <form method="post" class="winner-row">
      <?= sorteio_csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="delivery"><input type="hidden" name="wid" value="<?= (int) $w['id'] ?>">
      <div class="winner-pos"><?= (int) $w['position'] ?>º</div>
      <div class="winner-info">
        <strong><?= h($w['name']) ?></strong>
        <a href="https://wa.me/<?= h($wa) ?>" target="_blank" rel="noopener"><?= h(sorteio_format_whatsapp($w['whatsapp'])) ?></a>
        <span class="muted small"><?= h(implode(' · ', array_filter([$w['instagram'] ?? '', $w['website'] ?? '', 'inscrição #' . (int) $w['participant_id']]))) ?></span>
      </div>
      <div class="winner-delivery">
        <label class="check"><input type="checkbox" name="delivered" value="1" <?= (int) $w['delivered'] ? 'checked' : '' ?>> <span>Prêmio entregue<?= $w['delivered_at'] ? ' em ' . h(sorteio_date($w['delivered_at'], false)) : '' ?></span></label>
        <input type="text" name="note" value="<?= h($w['delivery_note'] ?? '') ?>" placeholder="Observação (ex.: retirou na loja)" maxlength="500">
        <button class="btn btn-outline btn-sm">Salvar</button>
      </div>
    </form>
  <?php endforeach; ?>
  </div>
  <div class="audit">
    <h3>Registro do sorteio</h3>
    <p>Participantes elegíveis no momento do sorteio: <strong><?= (int) $result['eligible_count'] ?></strong></p>
    <p><?= (int) $result['winners_count'] ?> ganhador<?= (int) $result['winners_count'] === 1 ? '' : 'es' ?> selecionado<?= (int) $result['winners_count'] === 1 ? '' : 's' ?>.</p>
    <p>Data: <?= h(sorteio_date($result['drawn_at'])) ?> · Executado por: <?= h($result['executed_by_username'] ?? '—') ?> · Sorteio nº <?= (int) $result['id'] ?></p>
    <p class="muted small">Método: <?= h($result['rng_method']) ?>. Inscrições consideradas: <?= h(implode(', ', array_map(fn($i) => '#' . $i, $result['considered']))) ?></p>
  </div>
</section>
<?php else: ?>
<section class="card draw-box">
  <h2 class="section-h">Realizar o sorteio</h2>
  <?php if ($raffle['status'] === 'aberto'): ?>
    <p class="muted">Recomendado: encerre as inscrições antes de sortear. Quem se inscrever depois do sorteio fica de fora.</p>
  <?php endif; ?>
  <?php if ($canDraw): ?>
    <p>O sorteio vai escolher <strong><?= (int) $raffle['winners_count'] ?></strong> entre os <strong><?= $nEligible ?></strong> elegíveis. Ele é feito uma única vez e o resultado fica gravado; não dá pra refazer.</p>
    <form method="post" onsubmit="return confirm('Realizar o sorteio agora? O resultado é definitivo.');">
      <?= sorteio_csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
      <button name="action" value="draw" class="btn btn-gold btn-large">Sortear agora</button>
    </form>
  <?php else: ?>
    <p class="muted">Ainda não dá pra sortear: há <?= $nEligible ?> elegível(is) e são necessários pelo menos <?= (int) $raffle['winners_count'] ?>.</p>
  <?php endif; ?>
</section>
<?php endif; ?>

<section class="card">
  <div class="page-head">
    <h2 class="section-h">Participantes (<?= count($participants) ?>)</h2>
    <?php if ($result): ?><a class="btn btn-outline btn-sm" href="exportar.php?id=<?= $id ?>">Baixar lista (CSV)</a><?php endif; ?>
  </div>
  <?php if (!$participants): ?>
    <p class="muted">Ninguém se inscreveu ainda.</p>
  <?php else: ?>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>#</th><th>Nome</th><th>WhatsApp</th><th>Instagram / Site</th><th>Inscrição</th><th>Situação</th><?php if (!$result): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($participants as $p): $isWinner = in_array((int) $p['id'], $winnerIds, true); ?>
      <tr class="<?= $p['status'] !== 'participante' ? 'row-off' : '' ?> <?= $isWinner ? 'row-win' : '' ?>">
        <td><?= (int) $p['id'] ?></td>
        <td><?= h($p['name']) ?><?= $isWinner ? ' <span class="badge badge-sorteado">ganhador</span>' : '' ?></td>
        <td class="nowrap"><a href="https://wa.me/<?= h($p['whatsapp']) ?>" target="_blank" rel="noopener"><?= h(sorteio_format_whatsapp($p['whatsapp'])) ?></a></td>
        <td class="small"><?= h($p['instagram'] ?? '') ?><?= $p['instagram'] && $p['website'] ? '<br>' : '' ?><?= h($p['website'] ?? '') ?></td>
        <td class="small"><?= h(sorteio_date($p['created_at'])) ?></td>
        <td><?= ['participante' => 'Participando', 'inelegivel' => 'Inelegível', 'cancelado' => 'Cancelado'][$p['status']] ?></td>
        <?php if (!$result): ?>
        <td>
          <form method="post" class="inline"><?= sorteio_csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="participant"><input type="hidden" name="pid" value="<?= (int) $p['id'] ?>">
            <?php if ($p['status'] === 'participante'): ?>
              <button name="to" value="inelegivel" class="link-btn">Tornar inelegível</button>
              <button name="to" value="cancelado" class="link-btn">Cancelar</button>
            <?php else: ?>
              <button name="to" value="participante" class="link-btn">Reativar</button>
            <?php endif; ?>
          </form>
        </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</section>

<?php if ((int) $raffle['is_test']): ?>
<section class="card danger-zone">
  <h2 class="section-h">Apagar sorteio de teste</h2>
  <p class="muted">Remove este sorteio de teste e todos os dados dele. Sorteios reais não podem ser apagados.</p>
  <form method="post" onsubmit="return confirm('Apagar este sorteio de teste e todos os participantes dele?');">
    <?= sorteio_csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
    <button name="action" value="delete_test" class="btn btn-danger">Apagar sorteio de teste</button>
  </form>
</section>
<?php endif; ?>
<?php admin_footer();
