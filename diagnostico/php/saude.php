<?php
/**
 * Endereço de saúde: usado por monitores externos.
 * Responde 200 {"ok":true} quando o site e o banco funcionam,
 * ou 503 {"ok":false} quando algo falha. Não expõe nenhum detalhe interno.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

try {
    require_once __DIR__ . '/db.php';
    $pdo = destrava_db();
    $pdo->query('SELECT 1')->fetchColumn();
    // confere que a tabela principal existe e responde
    $pdo->query('SELECT 1 FROM leads LIMIT 1')->fetchColumn();
    destrava_json_response(['ok' => true, 'banco' => 'ok', 'hora' => date('c')]);
} catch (Throwable $e) {
    destrava_log('erro', 'Falha no teste de saúde: ' . $e->getMessage());
    destrava_json_response(['ok' => false], 503);
}
