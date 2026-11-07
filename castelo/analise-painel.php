<?php
/** Castelo: painel de reunião da Pede Coxinha — uma tela, números grandes, pra deixar aberto com a equipe. */
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/analises.php';
destrava_admin_require_login();
$pdo = destrava_db();

$ult = castelo_an_info($pdo, 'pede-coxinha', 'ultimo_pedido');
destrava_admin_head('Painel da reunião · Pede Coxinha', 'analises');
if (!$ult): ?>
  <p class="empty-state">Ainda não tem dados. <a href="analise-importar.php">Importe o arquivo do Consumer</a>.</p>
<?php destrava_admin_foot(); exit; endif;

$visao = ($_GET['v'] ?? 'mes') === 'semana' ? 'semana' : 'mes';
$hoje = date('Y-m-d', strtotime($ult));
if ($visao === 'mes') {
    $ini = date('Y-m-01', strtotime($hoje));
    $n = (int) date('j', strtotime($hoje));
    $iniA = date('Y-m-01', strtotime($ini . ' -1 month'));
    $fimA = date('Y-m-d', min(strtotime($iniA . ' +' . ($n - 1) . ' days'), strtotime(date('Y-m-t', strtotime($iniA)))));
    $iniY = date('Y-m-01', strtotime($ini . ' -1 year'));
    $fimY = date('Y-m-d', min(strtotime($iniY . ' +' . ($n - 1) . ' days'), strtotime(date('Y-m-t', strtotime($iniY)))));
    $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    $rotulo = ucfirst($meses[(int) date('n', strtotime($hoje)) - 1]) . ' até dia ' . $n;
    $rotA = 'mesmos dias do mês anterior';
} else {
    $ini = date('Y-m-d', strtotime($hoje . ' -' . (date('N', strtotime($hoje)) - 1) . ' days'));
    $n = (int) round((strtotime($hoje) - strtotime($ini)) / 86400) + 1;
    $iniA = date('Y-m-d', strtotime($ini . ' -7 days'));
    $fimA = date('Y-m-d', strtotime($iniA . ' +' . ($n - 1) . ' days'));
    $iniY = date('Y-m-d', strtotime($ini . ' -364 days'));
    $fimY = date('Y-m-d', strtotime($iniY . ' +' . ($n - 1) . ' days'));
    $rotulo = 'Semana de ' . date('d/m', strtotime($ini)) . ' a ' . date('d/m', strtotime($hoje));
    $rotA = 'mesmos dias da semana anterior';
}
$per = function (string $a, string $b): array {
    return ['i' => $a . ' 00:00:00', 'f' => $b . ' 23:59:59'];
};
$P = $per($ini, $hoje);
$PA = $per($iniA, $fimA);
$PY = $per($iniY, $fimY);

