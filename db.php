<?php
/**
 * db.php — Conexão com o banco de dados via PDO
 * Centralizar aqui facilita trocar as credenciais sem tocar no resto do sistema.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'todo_db');
define('DB_USER', 'root');       // ← troque pelo seu usuário
define('DB_PASS', '');           // ← troque pela sua senha
define('DB_CHARSET', 'utf8mb4');

/**
 * Retorna uma instância PDO configurada com boas práticas de segurança:
 * - ERRMODE_EXCEPTION → lança exceções em vez de falhas silenciosas
 * - EMULATE_PREPARES false → usa prepared statements reais do MySQL
 * - FETCH_ASSOC → retorna arrays associativos por padrão
 */
function getConnection(): PDO {
    static $pdo = null; // Singleton: evita abrir múltiplas conexões por requisição

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES   => false,  // prepared statements reais → bloqueia SQL Injection
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Em produção, nunca exiba o erro diretamente — logue em arquivo
            error_log("Erro de conexão: " . $e->getMessage());
            http_response_code(500);
            die(json_encode(['erro' => 'Falha na conexão com o banco de dados.']));
        }
    }

    return $pdo;
}
