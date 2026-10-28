<?php
/**
 * Registro de erros do sistema.
 * - Grava todo erro em logs/AAAA-MM.log com data, hora, página e mensagem.
 * - Mostra ao usuário uma mensagem educada em vez de tela branca.
 * - Nunca grava senhas, corpo de formulário nem IP em texto puro.
 * Carregado pelo config.php, então vale para todos os endpoints.
 */

if (!function_exists('destrava_log')) {

    function destrava_log_dir(): string {
        $dir = dirname(__DIR__) . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $ht = $dir . '/.htaccess';
        if (!is_file($ht)) {
            @file_put_contents($ht, "Require all denied\n");
        }
        return $dir;
    }

    function destrava_log_file(): string {
        return destrava_log_dir() . '/' . date('Y-m') . '.log';
    }

    /** Grava uma linha JSON no log. Nunca lança erro. */
    function destrava_log(string $level, string $message, array $context = []): void {
        try {
            $file = destrava_log_file();
            if (is_file($file) && filesize($file) > 5 * 1024 * 1024) {
                @rename($file, $file . '.' . time() . '.old');
            }
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $entry = [
                'quando' => date('Y-m-d H:i:s'),
                'nivel' => $level,
                'metodo' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
                'pagina' => substr(strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: '', 0, 200),
                'veio_de' => substr($_SERVER['HTTP_REFERER'] ?? '', 0, 200),
                'navegador' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 160),
                'visitante' => $ip !== '' ? substr(hash('sha256', $ip . '|destrava-log'), 0, 10) : '',
                'mensagem' => substr($message, 0, 2000),
                'detalhe' => $context,
            ];
            @file_put_contents($file, json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
        } catch (Throwable $e) {
            // log nunca pode derrubar o sistema
        }
    }

    function destrava_is_json_request(): bool {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return strpos($uri, '/php/') !== false
            || stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    }

    /** Resposta educada para o usuário (JSON para endpoints, HTML para telas). */
    function destrava_friendly_error(int $status = 500): void {
        if (headers_sent()) {
            return;
        }
        http_response_code($status);
        if (destrava_is_json_request()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Algo deu errado do nosso lado. Tente novamente em instantes.'], JSON_UNESCAPED_UNICODE);
            return;
        }
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta name="robots" content="noindex"><title>Ops | Destrava</title></head>'
            . '<body style="font-family:system-ui,sans-serif;max-width:480px;margin:15vh auto;padding:0 24px;text-align:center">'
            . '<h1 style="font-size:22px">Algo deu errado do nosso lado</h1>'
            . '<p>Já registramos o problema. Tente novamente em instantes.</p></body></html>';
    }

    set_error_handler(function (int $severity, string $message, string $file = '', int $line = 0): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        destrava_log('aviso', $message, ['arquivo' => basename($file), 'linha' => $line]);
        return true; // não mostra nada na tela
    });

    set_exception_handler(function (Throwable $e): void {
        destrava_log('erro', get_class($e) . ': ' . $e->getMessage(), ['arquivo' => basename($e->getFile()), 'linha' => $e->getLine()]);
        destrava_friendly_error();
    });

    register_shutdown_function(function (): void {
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            destrava_log('fatal', $err['message'], ['arquivo' => basename($err['file']), 'linha' => $err['line']]);
            destrava_friendly_error();
        }
    });
}
