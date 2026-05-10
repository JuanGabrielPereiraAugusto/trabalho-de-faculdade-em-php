-- ============================================================
--  schema.sql — Script de criação do banco e tabela de tarefas
--  Execute uma vez para preparar o ambiente
-- ============================================================

-- Cria o banco se não existir e seleciona ele
CREATE DATABASE IF NOT EXISTS todo_db
    CHARACTER SET utf8mb4          -- suporte a emojis e caracteres especiais
    COLLATE utf8mb4_unicode_ci;

USE todo_db;

-- Tabela principal de tarefas
CREATE TABLE IF NOT EXISTS tarefas (
    id         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    titulo     VARCHAR(255)    NOT NULL,                         -- texto da tarefa
    concluida  TINYINT(1)      NOT NULL DEFAULT 0,              -- 0 = pendente | 1 = concluída
    criada_em  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_concluida (concluida)   -- índice para filtrar pendentes/concluídas rapidamente
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
