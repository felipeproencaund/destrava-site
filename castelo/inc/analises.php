<?php
/** Castelo: Análises. Importação dos dados das empresas e funções de cálculo e gráfico. */

/**
 * Importa o arquivo da Pede Coxinha (formato gerado pelo exportador do Consumer).
 * Atualiza o que já existe e acrescenta o que é novo. Devolve [ok, mensagem].
 */
function castelo_importar_pc(PDO $pdo, string $conteudoGz, string $usuario): array {
    $json = @gzdecode($conteudoGz);
    if ($json === false) {
        $json = $conteudoGz;
    }
    if (strlen($json) > 80 * 1024 * 1024) {
        return [false, 'Arquivo grande demais.'];
    }
    $d = json_decode($json, true);
    if (!is_array($d) || ($d['empresa'] ?? '') !== 'pede-coxinha' || (int) ($d['versao'] ?? 0) !== 1
        || !is_array($d['pedidos'] ?? null) || !is_array($d['itens'] ?? null) || !is_array($d['clientes'] ?? null)) {
        return [false, 'Esse arquivo não é um arquivo de dados da Pede Coxinha.'];
    }
    $dt = function ($v): ?string {
        return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $v) ? $v : null;
    };
    $txt = function ($v, int $max): string {
        return mb_substr(trim((string) $v), 0, $max);
    };
    $canais = ['balcao', 'entrega', 'retirada'];

    $lote = function (string $tabela, array $cols, array $linhas) use ($pdo): void {
        $upd = implode(', ', array_map(function ($c) { return "$c = VALUES($c)"; }, array_slice($cols, 1)));
        foreach (array_chunk($linhas, 400) as $parte) {
            $ph = '(' . implode(',', array_fill(0, count($cols), '?')) . ')';
            $sql = "INSERT INTO $tabela (" . implode(',', $cols) . ') VALUES ' . implode(',', array_fill(0, count($parte), $ph)) . " ON DUPLICATE KEY UPDATE $upd";
            $vals = [];
            foreach ($parte as $l) {
                foreach ($l as $v) {
                    $vals[] = $v;
                }
            }
            $pdo->prepare($sql)->execute($vals);
        }
    };

    $cli = [];
    foreach ($d['clientes'] as $c) {
        if (!is_array($c) || !is_int($c['id'] ?? null)) {
            continue;
        }
        $cli[] = [$c['id'], $txt($c['nome'] ?? '', 120), preg_replace('/\D/', '', $txt($c['fone'] ?? '', 20)), $txt($c['bairro'] ?? '', 80)];
    }
    $ped = [];
    foreach ($d['pedidos'] as $p) {
        $aberto = $dt($p['aberto'] ?? null);
        if (!is_array($p) || !is_int($p['id'] ?? null) || !$aberto) {
            continue;
        }
        $canal = in_array($p['canal'] ?? '', $canais, true) ? $p['canal'] : 'balcao';
        $ped[] = [$p['id'], (int) ($p['numero'] ?? 0), $aberto, round((float) ($p['total'] ?? 0), 2), round((float) ($p['entrega'] ?? 0), 2),
            $canal, empty($p['agendado']) ? 0 : 1, $dt($p['previsto'] ?? null), is_int($p['cliente_id'] ?? null) ? $p['cliente_id'] : null,
            isset($p['escola']) && $p['escola'] !== null ? $txt($p['escola'], 60) : null, empty($p['excluido']) ? 0 : 1, $txt($p['pagamento'] ?? '', 40)];
    }
    $its = [];
    foreach ($d['itens'] as $i) {
        if (!is_array($i) || !is_int($i['id'] ?? null) || !is_int($i['pedido_id'] ?? null)) {
            continue;
        }
        $its[] = [$i['id'], $i['pedido_id'], empty($i['pai']) ? 0 : 1, $txt($i['produto'] ?? '', 120), $txt($i['categoria'] ?? '', 40),
            round((float) ($i['qtd'] ?? 0), 3), round((float) ($i['valor'] ?? 0), 2), (int) ($i['mini'] ?? 0)];
    }
    if (!$ped) {
        return [false, 'O arquivo não tem pedidos.'];
    }

    $pdo->beginTransaction();
    try {
        $lote('pc_clientes', ['id', 'nome', 'fone', 'bairro'], $cli);
        $lote('pc_pedidos', ['id', 'numero', 'aberto', 'total', 'entrega', 'canal', 'agendado', 'previsto', 'cliente_id', 'escola', 'excluido', 'pagamento'], $ped);
        $lote('pc_itens', ['id', 'pedido_id', 'pai', 'produto', 'categoria', 'qtd', 'valor', 'mini'], $its);
        $info = is_array($d['info'] ?? null) ? $d['info'] : [];
        $st = $pdo->prepare('REPLACE INTO an_info (empresa, chave, valor) VALUES ("pede-coxinha", :c, :v)');
        foreach (['notas_problema', 'ultimo_pedido'] as $k) {
            if (isset($info[$k])) {
                $st->execute(['c' => $k, 'v' => json_encode($info[$k], JSON_UNESCAPED_UNICODE)]);
            }
        }
        $pdo->prepare('INSERT INTO an_importacoes (empresa, fonte, gerado_em, pedidos, itens, clientes, usuario) VALUES ("pede-coxinha", :f, :g, :p, :i, :c, :u)')
            ->execute(['f' => $txt($d['fonte'] ?? '', 40), 'g' => $dt($d['gerado_em'] ?? null), 'p' => count($ped), 'i' => count($its), 'c' => count($cli), 'u' => $usuario]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return [true, number_format(count($ped), 0, ',', '.') . ' pedidos, ' . number_format(count($its), 0, ',', '.') . ' itens e '
        . number_format(count($cli), 0, ',', '.') . ' clientes importados.'];
}

/** Lê um valor guardado em an_info. */
function castelo_an_info(PDO $pdo, string $empresa, string $chave) {
    $st = $pdo->prepare('SELECT valor FROM an_info WHERE empresa = :e AND chave = :c');
    $st->execute(['e' => $empresa, 'c' => $chave]);
    $v = $st->fetchColumn();
    return $v === false ? null : json_decode($v, true);
}

/** Executa uma consulta e devolve todas as linhas. */
function castelo_q(PDO $pdo, string $sql, array $p = []): array {
    $st = $pdo->prepare($sql);
    $st->execute($p);
    return $st->fetchAll();
}

/** Variação percentual formatada (+12% / −8%) com classe de cor. */
function castelo_var(float $atual, float $anterior): string {
    if ($anterior <= 0) {
        return '<span class="an-var">novo</span>';
    }
    $v = ($atual / $anterior - 1) * 100;
    $cls = $v >= 0 ? 'an-var an-sobe' : 'an-var an-desce';
    return '<span class="' . $cls . '">' . ($v >= 0 ? '+' : '−') . number_format(abs($v), 0, ',', '.') . '%</span>';
}

function castelo_num(float $v, int $casas = 0): string {
    return number_format($v, $casas, ',', '.');
}

/**
 * Gráfico de barras simples (uma ou duas séries empilhadas).
 * $dados: [rótulo => [valor1, valor2]] ou [rótulo => valor].
 */
function castelo_svg_barras(array $dados, array $op = []): string {
    $w = $op['largura'] ?? 760; $h = $op['altura'] ?? 220; $pad = 30; $n = max(1, count($dados));
    $fmt = $op['formato'] ?? 'num';
    $f = function (float $v) use ($fmt): string {
        return $fmt === 'reais' ? castelo_reais($v) : castelo_num($v);
    };
    $tot = [];
    foreach ($dados as $k => $v) {
        $tot[$k] = is_array($v) ? array_sum($v) : (float) $v;
    }
    $max = max(1, ...array_values($tot));
    $bw = ($w - $pad * 2) / $n;
    $cores = ['url(#gan)', '#5FD39A'];
    $svg = '<svg class="fin-grafico" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . htmlspecialchars($op['titulo'] ?? 'Gráfico', ENT_QUOTES) . '">';
    $svg .= '<defs><linearGradient id="gan" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#E3C27F"/><stop offset="1" stop-color="#8C6D2D"/></linearGradient></defs>';
    $i = 0;
    $passo = max(1, (int) ceil($n / 14));
    foreach ($dados as $rot => $v) {
        $vals = is_array($v) ? array_values($v) : [(float) $v];
        $x = $pad + $i * $bw + $bw * 0.15;
        $base = $h - $pad;
        foreach ($vals as $s => $val) {
            $bh = $val > 0 ? max(1.5, ($h - $pad * 2) * $val / $max) : 0;
            if ($bh > 0) {
                $base -= $bh;
                $nomeSerie = $op['series'][$s] ?? '';
                $svg .= '<rect x="' . round($x, 1) . '" y="' . round($base, 1) . '" width="' . round($bw * 0.7, 1) . '" height="' . round($bh, 1) . '" rx="2.5" fill="' . $cores[$s % 2] . '"><title>'
                    . htmlspecialchars($rot . ($nomeSerie ? ' · ' . $nomeSerie : '') . ': ' . $f($val), ENT_QUOTES) . '</title></rect>';
            }
        }
        if ($i % $passo === 0 || $i === $n - 1) {
            $svg .= '<text x="' . round($x + $bw * 0.35, 1) . '" y="' . ($h - 9) . '" text-anchor="middle" class="g-eixo">' . htmlspecialchars((string) $rot, ENT_QUOTES) . '</text>';
        }
        $i++;
    }
    $svg .= '<text x="' . $pad . '" y="' . ($pad - 10) . '" class="g-eixo">máx. ' . htmlspecialchars($f($max), ENT_QUOTES) . '</text>';
    return $svg . '</svg>';
}
