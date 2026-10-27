<?php
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/admin_layout.php';
sorteio_session_start();
$pdo = admin_db_or_die();
$user = sorteio_require_login();

$errors = [];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!sorteio_csrf_ok()) { http_response_code(400); exit('Sessão expirada. Volte e tente de novo.'); }
    $current = (string) ($_POST['current'] ?? '');
    $new = (string) ($_POST['new'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');

    $st = $pdo->prepare('SELECT password_hash FROM raffle_users WHERE id = ?');
    $st->execute([(int) $user['id']]);
    if (!password_verify($current, (string) $st->fetchColumn())) $errors[] = 'A senha atual está errada.';
    if (strlen($new) < 8) $errors[] = 'A nova senha precisa de pelo menos 8 caracteres.';
    if ($new !== $confirm) $errors[] = 'A confirmação não bate com a nova senha.';

    if (!$errors) {
        $pdo->prepare('UPDATE raffle_users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), (int) $user['id']]);
        session_regenerate_id(true);
        sorteio_flash('ok', 'Senha alterada.');
        sorteio_redirect('index.php');
    }
}

admin_header('Minha senha', $user);
?>
<p class="back"><a href="index.php">← Sorteios</a></p>
<h1>Minha senha</h1>
<form method="post" class="card form">
  <?= sorteio_csrf_field() ?>
  <?php foreach ($errors as $e): ?><p class="form-error"><?= h($e) ?></p><?php endforeach; ?>
  <label class="field"><span>Senha atual</span><input type="password" name="current" autocomplete="current-password" required></label>
  <label class="field"><span>Nova senha (mínimo 8 caracteres)</span><input type="password" name="new" autocomplete="new-password" minlength="8" required></label>
  <label class="field"><span>Repita a nova senha</span><input type="password" name="confirm" autocomplete="new-password" minlength="8" required></label>
  <button class="btn btn-primary">Salvar nova senha</button>
</form>
<?php admin_footer();
