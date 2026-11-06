<?php
require_once __DIR__ . '/inc/auth.php';

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
    require_once __DIR__ . '/../diagnostico/php/helpers.php';
    $blocked = !destrava_rate_limit('login', 10, 900);
    if ($_SESSION['login_attempts'] >= 5) {
        usleep(1500000); // 1.5s de atraso após 5 tentativas
    }

    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($blocked) {
        http_response_code(429);
        $error = 'Muitas tentativas. Aguarde 15 minutos e tente de novo.';
    } elseif ($username !== '' && ($semSenha = destrava_usuario_sem_senha($username))) {
        // Senha zerada pelo banco: a pessoa cria a senha nova antes de entrar.
        session_regenerate_id(true);
        $_SESSION['criar_senha_id'] = $semSenha;
        header('Location: criar-senha.php');
        exit;
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
<title>Entrar | Castelo</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/castelo.css?v=11">
</head>
<body class="castelo-login">
  <div class="login-card">
    <img class="login-logo" src="assets/logo-claro.webp" alt="Destrava Business" width="160" height="44">
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
