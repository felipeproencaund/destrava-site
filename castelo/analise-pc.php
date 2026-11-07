<?php
/** Castelo: análise da Pede Coxinha (dados do Consumer). */
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/analises.php';
destrava_admin_require_login();
$pdo = destrava_db();

$ult = castelo_an_info($pdo, 'pede-coxinha', 'ultimo_pedido');
destrava_admin_head('Pede Coxinha · Análises', 'analises');
if (!$ult): ?>
  <div class="page-head"><div><p class="intersect-kicker">Análises</p><h1>Pede Coxinha</h1></div></div>
  <p class="empty-state">Ainda não tem dados. <a href="analise-importar.php">Importe o arquivo do Consumer</a> pra começar.</p>
<?php destrava_admin_foot(); exit; endif;

// Período: 30/90/180/365 dias até o último pedido, todo o período, ou datas escolhidas
$fimD = date('Y-m-d', strtotime($ult));
$primeiroD = date('Y-m-d', strtotime((string) castelo_q($pdo, 'SELECT MIN(aberto) d FROM pc_pedidos WHERE excluido = 0')[0]['d']));
$okData = function ($v): bool {
    return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) !== false;
};
$modo = $_GET['p'] ?? '90';
$de = $_GET['de'] ?? '';
$ate = $_GET['ate'] ?? '';
if ($okData($de) && $okData($ate) && $de <= $ate) {
    $modo = 'pers';
    $iniD = $de;
    $fimD = $ate;
} elseif ($modo === 'tudo') {
    $iniD = $primeiroD;
} else {
    $modo = in_array((int) $modo, [30, 90, 180, 365], true) ? (string) (int) $modo : '90';
    $iniD = date('Y-m-d', strtotime($fimD . ' -' . ((int) $modo - 1) . ' days'));
}
$dias = (int) round((strtotime($fimD) - strtotime($iniD)) / 86400) + 1;
$fim = $fimD . ' 23:59:59';
$ini = $iniD . ' 00:00:00';
$temAnt = $modo !== 'tudo' && $iniD > $primeiroD;
$iniA = date('Y-m-d 00:00:00', strtotime($iniD . ' -' . $dias . ' days'));
$fimA = date('Y-m-d 23:59:59', strtotime($iniD . ' -1 day'));
$P = ['i' => $ini, 'f' => $fim];
$PA = ['i' => $iniA, 'f' => $fimA];
$sem = max(1, $dias) / 7;
$var = function (float $a, float $b) use ($temAnt): string {
    return $temAnt ? castelo_var($a, $b) : '<span class="muted">–</span>';
};

// KPIs do período e do anterior
$kpi = function (array $p) use ($pdo): array {
    $a = castelo_q($pdo, 'SELECT COUNT(*) n, COALESCE(SUM(total - lanche_valor),0) v FROM pc_pedidos WHERE excluido = 0 AND so_lanche = 0 AND aberto BETWEEN :i AND :f', $p)[0];
    $m = castelo_q($pdo, 'SELECT COALESCE(SUM(it.mini),0) m FROM pc_itens it JOIN pc_pedidos p ON p.id = it.pedido_id WHERE p.excluido = 0 AND it.pai = 0 AND NOT ' . castelo_pc_lanche('it.') . ' AND p.aberto BETWEEN :i AND :f', $p)[0];
    return ['n' => (float) $a['n'], 'v' => (float) $a['v'], 'm' => (float) $m['m'], 't' => $a['n'] > 0 ? $a['v'] / $a['n'] : 0];
};
$k = $kpi($P);
$ka = $kpi($PA);

// Faturamento por mês (18 meses)
$mesesRows = castelo_q($pdo, 'SELECT DATE_FORMAT(aberto, "%Y-%m") m, SUM(total - lanche_valor) v FROM pc_pedidos WHERE excluido = 0 AND so_lanche = 0 AND aberto >= DATE_SUB(:f, INTERVAL 18 MONTH) GROUP BY m ORDER BY m', ['f' => $fim]);
$nm = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
$porMes = [];
foreach ($mesesRows as $r) {
    $porMes[$nm[(int) substr($r['m'], 5) - 1] . '/' . substr($r['m'], 2, 2)] = (float) $r['v'];
}

