<?php
/** Cabeçalho e rodapé comuns das telas do CRM. */
function destrava_admin_head(string $title, string $active = ''): void {
    $nav = [
        'leads' => ['index.php', 'Leads'],
        'novo' => ['novo.php', 'Novo contato'],
        'senha' => ['senha.php', 'Trocar senha'],
    ];
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> | Destrava CRM</title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="admin.css?v=4">
</head>
<body class="admin-body">
<header class="admin-header">
  <div class="admin-brand"><img class="admin-logo" src="../assets/img/logo-destrava.png" alt="Destrava"></div>
  <nav class="admin-nav">
    <?php foreach ($nav as $key => [$href, $label]): ?>
      <a href="<?= h($href) ?>" class="<?= $active === $key ? 'is-active' : '' ?>"><?= h($label) ?></a>
    <?php endforeach; ?>
    <span>Olá, <?= h($_SESSION['admin_username'] ?? '') ?></span>
    <a href="logout.php">Sair</a>
  </nav>
</header>
<main class="admin-main">
<?php
}

function destrava_admin_foot(): void {
    echo "</main>\n</body>\n</html>\n";
}

/** Verdadeiro se o usuário logado ainda usa a senha padrão do schema. */
function destrava_admin_uses_default_password(PDO $pdo): bool {
    $stmt = $pdo->prepare('SELECT password_hash FROM admin_users WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['admin_user_id'] ?? 0]);
    $hash = $stmt->fetchColumn();
    return $hash && password_verify('destrava2026', $hash);
}
