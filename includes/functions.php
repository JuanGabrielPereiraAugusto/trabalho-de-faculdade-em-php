<?php

declare(strict_types=1);

const API_URL = 'https://restcountries.com/v3.1/all?fields=name,translations,flags,cca2,capital,region,population';
const CACHE_FILE = __DIR__ . '/../data/countries_cache.json';
const RANKING_FILE = __DIR__ . '/../data/ranking.json';
const CACHE_TTL = 86400;

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function ensureDataFiles(): void
{
    $dir = dirname(RANKING_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    if (!file_exists(RANKING_FILE)) {
        file_put_contents(RANKING_FILE, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

function fetchApi(string $url): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'QuizBandeirasPHP/1.0'
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (is_string($response) && $httpCode >= 200 && $httpCode < 300) {
            return $response;
        }
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 20,
            'header' => "User-Agent: QuizBandeirasPHP/1.0\r\n"
        ]
    ]);

    $response = @file_get_contents($url, false, $context);
    return is_string($response) ? $response : null;
}

function getCountries(): array
{
    ensureDataFiles();

    $json = null;

    if (file_exists(CACHE_FILE) && (time() - (int) filemtime(CACHE_FILE)) < CACHE_TTL) {
        $json = file_get_contents(CACHE_FILE) ?: null;
    }

    if ($json === null) {
        $json = fetchApi(API_URL);
        if ($json !== null) {
            file_put_contents(CACHE_FILE, $json);
        } elseif (file_exists(CACHE_FILE)) {
            $json = file_get_contents(CACHE_FILE) ?: null;
        }
    }

    if ($json === null) {
        return [];
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        return [];
    }

    $countries = [];
    foreach ($data as $item) {
        $code = strtoupper(trim((string) ($item['cca2'] ?? '')));
        $name = trim((string) ($item['translations']['por']['common'] ?? $item['name']['common'] ?? ''));
        $flag = trim((string) ($item['flags']['png'] ?? $item['flags']['svg'] ?? ''));
        $region = trim((string) ($item['region'] ?? ''));
        $population = (int) ($item['population'] ?? 0);
        $capital = '';

        if (!empty($item['capital']) && is_array($item['capital'])) {
            $capital = trim((string) $item['capital'][0]);
        }

        if ($code === '' || $name === '' || $flag === '') {
            continue;
        }

        $countries[$code] = [
            'code' => $code,
            'name' => $name,
            'flag' => $flag,
            'capital' => $capital,
            'region' => $region,
            'population' => $population,
        ];
    }

    uasort($countries, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));
    return $countries;
}

function difficultyConfig(): array
{
    return [
        'easy' => [
            'label' => 'Fácil',
            'timer' => 20,
            'min_population' => 15000000,
            'bonus' => 10,
        ],
        'medium' => [
            'label' => 'Médio',
            'timer' => 15,
            'min_population' => 3000000,
            'bonus' => 15,
        ],
        'hard' => [
            'label' => 'Difícil',
            'timer' => 10,
            'min_population' => 0,
            'bonus' => 20,
        ],
    ];
}

function knownCountryCodes(): array
{
    return [
        'BR','US','AR','PT','ES','FR','DE','IT','GB','JP','CN','IN','MX','CA','AU','ZA','EG','RU','CL','CO',
        'UY','PY','BO','PE','VE','KR','TR','NL','BE','CH','SE','NO','DK','FI','GR','PL','UA','CZ','AT','IE',
        'NZ','TH','ID','SA','AE','IL','MA','DZ','NG','KE'
    ];
}

function filterCountriesByDifficulty(array $countries, string $difficulty): array
{
    $config = difficultyConfig()[$difficulty] ?? difficultyConfig()['medium'];
    $filtered = [];

    foreach ($countries as $code => $country) {
        if ($difficulty === 'easy') {
            if ($country['population'] >= $config['min_population'] || in_array($code, knownCountryCodes(), true)) {
                $filtered[$code] = $country;
            }
            continue;
        }

        if ($difficulty === 'medium') {
            if ($country['population'] >= $config['min_population'] || in_array($code, knownCountryCodes(), true)) {
                $filtered[$code] = $country;
            }
            continue;
        }

        $filtered[$code] = $country;
    }

    if (count($filtered) < 20) {
        return $countries;
    }

    return $filtered;
}

function clampQuestionCount(int $value, int $poolSize): int
{
    $value = max(5, min(20, $value));
    return min($value, max(5, $poolSize));
}

function generateQuestion(array $countries, array $poolCodes, int $index, int $timerSeconds): ?array
{
    if (!isset($poolCodes[$index]) || !isset($countries[$poolCodes[$index]])) {
        return null;
    }

    $correctCode = $poolCodes[$index];
    $distractorCodes = array_values(array_filter($poolCodes, static fn(string $code): bool => $code !== $correctCode));

    shuffle($distractorCodes);
    $options = [$correctCode, ...array_slice($distractorCodes, 0, 3)];
    shuffle($options);

    return [
        'correct_code' => $correctCode,
        'option_codes' => $options,
        'started_at' => time(),
        'timer_seconds' => $timerSeconds,
        'hint_used' => false,
    ];
}

