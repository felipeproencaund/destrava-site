<?php
/** Castelo: Central de comando — cérebro do negócio, agentes por área e console. Dados da Pede Coxinha. */
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/analises.php';
destrava_admin_require_login();
$pdo = destrava_db();

$ult = castelo_an_info($pdo, 'pede-coxinha', 'ultimo_pedido');
destrava_admin_head('Central', 'central');
if (!$ult): ?>
  <p class="empty-state">A Central precisa dos dados da Pede Coxinha. <a href="analise-importar.php">Importe o arquivo do Consumer</a>.</p>
<?php destrava_admin_foot(); exit; endif;

$hoje = date('Y-m-d', strtotime($ult));
$fim = $hoje . ' 23:59:59';
$d = function (string $base, string $mod): string {
    return date('Y-m-d', strtotime($base . ' ' . $mod));
};
$sl = 'NOT ' . castelo_pc_lanche('it.');
$fat = function (string $i, string $f) use ($pdo): array {
    $r = castelo_q($pdo, 'SELECT COUNT(*) n, COALESCE(SUM(total - lanche_valor),0) v FROM pc_pedidos WHERE excluido = 0 AND so_lanche = 0 AND aberto BETWEEN :i AND :f', ['i' => $i . ' 00:00:00', 'f' => $f . ' 23:59:59'])[0];
    return ['n' => (float) $r['n'], 'v' => (float) $r['v']];
};
$pct = function (float $a, float $b): ?float {
    return $b > 0 ? ($a / $b - 1) * 100 : null;
};
$fmtPct = function (?float $v): string {
    return $v === null ? '–' : ($v >= 0 ? '+' : '−') . castelo_num(abs($v)) . '%';
};

// ---- Indicadores do topo
$ini30 = $d($hoje, '-29 days');
$a30 = $fat($ini30, $hoje);
$y30 = $fat($d($ini30, '-364 days'), $d($hoje, '-364 days'));
$p30 = $fat($d($ini30, '-30 days'), $d($hoje, '-30 days'));
$hojeV = $fat($hoje, $hoje);
$miniSem = (float) castelo_q($pdo, 'SELECT COALESCE(SUM(it.mini),0) m FROM pc_itens it JOIN pc_pedidos p ON p.id = it.pedido_id WHERE p.excluido = 0 AND it.pai = 0 AND p.aberto BETWEEN :i AND :f',
    ['i' => $d($hoje, '-27 days') . ' 00:00:00', 'f' => $fim])[0]['m'] / 4;

// ---- Agente Vendas: categoria que mais subiu e mais caiu (30 dias vs 30 anteriores)
$catV = function (string $i, string $f) use ($pdo, $sl): array {
    $o = [];
    foreach (castelo_q($pdo, 'SELECT it.categoria c, SUM(it.valor) v FROM pc_itens it JOIN pc_pedidos p ON p.id = it.pedido_id WHERE p.excluido = 0 AND it.pai = 0 AND ' . $sl . ' AND p.aberto BETWEEN :i AND :f GROUP BY c',
        ['i' => $i . ' 00:00:00', 'f' => $f . ' 23:59:59']) as $r) {
        $o[$r['c']] = (float) $r['v'];
    }
    return $o;
};
$cA = $catV($ini30, $hoje);
$cP = $catV($d($ini30, '-30 days'), $d($hoje, '-30 days'));
$mov = [];
foreach ($cA as $c => $v) {
    if (($cP[$c] ?? 0) >= 300) {
        $mov[$c] = $v - $cP[$c];
    }
}
arsort($mov);
$sobe = array_key_first($mov);
$desce = array_key_last($mov);

