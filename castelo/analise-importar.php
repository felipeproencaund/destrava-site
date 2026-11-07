<?php
/** Castelo: importar os dados da Pede Coxinha (arquivo gerado a partir do Consumer). */
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/analises.php';
destrava_admin_require_login();
$pdo = destrava_db();

$msg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = $_FILES['arquivo'] ?? null;
    if (!destrava_csrf_check($_POST['csrf_token'] ?? '')) {
        $msg = ['error', 'Sessão expirada. Tente de novo.'];
    } elseif (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        $msg = ['error', 'Não recebi o arquivo. Tente de novo.'];
    } elseif ($f['size'] > 20 * 1024 * 1024) {
        $msg = ['error', 'Arquivo grande demais (máximo 20 MB).'];
    } else {
        @set_time_limit(180);
        try {
            [$ok, $texto] = castelo_importar_pc($pdo, (string) file_get_contents($f['tmp_name']), (string) ($_SESSION['admin_username'] ?? ''));
            $msg = [$ok ? 'success' : 'error', $texto];
        } catch (Throwable $e) {
            error_log('Castelo importar PC: ' . $e->getMessage());
            $msg = ['error', 'Não foi possível importar. Nada foi alterado.'];
        }
    }
}
$hist = castelo_q($pdo, 'SELECT * FROM an_importacoes WHERE empresa = "pede-coxinha" ORDER BY id DESC LIMIT 10');
destrava_admin_head('Importar dados', 'analises');
?>
<div class="page-head">
  <div><p class="intersect-kicker">Análises · Pede Coxinha</p><h1>Importar dados</h1></div>
  <a class="btn-sec" href="analise-pc.php">← Voltar pras análises</a>
</div>
<?php if ($msg): ?><p class="flash flash-<?= h($msg[0]) ?>"><?= h($msg[1]) ?></p><?php endif; ?>
<section class="painel-card">
  <h2>Enviar arquivo</h2>
  <p class="muted">Arquivo <strong>.json.gz</strong> gerado a partir do backup do Consumer. Pode enviar de novo quantas vezes quiser: o que já existe é atualizado e o que é novo entra. Quando o envio automático da loja estiver ligado, essa tela vira só um plano B.</p>
  <form method="post" enctype="multipart/form-data" class="form-excluir">
    <input type="hidden" name="csrf_token" value="<?= h(destrava_csrf_token()) ?>">
    <input type="file" name="arquivo" required>
    <button type="submit" class="btn-primary">Importar</button>
  </form>
</section>
<section class="painel-card">
  <h2>Últimas importações</h2>
  <?php if (!$hist): ?><p class="empty-state">Nenhuma importação ainda.</p><?php else: ?>
  <ul class="lista-simples">
    <?php foreach ($hist as $r): ?>
      <li><span><?= h(date('d/m/Y H:i', strtotime($r['quando']))) ?> · <?= h($r['fonte']) ?><?= $r['gerado_em'] ? ' · arquivo de ' . h(date('d/m H:i', strtotime($r['gerado_em']))) : '' ?></span>
      <strong><?= castelo_num((float) $r['pedidos']) ?> pedidos</strong></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>
<?php destrava_admin_foot();
