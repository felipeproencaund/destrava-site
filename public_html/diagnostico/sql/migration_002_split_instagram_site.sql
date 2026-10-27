-- ============================================================
-- Migração: separa o campo único "instagram_site" em duas colunas
-- (instagram e site). Rode isso no phpMyAdmin do banco que JÁ
-- ESTÁ no ar (não precisa rodar se você ainda não tinha instalado
-- o schema.sql antes desta versão).
-- ============================================================
ALTER TABLE leads CHANGE instagram_site instagram VARCHAR(200) NULL;
ALTER TABLE leads ADD COLUMN site VARCHAR(200) NULL AFTER instagram;