// ---- Agente Compras e produção: previsão de mini salgados das próximas 4 semanas
$semanaIni = function (string $dia): string {
    return date('Y-m-d', strtotime($dia . ' -' . (date('N', strtotime($dia)) - 1) . ' days'));
};
$minis = function (string $i, string $f) use ($pdo): float {
    return (float) castelo_q($pdo, 'SELECT COALESCE(SUM(it.mini),0) m FROM pc_itens it JOIN pc_pedidos p ON p.id = it.pedido_id WHERE p.excluido = 0 AND it.pai = 0 AND p.aberto BETWEEN :i AND :f',
        ['i' => $i . ' 00:00:00', 'f' => $f . ' 23:59:59'])[0]['m'];
};
$segAtual = $semanaIni($hoje);
$base8 = $minis($d($segAtual, '-56 days'), $d($segAtual, '-1 day'));
$base8y = $minis($d($segAtual, '-420 days'), $d($segAtual, '-365 days'));
$mediaAtual = $base8 / 8;
$prev = [];
for ($k = 1; $k <= 4; $k++) {
    $ini = $d($segAtual, '+' . (7 * $k) . ' days');
    $iy = $d($ini, '-364 days');
    $ly = $minis($iy, $d($iy, '+6 days'));
    $indice = ($base8y > 0 && $ly > 0) ? max(0.5, min(2.2, $ly / ($base8y / 8))) : 1.0;
    $alto = $mediaAtual * $indice;
    $baixo = $mediaAtual * (1 + ($indice - 1) * 0.5);
    $prev[date('d/m', strtotime($ini))] = [round(min($baixo, $alto) / 100) * 100, round(max($baixo, $alto) / 100) * 100];
}
$picoPrev = max(array_map(function ($p) { return $p[1]; }, $prev));
$picoBaixo = max(array_map(function ($p) { return $p[0]; }, $prev));

// ---- Agente Marketing: clientes a contatar
$cli = castelo_q($pdo, 'SELECT c.id, COUNT(*) n, MAX(p.aberto) u, MAX(CASE WHEN EXISTS(SELECT 1 FROM pc_itens x WHERE x.pedido_id = p.id AND x.pai = 0 AND x.categoria = "cento") THEN p.aberto END) uc
    FROM pc_pedidos p JOIN pc_clientes c ON c.id = p.cliente_id WHERE p.excluido = 0 AND p.so_lanche = 0 AND p.aberto <= :f AND LENGTH(c.fone) >= 10
    AND c.id NOT IN (SELECT cliente_id FROM pc_msg_optout) GROUP BY c.id', ['f' => $fim]);
$esf = 0;
$recompra = 0;
foreach ($cli as $c) {
    $du = (strtotime($fim) - strtotime($c['u'])) / 86400;
    $dc = $c['uc'] ? (strtotime($fim) - strtotime($c['uc'])) / 86400 : null;
    if ($c['n'] >= 2 && $c['uc'] && $du >= 61 && $du <= 180) {
        $esf++;
    }
    if ($dc !== null && $dc >= 35 && $dc <= 50 && $du >= 35) {
        $recompra++;
    }
}
$enviadas = (int) castelo_q($pdo, 'SELECT COUNT(*) n FROM pc_msg_envios WHERE DATE(enviado_em) = CURDATE()')[0]['n'];
$novosC = function (string $i, string $f) use ($pdo): int {
    return (int) castelo_q($pdo, 'SELECT COUNT(*) n FROM (SELECT p.cliente_id, MIN(p.aberto) pr FROM pc_pedidos p WHERE p.excluido = 0 AND p.cliente_id IS NOT NULL
        AND EXISTS(SELECT 1 FROM pc_itens c WHERE c.pedido_id = p.id AND c.pai = 0 AND c.categoria = "cento") GROUP BY p.cliente_id) x WHERE pr BETWEEN :i AND :f',
        ['i' => $i . ' 00:00:00', 'f' => $f . ' 23:59:59'])[0]['n'];
};
$novos30 = $novosC($ini30, $hoje);
$novos30y = $novosC($d($ini30, '-364 days'), $d($hoje, '-364 days'));

$nomesMes = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

