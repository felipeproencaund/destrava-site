<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_layout.php';
destrava_admin_require_login();

$pdo = destrava_db();
$validStatuses = DESTRAVA_STATUSES;

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$statusMessage = !empty($_GET['criado']) ? ['type' => 'success', 'text' => 'Contato cadastrado.'] : null;
$autor = $_SESSION['admin_username'] ?? 'admin';

$addNote = function (string $texto, string $by) use ($pdo, $id): void {
    $pdo->prepare('INSERT INTO lead_notas (lead_id, texto, autor) VALUES (:id, :t, :a)')
        ->execute(['id' => $id, 't' => $texto, 'a' => $by]);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!destrava_csrf_check($_POST['csrf_token'] ?? '')) {
        $statusMessage = ['type' => 'error', 'text' => 'Sessão expirada, tente novamente.'];
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'delete') {
            $pdo->prepare('DELETE FROM leads WHERE id = :id')->execute(['id' => $id]);
            header('Location: index.php?deleted=1');
            exit;
        }

        if ($action === 'update_status') {
            $newStatus = $_POST['status'] ?? '';
            if (in_array($newStatus, $validStatuses, true)) {
                $old = $pdo->prepare('SELECT status FROM leads WHERE id = :id');
                $old->execute(['id' => $id]);
                $oldStatus = (string) $old->fetchColumn();
                if ($oldStatus !== $newStatus) {
                    $pdo->prepare('UPDATE leads SET status = :s WHERE id = :id')->execute(['s' => $newStatus, 'id' => $id]);
                    $addNote('Status: ' . $oldStatus . ' → ' . $newStatus, $autor);
                }
                $statusMessage = ['type' => 'success', 'text' => 'Status atualizado.'];
            }
        }

        if ($action === 'add_note') {
            $texto = destrava_sanitize_str($_POST['texto'] ?? '', 2000);
            if ($texto !== '') {
                $addNote($texto, $autor);
                $statusMessage = ['type' => 'success', 'text' => 'Anotação salva.'];
            }
        }

        if ($action === 'set_followup') {
            $raw = trim((string) ($_POST['proximo_contato'] ?? ''));
            $d = $raw === '' ? null : DateTime::createFromFormat('Y-m-d', $raw);
            if ($raw === '' || ($d && $d->format('Y-m-d') === $raw)) {
                $pdo->prepare('UPDATE leads SET proximo_contato = :d WHERE id = :id')->execute(['d' => $raw === '' ? null : $raw, 'id' => $id]);
                $addNote($raw === '' ? 'Próximo contato removido.' : 'Próximo contato marcado para ' . $d->format('d/m/Y') . '.', $autor);
                $statusMessage = ['type' => 'success', 'text' => 'Próximo contato atualizado.'];
            } else {
                $statusMessage = ['type' => 'error', 'text' => 'Data inválida.'];
            }
        }

        if ($action === 'update_contact') {
            $nome = destrava_sanitize_str($_POST['nome'] ?? '', 150);
            $empresa = destrava_sanitize_str($_POST['empresa'] ?? '', 150);
            $whats = destrava_sanitize_whatsapp($_POST['whatsapp'] ?? '');
            $email = destrava_sanitize_str($_POST['email'] ?? '', 150);
            $insta = destrava_sanitize_str($_POST['instagram'] ?? '', 200);
            $site = destrava_sanitize_str($_POST['site'] ?? '', 200);
            if ($nome === '' || strlen($whats) < 10 || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
                $statusMessage = ['type' => 'error', 'text' => 'Confira nome, WhatsApp e e-mail.'];
            } else {
                $pdo->prepare('UPDATE leads SET nome=:n, empresa=:e, whatsapp=:w, email=:m, instagram=:i, site=:s WHERE id=:id')
                    ->execute(['n' => $nome, 'e' => $empresa, 'w' => $whats, 'm' => $email ?: null, 'i' => $insta ?: null, 's' => $site ?: null, 'id' => $id]);
                $statusMessage = ['type' => 'success', 'text' => 'Dados atualizados.'];
            }
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

$isQuiz = $lead['diagnostico_principal'] !== '-';
$notasStmt = $pdo->prepare('SELECT texto, autor, created_at FROM lead_notas WHERE lead_id = :id ORDER BY created_at DESC, id DESC');
$notasStmt->execute(['id' => $id]);
$notas = $notasStmt->fetchAll();

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
destrava_admin_head('Contato: ' . $lead['nome'], 'leads');
?>
  <a class="back-link" href="index.php">← Voltar para leads</a>

  <?php if ($statusMessage): ?>
    <p class="flash flash-<?= h($statusMessage['type']) ?>"><?= h($statusMessage['text']) ?></p>
  <?php endif; ?>

  <div class="lead-detail-grid">
    <div class="lead-main">
      <h1><?= htmlspecialchars($lead['nome'], ENT_QUOTES) ?></h1>
      <p class="lead-subtitle"><?= htmlspecialchars($lead['empresa'], ENT_QUOTES) ?></p>

      <div class="lead-contact-row">
        <a href="<?= h(destrava_wa_link($lead['whatsapp'])) ?>" target="_blank" rel="noopener" class="btn-whatsapp">
          WhatsApp: <?= htmlspecialchars($lead['whatsapp'], ENT_QUOTES) ?>
        </a>
        <?php if (!empty($lead['email'])): ?>
          <a href="mailto:<?= h($lead['email']) ?>" class="lead-link">E-mail: <?= h($lead['email']) ?></a>
        <?php endif; ?>
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

      <?php if ($isQuiz): ?>
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

      <?php endif; ?>

      <section class="lead-card">
        <h2>Histórico e anotações</h2>
        <form method="post" class="note-form">
          <input type="hidden" name="csrf_token" value="<?= h(destrava_csrf_token()) ?>">
          <input type="hidden" name="action" value="add_note">
          <textarea name="texto" rows="3" maxlength="2000" placeholder="O que foi conversado, combinado, próximos passos..." required></textarea>
          <button type="submit" class="btn-primary">Salvar anotação</button>
        </form>
        <ul class="timeline">
          <?php foreach ($notas as $n): ?>
            <li>
              <span class="timeline-meta"><?= h(date('d/m/Y H:i', strtotime($n['created_at']))) ?> · <?= h($n['autor']) ?></span>
              <p><?= nl2br(h($n['texto'])) ?></p>
            </li>
          <?php endforeach; ?>
        </ul>
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
        <h2>Próximo contato</h2>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= h(destrava_csrf_token()) ?>">
          <input type="hidden" name="action" value="set_followup">
          <input type="date" name="proximo_contato" class="status-select" value="<?= h($lead['proximo_contato'] ?? '') ?>">
          <button type="submit" class="btn-primary btn-block">Salvar data</button>
        </form>
      </section>

      <section class="lead-card">
        <h2>Dados do contato</h2>
        <form method="post" class="crm-form crm-form-compact">
          <input type="hidden" name="csrf_token" value="<?= h(destrava_csrf_token()) ?>">
          <input type="hidden" name="action" value="update_contact">
          <label>Nome<input type="text" name="nome" value="<?= h($lead['nome']) ?>" maxlength="150" required></label>
          <label>Empresa<input type="text" name="empresa" value="<?= h($lead['empresa']) ?>" maxlength="150"></label>
          <label>WhatsApp<input type="tel" name="whatsapp" value="<?= h($lead['whatsapp']) ?>" required></label>
          <label>E-mail<input type="email" name="email" value="<?= h($lead['email'] ?? '') ?>" maxlength="150"></label>
          <label>Instagram<input type="text" name="instagram" value="<?= h($lead['instagram'] ?? '') ?>" maxlength="200"></label>
          <label>Site<input type="text" name="site" value="<?= h($lead['site'] ?? '') ?>" maxlength="200"></label>
          <button type="submit" class="btn-primary btn-block">Salvar dados</button>
        </form>
      </section>

      <section class="lead-card">
        <h2>Funil</h2>
        <table class="answers-table">
          <tr><td class="answers-label">Recebido em</td><td class="answers-value"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($lead['created_at'])), ENT_QUOTES) ?></td></tr>
          <?php if ($isQuiz): ?>
          <tr><td class="answers-label">Clicou no CTA</td><td class="answers-value"><?= $lead['cta_clicado'] ? 'Sim' : 'Não' ?></td></tr>
          <?php if ($lead['cta_clicado'] && $lead['cta_clicado_em']): ?>
          <tr><td class="answers-label">Clique em</td><td class="answers-value"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($lead['cta_clicado_em'])), ENT_QUOTES) ?></td></tr>
          <?php endif; ?>
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
<?php destrava_admin_foot();
