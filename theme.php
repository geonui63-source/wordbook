<?php
/**
 * KotobaAI 간단 테마 시스템
 * - localStorage('kotoba_theme') = 'light' | 'dark'
 * - document.documentElement.dataset.theme 로 적용
 * - 모든 페이지에서 동일하게 작동하도록 통일 완료
 */

function theme_head(): string {
  return <<<HTML
  <style>
    /* 기본(다크) 테마 변수 */
    :root{
      --bg:#0b1020;
      --bg-2:#141a33;
      --card:#161b2e;
      --card-soft:rgba(255,255,255,.03);
      --input-bg:#0e1430;
      --ink:#e7ecff;
      --ink2:#aab2d8;
      --border:#242b4a;
      --acc:#6ea8ff;

      /* 차트/표 전용 */
      --chart-bar:#4b84ff;
      --chart-grid:rgba(255,255,255,.08);
      --chart-tooltip-bg:rgba(0,0,0,.85);
      --chart-tooltip-fg:#ffffff;
    }

    /* 라이트 테마 변수 오버라이드 */
    [data-theme="light"]{
      --bg:#f5f7fb;
      --bg-2:#e9eef8;
      --card:#ffffff;
      --card-soft:rgba(0,0,0,.035);
      --input-bg:#ffffff;
      --ink:#0e1220;
      --ink2:#4b5575;
      --border:#d9dfef;
      --acc:#2b74ff;

      --chart-bar:#2b74ff;
      --chart-grid:rgba(0,0,0,.08);
      --chart-tooltip-bg:rgba(255,255,255,.97);
      --chart-tooltip-fg:#0e1220;
    }

    /* 공통 UI 스킨 */
    body.page{
      margin:0;
      font-family:system-ui,-apple-system,Segoe UI,Roboto,'Noto Sans KR',sans-serif;
      background: linear-gradient(160deg, var(--bg), var(--bg-2));
      color:var(--ink);
    }
    .card{background:var(--card); border:1px solid var(--border); border-radius:16px;}
    .tile{background:var(--card); border:1px solid var(--border); border-radius:12px; padding:12px}
    .btn{background:var(--acc); color:#071226; border:1px solid transparent; border-radius:12px; padding:10px 14px; cursor:pointer}
    .btn.ghost{background:transparent; border-color:var(--border); color:var(--ink)}
    .input{background:var(--input-bg); color:var(--ink); border:1px solid var(--border); border-radius:12px; padding:10px 12px}
    .table{width:100%; border-collapse:collapse}
    .table th,.table td{border-top:1px solid var(--border); padding:10px 12px; color:var(--ink)}
    .kpi{font-size:28px; font-weight:800}
    .kpi-label{color:var(--ink2); font-size:13px}
  </style>

  <!-- ▼ 테마 초기 설정: key를 kotoba_theme 로 통일 -->
  <script>
    (function(){
      var saved = localStorage.getItem('kotoba_theme');   // ★ 통합 키
      if(!saved){
        saved = (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches)
          ? 'light' : 'dark';
      }
      document.documentElement.setAttribute('data-theme', saved);
    })();
  </script>
  HTML;
}

/**
 * 상단에 넣는 테마 토글 버튼
 */
function theme_toggle_button(): string {
  return <<<HTML
  <button type="button" class="btn ghost" id="__theme_toggle">...</button>
  <script>
    (function(){
      var btn = document.getElementById('__theme_toggle');

      function label(){
        var t = document.documentElement.getAttribute('data-theme') || 'dark';
        btn.textContent = (t === 'light') ? '🌙 야간 모드' : '☀️ 주간 모드';
      }

      btn.addEventListener('click', function(){
        var cur  = document.documentElement.getAttribute('data-theme') || 'dark';
        var next = (cur === 'light') ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', next);

        // ★ 여기서도 동일한 key로 저장
        localStorage.setItem('kotoba_theme', next);

        label();
      });

      label();
    })();
  </script>
  HTML;
}
?>
