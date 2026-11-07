<?php
/** Castelo: análise de um produto ou categoria da Pede Coxinha. */
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/analises.php';
destrava_admin_require_login();
$pdo = destrava_db();

$ult = castelo_an_info($pdo, 'pede-coxinha', 'ultimo_pedido');
if (isset($_GET['sel']) && is_string($_GET['sel']) && strpos($_GET['sel'], '|') !== false) {
    [$t, $v] = explode('|', $_GET['sel'], 2);
    $pp = (string) ($_GET['p'] ?? '90');
    header('Location: analise-produto.php?' . http_build_query(($t === 'prod' ? ['prod' => $v] : ['cat' => $v]) + ['p' => $pp]));
    exit;
}
destrava_admin_head('Pede Coxinha · Produto', 'analises');
if (!$ult): ?>
  <p class="empty-state">Ainda não tem dados. <a href="analise-importar.php">Importe o arquivo do Consumer</a>.</p>
<?php destrava_admin_foot(); exit; endif;

$pe = castelo_pc_periodo($pdo, $ult);
['dias' => $dias, 'ini' => $ini, 'fim' => $fim, 'temAnt' => $temAnt, 'P' => $P, 'PA' => $PA, 'sem' => $sem, 'modo' => $modo] = $pe;
$semLanche = 'NOT ' . castelo_pc_lanche('it.');

