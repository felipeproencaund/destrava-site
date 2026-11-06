<?php
/** Castelo: metas e lucro — quanto vender por dia pra pagar as contas, e quanto sobra. */
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/fin.php';
destrava_admin_require_login();
$pdo = destrava_db();

$mes = preg_match('/^\d{4}-\d{2}$/', $_GET['mes'] ?? '') ? $_GET['mes'] : date('Y-m');
$empresas = $pdo->query('SELECT * FROM fin_carteiras WHERE ativa = 1 AND empresa = 1 ORDER BY ordem, id')->fetchAll();
$c = (int) ($_GET['c'] ?? ($empresas[0]['id'] ?? 0));
$cart = null;
foreach ($empresas as $k) {
    if ((int) $k['id'] === $c) {
        $cart = $k;
    }
}
$cart = $cart ?: ($empresas[0] ?? null);

$meses = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
$nomeMes = ucfirst($meses[(int) substr($mes, 5, 2) - 1]) . ' de ' . substr($mes, 0, 4);
$ant = date('Y-m', strtotime($mes . '-01 -1 month'));
$prox = date('Y-m', strtotime($mes . '-01 +1 month'));
$ehMesAtual = $mes === date('Y-m');

destrava_admin_head('Metas e lucro', 'financeiro');
if (!$cart): ?>
  <div class="page-head"><h1>Metas e lucro</h1></div>
  <p class="empty-state">Nenhuma carteira de empresa ativa. Em Financeiro → Configurar, marque quais carteiras são empresas.</p>
<?php destrava_admin_foot(); exit; endif;

$r = castelo_resultado($pdo, $cart, $mes);
$porDia = castelo_vendas_por_dia($pdo, (int) $cart['id'], $mes);
$pct = function (float $v): string { return number_format($v * 100, 1, ',', '.') . '%'; };
?>
<div class="page-head fin-head">
  <div>
    <p class="intersect-kicker">Metas e lucro</p>
    <h1><?= h($cart['nome']) ?> · <?= h($nomeMes) ?></h1>
  </div>
  <div class="fin-mes">
    <a class="btn-sec" href="metas.php?c=<?= (int) $cart['id'] ?>&amp;mes=<?= h($ant) ?>">← <?= h($meses[(int) substr($ant, 5, 2) - 1]) ?></a>
    <a class="btn-sec" href="metas.php?c=<?= (int) $cart['id'] ?>&amp;mes=<?= h($prox) ?>"><?= h($meses[(int) substr($prox, 5, 2) - 1]) ?> →</a>
    <a class="btn-sec" href="financeiro.php?mes=<?= h($mes) ?>&amp;c=<?= (int) $cart['id'] ?>">Lançamentos</a>
  </div>
</div>

<nav class="filter-bar" aria-label="Empresas">
  <?php foreach ($empresas as $k): ?>
    <a class="filter-chip <?= (int) $k['id'] === (int) $cart['id'] ? 'is-active' : '' ?>" href="metas.php?c=<?= (int) $k['id'] ?>&amp;mes=<?= h($mes) ?>"><?= h($k['nome']) ?></a>
  <?php endforeach; ?>
</nav>

