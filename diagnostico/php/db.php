<?php
/**
 * Conexão com o banco de dados via PDO.
 * Usa as constantes definidas em config.php.
 */
require_once __DIR__ . '/config.php';

function destrava_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        destrava_log('erro', 'Falha ao conectar no banco: ' . $e->getMessage());
        if (APP_ENV === 'local') {
            http_response_code(500);
            die('Erro de conexão com o banco: ' . $e->getMessage());
        }
        destrava_friendly_error(503);
        exit;
    }

    return $pdo;
}
