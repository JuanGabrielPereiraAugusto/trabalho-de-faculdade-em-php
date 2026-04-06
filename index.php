<?php

declare(strict_types=1);
session_start();
require __DIR__ . '/includes/functions.php';

$countries = getCountries();
$configs = difficultyConfig();
$error = null;

if (empty($countries)) {
    $error = 'Não foi possível carregar a API agora. Tente de novo em alguns segundos.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === null) {
    $action = $_POST['action'] ?? '';

    if ($action === 'start') {
        $difficulty = (string) ($_POST['difficulty'] ?? 'medium');
        $questions = (int) ($_POST['questions'] ?? 10);
        startGame($countries, array_key_exists($difficulty, $configs) ? $difficulty : 'medium', $questions);
        header('Location: index.php');
        exit;
    }

    if ($action === 'answer' && game() !== null) {
        $selected = isset($_POST['answer']) ? (string) $_POST['answer'] : null;
        $timedOut = ($_POST['timed_out'] ?? '0') === '1';
        answerQuestion($countries, $selected, $timedOut);
        header('Location: index.php');
        exit;
    }

    if ($action === 'next' && game() !== null) {
        nextQuestion($countries);
        header('Location: index.php');
        exit;
    }

    if ($action === 'hint' && game() !== null && !isFinished(game()) && !(game()['answered'] ?? false)) {
        $_SESSION['game']['question']['hint_used'] = true;
        header('Location: index.php');
        exit;
    }

    if ($action === 'restart') {
        resetGame();
        header('Location: index.php');
        exit;
    }

    if ($action === 'save_ranking' && game() !== null && isFinished(game())) {
        saveRanking((string) ($_POST['player_name'] ?? ''), game());
        header('Location: index.php');
        exit;
    }
}

