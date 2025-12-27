<?php
require_once __DIR__ . '/theme.php';
require_once __DIR__ . '/db.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/* ─────────────────────────────────────────
   언어 판별 유틸
────────────────────────────────────────── */
function has_hangul($str){
  return preg_match('/[\x{AC00}-\x{D7A3}\x{1100}-\x{11FF}]/u', $str) === 1;
}
function has_japanese($str){
  // 한자 + 히라가나/가타카나
  return preg_match('/[\p{Han}\x{3040}-\x{309F}\x{30A0}-\x{30FF}]/u', $str) === 1;
}

/* ─────────────────────────────────────────
   상태 변수
────────────────────────────────────────── */
$error   = null;
$ok      = null;
$editRow = null;   // 수정 에러 시 다시 채워줄 데이터

/* ─────────────────────────────────────────
   액션 처리 (삭제/전체삭제/수동추가/수정)
────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action = $_POST['action'] ?? '';

  // 1) 개별 삭제
  if ($action === 'delete_one') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id>0) {
      $stmt = $conn->prepare('DELETE FROM words WHERE id=?');
      if ($stmt){
        $stmt->bind_param('i',$id);
        $stmt->execute();
        $stmt->close();
        $ok='삭제했습니다.';
      } else {
        $error = '삭제 준비 실패: '.$conn->error;
      }
    }
  }

  // 2) 전체 삭제
  if ($action === 'delete_all') {
    if (($_POST['confirm'] ?? '') === 'YES') {
      if ($conn->query('TRUNCATE TABLE words')) $ok='전체 삭제 완료!';
      else $error='전체 삭제 실패: '.$conn->error;
    } else {
      $error='전체 삭제가 취소되었습니다.';
    }
  }

  // 3) 수동 추가
  if ($action === 'manual_add') {
    $word       = trim($_POST['word'] ?? '');
    $meaning    = trim($_POST['meaning'] ?? '');
    $reading    = trim($_POST['reading'] ?? '');
    $example    = trim($_POST['example'] ?? '');
    $example_ko = trim($_POST['example_ko'] ?? '');
    $sense      = trim($_POST['sense'] ?? '');

    if ($word==='' || $meaning==='' || $example==='' || $example_ko==='') {
      $error='단어, 뜻, 예문, 예문 한국어는 필수입니다.';
    } else {
      try{
        $sql="INSERT INTO words (word, sense, meaning, reading, example, example_ko) VALUES (?,?,?,?,?,?)";
        $st=$conn->prepare($sql);
        if(!$st) throw new RuntimeException('DB 준비 실패: '.$conn->error);
        $st->bind_param('ssssss',$word,$sense,$meaning,$reading,$example,$example_ko);
        if(!$st->execute()) throw new RuntimeException('DB 저장 실패: '.$st->error);
        $st->close();
        $ok='수동으로 저장했습니다! 🎉';
        $_POST=[]; // 폼 값 초기화
      }catch(Throwable $e){ $error=$e->getMessage(); }
    }
  }

  // 4) 단일 항목 수정
  if ($action === 'update_one') {
    $id         = (int)($_POST['id'] ?? 0);
    $word       = trim($_POST['word'] ?? '');
    $sense      = trim($_POST['sense'] ?? '');
    $meaning    = trim($_POST['meaning'] ?? '');
    $reading    = trim($_POST['reading'] ?? '');
    $example    = trim($_POST['example'] ?? '');
    $example_ko = trim($_POST['example_ko'] ?? '');
    $curPage    = (int)($_POST['page'] ?? 1);
    $curQ       = trim($_POST['q'] ?? '');
    $scrollPos  = isset($_POST['scroll']) ? (int)$_POST['scroll'] : 0;

    if ($id<=0) {
      $error = '잘못된 단어 ID입니다.';
    } elseif ($word==='' || $meaning==='') {
      $error = '단어와 뜻은 필수입니다.';
    } else {
      $sql = "UPDATE words
                 SET word=?, sense=?, meaning=?, reading=?, example=?, example_ko=?
               WHERE id=?";
      $st = $conn->prepare($sql);
      if (!$st) {
        $error = '수정 준비 실패: '.$conn->error;
      } else {
        $st->bind_param('ssssssi',
          $word,$sense,$meaning,$reading,$example,$example_ko,$id
        );
        if ($st->execute()) {
          $st->close();
          $ok = '수정했습니다.';

          // 저장 성공 시: 스크롤 파라미터를 붙여서 리다이렉트 (앵커 X)
          $qs = [];
          if ($curQ !== '')   $qs[] = 'q='.urlencode($curQ);
          if ($curPage > 1)   $qs[] = 'page='.$curPage;
          if ($scrollPos > 0) $qs[] = 'scroll='.$scrollPos;
          $base = 'list.php';
          if ($qs) $base .= '?'.implode('&',$qs);
          header('Location: '.$base);
          exit;
        } else {
          $error = '수정 실패: '.$st->error;
          $st->close();
        }
      }
    }

    // 에러가 있으면 방금 입력값으로 해당 카드만 다시 채우기 + 편집 모드 유지
    if ($error) {
      $editRow = [
        'id'         => $id,
        'word'       => $word,
        'sense'      => $sense,
        'meaning'    => $meaning,
        'reading'    => $reading,
        'example'    => $example,
        'example_ko' => $example_ko,
      ];
    }
  }
}

/* ─────────────────────────────────────────
   검색 + 페이지네이션
────────────────────────────────────────── */
$q = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page-1)*$perPage;

