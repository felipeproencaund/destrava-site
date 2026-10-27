<?php
/**
 * Autenticação simples de sessão para o admin de leads.
 */
require_once __DIR__ . '/../php/config.php';
require_once __DIR__ . '/../php/db.php';
require_once __DIR__ . '/../php/helpers.php';
require_once __DIR__ . '/migrate.php';

session_name(ADMIN_SESSION_NAME);
session_start();

function destrava_admin_is_logged_in(): bool {
    return !empty($_SESSION['admin_user_id']);
}

function destrava_admin_require_login(): void {
    if (!destrava_admin_is_logged_in()) {
        header('Location: login.php');
        exit;
    }
    // prepara o banco para o CRM (uma vez por sessão)
    if (empty($_SESSION['crm_migrated_v1'])) {
        destrava_migrate(destrava_db());
        $_SESSION['crm_migrated_v1'] = 1;
    }
}

function h($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

const DESTRAVA_STATUSES = ['Novo', 'Contato realizado', 'Reunião marcada', 'Proposta enviada', 'Cliente', 'Não avançou'];

/** Link do WhatsApp a partir de qualquer formato de número brasileiro. */
function destrava_wa_link(string $number, string $text = ''): string {
    $digits = preg_replace('/\D/', '', $number);
    if (strlen($digits) === 10 || strlen($digits) === 11) {
        $digits = '55' . $digits;
    }
    return 'https://wa.me/' . $digits . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

function destrava_admin_attempt_login(string $username, string $password): bool {
    $pdo = destrava_db();
    $stmt = $pdo->prepare('SELECT id, password_hash FROM admin_users WHERE username = :u LIMIT 1');
    $stmt->execute(['u' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_user_id'] = (int) $user['id'];
        $_SESSION['admin_username'] = $username;
        return true;
    }
    return false;
}

function destrava_admin_logout(): void {
    $_SESSION = [];
    session_destroy();
}

function destrava_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function destrava_csrf_check(string $token): bool {
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
