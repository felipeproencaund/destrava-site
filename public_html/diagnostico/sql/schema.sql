-- ============================================================
-- DESTRAVA — Schema do banco de dados
-- Importe este arquivo no phpMyAdmin do hPanel (Hostinger)
-- ou via linha de comando: mysql -u USUARIO -p NOME_DO_BANCO < schema.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Dados do lead
    nome VARCHAR(150) NOT NULL,
    empresa VARCHAR(150) NOT NULL,
    whatsapp VARCHAR(30) NOT NULL,
    instagram VARCHAR(200) NULL,
    site VARCHAR(200) NULL,
    consentimento TINYINT(1) NOT NULL DEFAULT 0,

    -- Respostas do quiz (JSON com todas as respostas brutas)
    respostas JSON NOT NULL,

    -- Diagnóstico
    diagnostico_principal VARCHAR(50) NOT NULL,   -- A, B, C ou D
    diagnostico_titulo VARCHAR(200) NOT NULL,
    gargalo_principal VARCHAR(100) NOT NULL,
    objetivo VARCHAR(200) NULL,
    sinais JSON NULL,               -- lista de sinais personalizados exibidos
    areas_desbloqueio JSON NULL,    -- lista de 2-3 áreas selecionadas

    -- Comercial / funil
    cta_clicado TINYINT(1) NOT NULL DEFAULT 0,
    cta_clicado_em DATETIME NULL,
    status ENUM('Novo', 'Contato realizado', 'Reunião marcada', 'Proposta enviada', 'Cliente', 'Não avançou')
        NOT NULL DEFAULT 'Novo',

    -- Origem / rastreamento
    origem VARCHAR(200) NULL,
    utm_source VARCHAR(150) NULL,
    utm_medium VARCHAR(150) NULL,
    utm_campaign VARCHAR(150) NULL,
    utm_content VARCHAR(150) NULL,
    utm_term VARCHAR(150) NULL,

    -- Proteção contra duplicidade / abuso
    submission_token VARCHAR(64) NOT NULL UNIQUE,
    ip_hash VARCHAR(64) NULL,

    INDEX idx_created_at (created_at),
    INDEX idx_status (status),
    INDEX idx_diagnostico (diagnostico_principal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Usuários do admin
-- ============================================================
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Usuário admin padrão: admin / destrava2026
-- IMPORTANTE: troque a senha assim que possível (tela de admin não inclui
-- troca de senha por padrão — gere um novo hash com o comando abaixo e
-- rode um UPDATE, ou peça para eu adicionar essa tela).
--
-- Para gerar um novo hash em PHP:
--   php -r "echo password_hash('SUA_SENHA_NOVA', PASSWORD_DEFAULT);"
-- ============================================================
INSERT INTO admin_users (username, password_hash)
VALUES ('admin', '$2y$12$0Zo6Lt4Fo8ZkWMrdRFvOOeubf.IdlsK8SpqSpuULwfQmT/gtppXAG')
ON DUPLICATE KEY UPDATE username = username;
-- O hash acima corresponde à senha: destrava2026 (verificado com password_verify)
