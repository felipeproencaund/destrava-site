<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_layout.php';
destrava_admin_require_login();

$file = destrava_log_file();
$entries = [];
if (is_file($file)) {
    $lines = array_slice(file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -100);
    foreach (array_reverse($lines) as $line) {
        $row = json_decode($line, true);
        if (is_array($row)) {
            $entries[] = $row;
        }
    }
}

destrava_admin_head('Erros recentes', 'erros');
?>
<h1>Erros recentes</h1>
<p class="muted">Os últimos 100 erros registrados neste mês. Nada aqui aparece para o cliente.</p>
<?php if (!$entries): ?>
  <p class="empty-state">Nenhum erro registrado. Tudo certo por aqui.</p>
<?php else: ?>
<div class="table-wrap">
  <table class="leads-table">
    <thead><tr><th>Quando</th><th>Nível</th><th>Página</th><th>Mensagem</th><th>Onde</th></tr></thead>
    <tbody>
    <?php foreach ($entries as $e): ?>
      <tr>
        <td><?= h($e['quando'] ?? '') ?></td>
        <td><?= h($e['nivel'] ?? '') ?></td>
        <td><?= h(($e['metodo'] ?? '') . ' ' . ($e['pagina'] ?? '')) ?></td>
        <td><?= h($e['mensagem'] ?? '') ?></td>
        <td><?= h(($e['detalhe']['arquivo'] ?? '') . (isset($e['detalhe']['linha']) ? ':' . $e['detalhe']['linha'] : '')) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
<?php destrava_admin_foot();
