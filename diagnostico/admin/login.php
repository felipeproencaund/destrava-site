<?php
require_once __DIR__ . '/auth.php';

if (destrava_admin_is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = null;

// Throttle simples contra força bruta (baseado na sessão)
if (empty($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../php/helpers.php';
    $blocked = !destrava_rate_limit('login', 10, 900);
    if ($_SESSION['login_attempts'] >= 5) {
        usleep(1500000); // 1.5s de atraso após 5 tentativas
    }

    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($blocked) {
        http_response_code(429);
        $error = 'Muitas tentativas. Aguarde 15 minutos e tente de novo.';
    } elseif ($username === '' || $password === '') {
        $error = 'Informe usuário e senha.';
    } elseif (destrava_admin_attempt_login($username, $password)) {
        $_SESSION['login_attempts'] = 0;
        header('Location: index.php');
        exit;
    } else {
        $_SESSION['login_attempts']++;
        $error = 'Usuário ou senha incorretos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin | Destrava</title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="admin.css?v=4">
</head>
<body class="admin-login-body">
  <div class="login-card">
    <img class="login-logo" src="../assets/img/logo-destrava.png" alt="Destrava">
    <h1>Acessar leads</h1>
    <?php if ($error): ?>
      <p class="login-error"><?= htmlspecialchars($error, ENT_QUOTES) ?></p>
    <?php endif; ?>
    <form method="post" class="login-form">
      <label>
        Usuário
        <input type="text" name="username" autocomplete="username" required autofocus>
      </label>
      <label>
        Senha
        <input type="password" name="password" autocomplete="current-password" required>
      </label>
      <button type="submit" class="btn-primary">Entrar</button>
    </form>
  </div>
</body>
</html>
