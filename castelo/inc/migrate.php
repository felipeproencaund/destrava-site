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
    $temB = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pc_pedidos' AND COLUMN_NAME = 'b2b'")->fetchColumn();
    if ((int) $temB === 0) {
        $pdo->exec('ALTER TABLE pc_pedidos ADD COLUMN b2b VARCHAR(10) NULL, ADD COLUMN b2b_quem VARCHAR(80) NULL');
    }
    $tem = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pc_pedidos' AND COLUMN_NAME = 'so_lanche'")->fetchColumn();
    if ((int) $tem === 0) {
        $pdo->exec('ALTER TABLE pc_pedidos ADD COLUMN lanche_valor DECIMAL(10,2) NOT NULL DEFAULT 0, ADD COLUMN so_lanche TINYINT(1) NOT NULL DEFAULT 0');
        castelo_pc_marca_lanches($pdo);
    }
}

/** Lanches da Forasteiro (prensado, hot dog, hambúrguer artesanal, milkshake) ficam fora das análises da Pede Coxinha. */
function castelo_pc_lanche(string $a = ''): string {
    return "({$a}categoria IN ('lanche prensado','hambúrguer') OR {$a}produto REGEXP 'hot ?dog|milk ?shake')";
}

/** Marca em cada pedido quanto foi de lanche e se o pedido foi só de lanche. */
function castelo_pc_marca_lanches(PDO $pdo): void {
    $pdo->exec('UPDATE pc_pedidos p JOIN (SELECT pedido_id, SUM(CASE WHEN ' . castelo_pc_lanche() . ' THEN valor ELSE 0 END) lv,
        SUM(NOT ' . castelo_pc_lanche() . ') outros FROM pc_itens WHERE pai = 0 GROUP BY pedido_id) x ON x.pedido_id = p.id
        SET p.lanche_valor = LEAST(x.lv, p.total), p.so_lanche = (x.outros = 0)');
}


/** Mensagens 1 a 1 pros clientes da Pede Coxinha: modelos, envios e quem não quer receber. */
function castelo_migrate_mensagens(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS pc_msg_modelos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        segmento VARCHAR(30) NOT NULL,
        texto TEXT NOT NULL,
        ativo TINYINT(1) NOT NULL DEFAULT 1,
        INDEX idx_seg (segmento)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS pc_msg_envios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cliente_id INT NOT NULL,
        segmento VARCHAR(30) NOT NULL,
        modelo_id INT NULL,
        enviado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        usuario VARCHAR(60) NOT NULL DEFAULT '',
        INDEX idx_cliente (cliente_id, enviado_em),
        INDEX idx_data (enviado_em)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS pc_msg_optout (
        cliente_id INT PRIMARY KEY,
        quando DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if ((int) $pdo->query('SELECT COUNT(*) FROM pc_msg_modelos')->fetchColumn() === 0) {
        $m = [
            'esfriando' => [
                'Oi, {nome}! Aqui é a Pede Coxinha. Seu último pedido de cento foi {quando}. A agenda de encomendas de dezembro já está aberta, e quem reserva antes garante o horário certinho. Quer que eu anote a sua data? É só me mandar o dia e a quantidade.',
                'Oi, {nome}, tudo bem? Aqui é a Pede Coxinha. A gente faz cento e meio cento por encomenda, com os sabores que você escolher (coxinha, bolinho de frango, risoles, salsicha e kibe) e entrega no horário combinado. Tem alguma comemoração vindo aí?',
                '{nome}, aqui é a Pede Coxinha! Vi que você costuma pedir {o_produto}. Se tiver aniversário, reunião ou festa nas próximas semanas, me fala a data que eu já deixo anotado.',
            ],
            'recompra' => [
                'Oi, {nome}! Aqui é a Pede Coxinha. Seu último cento foi {quando}. Tem alguma comemoração chegando? Me manda a data, a quantidade e os sabores que eu deixo agendado.',
                'Oi, {nome}, tudo bem? Da última vez você levou {o_produto}. Quer repetir pra alguma data? Encomendando com um dia de antecedência, fica pronto no horário que você escolher.',
                '{nome}, aqui é a Pede Coxinha 😊 Dezembro está chegando e a agenda de encomendas já está aberta. Quer garantir seu cento pra alguma data?',
            ],
            'segunda' => [
                'Oi, {nome}! Aqui é a Pede Coxinha. Obrigado pelo pedido! Salva nosso número: por aqui você encomenda cento e meio cento com data e horário marcados.',
                'Oi, {nome}, tudo bem? Você pediu {o_produto} com a gente. Sabia que dá pra montar um cento com os sabores que você quiser? Coxinha, bolinho de frango, risoles, salsicha e kibe. É só me chamar aqui.',
                '{nome}, aqui é a Pede Coxinha! Pra sua próxima festa, a gente entrega o cento no horário combinado. Me chama aqui com a data que eu já te passo tudo.',
            ],
            'sumiu' => [
                'Oi, {nome}! Aqui é a Pede Coxinha. Seu último pedido com a gente foi {quando}. O cardápio ganhou novidades, como os pastéis doces de chocolate com morango e de Romeu e Julieta. Quer que eu te mande o cardápio atualizado?',
                'Oi, {nome}, tudo bem? Aqui é a Pede Coxinha. Seguimos fazendo cento e meio cento por encomenda, com entrega no horário marcado. Tem alguma festa vindo aí? Me fala a data.',
                '{nome}, aqui é a Pede Coxinha! As encomendas de fim de ano já começaram. Se for fazer alguma confraternização, me chama aqui que eu monto o cento do jeito que você gosta.',
            ],
            'fieis' => [
                'Oi, {nome}! Aqui é a Pede Coxinha. Você está entre os clientes que mais pedem com a gente, então quis te avisar primeiro: a agenda de encomendas de dezembro já está aberta. Quer reservar alguma data?',
                '{nome}, aqui é a Pede Coxinha 😊 Obrigado por pedir sempre com a gente! Saíram sabores novos de pastel doce: chocolate com morango, Romeu e Julieta e Sonho de Valsa. Quer provar no seu próximo pedido?',
            ],
        ];
        $st = $pdo->prepare('INSERT INTO pc_msg_modelos (segmento, texto) VALUES (:s, :t)');
        foreach ($m as $seg => $txts) {
            foreach ($txts as $t) {
                $st->execute(['s' => $seg, 't' => $t]);
            }
        }
    }
}
