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


function openai_call($key,$msgs){
  $ch=curl_init('https://api.openai.com/v1/chat/completions');
  curl_setopt_array($ch,[
    CURLOPT_HTTPHEADER=>[
      'Content-Type: application/json',
      'Authorization: Bearer '.$key
    ],
    CURLOPT_POST=>true,
    CURLOPT_POSTFIELDS=>json_encode([
      'model'=>'gpt-4o-mini',
      'messages'=>$msgs,
      'temperature'=>0.4
    ],JSON_UNESCAPED_UNICODE),
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_TIMEOUT=>20
  ]);
  $r=curl_exec($ch);
  if($r===false){$e=curl_error($ch);curl_close($ch);throw new RuntimeException('OpenAI 연결 실패: '.$e);}
  $c=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  if($c<200||$c>=300) throw new RuntimeException("OpenAI 오류($c): $r");
  $d=json_decode($r,true);
  return trim($d['choices'][0]['message']['content']??'');
}

// --- 레벨 선택 ---
$levels=['N1','N2','N3','N4','N5'];
$reqLevel = $_GET['level'] ?? 'N1';
$level    = in_array($reqLevel, $levels) ? $reqLevel : 'N1';

$error=null; $ok=null;
$cards=[];           // 화면에 표시할 3개 카드
$cards_token='';     // cards를 base64 JSON
$saved_ids=[];       // 저장된 카드 식별자 목록 (md5(word|meaning))
$saved_token='';     // saved_ids를 base64 JSON

// --- 저장 처리 (POST) ---
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save'){
  // 레벨/상태 복구
  $postLevel = $_POST['level'] ?? $level;
  if(in_array($postLevel,$levels)) $level=$postLevel;

  if(!empty($_POST['cards_token'])){
    $decoded = json_decode(base64_decode($_POST['cards_token']), true);
    if(is_array($decoded)) $cards = $decoded;
  }
  if(!empty($_POST['saved_token'])){
    $decoded = json_decode(base64_decode($_POST['saved_token']), true);
    if(is_array($decoded)) $saved_ids = $decoded;
  }

  // 저장 대상
  $word=$_POST['word']??''; $meaning=$_POST['meaning']??''; $reading=$_POST['reading']??'';
  $example=$_POST['example']??''; $example_ko=$_POST['example_ko']??'';
  $sense='JLPT '.$level;

  // 중복 저장 방지용 id
  $this_id = md5($word.'|'.$meaning);

  if(!in_array($this_id,$saved_ids,true)){ // 아직 안 저장된 것만 저장
    $st=$conn->prepare("INSERT INTO words (word,sense,meaning,reading,example,example_ko) VALUES (?,?,?,?,?,?)");
    $st->bind_param('ssssss',$word,$sense,$meaning,$reading,$example,$example_ko);
    if($st->execute()){
      $ok='저장 완료!';
      $saved_ids[]=$this_id; // 저장 목록에 추가
    } else {
      $error='저장 실패: '.$st->error;
    }
    $st->close();
  } else {
    $ok='이미 저장된 항목입니다.'; // 버튼은 이미 흰색/비활성 처리됨
  }

  // 상태 토큰 갱신
  $cards_token = base64_encode(json_encode($cards, JSON_UNESCAPED_UNICODE));
  $saved_token = base64_encode(json_encode($saved_ids, JSON_UNESCAPED_UNICODE));
}

// --- 3개 단어 생성: GET + next=1 일 때만 ---
if($_SERVER['REQUEST_METHOD']==='GET' && (($_GET['next'] ?? '')==='1')){
  try{
    // 1) JSON 배열로 3개 요청
    $sys=['role'=>'system','content'=>
      "Return EXACT JSON for 3 JLPT {$level} vocabulary items.\n".
      "Format: {\"items\":[{\"word\":\"...\",\"reading\":\"...\",\"meaning_ko\":\"...\",\"example_ja\":\"...\",\"example_ko\":\"...\"}, ... (3 total)]}\n".
      "- word: kanji/kana\n- reading: hiragana only (optional, empty if N/A)\n- meaning_ko: short Korean meaning\n- example_ja: one concise Japanese example\n- example_ko: natural Korean translation\nNo extra text."
    ];
    $json=openai_call($OPENAI_API_KEY,[$sys,['role'=>'user','content'=>'Give me 3 items.']]);
    $j=json_decode($json,true);

    if(is_array($j) && isset($j['items']) && is_array($j['items']) && count($j['items'])>0){
      foreach($j['items'] as $it){
        if(isset($it['word'],$it['meaning_ko'])){
          $cards[]=[
            'word'=>trim($it['word']),
            'reading'=>trim($it['reading']??''),
            'meaning'=>trim($it['meaning_ko']),
            'example'=>trim($it['example_ja']??''),
            'example_ko'=>trim($it['example_ko']??''),
          ];
        }
      }
    }

    // 2) 폴백으로 3개 채우기
    if(count($cards)<3){
      $need = 3 - count($cards);
      for($i=0;$i<$need;$i++){
        $txt=openai_call($OPENAI_API_KEY,[
          ['role'=>'system','content'=>"Give one JLPT {$level} word as:\nWORD: ...\nREADING: ...\nJP: ...\nKO: ..."],
          ['role'=>'user','content'=>'One please.']
        ]);
        preg_match('/WORD:\s*(.+)/u',$txt,$m1);
        preg_match('/READING:\s*(.+)/u',$txt,$m2);
        preg_match('/JP:\s*(.+)/u',$txt,$m3);
        preg_match('/KO:\s*(.+)/u',$txt,$m4);
        $cards[]=[
          'word'=>$m1[1]??$txt,
          'reading'=>$m2[1]??'',
          'meaning'=>$m4[1]??'',
          'example'=>$m3[1]??'',
          'example_ko'=>$m4[1]??''
        ];
      }
      $cards = array_slice($cards,0,3);
    }

    // 초기엔 저장된 게 없으니 빈 배열
    $saved_ids = [];

    // 상태 토큰 생성
    $cards_token = base64_encode(json_encode($cards, JSON_UNESCAPED_UNICODE));
    $saved_token = base64_encode(json_encode($saved_ids, JSON_UNESCAPED_UNICODE));
  }catch(Throwable $e){ $error=$e->getMessage(); }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8"><title>JLPT 단어 보기 | KotobaAI</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<?= theme_head() ?>
<style>
  body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,'Noto Sans KR',sans-serif}
  .wrap{max-width:900px;margin:40px auto;padding:0 18px}
  .badge{display:inline-block;padding:2px 8px;border:1px solid var(--border);border-radius:999px;color:var(--ink2);font-size:11px;margin-left:6px}
  .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px}
