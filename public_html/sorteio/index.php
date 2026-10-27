<?php
/**
 * Página pública do sorteio: /sorteio/{slug}
 * O participante só consegue CRIAR a própria inscrição.
 */
require_once __DIR__ . '/inc/bootstrap.php';

$slug = isset($_GET['s']) ? strtolower(preg_replace('/[^a-z0-9-]/i', '', (string) $_GET['s'])) : '';
$pdo = sorteio_db();
$raffle = null;
$errors = [];
$old = ['name' => '', 'whatsapp' => '', 'instagram' => '', 'website' => ''];
$justRegistered = null;

if ($pdo && $slug !== '') {
    $st = $pdo->prepare('SELECT * FROM raffles WHERE slug = ?');
    $st->execute([$slug]);
    $raffle = $st->fetch() ?: null;
}

// ---- Inscrição -------------------------------------------------
if ($raffle && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $old['name'] = sorteio_clean((string) ($_POST['name'] ?? ''), 150);
    $old['whatsapp'] = sorteio_clean((string) ($_POST['whatsapp'] ?? ''), 30);
    $old['instagram'] = sorteio_clean((string) ($_POST['instagram'] ?? ''), 100);
    $old['website'] = sorteio_clean((string) ($_POST['website'] ?? ''), 200);
    $consent = !empty($_POST['consent']);
    $honeypot = trim((string) ($_POST['empresa_url'] ?? ''));

    $whats = sorteio_normalize_whatsapp($old['whatsapp']);
    if (mb_strlen($old['name']) < 3 || !preg_match('/\p{L}/u', $old['name'])) $errors['name'] = 'Informe seu nome completo.';
    if ($whats === null) $errors['whatsapp'] = 'Informe um WhatsApp válido, com DDD.';
    if (!$consent) $errors['consent'] = 'É preciso aceitar para participar.';
    $instagram = $old['instagram'] !== '' ? '@' . ltrim(preg_replace('/\s+/', '', $old['instagram']), '@') : null;
    $website = $old['website'] !== '' ? preg_replace('/\s+/', '', $old['website']) : null;

    if ($honeypot !== '') {
        // robô: finge sucesso e não grava nada
        sorteio_redirect(sorteio_public_path($raffle['slug']) . '?ok=0');
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare('SELECT * FROM raffles WHERE id = ? FOR UPDATE');
            $st->execute([(int) $raffle['id']]);
            $locked = $st->fetch();

            if ($locked['status'] !== 'aberto') {
                $errors['form'] = 'As inscrições deste sorteio estão encerradas.';
            } else {
                if ($locked['max_participants'] !== null) {
                    $st = $pdo->prepare("SELECT COUNT(*) FROM raffle_participants WHERE raffle_id = ? AND status = 'participante'");
                    $st->execute([(int) $locked['id']]);
                    if ((int) $st->fetchColumn() >= (int) $locked['max_participants']) {
                        $errors['form'] = 'As vagas deste sorteio já foram preenchidas.';
                    }
                }
                if (!$errors) {
                    $st = $pdo->prepare('SELECT id FROM raffle_participants WHERE raffle_id = ? AND whatsapp = ?');
                    $st->execute([(int) $locked['id'], $whats]);
                    if ($st->fetchColumn() !== false) {
                        $errors['whatsapp'] = 'Este WhatsApp já está inscrito neste sorteio.';
                    }
                }
                if (!$errors) {
                    $st = $pdo->prepare('INSERT INTO raffle_participants
                        (raffle_id, name, whatsapp, instagram, website, consent, ip_hash) VALUES (?, ?, ?, ?, ?, 1, ?)');
                    $st->execute([(int) $locked['id'], $old['name'], $whats, $instagram, $website, sorteio_ip_hash()]);
                    $newId = (int) $pdo->lastInsertId();
                }
            }

            if ($errors) {
                $pdo->rollBack();
            } else {
                $pdo->commit();
                sorteio_redirect(sorteio_public_path($raffle['slug']) . '?ok=' . sorteio_sign_id($newId));
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e instanceof PDOException && (string) $e->getCode() === '23000') {
                $errors['whatsapp'] = 'Este WhatsApp já está inscrito neste sorteio.';
            } else {
                error_log('[sorteio] inscrição: ' . $e->getMessage());
                $errors['form'] = 'Não foi possível registrar sua inscrição agora. Tente novamente em instantes.';
            }
        }
    }
}

