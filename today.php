<?php
require_once __DIR__ . '/theme.php';
require_once __DIR__ . '/db.php';
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

date_default_timezone_set('Asia/Seoul');
$selected_date = $_GET['date'] ?? date('Y-m-d');
$yesterday = date('Y-m-d', strtotime($selected_date.' -1 day'));
$tomorrow  = date('Y-m-d', strtotime($selected_date.' +1 day'));

// 오늘(선택일) 데이터
$sql="SELECT word,meaning,reading,example,example_ko,sense,created_at
      FROM words
      WHERE DATE(created_at)=?
      ORDER BY created_at DESC";
$st=$conn->prepare($sql);
$st->bind_param('s',$selected_date);
$st->execute();
$rows=$st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

// 언어 판별 (입력 단어 기준)
// - 한국어: 한글 범위 \x{AC00}-\x{D7A3}
// - 일본어: 히라가나/가타카나/칸지(주요), 장음부호 등
function is_korean_input($w){
  return (bool)preg_match('/\p{Hangul}|\x{AC00}-\x{D7A3}/u', $w);
}
function is_japanese_input($w){
  // 히라가나/가타카나/칸지/반각가타카나
  return (bool)preg_match('/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{31F0}-\x{31FF}\x{FF66}-\x{FF9D}\x{4E00}-\x{9FFF}]/u', $w);
}

// 좌/우 분리
$ko = []; // 한국어 입력
$ja = []; // 일본어 입력
foreach($rows as $r){
  $w = (string)$r['word'];
  if (is_korean_input($w) && !is_japanese_input($w)) {
    $ko[] = $r;
  } else {
    // 일본어로 간주(혼합/기타 포함)
    $ja[] = $r;
  }
}

$cnt_all = count($rows);
$cnt_ko  = count($ko);
$cnt_ja  = count($ja);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<title>날짜별 단어 | KotobaAI</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<?= theme_head() ?>
<style>
  body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,'Noto Sans KR',sans-serif}
  .wrap{max-width:1100px;margin:40px auto;padding:0 18px}
  .badge{display:inline-block;padding:2px 8px;border:1px solid var(--border);border-radius:999px;color:var(--ink2);font-size:11px;margin-left:6px}
  .cols{display:grid; grid-template-columns: 1fr 1fr; gap:14px}
  @media (max-width: 900px){ .cols{ grid-template-columns: 1fr } }
  .col-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
  .empty{padding:18px;border:1px solid var(--border);border-radius:12px;background:var(--muted);color:var(--ink2)}
</style>
</head>
<body class="page">
  <div class="wrap">
    <div class="nav"><a href="index.php" style="color:var(--acc)">← 메인</a></div>

    <div style="display:flex;align-items:flex-end;gap:12px;margin-bottom:8px">
      <h1 style="margin:0">📅 날짜별 단어 보기</h1>
      <div style="color:var(--ink2)"><?=h($selected_date)?> · 총 <?=$cnt_all?>개</div>
    </div>

    <form method="get" class="tile" style="display:flex;gap:8px;align-items:center;margin:12px 0">
      <input class="input" type="date" name="date" value="<?=h($selected_date)?>">
      <button class="btn">조회</button>
      <a class="btn ghost" href="?date=<?=date('Y-m-d')?>">오늘로</a>
    </form>

    <div class="tile" style="display:flex;gap:8px;margin-bottom:16px">
      <a class="btn ghost" href="?date=<?=$yesterday?>">← 어제 (<?=$yesterday?>)</a>
      <a class="btn ghost" href="?date=<?=$tomorrow?>">내일 (<?=$tomorrow?>) →</a>
    </div>

    <div class="cols">
      <!-- 왼쪽: 한국어 입력 -->
      <div>
        <div class="col-head">
          <h3 style="margin:0">🇰🇷 한국어로 입력한 단어</h3>
          <span style="color:var(--ink2)"><?=$cnt_ko?>개</span>
        </div>

        <?php if(!$ko): ?>
          <div class="empty">이 날짜에는 한국어로 입력한 단어가 없습니다.</div>
        <?php else: foreach($ko as $r): ?>
          <div class="card" style="padding:16px;margin-bottom:12px">
            <div style="font-size:20px;font-weight:800">
              <?=h($r['word'])?>
              <?php if(!empty($r['sense'])): ?><span class="badge"><?=h($r['sense'])?></span><?php endif; ?>
            </div>
            <div style="margin-top:6px">
              <b>뜻:</b> <?=h($r['meaning'])?><?= $r['reading'] ? '（'.h($r['reading']).'）' : '' ?>
            </div>
            <?php if(!empty($r['example'])): ?>
              <div style="margin-top:6px"><b>예문:</b> <?=nl2br(h($r['example']))?></div>
            <?php endif; ?>
            <?php if(!empty($r['example_ko'])): ?>
              <div style="margin-top:6px"><b>예문 한국어:</b> <?=nl2br(h($r['example_ko']))?></div>
            <?php endif; ?>
            <div style="margin-top:6px;color:var(--ink2);font-size:12px">추가된 시간: <?=h($r['created_at'])?></div>
          </div>
        <?php endforeach; endif; ?>
      </div>

      <!-- 오른쪽: 일본어 입력 -->
      <div>
        <div class="col-head">
          <h3 style="margin:0">🇯🇵 일본어로 입력한 단어</h3>
          <span style="color:var(--ink2)"><?=$cnt_ja?>개</span>
        </div>

        <?php if(!$ja): ?>
          <div class="empty">이 날짜에는 일본어로 입력한 단어가 없습니다.</div>
        <?php else: foreach($ja as $r): ?>
          <div class="card" style="padding:16px;margin-bottom:12px">
            <div style="font-size:20px;font-weight:800">
              <?=h($r['word'])?>
              <?php if(!empty($r['sense'])): ?><span class="badge"><?=h($r['sense'])?></span><?php endif; ?>
            </div>
            <div style="margin-top:6px">
              <b>뜻:</b> <?=h($r['meaning'])?><?= $r['reading'] ? '（'.h($r['reading']).'）' : '' ?>
            </div>
            <?php if(!empty($r['example'])): ?>
              <div style="margin-top:6px"><b>예문:</b> <?=nl2br(h($r['example']))?></div>
            <?php endif; ?>
            <?php if(!empty($r['example_ko'])): ?>
              <div style="margin-top:6px"><b>예문 한국어:</b> <?=nl2br(h($r['example_ko']))?></div>
            <?php endif; ?>
            <div style="margin-top:6px;color:var(--ink2);font-size:12px">추가된 시간: <?=h($r['created_at'])?></div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
