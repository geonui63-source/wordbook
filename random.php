<?php
require_once __DIR__ . '/theme.php';
require_once __DIR__ . '/db.php';
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function norm($s){ return preg_replace('/\s+/u','', trim((string)$s)); }

$r = $conn->query("SELECT word,sense,meaning,reading,example,example_ko,created_at FROM words ORDER BY RAND() LIMIT 5");
$rows = $r->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8"><title>랜덤 학습 | KotobaAI</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<?= theme_head() ?>
<style>
  body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,'Noto Sans KR',sans-serif}
  .wrap{max-width:820px;margin:40px auto;padding:0 18px}
  .badge{display:inline-block;padding:2px 8px;border:1px solid var(--border);border-radius:999px;color:var(--ink2);font-size:11px;margin-left:6px}

  /* 처음엔 블러로 가리기 */
  .masked{ filter: blur(7px); opacity:.85; pointer-events:none; user-select:none; }
  .hint{ display:inline-block; color:var(--ink2); font-size:12px; margin-left:6px; }
  .btn.small{ padding:6px 10px; font-size:13px; border-radius:8px }
  .btn[disabled]{ background:var(--muted); color:var(--ink2); cursor:default; filter:none; transform:none; border:1px solid var(--border); }
  .tile .actions{ margin-top:8px; display:flex; gap:8px; flex-wrap:wrap }
  .header-actions{ display:flex; gap:8px; align-items:center; margin:-6px 0 10px auto; justify-content:flex-end }
</style>
</head>
<body class="page">
  <div class="wrap">
    <div class="nav"><a href="index.php" style="color:var(--acc)">← 🏠 메인</a></div>

    <div class="card" style="padding:18px">
      <div style="display:flex;align-items:center;gap:10px;justify-content:space-between">
        <h2 style="margin:0">랜덤 학습 (5개)</h2>
        <div class="header-actions">
          <button id="revealAll" class="btn small ghost">👁 전체 보기</button>
        </div>
      </div>

      <?php foreach($rows as $i => $row): ?>
        <?php
          $mid  = "m{$i}";     // 뜻 id
          $koid = "k{$i}";     // 예문 한국어 id
          $hasKo = !empty($row['example_ko']);
          $isDupe = $hasKo && norm($row['example_ko']) === norm($row['meaning']); // 중복 검사
        ?>
        <div class="tile" style="margin-bottom:10px">
          <div style="font-weight:800;font-size:18px">
            <?=h($row['word'])?>
            <?php if(!empty($row['sense'])): ?><span class="badge"><?=h($row['sense'])?></span><?php endif; ?>
          </div>

          <!-- 뜻 (가림) -->
          <div style="margin-top:6px">
            <b>뜻:</b>
            <span id="<?=$mid?>" class="masked" data-maskable="1">
              <?=h($row['meaning'])?><?= $row['reading']?'（'.h($row['reading']).'）':'' ?>
            </span>
            <span class="hint">(버튼을 눌러 보기)</span>
          </div>

          <!-- 일본어 예문(보여줌) -->
          <?php if(!empty($row['example'])): ?>
            <div style="margin-top:6px"><b>예문:</b> <?=nl2br(h($row['example']))?></div>
          <?php endif; ?>

          <!-- 예문 한국어 (가림) — 뜻과 동일하면 표시 생략 -->
          <?php if($hasKo && !$isDupe): ?>
            <div style="margin-top:6px">
              <b>예문 한국어:</b>
              <span id="<?=$koid?>" class="masked" data-maskable="1"><?=nl2br(h($row['example_ko']))?></span>
              <span class="hint">(버튼을 눌러 보기)</span>
            </div>
          <?php endif; ?>

          <!-- 열기 버튼(뜻 / 예문 한국어) -->
          <div class="actions">
            <button class="btn small reveal-btn"
                    data-target="<?=$mid?>"
                    data-label="뜻 열람">뜻 열람</button>
            <?php if($hasKo && !$isDupe): ?>
              <button class="btn small ghost reveal-btn"
                      data-target="<?=$koid?>"
                      data-label="예문 한국어 열람">예문 한국어 열람</button>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>

      <div style="display:flex;gap:8px">
        <a class="btn" href="random.php">🔄 다시 뽑기</a>
        <a class="btn ghost" href="index.php">🏠 메인으로</a>
      </div>
    </div>
  </div>

<script>
  // 현재 전체가 열람 상태인지 판단
  function isAllRevealed(){
    return document.querySelectorAll('[data-maskable].masked').length === 0;
  }
  // 전체 버튼 라벨 갱신
  function syncRevealAllButton(){
    const btn = document.getElementById('revealAll');
    if(isAllRevealed()){
      btn.textContent = '🙈 전체 가리기';
      btn.classList.remove('ghost');
    }else{
      btn.textContent = '👁 전체 보기';
      btn.classList.add('ghost');
    }
  }
  // 개별 버튼 라벨/상태 초기화
  function resetItemButtons(){
    document.querySelectorAll('.reveal-btn').forEach(b=>{
      b.textContent = b.getAttribute('data-label') || '열람';
      b.removeAttribute('disabled');
    });
  }
  // 개별 열람
  document.querySelectorAll('.reveal-btn').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const id = btn.getAttribute('data-target');
      const el = document.getElementById(id);
      if(!el) return;
      el.classList.remove('masked');
      btn.textContent = '열람 완료';
      btn.setAttribute('disabled','disabled');
      syncRevealAllButton();
    });
  });

  // 전체 보기/가리기 토글
  document.getElementById('revealAll').addEventListener('click', ()=>{
    const maskables = document.querySelectorAll('[data-maskable]');
    if(isAllRevealed()){
      // 전체 가리기
      maskables.forEach(el=>el.classList.add('masked'));
      resetItemButtons();
    }else{
      // 전체 보기
      maskables.forEach(el=>el.classList.remove('masked'));
      document.querySelectorAll('.reveal-btn').forEach(b=>{
        b.textContent='열람 완료';
        b.setAttribute('disabled','disabled');
      });
    }
    syncRevealAllButton();
  });

  // 초기 버튼 라벨 동기화
  syncRevealAllButton();
</script>
</body>
</html>
