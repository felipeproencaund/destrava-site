<?php
/** Estrutura comum das telas do Castelo: menu lateral, topo e rodapé. */
function destrava_admin_head(string $title, string $active = ''): void {
    $menu = [
        ['inicio', 'index.php', 'Início', 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z'],
        ['leads', 'leads.php', 'CRM', 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8m13 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75'],
        ['financeiro', 'modulo.php?m=financeiro', 'Financeiro', 'M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6'],
        ['contratos', 'modulo.php?m=contratos', 'Contratos', 'M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7zM14 2v4a2 2 0 0 0 2 2h4M10 9H8m8 4H8m8 4H8'],
        ['planejamento', 'modulo.php?m=planejamento', 'Planejamento', 'M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2'],
    ];
    $emBreve = ['financeiro', 'contratos', 'planejamento'];
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> | Castelo</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/castelo.css?v=4">
<script src="assets/castelo.js?v=1" defer></script>
</head>
<body class="castelo-body">
<aside class="castelo-lado" id="castelo-lado">
  <div class="castelo-marca">
    <img src="assets/logo-claro.webp" alt="Destrava Business" width="140" height="38">
    <span>Castelo</span>
  </div>
  <nav class="castelo-menu" aria-label="Menu do Castelo">
    <?php foreach ($menu as [$key, $href, $label, $d]): ?>
      <a href="<?= h($href) ?>" class="<?= $active === $key ? 'is-active' : '' ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="<?= h($d) ?>"/></svg>
        <span><?= h($label) ?></span>
        <?php if (in_array($key, $emBreve, true)): ?><em>em breve</em><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="castelo-menu castelo-menu-pe">
    <a href="erros.php" class="<?= $active === 'erros' ? 'is-active' : '' ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0"/></svg><span>Erros do site</span></a>
    <a href="senha.php" class="<?= $active === 'senha' ? 'is-active' : '' ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 11h14v10H5zM8 11V7a4 4 0 0 1 8 0v4"/></svg><span>Minha conta</span></a>
    <a href="logout.php"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4m7 14 5-5-5-5m5 5H9"/></svg><span>Sair</span></a>
  </div>
</aside>
<div class="castelo-area">
  <header class="castelo-topo">
    <button type="button" class="castelo-abre" data-abre-menu aria-controls="castelo-lado" aria-label="Abrir menu"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
    <span class="castelo-ola">Olá, <?= h($_SESSION['admin_username'] ?? '') ?></span>
  </header>
  <main class="admin-main castelo-main">
<?php
}

function destrava_admin_foot(): void {
    echo "  </main>\n</div>\n</body>\n</html>\n";
}

/** Verdadeiro se o usuário logado ainda usa a senha provisória do schema.sql. */
function destrava_admin_uses_default_password(PDO $pdo): bool {
    $stmt = $pdo->prepare('SELECT password_hash FROM admin_users WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['admin_user_id'] ?? 0]);
    $hash = $stmt->fetchColumn();
    return $hash && hash_equals('$2y$12$0Zo6Lt4Fo8ZkWMrdRFvOOeubf.IdlsK8SpqSpuULwfQmT/gtppXAG', (string) $hash);
}
