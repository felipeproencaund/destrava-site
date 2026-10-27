<?php
/**
 * Migração automática do banco para o CRM.
 * Cria o que falta (colunas e tabelas) sem mexer nos dados existentes.
 * Roda uma vez por sessão de login e pode rodar quantas vezes precisar.
 */
function destrava_migrate(PDO $pdo): void {
    $hasColumn = function (string $table, string $column) use ($pdo): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c'
        );
        $stmt->execute(['t' => $table, 'c' => $column]);
        return (int) $stmt->fetchColumn() > 0;
    };

    if (!$hasColumn('leads', 'email')) {
        $pdo->exec('ALTER TABLE leads ADD COLUMN email VARCHAR(150) NULL AFTER whatsapp');
    }
    if (!$hasColumn('leads', 'proximo_contato')) {
        $pdo->exec('ALTER TABLE leads ADD COLUMN proximo_contato DATE NULL');
        $pdo->exec('ALTER TABLE leads ADD INDEX idx_proximo_contato (proximo_contato)');
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS lead_notas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            lead_id INT NOT NULL,
            texto TEXT NOT NULL,
            autor VARCHAR(100) NOT NULL DEFAULT "sistema",
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_lead (lead_id, created_at),
            CONSTRAINT fk_lead_notas_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}
