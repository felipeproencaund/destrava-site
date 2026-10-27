<?php
require_once __DIR__ . '/auth.php';
destrava_admin_require_login();

$pdo = destrava_db();

// exclusão rápida direto da listagem
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (destrava_csrf_check($_POST['csrf_token'] ?? '')) {
        $deleteId = (int) ($_POST['id'] ?? 0);
        if ($deleteId > 0) {
            $stmt = $pdo->prepare('DELETE FROM leads WHERE id = :id');
            $stmt->execute(['id' => $deleteId]);
        }
    }
    header('Location: index.php?deleted=1');
    exit;
}

$statusFilter = $_GET['status'] ?? '';
$validStatuses = ['Novo', 'Contato realizado', 'Reunião marcada', 'Proposta enviada', 'Cliente', 'Não avançou'];

$where = '';
$params = [];
if ($statusFilter !== '' && in_array($statusFilter, $validStatuses, true)) {
    $where = 'WHERE status = :status';
    $params['status'] = $statusFilter;
}

$stmt = $pdo->prepare("SELECT id, created_at, nome, empresa, whatsapp, diagnostico_principal, diagnostico_titulo, gargalo_principal, objetivo, status, cta_clicado
    FROM leads
    $where
    ORDER BY created_at DESC
    LIMIT 200");
$stmt->execute($params);
$leads = $stmt->fetchAll();

// contagem rápida por status para os filtros
$counts = $pdo->query('SELECT status, COUNT(*) as total FROM leads GROUP BY status')->fetchAll();
$countsByStatus = [];
foreach ($counts as $c) {
    $countsByStatus[$c['status']] = (int) $c['total'];
}
$totalLeads = array_sum($countsByStatus);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Leads | Destrava Admin</title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="admin.css?v=3">
</head>
<body class="admin-body">
<header class="admin-header">
  <div class="admin-brand"><img class="admin-logo" src="../assets/img/logo-destrava.png" alt="Destrava"></div>
  <nav class="admin-nav">
    <span>Olá, <?= htmlspecialchars($_SESSION['admin_username'] ?? '', ENT_QUOTES) ?></span>
    <a href="logout.php">Sair</a>
  </nav>
</header>

<main class="admin-main">
  <h1>Leads do diagnóstico</h1>

  <?php if (!empty($_GET['deleted'])): ?>
    <p class="flash flash-success">Lead excluído.</p>
  <?php endif; ?>

  <div class="filter-bar">
    <a class="filter-chip <?= $statusFilter === '' ? 'is-active' : '' ?>" href="index.php">
      Todos (<?= $totalLeads ?>)
    </a>
    <?php foreach ($validStatuses as $s): ?>
      <a class="filter-chip <?= $statusFilter === $s ? 'is-active' : '' ?>" href="index.php?status=<?= urlencode($s) ?>">
        <?= htmlspecialchars($s, ENT_QUOTES) ?> (<?= $countsByStatus[$s] ?? 0 ?>)
      </a>
    <?php endforeach; ?>
  </div>

  <?php if (empty($leads)): ?>
    <p class="empty-state">Nenhum lead encontrado ainda.</p>
  <?php else: ?>
  <div class="table-wrap">
    <table class="leads-table">
      <thead>
        <tr>
          <th>Data</th>
          <th>Nome</th>
          <th>Empresa</th>
          <th>WhatsApp</th>
          <th>Diagnóstico</th>
          <th>Gargalo</th>
          <th>Objetivo</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php $csrfToken = destrava_csrf_token(); ?>
        <?php foreach ($leads as $lead): ?>
        <tr onclick="window.location='lead.php?id=<?= (int) $lead['id'] ?>'">
          <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($lead['created_at'])), ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars($lead['nome'], ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars($lead['empresa'], ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars($lead['whatsapp'], ENT_QUOTES) ?></td>
          <td><span class="badge-diag">Diagnóstico <?= htmlspecialchars($lead['diagnostico_principal'], ENT_QUOTES) ?></span></td>
          <td class="col-gargalo"><?= htmlspecialchars($lead['gargalo_principal'], ENT_QUOTES) ?></td>
          <td class="col-objetivo"><?= htmlspecialchars($lead['objetivo'] ?? '', ENT_QUOTES) ?></td>
          <td><span class="badge-status badge-status-<?= htmlspecialchars(str_replace(' ', '-', $lead['status']), ENT_QUOTES) ?>"><?= htmlspecialchars($lead['status'], ENT_QUOTES) ?></span></td>
          <td>
            <form method="post" onclick="event.stopPropagation();" onsubmit="return confirm('Excluir este lead permanentemente?');">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $lead['id'] ?>">
              <button type="submit" class="btn-icon-danger" title="Excluir lead">✕</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</main>
</body>
</html>