if ($q!==''){
  $like = '%'.$conn->real_escape_string($q).'%';
  $totalRes = $conn->query("
    SELECT COUNT(*) AS c FROM words
    WHERE word LIKE '$like' OR sense LIKE '$like' OR meaning LIKE '$like'
       OR reading LIKE '$like' OR example LIKE '$like' OR example_ko LIKE '$like'
  ");
} else {
  $totalRes = $conn->query("SELECT COUNT(*) AS c FROM words");
}
$total = (int)($totalRes->fetch_assoc()['c'] ?? 0);

if ($q!==''){
  $stmt = $conn->prepare("
    SELECT id, word, sense, meaning, reading, example, example_ko, created_at
      FROM words
     WHERE word LIKE ? OR sense LIKE ? OR meaning LIKE ?
        OR reading LIKE ? OR example LIKE ? OR example_ko LIKE ?
     ORDER BY id DESC LIMIT ? OFFSET ?
  ");
  $likeParam = "%$q%";
  $stmt->bind_param('ssssssii',$likeParam,$likeParam,$likeParam,$likeParam,$likeParam,$likeParam,$perPage,$offset);
} else {
  $stmt = $conn->prepare("
    SELECT id, word, sense, meaning, reading, example, example_ko, created_at
      FROM words
     ORDER BY id DESC LIMIT ? OFFSET ?
  ");
  $stmt->bind_param('ii',$perPage,$offset);
}
$stmt->execute();
$res  = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$lastPage = max(1, (int)ceil($total / $perPage));

/* 2열 분리 */
$rows_ko=[]; $rows_jp=[];
foreach($rows as $r){
  $w=(string)$r['word'];
  if(has_hangul($w) && !has_japanese($w)) $rows_ko[]=$r;
  else $rows_jp[]=$r;
}

/* 수동 추가 폼 값 유지
   manual_add 때만 유지, 나머지 액션에서는 항상 비우기 */
$lastAction = $_POST['action'] ?? '';
if ($lastAction === 'manual_add') {
  $fv = [
    'word'       => $_POST['word']       ?? '',
    'meaning'    => $_POST['meaning']    ?? '',
    'reading'    => $_POST['reading']    ?? '',
    'example'    => $_POST['example']    ?? '',
    'example_ko' => $_POST['example_ko'] ?? '',
    'sense'      => $_POST['sense']      ?? '',
  ];
} else {
  $fv = [
    'word'       => '',
    'meaning'    => '',
    'reading'    => '',
    'example'    => '',
    'example_ko' => '',
    'sense'      => '',
  ];
}

// 수정 에러용 ID
$editingId = $editRow['id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>단어 목록 | KotobaAI</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?= theme_head() ?>
  <style>
    *{box-sizing:border-box}
    body{margin:0; font-family:system-ui, -apple-system, Segoe UI, Roboto, 'Noto Sans KR', sans-serif;}
    .page{ background:linear-gradient(160deg,var(--bg),var(--bg2)); color:var(--ink); min-height:100vh; }
    .container{ max-width:1100px; margin:40px auto; padding:0 18px; }

    .header{ display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; }
    .title{ font-size:28px; font-weight:800; margin:0; }
    .muted{ color:var(--ink2); font-size:13px; }

    .bar{ display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin:10px 0 14px; }
    .twoCol{ display:grid; grid-template-columns:1fr 1fr; gap:16px; }
    @media (max-width:900px){ .twoCol{ grid-template-columns:1fr; } }

    .badge{ display:inline-block; padding:2px 8px; border:1px solid var(--border); border-radius:999px; font-size:11px; color:var(--ink2); margin-left:6px; }
    .item{ padding:14px; border:1px solid var(--border); border-radius:12px; background:var(--card); box-shadow:var(--shadow); }
    .item .word{ font-weight:800; font-size:18px; }
    .item .meta{ color:var(--ink2); font-size:12px; margin-top:6px; }
    .empty{ padding:16px; border:1px dashed var(--border); border-radius:12px; background:var(--muted); color:var(--ink2); text-align:center; }

    details.manual{ margin:10px 0 0; }
    details.manual summary{ list-style:none; cursor:pointer; display:inline-flex; align-items:center; gap:8px; }
    details.manual summary::-webkit-details-marker{ display:none; }
    .grid2{ display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    @media (max-width:720px){ .grid2{ grid-template-columns:1fr; } }

    .pager{ display:flex; gap:8px; align-items:center; margin-top:14px; }
    .pill{ padding:8px 10px; border-radius:10px; border:1px solid var(--border); background:var(--card); color:var(--ink); }
    .pill.on{ background:var(--acc); color:white; border-color:transparent; font-weight:800; }

    .actions-row{margin-top:8px; display:flex; gap:8px; justify-content:flex-end;}

    /* 인라인 수정용 */
    .view-area{}
    .edit-area{display:none; margin-top:8px;}
    .item.editing .view-area{display:none;}
    .item.editing .edit-area{display:block;}
  </style>
</head>
<body class="page">
  <div class="container">

    <!-- 상단 -->
    <div class="header">
      <div>
        <a href="index.php" style="color:var(--acc)">← 메인</a>
        <h1 class="title" style="margin-top:6px">저장된 단어 <span class="muted">(총 <?=$total?>개)</span></h1>
      </div>
      <div class="bar">
        <!-- 전체 삭제 -->
        <form method="post" onsubmit="return confirm('정말 전체 삭제할까요? 이 작업은 되돌릴 수 없습니다.');">
          <input type="hidden" name="action" value="delete_all">
          <input type="hidden" name="confirm" value="YES">
          <button class="btn" style="background:#e2475e">전체 삭제</button>
        </form>
        <!-- 수동 추가 열기 -->
        <a class="btn ghost" href="#manual">수동 추가</a>
      </div>
    </div>

    <!-- 메시지 -->
    <?php if($error): ?><div class="tile" style="border-color:#e2475e; color:#e2475e; margin-bottom:10px;">⚠️ <?=h($error)?></div><?php endif; ?>
    <?php if($ok): ?><div class="tile" style="border-color:var(--ok); color:var(--ok); margin-bottom:10px;">✅ <?=$ok?></div><?php endif; ?>

    <!-- 검색창 -->
    <div class="card" style="padding:14px;">
      <form class="bar" method="get" style="margin:0">
        <input class="input" type="text" name="q" value="<?=h($q)?>" placeholder="단어/뜻/예문/라벨 검색" style="flex:1; min-width:220px">
        <button class="btn" type="submit">검색</button>
        <?php if ($q): ?><a class="btn ghost" href="list.php">초기화</a><?php endif; ?>
      </form>
    </div>

    <!-- 수동 추가 -->
    <div id="manual" class="card" style="padding:16px; margin-top:12px;">
      <details class="manual" open="<?= isset($_POST['action']) && $_POST['action']==='manual_add' ? 'open':'' ?>">
        <summary class="btn ghost">✚ 수동으로 단어 추가</summary>
        <form method="post" style="margin-top:14px;">
          <input type="hidden" name="action" value="manual_add">
          <div class="grid2">
            <div>
              <label class="muted">단어(원어)</label>
              <input class="input" name="word" value="<?=h($fv['word'])?>" placeholder="예) 運動 / 다리" required>
            </div>
            <div>
              <label class="muted">뜻(상대언어)</label>
              <input class="input" name="meaning" value="<?=h($fv['meaning'])?>" placeholder="예) 운동 / レッグ(脚)" required>
            </div>
            <div>
              <label class="muted">후리가나(선택)</label>
              <input class="input" name="reading" value="<?=h($fv['reading'])?>" placeholder="예) うんどう">
            </div>
            <div>
              <label class="muted">분류/라벨(선택)</label>
              <input class="input" name="sense" value="<?=h($fv['sense'])?>" placeholder="예) JLPT N2 / 신체 / 지명">
            </div>
          </div>
          <div style="margin-top:10px">
            <label class="muted">예문(일본어)</label>
            <textarea class="input" name="example" rows="3" placeholder="예) 運動は健康に良いです。" required><?=h($fv['example'])?></textarea>
          </div>
          <div style="margin-top:10px">
            <label class="muted">예문 한국어</label>
            <textarea class="input" name="example_ko" rows="3" placeholder="예) 운동은 건강에 좋습니다." required><?=h($fv['example_ko'])?></textarea>
          </div>
          <div style="margin-top:12px; display:flex; gap:8px; justify-content:flex-end;">
            <a class="btn ghost" href="#top">닫기</a>
            <button class="btn" type="submit">저장</button>
          </div>
        </form>
      </details>
    </div>

    <!-- 2열 리스트 -->
    <div class="twoCol" style="margin-top:16px">
      <!-- 왼쪽: 한국어 입력 -->
      <div>
        <div class="tile" style="margin-bottom:10px;">🇰🇷 한국어 입력</div>
        <?php if (!$rows_ko): ?>
          <div class="empty">이 페이지에 한국어 입력 항목이 없습니다.</div>
        <?php else: ?>
          <?php foreach ($rows_ko as $r): ?>
            <?php
              $isEditing = ($editingId && $editingId == $r['id']);
              $rowData = $isEditing && $editRow ? array_merge($r,$editRow) : $r;
            ?>
            <div class="item <?= $isEditing ? 'editing' : '' ?>" id="w<?=$r['id']?>">
              <!-- 보기 영역 -->
              <div class="view-area">
                <div class="word">
                  <?=h($rowData['word'])?>
                  <?php if(!empty($rowData['sense'])): ?><span class="badge"><?=h($rowData['sense'])?></span><?php endif; ?>
                </div>
                <div style="margin-top:4px"><b>뜻:</b> <?=h($rowData['meaning'])?><?= !empty($rowData['reading']) ? '（'.h($rowData['reading']).'）' : '' ?></div>
                <?php if(!empty($rowData['example'])): ?><div style="margin-top:4px"><b>예문:</b> <?=nl2br(h($rowData['example']))?></div><?php endif; ?>
                <?php if(!empty($rowData['example_ko'])): ?><div style="margin-top:4px"><b>예문 한국어:</b> <?=nl2br(h($rowData['example_ko']))?></div><?php endif; ?>
                <div class="meta">저장: <?=h($r['created_at'])?> · ID: <?= (int)$r['id']?></div>

                <div class="actions-row">
                  <button type="button" class="btn ghost edit-btn">수정</button>
                  <form method="post" onsubmit="return confirm('삭제할까요?');" style="margin:0">
                    <input type="hidden" name="action" value="delete_one">
                    <input type="hidden" name="id" value="<?=$r['id']?>">
                    <button class="btn" style="background:#e2475e">삭제</button>
                  </form>
                </div>
              </div>

              <!-- 수정 영역 -->
              <form class="edit-area" method="post">
                <input type="hidden" name="action" value="update_one">
                <input type="hidden" name="id" value="<?= (int)$rowData['id'] ?>">
                <input type="hidden" name="page" value="<?=$page?>">
                <?php if($q!==''): ?><input type="hidden" name="q" value="<?=h($q)?>"><?php endif; ?>
                <input type="hidden" name="scroll" value="">

                <div class="grid2">
                  <div>
                    <label class="muted">단어(원어)</label>
                    <input class="input" name="word" required value="<?=h($rowData['word'])?>">
                  </div>
                  <div>
                    <label class="muted">뜻</label>
                    <input class="input" name="meaning" required value="<?=h($rowData['meaning'])?>">
                  </div>
                  <div>
                    <label class="muted">후리가나(선택)</label>
                    <input class="input" name="reading" value="<?=h($rowData['reading'])?>">
                  </div>
                  <div>
                    <label class="muted">분류/라벨(선택)</label>
                    <input class="input" name="sense" value="<?=h($rowData['sense'] ?? '')?>">
                  </div>
                </div>

                <div style="margin-top:8px">
                  <label class="muted">예문(일본어)</label>
                  <textarea class="input" name="example" rows="2"><?=h($rowData['example'])?></textarea>
                </div>
                <div style="margin-top:8px">
                  <label class="muted">예문 한국어</label>
                  <textarea class="input" name="example_ko" rows="2"><?=h($rowData['example_ko'])?></textarea>
                </div>

                <div class="actions-row">
                  <button type="button" class="btn ghost edit-cancel-btn">취소</button>
                  <button class="btn" type="submit">저장</button>
                </div>
              </form>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- 오른쪽: 일본어 입력 -->
      <div>
        <div class="tile" style="margin-bottom:10px;">🇯🇵 일본어 입력(한자/가나)</div>
        <?php if (!$rows_jp): ?>
          <div class="empty">이 날짜에는 일본어로 입력한 단어가 없습니다.</div>
        <?php else: ?>
          <?php foreach ($rows_jp as $r): ?>
            <?php
              $isEditing = ($editingId && $editingId == $r['id']);
              $rowData = $isEditing && $editRow ? array_merge($r,$editRow) : $r;
            ?>
            <div class="item <?= $isEditing ? 'editing' : '' ?>" id="w<?=$r['id']?>">
              <div class="view-area">
                <div class="word">
                  <?=h($rowData['word'])?>
                  <?php if(!empty($rowData['sense'])): ?><span class="badge"><?=h($rowData['sense'])?></span><?php endif; ?>
                </div>
                <div style="margin-top:4px"><b>뜻:</b> <?=h($rowData['meaning'])?><?= !empty($rowData['reading']) ? '（'.h($rowData['reading']).'）' : '' ?></div>
                <?php if(!empty($rowData['example'])): ?><div style="margin-top:4px"><b>예문:</b> <?=nl2br(h($rowData['example']))?></div><?php endif; ?>
                <?php if(!empty($rowData['example_ko'])): ?><div style="margin-top:4px"><b>예문 한국어:</b> <?=nl2br(h($rowData['example_ko']))?></div><?php endif; ?>
                <div class="meta">저장: <?=h($r['created_at'])?> · ID: <?= (int)$r['id']?></div>

                <div class="actions-row">
                  <button type="button" class="btn ghost edit-btn">수정</button>
                  <form method="post" onsubmit="return confirm('삭제할까요?');" style="margin:0">
                    <input type="hidden" name="action" value="delete_one">
                    <input type="hidden" name="id" value="<?=$r['id']?>">
                    <button class="btn" style="background:#e2475e">삭제</button>
                  </form>
                </div>
              </div>

              <form class="edit-area" method="post">
                <input type="hidden" name="action" value="update_one">
                <input type="hidden" name="id" value="<?= (int)$rowData['id'] ?>">
                <input type="hidden" name="page" value="<?=$page?>">
                <?php if($q!==''): ?><input type="hidden" name="q" value="<?=h($q)?>"><?php endif; ?>
                <input type="hidden" name="scroll" value="">

                <div class="grid2">
                  <div>
                    <label class="muted">단어(원어)</label>
                    <input class="input" name="word" required value="<?=h($rowData['word'])?>">
                  </div>
                  <div>
                    <label class="muted">뜻</label>
                    <input class="input" name="meaning" required value="<?=h($rowData['meaning'])?>">
                  </div>
                  <div>
                    <label class="muted">후리가나(선택)</label>
                    <input class="input" name="reading" value="<?=h($rowData['reading'])?>">
                  </div>
                  <div>
                    <label class="muted">분류/라벨(선택)</label>
                    <input class="input" name="sense" value="<?=h($rowData['sense'] ?? '')?>">
                  </div>
                </div>

                <div style="margin-top:8px">
                  <label class="muted">예문(일본어)</label>
                  <textarea class="input" name="example" rows="2"><?=h($rowData['example'])?></textarea>
                </div>
                <div style="margin-top:8px">
                  <label class="muted">예문 한국어</label>
                  <textarea class="input" name="example_ko" rows="2"><?=h($rowData['example_ko'])?></textarea>
                </div>

                <div class="actions-row">
                  <button type="button" class="btn ghost edit-cancel-btn">취소</button>
                  <button class="btn" type="submit">저장</button>
                </div>
              </form>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- 페이지네이션 -->
    <?php if ($lastPage>1): ?>
      <div class="pager">
        <?php
          $mk=function($p,$label,$on=false) use($q){
            $qs = $q ? ('&q='.urlencode($q)) : '';
            $href = 'list.php?page='.$p.$qs;
            echo '<a class="pill'.($on?' on':'').'" href="'.h($href).'">'.h($label).'</a>';
          };
          $mk(max(1,$page-1),'이전');
          for($i=max(1,$page-2); $i<=min($lastPage,$page+2); $i++){
            $mk($i,(string)$i,$i===$page);
          }
          $mk(min($lastPage,$page+1),'다음');
        ?>
      </div>
    <?php endif; ?>

  </div>

<script>
// 수정 버튼: 카드만 editing 모드로 (페이지 이동 없음)
document.querySelectorAll('.item .edit-btn').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    const card = btn.closest('.item');
    if(!card) return;
    card.classList.add('editing');
    const input = card.querySelector('.edit-area input[name="word"]');
    if(input) input.focus();
  });
});

// 취소 버튼: editing 모드 해제
document.querySelectorAll('.item .edit-cancel-btn').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    const card = btn.closest('.item');
    if(!card) return;
    card.classList.remove('editing');
  });
});

// 저장 직전에 현재 스크롤 위치를 hidden 필드에 넣기
document.querySelectorAll('.item .edit-area').forEach(form=>{
  form.addEventListener('submit', ()=>{
    const hidden = form.querySelector('input[name="scroll"]');
    if(hidden){
      hidden.value = window.scrollY || window.pageYOffset || 0;
    }
  });
});

// 페이지 로드 시 ?scroll= 값이 있으면 그 위치로 이동
(function(){
  const params = new URLSearchParams(window.location.search);
  const s = params.get('scroll');
  if(s !== null){
    const y = parseInt(s,10);
    if(!isNaN(y)){
      window.scrollTo(0, y);
    }
  }
})();
</script>

</body>
</html>