<section class="meta-hero">
  <div class="meta-hero-principal">
    <?php if ($ehMesAtual && $r['falta'] > 0 && $r['dias_rest'] > 0): ?>
      <span class="kpi-rotulo">Precisa vender por dia, a partir de hoje</span>
      <strong class="meta-numero"><?= castelo_reais($r['meta_dia']) ?></strong>
      <span class="kpi-nota">Faltam <?= castelo_reais($r['falta']) ?> pra pagar as contas do mês, em <?= (int) $r['dias_rest'] ?> dia<?= $r['dias_rest'] > 1 ? 's' : '' ?> de funcionamento.</span>
    <?php elseif ($r['pe'] > 0 && $r['falta'] <= 0): ?>
      <span class="kpi-rotulo">Contas do mês cobertas</span>
      <strong class="meta-numero valor-entrada">✓ <?= castelo_reais($r['fat'] - $r['pe']) ?> acima</strong>
      <span class="kpi-nota">Daqui pra frente, o que vender (menos o custo das mercadorias) é lucro.</span>
    <?php else: ?>
      <span class="kpi-rotulo">Ponto de equilíbrio do mês</span>
      <strong class="meta-numero"><?= castelo_reais($r['pe']) ?></strong>
      <span class="kpi-nota">Média de <?= castelo_reais($r['meta_media']) ?> por dia de funcionamento.</span>
    <?php endif; ?>
    <div class="meta-barra" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= round($r['progresso'] * 100) ?>">
      <span data-pct="<?= round($r['progresso'] * 100) ?>"></span>
    </div>
    <span class="kpi-nota">Vendido no mês: <strong><?= castelo_reais($r['fat']) ?></strong> de <?= castelo_reais($r['pe']) ?> (<?= round($r['progresso'] * 100) ?>%)</span>
  </div>
  <?php if ($ehMesAtual): ?>
  <div class="meta-hoje">
    <span class="kpi-rotulo">Hoje</span>
    <strong><?= castelo_reais($r['hoje']) ?></strong>
    <?php if ($r['meta_dia'] > 0): ?>
      <span class="kpi-nota"><?= $r['hoje'] >= $r['meta_dia'] ? 'Meta do dia batida' : 'Faltam ' . castelo_reais($r['meta_dia'] - $r['hoje']) . ' pra meta de hoje' ?></span>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</section>

<section class="kpis">
  <div class="kpi"><span class="kpi-rotulo">Faturamento</span><strong class="valor-entrada"><?= castelo_reais($r['fat']) ?></strong><span class="kpi-nota">tudo que entrou no mês</span></div>
  <div class="kpi"><span class="kpi-rotulo">CMV (custo variável)</span><strong><?= $pct($r['cmv_pct']) ?></strong><span class="kpi-nota"><?= castelo_reais($r['var']) ?> · base: <?= h($r['cmv_origem']) ?></span></div>
  <div class="kpi"><span class="kpi-rotulo">Custos fixos + retirada</span><strong class="valor-saida"><?= castelo_reais($r['cobrir']) ?></strong><span class="kpi-nota">o que as vendas precisam pagar</span></div>
  <div class="kpi kpi-alerta"><span class="kpi-rotulo">Lucro do mês</span><strong><?= castelo_reais($r['lucro']) ?></strong><span class="kpi-nota">depois da retirada · antes dela: <?= castelo_reais($r['lucro_op']) ?></span></div>
</section>

<section class="painel-card">
  <h2>Vendas por dia</h2>
  <p class="muted">Barra verde: dia que bateu a meta média. Linha tracejada: <?= castelo_reais($r['meta_media']) ?> por dia, que é o ponto de equilíbrio dividido pelos <?= (int) $r['dias_mes'] ?> dias de funcionamento do mês.</p>
  <div class="fin-grafico-wrap"><?= castelo_svg_vendas($porDia, $r['meta_media'], $mes, $cart['dias']) ?></div>
</section>

<section class="painel-card meta-conta">
  <h2>De onde sai essa conta</h2>
  <ul class="lista-simples">
    <li><span>Custos fixos do mês (aluguel, funcionários, DAS, ferramentas…)</span><strong><?= castelo_reais($r['fixo']) ?></strong></li>
    <li><span>Retirada dos donos</span><strong><?= castelo_reais($r['ret']) ?></strong></li>
    <li><span>Outras saídas sem classificação</span><strong><?= castelo_reais($r['outro']) ?></strong></li>
    <li><span>Margem que sobra de cada venda (100% − CMV)</span><strong><?= $pct($r['mc_pct']) ?></strong></li>
    <li><span><strong>Ponto de equilíbrio</strong> = contas a cobrir ÷ margem</span><strong><?= castelo_reais($r['pe']) ?></strong></li>
  </ul>
  <p class="muted">A conta usa o que está lançado no Financeiro. Cadastre as contas do mês (de preferência como contas fixas) e marque em Configurar se cada categoria é custo fixo, variável (CMV) ou retirada. Quando o sistema da Pede Coxinha estiver ligado ao Castelo, o CMV passa a vir das fichas técnicas.</p>
</section>
<?php destrava_admin_foot();
