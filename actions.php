<?php

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$acao = $_POST['acao'] ?? '';
$pdo  = getConnection();

switch ($acao) {

    case 'adicionar':
        
        $titulo = trim($_POST['titulo'] ?? '');

        
        if ($titulo === '') {
            $_SESSION['erro'] = 'O título da tarefa não pode estar vazio.';
            header('Location: index.php');
            exit;
        }

       
        if (mb_strlen($titulo) > 255) {
            $_SESSION['erro'] = 'O título não pode ter mais de 255 caracteres.';
            header('Location: index.php');
            exit;
        }

        
        $stmt = $pdo->prepare('INSERT INTO tarefas (titulo) VALUES (:titulo)');
        $stmt->execute([':titulo' => $titulo]);
        break;

   
    case 'alternar':
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if ($id === false || $id === null) break; 

        
        $stmt = $pdo->prepare('UPDATE tarefas SET concluida = NOT concluida WHERE id = :id');
        $stmt->execute([':id' => $id]);
        break;

    
    case 'excluir':
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if ($id === false || $id === null) break;

        $stmt = $pdo->prepare('DELETE FROM tarefas WHERE id = :id');
        $stmt->execute([':id' => $id]);
        break;

    default:
   
        break;
}


header('Location: index.php');
exit;
