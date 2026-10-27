<?php
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/admin_layout.php';
sorteio_session_start();
$pdo = admin_db_or_die();
$user = sorteio_require_login();

$sql = "SELECT r.*, u.name AS owner_name,
          (SELECT COUNT(*) FROM raffle_participants p WHERE p.raffle_id = r.id AND p.status = 'participante') AS n_participants
        FROM raffles r JOIN raffle_users u ON u.id = r.owner_user_id";
$params = [];
if ($user['role'] !== 'destrava') {
    $sql .= ' WHERE r.owner_user_id = ?';
    $params[] = (int) $user['id'];
}
$sql .= ' ORDER BY r.created_at DESC';
$st = $pdo->prepare($sql);
$st->execute($params);
$raffles = $st->fetchAll();

admin_header('Meus sorteios', $user);
?>
<div class="page-head">
  <h1>Sorteios</h1>
  <a class="btn btn-primary" href="editar.php">+ Novo sorteio</a>
</div>

<?php if (!$raffles): ?>
  <section class="card empty">
    <p>Nenhum sorteio criado ainda.</p>
    <p class="muted">Crie o primeiro com o botão "Novo sorteio". Dica: marque "sorteio de teste" pra experimentar antes do sorteio de verdade.</p>
  </section>
<?php else: ?>
  <div class="raffle-list">
  <?php foreach ($raffles as $r): ?>
    <a class="card raffle-item" href="sorteio.php?id=<?= (int) $r['id'] ?>">
      <div class="raffle-item-main">
        <h2><?= h($r['title']) ?> <?php if ((int) $r['is_test']): ?><span class="badge badge-test">TESTE</span><?php endif; ?></h2>
        <p class="muted">Prêmio: <?= h($r['prize_name']) ?><?php if ($user['role'] === 'destrava'): ?> · Cliente: <?= h($r['owner_name']) ?><?php endif; ?></p>
      </div>
      <div class="raffle-item-meta">
        <span class="badge badge-<?= h($r['status']) ?>"><?= h(status_label($r['status'])) ?></span>
        <span class="muted small"><?= (int) $r['n_participants'] ?><?= $r['max_participants'] !== null ? ' / ' . (int) $r['max_participants'] : '' ?> inscritos · <?= (int) $r['winners_count'] ?> ganhador<?= (int) $r['winners_count'] === 1 ? '' : 'es' ?></span>
      </div>
    </a>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php admin_footer();
