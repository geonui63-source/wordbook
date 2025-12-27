<?php
require_once __DIR__ . '/theme.php';

/* ===== 배너 이미지 경로 ===== */
$heroJpg  = 'uploads/hero.jpg';
$heroPng  = 'uploads/hero.png';
$heroPath = file_exists($heroJpg) ? $heroJpg : (file_exists($heroPng) ? $heroPng : null);

$heroDayPath   = file_exists('uploads/hero_day.jpg')   ? 'uploads/hero_day.jpg'   : null;
$heroNightPath = file_exists('uploads/hero_night.jpg') ? 'uploads/hero_night.jpg' : null;

$initialHero = $heroPath ?? $heroDayPath ?? $heroNightPath;

$heroDayAttr   = $heroDayPath   ?? $initialHero;
$heroNightAttr = $heroNightPath ?? $initialHero;
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>KotobaAI | 내 단어장</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?= theme_head() ?>
  <style>
    body{
      margin:0;
      font-family:system-ui,-apple-system,Segoe UI,Roboto,'Noto Sans KR',sans-serif;
      background:var(--bg); color:var(--ink);
    }
    .container{ max-width:1100px; margin:0 auto; padding:20px; }

    header{ display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; }
    .logo{ font-weight:800; font-size:22px; }

    /* ===== 히어로 ===== */
    .hero{
      position:relative; overflow:hidden; border-radius:24px;
      background:linear-gradient(120deg,#fff3d1,#e8f0ff);
      box-shadow:var(--shadow); min-height:300px; display:flex; align-items:center;
    }
    [data-theme="dark"] .hero{ background:linear-gradient(120deg,#1b2336,#0f172a); }
    .hero img{
      position:absolute; inset:0; width:100%; height:100%;
      object-fit:cover; filter:saturate(1.05);
      opacity:1; transition:opacity .35s ease;
    }
    .hero-mask{
      position:absolute; inset:0; border-radius:24px;
      background:linear-gradient(to bottom,var(--mask-top),var(--mask-bottom));
      display:<?php echo $initialHero ? 'block' : 'none'; ?>;
    }
    .hero .hero-action{ position:absolute; top:12px; right:12px; z-index:5; }
    .btn.small{
      padding:8px 12px; font-size:13px; border-radius:999px;
      background:var(--btn-ghost-bg); color:var(--btn-ghost-text);
      border:1px solid var(--card-border); cursor:pointer; box-shadow:var(--shadow);
    }

    /* ===== 카드 ===== */
    .grid{ display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:16px; margin-top:24px; }
    .card{ background:var(--card); border:1px solid var(--border); border-radius:16px; padding:18px; box-shadow:var(--shadow); }
    .card h3{ margin:0 0 6px; font-size:18px; }
    .btn.primary{ background:#4b8df8; color:#fff; padding:8px 16px; border:none; border-radius:8px; cursor:pointer; margin-top:10px; }
    .btn.primary:hover{ opacity:.9; }

    /* ===== 업로드 모달 ===== */
    .drop{ position:fixed; inset:0; display:none; place-items:center; background:rgba(0,0,0,.35); z-index:50; }
    .drop-card{ background:var(--card); width:min(720px,94vw); border:1px solid var(--card-border); border-radius:18px; box-shadow:var(--shadow); }
    .tabs{ display:flex; gap:6px; padding:12px 12px 0; }
    .tab-btn{ flex:1; border:1px solid var(--card-border); background:var(--btn-ghost-bg); color:var(--btn-ghost-text); border-radius:10px; padding:10px; font-weight:700; cursor:pointer; }
    .tab-btn.on{ background:var(--acc); color:#fff; border-color:transparent; }
    .pane{ display:none; padding:12px; border-top:1px solid var(--card-border); }
    .pane.on{ display:block; }
    .uploader{ border:2px dashed #5a79b4; border-radius:14px; padding:14px; display:grid; grid-template-columns:1fr 260px; gap:12px; align-items:center; }
    .uploader .preview{ background:var(--muted); border:1px solid var(--border); border-radius:12px; padding:10px; display:flex; align-items:center; justify-content:center; height:160px; }
    .uploader .preview img{ max-width:100%; max-height:100%; border-radius:10px; }
    .uploader .controls{ display:flex; gap:8px; flex-wrap:wrap; }
    .uploader .meta{ font-size:12px; color:var(--ink2); margin-top:6px; }
    .row{ display:flex; justify-content:flex-end; gap:8px; padding:0 12px 12px; }
  </style>
</head>
<body>
  <div class="container">
    <header>
      <div>
        <span class="logo">📘 내 단어장</span><br>
        <small>단어 입력 → 자동 번역/예문 생성 → 저장 → 복습</small>
      </div>
      <div style="display:flex; gap:8px; align-items:center;">
        <span style="font-size:13px;">KotobaAI</span>
        <button id="modeBtn" class="btn small">🌙 야간 모드</button>
      </div>
    </header>

    <!-- ===== 히어로 ===== -->
    <section class="hero" aria-label="배너">
      <?php if ($initialHero): ?>
        <img
          id="heroImg"
          src="<?php echo $initialHero . '?v=' . filemtime(str_replace('?v=','',$initialHero)); ?>"
          data-hero-day="<?php   echo $heroDayAttr;   ?>"
          data-hero-night="<?php echo $heroNightAttr; ?>"
          alt="배너 이미지">
        <div class="hero-mask" aria-hidden="true"></div>
      <?php endif; ?>
      <div class="hero-action">
        <button class="btn small" id="openUpload">사진 바꾸기</button>
      </div>
    </section>

    <!-- ===== 메뉴 ===== -->
    <div class="grid">
      <div class="card"><h3>단어 추가</h3><p>자동 번역, 히라가나, 예문까지 한 번에 저장.</p><a href="add.php"><button class="btn.primary btn primary">시작하기</button></a></div>
      <div class="card"><h3>단어 목록</h3><p>검색/분류/수동추가/삭제/페이지네이션.</p><a href="list.php"><button class="btn primary">보러가기</button></a></div>
      <div class="card"><h3>퀴즈 모드</h3><p>큰 폰트로 집중: 뜻 맞히기.</p><a href="quiz.php"><button class="btn primary">도전하기</button></a></div>
      <div class="card"><h3>랜덤 학습</h3><p>무작위 5개로 가볍게 복습.</p><a href="random.php"><button class="btn primary">시작하기</button></a></div>
      <div class="card"><h3>오늘의 단어</h3><p>선택 날짜 카드 뷰.</p><a href="today.php"><button class="btn primary">보러가기</button></a></div>
      <div class="card"><h3>통계</h3><p>일/주/월 단위 시각화.</p><a href="stats.php"><button class="btn primary">열어보기</button></a></div>
      <div class="card"><h3>JLPT 단어 보기</h3><p>레벨별 단어 한 개씩 학습.</p><a href="jlpt.php"><button class="btn primary">시작하기</button></a></div>
      <div class="card"><h3>손글씨 한자 인식</h3><p>마우스로 그리고 인식해서 추가.</p><a href="kanji_draw.php"><button class="btn primary">그리러 가기</button></a></div>
    </div>
  </div>

  <!-- ===== 업로드 모달 (주간/야간 탭) ===== -->
  <div class="drop" id="drop">
    <div class="drop-card">
      <div class="tabs">
        <button class="tab-btn on" data-tab="day">☀️ 주간용 배너</button>
        <button class="tab-btn" data-tab="night">🌙 야간용 배너</button>
      </div>

      <!-- Day Pane -->
      <div class="pane on" id="pane-day">
        <form action="upload_hero.php" method="post" enctype="multipart/form-data">
          <input type="hidden" name="target" value="day">
          <div class="uploader">
            <div>
              <div class="controls">
                <input type="file" name="hero" id="fileDay" accept="image/*" hidden>
                <button type="button" class="btn small" onclick="document.getElementById('fileDay').click()">파일 선택</button>
                <button type="submit" class="btn small">저장</button>
                <a class="btn small" target="_blank" href="<?php echo $heroDayAttr ?: '#'; ?>">현재 보기</a>
              </div>
              <div class="meta">권장: JPG/PNG, 가로 1792px 이상, 세로 256~384px 권장</div>
            </div>
            <div class="preview"><img id="previewDay" alt="미리보기"></div>
          </div>
        </form>
      </div>

      <!-- Night Pane -->
      <div class="pane" id="pane-night">
        <form action="upload_hero.php" method="post" enctype="multipart/form-data">
          <input type="hidden" name="target" value="night">
          <div class="uploader">
            <div>
              <div class="controls">
                <input type="file" name="hero" id="fileNight" accept="image/*" hidden>
                <button type="button" class="btn small" onclick="document.getElementById('fileNight').click()">파일 선택</button>
                <button type="submit" class="btn small">저장</button>
                <a class="btn small" target="_blank" href="<?php echo $heroNightAttr ?: '#'; ?>">현재 보기</a>
              </div>
              <div class="meta">권장: JPG/PNG, 가로 1792px 이상, 세로 256~384px 권장</div>
            </div>
            <div class="preview"><img id="previewNight" alt="미리보기"></div>
          </div>
        </form>
      </div>

      <div class="row"><button class="btn small" id="closeDrop">닫기</button></div>
    </div>
  </div>

  <script>
  /* ===== 테마 util ===== */
  function getTheme(){ return document.documentElement.getAttribute('data-theme') || 'light'; }
  function setTheme(t){ document.documentElement.setAttribute('data-theme', t); localStorage.setItem('kotoba_theme', t); }

  /* ===== 배너 스왑 ===== */
  (function(){
    const img = document.getElementById('heroImg');
    if(!img) return;
    const daySrc   = img.getAttribute('data-hero-day');
    const nightSrc = img.getAttribute('data-hero-night');

    function preload(src){
      return new Promise(r=>{
        if(!src) return r(false);
        const i=new Image();
        i.onload=()=>r(true); i.onerror=()=>r(false);
        i.src = src + (src.includes('?')?'&':'?') + 'cache=' + Date.now();
      });
    }
    async function swapHero(target){
      if(!target) return;
      if(img.dataset.current === target) return;
      const ok = await preload(target);
      const next = ok ? target : (img.dataset.current || img.src);
      if(img.dataset.current === next) return;
      img.style.opacity = 0;
      setTimeout(()=>{
        img.src = next + (next.includes('?')?'&':'?') + 't=' + Date.now();
        img.dataset.current = next;
        requestAnimationFrame(()=> img.style.opacity = 1);
      },120);
    }

    // 초기
    (function boot(){
      let saved = localStorage.getItem('kotoba_theme');
      if(!saved){
        saved = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
      }
      setTheme(saved);
      swapHero(saved==='dark'? nightSrc : daySrc);
    })();

    // 토글
    const modeBtn = document.getElementById('modeBtn');
    function updateBtn(){ modeBtn.textContent = (getTheme()==='dark') ? '☀️ 주간 모드' : '🌙 야간 모드'; }
    updateBtn();
    modeBtn.addEventListener('click', async ()=>{
      const next = (getTheme()==='dark') ? 'light':'dark';
      setTheme(next); updateBtn(); await swapHero(next==='dark'?nightSrc:daySrc);
    });

    // 시스템 테마 변경(저장값 없는 경우만)
    if(window.matchMedia){
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', async e=>{
        if(localStorage.getItem('kotoba_theme')) return;
        const next = e.matches ? 'dark':'light';
        setTheme(next); updateBtn(); await swapHero(next==='dark'?nightSrc:daySrc);
      });
    }
  })();

  /* ===== 업로드 모달 / 탭 / 미리보기 ===== */
  const drop = document.getElementById('drop');
  document.getElementById('openUpload').addEventListener('click', ()=> drop.style.display='grid');
  document.getElementById('closeDrop').addEventListener('click', ()=> drop.style.display='none');
  drop.addEventListener('click', e=>{ if(e.target===drop) drop.style.display='none'; });

  document.querySelectorAll('.tab-btn').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('on'));
      document.querySelectorAll('.pane').forEach(p=>p.classList.remove('on'));
      btn.classList.add('on');
      document.getElementById('pane-'+btn.dataset.tab).classList.add('on');
    });
  });

  const fileDay   = document.getElementById('fileDay');
  const fileNight = document.getElementById('fileNight');
  const prevDay   = document.getElementById('previewDay');
  const prevNight = document.getElementById('previewNight');

  function showPreview(input, imgEl){
    const f = input.files && input.files[0];
    if(!f) return;
    if(!f.type.startsWith('image/')) return alert('이미지 파일만 가능합니다.');
    const r = new FileReader();
    r.onload = e => imgEl.src = e.target.result;
    r.readAsDataURL(f);
  }
  fileDay && fileDay.addEventListener('change', ()=> showPreview(fileDay, prevDay));
  fileNight && fileNight.addEventListener('change', ()=> showPreview(fileNight, prevNight));
  </script>
</body>
</html>
