<?php
require_once __DIR__ . '/theme.php';
require_once __DIR__ . '/db.php';
function h($s){ return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$mode  = $_GET['mode']  ?? 'week'; // day|week|month
$start = $_GET['start'] ?? date('Y-m-d', strtotime('-30 days'));
$end   = $_GET['end']   ?? date('Y-m-d');

$periods=[]; $labels=[]; $counts=[];
$startDt=new DateTime($start); $endDt=new DateTime($end); $endDt->setTime(23,59,59);

if($mode==='day'){
  $cur=clone $startDt;
  while($cur <= $endDt){
    $d=$cur->format('Y-m-d');
    $periods[]=[$d,$d];
    $labels[]=$d;
    $cur->modify('+1 day');
  }
}elseif($mode==='week'){
  $cur=clone $startDt; $cur->modify('monday this week');
  while($cur <= $endDt){
    $wStart=max($cur,$startDt);
    $wEnd  =min((clone $cur)->modify('sunday this week'),$endDt);
    $periods[]=[ $wStart->format('Y-m-d'), $wEnd->format('Y-m-d') ];
    $labels[] =  $wStart->format('Y-m-d').' ~ '.$wEnd->format('Y-m-d');
    $cur->modify('+1 week');
  }
}else{ // month
  $cur=new DateTime($startDt->format('Y-m-01'));
  while($cur <= $endDt){
    $mStart=max($cur,$startDt);
    $mEnd  =min((clone $cur)->modify('last day of this month'),$endDt);
    $periods[]=[ $mStart->format('Y-m-d'), $mEnd->format('Y-m-d') ];
    $labels[] =  $cur->format('Y-m');
    $cur->modify('first day of next month');
  }
}

$totalWords=0;
foreach($periods as [$s,$e]){
  $st=$conn->prepare("SELECT COUNT(*) AS c FROM words WHERE DATE(created_at) BETWEEN ? AND ?");
  $st->bind_param('ss',$s,$e);
  $st->execute();
  $c=(int)($st->get_result()->fetch_assoc()['c']??0);
  $st->close();
  $counts[]=$c;
  $totalWords+=$c;
}
$bucketCount=count($counts);
$avg=$bucketCount?round($totalWords/$bucketCount,2):0;
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8"><title>학습 통계 | KotobaAI</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<?= theme_head() ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
<style>
  body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,'Noto Sans KR',sans-serif}
  .wrap{max-width:1100px;margin:40px auto;padding:0 18px}

  .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;margin:14px 0}
  .pill{padding:8px 10px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--ink);text-decoration:none}
  .pill.on{background:var(--acc);color:#fff;border-color:transparent;font-weight:800}

  /* 보기 단위 줄 */
  .controls{display:flex;flex-wrap:wrap;gap:12px;margin-bottom:10px}
  .controls .unit{
    flex: 1 1 100%;
    display:flex; align-items:center; gap:8px; margin-bottom:6px;
  }
  .controls .unit .label{
    color:var(--ink2); font-size:13px; font-weight:600; line-height:32px; margin-right:4px;
  }

  /* ✅ 날짜 입력: 달력 아이콘 여백 보정 */
  .input[type="date"]{
    padding-right: 40px;        /* 아이콘이 오른쪽에 딱 붙지 않게 패딩 추가 */
  }
  /* 크롬/엣지/사파리 등 웹킷 브라우저에서 달력 아이콘 오른쪽 간격 살짝 확보 */
  .input[type="date"]::-webkit-calendar-picker-indicator{
    margin-right: 6px;          /* 아이콘을 안쪽으로 끌어와 여백 확보 */
    cursor: pointer;
  }

  /* 테이블 */
  .table{width:100%;border-collapse:separate;border-spacing:0;font-variant-numeric:tabular-nums}
  .table th,.table td{padding:12px 14px;border-bottom:1px solid var(--border)}
  .table thead th{color:var(--ink2);font-weight:700}
  .table th:nth-child(2), .table td.num{
    text-align:right; padding-right:22px; /* 숫자 우측 여백 */
  }
</style>
</head>
<body class="page">
  <div class="wrap">
    <div class="nav"><a href="index.php" style="color:var(--acc)">← 🏠 메인</a></div>

    <div class="card" style="padding:18px">
      <h2 style="margin:0 0 12px">📊 학습 통계 (선택 보기)</h2>

      <form class="controls" method="get">
        <div class="unit">
          <span class="label">보기 단위</span>
          <?php
            $mk=function($m,$label) use($mode,$start,$end){
              $on=$mode===$m?' on':'';
              echo '<a class="pill'.$on.'" href="?mode='.$m.'&start='.h($start).'&end='.h($end).'">'.h($label).'</a>';
            };
            $mk('day','일별'); $mk('week','주별'); $mk('month','월별');
          ?>
        </div>

        <div>
          <label class="label">시작일</label><br>
          <input class="input" type="date" name="start" value="<?=h($start)?>">
        </div>
        <div>
          <label class="label">종료일</label><br>
          <input class="input" type="date" name="end" value="<?=h($end)?>">
        </div>
        <div>
          <label class="label">&nbsp;</label><br>
          <button class="btn">적용</button>
        </div>
      </form>

      <div class="grid">
        <div class="tile"><div class="kpi"><?=$bucketCount?></div><div class="kpi-label">선택 구간 수</div></div>
        <div class="tile"><div class="kpi"><?=$totalWords?></div><div class="kpi-label">선택 구간 총 추가 단어</div></div>
        <div class="tile"><div class="kpi"><?=$avg?></div><div class="kpi-label">평균 (단위당)</div></div>
      </div>

      <div class="card" style="padding:18px">
        <canvas id="chart" height="160"></canvas>
      </div>

      <div class="card" style="padding:0;overflow:auto">
        <table class="table">
          <thead>
            <tr>
              <th><?=($mode==='day'?'날짜':($mode==='week'?'주(기간)':'월(기간)'))?></th>
              <th>추가 단어 수</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($periods as $i=>[$s,$e]): ?>
              <tr>
                <td>
                  <?php 
                    if($mode==='day') echo h($s);
                    elseif($mode==='week') echo h($labels[$i]);
                    else echo h((new DateTime($s))->format('Y-m'))." (".h($s)." ~ ".h($e).")"; 
                  ?>
                </td>
                <td class="num"><?= (int)$counts[$i] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

<script>
(function(){
  const css=getComputedStyle(document.documentElement);
  const ink2=css.getPropertyValue('--ink2').trim();
  const acc =css.getPropertyValue('--chart-bar').trim();
  const grid=css.getPropertyValue('--chart-grid').trim();
  const tBg =css.getPropertyValue('--chart-tooltip-bg').trim();
  const tFg =css.getPropertyValue('--chart-tooltip-fg').trim();
  const border=css.getPropertyValue('--border').trim();

  const labels = <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>;
  const data   = <?= json_encode($counts) ?>;

  new Chart(document.getElementById('chart'),{
    type:'bar',
    data:{ labels,
      datasets:[{
        data,
        backgroundColor:acc,
        borderColor:acc,
        borderWidth:1,
        borderRadius:8,
        barThickness:'flex',
        maxBarThickness:44
      }]
    },
    options:{
      maintainAspectRatio:false,
      plugins:{
        legend:{display:false},
        tooltip:{
          backgroundColor:tBg,
          titleColor:tFg, bodyColor:tFg,
          borderColor:border, borderWidth:1, padding:10
        }
      },
      scales:{
        x:{ticks:{color:ink2}, grid:{color:grid, drawBorder:false}},
        y:{ticks:{color:ink2}, grid:{color:grid, drawBorder:false}, beginAtZero:true, precision:0}
      }
    }
  });
})();
</script>
</body>
</html>