</style>
</head>
<body class="page">
  <div class="wrap">
    <div class="nav"><a href="index.php" style="color:var(--acc)">← 🏠 메인</a></div>

    <div class="card" style="padding:18px">
      <h2 style="margin:0 0 10px">JLPT 단어 보기</h2>

      <?php if($error): ?>
        <div class="tile" style="border-color:#e2475e;color:#e2475e;margin-bottom:10px">⚠️ <?=h($error)?></div>
      <?php endif; ?>
      <?php if($ok): ?>
        <div class="tile" style="border-color:var(--ok);color:var(--ok);margin-bottom:10px">✅ <?=$ok?></div>
      <?php endif; ?>

      <!-- 레벨 선택 + 다음 단어(3개) -->
      <form method="get" class="tile"
            style="display:flex;gap:8px;align-items:center;flex-wrap:nowrap;white-space:nowrap;margin-bottom:12px">
        <label style="flex:0 0 auto">레벨 선택</label>
        <select class="input" name="level" style="width:auto;flex:0 0 auto;min-width:90px">
          <?php foreach($levels as $lv): ?>
            <option value="<?=$lv?>" <?=$lv===$level?'selected':''?>><?=$lv?></option>
          <?php endforeach; ?>
        </select>
        <input type="hidden" name="next" value="1">
        <button class="btn" type="submit" style="flex:0 0 auto">다음 단어 3개</button>
      </form>

      <!-- 카드들 표시 -->
      <?php if($cards): ?>
        <div class="grid">
          <?php foreach($cards as $c): 
                $id = md5(($c['word']??'').'|'.($c['meaning']??'')); 
                $isSaved = in_array($id,$saved_ids,true);
          ?>
            <div class="tile" style="display:flex;flex-direction:column;gap:8px">
              <div style="font-size:18px;font-weight:800">
                <?=h($c['word'])?> <span class="badge">JLPT <?=$level?></span>
              </div>
              <div><b>한국어 뜻:</b> <?=h($c['meaning'])?></div>
              <?php if(!empty($c['reading'])): ?>
                <div><b>후리가나:</b> <?=h($c['reading'])?></div>
              <?php endif; ?>
              <?php if(!empty($c['example'])): ?>
                <div><b>예문:</b> <?=nl2br(h($c['example']))?></div>
              <?php endif; ?>
              <?php if(!empty($c['example_ko'])): ?>
                <div><b>예문 한국어:</b> <?=nl2br(h($c['example_ko']))?></div>
              <?php endif; ?>

              <!-- 개별 저장 (상태 유지용 토큰 동봉) -->
              <form method="post" action="jlpt.php?level=<?=h($level)?>" style="margin-top:4px">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="level" value="<?=h($level)?>">
                <input type="hidden" name="cards_token" value="<?=h($cards_token)?>">
                <input type="hidden" name="saved_token" value="<?=h($saved_token)?>">
                <input type="hidden" name="word" value="<?=h($c['word'])?>">
                <input type="hidden" name="meaning" value="<?=h($c['meaning'])?>">
                <input type="hidden" name="reading" value="<?=h($c['reading'])?>">
                <input type="hidden" name="example" value="<?=h($c['example'])?>">
                <input type="hidden" name="example_ko" value="<?=h($c['example_ko'])?>">

                <?php if($isSaved): ?>
                  <button class="btn ghost" type="button" disabled style="width:100%">저장됨</button>
                <?php else: ?>
                  <button class="btn" type="submit" style="width:100%">단어장에 추가</button>
                <?php endif; ?>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="tile">레벨을 선택하고 <b>“다음 단어 3개”</b>를 눌러 단어를 생성하세요.</div>
      <?php endif; ?>

      <div class="tile" style="margin-top:12px;color:var(--ink2)">
        저장 시 <b>sense</b> 필드에 “JLPT <?=$level?>” 라벨로 들어가서 단어 목록/검색/퀴즈에서 구분돼요.
      </div>
    </div>
  </div>
</body>
</html>
