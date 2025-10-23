<?php
require_once __DIR__ . '/../auth.php';
require_login();
$user = current_user();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { http_response_code(400); exit('Saknar id.'); }

ensure_question_bank_schema($mysqli);
$stmt = $mysqli->prepare('SELECT id,user_id FROM bank_questions WHERE id=? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$bq = $stmt->get_result()->fetch_assoc();
if (!$bq || (int)$bq['user_id'] !== (int)$user['id']) { http_response_code(403); exit('Otillåtet.'); }

// Delete options then the question
$mysqli->query('DELETE FROM bank_options WHERE bank_question_id='.(int)$id);
$stmt = $mysqli->prepare('DELETE FROM bank_questions WHERE id=?');
$stmt->bind_param('i', $id);
$stmt->execute();

header('Location: ' . base_url('/admin/profile.php'));
exit;

