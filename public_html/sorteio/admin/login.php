<?php
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/admin_layout.php';
sorteio_session_start();

$pdo = admin_db_or_die();
if (sorteio_current_user()) sorteio_redirect('index.php');

$error = null;
$_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? 0;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if ($_SESSION['login_attempts'] >= 5) usleep(1500000);
    $username = mb_strtolower(trim((string) ($_POST['username'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    $st = $pdo->prepare('SELECT id, password_hash, active FROM raffle_users WHERE username = ?');
    $st->execute([$username]);
    $u = $st->fetch();

    if ($u && (int) $u['active'] && password_verify($password, $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION = ['uid' => (int) $u['id']];
        $pdo->prepare('UPDATE raffle_users SET last_login_at = NOW() WHERE id = ?')->execute([(int) $u['id']]);
        sorteio_redirect('index.php');
    }
    $_SESSION['login_attempts']++;
    $error = 'Usuário ou senha incorretos.';
}

admin_header('Entrar');
?>
<section class="card login-card">
  <img src="/sorteio/assets/logo-destrava.png" alt="Destrava" class="login-logo">
  <h1>Painel de sorteios</h1>
  <?php if ($error): ?><p class="form-error"><?= h($error) ?></p><?php endif; ?>
  <form method="post" class="form">
    <label class="field"><span>Usuário</span><input type="text" name="username" autocomplete="username" autocapitalize="none" required autofocus></label>
    <label class="field"><span>Senha</span><input type="password" name="password" autocomplete="current-password" required></label>
    <button type="submit" class="btn btn-primary btn-block">Entrar</button>
  </form>
</section>
<?php admin_footer();