if ($raffle && isset($_GET['ok'])) {
    $justRegistered = sorteio_verify_signed_id((string) $_GET['ok']);
}

// Dados para exibição
$count = 0;
$result = null;
if ($raffle) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM raffle_participants WHERE raffle_id = ? AND status = 'participante'");
    $st->execute([(int) $raffle['id']]);
    $count = (int) $st->fetchColumn();
    if ($raffle['status'] === 'sorteado') {
        $result = sorteio_get_result($pdo, (int) $raffle['id']);
    }
    $spotsLeft = $raffle['max_participants'] !== null ? max(0, (int) $raffle['max_participants'] - $count) : null;
}

$pageTitle = $raffle ? $raffle['title'] . ' | Sorteio' : 'Sorteios | Destrava';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= h($pageTitle) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="/sorteio/assets/sorteio.css?v=1">
</head>
<body class="pub">
<main class="pub-wrap">
  <header class="pub-head">
    <img src="/sorteio/assets/logo-destrava.png" alt="Destrava" class="pub-logo">
  </header>

<?php if (!$pdo): ?>
  <section class="card">
    <h1 class="pub-title">Sorteio indisponível</h1>
    <p class="muted">Não foi possível carregar o sorteio agora. Tente novamente em alguns minutos.</p>
  </section>

<?php elseif (!$raffle): ?>
  <section class="card">
    <h1 class="pub-title">Sorteio não encontrado</h1>
    <p class="muted">Confira o link que você recebeu. Se o problema continuar, fale com quem está organizando o sorteio.</p>
  </section>