// Mini salgados por semana: semana normal x pedidos grandes (300+)
$semRows = castelo_q($pdo, 'SELECT YEARWEEK(p.aberto, 3) s, MIN(DATE(p.aberto)) d, SUM(CASE WHEN x.m >= 300 THEN x.m ELSE 0 END) g, SUM(CASE WHEN x.m < 300 THEN x.m ELSE 0 END) nml
    FROM (SELECT pedido_id, SUM(mini) m FROM pc_itens WHERE pai = 0 AND mini > 0 GROUP BY pedido_id) x JOIN pc_pedidos p ON p.id = x.pedido_id
    WHERE p.excluido = 0 AND p.aberto >= DATE_SUB(:f, INTERVAL 26 WEEK) AND p.aberto <= :f2 GROUP BY s ORDER BY s', ['f' => $fim, 'f2' => $fim]);
$porSemana = [];
foreach ($semRows as $r) {
    $porSemana[date('d/m', strtotime($r['d'] . ' -' . (date('N', strtotime($r['d'])) - 1) . ' days'))] = [(float) $r['nml'], (float) $r['g']];
}

// Encomenda x do dia (mini salgados)
$enc = castelo_q($pdo, 'SELECT CASE WHEN p.agendado = 1 AND p.previsto IS NOT NULL THEN GREATEST(DATEDIFF(p.previsto, p.aberto), 0) ELSE -1 END a, SUM(it.mini) m
    FROM pc_itens it JOIN pc_pedidos p ON p.id = it.pedido_id WHERE p.excluido = 0 AND it.pai = 0 AND it.mini > 0 AND p.aberto BETWEEN :i AND :f GROUP BY a', $P);
$faixas = ['Pronta entrega' => 0, 'Agendado pro mesmo dia' => 0, 'Véspera' => 0, '2 a 3 dias antes' => 0, '4 a 6 dias antes' => 0, '7 dias ou mais' => 0];
foreach ($enc as $r) {
    $a = (int) $r['a'];
    $key = $a < 0 ? 'Pronta entrega' : ($a === 0 ? 'Agendado pro mesmo dia' : ($a === 1 ? 'Véspera' : ($a <= 3 ? '2 a 3 dias antes' : ($a <= 6 ? '4 a 6 dias antes' : '7 dias ou mais'))));
    $faixas[$key] += (float) $r['m'];
}
$totMini = max(1, array_sum($faixas));
$encomenda = $totMini - $faixas['Pronta entrega'] - $faixas['Agendado pro mesmo dia'];

// Dias da semana
$dsRows = castelo_q($pdo, 'SELECT WEEKDAY(aberto) d, SUM(total - lanche_valor) v, COUNT(*) n, COUNT(DISTINCT DATE(aberto)) dias FROM pc_pedidos WHERE excluido = 0 AND so_lanche = 0 AND aberto BETWEEN :i AND :f GROUP BY d ORDER BY d', $P);
$nd = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];

// Produtos
$prod = castelo_q($pdo, 'SELECT it.produto, it.categoria, SUM(it.qtd) q, SUM(it.valor) v FROM pc_itens it JOIN pc_pedidos p ON p.id = it.pedido_id
    WHERE p.excluido = 0 AND it.pai = 0 AND NOT ' . castelo_pc_lanche('it.') . ' AND p.aberto BETWEEN :i AND :f GROUP BY it.produto, it.categoria ORDER BY v DESC', $P);
$prodA = [];
foreach (castelo_q($pdo, 'SELECT it.produto, SUM(it.qtd) q FROM pc_itens it JOIN pc_pedidos p ON p.id = it.pedido_id WHERE p.excluido = 0 AND it.pai = 0 AND NOT ' . castelo_pc_lanche('it.') . ' AND p.aberto BETWEEN :i AND :f GROUP BY it.produto', $PA) as $r) {
    $prodA[$r['produto']] = (float) $r['q'];
}
$primeira = [];
foreach (castelo_q($pdo, 'SELECT it.produto, MIN(p.aberto) d FROM pc_itens it JOIN pc_pedidos p ON p.id = it.pedido_id WHERE p.excluido = 0 AND it.pai = 0 AND NOT ' . castelo_pc_lanche('it.') . ' GROUP BY it.produto') as $r) {
    $primeira[$r['produto']] = $r['d'];
}
// Voltaram: venderam nos últimos 60 dias depois de pelo menos 60 dias parados
$voltou = [];
foreach (castelo_q($pdo, 'SELECT it.produto, MAX(CASE WHEN p.aberto < DATE_SUB(:f, INTERVAL 60 DAY) THEN p.aberto END) antes,
    MIN(CASE WHEN p.aberto >= DATE_SUB(:f2, INTERVAL 60 DAY) THEN p.aberto END) depois
    FROM pc_itens it JOIN pc_pedidos p ON p.id = it.pedido_id WHERE p.excluido = 0 AND it.pai = 0 AND NOT ' . castelo_pc_lanche('it.') . ' AND p.aberto <= :f3 GROUP BY it.produto', ['f' => $fim, 'f2' => $fim, 'f3' => $fim]) as $r) {
    if ($r['antes'] && $r['depois'] && (strtotime($r['depois']) - strtotime($r['antes'])) >= 60 * 86400) {
        $voltou[$r['produto']] = true;
    }
}
$novo = function (string $p) use ($primeira, $fimD, $voltou): bool {
    return isset($voltou[$p]) || (isset($primeira[$p]) && strtotime($primeira[$p]) >= strtotime($fimD . ' -60 days'));
};
$totProd = max(1, array_sum(array_column($prod, 'v')));
$cats = [];
foreach ($prod as $r) {
    $cats[$r['categoria'] ?: 'outros'] = ($cats[$r['categoria'] ?: 'outros'] ?? 0) + (float) $r['v'];
}
arsort($cats);
$caindo = array_filter($prod, function ($r) use ($prodA) {
    $a = $prodA[$r['produto']] ?? 0;
    return $a >= 20 && $r['q'] < $a * 0.8;
});
usort($caindo, function ($a, $b) use ($prodA) {
    return ($a['q'] / $prodA[$a['produto']]) <=> ($b['q'] / $prodA[$b['produto']]);
});
// Fora da lista de "vendem pouco": centos, combos e lanches gourmet (linha de hambúrguer)
$foraPoucos = function (array $r): bool {
    return in_array($r['categoria'], ['cento', 'hambúrguer'], true)
        || preg_match('/combo|burger|is life|classic|rogers|bufalo|costela prime/i', $r['produto']);
};
$poucos = array_values(array_filter($prod, function ($r) use ($novo, $sem, $foraPoucos) {
    return $r['q'] / $sem < 1 && !$novo($r['produto']) && !$foraPoucos($r);
}));
usort($poucos, function ($a, $b) {
    return $a['q'] <=> $b['q'];
});
$novidades = array_filter($prod, function ($r) use ($novo) {
    return $novo($r['produto']);
});
$batatas = array_values(array_filter($prod, function ($r) {
    return $r['categoria'] === 'batata recheada' && !preg_match('/simples|palha/i', $r['produto']);
}));

// Clientes
$novosRows = castelo_q($pdo, 'SELECT DATE_FORMAT(pr, "%Y-%m") m, COUNT(*) n FROM (SELECT cliente_id, MIN(aberto) pr FROM pc_pedidos WHERE excluido = 0 AND so_lanche = 0 AND cliente_id IS NOT NULL GROUP BY cliente_id) x
    WHERE pr >= DATE_SUB(:f, INTERVAL 14 MONTH) GROUP BY m ORDER BY m', ['f' => $fim]);
$novosMes = [];
foreach ($novosRows as $r) {
    $novosMes[$nm[(int) substr($r['m'], 5) - 1] . '/' . substr($r['m'], 2, 2)] = (float) $r['n'];
}
$cliBase = castelo_q($pdo, 'SELECT c.id, c.nome, c.fone, c.bairro, COUNT(*) n, SUM(p.total - p.lanche_valor) v, MAX(p.aberto) u,
    SUM(EXISTS(SELECT 1 FROM pc_itens it WHERE it.pedido_id = p.id AND it.categoria = "cento" AND it.pai = 0)) centos
    FROM pc_pedidos p JOIN pc_clientes c ON c.id = p.cliente_id WHERE p.excluido = 0 AND p.so_lanche = 0 AND p.aberto <= :f GROUP BY c.id, c.nome, c.fone, c.bairro', ['f' => $fim]);
$status = ['Ativos (2+ compras, até 60 dias)' => [0, 0], 'Esfriando (61 a 180 dias)' => [0, 0], 'Sumiram (mais de 180 dias)' => [0, 0], 'Novos (1 compra, até 60 dias)' => [0, 0], '1 compra antiga' => [0, 0]];
$esfriando = [];
foreach ($cliBase as $c) {
    $ds = (int) floor((strtotime($fim) - strtotime($c['u'])) / 86400);
    $s = $c['n'] >= 2 ? ($ds <= 60 ? 'Ativos (2+ compras, até 60 dias)' : ($ds <= 180 ? 'Esfriando (61 a 180 dias)' : 'Sumiram (mais de 180 dias)')) : ($ds <= 60 ? 'Novos (1 compra, até 60 dias)' : '1 compra antiga');
    $status[$s][0]++;
    $status[$s][1] += (float) $c['v'];
    if ($s === 'Esfriando (61 a 180 dias)' && $c['centos'] > 0) {
        $c['dias'] = $ds;
        $esfriando[] = $c;
    }
}
usort($esfriando, function ($a, $b) {
    return $b['v'] <=> $a['v'];
});
$canais = castelo_q($pdo, 'SELECT canal, COUNT(*) n, SUM(cliente_id IS NOT NULL) c, SUM(total - lanche_valor) v FROM pc_pedidos WHERE excluido = 0 AND so_lanche = 0 AND aberto BETWEEN :i AND :f GROUP BY canal ORDER BY v DESC', $P);

// Escolas (últimos 12 meses)
$escolas = castelo_q($pdo, 'SELECT p.escola, COUNT(*) n, SUM(p.total - p.lanche_valor) v, COALESCE(SUM(x.m),0) m, MAX(p.aberto) u FROM pc_pedidos p
    LEFT JOIN (SELECT pedido_id, SUM(mini) m FROM pc_itens WHERE pai = 0 GROUP BY pedido_id) x ON x.pedido_id = p.id
    WHERE p.excluido = 0 AND p.so_lanche = 0 AND p.escola IS NOT NULL AND p.aberto >= DATE_SUB(:f, INTERVAL 12 MONTH) GROUP BY p.escola ORDER BY v DESC', ['f' => $fim]);
$escMes = [];
foreach (castelo_q($pdo, 'SELECT DATE_FORMAT(p.aberto, "%Y-%m") mm, COALESCE(SUM(x.m),0) m FROM pc_pedidos p LEFT JOIN (SELECT pedido_id, SUM(mini) m FROM pc_itens WHERE pai = 0 GROUP BY pedido_id) x ON x.pedido_id = p.id
    WHERE p.excluido = 0 AND p.so_lanche = 0 AND p.escola IS NOT NULL AND p.aberto >= DATE_SUB(:f, INTERVAL 14 MONTH) GROUP BY mm ORDER BY mm', ['f' => $fim]) as $r) {
    $escMes[$nm[(int) substr($r['mm'], 5) - 1] . '/' . substr($r['mm'], 2, 2)] = (float) $r['m'];
}

// Pagamentos, exclusões e notas
$pag = castelo_q($pdo, 'SELECT IF(pagamento = "", "Não informado", pagamento) f, COUNT(*) n, SUM(total - lanche_valor) v FROM pc_pedidos WHERE excluido = 0 AND so_lanche = 0 AND aberto BETWEEN :i AND :f GROUP BY f ORDER BY v DESC', $P);
$exc = castelo_q($pdo, 'SELECT COUNT(*) n, COALESCE(SUM(total),0) v FROM pc_pedidos WHERE excluido = 1 AND so_lanche = 0 AND aberto BETWEEN :i AND :f', $P)[0];
$notas = castelo_an_info($pdo, 'pede-coxinha', 'notas_problema') ?: [];
$nomeNota = ['0' => 'Rejeitadas', '8888' => 'Indefinidas', '999' => 'Em contingência (off-line)'];
$imp = castelo_q($pdo, 'SELECT quando FROM an_importacoes WHERE empresa = "pede-coxinha" ORDER BY id DESC LIMIT 1')[0]['quando'] ?? null;
$pct = function (float $a, float $b): string {
    return $b > 0 ? castelo_num($a / $b * 100) . '%' : '–';
};
$wa = function (string $f): string {
    $d = preg_replace('/\D/', '', $f);
    return 'https://wa.me/' . (strlen($d) <= 11 ? '55' : '') . $d;
};
?>
<div class="page-head fin-head">
  <div>
    <p class="intersect-kicker">Análises</p>
    <h1>Pede Coxinha</h1>
    <p class="muted">Sem os lanches da Forasteiro (prensado, hot dog, hambúrguer artesanal e milkshake). Dados do Consumer até <?= h(date('d/m/Y H:i', strtotime($ult))) ?><?= $imp ? ' · importado em ' . h(date('d/m/Y', strtotime($imp))) : '' ?>. Período: <?= h(date('d/m/Y', strtotime($ini))) ?> a <?= h(date('d/m/Y', strtotime($fim))) ?> (<?= castelo_num($dias) ?> dias)<?= $temAnt ? ', comparado com os ' . castelo_num($dias) . ' dias anteriores' : ', sem comparação' ?>.</p>
  </div>
  <div class="fin-mes"><a class="btn-sec" href="analise-importar.php">Importar dados</a></div>
</div>
<nav class="filter-bar" aria-label="Período">
  <?php foreach (['30' => '30 dias', '90' => '90 dias', '180' => '6 meses', '365' => '12 meses', 'tudo' => 'Todo o período'] as $d => $l): ?>
    <a class="filter-chip <?= (string) $d === $modo ? 'is-active' : '' ?>" href="analise-pc.php?p=<?= h((string) $d) ?>"><?= h($l) ?></a>
  <?php endforeach; ?>
</nav>
<form method="get" class="an-periodo <?= $modo === 'pers' ? 'is-active' : '' ?>" action="analise-pc.php">
  <span class="kpi-rotulo">Período personalizado</span>
  <label>De <input type="date" name="de" value="<?= h($iniD) ?>" min="<?= h($primeiroD) ?>" required></label>
  <label>Até <input type="date" name="ate" value="<?= h($fimD) ?>" min="<?= h($primeiroD) ?>" required></label>
  <button type="submit" class="btn-sec">Ver</button>
</form>

<section class="kpis">
  <div class="kpi"><span class="kpi-rotulo">Faturamento</span><strong class="valor-entrada"><?= castelo_reais($k['v']) ?></strong><span class="kpi-nota"><?= $temAnt ? $var($k['v'], $ka['v']) . ' · antes ' . castelo_reais($ka['v']) : castelo_reais($k['v'] / max(1, $dias)) . ' por dia' ?></span></div>
  <div class="kpi"><span class="kpi-rotulo">Pedidos</span><strong><?= castelo_num($k['n']) ?></strong><span class="kpi-nota"><?= $temAnt ? $var($k['n'], $ka['n']) . ' · antes ' . castelo_num($ka['n']) : castelo_num($k['n'] / max(1, $dias), 1) . ' por dia' ?></span></div>
  <div class="kpi"><span class="kpi-rotulo">Ticket médio</span><strong><?= castelo_reais($k['t']) ?></strong><span class="kpi-nota"><?= $temAnt ? $var($k['t'], $ka['t']) . ' · antes ' . castelo_reais($ka['t']) : 'média do período' ?></span></div>
  <div class="kpi"><span class="kpi-rotulo">Mini salgados por semana</span><strong><?= castelo_num($k['m'] / $sem) ?></strong><span class="kpi-nota"><?= $temAnt ? $var($k['m'], $ka['m']) . ' · ' : '' ?>em cento e meio cento</span></div>
</section>

<section class="painel-card">
  <h2>Faturamento por mês</h2>
  <div class="fin-grafico-wrap"><?= castelo_svg_barras($porMes, ['formato' => 'reais', 'titulo' => 'Faturamento por mês']) ?></div>
</section>

<div class="painel-grade an-duas">
  <section class="painel-card">
    <h2>Encomenda × do dia</h2>
    <p class="muted">Mini salgados de cento no período. Encomenda é o pedido feito pelo menos um dia antes.</p>
    <div class="an-split">
      <div><span class="kpi-rotulo">Encomenda</span><strong><?= $pct($encomenda, $totMini) ?></strong></div>
      <div><span class="kpi-rotulo">Do dia</span><strong><?= $pct($totMini - $encomenda, $totMini) ?></strong></div>
    </div>
    <ul class="lista-simples">
      <?php foreach ($faixas as $f => $v): ?><li><span><?= h($f) ?></span><strong><?= castelo_num($v) ?> · <?= $pct($v, $totMini) ?></strong></li><?php endforeach; ?>
    </ul>
  </section>
  <section class="painel-card">
    <h2>Dias da semana</h2>
    <p class="muted">Média por dia aberto no período.</p>
    <ul class="lista-simples">
      <?php foreach ($dsRows as $r): ?><li><span><?= h($nd[(int) $r['d']]) ?> <small class="muted">(<?= (int) $r['dias'] ?> dias)</small></span><strong><?= castelo_reais($r['v'] / max(1, $r['dias'])) ?> · <?= castelo_num($r['n'] / max(1, $r['dias']), 1) ?> pedidos</strong></li><?php endforeach; ?>
    </ul>
  </section>
</div>

<section class="painel-card">
  <h2>Mini salgados por semana</h2>
  <p class="muted"><span class="an-leg an-leg-ouro"></span> semana normal · <span class="an-leg an-leg-verde"></span> pedidos grandes (300 ou mais, geralmente escola e evento). Últimas 26 semanas.</p>
  <div class="fin-grafico-wrap"><?= castelo_svg_barras($porSemana, ['series' => ['semana normal', 'pedidos grandes'], 'titulo' => 'Mini salgados por semana']) ?></div>
</section>

<section class="painel-card">
  <h2>Produtos</h2>
  <div class="an-cats">
    <?php foreach ($cats as $c => $v): ?><span class="filter-chip"><?= h(ucfirst($c)) ?> · <?= $pct($v, $totProd) ?></span><?php endforeach; ?>
  </div>
  <div class="fin-tabela"><table>
    <thead><tr><th>Produto</th><th>Unidades</th><th>Por semana</th><th>Faturamento</th><th>%</th><th>vs. antes</th></tr></thead>
    <tbody>
    <?php foreach (array_slice($prod, 0, 25) as $r): ?>
      <tr><td><?= h($r['produto']) ?><?= $novo($r['produto']) ? ' <span class="an-novo">novidade</span>' : '' ?></td><td><?= castelo_num((float) $r['q']) ?></td><td><?= castelo_num($r['q'] / $sem, 1) ?></td><td><?= castelo_reais((float) $r['v']) ?></td><td><?= $pct((float) $r['v'], $totProd) ?></td><td><?= $var((float) $r['q'], $prodA[$r['produto']] ?? 0) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</section>

<div class="painel-grade an-tres">
  <section class="painel-card">
    <h2>Caindo</h2>
    <p class="muted">Venderam 20% menos que no período anterior.</p>
    <ul class="lista-simples an-rolagem">
      <?php foreach ($caindo as $r): ?><li><span><?= h($r['produto']) ?></span><strong><?= $var((float) $r['q'], $prodA[$r['produto']]) ?></strong></li><?php endforeach; ?>
      <?php if (!$caindo): ?><li><span class="muted"><?= $temAnt ? 'Nenhum produto caiu mais de 20%.' : 'Sem período anterior pra comparar.' ?></span></li><?php endif; ?>
    </ul>
  </section>
  <section class="painel-card">
    <h2>Novidades e retornos</h2>
    <p class="muted">Começaram a vender há menos de 60 dias, ou voltaram depois de 60 dias parados. Acompanhar, não cortar.</p>
    <ul class="lista-simples an-rolagem">
      <?php foreach ($novidades as $r): ?><li><span><?= h($r['produto']) ?><?= isset($voltou[$r['produto']]) ? ' <span class="an-novo">voltou</span>' : '' ?></span><strong><?= castelo_num((float) $r['q']) ?> un.</strong></li><?php endforeach; ?>
      <?php if (!$novidades): ?><li><span class="muted">Nenhum produto novo.</span></li><?php endif; ?>
    </ul>
  </section>
  <section class="painel-card">
    <h2>Vendem pouco</h2>
    <p class="muted">Menos de 1 por semana, vendendo há mais de 60 dias sem parar. Sem combos e lanches gourmet. <?= count($poucos) ?> produtos.</p>
    <ul class="lista-simples an-rolagem">
      <?php foreach ($poucos as $r): ?><li><span><?= h($r['produto']) ?></span><strong><?= castelo_num((float) $r['q']) ?> un.</strong></li><?php endforeach; ?>
      <?php if (!$poucos): ?><li><span class="muted">Nenhum produto.</span></li><?php endif; ?>
    </ul>
  </section>
</div>

<?php if ($batatas): $totB = max(1, array_sum(array_column($batatas, 'q'))); ?>
<section class="painel-card">
  <h2>Batata recheada</h2>
  <p class="muted"><?= castelo_num($totB / $sem, 1) ?> por semana no período.</p>
  <ul class="lista-simples">
    <?php foreach ($batatas as $r): ?><li><span><?= h(preg_replace('/^Batata /', '', $r['produto'])) ?></span><strong><?= castelo_num((float) $r['q']) ?> · <?= $pct((float) $r['q'], $totB) ?> · <?= $var((float) $r['q'], $prodA[$r['produto']] ?? 0) ?></strong></li><?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<div class="painel-grade an-duas">
  <section class="painel-card">
    <h2>Clientes novos por mês</h2>
    <p class="muted">Primeira compra de clientes cadastrados (encomenda e entrega).</p>
    <div class="fin-grafico-wrap"><?= castelo_svg_barras($novosMes, ['largura' => 420, 'altura' => 260, 'titulo' => 'Clientes novos por mês']) ?></div>
  </section>
  <section class="painel-card">
    <h2>Situação dos clientes</h2>
    <ul class="lista-simples">
      <?php foreach ($status as $s => [$n, $v]): ?><li><span><?= h($s) ?></span><strong><?= castelo_num($n) ?> · <?= castelo_reais($v) ?></strong></li><?php endforeach; ?>
    </ul>
    <h3 class="fin-sub">Cadastro por tipo de pedido</h3>
    <ul class="lista-simples">
      <?php foreach ($canais as $r): ?><li><span><?= h(['balcao' => 'Balcão', 'entrega' => 'Entrega', 'retirada' => 'Retirada'][$r['canal']] ?? $r['canal']) ?> <small class="muted">(<?= castelo_num((float) $r['n']) ?> pedidos)</small></span><strong><?= $pct((float) $r['c'], (float) $r['n']) ?> com cliente</strong></li><?php endforeach; ?>
    </ul>
  </section>
</div>

<section class="painel-card">
  <h2>Clientes de cento esfriando</h2>
  <p class="muted">Compraram cento, têm 2 ou mais pedidos e estão sem comprar há 61 a 180 dias. Os 30 que mais gastaram; total na lista: <?= count($esfriando) ?>.</p>
  <div class="fin-tabela"><table>
    <thead><tr><th>Cliente</th><th>Bairro</th><th>Pedidos</th><th>Já gastou</th><th>Sem comprar</th><th></th></tr></thead>
    <tbody>
    <?php foreach (array_slice($esfriando, 0, 30) as $c): ?>
      <tr><td><?= h($c['nome']) ?></td><td><?= h(mb_strlen(trim($c['bairro'], ' .-')) >= 3 ? $c['bairro'] : '') ?></td><td><?= (int) $c['n'] ?></td><td><?= castelo_reais((float) $c['v']) ?></td><td><?= (int) $c['dias'] ?> dias</td>
      <td><?php if (strlen($c['fone']) >= 10): ?><a class="btn-whatsapp btn-sm" href="<?= h($wa($c['fone'])) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</section>

<div class="painel-grade an-duas">
  <section class="painel-card">
    <h2>Escolas · últimos 12 meses</h2>
    <p class="muted">Achadas pelo endereço de entrega e pelas observações. Retirada na loja pra escola não aparece aqui.</p>
    <ul class="lista-simples">
      <?php foreach ($escolas as $r): ?><li><span><?= h($r['escola']) ?> <small class="muted">(<?= (int) $r['n'] ?> pedidos · último <?= h(date('d/m', strtotime($r['u']))) ?>)</small></span><strong><?= castelo_reais((float) $r['v']) ?></strong></li><?php endforeach; ?>
      <?php if (!$escolas): ?><li><span class="muted">Nenhum pedido de escola.</span></li><?php endif; ?>
    </ul>
  </section>
  <section class="painel-card">
    <h2>Mini salgados pra escolas por mês</h2>
    <div class="fin-grafico-wrap"><?= castelo_svg_barras($escMes, ['largura' => 420, 'altura' => 260, 'titulo' => 'Mini salgados pra escolas por mês']) ?></div>
  </section>
</div>

<div class="painel-grade an-tres">
  <section class="painel-card">
    <h2>Formas de pagamento</h2>
    <ul class="lista-simples">
      <?php foreach ($pag as $r): ?><li><span><?= h($r['f']) ?></span><strong><?= $pct((float) $r['v'], $k['v']) ?></strong></li><?php endforeach; ?>
    </ul>
  </section>
  <section class="painel-card">
    <h2>Pedidos excluídos</h2>
    <p class="an-grande"><?= castelo_num((float) $exc['n']) ?></p>
    <p class="muted">no período, somando <?= castelo_reais((float) $exc['v']) ?>. Pode ser correção de lançamento; se subir muito, vale conferir.</p>
  </section>
  <section class="painel-card">
    <h2>Notas fiscais com problema</h2>
    <ul class="lista-simples">
      <?php foreach ($notas as $s => $r): ?><li><span><?= h($nomeNota[(string) $s] ?? 'Situação ' . $s) ?></span><strong><?= (int) $r['n'] ?> · <?= castelo_reais((float) $r['v']) ?></strong></li><?php endforeach; ?>
      <?php if (!$notas): ?><li><span class="muted">Nenhuma pendência.</span></li><?php endif; ?>
    </ul>
    <p class="muted">Resolver no Consumer com o contador ou o suporte da RAL.</p>
  </section>
</div>
<?php destrava_admin_foot();
