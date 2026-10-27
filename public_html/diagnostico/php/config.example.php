<?php
/**
 * ============================================================
 * CONFIGURAÇÃO CENTRAL — DESTRAVA
 * ============================================================
 * Este é o ÚNICO lugar onde você deve alterar:
 *   - Dados de acesso ao banco de dados MySQL (Hostinger hPanel)
 *   - Número de WhatsApp comercial
 *   - Chave de sessão do admin
 *
 * Não coloque esses dados em nenhum outro arquivo.
 * ============================================================
 */

// ---- BANCO DE DADOS (preencha com os dados do hPanel > Bancos de dados MySQL) ----
define('DB_HOST', getenv('DESTRAVA_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DESTRAVA_DB_NAME') ?: 'NOME_DO_BANCO');
define('DB_USER', getenv('DESTRAVA_DB_USER') ?: 'USUARIO_DO_BANCO');
define('DB_PASS', getenv('DESTRAVA_DB_PASS') ?: 'SENHA_DO_BANCO');
define('DB_PORT', getenv('DESTRAVA_DB_PORT') ?: '3306');

// ---- WHATSAPP COMERCIAL (formato: código do país + DDD + número, só dígitos) ----
define('WHATSAPP_NUMBER', '5543999119533');

// ---- ADMIN ----
// Troque este usuário/senha antes de publicar em produção.
// A senha é armazenada como hash (veja sql/schema.sql para gerar o hash inicial).
define('ADMIN_SESSION_NAME', 'destrava_admin_session');

// ---- AMBIENTE ----
// 'local' para testes, 'production' quando estiver no ar na Hostinger.
define('APP_ENV', getenv('DESTRAVA_ENV') ?: 'production');

// Exibir erros apenas em ambiente local (nunca em produção)
if (APP_ENV === 'local') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// Timezone
date_default_timezone_set('America/Sao_Paulo');