<?php else: ?>
  <?php if ((int) $raffle['is_test']): ?>
    <p class="test-banner">SORTEIO DE TESTE — não vale prêmio</p>
  <?php endif; ?>

  <section class="card prize-card">
    <?php if ($raffle['organizer']): ?><p class="eyebrow"><?= h($raffle['organizer']) ?></p><?php endif; ?>
    <h1 class="pub-title"><?= h($raffle['title']) ?></h1>
    <div class="prize">
      <span class="prize-label">Prêmio</span>
      <strong class="prize-name"><?= h($raffle['prize_name']) ?></strong>
      <?php if ($raffle['prize_description']): ?>
        <p class="prize-desc"><?= nl2br(h($raffle['prize_description'])) ?></p>
      <?php endif; ?>
    </div>
    <ul class="facts">
      <li><strong><?= (int) $raffle['winners_count'] ?></strong> <?= (int) $raffle['winners_count'] === 1 ? 'ganhador' : 'ganhadores' ?></li>
      <li><strong><?= $count ?></strong> <?= $count === 1 ? 'inscrito' : 'inscritos' ?></li>
      <?php if ($spotsLeft !== null && $raffle['status'] === 'aberto'): ?>
        <li><strong><?= $spotsLeft ?></strong> <?= $spotsLeft === 1 ? 'vaga restante' : 'vagas restantes' ?></li>
      <?php endif; ?>
    </ul>
  </section>

  <?php if ($justRegistered !== null): ?>
    <section class="card success">
      <h2>Inscrição confirmada!</h2>
      <p>Você já está participando.<?php if ($justRegistered > 0): ?> Seu número de inscrição é <strong>#<?= str_pad((string) $justRegistered, 5, '0', STR_PAD_LEFT) ?></strong>.<?php endif; ?></p>
      <p class="muted">Se você for sorteado, o contato será feito pelo WhatsApp informado.</p>
    </section>

  <?php elseif ($raffle['status'] === 'sorteado' && $result): ?>
    <section class="card">
      <h2 class="section-h">Resultado</h2>
      <p class="muted">Sorteio realizado em <?= h(sorteio_date($result['drawn_at'])) ?>, entre <?= (int) $result['eligible_count'] ?> participantes.</p>
      <ol class="pub-winners">
        <?php foreach ($result['winners'] as $w): ?>
          <li><span class="pos"><?= (int) $w['position'] ?>º</span> <?= h(sorteio_public_name($w['name'])) ?> <span class="muted">· WhatsApp <?= h(sorteio_mask_whatsapp($w['whatsapp'])) ?></span></li>
        <?php endforeach; ?>
      </ol>
    </section>

  <?php elseif ($raffle['status'] !== 'aberto' || ($spotsLeft !== null && $spotsLeft === 0)): ?>
    <section class="card">
      <h2 class="section-h">Inscrições encerradas</h2>
      <p class="muted"><?= $spotsLeft === 0 && $raffle['status'] === 'aberto' ? 'Todas as vagas foram preenchidas.' : 'Este sorteio não está recebendo novas inscrições.' ?> O resultado vai aparecer aqui depois do sorteio.</p>
    </section>

  <?php else: ?>
    <section class="card">
      <h2 class="section-h">Participe</h2>
      <?php if (!empty($errors['form'])): ?><p class="form-error"><?= h($errors['form']) ?></p><?php endif; ?>
      <form method="post" class="form" novalidate>
        <label class="field">
          <span>Nome completo *</span>
          <input type="text" name="name" value="<?= h($old['name']) ?>" autocomplete="name" maxlength="150" required>
          <?php if (!empty($errors['name'])): ?><small class="err"><?= h($errors['name']) ?></small><?php endif; ?>
        </label>
        <label class="field">
          <span>WhatsApp *</span>
          <input type="tel" name="whatsapp" value="<?= h($old['whatsapp']) ?>" inputmode="tel" autocomplete="tel" placeholder="(00) 00000-0000" maxlength="30" required>
          <?php if (!empty($errors['whatsapp'])): ?><small class="err"><?= h($errors['whatsapp']) ?></small><?php endif; ?>
        </label>
        <label class="field">
          <span>Instagram</span>
          <input type="text" name="instagram" value="<?= h($old['instagram']) ?>" placeholder="@seuperfil" maxlength="100">
        </label>
        <label class="field">
          <span>Site</span>
          <input type="text" name="website" value="<?= h($old['website']) ?>" placeholder="seusite.com.br" maxlength="200">
        </label>
        <div class="hp" aria-hidden="true">
          <label>Não preencha <input type="text" name="empresa_url" tabindex="-1" autocomplete="off"></label>
        </div>
        <label class="check">
          <input type="checkbox" name="consent" value="1" <?= !empty($_POST['consent']) ? 'checked' : '' ?>>
          <span>Aceito participar do sorteio e ser contatado pelo WhatsApp informado caso seja sorteado.</span>
        </label>
        <?php if (!empty($errors['consent'])): ?><small class="err"><?= h($errors['consent']) ?></small><?php endif; ?>
        <button type="submit" class="btn btn-primary btn-block">Quero participar</button>
        <p class="muted small">Cada WhatsApp pode se inscrever uma vez. Seus dados são usados só para este sorteio.</p>
      </form>
    </section>
  <?php endif; ?>

  <?php if ($raffle['rules_text']): ?>
    <details class="card rules">
      <summary>Regulamento</summary>
      <div class="rules-body"><?= nl2br(h($raffle['rules_text'])) ?></div>
    </details>
  <?php endif; ?>
<?php endif; ?>

  <footer class="pub-foot">Sorteio realizado com a ferramenta da <strong>Destrava</strong>. O resultado é gerado e registrado no servidor.</footer>
</main>
<script>
// Máscara simples de WhatsApp (só visual; a validação real é no servidor)
(function () {
  var el = document.querySelector('input[name="whatsapp"]');
  if (!el) return;
  el.addEventListener('input', function () {
    var d = el.value.replace(/\D/g, '').slice(0, 11);
    if (d.length > 6) el.value = '(' + d.slice(0, 2) + ') ' + d.slice(2, d.length - 4) + '-' + d.slice(-4);
    else if (d.length > 2) el.value = '(' + d.slice(0, 2) + ') ' + d.slice(2);
    else el.value = d;
  });
})();
</script>
</body>
</html>
