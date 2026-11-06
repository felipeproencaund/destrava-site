<?php
/** Castelo: configurar carteiras, categorias e contas fixas do Financeiro. */
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/layout.php';
destrava_admin_require_login();
$pdo = destrava_db();

$msg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!destrava_csrf_check($_POST['csrf_token'] ?? '')) {
        $msg = ['error', 'Sessão expirada. Tente de novo.'];
    } else {
        $acao = $_POST['acao'] ?? '';
        $nome = destrava_sanitize_str($_POST['nome'] ?? '', 60);
        $id = (int) ($_POST['id'] ?? 0);
        $tipo = ($_POST['tipo'] ?? '') === 'entrada' ? 'entrada' : 'saida';
        $cart = (int) ($_POST['carteira_id'] ?? 0);
        $existeCart = $pdo->prepare('SELECT COUNT(*) FROM fin_carteiras WHERE id = :id');
        $existeCart->execute(['id' => $cart]);
        $cartOk = (int) $existeCart->fetchColumn() > 0;

        if ($acao === 'carteira_nova' && $nome !== '') {
            $ordem = (int) $pdo->query('SELECT COALESCE(MAX(ordem),0)+1 FROM fin_carteiras')->fetchColumn();
            $pdo->prepare('INSERT INTO fin_carteiras (nome, ordem) VALUES (:n, :o)')->execute(['n' => $nome, 'o' => $ordem]);
            $msg = ['success', 'Carteira criada.'];
        } elseif ($acao === 'carteira_renomear' && $nome !== '') {
            $pdo->prepare('UPDATE fin_carteiras SET nome = :n WHERE id = :id')->execute(['n' => $nome, 'id' => $id]);
            $msg = ['success', 'Carteira renomeada.'];
        } elseif ($acao === 'carteira_ativa') {
            $pdo->prepare('UPDATE fin_carteiras SET ativa = 1 - ativa WHERE id = :id')->execute(['id' => $id]);
            $msg = ['success', 'Carteira atualizada.'];
        } elseif ($acao === 'categoria_nova' && $nome !== '' && $cartOk) {
            $pdo->prepare('INSERT INTO fin_categorias (carteira_id, tipo, nome) VALUES (:c, :t, :n)')->execute(['c' => $cart, 't' => $tipo, 'n' => $nome]);
            $msg = ['success', 'Categoria criada.'];
        } elseif ($acao === 'categoria_renomear' && $nome !== '') {
            $pdo->prepare('UPDATE fin_categorias SET nome = :n WHERE id = :id')->execute(['n' => $nome, 'id' => $id]);
            $msg = ['success', 'Categoria renomeada.'];
        } elseif ($acao === 'categoria_ativa') {
            $pdo->prepare('UPDATE fin_categorias SET ativa = 1 - ativa WHERE id = :id')->execute(['id' => $id]);
            $msg = ['success', 'Categoria atualizada.'];
        } elseif ($acao === 'fixa_nova' && $cartOk) {
            $desc = destrava_sanitize_str($_POST['descricao'] ?? '', 120);
            $valor = castelo_valor($_POST['valor'] ?? '');
            $dia = max(1, min(31, (int) ($_POST['dia'] ?? 10)));
            $cat = (int) ($_POST['categoria_id'] ?? 0);
            $okCat = $pdo->prepare('SELECT COUNT(*) FROM fin_categorias WHERE id = :k AND carteira_id = :c AND tipo = :t');
            $okCat->execute(['k' => $cat, 'c' => $cart, 't' => $tipo]);
            if ($desc === '' || $valor === null) {
                $msg = ['error', 'Preencha a descrição e um valor válido (ex.: 76,90).'];
            } else {
                // começa a valer no mês que vem se o dia já passou; senão, já entra neste mês
                $ultimo = $dia < (int) date('j') ? date('Y-m') : null;
                $pdo->prepare('INSERT INTO fin_recorrentes (carteira_id, categoria_id, tipo, descricao, valor, dia, ultimo_mes) VALUES (:c, :k, :t, :d, :v, :dia, :u)')
                    ->execute(['c' => $cart, 'k' => (int) $okCat->fetchColumn() ? $cat : null, 't' => $tipo, 'd' => $desc, 'v' => $valor, 'dia' => $dia, 'u' => $ultimo]);
                $msg = ['success', 'Conta fixa criada.' . ($ultimo ? ' Começa no mês que vem.' : ' Já entrou neste mês.')];
            }
        } elseif ($acao === 'fixa_excluir') {
            $pdo->prepare('DELETE FROM fin_recorrentes WHERE id = :id')->execute(['id' => $id]);
            $msg = ['success', 'Conta fixa removida. Os lançamentos que já foram gerados continuam no histórico.'];
        }
    }
}

$carteiras = $pdo->query('SELECT * FROM fin_carteiras ORDER BY ordem, id')->fetchAll();
$cats = $pdo->query('SELECT * FROM fin_categorias ORDER BY tipo DESC, nome')->fetchAll();
$porCart = [];
foreach ($cats as $g) {
    $porCart[(int) $g['carteira_id']][] = $g;
}
$fixas = $pdo->query('SELECT r.*, k.nome AS carteira, g.nome AS categoria FROM fin_recorrentes r JOIN fin_carteiras k ON k.id = r.carteira_id LEFT JOIN fin_categorias g ON g.id = r.categoria_id ORDER BY k.ordem, r.dia')->fetchAll();
$catsAtivas = array_filter($cats, fn ($g) => (int) $g['ativa'] === 1);
$csrf = destrava_csrf_token();

