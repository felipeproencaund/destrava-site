<?php
/**
 * Recebe a submissão do quiz (dados do lead + diagnóstico) e persiste no banco.
 * Protegido contra submissões duplicadas via submission_token (UNIQUE no banco).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

destrava_require_method('POST');
destrava_require_same_origin();
if (!destrava_rate_limit('submit', 8, 3600)) {
    destrava_json_response(['ok' => false, 'error' => 'Muitas tentativas. Tente novamente mais tarde.'], 429);
}

$body = destrava_read_json_body();

// ---- Validação de campos obrigatórios ----
$errors = [];

$nome = destrava_sanitize_str($body['nome'] ?? '', 150);
$empresa = destrava_sanitize_str($body['empresa'] ?? '', 150);
$whatsapp = destrava_sanitize_whatsapp($body['whatsapp'] ?? '');
$instagram = destrava_sanitize_str($body['instagram'] ?? '', 200);
$site = destrava_sanitize_str($body['site'] ?? '', 200);
$consentimento = !empty($body['consentimento']) ? 1 : 0;
$submissionToken = destrava_sanitize_str($body['submission_token'] ?? '', 64);

$respostas = destrava_limit_list($body['respostas'] ?? null, 40, 20000);
$diagnosticoId = destrava_sanitize_str($body['diagnostico_id'] ?? '', 5);
$diagnosticoTitulo = destrava_sanitize_str($body['diagnostico_titulo'] ?? '', 200);
$gargaloPrincipal = destrava_sanitize_str($body['gargalo_principal'] ?? '', 150);
$objetivo = destrava_sanitize_str($body['objetivo'] ?? '', 200);
$sinais = array_values(destrava_limit_list($body['sinais'] ?? [], 30, 5000) ?? []);
$areas = array_values(destrava_limit_list($body['areas_desbloqueio'] ?? [], 30, 5000) ?? []);

$origem = destrava_sanitize_str($body['origem'] ?? '', 200);
$utmSource = destrava_sanitize_str($body['utm_source'] ?? '', 150);
$utmMedium = destrava_sanitize_str($body['utm_medium'] ?? '', 150);
$utmCampaign = destrava_sanitize_str($body['utm_campaign'] ?? '', 150);
$utmContent = destrava_sanitize_str($body['utm_content'] ?? '', 150);
$utmTerm = destrava_sanitize_str($body['utm_term'] ?? '', 150);

if ($nome === '') $errors[] = 'Nome é obrigatório.';
if ($empresa === '') $errors[] = 'Empresa é obrigatória.';
if (strlen($whatsapp) < 10) $errors[] = 'WhatsApp inválido.';
if (!$consentimento) $errors[] = 'É necessário aceitar o contato.';
if (!$respostas || count($respostas) < 1) $errors[] = 'Respostas do quiz ausentes.';
if (!in_array($diagnosticoId, ['A', 'B', 'C', 'D'], true)) $errors[] = 'Diagnóstico inválido.';
if ($submissionToken === '') $errors[] = 'Token de submissão ausente.';

if (!empty($errors)) {
    destrava_json_response(['ok' => false, 'error' => implode(' ', $errors)], 422);
}

$pdo = destrava_db();

// ---- Proteção contra submissão duplicada ----
// Se o mesmo submission_token já existe, retorna sucesso com o lead já salvo
// (idempotente — evita duplicar em caso de reenvio de rede/duplo clique).
$stmt = $pdo->prepare('SELECT id FROM leads WHERE submission_token = :token LIMIT 1');
$stmt->execute(['token' => $submissionToken]);
$existing = $stmt->fetch();

if ($existing) {
    destrava_json_response(['ok' => true, 'lead_id' => (int) $existing['id'], 'duplicate' => true]);
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO leads (
            nome, empresa, whatsapp, instagram, site, consentimento,
            respostas, diagnostico_principal, diagnostico_titulo, gargalo_principal, objetivo,
            sinais, areas_desbloqueio,
            origem, utm_source, utm_medium, utm_campaign, utm_content, utm_term,
            submission_token, ip_hash
        ) VALUES (
            :nome, :empresa, :whatsapp, :instagram, :site, :consentimento,
            :respostas, :diagnostico_principal, :diagnostico_titulo, :gargalo_principal, :objetivo,
            :sinais, :areas_desbloqueio,
            :origem, :utm_source, :utm_medium, :utm_campaign, :utm_content, :utm_term,
            :submission_token, :ip_hash
        )'
    );

    $stmt->execute([
        'nome' => $nome,
        'empresa' => $empresa,
        'whatsapp' => $whatsapp,
        'instagram' => $instagram !== '' ? $instagram : null,
        'site' => $site !== '' ? $site : null,
        'consentimento' => $consentimento,
        'respostas' => json_encode($respostas, JSON_UNESCAPED_UNICODE),
        'diagnostico_principal' => $diagnosticoId,
        'diagnostico_titulo' => $diagnosticoTitulo,
        'gargalo_principal' => $gargaloPrincipal,
        'objetivo' => $objetivo !== '' ? $objetivo : null,
        'sinais' => json_encode($sinais, JSON_UNESCAPED_UNICODE),
        'areas_desbloqueio' => json_encode($areas, JSON_UNESCAPED_UNICODE),
        'origem' => $origem !== '' ? $origem : 'direto',
        'utm_source' => $utmSource !== '' ? $utmSource : null,
        'utm_medium' => $utmMedium !== '' ? $utmMedium : null,
        'utm_campaign' => $utmCampaign !== '' ? $utmCampaign : null,
        'utm_content' => $utmContent !== '' ? $utmContent : null,
        'utm_term' => $utmTerm !== '' ? $utmTerm : null,
        'submission_token' => $submissionToken,
        'ip_hash' => destrava_client_ip_hash(),
    ]);

    $leadId = (int) $pdo->lastInsertId();
    destrava_json_response(['ok' => true, 'lead_id' => $leadId]);
} catch (PDOException $e) {
    // corrida entre duas requisições simultâneas com o mesmo token
    if ($e->getCode() === '23000') {
        $stmt = $pdo->prepare('SELECT id FROM leads WHERE submission_token = :token LIMIT 1');
        $stmt->execute(['token' => $submissionToken]);
        $row = $stmt->fetch();
        destrava_json_response(['ok' => true, 'lead_id' => $row ? (int) $row['id'] : null, 'duplicate' => true]);
    }

    http_response_code(500);
    destrava_json_response([
        'ok' => false,
        'error' => APP_ENV === 'local' ? $e->getMessage() : 'Não foi possível salvar seu diagnóstico agora.',
    ], 500);
}
