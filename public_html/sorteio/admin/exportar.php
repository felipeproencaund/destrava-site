<?php
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/admin_layout.php';
sorteio_session_start();
$pdo = admin_db_or_die();
$user = sorteio_require_login();

$id = (int) ($_GET['id'] ?? 0);
$raffle = sorteio_load_raffle_for_user($pdo, $id, $user);
if (!$raffle) { http_response_code(404); exit('Sorteio não encontrado.'); }

$result = sorteio_get_result($pdo, $id);
$pos = [];
if ($result) foreach ($result['winners'] as $w) $pos[(int) $w['participant_id']] = (int) $w['position'];

$st = $pdo->prepare('SELECT * FROM raffle_participants WHERE raffle_id = ? ORDER BY id');
$st->execute([$id]);

$filename = 'sorteio-' . $raffle['slug'] . '-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // acentos certos no Excel
$cell = fn($v) => (is_string($v) && preg_match('/^[=+\-@]/', $v)) ? "'" . $v : $v; // evita fórmulas no Excel
fputcsv($out, ['Inscrição', 'Nome', 'WhatsApp', 'Instagram', 'Site', 'Data da inscrição', 'Situação', 'Ganhador (posição)'], ';');
while ($p = $st->fetch()) {
    fputcsv($out, array_map($cell, [
        $p['id'], $p['name'], sorteio_format_whatsapp($p['whatsapp']), $p['instagram'], $p['website'],
        sorteio_date($p['created_at']), $p['status'], $pos[(int) $p['id']] ?? '',
    ]), ';');
}
fclose($out);
