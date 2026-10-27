<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_layout.php';
destrava_admin_require_login();
$pdo = destrava_db();

// exclusão rápida direto da listagem
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (destrava_csrf_check($_POST['csrf_token'] ?? '')) {
        $deleteId = (int) ($_POST['id'] ?? 0);
        if ($deleteId > 0) {
            $pdo->prepare('DELETE FROM leads WHERE id = :id')->execute(['id' => $deleteId]);
        }
    }
    header('Location: index.php?deleted=1');
    exit;
}

$statusFilter = $_GET['status'] ?? '';
$q = destrava_sanitize_str($_GET['q'] ?? '', 100);
$origemFilter = $_GET['origem'] ?? '';
$due = ($_GET['retorno'] ?? '') === 'hoje';

$where = [];
$params = [];
if (in_array($statusFilter, DESTRAVA_STATUSES, true)) {
    $where[] = 'status = :status';
    $params['status'] = $statusFilter;
}
if ($q !== '') {
    $where[] = '(nome LIKE :q1 OR empresa LIKE :q2 OR whatsapp LIKE :q3 OR email LIKE :q4)';
    $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
    $params['q1'] = $params['q2'] = $params['q3'] = $params['q4'] = $like;
}
if ($origemFilter === 'quiz') {
    $where[] = "diagnostico_principal <> '-'";
} elseif ($origemFilter === 'manual') {
    $where[] = "diagnostico_principal = '-'";
}
if ($due) {
    $where[] = "proximo_contato IS NOT NULL AND proximo_contato <= CURDATE() AND status NOT IN ('Cliente', 'Não avançou')";
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("SELECT id, created_at, nome, empresa, whatsapp, diagnostico_principal, gargalo_principal, status, proximo_contato, origem
    FROM leads $whereSql
    ORDER BY (proximo_contato IS NULL), proximo_contato ASC, created_at DESC
    LIMIT 300");
$stmt->execute($params);
$leads = $stmt->fetchAll();

$countsByStatus = [];
foreach ($pdo->query('SELECT status, COUNT(*) AS total FROM leads GROUP BY status')->fetchAll() as $c) {
    $countsByStatus[$c['status']] = (int) $c['total'];
}
$totalLeads = array_sum($countsByStatus);
$dueCount = (int) $pdo->query("SELECT COUNT(*) FROM leads WHERE proximo_contato IS NOT NULL AND proximo_contato <= CURDATE() AND status NOT IN ('Cliente', 'Não avançou')")->fetchColumn();
$today = date('Y-m-d');
$csrfToken = destrava_csrf_token();

destrava_admin_head('Leads', 'leads');
?>
<div class="page-head">
  <h1>Leads e contatos</h1>
  <a class="btn-primary" href="novo.php">+ Novo contato</a>
</div>

<?php if (destrava_admin_uses_default_password($pdo)): ?>
  <p class="flash flash-error">Você ainda usa a senha padrão. <a href="senha.php">Troque agora</a>.</p>
<?php endif; ?>
<?php if (!empty($_GET['deleted'])): ?><p class="flash flash-success">Contato excluído.</p><?php endif; ?>

<form method="get" class="search-bar">
  <input type="search" name="q" value="<?= h($q) ?>" placeholder="Buscar por nome, empresa, WhatsApp ou e-mail">
  <?php if ($statusFilter !== ''): ?><input type="hidden" name="status" value="<?= h($statusFilter) ?>"><?php endif; ?>
  <select name="origem">
    <option value="">Todas as origens</option>
    <option value="quiz" <?= $origemFilter === 'quiz' ? 'selected' : '' ?>>Vieram do quiz</option>
    <option value="manual" <?= $origemFilter === 'manual' ? 'selected' : '' ?>>Cadastrados por mim</option>
  </select>
  <button type="submit" class="btn-primary">Buscar</button>
</form>

<div class="filter-bar">
  <a class="filter-chip <?= $statusFilter === '' && !$due ? 'is-active' : '' ?>" href="index.php">Todos (<?= $totalLeads ?>)</a>
  <a class="filter-chip filter-chip-alert <?= $due ? 'is-active' : '' ?>" href="index.php?retorno=hoje">Para retornar (<?= $dueCount ?>)</a>
  <?php foreach (DESTRAVA_STATUSES as $s): ?>
    <a class="filter-chip <?= $statusFilter === $s ? 'is-active' : '' ?>" href="index.php?status=<?= urlencode($s) ?>"><?= h($s) ?> (<?= $countsByStatus[$s] ?? 0 ?>)</a>
  <?php endforeach; ?>
</div>

<?php if (empty($leads)): ?>
  <p class="empty-state">Nenhum contato encontrado.</p>
<?php else: ?>
<div class="table-wrap">
  <table class="leads-table">
    <thead>
      <tr><th>Nome</th><th>Empresa</th><th>WhatsApp</th><th>Origem</th><th>Status</th><th>Próximo contato</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($leads as $lead):
        $overdue = $lead['proximo_contato'] && $lead['proximo_contato'] < $today && !in_array($lead['status'], ['Cliente', 'Não avançou'], true);
        $isQuiz = $lead['diagnostico_principal'] !== '-';
    ?>
      <tr>
        <td><a class="row-link" href="lead.php?id=<?= (int) $lead['id'] ?>"><?= h($lead['nome']) ?></a></td>
        <td><?= h($lead['empresa']) ?></td>
        <td><a class="btn-whatsapp btn-sm" href="<?= h(destrava_wa_link($lead['whatsapp'])) ?>" target="_blank" rel="noopener"><?= h($lead['whatsapp']) ?></a></td>
        <td><?= $isQuiz ? '<span class="badge-diag">Quiz · ' . h($lead['diagnostico_principal']) . '</span>' : h($lead['origem'] ?: 'manual') ?></td>
        <td><span class="badge-status badge-status-<?= h(str_replace(' ', '-', $lead['status'])) ?>"><?= h($lead['status']) ?></span></td>
        <td class="<?= $overdue ? 'is-overdue' : '' ?>"><?= $lead['proximo_contato'] ? h(date('d/m/Y', strtotime($lead['proximo_contato']))) : '-' ?></td>
        <td>
          <form method="post" onsubmit="return confirm('Excluir este contato permanentemente?');">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $lead['id'] ?>">
            <button type="submit" class="btn-icon-danger" title="Excluir">✕</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
<?php destrava_admin_foot();
