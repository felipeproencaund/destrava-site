<?php
/** Cálculos do Financeiro: lucro, CMV, ponto de equilíbrio e meta de venda por dia. */

/** Dias de funcionamento entre duas datas (inclusive). $dias = 7 dígitos, segunda a domingo, '1' = abre. */
function castelo_dias_abertos(string $dias, string $ini, string $fim): int {
    $n = 0;
    for ($t = strtotime($ini); $t <= strtotime($fim); $t += 86400) {
        if (($dias[(int) date('N', $t) - 1] ?? '0') === '1') {
            $n++;
        }
    }
    return $n;
}

/** Resultado do mês de uma carteira de empresa. */
function castelo_resultado(PDO $pdo, array $cart, string $mes): array {
    $ini = $mes . '-01';
    $fim = date('Y-m-t', strtotime($ini));
    $hoje = date('Y-m-d');
    $st = $pdo->prepare("SELECT
        COALESCE(SUM(CASE WHEN l.tipo='entrada' THEN l.valor END),0) AS fat,
        COALESCE(SUM(CASE WHEN l.tipo='saida' AND g.natureza='variavel' THEN l.valor END),0) AS var,
        COALESCE(SUM(CASE WHEN l.tipo='saida' AND g.natureza='fixo' THEN l.valor END),0) AS fixo,
        COALESCE(SUM(CASE WHEN l.tipo='saida' AND g.natureza='retirada' THEN l.valor END),0) AS ret,
        COALESCE(SUM(CASE WHEN l.tipo='saida' AND (g.natureza='outro' OR g.natureza IS NULL) THEN l.valor END),0) AS outro,
        COALESCE(SUM(CASE WHEN l.tipo='entrada' AND l.data = :hoje THEN l.valor END),0) AS hoje
        FROM fin_lancamentos l LEFT JOIN fin_categorias g ON g.id = l.categoria_id
        WHERE l.carteira_id = :c AND l.data BETWEEN :ini AND :fim");
    $st->execute(['c' => $cart['id'], 'ini' => $ini, 'fim' => $fim, 'hoje' => $hoje]);
    $r = array_map('floatval', $st->fetch());

    // CMV %: do próprio mês; se ainda não tem venda, dos últimos 90 dias; se não tem histórico, o estimado da carteira
    $origemCmv = 'mês';
    if ($r['fat'] > 0) {
        $cmvPct = $r['var'] / $r['fat'];
    } else {
        $h = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN l.tipo='entrada' THEN l.valor END),0) f,
            COALESCE(SUM(CASE WHEN l.tipo='saida' AND g.natureza='variavel' THEN l.valor END),0) v
            FROM fin_lancamentos l LEFT JOIN fin_categorias g ON g.id = l.categoria_id
            WHERE l.carteira_id = :c AND l.data >= :d");
        $h->execute(['c' => $cart['id'], 'd' => date('Y-m-d', strtotime('-90 days'))]);
        $hh = $h->fetch();
        if ((float) $hh['f'] > 0) {
            $cmvPct = $hh['v'] / $hh['f'];
            $origemCmv = 'últimos 90 dias';
        } else {
            $cmvPct = ((float) $cart['cmv_estimado']) / 100;
            $origemCmv = 'estimado';
        }
    }
    $mcPct = max(0.05, 1 - $cmvPct);                       // margem de contribuição (nunca abaixo de 5% pra não explodir a conta)
    $cobrir = $r['fixo'] + $r['ret'] + $r['outro'];         // o que as vendas precisam pagar além das variáveis
    $pe = $cobrir / $mcPct;                                 // ponto de equilíbrio do mês
    $diasMes = max(1, castelo_dias_abertos($cart['dias'], $ini, $fim));
    $deHoje = max($ini, min($hoje, $fim));
    $diasRest = ($hoje > $fim) ? 0 : castelo_dias_abertos($cart['dias'], $hoje < $ini ? $ini : $deHoje, $fim);
    $falta = max(0, $pe - $r['fat']);
    return $r + [
        'cmv_pct' => $cmvPct, 'cmv_origem' => $origemCmv, 'mc_pct' => $mcPct, 'cobrir' => $cobrir,
        'pe' => $pe, 'meta_media' => $pe / $diasMes, 'dias_mes' => $diasMes, 'dias_rest' => $diasRest,
        'falta' => $falta, 'meta_dia' => $diasRest > 0 ? $falta / $diasRest : 0,
        'lucro_op' => $r['fat'] - $r['var'] - $r['fixo'] - $r['outro'],
        'lucro' => $r['fat'] - $r['var'] - $r['fixo'] - $r['outro'] - $r['ret'],
        'progresso' => $pe > 0 ? min(1, $r['fat'] / $pe) : ($r['fat'] > 0 ? 1 : 0),
    ];
}

