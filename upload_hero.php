<?php
// 업로드 처리: target=day|night
// 저장 경로: /uploads/hero_day.jpg, /uploads/hero_night.jpg

$dir = __DIR__ . '/uploads';
if (!is_dir($dir)) { @mkdir($dir, 0777, true); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: index.php'); exit;
}

$target = $_POST['target'] ?? '';
if (!in_array($target, ['day','night'], true)) {
  die('잘못된 대상입니다.');
}

if (!isset($_FILES['hero']) || $_FILES['hero']['error'] !== UPLOAD_ERR_OK) {
  die('파일 업로드에 실패했습니다.');
}

// 용량 제한
$maxSize = 12 * 1024 * 1024; // 12MB
if ($_FILES['hero']['size'] > $maxSize) {
  die('파일이 너무 큽니다. 12MB 이하로 업로드 해주세요.');
}

// MIME 체크
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $_FILES['hero']['tmp_name']);
finfo_close($finfo);

$allowed = ['image/jpeg'=>'jpg','image/png'=>'png'];
if (!isset($allowed[$mime])) { die('JPG 또는 PNG 파일만 가능합니다.'); }

// 저장 파일명
$saveJpg = $dir . '/hero_'.$target.'.jpg';
$savePng = $dir . '/hero_'.$target.'.png';

// GD 가능 여부
$hasGD = function_exists('imagecreatefromjpeg') && function_exists('imagecreatetruecolor');

// 가로 최소 권장 1792px -> 리사이즈(선택)
if ($hasGD) {
  list($w,$h) = getimagesize($_FILES['hero']['tmp_name']);
  $maxW = 2400; // 적당히 리사이즈
  $ratio = $w > $maxW ? ($maxW / $w) : 1;
  $newW = (int)($w * $ratio);
  $newH = (int)($h * $ratio);

  if ($mime === 'image/jpeg') {
    $src = imagecreatefromjpeg($_FILES['hero']['tmp_name']);
  } else {
    $src = imagecreatefrompng($_FILES['hero']['tmp_name']);
    if (function_exists('imagepalettetotruecolor')) { @imagepalettetotruecolor($src); }
  }
  $dst = imagecreatetruecolor($newW, $newH);
  imagecopyresampled($dst, $src, 0,0, 0,0, $newW,$newH, $w,$h);

  // JPG로 일괄 저장(용량/호환성 좋음)
  imagejpeg($dst, $saveJpg, 88);
  imagedestroy($src); imagedestroy($dst);

  // 혹시 남아있을 png 버전 정리
  if (file_exists($savePng)) @unlink($savePng);
} else {
  // GD가 없으면 원본 그대로 저장(확장자 유지)
  $dest = ($allowed[$mime] === 'jpg') ? $saveJpg : $savePng;
  if (!move_uploaded_file($_FILES['hero']['tmp_name'], $dest)) {
    die('파일 저장 실패');
  }
}

header('Location: index.php');
