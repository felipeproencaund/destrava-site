<?php
/** Castelo: Financeiro (carteiras Casa, MEIs e empresas do grupo). */
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/layout.php';
destrava_admin_require_login();
$pdo = destrava_db();

// ---- mês e carteira escolhidos ----
$mes = preg_match('/^\d{4}-\d{2}$/', $_GET['mes'] ?? '') ? $_GET['mes'] : date('Y-m');
$ini = $mes . '-01';
$fim = date('Y-m-t', strtotime($ini));
$carteiras = $pdo->query('SELECT id, nome FROM fin_carteiras WHERE ativa = 1 ORDER BY ordem, id')->fetchAll();
$idsCarteiras = array_map('intval', array_column($carteiras, 'id'));
$c = (int) ($_GET['c'] ?? 0);
if (!in_array($c, $idsCarteiras, true)) {
    $c = 0;
}
$qs = function (array $extra = []) use ($mes, $c): string {
    return '?' . http_build_query(array_merge(['mes' => $mes, 'c' => $c ?: null], $extra));
};

// ---- contas fixas: gera os lançamentos do mês atual uma única vez ----
$mesAtual = date('Y-m');
$rec = $pdo->prepare("SELECT * FROM fin_recorrentes WHERE ativo = 1 AND (ultimo_mes IS NULL OR ultimo_mes < :m)");
$rec->execute(['m' => $mesAtual]);
foreach ($rec->fetchAll() as $r) {
    $dia = min((int) $r['dia'], (int) date('t'));
    $pdo->prepare('INSERT INTO fin_lancamentos (carteira_id, categoria_id, tipo, descricao, valor, data, pago, recorrente_id, criado_por)
        VALUES (:c, :k, :t, :d, :v, :dt, 0, :r, :u)')->execute([
        'c' => $r['carteira_id'], 'k' => $r['categoria_id'], 't' => $r['tipo'], 'd' => $r['descricao'], 'v' => $r['valor'],
        'dt' => $mesAtual . '-' . str_pad((string) $dia, 2, '0', STR_PAD_LEFT), 'r' => $r['id'], 'u' => 'conta fixa',
    ]);
    $pdo->prepare('UPDATE fin_recorrentes SET ultimo_mes = :m WHERE id = :id')->execute(['m' => $mesAtual, 'id' => $r['id']]);
}

// ---- ações ----
$msg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!destrava_csrf_check($_POST['csrf_token'] ?? '')) {
        $msg = ['error', 'Sessão expirada. Tente de novo.'];
    } else {
        $acao = $_POST['acao'] ?? '';
        if ($acao === 'lancar') {
            $tipo = ($_POST['tipo'] ?? '') === 'entrada' ? 'entrada' : 'saida';
            $cart = (int) ($_POST['carteira_id'] ?? 0);
            $cat = (int) ($_POST['categoria_id'] ?? 0);
            $desc = destrava_sanitize_str($_POST['descricao'] ?? '', 120);
            $valor = castelo_valor($_POST['valor'] ?? '');
            $data = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['data'] ?? '') ? $_POST['data'] : date('Y-m-d');
            $pago = !empty($_POST['pago']) ? 1 : 0;
            $okCat = $pdo->prepare('SELECT COUNT(*) FROM fin_categorias WHERE id = :k AND carteira_id = :c AND tipo = :t');
            $okCat->execute(['k' => $cat, 'c' => $cart, 't' => $tipo]);
            if (!in_array($cart, $idsCarteiras, true)) {
                $msg = ['error', 'Escolha a carteira.'];
            } elseif ($valor === null) {
                $msg = ['error', 'Valor inválido. Use, por exemplo, 150 ou 1.250,90.'];
            } elseif (is_string($arq = castelo_salvar_comprovante($_FILES['comprovante'] ?? []))) {
                $msg = ['error', $arq];
            } else {
                $pdo->prepare('INSERT INTO fin_lancamentos (carteira_id, categoria_id, tipo, descricao, valor, data, pago, criado_por, comprovante, comprovante_tipo)
                    VALUES (:c, :k, :t, :d, :v, :dt, :p, :u, :arq, :mime)')->execute([
                    'c' => $cart, 'k' => (int) $okCat->fetchColumn() ? $cat : null, 't' => $tipo,
                    'd' => $desc !== '' ? $desc : ($tipo === 'entrada' ? 'Entrada' : 'Saída'),
                    'v' => $valor, 'dt' => $data, 'p' => $pago, 'u' => $_SESSION['admin_username'] ?? '',
                    'arq' => $arq[0] ?? null, 'mime' => $arq[1] ?? null,
                ]);
                header('Location: financeiro.php' . $qs(['ok' => 1]) . '#lancamentos');
                exit;
            }
        } elseif ($acao === 'anexar') {
            $id = (int) ($_POST['id'] ?? 0);
            $arq = castelo_salvar_comprovante($_FILES['comprovante'] ?? []);
            if (is_array($arq)) {
                $pdo->prepare('UPDATE fin_lancamentos SET comprovante = :a, comprovante_tipo = :m WHERE id = :id')->execute(['a' => $arq[0], 'm' => $arq[1], 'id' => $id]);
                header('Location: financeiro.php' . $qs(['anexo' => 1]) . '#lancamentos');
                exit;
            }
            $msg = ['error', is_string($arq) ? $arq : 'Escolha um arquivo.'];
        } elseif ($acao === 'pago' || $acao === 'excluir') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($acao === 'pago') {
                $pdo->prepare('UPDATE fin_lancamentos SET pago = 1 - pago WHERE id = :id')->execute(['id' => $id]);
            } else {
                $velho = $pdo->prepare('SELECT comprovante FROM fin_lancamentos WHERE id = :id');
                $velho->execute(['id' => $id]);
                $arqVelho = (string) $velho->fetchColumn();
                $pdo->prepare('DELETE FROM fin_lancamentos WHERE id = :id')->execute(['id' => $id]);
                if ($arqVelho !== '' && preg_match('/^[a-f0-9]{32}\.[a-z]+$/', $arqVelho)) {
                    @unlink(castelo_pasta_comprovantes() . '/' . $arqVelho);
                }
            }
            header('Location: financeiro.php' . $qs() . '#lancamentos');
            exit;
        }
    }
}

