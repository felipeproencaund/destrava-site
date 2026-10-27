<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_layout.php';
destrava_admin_require_login();
$pdo = destrava_db();

$errors = [];
$old = ['nome' => '', 'empresa' => '', 'whatsapp' => '', 'email' => '', 'instagram' => '', 'site' => '', 'origem' => '', 'nota' => '', 'proximo_contato' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!destrava_csrf_check($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Sessão expirada. Tente de novo.';
    } else {
        foreach ($old as $k => $_) {
            $old[$k] = destrava_sanitize_str($_POST[$k] ?? '', $k === 'nota' ? 2000 : 200);
        }
        $whats = destrava_sanitize_whatsapp($old['whatsapp']);
        if ($old['nome'] === '') $errors[] = 'Informe o nome.';
        if (strlen($whats) < 10) $errors[] = 'WhatsApp inválido (use DDD + número).';
        if ($old['email'] !== '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mail inválido.';
        $prox = null;
        if ($old['proximo_contato'] !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $old['proximo_contato']);
            if (!$d || $d->format('Y-m-d') !== $old['proximo_contato']) $errors[] = 'Data de próximo contato inválida.';
            else $prox = $old['proximo_contato'];
        }

        if (!$errors) {
            $stmt = $pdo->prepare(
                'INSERT INTO leads (nome, empresa, whatsapp, email, instagram, site, consentimento, respostas,
                    diagnostico_principal, diagnostico_titulo, gargalo_principal, origem, proximo_contato, submission_token)
                 VALUES (:nome, :empresa, :whatsapp, :email, :instagram, :site, 0, :respostas,
                    :dp, :dt, :gp, :origem, :prox, :token)'
            );
            $stmt->execute([
                'nome' => $old['nome'],
                'empresa' => $old['empresa'],
                'whatsapp' => $whats,
                'email' => $old['email'] !== '' ? $old['email'] : null,
                'instagram' => $old['instagram'] !== '' ? $old['instagram'] : null,
                'site' => $old['site'] !== '' ? $old['site'] : null,
                'respostas' => '[]',
                'dp' => '-', 'dt' => '', 'gp' => '',
                'origem' => $old['origem'] !== '' ? $old['origem'] : 'manual',
                'prox' => $prox,
                'token' => bin2hex(random_bytes(16)),
            ]);
            $newId = (int) $pdo->lastInsertId();
            $autor = $_SESSION['admin_username'] ?? 'admin';
            $nota = $pdo->prepare('INSERT INTO lead_notas (lead_id, texto, autor) VALUES (:id, :t, :a)');
            $nota->execute(['id' => $newId, 't' => 'Contato cadastrado manualmente.', 'a' => 'sistema']);
            if ($old['nota'] !== '') {
                $nota->execute(['id' => $newId, 't' => $old['nota'], 'a' => $autor]);
            }
            header('Location: lead.php?id=' . $newId . '&criado=1');
            exit;
        }
    }
}

destrava_admin_head('Novo contato', 'novo');
?>
<h1>Novo contato</h1>
<?php foreach ($errors as $e): ?><p class="flash flash-error"><?= h($e) ?></p><?php endforeach; ?>
<form method="post" class="crm-form">
  <input type="hidden" name="csrf_token" value="<?= h(destrava_csrf_token()) ?>">
  <label>Nome *<input type="text" name="nome" value="<?= h($old['nome']) ?>" maxlength="150" required></label>
  <label>Empresa<input type="text" name="empresa" value="<?= h($old['empresa']) ?>" maxlength="150"></label>
  <label>WhatsApp * (com DDD)<input type="tel" name="whatsapp" value="<?= h($old['whatsapp']) ?>" required></label>
  <label>E-mail<input type="email" name="email" value="<?= h($old['email']) ?>" maxlength="150"></label>
  <label>Instagram<input type="text" name="instagram" value="<?= h($old['instagram']) ?>" maxlength="200"></label>
  <label>Site<input type="text" name="site" value="<?= h($old['site']) ?>" maxlength="200"></label>
  <label>De onde veio (indicação, evento, Instagram…)<input type="text" name="origem" value="<?= h($old['origem']) ?>" maxlength="200" placeholder="manual"></label>
  <label>Próximo contato<input type="date" name="proximo_contato" value="<?= h($old['proximo_contato']) ?>"></label>
  <label>Anotação inicial<textarea name="nota" rows="4" maxlength="2000"><?= h($old['nota']) ?></textarea></label>
  <button type="submit" class="btn-primary">Cadastrar contato</button>
</form>
<?php destrava_admin_foot();