// ---- Agente Preço: há quanto tempo o cento e a coxinha estão no mesmo preço
$desde = function (string $produto) use ($pdo, $fim): ?array {
    $rows = castelo_q($pdo, 'SELECT DATE_FORMAT(p.aberto, "%Y-%m") m, SUM(it.valor) / NULLIF(SUM(it.qtd),0) pr FROM pc_itens it JOIN pc_pedidos p ON p.id = it.pedido_id
        WHERE p.excluido = 0 AND it.pai = 0 AND it.produto = :x AND p.aberto <= :f GROUP BY m ORDER BY m DESC', ['x' => $produto, 'f' => $fim]);
    if (!$rows) {
        return null;
    }
    $atual = (float) $rows[0]['pr'];
    $mes = $rows[0]['m'];
    foreach ($rows as $r) {
        if (abs((float) $r['pr'] - $atual) / max(0.01, $atual) > 0.04) {
            break;
        }
        $mes = $r['m'];
    }
    $meses = (int) ((strtotime($fim) - strtotime($mes . '-01')) / (86400 * 30.4));
    return ['preco' => round($atual * 2) / 2, 'mes' => $mes, 'meses' => $meses];
};
$pCento = $desde('Cento de Salgados');
$pCoxinha = $desde('Coxinha de Frango');

// ---- Agente Empresas (B2B): quem compra como empresa e quem pede do trabalho
$b2b = function (string $i, string $f) use ($pdo): array {
    $o = ['empresa' => ['n' => 0, 'v' => 0, 'q' => 0], 'trabalho' => ['n' => 0, 'v' => 0, 'q' => 0]];
    foreach (castelo_q($pdo, 'SELECT b2b, COUNT(*) n, SUM(total - lanche_valor) v, COUNT(DISTINCT COALESCE(cliente_id, b2b_quem)) q FROM pc_pedidos
        WHERE excluido = 0 AND so_lanche = 0 AND b2b IS NOT NULL AND aberto BETWEEN :i AND :f GROUP BY b2b', ['i' => $i . ' 00:00:00', 'f' => $f . ' 23:59:59']) as $r) {
        $o[$r['b2b']] = ['n' => (int) $r['n'], 'v' => (float) $r['v'], 'q' => (int) $r['q']];
    }
    return $o;
};
$i365 = $d($hoje, '-364 days');
$bAno = $b2b($i365, $hoje);
$b90 = $b2b($d($hoje, '-89 days'), $hoje);
$b90y = $b2b($d($hoje, '-454 days'), $d($hoje, '-365 days'));
$fatAno = $fat($i365, $hoje)['v'];
$topEmp = castelo_q($pdo, 'SELECT b2b_quem q, COUNT(*) n, SUM(total - lanche_valor) v FROM pc_pedidos WHERE excluido = 0 AND b2b = "empresa" AND aberto BETWEEN :i AND :f
    GROUP BY b2b_quem ORDER BY v DESC LIMIT 3', ['i' => $i365 . ' 00:00:00', 'f' => $fim]);
$b2bPct = $fatAno > 0 ? ($bAno['empresa']['v'] + $bAno['trabalho']['v']) / $fatAno * 100 : 0;

// ---- Agente Controle: notas fiscais e dados
$notas = castelo_an_info($pdo, 'pede-coxinha', 'notas_problema') ?: [];
$notasN = array_sum(array_column($notas, 'n'));
$imp = castelo_q($pdo, 'SELECT quando FROM an_importacoes WHERE empresa = "pede-coxinha" ORDER BY id DESC LIMIT 1')[0]['quando'] ?? null;
$diasDados = (int) floor((time() - strtotime($ult)) / 86400);
$balcao = castelo_q($pdo, 'SELECT COUNT(*) n, SUM(cliente_id IS NOT NULL) c FROM pc_pedidos WHERE excluido = 0 AND so_lanche = 0 AND canal = "balcao" AND aberto BETWEEN :i AND :f', ['i' => $ini30 . ' 00:00:00', 'f' => $fim])[0];
$balcaoPct = $balcao['n'] > 0 ? $balcao['c'] / $balcao['n'] * 100 : 0;

// ---- Cérebro: categorias, produtos e o que sai junto (90 dias)
$i90 = $d($hoje, '-89 days') . ' 00:00:00';
$prodTop = castelo_q($pdo, 'SELECT it.produto, it.categoria, SUM(it.valor) v FROM pc_itens it JOIN pc_pedidos p ON p.id = it.pedido_id
    WHERE p.excluido = 0 AND it.pai = 0 AND ' . $sl . ' AND p.aberto BETWEEN :i AND :f GROUP BY it.produto, it.categoria ORDER BY v DESC LIMIT 28', ['i' => $i90, 'f' => $fim]);
$catTot = [];
foreach (castelo_q($pdo, 'SELECT it.categoria c, SUM(it.valor) v FROM pc_itens it JOIN pc_pedidos p ON p.id = it.pedido_id
    WHERE p.excluido = 0 AND it.pai = 0 AND ' . $sl . ' AND p.aberto BETWEEN :i AND :f GROUP BY c ORDER BY v DESC', ['i' => $i90, 'f' => $fim]) as $r) {
    $catTot[$r['c']] = (float) $r['v'];
}
$cerebro = ['cats' => [], 'prods' => [], 'links' => []];
$somaCat = max(1, array_sum($catTot));
foreach ($catTot as $c => $v) {
    if ($v / $somaCat >= 0.015 && $c !== 'outros') {
        $cerebro['cats'][] = ['n' => ucfirst($c), 'v' => round($v)];
    }
}
$catsOk = array_column($cerebro['cats'], 'n');
$prodTop = array_values(array_filter($prodTop, function ($p) use ($catsOk) {
    return in_array(ucfirst($p['categoria']), $catsOk, true);
}));
$pares = [];
if ($prodTop) {
    $lista = implode(',', array_map([$pdo, 'quote'], array_column($prodTop, 'produto')));
    $pares = castelo_q($pdo, 'SELECT a.produto pa, b.produto pb, COUNT(DISTINCT a.pedido_id) n FROM pc_itens a JOIN pc_itens b ON b.pedido_id = a.pedido_id AND a.produto < b.produto AND b.pai = 0
        JOIN pc_pedidos p ON p.id = a.pedido_id WHERE p.excluido = 0 AND a.pai = 0 AND p.aberto BETWEEN :i AND :f AND a.produto IN (' . $lista . ') AND b.produto IN (' . $lista . ')
        GROUP BY pa, pb HAVING n >= 5 ORDER BY n DESC LIMIT 45', ['i' => $i90, 'f' => $fim]);
}
foreach ($prodTop as $p) {
    $cerebro['prods'][] = ['n' => $p['produto'], 'c' => ucfirst($p['categoria']), 'v' => round((float) $p['v'])];
}
foreach ($pares as $p) {
    $cerebro['links'][] = [$p['pa'], $p['pb'], (int) $p['n']];
}

// ---- Alertas (o que pede ação)
$alertas = [];
if ($pCento && $pCento['meses'] >= 6) {
    $alertas[] = ['alto', 'Cento a ' . castelo_reais($pCento['preco']) . ' há ' . $pCento['meses'] . ' meses sem reajuste.'];
}
if ($picoBaixo > $mediaAtual * 1.15) {
    $alertas[] = ['alto', 'Próximas semanas: entre ' . castelo_num($picoBaixo) . ' e ' . castelo_num($picoPrev) . ' mini salgados na semana mais forte (média atual ' . castelo_num($mediaAtual) . '). Reforçar compra.'];
}
if ($novos30y > 0 && $novos30 < $novos30y * 0.8) {
    $alertas[] = ['medio', 'Clientes novos de cento: ' . $novos30 . ' em 30 dias, contra ' . $novos30y . ' no mesmo período do ano passado.'];
}
if ($esf > 0) {
    $alertas[] = ['medio', $esf . ' clientes de cento esfriando com WhatsApp. ' . $enviadas . ' mensagens enviadas hoje.'];
}
if ($bAno['empresa']['q'] < 50) {
    $alertas[] = ['alto', 'Só ' . $bAno['empresa']['q'] . ' empresas compraram em 12 meses, com ticket ' . castelo_reais($bAno['empresa']['n'] > 0 ? $bAno['empresa']['v'] / $bAno['empresa']['n'] : 0) . '. Mercado de empresas quase todo por abrir.'];
}
if ($notasN > 0) {
    $alertas[] = ['medio', $notasN . ' notas fiscais com problema no Consumer.'];
}
if ($balcaoPct < 10) {
    $alertas[] = ['baixo', 'Balcão com ' . castelo_num($balcaoPct) . '% de clientes cadastrados (meta 30%).'];
}
if ($diasDados > 2) {
    $alertas[] = ['baixo', 'Dados de vendas de ' . $diasDados . ' dias atrás. Importe o arquivo novo.'];
}
$iaLigada = defined('CASTELO_IA_KEY') && CASTELO_IA_KEY !== '';
$nivel = ['alto' => 'Ação', 'medio' => 'Atenção', 'baixo' => 'Info'];
?>
<div class="cc" data-cc>
  <header class="cc-topo">
    <div class="cc-marca"><span class="cc-pulso"></span> CASTELO · CENTRAL <small>PEDE COXINHA</small></div>
    <div class="cc-sys">
      <span><i class="cc-on"></i>CONSUMER <b><?= h(date('d/m H:i', strtotime($ult))) ?></b></span>
      <span><i class="cc-on"></i>BANCO <b>OK</b></span>
      <span><i class="<?= $iaLigada ? 'cc-on' : 'cc-off' ?>"></i>IA <b><?= $iaLigada ? 'ON' : 'AGUARDANDO CHAVE' ?></b></span>
      <span class="cc-relogio" data-relogio>--:--:--</span>
      <button type="button" class="cc-btn" data-tela-cheia>Tela cheia</button>
    </div>
  </header>

  <div class="cc-grade">
    <aside class="cc-col">
      <h2 class="cc-tit">[ AGENTES ]</h2>
      <article class="cc-agente">
        <header><span class="cc-ico">◈</span> VENDAS <em class="cc-st <?= ($pct($a30['v'], $y30['v']) ?? 0) >= 0 ? 'ok' : 'alerta' ?>"><?= $fmtPct($pct($a30['v'], $y30['v'])) ?> ANO</em></header>
        <p><?= castelo_reais($a30['v']) ?> em 30 dias (<?= $fmtPct($pct($a30['v'], $p30['v'])) ?> vs 30 anteriores).<?= $sobe ? ' Puxando pra cima: <b>' . h($sobe) . '</b>.' : '' ?><?= $desce && $mov[$desce] < 0 ? ' Caindo: <b>' . h($desce) . '</b>.' : '' ?></p>
      </article>
      <article class="cc-agente">
        <header><span class="cc-ico">▤</span> COMPRAS E PRODUÇÃO <em class="cc-st <?= $picoBaixo > $mediaAtual * 1.15 ? 'alerta' : 'ok' ?>"><?= $picoBaixo > $mediaAtual * 1.15 ? 'REFORÇAR' : 'ESTÁVEL' ?></em></header>
        <p>Média de <?= castelo_num($mediaAtual) ?> mini/semana. Previsão (faixa) pelo comportamento das mesmas semanas do ano passado:</p>
        <ul class="cc-prev"><?php foreach ($prev as $s => [$lo, $hi]): ?><li><span><?= h($s) ?></span><span class="cc-barra"><span data-pct="<?= round($hi / max(1, $picoPrev) * 100) ?>"></span></span><b><?= castelo_num($lo / 1000, 1) ?>–<?= castelo_num($hi / 1000, 1) ?> mil</b></li><?php endforeach; ?></ul>
      </article>
      <article class="cc-agente">
        <header><span class="cc-ico">✉</span> MARKETING <em class="cc-st <?= $novos30y > 0 && $novos30 < $novos30y * 0.8 ? 'alerta' : 'ok' ?>"><?= $novos30 ?> NOVOS</em></header>
        <p><b><?= $esf ?></b> clientes de cento esfriando e <b><?= $recompra ?></b> na hora de recomprar. Enviadas hoje: <?= $enviadas ?>/30. <a href="mensagens.php">Abrir mensagens →</a></p>
      </article>
      <article class="cc-agente">
        <header><span class="cc-ico">$</span> PREÇO <em class="cc-st <?= $pCento && $pCento['meses'] >= 6 ? 'alerta' : 'ok' ?>"><?= $pCento ? $pCento['meses'] . ' MESES' : '–' ?></em></header>
        <p><?php if ($pCento): ?>Cento a <b><?= castelo_reais($pCento['preco']) ?></b> desde <?= h($nomesMes[(int) substr($pCento['mes'], 5)] . '/' . substr($pCento['mes'], 2, 2)) ?>.<?php endif; ?><?php if ($pCoxinha): ?> Coxinha a <b><?= castelo_reais($pCoxinha['preco']) ?></b> desde <?= h($nomesMes[(int) substr($pCoxinha['mes'], 5)] . '/' . substr($pCoxinha['mes'], 2, 2)) ?>.<?php endif; ?></p>
      </article>
      <article class="cc-agente">
        <header><span class="cc-ico">▣</span> EMPRESAS (B2B) <em class="cc-st alerta"><?= castelo_num($b2bPct) ?>% DO FAT.</em></header>
        <p>12 meses: <b><?= $bAno['empresa']['q'] ?></b> empresas compraram <?= castelo_reais($bAno['empresa']['v']) ?> (ticket <?= castelo_reais($bAno['empresa']['n'] > 0 ? $bAno['empresa']['v'] / $bAno['empresa']['n'] : 0) ?>) e <b><?= $bAno['trabalho']['q'] ?></b> clientes pediram no trabalho (<?= castelo_reais($bAno['trabalho']['v']) ?>). Empresas em 90 dias: <?= castelo_reais($b90['empresa']['v']) ?> (<?= $fmtPct($pct($b90['empresa']['v'], $b90y['empresa']['v'])) ?> ano).</p>
        <?php if ($topEmp): ?><ul class="cc-top"><?php foreach ($topEmp as $t): ?><li><span><?= h(mb_strimwidth((string) $t['q'], 0, 30, '…')) ?></span><b><?= castelo_reais((float) $t['v']) ?></b></li><?php endforeach; ?></ul><?php endif; ?>
      </article>
      <article class="cc-agente">
        <header><span class="cc-ico">⚑</span> CONTROLE <em class="cc-st <?= $notasN > 0 ? 'alerta' : 'ok' ?>"><?= $notasN ?> NOTAS</em></header>
        <p><?= $notasN ?> notas fiscais com problema. Balcão com <?= castelo_num($balcaoPct) ?>% de clientes cadastrados. Dados importados <?= $imp ? 'em ' . h(date('d/m', strtotime($imp))) : '–' ?>.</p>
      </article>
    </aside>

    <main class="cc-centro">
      <div class="cc-cerebro-wrap">
        <canvas class="cc-cerebro" data-cerebro="<?= h(json_encode($cerebro, JSON_UNESCAPED_UNICODE)) ?>"></canvas>
        <div class="cc-cerebro-leg">MAPA DO NEGÓCIO · 90 DIAS · tamanho = faturamento · linhas = sai junto no mesmo pedido</div>
      </div>
      <form class="cc-console" method="get" action="central.php" data-cc-console>
        <span class="cc-console-ico">▸</span>
        <label class="sr-only" for="cc-pergunta">Pergunte ao Castelo</label>
        <input id="cc-pergunta" name="q" type="text" maxlength="300" autocomplete="off" placeholder="<?= $iaLigada ? 'Pergunte ao Castelo: quanto vendi de batata às segundas?' : 'Console de IA aguardando a chave. Exemplo: o que comprar pra próxima semana?' ?>" <?= $iaLigada ? '' : 'disabled' ?>>
        <button type="submit" class="cc-btn" <?= $iaLigada ? '' : 'disabled' ?>>Perguntar</button>
      </form>
    </main>

    <aside class="cc-col">
      <h2 class="cc-tit">[ AGORA ]</h2>
      <div class="cc-kpi"><span>FATURAMENTO 30 DIAS</span><strong><?= castelo_reais($a30['v']) ?></strong><em class="<?= ($pct($a30['v'], $y30['v']) ?? 0) >= 0 ? 'ok' : 'alerta' ?>"><?= $fmtPct($pct($a30['v'], $y30['v'])) ?> vs ano passado</em></div>
      <div class="cc-kpi"><span>PEDIDOS 30 DIAS</span><strong><?= castelo_num($a30['n']) ?></strong><em>ticket <?= castelo_reais($a30['n'] > 0 ? $a30['v'] / $a30['n'] : 0) ?></em></div>
      <div class="cc-kpi"><span>ÚLTIMO DIA (<?= h(date('d/m', strtotime($hoje))) ?>)</span><strong><?= castelo_reais($hojeV['v']) ?></strong><em><?= castelo_num($hojeV['n']) ?> pedidos</em></div>
      <div class="cc-kpi"><span>MINI SALGADOS / SEMANA</span><strong><?= castelo_num($miniSem) ?></strong><em>média das 4 últimas</em></div>
      <h2 class="cc-tit">[ ALERTAS ]</h2>
      <ul class="cc-alertas">
        <?php foreach ($alertas as [$nv, $txt]): ?><li class="cc-al-<?= h($nv) ?>"><b><?= h($nivel[$nv]) ?></b><?= h($txt) ?></li><?php endforeach; ?>
        <?php if (!$alertas): ?><li class="cc-al-baixo"><b>OK</b>Nada pedindo ação agora.</li><?php endif; ?>
      </ul>
      <nav class="cc-atalhos"><a href="analise-painel.php">Painel da reunião</a><a href="analise-pc.php">Análise detalhada</a><a href="analise-produto.php">Por produto</a></nav>
    </aside>
  </div>
</div>
<?php destrava_admin_foot();
