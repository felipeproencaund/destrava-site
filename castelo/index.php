<?php
/** Castelo: tela inicial (Intersect) com os números do mês e os próximos retornos. */
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/layout.php';
destrava_admin_require_login();
$pdo = destrava_db();

$fechados = "status NOT IN ('Cliente', 'Não avançou')";
$novosMes = (int) $pdo->query("SELECT COUNT(*) FROM leads WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn();
$novosMesAnt = (int) $pdo->query("SELECT COUNT(*) FROM leads WHERE created_at >= DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01') AND created_at < DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn();
$total = (int) $pdo->query('SELECT COUNT(*) FROM leads')->fetchColumn();
$retornarHoje = (int) $pdo->query("SELECT COUNT(*) FROM leads WHERE proximo_contato IS NOT NULL AND proximo_contato <= CURDATE() AND $fechados")->fetchColumn();
$clientes = (int) $pdo->query("SELECT COUNT(*) FROM leads WHERE status = 'Cliente'")->fetchColumn();

$porStatus = [];
foreach ($pdo->query('SELECT status, COUNT(*) AS n FROM leads GROUP BY status')->fetchAll() as $r) {
    $porStatus[$r['status']] = (int) $r['n'];
}
$maior = max(1, ...array_values($porStatus ?: [0]));

$proximos = $pdo->query("SELECT id, nome, empresa, whatsapp, status, proximo_contato FROM leads
    WHERE proximo_contato IS NOT NULL AND proximo_contato <= CURDATE() + INTERVAL 7 DAY AND $fechados
    ORDER BY proximo_contato ASC LIMIT 6")->fetchAll();
$ultimos = $pdo->query('SELECT id, nome, empresa, diagnostico_principal, origem, created_at FROM leads ORDER BY created_at DESC LIMIT 5')->fetchAll();

$hoje = date('Y-m-d');
$meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$mesNome = $meses[(int) date('n') - 1];

destrava_admin_head('Início', 'inicio');
?>
<div class="page-head intersect-head">
  <div>
    <p class="intersect-kicker">Intersect</p>
    <h1>Visão geral de <?= h($mesNome) ?></h1>
  </div>
  <a class="btn-primary" href="novo.php">+ Novo contato</a>
</div>

<?php if (destrava_admin_uses_default_password($pdo)): ?>
  <p class="flash flash-error">Você ainda usa a senha provisória. <a href="senha.php">Troque agora</a>.</p>
<?php endif; ?>

<section class="kpis">
  <a class="kpi" href="leads.php">
    <span class="kpi-rotulo">Leads novos no mês</span>
    <strong><?= $novosMes ?></strong>
    <span class="kpi-nota"><?= $novosMesAnt ?> no mês passado</span>
  </a>
  <a class="kpi kpi-alerta" href="leads.php?retorno=hoje">
    <span class="kpi-rotulo">Para retornar hoje</span>
    <strong><?= $retornarHoje ?></strong>
    <span class="kpi-nota">contatos com retorno vencido ou para hoje</span>
  </a>
  <a class="kpi" href="leads.php?status=Cliente">
    <span class="kpi-rotulo">Clientes</span>
    <strong><?= $clientes ?></strong>
    <span class="kpi-nota">leads que fecharam</span>
  </a>
  <a class="kpi" href="leads.php">
    <span class="kpi-rotulo">Leads no total</span>
    <strong><?= $total ?></strong>
    <span class="kpi-nota">desde o início</span>
  </a>
</section>

<div class="painel-grade">
  <section class="painel-card">
    <h2>Funil de vendas</h2>
    <ul class="funil-lista">
      <?php foreach (DESTRAVA_STATUSES as $s): $n = $porStatus[$s] ?? 0; ?>
        <li>
          <a href="leads.php?status=<?= urlencode($s) ?>">
            <span class="funil-nome"><?= h($s) ?></span>
            <span class="funil-barra"><span data-pct="<?= round($n / $maior * 100) ?>"></span></span>
            <span class="funil-n"><?= $n ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <section class="painel-card">
    <h2>Próximos retornos</h2>
    <?php if (!$proximos): ?>
      <p class="empty-state">Nenhum retorno marcado para os próximos 7 dias.</p>
    <?php else: ?>
      <ul class="lista-simples">
        <?php foreach ($proximos as $l): $vencido = $l['proximo_contato'] < $hoje; ?>
          <li>
            <a href="lead.php?id=<?= (int) $l['id'] ?>"><strong><?= h($l['nome']) ?></strong><span><?= h($l['empresa'] ?: $l['status']) ?></span></a>
            <span class="data-tag <?= $vencido ? 'is-overdue' : '' ?>"><?= $l['proximo_contato'] === $hoje ? 'hoje' : h(date('d/m', strtotime($l['proximo_contato']))) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="painel-card">
    <h2>Últimos leads</h2>
    <?php if (!$ultimos): ?>
      <p class="empty-state">Ainda não chegou nenhum lead.</p>
    <?php else: ?>
      <ul class="lista-simples">
        <?php foreach ($ultimos as $l): ?>
          <li>
            <a href="lead.php?id=<?= (int) $l['id'] ?>"><strong><?= h($l['nome']) ?></strong><span><?= $l['diagnostico_principal'] !== '-' ? 'Quiz · ' . h($l['diagnostico_principal']) : h($l['origem'] ?: 'Cadastro manual') ?></span></a>
            <span class="data-tag"><?= h(date('d/m', strtotime($l['created_at']))) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="painel-card painel-modulos">
    <h2>Em construção</h2>
    <ul class="lista-simples">
      <li><a href="modulo.php?m=financeiro"><strong>Financeiro</strong><span>Entradas, saídas e lucro por empresa</span></a></li>
      <li><a href="modulo.php?m=contratos"><strong>Contratos</strong><span>Gerar e assinar online</span></a></li>
      <li><a href="modulo.php?m=planejamento"><strong>Planejamento</strong><span>Plano de marketing de 90 dias</span></a></li>
    </ul>
  </section>
</div>
<?php destrava_admin_foot();
