<?php
require_once __DIR__ . '/theme.php';
require_once __DIR__ . '/db.php';
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// 문제 한 개 랜덤 선택 (초기 표시용)
$r = $conn->query("SELECT id,word,meaning,reading,example,example_ko FROM words ORDER BY RAND() LIMIT 1");
$word = $r->fetch_assoc();

$feedback = '';
$revealed = null;
$userAnswer = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = (int)($_POST['id'] ?? 0);
  $answer = trim($_POST['answer'] ?? '');
  $userAnswer = $answer;
  $revealOnly = isset($_POST['reveal']) && $_POST['reveal'] === '1';

  // 현재 문제 불러오기
  $st = $conn->prepare("SELECT word,meaning,reading,example,example_ko FROM words WHERE id=?");
  $st->bind_param('i', $id);
  $st->execute();
  $res = $st->get_result()->fetch_assoc();
  $st->close();

  if ($res) {
    $revealed = $res;

    if ($revealOnly) {
      $feedback = ''; // 채점 X
    } else {
      if (mb_strtolower($answer, 'UTF-8') === mb_strtolower($res['meaning'], 'UTF-8')) {
        $feedback = '✅ 정답입니다!';
      } else {
        $feedback = '❌ 오답입니다!';
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8"><title>퀴즈 모드 | KotobaAI</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<?= theme_head() ?>
<style>
  body{
    margin:0;
    font-family:system-ui,-apple-system,Segoe UI,Roboto,'Noto Sans KR',sans-serif;
    display:flex; align-items:center; justify-content:center; min-height:100vh;
  }
  .card{ width:min(720px,92vw); padding:40px; }
  .tile{
    background:var(--card); border-radius:14px; padding:16px 20px;
    border:1px solid var(--border); margin-top:14px; font-size:1rem; line-height:1.55;
  }
  .line { margin-bottom:6px; }
  .btn-row{ display:flex; gap:8px; justify-content:center; margin-top:14px; flex-wrap:wrap; }
  .problem{
    font-size:2.6rem; font-weight:800; margin:10px 0 12px; color:var(--ink);
    text-align:center; text-shadow:0 0 12px rgba(76,128,255,.2);
  }
  h1{ margin:0 0 16px; color:var(--acc); text-align:center; }
  input[type=text]{
    width:100%; padding:12px; border-radius:12px; border:1px solid var(--border);
    background:var(--card); color:var(--ink); font-size:1.1rem; text-align:center;
  }
  .feedback{ margin-top:16px; font-size:1.2rem; font-weight:700; text-align:center; }
  .sub{ text-align:center; margin-bottom:14px; color:var(--ink2); }
</style>
</head>
<body class="page">
  <div class="card">
    <h1>🎯 퀴즈 모드</h1>

    <?php if($revealed): ?>

      <!-- 문제 제목 -->
      <div class="problem">
        <?=h($revealed['word'])?><?= $revealed['reading'] ? '（'.h($revealed['reading']).'）' : '' ?>
      </div>



      <!-- 피드백 -->
      <?php if($feedback): ?>
        <div class="feedback"><?=$feedback?></div>
      <?php endif; ?>

      <!-- 상세 결과 (스크린샷 2번 스타일) -->
      <div class="tile">
        <div class="line"><b>문제:</b> <?=h($revealed['word'])?><?= $revealed['reading'] ? '（'.h($revealed['reading']).'）' : '' ?></div>

        <div class="line"><b>내 답:</b> 
          <?= $revealOnly ? '(모르겠어요)' : h($userAnswer) ?>
        </div>

        <div class="line"><b>정답:</b> <?=h($revealed['meaning'])?></div>

        <?php if(!empty($revealed['example'])): ?>
          <div class="line"><b>예문 (JP):</b> <?=nl2br(h($revealed['example']))?></div>
        <?php endif; ?>

        <?php if(!empty($revealed['example_ko'])): ?>
          <div class="line"><b>예문 (KR):</b> <?=nl2br(h($revealed['example_ko']))?></div>
        <?php endif; ?>
      </div>

      <div class="btn-row">
        <a class="btn" href="quiz.php">다음 문제</a>
        <a class="btn ghost" href="index.php">🏠 메인으로</a>
      </div>

    <?php else: ?>

      <!-- 문제 -->
      <div class="problem">
        <?=h($word['word'])?><?= $word['reading'] ? '（'.h($word['reading']).'）' : '' ?>
      </div>
      <div class="sub">위 단어의 <b>뜻(한국어)</b>을 입력하세요</div>

      <form method="post" autocomplete="off" novalidate>
        <input type="hidden" name="id" value="<?=$word['id']?>">
        <input type="text" name="answer" placeholder="정답 입력" required>

        <div class="btn-row">
          <button class="btn" type="submit" name="submit" value="1">제출하기</button>
          <button class="btn ghost" type="submit" name="reveal" value="1" formnovalidate>모르겠어요</button>
          <a class="btn ghost" href="index.php">🏠 메인으로</a>
        </div>
      </form>

    <?php endif; ?>
  </div>
</body>
</html>
