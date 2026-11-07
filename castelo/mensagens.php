<?php
/** Castelo: mensagens 1 a 1 pros clientes da Pede Coxinha, pelo WhatsApp do celular, sem disparo em massa. */
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/analises.php';
destrava_admin_require_login();
$pdo = destrava_db();

const MSG_LIMITE_DIA = 30;
const MSG_INTERVALO_DIAS = 30;
$segs = [
    'esfriando' => ['Cento esfriando', 'Já pediram cento, têm 2 ou mais pedidos e estão sem comprar há 61 a 180 dias.'],
    'recompra' => ['Hora de recomprar', 'O último cento foi há 35 a 50 dias. O intervalo médio entre centos é de 47 dias.'],
    'segunda' => ['Segunda compra', 'Compraram uma vez só, há 7 a 60 dias. O objetivo é o segundo pedido.'],
    'sumiu' => ['Sumiram', 'Têm 2 ou mais pedidos e estão sem comprar há 181 a 540 dias.'],
    'fieis' => ['Clientes fiéis', '5 ou mais pedidos e compraram nos últimos 60 dias. Mensagem de agradecimento, sem empurrar venda.'],
];

// Registro de envio (vem do clique no botão, sem sair da página) e "não quer receber"
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $cid = (int) ($_POST['cliente'] ?? 0);
    $ok = destrava_csrf_check($_POST['csrf_token'] ?? '');
    if ($acao === 'enviado') {
        if ($ok && $cid > 0 && isset($segs[$_POST['seg'] ?? ''])) {
            $pdo->prepare('INSERT INTO pc_msg_envios (cliente_id, segmento, modelo_id, usuario) VALUES (:c, :s, :m, :u)')
                ->execute(['c' => $cid, 's' => $_POST['seg'], 'm' => (int) ($_POST['modelo'] ?? 0) ?: null, 'u' => (string) ($_SESSION['admin_username'] ?? '')]);
        }
        http_response_code(204);
        exit;
    }
    $volta = 'mensagens.php?seg=' . rawurlencode((string) ($_POST['seg'] ?? 'esfriando'));
    if ($ok && $acao === 'optout' && $cid > 0) {
        $pdo->prepare('INSERT IGNORE INTO pc_msg_optout (cliente_id) VALUES (:c)')->execute(['c' => $cid]);
    } elseif ($ok && $acao === 'modelo' && isset($segs[$_POST['seg'] ?? ''])) {
        $txt = trim((string) ($_POST['texto'] ?? ''));
        $mid = (int) ($_POST['modelo'] ?? 0);
        if ($mid > 0 && ($_POST['apagar'] ?? '') === '1') {
            $pdo->prepare('UPDATE pc_msg_modelos SET ativo = 0 WHERE id = :id')->execute(['id' => $mid]);
        } elseif ($txt !== '' && mb_strlen($txt) <= 700) {
            if ($mid > 0) {
                $pdo->prepare('UPDATE pc_msg_modelos SET texto = :t WHERE id = :id')->execute(['t' => $txt, 'id' => $mid]);
            } else {
                $pdo->prepare('INSERT INTO pc_msg_modelos (segmento, texto) VALUES (:s, :t)')->execute(['s' => $_POST['seg'], 't' => $txt]);
            }
        }
        $volta .= '#modelos';
    }
    header('Location: ' . $volta);
    exit;
}

$seg = isset($segs[$_GET['seg'] ?? '']) ? $_GET['seg'] : 'esfriando';
$ult = castelo_an_info($pdo, 'pede-coxinha', 'ultimo_pedido');
destrava_admin_head('Mensagens', 'mensagens');
if (!$ult): ?>
  <div class="page-head"><div><p class="intersect-kicker">Pede Coxinha</p><h1>Mensagens</h1></div></div>
  <p class="empty-state">Ainda não tem dados de clientes. <a href="analise-importar.php">Importe o arquivo do Consumer</a>.</p>