// ---- números do mês ----
$filtro = 'l.data BETWEEN :ini AND :fim' . ($c ? ' AND l.carteira_id = :c' : '');
$par = ['ini' => $ini, 'fim' => $fim] + ($c ? ['c' => $c] : []);
$tot = $pdo->prepare("SELECT
    COALESCE(SUM(CASE WHEN tipo='entrada' THEN valor END),0) AS entradas,
    COALESCE(SUM(CASE WHEN tipo='saida' THEN valor END),0) AS saidas,
    COALESCE(SUM(CASE WHEN tipo='saida' AND pago=0 THEN valor END),0) AS a_pagar,
    COALESCE(SUM(CASE WHEN tipo='entrada' AND pago=0 THEN valor END),0) AS a_receber
    FROM fin_lancamentos l WHERE $filtro");
$tot->execute($par);
$t = $tot->fetch();
$saldo = $t['entradas'] - $t['saidas'];

$porCart = $pdo->prepare("SELECT k.id, k.nome,
    COALESCE(SUM(CASE WHEN l.tipo='entrada' THEN l.valor END),0) AS entradas,
    COALESCE(SUM(CASE WHEN l.tipo='saida' THEN l.valor END),0) AS saidas
    FROM fin_carteiras k LEFT JOIN fin_lancamentos l ON l.carteira_id = k.id AND l.data BETWEEN :ini AND :fim
    WHERE k.ativa = 1 GROUP BY k.id, k.nome, k.ordem ORDER BY k.ordem, k.id");
$porCart->execute(['ini' => $ini, 'fim' => $fim]);
$porCart = $porCart->fetchAll();

$porCat = $pdo->prepare("SELECT COALESCE(g.nome, 'Sem categoria') AS nome, SUM(l.valor) AS total
    FROM fin_lancamentos l LEFT JOIN fin_categorias g ON g.id = l.categoria_id
    WHERE l.tipo = 'saida' AND $filtro GROUP BY g.nome ORDER BY total DESC LIMIT 8");
$porCat->execute($par);
$porCat = $porCat->fetchAll();
$maiorCat = $porCat ? max(array_map('floatval', array_column($porCat, 'total'))) : 1;

$lanc = $pdo->prepare("SELECT l.*, k.nome AS carteira, g.nome AS categoria FROM fin_lancamentos l
    JOIN fin_carteiras k ON k.id = l.carteira_id LEFT JOIN fin_categorias g ON g.id = l.categoria_id
    WHERE $filtro ORDER BY l.data DESC, l.id DESC LIMIT 400");
$lanc->execute($par);
$lanc = $lanc->fetchAll();

$cats = $pdo->query('SELECT id, carteira_id, tipo, nome FROM fin_categorias WHERE ativa = 1 ORDER BY nome')->fetchAll();

$meses = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
$nomeMes = $meses[(int) substr($mes, 5, 2) - 1] . ' de ' . substr($mes, 0, 4);
$ant = date('Y-m', strtotime($ini . ' -1 month'));
$prox = date('Y-m', strtotime($ini . ' +1 month'));
$csrf = destrava_csrf_token();

destrava_admin_head('Financeiro', 'financeiro');
?>
<div class="page-head fin-head">
  <div>
    <p class="intersect-kicker">Financeiro</p>
    <h1><?= h(ucfirst($nomeMes)) ?></h1>
  </div>
  <div class="fin-mes">
    <a class="btn-sec" href="financeiro.php?<?= h(http_build_query(['mes' => $ant, 'c' => $c ?: null])) ?>">← <?= h($meses[(int) substr($ant, 5, 2) - 1]) ?></a>
    <a class="btn-sec" href="financeiro.php?<?= h(http_build_query(['mes' => $prox, 'c' => $c ?: null])) ?>"><?= h($meses[(int) substr($prox, 5, 2) - 1]) ?> →</a>
    <a class="btn-sec" href="fin-config.php">Configurar</a>
  </div>
</div>

<?php if ($msg): ?><p class="flash flash-<?= h($msg[0]) ?>"><?= h($msg[1]) ?></p><?php endif; ?>
<?php if (!empty($_GET['ok'])): ?><p class="flash flash-success">Lançamento salvo.</p><?php endif; ?>
<?php if (!empty($_GET['anexo'])): ?><p class="flash flash-success">Comprovante anexado.</p><?php endif; ?>

<nav class="filter-bar" aria-label="Carteiras">
  <a class="filter-chip <?= $c === 0 ? 'is-active' : '' ?>" href="financeiro.php?mes=<?= h($mes) ?>">Todas</a>
  <?php foreach ($carteiras as $k): ?>
    <a class="filter-chip <?= $c === (int) $k['id'] ? 'is-active' : '' ?>" href="financeiro.php?mes=<?= h($mes) ?>&amp;c=<?= (int) $k['id'] ?>"><?= h($k['nome']) ?></a>
  <?php endforeach; ?>
</nav>

<section class="kpis">
  <div class="kpi"><span class="kpi-rotulo">Entradas</span><strong class="valor-entrada"><?= castelo_reais((float) $t['entradas']) ?></strong><span class="kpi-nota"><?= castelo_reais((float) $t['a_receber']) ?> ainda a receber</span></div>
  <div class="kpi"><span class="kpi-rotulo">Saídas</span><strong class="valor-saida"><?= castelo_reais((float) $t['saidas']) ?></strong><span class="kpi-nota">tudo que foi lançado no mês</span></div>
  <div class="kpi kpi-alerta"><span class="kpi-rotulo">Saldo do mês</span><strong><?= castelo_reais((float) $saldo) ?></strong><span class="kpi-nota">entradas menos saídas</span></div>
  <div class="kpi"><span class="kpi-rotulo">Falta pagar</span><strong><?= castelo_reais((float) $t['a_pagar']) ?></strong><span class="kpi-nota">saídas ainda não pagas</span></div>
</section>

<div class="painel-grade">
  <section class="painel-card fin-lancar">
    <h2>Novo lançamento</h2>
    <form method="post" class="crm-form fin-form" data-fin-form enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <input type="hidden" name="acao" value="lancar">
      <div class="fin-tipo" role="radiogroup" aria-label="Tipo">
        <label><input type="radio" name="tipo" value="saida" checked> Saída</label>
        <label><input type="radio" name="tipo" value="entrada"> Entrada</label>
      </div>
      <label>Carteira
        <select name="carteira_id" required data-fin-carteira>
          <?php foreach ($carteiras as $k): ?><option value="<?= (int) $k['id'] ?>" <?= $c === (int) $k['id'] ? 'selected' : '' ?>><?= h($k['nome']) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label>Categoria
        <select name="categoria_id" data-fin-categoria>
          <?php foreach ($cats as $g): ?><option value="<?= (int) $g['id'] ?>" data-carteira="<?= (int) $g['carteira_id'] ?>" data-tipo="<?= h($g['tipo']) ?>"><?= h($g['nome']) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label>Descrição<input type="text" name="descricao" maxlength="120" placeholder="Ex.: mercado do mês, cliente Pedra Angular"></label>
      <div class="fin-linha">
        <label>Valor (R$)<input type="text" name="valor" inputmode="decimal" placeholder="0,00" required></label>
        <label>Data<input type="date" name="data" value="<?= h($mes === date('Y-m') ? date('Y-m-d') : $ini) ?>" required></label>
      </div>
      <label>Comprovante ou nota (foto ou PDF, opcional)<input type="file" name="comprovante" accept="image/*,application/pdf"></label>
      <label class="fin-check"><input type="checkbox" name="pago" value="1" checked> Já foi pago / recebido</label>
      <button type="submit" class="btn-primary">Salvar lançamento</button>
    </form>
  </section>

  <section class="painel-card">
    <h2>Por carteira</h2>
    <div class="table-wrap fin-tabela">
      <table>
        <thead><tr><th>Carteira</th><th>Entradas</th><th>Saídas</th><th>Saldo</th></tr></thead>
        <tbody>
        <?php foreach ($porCart as $k): $s = $k['entradas'] - $k['saidas']; ?>
          <tr>
            <td><a href="financeiro.php?mes=<?= h($mes) ?>&amp;c=<?= (int) $k['id'] ?>"><?= h($k['nome']) ?></a></td>
            <td class="valor-entrada"><?= castelo_reais((float) $k['entradas']) ?></td>
            <td class="valor-saida"><?= castelo_reais((float) $k['saidas']) ?></td>
            <td class="<?= $s < 0 ? 'valor-saida' : '' ?>"><strong><?= castelo_reais((float) $s) ?></strong></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <h2 class="fin-sub">Para onde foi o dinheiro</h2>
    <?php if (!$porCat): ?>
      <p class="empty-state">Nenhuma saída lançada neste mês.</p>
    <?php else: ?>
      <ul class="funil-lista">
        <?php foreach ($porCat as $g): ?>
          <li><span class="fin-cat-linha"><span class="funil-nome"><?= h($g['nome']) ?></span><span class="funil-barra"><span data-pct="<?= round($g['total'] / $maiorCat * 100) ?>"></span></span><span class="fin-cat-valor"><?= castelo_reais((float) $g['total']) ?></span></span></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>

<section class="painel-card fin-lista" id="lancamentos">
  <h2>Lançamentos do mês</h2>
  <?php if (!$lanc): ?>
    <p class="empty-state">Nenhum lançamento neste mês. Use o formulário acima pra começar.</p>
  <?php else: ?>
  <div class="table-wrap fin-tabela">
    <table>
      <thead><tr><th>Data</th><th>Descrição</th><th>Carteira</th><th>Categoria</th><th>Valor</th><th>Situação</th><th>Nota</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($lanc as $l): ?>
        <tr class="<?= $l['pago'] ? '' : 'fin-pendente' ?>">
          <td><?= h(date('d/m', strtotime($l['data']))) ?></td>
          <td><?= h($l['descricao']) ?><?= $l['recorrente_id'] ? ' <span class="badge-diag">fixa</span>' : '' ?></td>
          <td><?= h($l['carteira']) ?></td>
          <td><?= h($l['categoria'] ?? '-') ?></td>
          <td class="<?= $l['tipo'] === 'entrada' ? 'valor-entrada' : 'valor-saida' ?>"><?= $l['tipo'] === 'entrada' ? '+' : '−' ?> <?= castelo_reais((float) $l['valor']) ?></td>
          <td>
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
              <input type="hidden" name="acao" value="pago">
              <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
              <button type="submit" class="fin-situacao <?= $l['pago'] ? 'is-ok' : '' ?>" title="Clique para mudar"><?= $l['pago'] ? ($l['tipo'] === 'entrada' ? 'Recebido' : 'Pago') : ($l['tipo'] === 'entrada' ? 'A receber' : 'A pagar') ?></button>
            </form>
          </td>
          <td>
            <?php if ($l['comprovante']): ?>
              <a class="fin-anexo" href="comprovante.php?id=<?= (int) $l['id'] ?>" target="_blank" rel="noopener" title="Ver comprovante">📎 Ver</a>
            <?php else: ?>
              <form method="post" enctype="multipart/form-data" class="fin-anexar">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="acao" value="anexar">
                <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                <label class="fin-anexo fin-anexo-add">+ Anexar<input type="file" name="comprovante" accept="image/*,application/pdf" data-envia-sozinho></label>
              </form>
            <?php endif; ?>
          </td>
          <td>
            <form method="post" data-confirm="Excluir este lançamento?">
              <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
              <input type="hidden" name="acao" value="excluir">
              <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
              <button type="submit" class="btn-icon-danger" title="Excluir">✕</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>
<?php destrava_admin_foot();
