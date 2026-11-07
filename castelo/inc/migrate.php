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

/** Tabelas do Financeiro do Castelo. Cria e preenche o básico só na primeira vez. */
function castelo_migrate_financeiro(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS fin_carteiras (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(60) NOT NULL,
        ordem INT NOT NULL DEFAULT 0,
        ativa TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS fin_categorias (
        id INT AUTO_INCREMENT PRIMARY KEY,
        carteira_id INT NOT NULL,
        tipo ENUM('entrada','saida') NOT NULL,
        nome VARCHAR(60) NOT NULL,
        ativa TINYINT(1) NOT NULL DEFAULT 1,
        INDEX idx_cart (carteira_id, tipo),
        CONSTRAINT fk_fin_cat_cart FOREIGN KEY (carteira_id) REFERENCES fin_carteiras(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS fin_recorrentes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        carteira_id INT NOT NULL,
        categoria_id INT NULL,
        tipo ENUM('entrada','saida') NOT NULL,
        descricao VARCHAR(120) NOT NULL,
        valor DECIMAL(12,2) NOT NULL,
        dia TINYINT NOT NULL DEFAULT 10,
        ativo TINYINT(1) NOT NULL DEFAULT 1,
        ultimo_mes CHAR(7) NULL,
        CONSTRAINT fk_fin_rec_cart FOREIGN KEY (carteira_id) REFERENCES fin_carteiras(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS fin_lancamentos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        carteira_id INT NOT NULL,
        categoria_id INT NULL,
        tipo ENUM('entrada','saida') NOT NULL,
        descricao VARCHAR(120) NOT NULL,
        valor DECIMAL(12,2) NOT NULL,
        data DATE NOT NULL,
        pago TINYINT(1) NOT NULL DEFAULT 1,
        recorrente_id INT NULL,
        criado_por VARCHAR(40) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_data (data),
        INDEX idx_cart_data (carteira_id, data),
        CONSTRAINT fk_fin_lanc_cart FOREIGN KEY (carteira_id) REFERENCES fin_carteiras(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // comprovante (foto ou PDF) de cada lançamento
    $tem = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fin_lancamentos' AND COLUMN_NAME = 'comprovante'")->fetchColumn();
    if (!(int) $tem) {
        $pdo->exec('ALTER TABLE fin_lancamentos ADD COLUMN comprovante VARCHAR(80) NULL, ADD COLUMN comprovante_tipo VARCHAR(40) NULL');
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM fin_carteiras')->fetchColumn() === 0) {
        // Lista inicial: tudo editável pelo próprio Castelo (Financeiro > Configurar).
        $base = [
            'Casa' => [
                'entrada' => ['Retirada dos MEIs', 'Outras entradas'],
                'saida' => ['Mercado', 'Contas da casa', 'Filhos', 'Saúde', 'Transporte', 'Lazer', 'Parcelas', 'Outros'],
            ],
            'MEI Felipe (Destrava)' => [
                'entrada' => ['Clientes', 'Outras entradas'],
                'saida' => ['DAS', 'Ferramentas e assinaturas', 'Anúncios', 'Retirada para a Casa', 'Outros'],
            ],
            'MEI Nadine' => [
                'entrada' => ['Vendas', 'Outras entradas'],
                'saida' => ['DAS', 'Ferramentas e assinaturas', 'Retirada para a Casa', 'Outros'],
            ],
            'Pede Coxinha' => [
                'entrada' => ['Vendas no balcão', 'Encomendas', 'Delivery'],
                'saida' => ['Insumos', 'Funcionários', 'Aluguel e contas', 'Taxas de app e maquininha', 'Impostos', 'Outros'],
            ],
        ];
        $insC = $pdo->prepare('INSERT INTO fin_carteiras (nome, ordem) VALUES (:n, :o)');
        $insK = $pdo->prepare('INSERT INTO fin_categorias (carteira_id, tipo, nome) VALUES (:c, :t, :n)');
        $ordem = 0;
        foreach ($base as $nome => $tipos) {
            $insC->execute(['n' => $nome, 'o' => ++$ordem]);
            $cid = (int) $pdo->lastInsertId();
            foreach ($tipos as $tipo => $cats) {
                foreach ($cats as $cat) {
                    $insK->execute(['c' => $cid, 't' => $tipo, 'n' => $cat]);
                }
            }
        }
    }
    // metas e lucro: natureza de cada categoria e dados de funcionamento de cada carteira
    $col = function (string $t, string $c) use ($pdo): bool {
        $q = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c');
        $q->execute(['t' => $t, 'c' => $c]);
        return (int) $q->fetchColumn() > 0;
    };
    if (!$col('fin_categorias', 'natureza')) {
        $pdo->exec("ALTER TABLE fin_categorias ADD COLUMN natureza ENUM('fixo','variavel','retirada','outro') NOT NULL DEFAULT 'outro'");
        $pdo->exec("UPDATE fin_categorias SET natureza = 'variavel' WHERE tipo = 'saida' AND nome IN ('Insumos', 'Taxas de app e maquininha', 'Impostos')");
        $pdo->exec("UPDATE fin_categorias SET natureza = 'fixo' WHERE tipo = 'saida' AND nome IN ('Funcionários', 'Aluguel e contas', 'DAS', 'Ferramentas e assinaturas', 'Anúncios')");
        $pdo->exec("UPDATE fin_categorias SET natureza = 'retirada' WHERE tipo = 'saida' AND nome LIKE 'Retirada%'");
    }
    if (!$col('fin_carteiras', 'empresa')) {
        $pdo->exec("ALTER TABLE fin_carteiras ADD COLUMN empresa TINYINT(1) NOT NULL DEFAULT 1, ADD COLUMN dias CHAR(7) NOT NULL DEFAULT '1111110', ADD COLUMN cmv_estimado DECIMAL(5,2) NOT NULL DEFAULT 35.00");
        $pdo->exec("UPDATE fin_carteiras SET empresa = 0 WHERE nome = 'Casa'");
        $pdo->exec("UPDATE fin_carteiras SET dias = '1111100', cmv_estimado = 10 WHERE nome LIKE 'MEI%'");
    }

}

/** Tabelas das Análises (dados que vêm dos sistemas das empresas; hoje, Pede Coxinha via Consumer). */
function castelo_migrate_analises(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS pc_clientes (
        id INT PRIMARY KEY,
        nome VARCHAR(120) NOT NULL DEFAULT '',
        fone VARCHAR(20) NOT NULL DEFAULT '',
        bairro VARCHAR(80) NOT NULL DEFAULT ''
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS pc_pedidos (
        id INT PRIMARY KEY,
        numero INT NOT NULL DEFAULT 0,
        aberto DATETIME NOT NULL,
        total DECIMAL(10,2) NOT NULL DEFAULT 0,
        entrega DECIMAL(10,2) NOT NULL DEFAULT 0,
        canal VARCHAR(10) NOT NULL DEFAULT 'balcao',
        agendado TINYINT(1) NOT NULL DEFAULT 0,
        previsto DATETIME NULL,
        cliente_id INT NULL,
        escola VARCHAR(60) NULL,
        excluido TINYINT(1) NOT NULL DEFAULT 0,
        pagamento VARCHAR(40) NOT NULL DEFAULT '',
        INDEX idx_aberto (aberto),
        INDEX idx_cliente (cliente_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS pc_itens (
        id INT PRIMARY KEY,
        pedido_id INT NOT NULL,
        pai TINYINT(1) NOT NULL DEFAULT 0,
        produto VARCHAR(120) NOT NULL DEFAULT '',
        categoria VARCHAR(40) NOT NULL DEFAULT '',
        qtd DECIMAL(10,3) NOT NULL DEFAULT 0,
        valor DECIMAL(10,2) NOT NULL DEFAULT 0,
        mini INT NOT NULL DEFAULT 0,
        INDEX idx_pedido (pedido_id),
        INDEX idx_produto (produto)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS an_info (
        empresa VARCHAR(40) NOT NULL,
        chave VARCHAR(60) NOT NULL,
        valor TEXT NOT NULL,
        PRIMARY KEY (empresa, chave)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS an_importacoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa VARCHAR(40) NOT NULL,
        quando DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        fonte VARCHAR(40) NOT NULL DEFAULT '',
        gerado_em DATETIME NULL,
        pedidos INT NOT NULL DEFAULT 0,
        itens INT NOT NULL DEFAULT 0,
        clientes INT NOT NULL DEFAULT 0,
        usuario VARCHAR(60) NOT NULL DEFAULT ''
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
