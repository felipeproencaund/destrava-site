<?php
/**
 * ============================================================
 * DESTRAVA — Ferramenta de Sorteios: núcleo compartilhado
 * ============================================================
 * Os dados de acesso ao banco NÃO ficam aqui: são lidos do
 * config.php do quiz de diagnóstico (mesmo banco), pra existir
 * um único lugar com senha de banco no servidor.
 * ============================================================
 */

// ---- Localiza o config do banco -------------------------------
// 1) inc/config.local.php (se um dia o sorteio mudar de servidor)
// 2) config do quiz: public_html/diagnostico/php/config.php
$sorteioConfigCandidates = [
    __DIR__ . '/config.local.php',
    __DIR__ . '/../../diagnostico/php/config.php',
];
$sorteioConfigLoaded = false;
foreach ($sorteioConfigCandidates as $candidate) {
    if (is_file($candidate)) {
        require_once $candidate;
        $sorteioConfigLoaded = true;
        break;
    }
}
if (!defined('APP_ENV')) {
    define('APP_ENV', 'production');
}
if (APP_ENV !== 'local') {
    ini_set('display_errors', '0');
}
date_default_timezone_set('America/Sao_Paulo');

// Nada do sorteio pode ficar em cache (CDN ou navegador).
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

const SORTEIO_DB_UNAVAILABLE = 'Não foi possível realizar o sorteio porque o banco de participantes não está disponível.';
const SORTEIO_MAX_WINNERS = 50;

/**
 * Conexão PDO. Retorna null se o banco não estiver configurado ou
 * acessível (quem chama decide o que mostrar; nunca há fallback).
 */
function sorteio_db(): ?PDO {
    static $pdo = null;
    static $failed = false;
    global $sorteioConfigLoaded;

    if ($pdo !== null) return $pdo;
    if ($failed || !$sorteioConfigLoaded || !defined('DB_HOST') || !defined('DB_NAME')) {
        $failed = true;
        return null;
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST, defined('DB_PORT') ? DB_PORT : '3306', DB_NAME);
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        // Confirma que as tabelas do sorteio existem
        $pdo->query('SELECT 1 FROM raffle_participants LIMIT 1');
    } catch (Throwable $e) {
        error_log('[sorteio] banco indisponível: ' . $e->getMessage());
        $pdo = null;
        $failed = true;
        return null;
    }
    return $pdo;
}