$game = game();
$finished = $game !== null && isFinished($game);
$questionCountry = ($game && !$finished) ? currentQuestionCountry($countries, $game) : null;
$ranking = rankingList();
$remaining = ($game && !$finished) ? secondsRemaining($game) : 0;
$total = (int) ($game['total_questions'] ?? 0);
$currentNumber = $game ? ((int) $game['current_index'] + 1) : 0;
$progress = $game && $total > 0 ? ((int) $game['current_index'] / $total) * 100 : 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz de Países Pro</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="bg-orb orb-1"></div>
    <div class="bg-orb orb-2"></div>

    <main class="layout">
        <section class="panel main-panel">
            <div class="brand-row">
                <div>
                    <span class="badge">PHP + REST Countries</span>
                    <h1>Quiz de Países Pro</h1>
                    <p class="muted">Versão mais completa: timer, ranking, dificuldade, dicas e visual responsivo.</p>
                </div>
                <?php if ($game !== null): ?>
                    <form method="post">
                        <input type="hidden" name="action" value="restart">
                        <button class="btn btn-ghost" type="submit">Sair da partida</button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if ($error !== null): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php elseif ($game === null): ?>
                <section class="hero-card">
                    <div>
                        <h2>Começar nova partida</h2>
                        <p class="muted">Escolha a dificuldade e a quantidade de perguntas.</p>
                    </div>

                    <form method="post" class="setup-form">
                        <input type="hidden" name="action" value="start">

                        <div class="grid-3">
                            <label class="choice-card">
                                <input type="radio" name="difficulty" value="easy" checked>
                                <span class="choice-title">Fácil</span>
                                <span class="choice-text">20s por pergunta • países mais conhecidos</span>
                            </label>
                            <label class="choice-card">
                                <input type="radio" name="difficulty" value="medium">
                                <span class="choice-title">Médio</span>
                                <span class="choice-text">15s por pergunta • equilíbrio</span>
                            </label>
                            <label class="choice-card">
                                <input type="radio" name="difficulty" value="hard">
                                <span class="choice-title">Difícil</span>
                                <span class="choice-text">10s por pergunta • inclui mais países</span>
                            </label>
                        </div>

                        <div class="question-counts">
                            <label><input type="radio" name="questions" value="5"> 5 perguntas</label>
                            <label><input type="radio" name="questions" value="10" checked> 10 perguntas</label>
                            <label><input type="radio" name="questions" value="15"> 15 perguntas</label>
                            <label><input type="radio" name="questions" value="20"> 20 perguntas</label>
                        </div>

                        <button class="btn btn-primary" type="submit">Jogar agora</button>
                    </form>
                </section>
            <?php elseif ($finished): ?>
                <section class="result-card">
                    <div class="result-score-wrap">
                        <div class="score-circle">
                            <span><?= (int) $game['score'] ?></span>
                            <small>pontos</small>
                        </div>
                        <div>
                            <h2>Partida encerrada</h2>
                            <p class="muted">Dificuldade: <strong><?= h((string) $game['difficulty_label']) ?></strong></p>
                            <p class="muted">Melhor sequência: <strong><?= (int) $game['best_streak'] ?></strong></p>
                            <p class="muted">Perguntas: <strong><?= (int) $game['total_questions'] ?></strong></p>
                        </div>
                    </div>

                    <div class="result-actions">
                        <form method="post" class="save-form">
                            <input type="hidden" name="action" value="save_ranking">
                            <input
                                type="text"
                                name="player_name"
                                maxlength="24"
                                placeholder="Seu nome no ranking"
                                <?= ($game['ranking_saved'] ?? false) ? 'disabled' : '' ?>
                            >
                            <button class="btn btn-primary" type="submit" <?= ($game['ranking_saved'] ?? false) ? 'disabled' : '' ?>>
                                <?= ($game['ranking_saved'] ?? false) ? 'Pontuação salva' : 'Salvar no ranking' ?>
                            </button>
                        </form>

                        <form method="post">
                            <input type="hidden" name="action" value="restart">
                            <button class="btn btn-secondary" type="submit">Nova partida</button>
                        </form>
                    </div>
                </section>
            <?php else: ?>
                <section class="game-header">
                    <div>
                        <div class="game-meta">
                            <span class="badge"><?= h((string) $game['difficulty_label']) ?></span>
                            <span class="badge badge-dark">Pergunta <?= $currentNumber ?> de <?= $total ?></span>
                            <span class="badge badge-dark">Pontos: <?= (int) $game['score'] ?></span>
                            <span class="badge badge-dark">Sequência: <?= (int) $game['streak'] ?></span>
                        </div>
                        <div class="progress-track">
                            <div class="progress-bar" style="width: <?= number_format($progress, 2, '.', '') ?>%"></div>
                        </div>
                    </div>
                    <div class="timer-box" data-timer-box>
                        <span class="timer-label">Tempo</span>
                        <strong id="timer-value"><?= $remaining ?></strong>
                    </div>
                </section>

                <section class="quiz-card">
                    <div class="question-top">
                        <div>
                            <h2>De qual país é esta bandeira?</h2>
                            <p class="muted">Escolha uma alternativa antes do tempo acabar.</p>
                        </div>
                        <?php if (!($game['question']['hint_used'] ?? false)): ?>
                            <form method="post">
                                <input type="hidden" name="action" value="hint">
                                <button class="btn btn-ghost" type="submit">Usar dica</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <div class="flag-stage">
                        <img src="<?= h($questionCountry['flag'] ?? '') ?>" alt="Bandeira do país" class="flag-image">
                    </div>

                    <?php if (($game['question']['hint_used'] ?? false) && $questionCountry !== null): ?>
                        <div class="hint-box">
                            <strong>Dica:</strong>
                            <?= $questionCountry['capital'] !== '' ? 'Capital: ' . h($questionCountry['capital']) . ' • ' : '' ?>
                            <?= $questionCountry['region'] !== '' ? 'Região: ' . h($questionCountry['region']) : 'Sem região disponível' ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($game['feedback'])): ?>
                        <div class="alert alert-<?= h((string) $game['feedback']['type']) ?>">
                            <strong><?= h((string) $game['feedback']['title']) ?></strong>
                            <span><?= h((string) $game['feedback']['text']) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!($game['answered'] ?? false)): ?>
                        <form method="post" id="answer-form" class="answers-grid" data-seconds="<?= $remaining ?>">
                            <input type="hidden" name="action" value="answer">
                            <input type="hidden" name="timed_out" id="timed-out" value="0">
                            <?php foreach (($game['question']['option_codes'] ?? []) as $code): ?>
                                <?php if (isset($countries[$code])): ?>
                                    <button class="answer-btn" type="submit" name="answer" value="<?= h($code) ?>">
                                        <?= h($countries[$code]['name']) ?>
                                    </button>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </form>
                    <?php else: ?>
                        <div class="answers-grid">
                            <?php
                            $correctCode = (string) ($game['question']['correct_code'] ?? '');
                            $selectedCode = (string) ($game['selected_code'] ?? '');
                            ?>
                            <?php foreach (($game['question']['option_codes'] ?? []) as $code): ?>
                                <?php if (isset($countries[$code])): ?>
                                    <?php
                                    $class = 'answer-btn disabled';
                                    if ($code === $correctCode) {
                                        $class .= ' correct';
                                    } elseif ($code === $selectedCode) {
                                        $class .= ' wrong';
                                    }
                                    ?>
                                    <div class="<?= h($class) ?>"><?= h($countries[$code]['name']) ?></div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>

                        <form method="post">
                            <input type="hidden" name="action" value="next">
                            <button class="btn btn-primary" type="submit">
                                <?= $currentNumber >= $total ? 'Ver resultado' : 'Próxima pergunta' ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </section>

        <aside class="panel side-panel">
            <section class="ranking-card">
                <div class="side-title-row">
                    <h3>Top 10</h3>
                    <span class="muted small">ranking local</span>
                </div>

                <?php if (empty($ranking)): ?>
                    <p class="muted">Ainda não há pontuações salvas.</p>
                <?php else: ?>
                    <ol class="ranking-list">
                        <?php foreach ($ranking as $item): ?>
                            <li>
                                <div>
                                    <strong><?= h((string) $item['name']) ?></strong>
                                    <span><?= h((string) $item['difficulty']) ?> • seq. <?= (int) $item['streak'] ?></span>
                                </div>
                                <div class="ranking-score"><?= (int) $item['score'] ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>

            <section class="ranking-card">
                <h3>Como funciona a pontuação</h3>
                <ul class="info-list">
                    <li>Você ganha pontos por acerto.</li>
                    <li>Sobra de tempo dá pontos extras.</li>
                    <li>Difíceis valem mais.</li>
                    <li>Erros zeram a sequência atual.</li>
                </ul>
            </section>
        </aside>
    </main>

    <script src="assets/app.js"></script>
</body>
</html>
