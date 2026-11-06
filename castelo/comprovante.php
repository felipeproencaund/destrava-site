<?php
/** Castelo: abre o comprovante de um lançamento (só com login). */
require_once __DIR__ . '/inc/auth.php';
destrava_admin_require_login();
$st = destrava_db()->prepare('SELECT comprovante, comprovante_tipo FROM fin_lancamentos WHERE id = :id');
$st->execute(['id' => (int) ($_GET['id'] ?? 0)]);
$r = $st->fetch();
$nome = (string) ($r['comprovante'] ?? '');
$arq = castelo_pasta_comprovantes() . '/' . $nome;
if (!preg_match('/^[a-f0-9]{32}\.[a-z]+$/', $nome) || !is_file($arq)) {
    http_response_code(404);
    exit('Comprovante não encontrado.');
}
$permitidos = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif', 'application/pdf'];
$mime = in_array($r['comprovante_tipo'], $permitidos, true) ? $r['comprovante_tipo'] : 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($arq));
header('Content-Disposition: inline; filename="comprovante-' . (int) $_GET['id'] . '.' . pathinfo($nome, PATHINFO_EXTENSION) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($arq);
