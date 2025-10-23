<?php
require_once __DIR__ . '/../auth.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

if (!is_post()) { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Method not allowed']); exit; }

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) { $data = $_POST; }

$quiz_id = isset($data['quiz_id']) ? (int)$data['quiz_id'] : 0;
$order = $data['order'] ?? null; // expected array of question IDs in desired sequence

if (!$quiz_id || !is_array($order) || count($order) === 0) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Bad request']);
    exit;
}

// Ensure the user owns the quiz
$user = current_user();
$stmt = $mysqli->prepare('SELECT id FROM quizzes WHERE id=? AND user_id=?');
$stmt->bind_param('ii', $quiz_id, $user['id']);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'Forbidden']);
    exit;
}

// Load existing question IDs for this quiz
$ids = [];
$res = $mysqli->query('SELECT id FROM questions WHERE quiz_id='.(int)$quiz_id.' ORDER BY q_order, id');
while ($r = $res->fetch_assoc()) { $ids[] = (int)$r['id']; }

// Validate that provided order covers exactly all existing IDs
$provided = array_map('intval', $order);
sort($provided);
$sortedExisting = $ids; sort($sortedExisting);
if ($provided !== $sortedExisting) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Order set does not match existing questions']);
    exit;
}

// Apply new order
try {
    $mysqli->begin_transaction();
    $ord = 1;
    $stmtU = $mysqli->prepare('UPDATE questions SET q_order=? WHERE id=? AND quiz_id=?');
    foreach ($order as $qid) {
        $qid = (int)$qid;
        $stmtU->bind_param('iii', $ord, $qid, $quiz_id);
        $stmtU->execute();
        $ord++;
    }
    $mysqli->commit();
    echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Failed to update']);
}

