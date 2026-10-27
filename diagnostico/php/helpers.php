<?php
/**
 * Funções auxiliares compartilhadas pelos endpoints PHP.
 */

function destrava_json_response(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function destrava_read_json_body(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return [];
    }
    return $data;
}

function destrava_sanitize_str($value, int $maxLength = 255): string {
    if (!is_string($value)) {
        $value = is_scalar($value) ? (string) $value : '';
    }
    $value = trim($value);
    $value = strip_tags($value);
    // remove caracteres de controle
    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
    if (function_exists('mb_substr')) {
        $value = mb_substr($value, 0, $maxLength);
    } else {
        $value = substr($value, 0, $maxLength);
    }
    return $value;
}

function destrava_sanitize_whatsapp(string $value): string {
    // mantém apenas dígitos, +, espaço e parênteses/traço para exibição amigável
    $digits = preg_replace('/\D/', '', $value);
    return substr($digits, 0, 20);
}

function destrava_client_ip_hash(): ?string {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
    if (!$ip) {
        return null;
    }
    // pega apenas o primeiro IP da cadeia (caso venha de proxy)
    $ip = trim(explode(',', $ip)[0]);
    return hash('sha256', $ip . '|destrava-salt');
}

function destrava_require_method(string $method): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        destrava_json_response(['ok' => false, 'error' => 'Método não permitido.'], 405);
    }
}


/**
 * Limite de requisicoes por IP (arquivos na pasta temporaria do servidor).
 * Retorna false quando o limite da janela foi excedido.
 */
function destrava_rate_limit(string $bucket, int $max, int $windowSeconds): bool {
    $dir = rtrim(sys_get_temp_dir(), '/') . '/destrava-rl';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    $ipHash = destrava_client_ip_hash() ?? 'sem-ip';
    $file = $dir . '/' . preg_replace('/[^a-z0-9_-]/i', '', $bucket) . '-' . substr($ipHash, 0, 32) . '.json';
    $now = time();
    $hits = [];
    $fh = @fopen($file, 'c+');
    if (!$fh) {
        return true; // se nao conseguir gravar, nao bloqueia o usuario
    }
    flock($fh, LOCK_EX);
    $raw = stream_get_contents($fh);
    $data = $raw ? json_decode($raw, true) : [];
    if (is_array($data)) {
        foreach ($data as $t) {
            if (is_int($t) && $t > $now - $windowSeconds) {
                $hits[] = $t;
            }
        }
    }
    $allowed = count($hits) < $max;
    if ($allowed) {
        $hits[] = $now;
    }
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($hits));
    flock($fh, LOCK_UN);
    fclose($fh);
    return $allowed;
}

/** Aceita apenas requisicoes vindas do proprio site (quando o navegador informa a origem). */
function destrava_require_same_origin(): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '') {
        return;
    }
    $originHost = parse_url($origin, PHP_URL_HOST);
    $host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
    if (!$originHost || strcasecmp($originHost, $host) !== 0) {
        destrava_json_response(['ok' => false, 'error' => 'Origem não permitida.'], 403);
    }
}

/** Garante que uma lista vinda do navegador tem tamanho razoavel. */
function destrava_limit_list($value, int $maxItems, int $maxJsonBytes): ?array {
    if (!is_array($value) || count($value) > $maxItems) {
        return null;
    }
    $json = json_encode($value, JSON_UNESCAPED_UNICODE);
    if ($json === false || strlen($json) > $maxJsonBytes) {
        return null;
    }
    return $value;
}
