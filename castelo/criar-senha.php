<?php
/** Castelo: criar senha quando ela foi zerada no banco (primeiro acesso ou recuperação). */
require_once __DIR__ . '/inc/auth.php';

$id = (int) ($_SESSION['criar_senha_id'] ?? 0);
$stmt = destrava_db()->prepare("SELECT username FROM admin_users WHERE id = :id AND (password_hash IS NULL OR password_hash = '')");
$stmt->execute(['id' => $id]);
$username = $stmt->fetchColumn();
if (!$id || !$username) {
    unset($_SESSION['criar_senha_id']);
    header('Location: login.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nova = (string) ($_POST['nova'] ?? '');
    $conf = (string) ($_POST['confirma'] ?? '');
    if (!destrava_csrf_check($_POST['csrf_token'] ?? '')) {
        $error = 'Sessão expirada. Tente de novo.';
    } elseif (strlen($nova) < 10) {
        $error = 'A senha precisa ter pelo menos 10 caracteres.';
    } elseif ($nova !== $conf) {
        $error = 'A confirmação não é igual à senha.';
    } else {
        $upd = destrava_db()->prepare("UPDATE admin_users SET password_hash = :h WHERE id = :id AND (password_hash IS NULL OR password_hash = '')");
        $upd->execute(['h' => password_hash($nova, PASSWORD_DEFAULT), 'id' => $id]);
        unset($_SESSION['criar_senha_id']);
        session_regenerate_id(true);
        $_SESSION['admin_user_id'] = $id;
        $_SESSION['admin_username'] = $username;
        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Criar senha | Castelo</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/castelo.css?v=6">
</head>
<body class="castelo-login">
  <div class="login-card">
    <img class="login-logo" src="assets/logo-claro.webp" alt="Destrava Business" width="160" height="44">
    <p class="login-aviso">Crie a senha de <strong><?= h($username) ?></strong>.</p>
    <?php if ($error): ?><p class="login-error"><?= h($error) ?></p><?php endif; ?>
    <form method="post" class="login-form">
      <input type="hidden" name="csrf_token" value="<?= h(destrava_csrf_token()) ?>">
      <label>Nova senha (mínimo 10 caracteres)<input type="password" name="nova" autocomplete="new-password" minlength="10" required autofocus></label>
      <label>Repita a senha<input type="password" name="confirma" autocomplete="new-password" minlength="10" required></label>
      <button type="submit" class="btn-primary">Salvar e entrar</button>
    </form>
  </div>
</body>
</html>