// ---- Utilidades -----------------------------------------------
function h($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function sorteio_clean(string $value, int $max): string {
    $value = trim(strip_tags($value));
    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
    return mb_substr($value, 0, $max);
}

/** Normaliza WhatsApp brasileiro para 55 + DDD + número (só dígitos). */
function sorteio_normalize_whatsapp(string $value): ?string {
    $d = preg_replace('/\D/', '', $value) ?? '';
    if (strlen($d) >= 12 && str_starts_with($d, '55')) {
        $d = substr($d, 2);
    }
    $d = ltrim($d, '0');
    if (strlen($d) < 10 || strlen($d) > 11) {
        return null;
    }
    return '55' . $d;
}

function sorteio_format_whatsapp(string $d): string {
    $n = str_starts_with($d, '55') ? substr($d, 2) : $d;
    if (strlen($n) === 11) return sprintf('(%s) %s-%s', substr($n, 0, 2), substr($n, 2, 5), substr($n, 7));
    if (strlen($n) === 10) return sprintf('(%s) %s-%s', substr($n, 0, 2), substr($n, 2, 4), substr($n, 6));
    return $d;
}

function sorteio_mask_whatsapp(string $d): string {
    return 'final ' . substr($d, -4);
}

/** "Maria Aparecida Souza" -> "Maria S." (exibição pública) */
function sorteio_public_name(string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    if (count($parts) <= 1) return $parts[0] ?? '';
    return $parts[0] . ' ' . mb_strtoupper(mb_substr(end($parts), 0, 1)) . '.';
}

function sorteio_first_name(string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    return $parts[0] ?? '';
}

function sorteio_slugify(string $text): string {
    $text = mb_strtolower(trim($text));
    $text = strtr($text, [
        'á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c','ñ'=>'n',
    ]);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim(substr($text, 0, 60), '-');
}

function sorteio_ip_hash(): ?string {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
    if (!$ip) return null;
    $ip = trim(explode(',', $ip)[0]);
    return hash('sha256', $ip . '|destrava-sorteio');
}

function sorteio_secret(): string {
    return hash('sha256', (defined('DB_PASS') ? DB_PASS : '') . '|' . (defined('DB_NAME') ? DB_NAME : '') . '|destrava-sorteio-v1');
}

/** "123.ab12cd34" — evita que alguém mude o número na URL de confirmação. */
function sorteio_sign_id(int $id): string {
    return $id . '.' . substr(hash_hmac('sha256', (string) $id, sorteio_secret()), 0, 16);
}

function sorteio_verify_signed_id(string $value): ?int {
    if (!preg_match('/^(\d{1,10})\.([a-f0-9]{16})$/', $value, $m)) return null;
    $expected = substr(hash_hmac('sha256', $m[1], sorteio_secret()), 0, 16);
    return hash_equals($expected, $m[2]) ? (int) $m[1] : null;
}

function sorteio_date(?string $datetime, bool $withTime = true): string {
    if (!$datetime) return '';
    $ts = strtotime($datetime);
    return $withTime ? date('d/m/Y \à\s H:i', $ts) : date('d/m/Y', $ts);
}

/** URL pública de um sorteio, relativa à pasta /sorteio/ */
function sorteio_public_path(string $slug): string {
    return '/sorteio/' . rawurlencode($slug);
}

function sorteio_base_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

// ---- Sessão / login -------------------------------------------
function sorteio_session_start(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('destrava_sorteio');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/sorteio/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function sorteio_csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function sorteio_csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . h(sorteio_csrf_token()) . '">';
}

function sorteio_csrf_ok(): bool {
    $t = $_POST['csrf'] ?? '';
    return is_string($t) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}

/** Usuário logado, relido do banco a cada requisição (desativação vale na hora). */
function sorteio_current_user(): ?array {
    static $user = false;
    if ($user !== false) return $user;
    $user = null;
    if (empty($_SESSION['uid'])) return null;
    $pdo = sorteio_db();
    if (!$pdo) return null;
    $st = $pdo->prepare('SELECT id, name, username, role, active FROM raffle_users WHERE id = ?');
    $st->execute([(int) $_SESSION['uid']]);
    $row = $st->fetch();
    if (!$row || !(int) $row['active']) {
        $_SESSION = [];
        return null;
    }
    $user = $row;
    return $user;
}

function sorteio_is_destrava(): bool {
    $u = sorteio_current_user();
    return $u !== null && $u['role'] === 'destrava';
}

function sorteio_require_login(): array {
    $u = sorteio_current_user();
    if (!$u) {
        header('Location: login.php');
        exit;
    }
    return $u;
}

function sorteio_require_destrava(): array {
    $u = sorteio_require_login();
    if ($u['role'] !== 'destrava') {
        http_response_code(403);
        exit('Acesso restrito.');
    }
    return $u;
}

/** Carrega um sorteio garantindo que o usuário logado pode vê-lo. */
function sorteio_load_raffle_for_user(PDO $pdo, int $id, array $user, bool $forUpdate = false): ?array {
    $sql = 'SELECT r.*, u.name AS owner_name FROM raffles r JOIN raffle_users u ON u.id = r.owner_user_id WHERE r.id = ?';
    $params = [$id];
    if ($user['role'] !== 'destrava') {
        $sql .= ' AND r.owner_user_id = ?';
        $params[] = (int) $user['id'];
    }
    if ($forUpdate) $sql .= ' FOR UPDATE';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetch() ?: null;
}

function sorteio_flash(string $type, string $msg): void {
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function sorteio_take_flash(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function sorteio_redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

// ---- O SORTEIO ------------------------------------------------
/**
 * Executa o sorteio 100% no servidor, a partir do banco.
 * - Relê os participantes elegíveis dentro de uma transação com trava.
 * - Sorteia com random_int (gerador criptograficamente seguro do PHP).
 * - Revalida os escolhidos antes de gravar.
 * - Grava draw + ganhadores; a UNIQUE(raffle_id) impede sortear de novo.
 * Retorna ['ok'=>true,'draw_id'=>..] ou ['ok'=>false,'error'=>..].
 */
function sorteio_execute_draw(int $raffleId, array $user): array {
    $pdo = sorteio_db();
    if (!$pdo) {
        return ['ok' => false, 'error' => SORTEIO_DB_UNAVAILABLE];
    }

    try {
        $pdo->beginTransaction();

        $raffle = sorteio_load_raffle_for_user($pdo, $raffleId, $user, true);
        if (!$raffle) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Sorteio não encontrado.'];
        }

        $st = $pdo->prepare('SELECT id FROM raffle_draws WHERE raffle_id = ?');
        $st->execute([$raffleId]);
        if ($st->fetchColumn() !== false || $raffle['status'] === 'sorteado') {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Este sorteio já foi realizado. O resultado oficial está registrado e não pode ser refeito.'];
        }

        $n = (int) $raffle['winners_count'];
        if ($n < 1 || $n > SORTEIO_MAX_WINNERS) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Número de ganhadores inválido.'];
        }

        // Consulta o banco NOVAMENTE, no momento do sorteio, com trava.
        $st = $pdo->prepare("SELECT id FROM raffle_participants
                             WHERE raffle_id = ? AND status = 'participante' AND eligible = 1
                             ORDER BY id FOR UPDATE");
        $st->execute([$raffleId]);
        $eligibleIds = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        $eligibleCount = count($eligibleIds);

        if ($eligibleCount < $n) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => "Participantes elegíveis insuficientes: há {$eligibleCount} e o sorteio precisa de {$n} ganhadores."];
        }

        // Fisher–Yates parcial com random_int (CSPRNG)
        $pool = $eligibleIds;
        $winners = [];
        for ($i = 0; $i < $n; $i++) {
            $j = random_int($i, $eligibleCount - 1);
            [$pool[$i], $pool[$j]] = [$pool[$j], $pool[$i]];
            $winners[] = $pool[$i];
        }

        // Revalidação antes de confirmar
        if (count(array_unique($winners)) !== $n || array_diff($winners, $eligibleIds)) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Falha na validação do resultado. Nada foi gravado; tente novamente.'];
        }
        $in = implode(',', array_fill(0, $n, '?'));
        $st = $pdo->prepare("SELECT COUNT(*) FROM raffle_participants
                             WHERE raffle_id = ? AND status = 'participante' AND eligible = 1 AND id IN ($in)");
        $st->execute(array_merge([$raffleId], $winners));
        if ((int) $st->fetchColumn() !== $n) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Falha na validação do resultado. Nada foi gravado; tente novamente.'];
        }

        $st = $pdo->prepare('INSERT INTO raffle_draws
            (raffle_id, drawn_at, eligible_count, considered_ids, winners_count, executed_by_user_id, executed_by_username, rng_method)
            VALUES (?, NOW(), ?, ?, ?, ?, ?, ?)');
        $st->execute([
            $raffleId, $eligibleCount, json_encode($eligibleIds), $n,
            (int) $user['id'], $user['username'], 'PHP random_int (CSPRNG) + Fisher-Yates',
        ]);
        $drawId = (int) $pdo->lastInsertId();

        $st = $pdo->prepare('INSERT INTO raffle_draw_winners (draw_id, participant_id, position) VALUES (?, ?, ?)');
        foreach ($winners as $pos => $pid) {
            $st->execute([$drawId, $pid, $pos + 1]);
        }

        $pdo->prepare("UPDATE raffles SET status = 'sorteado' WHERE id = ?")->execute([$raffleId]);

        $pdo->commit();
        return ['ok' => true, 'draw_id' => $drawId];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[sorteio] erro no sorteio: ' . $e->getMessage());
        if ($e instanceof PDOException && (string) $e->getCode() === '23000') {
            return ['ok' => false, 'error' => 'Este sorteio já foi realizado. O resultado oficial está registrado e não pode ser refeito.'];
        }
        return ['ok' => false, 'error' => SORTEIO_DB_UNAVAILABLE];
    }
}

/** Resultado oficial gravado (ou null). */
function sorteio_get_result(PDO $pdo, int $raffleId): ?array {
    $st = $pdo->prepare('SELECT * FROM raffle_draws WHERE raffle_id = ?');
    $st->execute([$raffleId]);
    $draw = $st->fetch();
    if (!$draw) return null;
    $st = $pdo->prepare('SELECT w.*, p.name, p.whatsapp, p.instagram, p.website
                         FROM raffle_draw_winners w JOIN raffle_participants p ON p.id = w.participant_id
                         WHERE w.draw_id = ? ORDER BY w.position');
    $st->execute([(int) $draw['id']]);
    $draw['winners'] = $st->fetchAll();
    $draw['considered'] = json_decode($draw['considered_ids'], true) ?: [];
    return $draw;
}
