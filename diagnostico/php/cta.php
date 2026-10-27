<?php
/**
 * Registra o clique no CTA final ("Quero destravar meu marketing"),
 * associando ao lead pelo submission_token.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

destrava_require_method('POST');
destrava_require_same_origin();
if (!destrava_rate_limit('cta', 30, 3600)) {
    destrava_json_response(['ok' => false, 'error' => 'Muitas tentativas.'], 429);
}

$body = destrava_read_json_body();
$submissionToken = destrava_sanitize_str($body['submission_token'] ?? '', 64);

if ($submissionToken === '') {
    destrava_json_response(['ok' => false, 'error' => 'Token ausente.'], 422);
}

$pdo = destrava_db();
$stmt = $pdo->prepare(
    'UPDATE leads
     SET cta_clicado = 1, cta_clicado_em = NOW()
     WHERE submission_token = :token'
);
$stmt->execute(['token' => $submissionToken]);

destrava_json_response(['ok' => true, 'updated' => $stmt->rowCount() > 0]);
