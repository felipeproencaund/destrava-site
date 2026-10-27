<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_layout.php';
destrava_admin_require_login();
$pdo = destrava_db();

$msg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $atual = (string) ($_POST['atual'] ?? '');
    $nova = (string) ($_POST['nova'] ?? '');
    $conf = (string) ($_POST['confirma'] ?? '');
    $stmt = $pdo->prepare('SELECT password_hash FROM admin_users WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['admin_user_id']]);
    $hash = $stmt->fetchColumn();

    if (!destrava_csrf_check($_POST['csrf_token'] ?? '')) {
        $msg = ['error', 'Sessão expirada. Tente de novo.'];
    } elseif (!$hash || !password_verify($atual, $hash)) {
        $msg = ['error', 'A senha atual está incorreta.'];
    } elseif (strlen($nova) < 10) {
        $msg = ['error', 'A nova senha precisa ter pelo menos 10 caracteres.'];
    } elseif ($nova !== $conf) {
        $msg = ['error', 'A confirmação não é igual à nova senha.'];
    } elseif ($nova === $atual) {
        $msg = ['error', 'Escolha uma senha diferente da atual.'];
    } else {
        $upd = $pdo->prepare('UPDATE admin_users SET password_hash = :h WHERE id = :id');
        $upd->execute(['h' => password_hash($nova, PASSWORD_DEFAULT), 'id' => $_SESSION['admin_user_id']]);
        session_regenerate_id(true);
        $msg = ['success', 'Senha alterada.'];
    }
}

destrava_admin_head('Trocar senha', 'senha');
?>
<h1>Trocar senha</h1>
<?php if ($msg): ?><p class="flash flash-<?= h($msg[0]) ?>"><?= h($msg[1]) ?></p><?php endif; ?>
<form method="post" class="crm-form">
  <input type="hidden" name="csrf_token" value="<?= h(destrava_csrf_token()) ?>">
  <label>Senha atual<input type="password" name="atual" autocomplete="current-password" required></label>
  <label>Nova senha (mínimo 10 caracteres)<input type="password" name="nova" autocomplete="new-password" minlength="10" required></label>
  <label>Repita a nova senha<input type="password" name="confirma" autocomplete="new-password" minlength="10" required></label>
  <button type="submit" class="btn-primary">Salvar nova senha</button>
</form>
<?php destrava_admin_foot();
