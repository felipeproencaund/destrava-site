<?php
require_once __DIR__ . '/auth.php';
destrava_admin_require_login();

$pdo = destrava_db();
$validStatuses = ['Novo', 'Contato realizado', 'Reunião marcada', 'Proposta enviada', 'Cliente', 'Não avançou'];

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$statusMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!destrava_csrf_check($_POST['csrf_token'] ?? '')) {
        $statusMessage = ['type' => 'error', 'text' => 'Sessão expirada, tente novamente.'];
    } else {
        $action = $_POST['action'] ?? 'update_status';

        if ($action === 'delete') {
            $stmt = $pdo->prepare('DELETE FROM leads WHERE id = :id');
            $stmt->execute(['id' => $id]);
            header('Location: index.php?deleted=1');
            exit;
        }

        $newStatus = $_POST['status'] ?? '';
        if (in_array($newStatus, $validStatuses, true)) {
            $stmt = $pdo->prepare('UPDATE leads SET status = :status WHERE id = :id');
            $stmt->execute(['status' => $newStatus, 'id' => $id]);
            $statusMessage = ['type' => 'success', 'text' => 'Status atualizado.'];
        }
    }
}

$stmt = $pdo->prepare('SELECT * FROM leads WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$lead = $stmt->fetch();

if (!$lead) {
    header('Location: index.php');
    exit;
}

$respostas = json_decode($lead['respostas'] ?? '[]', true) ?: [];
$sinais = json_decode($lead['sinais'] ?? '[]', true) ?: [];
$areas = json_decode($lead['areas_desbloqueio'] ?? '[]', true) ?: [];

$questionLabels = [];
if (isset($GLOBALS['DESTRAVA_QUESTION_LABELS'])) {
    $questionLabels = $GLOBALS['DESTRAVA_QUESTION_LABELS'];
}
// Mapeia campo -> pergunta legível (mantido em sincronia com js/quiz-data.js)
$questionLabels = [
    'responsavel_marketing' => 'Quem é responsável pelo marketing',
    'consistencia_conteudo' => 'Frequência de produção de conteúdo',
    'percepcao_marketing' => 'Percepção sobre o marketing da empresa',
    'posicionamento' => 'Clareza sobre por que o cliente escolhe a empresa',
    'presenca_digital' => 'Presença no Instagram/Google',
    'mensuracao' => 'Sabe de onde vêm os clientes',
    'tempo_disponivel' => 'Tempo disponível por semana para marketing',
    'principal_obstaculo' => 'Principal obstáculo',
    'dor_principal' => 'Dor principal',
    'objetivo' => 'Objetivo com o marketing',
];

/**
 * Monta uma URL clicável a partir do que o lead digitou em
 * "Instagram" ou "Site" (que pode vir como @handle, handle,
 * "instagram.com/handle", "seusite.com.br" ou uma URL completa).
 */
function destrava_build_contact_link(string $value, string $kind): string {
    $value = trim($value);
    if (preg_match('#^https?://#i', $value)) {
        return $value;
    }
    if ($kind === 'instagram') {
        $handle = ltrim($value, '@');
        // se a pessoa já colou "instagram.com/handle"
        $handle = preg_replace('#^(www\.)?instagram\.com/#i', '', $handle);
        return 'https://instagram.com/' . rawurlencode($handle);
    }
    return 'https://' . $value;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Lead: <?= htmlspecialchars($lead['nome'], ENT_QUOTES) ?> | Destrava Admin</title>
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
  <a class="back-link" href="index.php">← Voltar para leads</a>

  <?php if ($statusMessage): ?>
    <p class="flash flash-<?= $statusMessage['type'] ?>"><?= htmlspecialchars($statusMessage['text'], ENT_QUOTES) ?></p>
  <?php endif; ?>

  <div class="lead-detail-grid">
    <div class="lead-main">
      <h1><?= htmlspecialchars($lead['nome'], ENT_QUOTES) ?></h1>
      <p class="lead-subtitle"><?= htmlspecialchars($lead['empresa'], ENT_QUOTES) ?></p>

      <div class="lead-contact-row">
        <a href="https://wa.me/<?= preg_replace('/\D/', '', $lead['whatsapp']) ?>" target="_blank" rel="noopener" class="btn-whatsapp">
          WhatsApp: <?= htmlspecialchars($lead['whatsapp'], ENT_QUOTES) ?>
        </a>
        <?php if (!empty($lead['instagram'])): ?>
          <a href="<?= htmlspecialchars(destrava_build_contact_link($lead['instagram'], 'instagram'), ENT_QUOTES) ?>"
             target="_blank" rel="noopener" class="lead-link">
            Instagram: <?= htmlspecialchars($lead['instagram'], ENT_QUOTES) ?> ↗
          </a>
        <?php endif; ?>
        <?php if (!empty($lead['site'])): ?>
          <a href="<?= htmlspecialchars(destrava_build_contact_link($lead['site'], 'site'), ENT_QUOTES) ?>"
             target="_blank" rel="noopener" class="lead-link">
            Site: <?= htmlspecialchars($lead['site'], ENT_QUOTES) ?> ↗
          </a>
        <?php endif; ?>
      </div>

      <section class="lead-card">
        <h2>Diagnóstico</h2>
        <p class="diag-titulo">Diagnóstico <?= htmlspecialchars($lead['diagnostico_principal'], ENT_QUOTES) ?>: <?= htmlspecialchars($lead['diagnostico_titulo'], ENT_QUOTES) ?></p>
        <p class="diag-gargalo"><strong>Gargalo principal:</strong> <?= htmlspecialchars($lead['gargalo_principal'], ENT_QUOTES) ?></p>
        <?php if (!empty($lead['objetivo'])): ?>
          <p class="diag-objetivo"><strong>Objetivo declarado:</strong> <?= htmlspecialchars($lead['objetivo'], ENT_QUOTES) ?></p>
        <?php endif; ?>
      </section>

      <?php if (!empty($sinais)): ?>
      <section class="lead-card">
        <h2>Sinais identificados</h2>
        <ul class="plain-list">
          <?php foreach ($sinais as $s): ?>
            <li><?= htmlspecialchars($s, ENT_QUOTES) ?></li>
          <?php endforeach; ?>
        </ul>
      </section>
      <?php endif; ?>

      <?php if (!empty($areas)): ?>
      <section class="lead-card">
        <h2>Áreas a destravar</h2>
        <ul class="plain-list">
          <?php foreach ($areas as $a): ?>
            <li><?= htmlspecialchars($a, ENT_QUOTES) ?></li>
          <?php endforeach; ?>
        </ul>
      </section>
      <?php endif; ?>

      <section class="lead-card">
        <h2>Respostas completas</h2>
        <table class="answers-table">
          <?php foreach ($respostas as $field => $value): ?>
            <tr>
              <td class="answers-label"><?= htmlspecialchars($questionLabels[$field] ?? $field, ENT_QUOTES) ?></td>
              <td class="answers-value"><?= htmlspecialchars((string) $value, ENT_QUOTES) ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </section>

      <section class="lead-card">
        <h2>Origem</h2>
        <table class="answers-table">
          <tr><td class="answers-label">Origem</td><td class="answers-value"><?= htmlspecialchars($lead['origem'] ?? '-', ENT_QUOTES) ?></td></tr>
          <tr><td class="answers-label">UTM Source</td><td class="answers-value"><?= htmlspecialchars($lead['utm_source'] ?? '-', ENT_QUOTES) ?></td></tr>
          <tr><td class="answers-label">UTM Medium</td><td class="answers-value"><?= htmlspecialchars($lead['utm_medium'] ?? '-', ENT_QUOTES) ?></td></tr>
          <tr><td class="answers-label">UTM Campaign</td><td class="answers-value"><?= htmlspecialchars($lead['utm_campaign'] ?? '-', ENT_QUOTES) ?></td></tr>
          <tr><td class="answers-label">UTM Content</td><td class="answers-value"><?= htmlspecialchars($lead['utm_content'] ?? '-', ENT_QUOTES) ?></td></tr>
          <tr><td class="answers-label">UTM Term</td><td class="answers-value"><?= htmlspecialchars($lead['utm_term'] ?? '-', ENT_QUOTES) ?></td></tr>
        </table>
      </section>
    </div>

    <aside class="lead-sidebar">
      <section class="lead-card">
        <h2>Status</h2>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(destrava_csrf_token(), ENT_QUOTES) ?>">
          <input type="hidden" name="action" value="update_status">
          <select name="status" class="status-select">
            <?php foreach ($validStatuses as $s): ?>
              <option value="<?= htmlspecialchars($s, ENT_QUOTES) ?>" <?= $lead['status'] === $s ? 'selected' : '' ?>><?= htmlspecialchars($s, ENT_QUOTES) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn-primary btn-block">Atualizar status</button>
        </form>
      </section>

      <section class="lead-card">
        <h2>Funil</h2>
        <table class="answers-table">
          <tr><td class="answers-label">Recebido em</td><td class="answers-value"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($lead['created_at'])), ENT_QUOTES) ?></td></tr>
          <tr><td class="answers-label">Clicou no CTA</td><td class="answers-value"><?= $lead['cta_clicado'] ? 'Sim' : 'Não' ?></td></tr>
          <?php if ($lead['cta_clicado'] && $lead['cta_clicado_em']): ?>
          <tr><td class="answers-label">Clique em</td><td class="answers-value"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($lead['cta_clicado_em'])), ENT_QUOTES) ?></td></tr>
          <?php endif; ?>
        </table>
      </section>

      <section class="lead-card lead-card-danger">
        <h2>Zona de risco</h2>
        <form method="post" onsubmit="return confirm('Excluir este lead permanentemente? Essa ação não pode ser desfeita.');">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(destrava_csrf_token(), ENT_QUOTES) ?>">
          <input type="hidden" name="action" value="delete">
          <button type="submit" class="btn-danger btn-block">Excluir lead</button>
        </form>
      </section>
    </aside>
  </div>
</main>
</body>
</html>
