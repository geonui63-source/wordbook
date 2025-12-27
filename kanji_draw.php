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

// 기존 코드가 $OPENAI_API_KEY 변수를 쓰므로 호환용 변수 생성
$OPENAI_API_KEY = (defined('OPENAI_API_KEY') && OPENAI_API_KEY !== 'YOUR_OPENAI_API_KEY_HERE')
    ? OPENAI_API_KEY
    : '';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$error=null; $recognized='';

if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='recognize'){
  $png = $_POST['img'] ?? '';
  if(!$png){
    $error='이미지가 비었습니다.';
  } else {
    try{
      // OpenAI 비전 호출
      $messages = [
        ['role'=>'system','content'=>'You are a kanji handwriting recognizer. Return ONLY the recognized Japanese text (no explanations).'],
        ['role'=>'user','content'=>[
          ['type'=>'text','text'=>'Recognize this handwritten Japanese (kanji/kana). Return plain text only.'],
          ['type'=>'image_url','image_url'=>['url'=>$png]],
        ]],
      ];
      $ch=curl_init('https://api.openai.com/v1/chat/completions');
      curl_setopt_array($ch,[
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$OPENAI_API_KEY],
        CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>json_encode(['model'=>'gpt-4o-mini','messages'=>$messages,'temperature'=>0],JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_TIMEOUT=>25
      ]);
      $r=curl_exec($ch);
      if($r===false){ $e=curl_error($ch); curl_close($ch); throw new RuntimeException('OpenAI 연결 실패: '.$e); }
      $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
      if($code<200||$code>=300) throw new RuntimeException("OpenAI 오류(HTTP $code): $r");
      $d=json_decode($r,true);
      $recognized=trim($d['choices'][0]['message']['content']??'');
      if($recognized==='') $error='인식 결과가 비었습니다.';
    }catch(Throwable $e){
      $error=$e->getMessage();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8"><title>손글씨 한자 인식 | KotobaAI</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<?= theme_head() ?>
<style>
  body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,'Noto Sans KR',sans-serif}
  .wrap{max-width:980px;margin:40px auto;padding:0 18px}

  /* 패드 배경은 테마와 동기화 */
  .pad{
    width:100%;height:360px;border:1px solid var(--border);border-radius:14px;
    background:#ffffff; /* 라이트 모드 */
    box-shadow:var(--shadow);position:relative
  }
  [data-theme="dark"] .pad{ background:#0b1220; } /* 다크 모드 */

  .tools{display:flex;gap:8px;margin:10px 0}
  .res{min-height:48px}
</style>
</head>
<body class="page">
  <div class="wrap">
    <div class="nav"><a href="index.php" style="color:var(--acc)">← 🏠 메인</a> &nbsp;|&nbsp; <a href="add.php" style="color:var(--acc)">단어 추가</a></div>
    <div class="card" style="padding:18px">
      <h2 style="margin:0 0 10px">✍️ 손글씨 한자 인식 (마우스/터치)</h2>
      <p style="color:var(--ink2);margin:0 0 10px">모르는 일본어 한자를 그려서 인식하세요. 인식된 텍스트는 단어 추가로 보낼 수 있어요. (자동 검색 X, 입력창에만 채워짐)</p>

      <?php if($error): ?>
        <div class="tile" style="border-color:#e2475e;color:#e2475e;margin-bottom:10px">⚠️ <?=h($error)?></div>
      <?php endif; ?>

      <div class="pad">
        <canvas id="cv" width="900" height="360" style="width:100%;height:100%;border-radius:14px"></canvas>
      </div>

      <div class="tools">
        <button class="btn" id="btnClear" style="background:#e2475e">지우기</button>
        <button class="btn ghost" id="btnUndo">되돌리기</button>
        <button class="btn" id="btnThick">굵게</button>
        <button class="btn" id="btnThin">얇게</button>
        <form method="post" style="margin-left:auto;display:flex;gap:8px;align-items:center">
          <input type="hidden" name="action" value="recognize">
          <input type="hidden" name="img" id="imgField">
          <button class="btn" id="btnSend" type="submit">🔍 인식하기</button>
        </form>
      </div>

      <div class="tile res">
        <?php if($recognized): ?>
          <div><b>인식 결과:</b> <span id="outText"><?=h($recognized)?></span></div>
          <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">
            <!-- ✅ add.php?word=... 로 이동만 (자동 검색 X) -->
            <a class="btn" href="#" id="btnToAdd">단어 추가로 보내기</a>
            <button class="btn ghost" id="btnCopy" type="button">복사</button>
          </div>
        <?php else: ?>
          여기에 인식 결과가 표시됩니다.
        <?php endif; ?>
      </div>

      <div class="tile" style="margin-top:10px;color:var(--ink2)">
        팁: 획은 천천히 그리면 인식률이 좋아집니다. 여러 글자도 가능합니다.
      </div>
    </div>
  </div>

<script>
const cv=document.getElementById('cv'), ctx=cv.getContext('2d');
let drawing=false, paths=[], cur=[], lw=8;
const DPR=window.devicePixelRatio||1;

function getThemeColors(){
  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  return {
    bg:  isDark ? '#0b1220' : '#ffffff', // 배경
    ink: isDark ? '#ffffff' : '#111111'  // 선색
  };
}

function resize(){
  const r=cv.getBoundingClientRect();
  cv.width=r.width*DPR; cv.height=r.height*DPR;
  ctx.setTransform(DPR,0,0,DPR,0,0);
  redraw();
}

function redraw(){
  const {bg, ink} = getThemeColors();
  ctx.globalCompositeOperation='source-over';
  ctx.fillStyle=bg;
  ctx.fillRect(0,0,cv.width,cv.height);

  ctx.lineCap='round'; ctx.lineJoin='round';
  ctx.strokeStyle=ink;

  for(const p of paths){
    ctx.lineWidth=p.w;
    ctx.beginPath();
    for(let i=0;i<p.pts.length;i++){
      const [x,y]=p.pts[i];
      i?ctx.lineTo(x,y):ctx.moveTo(x,y);
    }
    ctx.stroke();
  }
  if(cur.length){
    ctx.lineWidth=lw;
    ctx.beginPath();
    for(let i=0;i<cur.length;i++){
      const [x,y]=cur[i];
      i?ctx.lineTo(x,y):ctx.moveTo(x,y);
    }
    ctx.stroke();
  }
}

function pos(e){
  const r=cv.getBoundingClientRect();
  const x=(e.touches?e.touches[0].clientX:e.clientX)-r.left;
  const y=(e.touches?e.touches[0].clientY:e.clientY)-r.top;
  return [x,y];
}

cv.addEventListener('mousedown',e=>{drawing=true;cur=[pos(e)];redraw()});
cv.addEventListener('mousemove',e=>{if(!drawing)return;cur.push(pos(e));redraw()});
cv.addEventListener('mouseup',()=>{if(cur.length){paths.push({w:lw,pts:cur});cur=[];redraw()} drawing=false});
cv.addEventListener('mouseleave',()=>{if(drawing){paths.push({w:lw,pts:cur});cur=[];redraw()} drawing=false});

cv.addEventListener('touchstart',e=>{drawing=true;cur=[pos(e)];e.preventDefault();redraw()},{passive:false});
cv.addEventListener('touchmove',e=>{if(!drawing)return;cur.push(pos(e));e.preventDefault();redraw()},{passive:false});
cv.addEventListener('touchend',()=>{if(cur.length){paths.push({w:lw,pts:cur});cur=[];redraw()} drawing=false});

window.addEventListener('resize',resize);
resize();

document.getElementById('btnClear').onclick=()=>{paths=[];cur=[];redraw()};
document.getElementById('btnUndo').onclick=()=>{paths.pop();redraw()};
document.getElementById('btnThick').onclick=()=>{lw=Math.min(20,lw+2)};
document.getElementById('btnThin').onclick=()=>{lw=Math.max(2,lw-2)};
document.getElementById('btnSend').onclick=()=>{document.getElementById('imgField').value=cv.toDataURL('image/png')};

/* 테마 변경 시 재렌더링 */
new MutationObserver(() => redraw()).observe(document.documentElement,{attributes:true,attributeFilter:['data-theme']});

/* 결과 버튼 동작 */
const outEl = document.getElementById('outText');
const toAddBtn = document.getElementById('btnToAdd');
const copyBtn  = document.getElementById('btnCopy');

if (toAddBtn && outEl) {
  toAddBtn.addEventListener('click', (e)=>{
    e.preventDefault();
    const w = outEl.textContent.trim();
    // ✅ add.php로 단순 이동 + 입력창에만 채우기 (자동 검색 X)
    const url = 'add.php?word=' + encodeURIComponent(w);
    window.location.href = url;
  });
}

if (copyBtn && outEl) {
  copyBtn.addEventListener('click', ()=>{
    navigator.clipboard.writeText(outEl.textContent.trim());
  });
}

// Add 페이지 프리필 관련 과거 로컬스토리지는 사용하지 않음(파라미터 방식 채택)
</script>
</body>
</html>
