<?php
/** Cabeçalho e rodapé das telas do painel. */

function admin_header(string $title, ?array $user = null): void {
    $flash = sorteio_take_flash();
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= h($title) ?> | Sorteios Destrava</title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="/sorteio/assets/sorteio.css?v=1">
</head>
<body class="adm">
<?php if ($user): ?>
<header class="adm-top">
  <div class="adm-top-inner">
    <a href="index.php" class="adm-brand"><img src="/sorteio/assets/logo-destrava.png" alt="Destrava"><span>Sorteios</span></a>
    <nav class="adm-nav">
      <a href="index.php">Meus sorteios</a>
      <?php if ($user['role'] === 'destrava'): ?><a href="clientes.php">Clientes</a><?php endif; ?>
      <a href="senha.php">Minha senha</a>
      <a href="logout.php">Sair</a>
    </nav>
  </div>
  <p class="adm-who">Conectado como <strong><?= h($user['name']) ?></strong><?= $user['role'] === 'destrava' ? ' · acesso Destrava' : '' ?></p>
</header>
<?php endif; ?>
<main class="adm-main">
<?php foreach ($flash as $f): ?>
  <p class="flash flash-<?= h($f['type']) ?>"><?= h($f['msg']) ?></p>
<?php endforeach;
}

function admin_footer(): void {
    ?>
</main>
</body>
</html>
<?php
}

function admin_db_or_die(?array $user = null): PDO {
    $pdo = sorteio_db();
    if ($pdo) return $pdo;
    http_response_code(503);
    admin_header('Banco indisponível', null);
    echo '<section class="card"><h1>Banco indisponível</h1><p>' . h(SORTEIO_DB_UNAVAILABLE) . '</p>'
       . '<p class="muted">Verifique se as tabelas do sorteio foram criadas no phpMyAdmin e se o quiz de diagnóstico está funcionando (o sorteio usa a mesma configuração de banco).</p></section>';
    admin_footer();
    exit;
}

function status_label(string $status): string {
    return ['aberto' => 'Inscrições abertas', 'encerrado' => 'Inscrições encerradas', 'sorteado' => 'Sorteado'][$status] ?? $status;
}