destrava_admin_head('Configurar financeiro', 'financeiro');
?>
<a class="back-link" href="financeiro.php">← Voltar para o Financeiro</a>
<div class="page-head"><h1>Configurar financeiro</h1></div>
<?php if ($msg): ?><p class="flash flash-<?= h($msg[0]) ?>"><?= h($msg[1]) ?></p><?php endif; ?>

<section class="painel-card">
  <h2>Contas fixas</h2>
  <p class="muted">Contas que se repetem todo mês (DAS, aluguel, internet, assinaturas). Elas entram sozinhas como "a pagar" no dia escolhido.</p>
  <form method="post" class="crm-form fin-form fin-fixa-form" data-fin-form>
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
    <input type="hidden" name="acao" value="fixa_nova">
    <div class="fin-tipo"><label><input type="radio" name="tipo" value="saida" checked> Saída</label><label><input type="radio" name="tipo" value="entrada"> Entrada</label></div>
    <div class="fin-linha fin-linha-4">
      <label>Carteira<select name="carteira_id" data-fin-carteira><?php foreach ($carteiras as $k): if ($k['ativa']): ?><option value="<?= (int) $k['id'] ?>"><?= h($k['nome']) ?></option><?php endif; endforeach; ?></select></label>
      <label>Categoria<select name="categoria_id" data-fin-categoria><?php foreach ($catsAtivas as $g): ?><option value="<?= (int) $g['id'] ?>" data-carteira="<?= (int) $g['carteira_id'] ?>" data-tipo="<?= h($g['tipo']) ?>"><?= h($g['nome']) ?></option><?php endforeach; ?></select></label>
      <label>Descrição<input type="text" name="descricao" maxlength="120" placeholder="Ex.: DAS do MEI" required></label>
      <label>Valor (R$)<input type="text" name="valor" inputmode="decimal" placeholder="0,00" required></label>
      <label>Dia do mês<input type="number" name="dia" min="1" max="31" value="20" required></label>
    </div>
    <button type="submit" class="btn-primary">Adicionar conta fixa</button>
  </form>
  <?php if ($fixas): ?>
    <ul class="lista-simples">
      <?php foreach ($fixas as $r): ?>
        <li>
          <span><strong><?= h($r['descricao']) ?></strong><span class="muted"> · <?= h($r['carteira']) ?><?= $r['categoria'] ? ' · ' . h($r['categoria']) : '' ?> · todo dia <?= (int) $r['dia'] ?></span></span>
          <span class="fin-fixa-acoes">
            <strong class="<?= $r['tipo'] === 'entrada' ? 'valor-entrada' : 'valor-saida' ?>"><?= castelo_reais((float) $r['valor']) ?></strong>
            <form method="post" data-confirm="Remover esta conta fixa?"><input type="hidden" name="csrf_token" value="<?= h($csrf) ?>"><input type="hidden" name="acao" value="fixa_excluir"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button type="submit" class="btn-icon-danger" title="Remover">✕</button></form>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<div class="fin-config-grade">
  <?php foreach ($carteiras as $k): $kid = (int) $k['id']; ?>
    <section class="painel-card <?= $k['ativa'] ? '' : 'fin-inativa' ?>">
      <form method="post" class="fin-renomear">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="acao" value="carteira_renomear">
        <input type="hidden" name="id" value="<?= $kid ?>">
        <input type="text" name="nome" value="<?= h($k['nome']) ?>" maxlength="60" aria-label="Nome da carteira" required>
        <button type="submit" class="btn-sec">Renomear</button>
      </form>
      <?php foreach (['entrada' => 'Entradas', 'saida' => 'Saídas'] as $tp => $rot): ?>
        <h3 class="fin-config-tipo"><?= $rot ?></h3>
        <ul class="fin-cats">
          <?php foreach ($porCart[$kid] ?? [] as $g): if ($g['tipo'] !== $tp) { continue; } ?>
            <li class="<?= $g['ativa'] ? '' : 'fin-inativa' ?>">
              <form method="post" class="fin-renomear">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="acao" value="categoria_renomear">
                <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
                <input type="text" name="nome" value="<?= h($g['nome']) ?>" maxlength="60" aria-label="Nome da categoria" required>
                <button type="submit" class="btn-sec" title="Salvar nome">Salvar</button>
              </form>
              <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="acao" value="categoria_ativa">
                <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
                <button type="submit" class="btn-sec"><?= $g['ativa'] ? 'Ocultar' : 'Mostrar' ?></button>
              </form>
            </li>
          <?php endforeach; ?>
        </ul>
        <form method="post" class="fin-renomear fin-nova-cat">
          <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
          <input type="hidden" name="acao" value="categoria_nova">
          <input type="hidden" name="carteira_id" value="<?= $kid ?>">
          <input type="hidden" name="tipo" value="<?= $tp ?>">
          <input type="text" name="nome" maxlength="60" placeholder="Nova categoria de <?= mb_strtolower($rot) ?>" aria-label="Nova categoria" required>
          <button type="submit" class="btn-sec">+ Adicionar</button>
        </form>
      <?php endforeach; ?>
      <form method="post" class="fin-ativar">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="acao" value="carteira_ativa">
        <input type="hidden" name="id" value="<?= $kid ?>">
        <button type="submit" class="btn-link"><?= $k['ativa'] ? 'Ocultar esta carteira' : 'Mostrar esta carteira de novo' ?></button>
      </form>
    </section>
  <?php endforeach; ?>
  <section class="painel-card">
    <h2>Nova carteira</h2>
    <p class="muted">Ex.: outra empresa do grupo.</p>
    <form method="post" class="fin-renomear">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <input type="hidden" name="acao" value="carteira_nova">
      <input type="text" name="nome" maxlength="60" placeholder="Nome da carteira" required>
      <button type="submit" class="btn-primary">Criar</button>
    </form>
  </section>
</div>
<?php destrava_admin_foot();