<?php destrava_admin_foot(); exit; endif;

$ref = date('Y-m-d 23:59:59', strtotime($ult));
$hoje = (int) castelo_q($pdo, 'SELECT COUNT(*) n FROM pc_msg_envios WHERE DATE(enviado_em) = CURDATE()')[0]['n'];
$modelos = castelo_q($pdo, 'SELECT id, texto FROM pc_msg_modelos WHERE segmento = :s AND ativo = 1 ORDER BY id', ['s' => $seg]);

// Base de clientes com telefone, fora de quem pediu pra não receber e de quem recebeu nos últimos 30 dias
$base = castelo_q($pdo, 'SELECT c.id, c.nome, c.fone, c.bairro, COUNT(*) n, SUM(p.total - p.lanche_valor) v, MAX(p.aberto) u,
    MAX(CASE WHEN EXISTS(SELECT 1 FROM pc_itens x WHERE x.pedido_id = p.id AND x.pai = 0 AND x.categoria = "cento") THEN p.aberto END) uc
    FROM pc_pedidos p JOIN pc_clientes c ON c.id = p.cliente_id
    WHERE p.excluido = 0 AND p.so_lanche = 0 AND p.aberto <= :r AND LENGTH(c.fone) >= 10
      AND c.id NOT IN (SELECT cliente_id FROM pc_msg_optout)
      AND c.id NOT IN (SELECT cliente_id FROM pc_msg_envios WHERE enviado_em >= DATE_SUB(NOW(), INTERVAL ' . MSG_INTERVALO_DIAS . ' DAY))
    GROUP BY c.id, c.nome, c.fone, c.bairro', ['r' => $ref]);
$dias = function (?string $d) use ($ref): ?int {
    return $d ? (int) floor((strtotime($ref) - strtotime($d)) / 86400) : null;
};
$contagem = array_fill_keys(array_keys($segs), 0);
$lista = [];
foreach ($base as $c) {
    $du = $dias($c['u']);
    $dc = $dias($c['uc']);
    $em = [];
    if ($c['n'] >= 2 && $c['uc'] && $du >= 61 && $du <= 180) {
        $em[] = 'esfriando';
    }
    if ($c['uc'] && $dc >= 35 && $dc <= 50 && $du >= 35) {
        $em[] = 'recompra';
    }
    if ($c['n'] == 1 && $du >= 7 && $du <= 60) {
        $em[] = 'segunda';
    }
    if ($c['n'] >= 2 && $du >= 181 && $du <= 540) {
        $em[] = 'sumiu';
    }
    if ($c['n'] >= 5 && $du <= 60) {
        $em[] = 'fieis';
    }
    foreach ($em as $s) {
        $contagem[$s]++;
    }
    if (in_array($seg, $em, true)) {
        $c['dias'] = $du;
        $lista[] = $c;
    }
}
usort($lista, function ($a, $b) {
    return $b['v'] <=> $a['v'];
});
$total = count($lista);
$lista = array_slice($lista, 0, 60);

// Produto que cada cliente da lista mais pede
$fav = [];
if ($lista) {
    $ids = implode(',', array_map('intval', array_column($lista, 'id')));
    foreach (castelo_q($pdo, 'SELECT p.cliente_id, it.produto, SUM(it.qtd) q FROM pc_itens it JOIN pc_pedidos p ON p.id = it.pedido_id
        WHERE p.excluido = 0 AND it.pai = 0 AND it.categoria <> "bebida" AND NOT ' . castelo_pc_lanche('it.') . ' AND p.cliente_id IN (' . $ids . ')
        GROUP BY p.cliente_id, it.produto ORDER BY p.cliente_id, q DESC') as $r) {
        if (!isset($fav[$r['cliente_id']])) {
            $fav[$r['cliente_id']] = $r['produto'];
        }
    }
}
$primeiroNome = function (string $nome): string {
    $p = preg_split('/\s+/', trim($nome));
    $n = $p[0] ?? '';
    if (mb_strlen($n) < 2 || preg_match('/\d|escola|col[eé]gio|creche|apae|prefeitura/iu', $nome)) {
        return '';
    }
    return mb_convert_case(mb_strtolower($n), MB_CASE_TITLE);
};
$refBase = $ref;
$monta = function (string $modelo, array $c) use ($fav, $primeiroNome, $refBase): string {
    $nome = $primeiroNome((string) $c['nome']);
    $prod = mb_strtolower($fav[$c['id']] ?? 'cento de salgados');
    $prim = preg_split('/\s+/', $prod)[0] ?? '';
    $fem = (bool) preg_match('/as?$/u', $prim);
    $ref = $c['uc'] ?: $c['u'];
    $mesesN = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    $quando = 'em ' . $mesesN[(int) date('n', strtotime($ref)) - 1] . (date('Y', strtotime($ref)) !== date('Y', strtotime($refBase)) ? ' de ' . date('Y', strtotime($ref)) : '');
    $t = str_replace(['{nome}', '{seu_produto}', '{o_produto}', '{do_produto}', '{produto}', '{quando}'],
        [$nome, ($fem ? 'sua ' : 'seu ') . $prod, ($fem ? 'a ' : 'o ') . $prod, ($fem ? 'da ' : 'do ') . $prod, $prod, $quando], $modelo);
    if ($nome === '') {
        $t = preg_replace(['/^Oi, ,? ?/u', '/^, ?/u', '/^,\s*/u'], ['Oi! ', '', ''], $t);
        $t = preg_replace('/^Oi! tudo bem\?/u', 'Oi, tudo bem?', $t);
        $t = mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1);
    }
    return $t;
};
$wa = function (string $fone, string $texto): string {
    $d = preg_replace('/\D/', '', $fone);
    return 'https://wa.me/' . (strlen($d) <= 11 ? '55' : '') . $d . '?text=' . rawurlencode($texto);
};
$csrf = destrava_csrf_token();
?>
<div class="page-head fin-head">
  <div>
    <p class="intersect-kicker">Pede Coxinha</p>
    <h1>Mensagens 1 a 1</h1>
    <p class="muted">Cada botão abre o WhatsApp com a mensagem pronta. Confira, envie e volte aqui. Quem recebeu some da lista por <?= MSG_INTERVALO_DIAS ?> dias. Dados de clientes até <?= h(date('d/m/Y', strtotime($ult))) ?>.</p>
  </div>
  <div class="msg-contador <?= $hoje >= MSG_LIMITE_DIA ? 'is-limite' : '' ?>">
    <span class="kpi-rotulo">Enviadas hoje</span>
    <strong><span data-msg-hoje><?= $hoje ?></span> de <?= MSG_LIMITE_DIA ?></strong>
    <span class="kpi-nota"><?= $hoje >= MSG_LIMITE_DIA ? 'Limite do dia atingido. Continue amanhã pra proteger o número.' : 'Limite pra proteger o número' ?></span>
  </div>
</div>

<nav class="filter-bar" aria-label="Grupos de clientes">
  <?php foreach ($segs as $k => [$nome]): ?>
    <a class="filter-chip <?= $k === $seg ? 'is-active' : '' ?>" href="mensagens.php?seg=<?= h($k) ?>"><?= h($nome) ?> · <?= $contagem[$k] ?></a>
  <?php endforeach; ?>
</nav>
<p class="muted msg-desc"><?= h($segs[$seg][1]) ?> <?= $total > 60 ? 'Mostrando os 60 que mais gastaram de ' . $total . '.' : '' ?></p>

<section class="painel-card msg-regras">
  <strong>Pra não perder o número:</strong> até <?= MSG_LIMITE_DIA ?> por dia · só pra quem já comprou · um texto diferente pra cada cliente (o Castelo já alterna) · se a pessoa pedir pra parar, clique em "Não quer receber" · se começarem a bloquear, pare por uns dias.
</section>

<?php if (!$modelos): ?>
  <p class="empty-state">Esse grupo não tem mensagem cadastrada. Crie uma lá embaixo.</p>
<?php elseif (!$lista): ?>
  <p class="empty-state">Ninguém nesse grupo agora.</p>
<?php else: ?>
<div class="msg-lista">
  <?php foreach ($lista as $i => $c): $mo = $modelos[$c['id'] % count($modelos)]; $txt = $monta($mo['texto'], $c); ?>
    <article class="painel-card msg-item">
      <div class="msg-quem">
        <strong><?= h($c['nome']) ?></strong>
        <span class="muted"><?= (int) $c['n'] ?> pedido<?= $c['n'] > 1 ? 's' : '' ?> · <?= castelo_reais((float) $c['v']) ?> · último há <?= (int) $c['dias'] ?> dias<?= mb_strlen(trim($c['bairro'], ' .-')) >= 3 ? ' · ' . h($c['bairro']) : '' ?></span>
        <span class="muted">Mais pede: <?= h($fav[$c['id']] ?? '—') ?></span>
      </div>
      <p class="msg-texto"><?= h($txt) ?></p>
      <div class="msg-acoes">
        <a class="btn-whatsapp" href="<?= h($wa($c['fone'], $txt)) ?>" target="_blank" rel="noopener"
           data-msg-envio data-cliente="<?= (int) $c['id'] ?>" data-seg="<?= h($seg) ?>" data-modelo="<?= (int) $mo['id'] ?>" data-csrf="<?= h($csrf) ?>">Abrir no WhatsApp</a>
        <form method="post" data-confirm="<?= h($c['nome']) ?> não quer mais receber mensagens? Ela sai de todas as listas.">
          <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>"><input type="hidden" name="acao" value="optout">
          <input type="hidden" name="cliente" value="<?= (int) $c['id'] ?>"><input type="hidden" name="seg" value="<?= h($seg) ?>">
          <button type="submit" class="btn-link">Não quer receber</button>
        </form>
      </div>
    </article>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<section class="painel-card" id="modelos">
  <h2>Mensagens do grupo "<?= h($segs[$seg][0]) ?>"</h2>
  <p class="muted">O Castelo alterna entre elas pra cada cliente receber um texto diferente. Use <code>{nome}</code> pro primeiro nome. Pro que a pessoa mais pede: <code>{seu_produto}</code> (sua coxinha / seu cento), <code>{o_produto}</code> (a coxinha / o cento), <code>{do_produto}</code> (da coxinha / do cento) ou <code>{produto}</code> (só o nome). <code>{quando}</code> vira o mês do último pedido ("em agosto"). Evite frases que deem a entender que houve problema.</p>
  <?php foreach ($modelos as $i => $mo): ?>
    <form method="post" class="crm-form msg-modelo">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>"><input type="hidden" name="acao" value="modelo">
      <input type="hidden" name="seg" value="<?= h($seg) ?>"><input type="hidden" name="modelo" value="<?= (int) $mo['id'] ?>">
      <label>Variação <?= $i + 1 ?><textarea name="texto" rows="3" maxlength="700"><?= h($mo['texto']) ?></textarea></label>
      <div class="msg-modelo-acoes">
        <button type="submit" class="btn-sec">Salvar</button>
        <button type="submit" name="apagar" value="1" class="btn-link" data-confirm-click="Apagar essa variação?">Apagar</button>
      </div>
    </form>
  <?php endforeach; ?>
  <form method="post" class="crm-form msg-modelo">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>"><input type="hidden" name="acao" value="modelo"><input type="hidden" name="seg" value="<?= h($seg) ?>">
    <label>Nova variação<textarea name="texto" rows="3" maxlength="700" placeholder="Oi, {nome}! Aqui é da Pede Coxinha..."></textarea></label>
    <div class="msg-modelo-acoes"><button type="submit" class="btn-primary">Adicionar</button></div>
  </form>
</section>
<?php destrava_admin_foot();