/** Vendas por dia do mês: [dia => valor]. */
function castelo_vendas_por_dia(PDO $pdo, int $carteira, string $mes): array {
    $st = $pdo->prepare("SELECT DAY(data) d, SUM(valor) v FROM fin_lancamentos WHERE carteira_id = :c AND tipo = 'entrada' AND data BETWEEN :i AND :f GROUP BY DAY(data)");
    $st->execute(['c' => $carteira, 'i' => $mes . '-01', 'f' => date('Y-m-t', strtotime($mes . '-01'))]);
    $out = array_fill(1, (int) date('t', strtotime($mes . '-01')), 0.0);
    foreach ($st->fetchAll() as $r) {
        $out[(int) $r['d']] = (float) $r['v'];
    }
    return $out;
}

/** Gráfico de barras em SVG (vendas por dia) com a linha da meta média. Sem biblioteca externa. */
function castelo_svg_vendas(array $porDia, float $meta, string $mes, string $dias): string {
    $w = 760; $h = 220; $pad = 28; $n = count($porDia);
    $max = max(1, $meta * 1.25, ...array_values($porDia));
    $bw = ($w - $pad * 2) / $n;
    $hoje = date('Y-m') === $mes ? (int) date('j') : ($mes < date('Y-m') ? $n + 1 : 0);
    $svg = '<svg class="fin-grafico" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="Vendas por dia do mês comparadas com a meta">';
    $svg .= '<defs><linearGradient id="gb" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#E3C27F"/><stop offset="1" stop-color="#8C6D2D"/></linearGradient></defs>';
    foreach ($porDia as $d => $v) {
        $x = $pad + ($d - 1) * $bw + $bw * 0.18;
        $bh = $v > 0 ? max(2, ($h - $pad * 2) * $v / $max) : 0;
        $y = $h - $pad - $bh;
        $aberto = ($dias[(int) date('N', strtotime($mes . '-' . str_pad((string) $d, 2, '0', STR_PAD_LEFT))) - 1] ?? '0') === '1';
        $fill = $v >= $meta && $meta > 0 ? '#5FD39A' : 'url(#gb)';
        if ($bh > 0) {
            $svg .= '<rect x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round($bw * 0.64, 1) . '" height="' . round($bh, 1) . '" rx="3" fill="' . $fill . '"><title>Dia ' . $d . ': ' . castelo_reais($v) . '</title></rect>';
        } elseif ($aberto && $d < $hoje) {
            $svg .= '<rect x="' . round($x, 1) . '" y="' . ($h - $pad - 2) . '" width="' . round($bw * 0.64, 1) . '" height="2" fill="rgb(255 138 128 / .6)"/>';
        }
        if ($d === 1 || $d % 5 === 0 || $d === $n) {
            $svg .= '<text x="' . round($x + $bw * 0.32, 1) . '" y="' . ($h - 8) . '" text-anchor="middle" class="g-eixo">' . $d . '</text>';
        }
        if ($d === $hoje) {
            $svg .= '<rect x="' . round($x - $bw * 0.1, 1) . '" y="' . $pad . '" width="' . round($bw * 0.84, 1) . '" height="' . ($h - $pad * 2) . '" fill="rgb(255 255 255 / .05)" rx="4"/>';
        }
    }
    if ($meta > 0) {
        $ym = round($h - $pad - ($h - $pad * 2) * $meta / $max, 1);
        $svg .= '<line x1="' . $pad . '" x2="' . ($w - $pad) . '" y1="' . $ym . '" y2="' . $ym . '" stroke="#FF8A80" stroke-width="1.5" stroke-dasharray="6 5"/>';
        $svg .= '<text x="' . ($w - $pad) . '" y="' . ($ym - 6) . '" text-anchor="end" class="g-meta">meta por dia ' . castelo_reais($meta) . '</text>';
    }
    return $svg . '</svg>';
}
