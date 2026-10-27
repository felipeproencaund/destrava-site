<?php
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/admin_layout.php';
sorteio_session_start();
$pdo = admin_db_or_die();
$user = sorteio_require_login();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$raffle = null;
$nParticipants = 0;
if ($id) {
    $raffle = sorteio_load_raffle_for_user($pdo, $id, $user);
    if (!$raffle) { http_response_code(404); exit('Sorteio não encontrado.'); }
    if ($raffle['status'] === 'sorteado') {
        sorteio_flash('erro', 'Este sorteio já foi realizado e não pode mais ser editado.');
        sorteio_redirect('sorteio.php?id=' . $id);
    }
    $st = $pdo->prepare('SELECT COUNT(*) FROM raffle_participants WHERE raffle_id = ?');
    $st->execute([$id]);
    $nParticipants = (int) $st->fetchColumn();
}

$clients = [];
if ($user['role'] === 'destrava') {
    $clients = $pdo->query('SELECT id, name, role FROM raffle_users WHERE active = 1 ORDER BY role DESC, name')->fetchAll();
}

$v = [
    'title' => $raffle['title'] ?? '',
    'organizer' => $raffle['organizer'] ?? ($user['role'] === 'cliente' ? $user['name'] : ''),
    'prize_name' => $raffle['prize_name'] ?? '',
    'prize_description' => $raffle['prize_description'] ?? '',
    'rules_text' => $raffle['rules_text'] ?? '',
    'max_participants' => isset($raffle['max_participants']) ? (string) $raffle['max_participants'] : '',
    'winners_count' => (string) ($raffle['winners_count'] ?? 3),
    'slug' => $raffle['slug'] ?? '',
    'is_test' => (int) ($raffle['is_test'] ?? 0),
    'owner_user_id' => (int) ($raffle['owner_user_id'] ?? $user['id']),
];
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!sorteio_csrf_ok()) { http_response_code(400); exit('Sessão expirada. Volte e tente de novo.'); }

    $v['title'] = sorteio_clean((string) ($_POST['title'] ?? ''), 150);
    $v['organizer'] = sorteio_clean((string) ($_POST['organizer'] ?? ''), 150);
    $v['prize_name'] = sorteio_clean((string) ($_POST['prize_name'] ?? ''), 200);
    $v['prize_description'] = trim(strip_tags((string) ($_POST['prize_description'] ?? '')));
    $v['rules_text'] = trim(strip_tags((string) ($_POST['rules_text'] ?? '')));
    $v['max_participants'] = trim((string) ($_POST['max_participants'] ?? ''));
    $v['winners_count'] = trim((string) ($_POST['winners_count'] ?? ''));
    $slugInput = trim((string) ($_POST['slug'] ?? ''));
    if (!$raffle) {
        $v['is_test'] = !empty($_POST['is_test']) ? 1 : 0;
    }
    if ($user['role'] === 'destrava' && isset($_POST['owner_user_id'])) {
        $v['owner_user_id'] = (int) $_POST['owner_user_id'];
    }

    if (mb_strlen($v['title']) < 3) $errors['title'] = 'Dê um nome ao sorteio.';
    if (mb_strlen($v['prize_name']) < 2) $errors['prize_name'] = 'Informe o produto ou serviço sorteado.';

    $winners = filter_var($v['winners_count'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => SORTEIO_MAX_WINNERS]]);
    if ($winners === false) $errors['winners_count'] = 'Número de ganhadores entre 1 e ' . SORTEIO_MAX_WINNERS . '.';

    $max = null;
    if ($v['max_participants'] !== '') {
        $max = filter_var($v['max_participants'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]);
        if ($max === false) {
            $errors['max_participants'] = 'Use um número inteiro, ou deixe em branco para não ter limite.';
        } elseif ($winners !== false && $max < $winners) {
            $errors['max_participants'] = 'O limite de participantes não pode ser menor que o número de ganhadores.';
        } elseif ($max < $nParticipants) {
            $errors['max_participants'] = "Já existem {$nParticipants} inscritos; o limite não pode ser menor que isso.";
        }
    }

    $slug = sorteio_slugify($slugInput !== '' ? $slugInput : $v['title']);
    if ($slug === '' || in_array($slug, ['admin', 'assets', 'inc', 'index'], true)) {
        $errors['slug'] = 'Escolha outro endereço.';
    } else {
        $st = $pdo->prepare('SELECT id FROM raffles WHERE slug = ? AND id <> ?');
        $st->execute([$slug, $id]);
        if ($st->fetchColumn() !== false) $errors['slug'] = 'Esse endereço já está em uso. Escolha outro.';
    }
    if ($raffle && $nParticipants > 0 && $slug !== $raffle['slug']) {
        $errors['slug'] = 'O endereço não pode mudar depois que já existem inscritos (o link já foi divulgado).';
    }
    $v['slug'] = $slug;

    if ($user['role'] === 'destrava') {
        $ids = array_map(fn($c) => (int) $c['id'], $clients);
        if (!in_array($v['owner_user_id'], $ids, true)) $errors['owner_user_id'] = 'Escolha o cliente.';
    } else {
        $v['owner_user_id'] = (int) $user['id'];
    }

    if (!$errors) {
        if ($raffle) {
            $st = $pdo->prepare('UPDATE raffles SET owner_user_id=?, slug=?, title=?, organizer=?, prize_name=?, prize_description=?,
                                 rules_text=?, max_participants=?, winners_count=? WHERE id=? AND status <> \'sorteado\'');
            $st->execute([$v['owner_user_id'], $slug, $v['title'], $v['organizer'] ?: null, $v['prize_name'],
                $v['prize_description'] ?: null, $v['rules_text'] ?: null, $max, $winners, $id]);
            sorteio_flash('ok', 'Sorteio atualizado.');
        } else {
            $st = $pdo->prepare('INSERT INTO raffles (owner_user_id, slug, title, organizer, prize_name, prize_description, rules_text,
                                 max_participants, winners_count, is_test) VALUES (?,?,?,?,?,?,?,?,?,?)');
            $st->execute([$v['owner_user_id'], $slug, $v['title'], $v['organizer'] ?: null, $v['prize_name'],
                $v['prize_description'] ?: null, $v['rules_text'] ?: null, $max, $winners, $v['is_test']]);
            $id = (int) $pdo->lastInsertId();
            sorteio_flash('ok', 'Sorteio criado. Agora é só divulgar o link.');
        }
        sorteio_redirect('sorteio.php?id=' . $id);
    }
}