// Lista pra escolher: categorias e produtos (só o que já vendeu, sem lanches)
$lista = castelo_q($pdo, 'SELECT it.categoria c, it.produto pr, SUM(it.qtd) q FROM pc_itens it JOIN pc_pedidos p ON p.id = it.pedido_id
    WHERE p.excluido = 0 AND it.pai = 0 AND ' . $semLanche . ' GROUP BY it.categoria, it.produto ORDER BY it.categoria, q DESC');
$cats = [];
$prods = [];
foreach ($lista as $r) {
    $cats[$r['c']][] = $r['pr'];
    $prods[$r['pr']] = $r['c'];
}

$cat = (string) ($_GET['cat'] ?? '');
$prod = (string) ($_GET['prod'] ?? '');
if ($prod !== '' && isset($prods[$prod])) {
    $cond = 'it.produto = :x';
    $x = $prod;
    $titulo = $prod;
    $cat = '';
    $extra = ['prod' => $prod];
} elseif ($cat !== '' && isset($cats[$cat])) {
    $cond = 'it.categoria = :x' . ($cat === 'batata recheada' ? " AND it.produto NOT REGEXP 'simples|palha'" : '');
    $x = $cat;
    $titulo = ucfirst($cat);
    $prod = '';
    $extra = ['cat' => $cat];
} else {
    $cond = null;
    $extra = [];
}
$sel = $prod !== '' ? 'prod|' . $prod : ($cat !== '' ? 'cat|' . $cat : '');
?>
<div class="page-head fin-head">
  <div>
    <p class="intersect-kicker">Análises · Pede Coxinha</p>
    <h1><?= $cond ? h($titulo) : 'Analisar produto' ?></h1>
    <p class="muted"><?= h(castelo_pc_periodo_texto($pe)) ?> Sem os lanches da Forasteiro.</p>
  </div>
  <div class="fin-mes"><a class="btn-sec" href="analise-pc.php?p=<?= h($modo === 'pers' ? '90' : $modo) ?>">← Visão geral</a></div>
</div>

<form method="get" class="an-escolha" action="analise-produto.php">
  <input type="hidden" name="p" value="<?= h($modo === 'pers' ? '90' : $modo) ?>">
  <label class="kpi-rotulo" for="an-sel">Produto ou categoria</label>
  <select id="an-sel" name="sel" data-envia-sozinho-select>
    <option value="">Escolha…</option>
    <optgroup label="Categorias inteiras">
      <?php foreach (array_keys($cats) as $c): ?><option value="cat|<?= h($c) ?>" <?= $sel === 'cat|' . $c ? 'selected' : '' ?>><?= h(ucfirst($c)) ?> (todos)</option><?php endforeach; ?>
    </optgroup>
    <?php foreach ($cats as $c => $ps): ?>
      <optgroup label="<?= h(ucfirst($c)) ?>">
        <?php foreach ($ps as $pr): ?><option value="prod|<?= h($pr) ?>" <?= $sel === 'prod|' . $pr ? 'selected' : '' ?>><?= h($pr) ?></option><?php endforeach; ?>
      </optgroup>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn-sec">Abrir</button>
</form>

<?php if (!$cond): ?>
  <p class="empty-state">Escolha um produto ou uma categoria na lista acima.</p>
<?php destrava_admin_foot(); exit; endif;

echo castelo_pc_periodo_html($pe, 'analise-produto.php', $extra);

$q = function (string $sql, array $p = []) use ($pdo, $x): array {
    return castelo_q($pdo, $sql, $p + ['x' => $x]);
};
$base = 'FROM pc_itens it JOIN pc_pedidos p ON p.id = it.pedido_id WHERE p.excluido = 0 AND it.pai = 0 AND ' . $semLanche . ' AND ' . $cond;
$nm = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
$nd = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
$pct = function (float $a, float $b): string {
    return $b > 0 ? castelo_num($a / $b * 100) . '%' : '–';
};
$var = function (float $a, float $b) use ($temAnt): string {
    return $temAnt ? castelo_var($a, $b) : '<span class="muted">–</span>';
};
$canalNome = ['balcao' => 'Balcão', 'entrega' => 'Entrega', 'retirada' => 'Retirada'];

$k = $q('SELECT COALESCE(SUM(it.qtd),0) q, COALESCE(SUM(it.valor),0) v, COUNT(DISTINCT p.id) n, COALESCE(SUM(it.mini),0) m ' . $base . ' AND p.aberto BETWEEN :i AND :f', $P)[0];
$ka = $q('SELECT COALESCE(SUM(it.qtd),0) q, COALESCE(SUM(it.valor),0) v, COUNT(DISTINCT p.id) n ' . $base . ' AND p.aberto BETWEEN :i AND :f', $PA)[0];
$totLoja = (float) castelo_q($pdo, 'SELECT COALESCE(SUM(total - lanche_valor),0) v FROM pc_pedidos WHERE excluido = 0 AND so_lanche = 0 AND aberto BETWEEN :i AND :f', $P)[0]['v'];
$prim = $q('SELECT MIN(p.aberto) d, MAX(p.aberto) u ' . $base)[0];
$ticket = $q('SELECT COALESCE(AVG(p.total - p.lanche_valor),0) t FROM pc_pedidos p WHERE p.excluido = 0 AND p.aberto BETWEEN :i AND :f
    AND EXISTS (SELECT 1 FROM pc_itens it WHERE it.pedido_id = p.id AND it.pai = 0 AND ' . $cond . ')', $P)[0]['t'];

$mes = [];
foreach ($q('SELECT DATE_FORMAT(p.aberto, "%Y-%m") m, SUM(it.qtd) q ' . $base . ' AND p.aberto >= DATE_SUB(:f, INTERVAL 18 MONTH) AND p.aberto <= :f2 GROUP BY m ORDER BY m', ['f' => $fim, 'f2' => $fim]) as $r) {
    $mes[$nm[(int) substr($r['m'], 5) - 1] . '/' . substr($r['m'], 2, 2)] = (float) $r['q'];
}
$hora = [];
for ($hh = 7; $hh <= 21; $hh++) {
    $hora[$hh . 'h'] = 0;
}
foreach ($q('SELECT HOUR(p.aberto) h, SUM(it.qtd) q ' . $base . ' AND p.aberto BETWEEN :i AND :f GROUP BY h', $P) as $r) {
    $hora[(int) $r['h'] . 'h'] = ($hora[(int) $r['h'] . 'h'] ?? 0) + (float) $r['q'];
}
$abertos = [];
foreach (castelo_q($pdo, 'SELECT WEEKDAY(aberto) d, COUNT(DISTINCT DATE(aberto)) n FROM pc_pedidos WHERE excluido = 0 AND aberto BETWEEN :i AND :f GROUP BY d', $P) as $r) {
    $abertos[(int) $r['d']] = (int) $r['n'];
}
$dia = $q('SELECT WEEKDAY(p.aberto) d, SUM(it.qtd) q, SUM(it.valor) v ' . $base . ' AND p.aberto BETWEEN :i AND :f GROUP BY d ORDER BY d', $P);
$maxDia = 0;
foreach ($dia as $r) {
    $maxDia = max($maxDia, $r['q'] / max(1, $abertos[(int) $r['d']] ?? 1));
}
$itens = $q('SELECT it.produto, SUM(it.qtd) q, SUM(it.valor) v ' . $base . ' AND p.aberto BETWEEN :i AND :f GROUP BY it.produto ORDER BY q DESC', $P);
$itensA = [];
foreach ($q('SELECT it.produto, SUM(it.qtd) q ' . $base . ' AND p.aberto BETWEEN :i AND :f GROUP BY it.produto', $PA) as $r) {
    $itensA[$r['produto']] = (float) $r['q'];
}
$junto = $q('SELECT o.produto, COUNT(DISTINCT o.pedido_id) n FROM pc_itens o JOIN pc_pedidos p ON p.id = o.pedido_id
    WHERE p.excluido = 0 AND o.pai = 0 AND p.aberto BETWEEN :i AND :f AND NOT ' . castelo_pc_lanche('o.') . '
    AND NOT (' . str_replace(['it.', ':x'], ['o.', ':x2'], $cond) . ')
    AND EXISTS (SELECT 1 FROM pc_itens it WHERE it.pedido_id = p.id AND it.pai = 0 AND ' . $cond . ') GROUP BY o.produto ORDER BY n DESC LIMIT 10', $P + ['x2' => $x]);
$canal = $q('SELECT p.canal, SUM(it.qtd) q, COUNT(DISTINCT p.id) n, COUNT(DISTINCT CASE WHEN p.cliente_id IS NOT NULL THEN p.id END) cad ' . $base . ' AND p.aberto BETWEEN :i AND :f GROUP BY p.canal ORDER BY q DESC', $P);
$pag = $q('SELECT IF(p.pagamento = "", "Não informado", p.pagamento) f, COUNT(DISTINCT p.id) n ' . $base . ' AND p.aberto BETWEEN :i AND :f GROUP BY f ORDER BY n DESC', $P);
$preco = $q('SELECT DATE_FORMAT(p.aberto, "%Y-%m") m, SUM(it.valor) / NULLIF(SUM(it.qtd),0) pr ' . $base . ' AND p.aberto >= DATE_SUB(:f, INTERVAL 18 MONTH) AND p.aberto <= :f2 GROUP BY m ORDER BY m', ['f' => $fim, 'f2' => $fim]);
$horaTop = array_keys($hora, max($hora))[0] ?? '';
$totQ = max(1, (float) $k['q']);
$almoco = ($hora['10h'] ?? 0) + ($hora['11h'] ?? 0) + ($hora['12h'] ?? 0) + ($hora['13h'] ?? 0);
?>
<section class="kpis">
  <div class="kpi"><span class="kpi-rotulo">Unidades vendidas</span><strong><?= castelo_num((float) $k['q']) ?></strong><span class="kpi-nota"><?= $var((float) $k['q'], (float) $ka['q']) ?> · <?= castelo_num($k['q'] / $sem, 1) ?> por semana</span></div>
  <div class="kpi"><span class="kpi-rotulo">Faturamento</span><strong class="valor-entrada"><?= castelo_reais((float) $k['v']) ?></strong><span class="kpi-nota"><?= $var((float) $k['v'], (float) $ka['v']) ?> · <?= $pct((float) $k['v'], $totLoja) ?> da loja</span></div>
  <div class="kpi"><span class="kpi-rotulo">Pedidos com <?= $prod !== '' ? 'o produto' : 'a categoria' ?></span><strong><?= castelo_num((float) $k['n']) ?></strong><span class="kpi-nota">ticket desses pedidos <?= castelo_reais((float) $ticket) ?></span></div>
  <div class="kpi"><span class="kpi-rotulo">Horário mais forte</span><strong><?= h($horaTop) ?></strong><span class="kpi-nota"><?= $pct($almoco, $totQ) ?> das 10h às 13h59</span></div>
</section>
<p class="muted an-desde">Primeira venda em <?= h(date('d/m/Y', strtotime($prim['d']))) ?> · última em <?= h(date('d/m/Y', strtotime($prim['u']))) ?><?= (float) $k['m'] > 0 ? ' · ' . castelo_num($k['m'] / $sem) . ' mini salgados por semana' : '' ?></p>

<section class="painel-card">
  <h2>Unidades por mês</h2>
  <p class="muted">Últimos 18 meses, até o fim do período escolhido.</p>
  <div class="fin-grafico-wrap"><?= castelo_svg_barras($mes, ['titulo' => 'Unidades por mês']) ?></div>
</section>

<div class="painel-grade an-duas">
  <section class="painel-card">
    <h2>Por horário</h2>
    <p class="muted">Unidades no período, pela hora em que o pedido foi aberto.</p>
    <div class="fin-grafico-wrap"><?= castelo_svg_barras($hora, ['largura' => 420, 'altura' => 240, 'titulo' => 'Unidades por horário']) ?></div>
  </section>
  <section class="painel-card">
    <h2>Por dia da semana</h2>
    <p class="muted">Média por dia aberto no período.</p>
    <ul class="lista-simples">
      <?php foreach ($dia as $r): $md = $r['q'] / max(1, $abertos[(int) $r['d']] ?? 1); ?>
        <li><span><?= h($nd[(int) $r['d']]) ?><span class="an-barrinha"><span data-pct="<?= $maxDia > 0 ? round($md / $maxDia * 100) : 0 ?>"></span></span></span><strong><?= castelo_num($md, 1) ?> por dia · <?= castelo_reais($r['v'] / max(1, $abertos[(int) $r['d']] ?? 1)) ?></strong></li>
      <?php endforeach; ?>
    </ul>
  </section>
</div>

<div class="painel-grade an-tres">
  <section class="painel-card">
    <h2><?= $prod !== '' ? 'Produto' : 'Produtos e sabores' ?></h2>
    <ul class="lista-simples an-rolagem">
      <?php foreach ($itens as $r): ?><li><span><a class="an-link" href="analise-produto.php?<?= h(http_build_query(['prod' => $r['produto'], 'p' => $modo === 'pers' ? '90' : $modo])) ?>"><?= h($r['produto']) ?></a></span><strong><?= castelo_num((float) $r['q']) ?> · <?= $pct((float) $r['q'], $totQ) ?> · <?= $var((float) $r['q'], $itensA[$r['produto']] ?? 0) ?></strong></li><?php endforeach; ?>
    </ul>
  </section>
  <section class="painel-card">
    <h2>Sai junto</h2>
    <p class="muted">Em quantos dos pedidos com <?= $prod !== '' ? 'o produto' : 'a categoria' ?> esse item também foi.</p>
    <ul class="lista-simples an-rolagem">
      <?php foreach ($junto as $r): ?><li><span><?= h($r['produto']) ?></span><strong><?= $pct((float) $r['n'], (float) $k['n']) ?></strong></li><?php endforeach; ?>
      <?php if (!$junto): ?><li><span class="muted">Sai sozinho na maioria das vezes.</span></li><?php endif; ?>
    </ul>
  </section>
  <section class="painel-card">
    <h2>Como sai</h2>
    <ul class="lista-simples">
      <?php foreach ($canal as $r): ?><li><span><?= h($canalNome[$r['canal']] ?? $r['canal']) ?> <small class="muted">(<?= $pct((float) $r['cad'], (float) $r['n']) ?> com cliente)</small></span><strong><?= $pct((float) $r['q'], $totQ) ?></strong></li><?php endforeach; ?>
    </ul>
    <h3 class="fin-sub">Pagamento</h3>
    <ul class="lista-simples">
      <?php foreach (array_slice($pag, 0, 5) as $r): ?><li><span><?= h($r['f']) ?></span><strong><?= $pct((float) $r['n'], (float) $k['n']) ?></strong></li><?php endforeach; ?>
    </ul>
  </section>
</div>

<?php if ($prod !== '' && $preco): ?>
<section class="painel-card">
  <h2>Preço médio por mês</h2>
  <p class="muted">Faturamento dividido pelas unidades. Mostra reajustes e descontos ao longo do tempo.</p>
  <div class="an-precos">
    <?php foreach ($preco as $r): ?><span><small><?= h($nm[(int) substr($r['m'], 5) - 1] . '/' . substr($r['m'], 2, 2)) ?></small><?= castelo_reais((float) $r['pr']) ?></span><?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
<?php destrava_admin_foot();
