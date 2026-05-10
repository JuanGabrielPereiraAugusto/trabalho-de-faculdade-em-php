<?php
/**
 * actions.php — Controlador de ações CRUD
 * Recebe requisições POST e executa a operação correta no banco.
 * Cada operação usa Prepared Statements → zero risco de SQL Injection.
 */

require_once 'db.php';

// Apenas aceita requisições POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$acao = $_POST['acao'] ?? '';
$pdo  = getConnection();

switch ($acao) {

    // ── CREATE: adicionar nova tarefa ────────────────────────────────────────
    case 'adicionar':
        // Sanitização básica: remove espaços extras nas bordas
        $titulo = trim($_POST['titulo'] ?? '');

        // Validação: não permite tarefa em branco
        if ($titulo === '') {
            $_SESSION['erro'] = 'O título da tarefa não pode estar vazio.';
            header('Location: index.php');
            exit;
        }

        // Limita o tamanho para evitar inputs excessivos
        if (mb_strlen($titulo) > 255) {
            $_SESSION['erro'] = 'O título não pode ter mais de 255 caracteres.';
            header('Location: index.php');
            exit;
        }

        // Prepared statement: o :titulo nunca será interpretado como SQL
        $stmt = $pdo->prepare('INSERT INTO tarefas (titulo) VALUES (:titulo)');
        $stmt->execute([':titulo' => $titulo]);
        break;

    // ── UPDATE: alternar status concluída/pendente ───────────────────────────
    case 'alternar':
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if ($id === false || $id === null) break; // ignora IDs inválidos

        // Usa NOT para inverter o valor booleano diretamente no banco
        $stmt = $pdo->prepare('UPDATE tarefas SET concluida = NOT concluida WHERE id = :id');
        $stmt->execute([':id' => $id]);
        break;

    // ── DELETE: excluir tarefa ───────────────────────────────────────────────
    case 'excluir':
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if ($id === false || $id === null) break;

        $stmt = $pdo->prepare('DELETE FROM tarefas WHERE id = :id');
        $stmt->execute([':id' => $id]);
        break;

    default:
        // Ação desconhecida: apenas redireciona
        break;
}

// Após qualquer ação, volta para a página principal (Post/Redirect/Get pattern)
// Isso evita o "Você deseja reenviar o formulário?" ao recarregar a página
header('Location: index.php');
exit;
