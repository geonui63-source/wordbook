<?php
require_once __DIR__ . '/theme.php';
require_once __DIR__ . '/db.php';

/**
 * OpenAI 설정 로드
 * - config.php 있으면 사용 (로컬 개발)
 * - 없으면 config.sample.php 사용 (GitHub/포트폴리오)
 */
$configFile = __DIR__ . '/config.php';
$configSampleFile = __DIR__ . '/config.sample.php';

if (file_exists($configFile)) {
    require_once $configFile;
} elseif (file_exists($configSampleFile)) {
    require_once $configSampleFile;
} else {
    http_response_code(500);
    die('설정 파일이 없습니다: config.php 또는 config.sample.php를 확인하세요.');
}

/**
 * 기존 코드 호환용
 * (아래 로직들이 $OPENAI_API_KEY 변수를 사용함)
 */
$OPENAI_API_KEY = (defined('OPENAI_API_KEY') && OPENAI_API_KEY !== 'YOUR_OPENAI_API_KEY_HERE')
    ? OPENAI_API_KEY
    : '';

function h($s){
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ── 언어 판별 유틸 ────────────────────── */
function has_hangul(string $str): bool {
    return preg_match('/[\x{AC00}-\x{D7A3}\x{1100}-\x{11FF}]/u', $str) === 1;
}
function has_japanese(string $str): bool {
    return preg_match('/[\p{Han}\x{3040}-\x{309F}\x{30A0}-\x{30FF}]/u', $str) === 1;
}

/* ---------------- OpenAI 호출 ---------------- */
function openai_chat_call(
    string $apiKey,
    array $messages,
    string $model = 'gpt-4o-mini',
    int $timeout = 20
): string {
    if (!$apiKey) {
        throw new RuntimeException('OPENAI_API_KEY가 없습니다.');
    }

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    $payload = json_encode([
        'model' => $model,
        'messages' => $messages,
        'temperature' => 0.2,
    ], JSON_UNESCAPED_UNICODE);

    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer '.$apiKey
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
    ]);

    $resp = curl_exec($ch);
    if ($resp === false) {
        $e = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('OpenAI 연결 실패: '.$e);
    }

    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code < 200 || $code >= 300) {
        throw new RuntimeException("OpenAI 응답 오류(HTTP $code): $resp");
    }

    $data = json_decode($resp, true);
    return trim($data['choices'][0]['message']['content'] ?? '');
}

/**
 * 의미 생성
 */
function ai_generate_senses(string $apiKey, string $input): array {
    $sys = [
        'role'=>'system',
        'content'=>
            "You are a bilingual assistant (Korean ↔ Japanese).\n".
            "Return STRICT JSON only.\n".
            "{\"senses\":[{\"label_ko\":\"...\",\"jp_word\":\"...\",\"ko_word\":\"...\",\"reading\":\"...\",\"example_jp\":\"...\",\"example_ko\":\"...\"}]}"
    ];

    $usr = ['role'=>'user','content'=>$input];
    $json = openai_chat_call($apiKey, [$sys,$usr]);
    $data = json_decode($json, true);

    if (!is_array($data) || !isset($data['senses']) || !is_array($data['senses'])) {
        throw new RuntimeException('AI 응답 파싱 실패');
    }

    $out = [];
    foreach ($data['senses'] as $s) {
        $out[] = [
            'label_ko'   => trim($s['label_ko'] ?? ''),
            'jp_word'    => trim($s['jp_word'] ?? ''),
            'ko_word'    => trim($s['ko_word'] ?? ''),
            'reading'    => trim($s['reading'] ?? ''),
            'example_jp' => trim($s['example_jp'] ?? ''),
            'example_ko' => trim($s['example_ko'] ?? ''),
        ];
    }

    return array_slice($out, 0, 3);
}

/* ---------------- 상태 ---------------- */
$error = null;
$ok = null;
$senses = [];

$word = trim($_POST['word'] ?? ($_GET['word'] ?? ''));


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'generate';

    if ($action === 'generate') {
        if ($word === '') {
            $error = '단어를 입력해 주세요.';
        } else {
            try {
                $senses = ai_generate_senses($OPENAI_API_KEY, $word);
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<title>단어 추가 | KotobaAI</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<?= theme_head() ?>
</head>
<body class="page">

<div class="wrap">
  <h2>단어 추가</h2>

  <?php if ($error): ?>
    <p style="color:red"><?=h($error)?></p>
  <?php endif; ?>

  <form method="post">
    <input type="hidden" name="action" value="generate">
    <input type="text" name="word" value="<?=h($word)?>" placeholder="단어 입력">
    <button type="submit">의미 생성</button>
  </form>

  <?php if ($senses): ?>
    <h3>의미 후보</h3>
    <ul>
      <?php foreach ($senses as $s): ?>
        <li>
          <b><?=h($s['label_ko'])?></b> :
          <?=h($s['jp_word'])?> / <?=h($s['ko_word'])?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

</body>
</html>
