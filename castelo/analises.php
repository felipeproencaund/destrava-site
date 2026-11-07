<?php
/** Castelo: Análises — painel de cada empresa do grupo. */
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/analises.php';
destrava_admin_require_login();
$pdo = destrava_db();

$ult = castelo_an_info($pdo, 'pede-coxinha', 'ultimo_pedido');
$pc = null;
if ($ult) {
    $fim = date('Y-m-d 23:59:59', strtotime($ult));
    $ini = date('Y-m-d 00:00:00', strtotime($ult . ' -29 days'));
    $pc = castelo_q($pdo, 'SELECT COUNT(*) n, COALESCE(SUM(total - lanche_valor),0) v FROM pc_pedidos WHERE excluido = 0 AND so_lanche = 0 AND aberto BETWEEN :i AND :f', ['i' => $ini, 'f' => $fim])[0];
}
$leads = castelo_q($pdo, 'SELECT COUNT(*) n FROM leads WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')[0]['n'] ?? 0;
destrava_admin_head('Análises', 'analises');
?>
<div class="page-head"><div><p class="intersect-kicker">Torre de controle</p><h1>Análises</h1></div></div>
<div class="an-empresas">
  <a class="painel-card an-empresa" href="analise-painel.php">
    <span class="kpi-rotulo">Pede Coxinha</span>
    <?php if ($pc): ?>
      <strong><?= castelo_reais((float) $pc['v']) ?></strong>
      <span class="kpi-nota"><?= castelo_num((float) $pc['n']) ?> pedidos nos 30 dias até <?= h(date('d/m/Y', strtotime($ult))) ?></span>
    <?php else: ?>
      <strong>Sem dados</strong><span class="kpi-nota">Importe o arquivo do Consumer pra começar.</span>
    <?php endif; ?>
    <span class="an-abrir">Abrir painel da reunião →</span>
  </a>
  <a class="painel-card an-empresa" href="leads.php">
    <span class="kpi-rotulo">Destrava</span>
    <strong><?= castelo_num((float) $leads) ?> leads</strong>
    <span class="kpi-nota">nos últimos 30 dias. A análise completa da Destrava vem depois.</span>
    <span class="an-abrir">Abrir CRM →</span>
  </a>
  <div class="painel-card an-empresa an-embreve">
    <span class="kpi-rotulo">Outras empresas</span>
    <strong>Em breve</strong>
    <span class="kpi-nota">Cada empresa entra aqui quando o sistema dela estiver ligado ao Castelo.</span>
  </div>
</div>
<?php destrava_admin_foot();
