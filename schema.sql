


CREATE DATABASE IF NOT EXISTS todo_db
    CHARACTER SET utf8mb4          
    COLLATE utf8mb4_unicode_ci;

USE todo_db;

-- Tabela principal de tarefas
CREATE TABLE IF NOT EXISTS tarefas (
    id         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    titulo     VARCHAR(255)    NOT NULL,                        
    concluida  TINYINT(1)      NOT NULL DEFAULT 0,             
    criada_em  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_concluida (concluida)  
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