function startGame(array $countries, string $difficulty, int $totalQuestions): void
{
    $config = difficultyConfig()[$difficulty] ?? difficultyConfig()['medium'];
    $pool = filterCountriesByDifficulty($countries, $difficulty);
    $poolCodes = array_keys($pool);
    shuffle($poolCodes);

    $totalQuestions = clampQuestionCount($totalQuestions, count($poolCodes));
    $poolCodes = array_slice($poolCodes, 0, max($totalQuestions, 4));

    $_SESSION['game'] = [
        'difficulty' => $difficulty,
        'difficulty_label' => $config['label'],
        'total_questions' => $totalQuestions,
        'current_index' => 0,
        'score' => 0,
        'streak' => 0,
        'best_streak' => 0,
        'answered' => false,
        'selected_code' => null,
        'feedback' => null,
        'ranking_saved' => false,
        'pool_codes' => $poolCodes,
        'question' => generateQuestion($countries, $poolCodes, 0, $config['timer']),
    ];
}

function game(): ?array
{
    return $_SESSION['game'] ?? null;
}

function currentQuestionCountry(array $countries, array $game): ?array
{
    $code = $game['question']['correct_code'] ?? null;
    return ($code && isset($countries[$code])) ? $countries[$code] : null;
}

function secondsRemaining(array $game): int
{
    if (($game['answered'] ?? false) === true) {
        return 0;
    }

    $started = (int) ($game['question']['started_at'] ?? time());
    $timer = (int) ($game['question']['timer_seconds'] ?? 15);
    $remaining = $timer - (time() - $started);
    return max(0, $remaining);
}

function buildFeedback(array $country, bool $correct, bool $timedOut): array
{
    $parts = [];
    if ($country['capital'] !== '') {
        $parts[] = 'Capital: ' . $country['capital'];
    }
    if ($country['region'] !== '') {
        $parts[] = 'Região: ' . $country['region'];
    }
    if ($country['population'] > 0) {
        $parts[] = 'População: ' . number_format($country['population'], 0, ',', '.');
    }

    if ($timedOut) {
        $title = 'Tempo esgotado!';
        $type = 'warning';
    } elseif ($correct) {
        $title = 'Acertou!';
        $type = 'success';
    } else {
        $title = 'Errou!';
        $type = 'error';
    }

    return [
        'title' => $title,
        'type' => $type,
        'text' => 'Resposta certa: ' . $country['name'] . (count($parts) ? ' • ' . implode(' • ', $parts) : ''),
    ];
}

function answerQuestion(array $countries, ?string $selectedCode, bool $timedOut = false): void
{
    if (!isset($_SESSION['game']) || ($_SESSION['game']['answered'] ?? false) === true) {
        return;
    }

    $game = &$_SESSION['game'];
    $question = $game['question'] ?? null;
    if (!is_array($question)) {
        return;
    }

    $remaining = secondsRemaining($game);
    if ($remaining <= 0) {
        $timedOut = true;
    }

    $correctCode = (string) ($question['correct_code'] ?? '');
    if ($correctCode === '' || !isset($countries[$correctCode])) {
        return;
    }

    $country = $countries[$correctCode];
    $selectedCode = $selectedCode !== null ? strtoupper(trim($selectedCode)) : null;
    $isCorrect = !$timedOut && $selectedCode === $correctCode;

    if ($isCorrect) {
        $difficulty = $game['difficulty'] ?? 'medium';
        $bonus = difficultyConfig()[$difficulty]['bonus'] ?? 15;
        $game['score'] += $bonus + $remaining;
        $game['streak']++;
        $game['best_streak'] = max((int) $game['best_streak'], (int) $game['streak']);
    } else {
        $game['streak'] = 0;
    }

    $game['answered'] = true;
    $game['selected_code'] = $selectedCode;
    $game['feedback'] = buildFeedback($country, $isCorrect, $timedOut);
}

function nextQuestion(array $countries): void
{
    if (!isset($_SESSION['game'])) {
        return;
    }

    $game = &$_SESSION['game'];
    $game['current_index']++;
    $game['answered'] = false;
    $game['selected_code'] = null;
    $game['feedback'] = null;

    if ($game['current_index'] >= $game['total_questions']) {
        $game['question'] = null;
        return;
    }

    $difficulty = $game['difficulty'] ?? 'medium';
    $timer = difficultyConfig()[$difficulty]['timer'] ?? 15;
    $game['question'] = generateQuestion($countries, $game['pool_codes'], (int) $game['current_index'], $timer);
}

function resetGame(): void
{
    unset($_SESSION['game']);
}

function isFinished(array $game): bool
{
    return ($game['current_index'] ?? 0) >= ($game['total_questions'] ?? 0) || empty($game['question']);
}

function rankingList(): array
{
    ensureDataFiles();
    $json = file_get_contents(RANKING_FILE);
    $data = json_decode((string) $json, true);
    return is_array($data) ? $data : [];
}

function saveRanking(string $name, array $game): void
{
    ensureDataFiles();

    $name = trim($name);
    $name = preg_replace('/\s+/', ' ', $name ?? '');
    $name = mb_substr((string) $name, 0, 24);

    if ($name === '' || ($game['ranking_saved'] ?? false) === true) {
        return;
    }

    $list = rankingList();
    $list[] = [
        'name' => $name,
        'score' => (int) ($game['score'] ?? 0),
        'difficulty' => (string) ($game['difficulty_label'] ?? 'Médio'),
        'streak' => (int) ($game['best_streak'] ?? 0),
        'questions' => (int) ($game['total_questions'] ?? 0),
        'date' => date('d/m/Y H:i'),
    ];

    usort($list, static function (array $a, array $b): int {
        if ($a['score'] === $b['score']) {
            return $b['streak'] <=> $a['streak'];
        }
        return $b['score'] <=> $a['score'];
    });

    $list = array_slice($list, 0, 10);
    file_put_contents(RANKING_FILE, json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $_SESSION['game']['ranking_saved'] = true;
}