$res = function (array $p) use ($pdo): array {
    $a = castelo_q($pdo, 'SELECT COUNT(*) n, COALESCE(SUM(total - lanche_valor),0) v FROM pc_pedidos WHERE excluido = 0 AND so_lanche = 0 AND aberto BETWEEN :i AND :f', $p)[0];
    $m = castelo_q($pdo, 'SELECT COALESCE(SUM(it.mini),0) m FROM pc_itens it JOIN pc_pedidos p ON p.id = it.pedido_id WHERE p.excluido = 0 AND it.pai = 0 AND p.aberto BETWEEN :i AND :f', $p)[0]['m'];
    $c = castelo_q($pdo, 'SELECT COUNT(*) n, SUM(EXISTS(SELECT 1 FROM pc_itens b WHERE b.pedido_id = p.id AND b.pai = 0 AND b.categoria = "bebida")) b FROM pc_pedidos p
        WHERE p.excluido = 0 AND p.canal <> "balcao" AND p.aberto BETWEEN :i AND :f AND EXISTS(SELECT 1 FROM pc_itens c WHERE c.pedido_id = p.id AND c.pai = 0 AND c.categoria = "cento")', $p)[0];
    $bl = castelo_q($pdo, 'SELECT COUNT(*) n, SUM(cliente_id IS NOT NULL) c FROM pc_pedidos WHERE excluido = 0 AND so_lanche = 0 AND canal = "balcao" AND aberto BETWEEN :i AND :f', $p)[0];
    $enc = castelo_q($pdo, 'SELECT COALESCE(SUM(CASE WHEN p.agendado = 1 AND p.previsto IS NOT NULL AND DATE(p.previsto) > DATE(p.aberto) THEN it.mini ELSE 0 END),0) e, COALESCE(SUM(it.mini),0) t
        FROM pc_itens it JOIN pc_pedidos p ON p.id = it.pedido_id WHERE p.excluido = 0 AND it.pai = 0 AND it.mini > 0 AND p.aberto BETWEEN :i AND :f', $p)[0];
    $novos = castelo_q($pdo, 'SELECT COUNT(*) n FROM (SELECT p.cliente_id, MIN(p.aberto) pr FROM pc_pedidos p WHERE p.excluido = 0 AND p.cliente_id IS NOT NULL
        AND EXISTS(SELECT 1 FROM pc_itens c WHERE c.pedido_id = p.id AND c.pai = 0 AND c.categoria = "cento") GROUP BY p.cliente_id) x WHERE pr BETWEEN :i AND :f', $p)[0]['n'];
    return ['v' => (float) $a['v'], 'n' => (float) $a['n'], 't' => $a['n'] > 0 ? $a['v'] / $a['n'] : 0, 'm' => (float) $m,
        'beb' => $c['n'] > 0 ? $c['b'] / $c['n'] * 100 : 0, 'centos' => (float) $c['n'],
        'cad' => $bl['n'] > 0 ? $bl['c'] / $bl['n'] * 100 : 0, 'enc' => $enc['t'] > 0 ? $enc['e'] / $enc['t'] * 100 : 0,
        'novos' => (float) $novos];
};
$r = $res($P);
$ra = $res($PA);
$ry = $res($PY);
$cmp = function (float $a, float $b, string $nome): string {
    if ($b <= 0) {
        return '<span class="pn-cmp"><span class="muted">' . $nome . ': sem dado</span></span>';
    }
    $v = ($a / $b - 1) * 100;
    return '<span class="pn-cmp"><b class="' . ($v >= 0 ? 'an-sobe' : 'an-desce') . '">' . ($v >= 0 ? '▲ ' : '▼ ') . castelo_num(abs($v)) . '%</b> ' . $nome . '</span>';
};

// Gráficos: 12 meses e 12 semanas
$nm = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
$porMes = [];
foreach (castelo_q($pdo, 'SELECT DATE_FORMAT(aberto, "%Y-%m") m, SUM(total - lanche_valor) v FROM pc_pedidos WHERE excluido = 0 AND so_lanche = 0
    AND aberto >= DATE_SUB(:f, INTERVAL 12 MONTH) AND aberto <= :f2 GROUP BY m ORDER BY m', ['f' => $hoje . ' 23:59:59', 'f2' => $hoje . ' 23:59:59']) as $x) {
    $porMes[$nm[(int) substr($x['m'], 5) - 1] . '/' . substr($x['m'], 2, 2)] = (float) $x['v'];
}
$porSem = [];
foreach (castelo_q($pdo, 'SELECT YEARWEEK(p.aberto, 3) s, MIN(DATE(p.aberto)) d, SUM(it.mini) m FROM pc_itens it JOIN pc_pedidos p ON p.id = it.pedido_id
    WHERE p.excluido = 0 AND it.pai = 0 AND it.mini > 0 AND p.aberto >= :i AND p.aberto <= :f2 GROUP BY s ORDER BY s', ['i' => date('Y-m-d', strtotime($hoje . ' -' . (date('N', strtotime($hoje)) - 1 + 77) . ' days')) . ' 00:00:00', 'f2' => $hoje . ' 23:59:59']) as $x) {
    $porSem[date('d/m', strtotime($x['d'] . ' -' . (date('N', strtotime($x['d'])) - 1) . ' days'))] = (float) $x['m'];
}

// Encomendas já marcadas pros próximos 7 dias (a partir do dia seguinte aos dados)
$prox = castelo_q($pdo, 'SELECT DATE(p.previsto) d, COUNT(DISTINCT p.id) n, COALESCE(SUM(it.mini),0) m FROM pc_pedidos p LEFT JOIN pc_itens it ON it.pedido_id = p.id AND it.pai = 0
    WHERE p.excluido = 0 AND p.agendado = 1 AND p.previsto > :f AND p.previsto <= DATE_ADD(:f2, INTERVAL 7 DAY) GROUP BY d ORDER BY d', ['f' => $hoje . ' 23:59:59', 'f2' => $hoje . ' 23:59:59']);

// Top produtos do período
$top = castelo_q($pdo, 'SELECT it.produto, SUM(it.qtd) q, SUM(it.valor) v FROM pc_itens it JOIN pc_pedidos p ON p.id = it.pedido_id
    WHERE p.excluido = 0 AND it.pai = 0 AND NOT ' . castelo_pc_lanche('it.') . ' AND p.aberto BETWEEN :i AND :f GROUP BY it.produto ORDER BY v DESC LIMIT 6', $P);
$topA = [];
foreach (castelo_q($pdo, 'SELECT it.produto, SUM(it.qtd) q FROM pc_itens it JOIN pc_pedidos p ON p.id = it.pedido_id WHERE p.excluido = 0 AND it.pai = 0 AND p.aberto BETWEEN :i AND :f GROUP BY it.produto', $PA) as $x) {
    $topA[$x['produto']] = (float) $x['q'];
}
$nd = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
$meta = function (float $v, float $alvo): string {
    return $v >= $alvo ? 'pn-ok' : ($v >= $alvo * 0.6 ? 'pn-atencao' : 'pn-ruim');
};
?>
<div class="pn">
  <header class="pn-topo">
    <div>
      <p class="intersect-kicker">Painel da reunião</p>
      <h1>Pede Coxinha · <?= h($rotulo) ?></h1>
      <p class="muted">Dados até <?= h(date('d/m/Y H:i', strtotime($ult))) ?> · comparado com os <?= h($rotA) ?> e com o mesmo período do ano passado · sem os lanches da Forasteiro</p>
    </div>
    <nav class="filter-bar pn-visao" aria-label="Visão">
      <a class="filter-chip <?= $visao === 'mes' ? 'is-active' : '' ?>" href="analise-painel.php?v=mes">Mês</a>
      <a class="filter-chip <?= $visao === 'semana' ? 'is-active' : '' ?>" href="analise-painel.php?v=semana">Semana</a>
      <button type="button" class="filter-chip" data-tela-cheia>Tela cheia</button>
      <a class="filter-chip" href="analise-pc.php">Análise detalhada →</a>
    </nav>
  </header>

  <section class="pn-kpis">
    <div class="pn-kpi pn-destaque"><span>Faturamento</span><strong><?= castelo_reais($r['v']) ?></strong><?= $cmp($r['v'], $ra['v'], 'vs anterior') ?><?= $cmp($r['v'], $ry['v'], 'vs ano passado') ?></div>
    <div class="pn-kpi"><span>Pedidos</span><strong><?= castelo_num($r['n']) ?></strong><?= $cmp($r['n'], $ra['n'], 'vs anterior') ?><?= $cmp($r['n'], $ry['n'], 'vs ano passado') ?></div>
    <div class="pn-kpi"><span>Ticket médio</span><strong><?= castelo_reais($r['t']) ?></strong><?= $cmp($r['t'], $ra['t'], 'vs anterior') ?><?= $cmp($r['t'], $ry['t'], 'vs ano passado') ?></div>
    <div class="pn-kpi"><span>Mini salgados</span><strong><?= castelo_num($r['m']) ?></strong><?= $cmp($r['m'], $ra['m'], 'vs anterior') ?><span class="pn-cmp"><?= castelo_num($r['enc']) ?>% por encomenda</span></div>
  </section>

  <section class="pn-meio">
    <div class="pn-card pn-graf">
      <h2>Faturamento · 12 meses (o último vai até hoje)</h2>
      <?= castelo_svg_barras($porMes, ['formato' => 'reais', 'largura' => 640, 'altura' => 200, 'min_deitado' => 40, 'titulo' => 'Faturamento 12 meses']) ?>
    </div>
    <div class="pn-card pn-graf">
      <h2>Mini salgados por semana · 12 semanas (a última vai até hoje)</h2>
      <?= castelo_svg_barras($porSem, ['largura' => 640, 'altura' => 200, 'min_deitado' => 40, 'titulo' => 'Mini salgados por semana']) ?>
    </div>
  </section>

  <section class="pn-baixo">
    <div class="pn-card">
      <h2>Encomendas marcadas · próximos 7 dias</h2>
      <?php if ($prox): ?>
        <ul class="pn-lista">
          <?php foreach ($prox as $x): ?><li><span><?= h($nd[(int) date('N', strtotime($x['d'])) - 1] . ' ' . date('d/m', strtotime($x['d']))) ?></span><b><?= (int) $x['n'] ?> pedido<?= (int) $x['n'] === 1 ? '' : 's' ?> · <?= castelo_num((float) $x['m']) ?> mini</b></li><?php endforeach; ?>
        </ul>
      <?php else: ?><p class="muted">Nenhuma encomenda marcada no arquivo importado.</p><?php endif; ?>
    </div>
    <div class="pn-card">
      <h2>Mais vendidos</h2>
      <ul class="pn-lista">
        <?php foreach ($top as $x): ?><li><span><?= h($x['produto']) ?></span><b><?= castelo_reais((float) $x['v']) ?> <?= castelo_var((float) $x['q'], $topA[$x['produto']] ?? 0) ?></b></li><?php endforeach; ?>
      </ul>
    </div>
    <div class="pn-card">
      <h2>Indicadores com meta</h2>
      <ul class="pn-metas">
        <li class="<?= $meta($r['novos'], max(1, $ra['novos'])) ?>"><span>Clientes novos de cento</span><b><?= castelo_num($r['novos']) ?></b><small>anterior: <?= castelo_num($ra['novos']) ?></small></li>
        <li class="<?= $meta($r['beb'], 25) ?>"><span>Centos com bebida</span><b><?= castelo_num($r['beb']) ?>%</b><small>meta: 25%</small></li>
        <li class="<?= $meta($r['cad'], 30) ?>"><span>Balcão com cliente cadastrado</span><b><?= castelo_num($r['cad']) ?>%</b><small>meta: 30%</small></li>
      </ul>
    </div>
  </section>
</div>
<?php destrava_admin_foot();
