<?php
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'wordbook';

$conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
  http_response_code(500);
  die('DB 연결 실패: ' . htmlspecialchars($conn->connect_error, ENT_QUOTES));
}
$conn->set_charset('utf8mb4');
