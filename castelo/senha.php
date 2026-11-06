<?php
/** Castelo: minha conta (trocar nome de usuário e senha). */
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/layout.php';
destrava_admin_require_login();
$pdo = destrava_db();

$msg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $atual = (string) ($_POST['atual'] ?? '');
    $stmt = $pdo->prepare('SELECT password_hash FROM admin_users WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['admin_user_id']]);
    $hash = $stmt->fetchColumn();

    if (!destrava_csrf_check($_POST['csrf_token'] ?? '')) {
        $msg = ['error', 'Sessão expirada. Tente de novo.'];
    } elseif (!$hash || !password_verify($atual, $hash)) {
        $msg = ['error', 'A senha atual está incorreta.'];
    } elseif ($acao === 'usuario') {
        $novo = strtolower(trim((string) ($_POST['usuario'] ?? '')));
        if (!preg_match('/^[a-z0-9._-]{3,40}$/', $novo)) {
            $msg = ['error', 'Use de 3 a 40 caracteres: letras minúsculas, números, ponto, hífen ou sublinhado. Sem espaço e sem acento.'];
        } else {
            $existe = $pdo->prepare('SELECT COUNT(*) FROM admin_users WHERE username = :u AND id <> :id');
            $existe->execute(['u' => $novo, 'id' => $_SESSION['admin_user_id']]);
            if ((int) $existe->fetchColumn() > 0) {
                $msg = ['error', 'Esse nome de usuário já está em uso.'];
            } else {
                $pdo->prepare('UPDATE admin_users SET username = :u WHERE id = :id')->execute(['u' => $novo, 'id' => $_SESSION['admin_user_id']]);
                $_SESSION['admin_username'] = $novo;
                session_regenerate_id(true);
                $msg = ['success', 'Usuário alterado. No próximo login, entre com "' . $novo . '".'];
            }
        }
    } elseif ($acao === 'excluir') {
        $alvo = (int) ($_POST['id'] ?? 0);
        if ($alvo > 0 && $alvo !== (int) $_SESSION['admin_user_id']) {
            $pdo->prepare('DELETE FROM admin_users WHERE id = :id')->execute(['id' => $alvo]);
            $msg = ['success', 'Usuário excluído.'];
        } else {
            $msg = ['error', 'Você não pode excluir o seu próprio usuário.'];
        }
    } elseif ($acao === 'senha') {
        $nova = (string) ($_POST['nova'] ?? '');
        $conf = (string) ($_POST['confirma'] ?? '');
        if (strlen($nova) < 10) {
            $msg = ['error', 'A nova senha precisa ter pelo menos 10 caracteres.'];
        } elseif ($nova !== $conf) {
            $msg = ['error', 'A confirmação não é igual à nova senha.'];
        } elseif ($nova === $atual) {
            $msg = ['error', 'Escolha uma senha diferente da atual.'];
        } else {
            $pdo->prepare('UPDATE admin_users SET password_hash = :h WHERE id = :id')
                ->execute(['h' => password_hash($nova, PASSWORD_DEFAULT), 'id' => $_SESSION['admin_user_id']]);
            session_regenerate_id(true);
            $msg = ['success', 'Senha alterada.'];
        }
    }
}
$csrf = destrava_csrf_token();
$outros = $pdo->prepare('SELECT id, username FROM admin_users WHERE id <> :id ORDER BY username');
$outros->execute(['id' => $_SESSION['admin_user_id']]);
$outros = $outros->fetchAll();
destrava_admin_head('Minha conta', 'senha');
?>
<div class="page-head"><h1>Minha conta</h1></div>
<?php if ($msg): ?><p class="flash flash-<?= h($msg[0]) ?>"><?= h($msg[1]) ?></p><?php endif; ?>
<div class="conta-grade">
  <form method="post" class="crm-form painel-card">
    <h2>Nome de usuário</h2>
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
    <input type="hidden" name="acao" value="usuario">
    <label>Novo usuário<input type="text" name="usuario" value="<?= h($_SESSION['admin_username'] ?? '') ?>" autocomplete="username" pattern="[a-z0-9._\-]{3,40}" maxlength="40" required></label>
    <label>Senha atual (para confirmar)<input type="password" name="atual" autocomplete="current-password" required></label>
    <button type="submit" class="btn-primary">Salvar usuário</button>
  </form>
  <form method="post" class="crm-form painel-card">
    <h2>Senha</h2>
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
    <input type="hidden" name="acao" value="senha">
    <label>Senha atual<input type="password" name="atual" autocomplete="current-password" required></label>
    <label>Nova senha (mínimo 10 caracteres)<input type="password" name="nova" autocomplete="new-password" minlength="10" required></label>
    <label>Repita a nova senha<input type="password" name="confirma" autocomplete="new-password" minlength="10" required></label>
    <button type="submit" class="btn-primary">Salvar nova senha</button>
  </form>
</div>

<section class="painel-card conta-usuarios">
  <h2>Outros usuários com acesso</h2>
  <?php if (!$outros): ?>
    <p class="empty-state">Só você tem acesso ao Castelo.</p>
  <?php else: ?>
    <ul class="lista-simples">
      <?php foreach ($outros as $u): ?>
        <li>
          <strong><?= h($u['username']) ?></strong>
          <form method="post" class="form-excluir" data-confirm="Excluir o usuário <?= h($u['username']) ?>? Ele perde o acesso ao Castelo.">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <input type="hidden" name="acao" value="excluir">
            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
            <input type="password" name="atual" placeholder="Sua senha" autocomplete="current-password" required>
            <button type="submit" class="btn-danger">Excluir</button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php destrava_admin_foot();
