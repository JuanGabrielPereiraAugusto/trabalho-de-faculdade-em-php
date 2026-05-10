<?php
/**
 * index.php — Interface principal do To-Do List
 * Responsabilidades: buscar tarefas no banco e exibir o HTML.
 * Toda lógica de escrita está em actions.php (separação de responsabilidades).
 */

require_once 'db.php';

session_start(); // Necessário para mensagens de erro entre redirecionamentos

$pdo = getConnection();

// ── Busca todas as tarefas, pendentes primeiro, depois as concluídas ────────
$stmt = $pdo->query('SELECT * FROM tarefas ORDER BY concluida ASC, criada_em DESC');
$tarefas = $stmt->fetchAll();

// Conta separado para o resumo no cabeçalho
$total     = count($tarefas);
$concluidas = count(array_filter($tarefas, fn($t) => $t['concluida']));
$pendentes  = $total - $concluidas;

// Pega e limpa mensagem de erro da sessão (se houver)
$erro = $_SESSION['erro'] ?? null;
unset($_SESSION['erro']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Tarefas</title>

    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome para ícones -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Google Fonts: Sora (display) + Inter (body) -->
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500&display=swap" rel="stylesheet">

    <script>
        // Configuração do tema personalizado do Tailwind
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        display: ['Sora', 'sans-serif'],
                        body: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        ink: {
                            900: '#0d0d0d',
                            800: '#1a1a1a',
                            700: '#2a2a2a',
                        },
                        accent: {
                            DEFAULT: '#7c3aed',   // roxo vibrante
                            light:   '#a78bfa',
                            glow:    '#7c3aed33',
                        },
                        surface: '#141414',
                    },
                    boxShadow: {
                        'glow': '0 0 20px rgba(124, 58, 237, 0.25)',
                        'glow-lg': '0 0 40px rgba(124, 58, 237, 0.35)',
                    }
                }
            }
        }
    </script>

    <style>
        /* Estilos globais e animações que o Tailwind não cobre diretamente */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #0d0d0d;
            background-image:
                radial-gradient(ellipse 80% 50% at 50% -10%, rgba(124,58,237,0.18) 0%, transparent 70%);
            min-height: 100vh;
        }

        /* Entrada suave para os itens da lista */
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .tarefa-item { animation: slideIn 0.25s ease-out both; }

        /* Linha riscada suave para tarefas concluídas */
        .concluida-texto {
            text-decoration: line-through;
            text-decoration-color: #7c3aed;
            text-decoration-thickness: 2px;
        }

        /* Efeito de brilho no input ao focar */
        .input-glow:focus {
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.3);
        }

        /* Scrollbar customizada para navegadores Webkit */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #1a1a1a; }
        ::-webkit-scrollbar-thumb { background: #7c3aed; border-radius: 3px; }
    </style>
</head>
<body class="text-white">

    <div class="max-w-2xl mx-auto px-4 py-12">

        <!-- ── Cabeçalho ─────────────────────────────────────────────────── -->
        <header class="mb-10 text-center">
            <div class="inline-flex items-center gap-2 bg-accent/10 border border-accent/20 rounded-full px-4 py-1 mb-5">
                <i class="fa-solid fa-sparkles text-accent text-xs"></i>
                <span class="text-accent text-xs font-medium tracking-wider uppercase">Seu espaço de foco</span>
            </div>
            <h1 class="font-display text-5xl font-extrabold tracking-tight mb-2">
                Minhas Tarefas
            </h1>
            <p class="text-zinc-400 text-sm">
                Organize, complete, avance.
            </p>

            <!-- Contadores de resumo -->
            <?php if ($total > 0): ?>
            <div class="flex justify-center gap-6 mt-6">
                <div class="text-center">
                    <span class="block text-2xl font-display font-bold text-white"><?= $total ?></span>
                    <span class="text-xs text-zinc-500 uppercase tracking-wider">Total</span>
                </div>
                <div class="w-px bg-zinc-800"></div>
                <div class="text-center">
                    <span class="block text-2xl font-display font-bold text-amber-400"><?= $pendentes ?></span>
                    <span class="text-xs text-zinc-500 uppercase tracking-wider">Pendentes</span>
                </div>
                <div class="w-px bg-zinc-800"></div>
                <div class="text-center">
                    <span class="block text-2xl font-display font-bold text-emerald-400"><?= $concluidas ?></span>
                    <span class="text-xs text-zinc-500 uppercase tracking-wider">Concluídas</span>
                </div>
            </div>
            <?php endif; ?>
        </header>

        <!-- ── Mensagem de erro (validação) ──────────────────────────────── -->
        <?php if ($erro): ?>
        <div class="flex items-center gap-3 bg-red-950/60 border border-red-800/50 rounded-xl px-4 py-3 mb-6 text-red-300 text-sm">
            <i class="fa-solid fa-triangle-exclamation text-red-400"></i>
            <?= htmlspecialchars($erro) ?>
        </div>
        <?php endif; ?>

        <!-- ── Formulário: adicionar tarefa ──────────────────────────────── -->
        <form action="actions.php" method="POST" class="mb-8">
            <input type="hidden" name="acao" value="adicionar">
            <div class="flex gap-3">
                <input
                    type="text"
                    name="titulo"
                    placeholder="Nova tarefa... (ex: Estudar PDO)"
                    maxlength="255"
                    autocomplete="off"
                    class="input-glow flex-1 bg-ink-800 border border-zinc-700/60 rounded-xl px-4 py-3
                           text-white placeholder-zinc-500 text-sm
                           focus:outline-none focus:border-accent transition-all duration-200"
                >
                <button
                    type="submit"
                    class="bg-accent hover:bg-violet-500 active:scale-95
                           text-white font-semibold text-sm px-5 py-3 rounded-xl
                           shadow-glow hover:shadow-glow-lg
                           transition-all duration-200 flex items-center gap-2 whitespace-nowrap"
                >
                    <i class="fa-solid fa-plus"></i>
                    <span class="hidden sm:inline">Adicionar</span>
                </button>
            </div>
        </form>

        <!-- ── Lista de tarefas ───────────────────────────────────────────── -->
        <?php if (empty($tarefas)): ?>

            <!-- Estado vazio -->
            <div class="text-center py-20">
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-ink-800 border border-zinc-800 mb-5">
                    <i class="fa-regular fa-clipboard text-3xl text-zinc-600"></i>
                </div>
                <p class="text-zinc-500 font-medium">Nenhuma tarefa ainda.</p>
                <p class="text-zinc-600 text-sm mt-1">Adicione a primeira acima ↑</p>
            </div>

        <?php else: ?>

            <ul class="space-y-3">
                <?php foreach ($tarefas as $index => $tarefa): ?>
                <?php
                    $concluida = (bool) $tarefa['concluida'];
                    // Define o delay da animação de entrada com base na posição
                    $delay = min($index * 50, 400);
                ?>
                <li class="tarefa-item group" style="animation-delay: <?= $delay ?>ms">
                    <div class="flex items-center gap-3 bg-ink-800 hover:bg-ink-700
                                border <?= $concluida ? 'border-zinc-800' : 'border-zinc-700/60 hover:border-zinc-600' ?>
                                rounded-xl px-4 py-3.5 transition-all duration-200">

                        <!-- Botão: marcar como concluída / desfazer -->
                        <form action="actions.php" method="POST" class="shrink-0">
                            <input type="hidden" name="acao" value="alternar">
                            <input type="hidden" name="id"   value="<?= $tarefa['id'] ?>">
                            <button
                                type="submit"
                                title="<?= $concluida ? 'Marcar como pendente' : 'Marcar como concluída' ?>"
                                class="w-6 h-6 rounded-full border-2 flex items-center justify-center
                                       transition-all duration-200
                                       <?= $concluida
                                            ? 'bg-emerald-500 border-emerald-500 hover:bg-emerald-400'
                                            : 'border-zinc-600 hover:border-accent hover:bg-accent/10' ?>"
                            >
                                <?php if ($concluida): ?>
                                    <i class="fa-solid fa-check text-white text-[10px]"></i>
                                <?php endif; ?>
                            </button>
                        </form>

                        <!-- Texto da tarefa -->
                        <span class="flex-1 text-sm leading-relaxed break-words
                                     <?= $concluida ? 'concluida-texto text-zinc-500' : 'text-zinc-100' ?>">
                            <?= htmlspecialchars($tarefa['titulo']) ?>
                        </span>

                        <!-- Data de criação (aparece só no hover em telas maiores) -->
                        <span class="hidden sm:block text-[11px] text-zinc-600 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                            <?= date('d/m H:i', strtotime($tarefa['criada_em'])) ?>
                        </span>

                        <!-- Botão: excluir -->
                        <form action="actions.php" method="POST" class="shrink-0"
                              onsubmit="return confirm('Excluir esta tarefa?')">
                            <input type="hidden" name="acao" value="excluir">
                            <input type="hidden" name="id"   value="<?= $tarefa['id'] ?>">
                            <button
                                type="submit"
                                title="Excluir tarefa"
                                class="w-8 h-8 flex items-center justify-center rounded-lg
                                       text-zinc-600 hover:text-red-400 hover:bg-red-950/50
                                       opacity-0 group-hover:opacity-100
                                       transition-all duration-150"
                            >
                                <i class="fa-solid fa-trash text-xs"></i>
                            </button>
                        </form>

                    </div>
                </li>
                <?php endforeach; ?>
            </ul>

            <!-- Barra de progresso -->
            <?php if ($total > 0): ?>
            <div class="mt-8">
                <?php $progresso = round(($concluidas / $total) * 100); ?>
                <div class="flex justify-between text-xs text-zinc-500 mb-2">
                    <span>Progresso</span>
                    <span><?= $progresso ?>%</span>
                </div>
                <div class="h-1.5 bg-ink-800 rounded-full overflow-hidden">
                    <div
                        class="h-full bg-gradient-to-r from-accent to-violet-400 rounded-full transition-all duration-700"
                        style="width: <?= $progresso ?>%"
                    ></div>
                </div>
            </div>
            <?php endif; ?>

        <?php endif; ?>

    </div><!-- /max-w-2xl -->

</body>
</html>
