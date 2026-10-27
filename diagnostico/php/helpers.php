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