admin_header($raffle ? 'Editar sorteio' : 'Novo sorteio', $user);
$err = fn($k) => !empty($errors[$k]) ? '<small class="err">' . h($errors[$k]) . '</small>' : '';
?>
<p class="back"><a href="<?= $raffle ? 'sorteio.php?id=' . (int) $raffle['id'] : 'index.php' ?>">← Voltar</a></p>
<h1><?= $raffle ? 'Editar sorteio' : 'Novo sorteio' ?></h1>

<form method="post" class="card form form-wide">
  <?= sorteio_csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">

  <label class="field"><span>Nome do sorteio *</span>
    <input type="text" name="title" value="<?= h($v['title']) ?>" maxlength="150" placeholder="Ex.: Sorteio de aniversário da loja" required>
    <?= $err('title') ?></label>

  <label class="field"><span>Produto ou serviço sorteado *</span>
    <input type="text" name="prize_name" value="<?= h($v['prize_name']) ?>" maxlength="200" placeholder="Ex.: 1 consultoria de marketing" required>
    <?= $err('prize_name') ?></label>

  <label class="field"><span>Descrição do prêmio</span>
    <textarea name="prize_description" rows="3" placeholder="Detalhes, validade, como retirar..."><?= h($v['prize_description']) ?></textarea></label>

  <div class="grid-2">
    <label class="field"><span>Número de ganhadores *</span>
      <input type="number" name="winners_count" value="<?= h($v['winners_count']) ?>" min="1" max="<?= SORTEIO_MAX_WINNERS ?>" required>
      <?= $err('winners_count') ?></label>
    <label class="field"><span>Limite de participantes</span>
      <input type="number" name="max_participants" value="<?= h($v['max_participants']) ?>" min="1" placeholder="Sem limite">
      <small class="hint">Deixe em branco para não ter limite.</small>
      <?= $err('max_participants') ?></label>
  </div>

  <label class="field"><span>Quem está sorteando (aparece na página)</span>
    <input type="text" name="organizer" value="<?= h($v['organizer']) ?>" maxlength="150" placeholder="Ex.: Sebrae + Destrava"></label>

  <label class="field"><span>Endereço da página</span>
    <div class="slug-row"><span class="slug-prefix"><?= h(sorteio_base_url()) ?>/sorteio/</span>
      <input type="text" name="slug" value="<?= h($v['slug']) ?>" maxlength="60" placeholder="gerado a partir do nome"></div>
    <small class="hint">Só letras minúsculas, números e hífen.</small>
    <?= $err('slug') ?></label>

  <label class="field"><span>Regulamento (opcional)</span>
    <textarea name="rules_text" rows="5" placeholder="Regras, prazo para retirada, quem pode participar..."><?= h($v['rules_text']) ?></textarea></label>

  <?php if ($user['role'] === 'destrava'): ?>
    <label class="field"><span>Cliente dono do sorteio</span>
      <select name="owner_user_id">
        <?php foreach ($clients as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === $v['owner_user_id'] ? 'selected' : '' ?>><?= h($c['name']) ?><?= $c['role'] === 'destrava' ? ' (Destrava)' : '' ?></option>
        <?php endforeach; ?>
      </select>
      <?= $err('owner_user_id') ?></label>
  <?php endif; ?>

  <?php if (!$raffle): ?>
    <label class="check"><input type="checkbox" name="is_test" value="1" <?= $v['is_test'] ? 'checked' : '' ?>>
      <span><strong>Sorteio de teste.</strong> Fica marcado como TESTE em todo lugar e pode ser apagado depois. Use pra experimentar.</span></label>
  <?php elseif ((int) $raffle['is_test']): ?>
    <p class="badge badge-test">Este é um sorteio de TESTE</p>
  <?php endif; ?>

  <button type="submit" class="btn btn-primary"><?= $raffle ? 'Salvar alterações' : 'Criar sorteio' ?></button>
</form>
<?php admin_footer();
