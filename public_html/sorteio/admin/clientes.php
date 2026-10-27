<?php
/** Só para o login Destrava: criar e gerenciar os acessos dos clientes. */
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/admin_layout.php';
sorteio_session_start();
$pdo = admin_db_or_die();
$user = sorteio_require_destrava();

$errors = [];
$v = ['name' => '', 'username' => ''];
$newPassword = null;

function gen_password(): string {
    $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
    $s = '';
    for ($i = 0; $i < 8; $i++) $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    return $s;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!sorteio_csrf_ok()) { http_response_code(400); exit('Sessão expirada. Volte e tente de novo.'); }
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        $v['name'] = sorteio_clean((string) ($_POST['name'] ?? ''), 150);
        $v['username'] = mb_strtolower(sorteio_clean((string) ($_POST['username'] ?? ''), 60));
        $pw = (string) ($_POST['password'] ?? '');
        if ($pw === '') $pw = gen_password();

        if (mb_strlen($v['name']) < 2) $errors['name'] = 'Informe o nome do cliente.';
        if (!preg_match('/^[a-z0-9._-]{3,60}$/', $v['username'])) $errors['username'] = 'Use de 3 a 60 caracteres: letras minúsculas, números, ponto, hífen.';
        if (strlen($pw) < 8) $errors['password'] = 'A senha precisa de pelo menos 8 caracteres.';
        if (!$errors) {
            $st = $pdo->prepare('SELECT id FROM raffle_users WHERE username = ?');
            $st->execute([$v['username']]);
            if ($st->fetchColumn() !== false) $errors['username'] = 'Esse usuário já existe.';
        }
        if (!$errors) {
            $pdo->prepare("INSERT INTO raffle_users (name, username, password_hash, role) VALUES (?, ?, ?, 'cliente')")
                ->execute([$v['name'], $v['username'], password_hash($pw, PASSWORD_DEFAULT)]);
            $_SESSION['show_password'] = ['username' => $v['username'], 'password' => $pw];
            sorteio_redirect('clientes.php');
        }
    } elseif ($action === 'toggle' || $action === 'reset') {
        $uid = (int) ($_POST['uid'] ?? 0);
        $st = $pdo->prepare("SELECT * FROM raffle_users WHERE id = ? AND role = 'cliente'");
        $st->execute([$uid]);
        $target = $st->fetch();
        if ($target && $action === 'toggle') {
            $pdo->prepare('UPDATE raffle_users SET active = 1 - active WHERE id = ?')->execute([$uid]);
            sorteio_flash('ok', (int) $target['active'] ? 'Acesso desativado.' : 'Acesso reativado.');
        } elseif ($target && $action === 'reset') {
            $pw = gen_password();
            $pdo->prepare('UPDATE raffle_users SET password_hash = ? WHERE id = ?')->execute([password_hash($pw, PASSWORD_DEFAULT), $uid]);
            $_SESSION['show_password'] = ['username' => $target['username'], 'password' => $pw];
        }
        sorteio_redirect('clientes.php');
    }
}

$show = $_SESSION['show_password'] ?? null;
unset($_SESSION['show_password']);
$users = $pdo->query("SELECT u.*, (SELECT COUNT(*) FROM raffles r WHERE r.owner_user_id = u.id) AS n_raffles
                      FROM raffle_users u ORDER BY u.role DESC, u.name")->fetchAll();

admin_header('Clientes', $user);
$err = fn($k) => !empty($errors[$k]) ? '<small class="err">' . h($errors[$k]) . '</small>' : '';
?>
<h1>Clientes</h1>
<p class="muted">Cada cliente entra com o próprio usuário e senha e só vê os sorteios dele. Esses acessos não dão entrada nos leads do diagnóstico.</p>

<?php if ($show): ?>
<section class="card success">
  <h2>Acesso pronto para enviar</h2>
  <p>Mande isso pro cliente (a senha não aparece de novo):</p>
  <pre class="credentials" id="cred">Painel: <?= h(sorteio_base_url()) ?>/sorteio/admin/
Usuário: <?= h($show['username']) ?>

Senha: <?= h($show['password']) ?></pre>
  <button type="button" class="btn btn-outline btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('cred').textContent);this.textContent='Copiado!'">Copiar</button>
</section>
<?php endif; ?>

<section class="card">
  <h2 class="section-h">Novo acesso</h2>
  <form method="post" class="form">
    <?= sorteio_csrf_field() ?><input type="hidden" name="action" value="create">
    <div class="grid-2">
      <label class="field"><span>Nome do cliente *</span><input type="text" name="name" value="<?= h($v['name']) ?>" placeholder="Ex.: Sebrae Itapeva" required><?= $err('name') ?></label>
      <label class="field"><span>Usuário *</span><input type="text" name="username" value="<?= h($v['username']) ?>" placeholder="ex.: sebrae" autocapitalize="none" required><?= $err('username') ?></label>
    </div>
    <label class="field"><span>Senha</span><input type="text" name="password" placeholder="Deixe em branco para gerar uma automática" autocomplete="new-password"><?= $err('password') ?></label>
    <button class="btn btn-primary">Criar acesso</button>
  </form>
</section>

<section class="card">
  <h2 class="section-h">Acessos</h2>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>Nome</th><th>Usuário</th><th>Tipo</th><th>Sorteios</th><th>Último acesso</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
      <tr class="<?= (int) $u['active'] ? '' : 'row-off' ?>">
        <td><?= h($u['name']) ?></td>
        <td><?= h($u['username']) ?></td>
        <td><?= $u['role'] === 'destrava' ? 'Destrava' : 'Cliente' ?><?= (int) $u['active'] ? '' : ' (desativado)' ?></td>
        <td><?= (int) $u['n_raffles'] ?></td>
        <td class="small"><?= h(sorteio_date($u['last_login_at'])) ?: '—' ?></td>
        <td>
          <?php if ($u['role'] === 'cliente'): ?>
          <form method="post" class="inline"><?= sorteio_csrf_field() ?><input type="hidden" name="uid" value="<?= (int) $u['id'] ?>">
            <button name="action" value="reset" class="link-btn" onclick="return confirm('Gerar uma senha nova para <?= h($u['username']) ?>?')">Nova senha</button>
            <button name="action" value="toggle" class="link-btn"><?= (int) $u['active'] ? 'Desativar' : 'Reativar' ?></button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>
<?php admin_footer();
