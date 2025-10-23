<?php
require_once __DIR__ . '/config.php';

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function require_login(): void {
    if (!current_user()) {
        header('Location: ' . base_url('/admin/login.php'));
        exit;
    }
}

function login_user(array $user): void {
    $_SESSION['user'] = [
        'id' => (int)$user['id'],
        'name' => $user['name'],
        'email' => $user['email']
    ];
}

function logout_user(): void {
    unset($_SESSION['user']);
}

function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function rand_code(int $len = 6): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i=0; $i<$len; $i++) {
        $out .= $alphabet[random_int(0, strlen($alphabet)-1)];
    }
    return $out;
}

function ensure_upload_dirs(): void {
    global $UPLOAD_DIR;
    if (!is_dir($UPLOAD_DIR)) {
        @mkdir($UPLOAD_DIR, 0777, true);
    }
}

// Ensure tables for user question bank exist
function ensure_question_bank_schema(mysqli $db): void {
    $db->query("CREATE TABLE IF NOT EXISTS bank_questions (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        text TEXT NOT NULL,
        image_path VARCHAR(255) DEFAULT NULL,
        type ENUM('mcq','tiebreaker') NOT NULL DEFAULT 'mcq',
        correct_option TINYINT UNSIGNED DEFAULT NULL,
        tiebreaker_answer DOUBLE DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_bank_user (user_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $db->query("CREATE TABLE IF NOT EXISTS bank_options (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        bank_question_id INT UNSIGNED NOT NULL,
        opt_order TINYINT UNSIGNED NOT NULL,
        text VARCHAR(500) NOT NULL,
        INDEX idx_bank_opt (bank_question_id, opt_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// Copy quiz questions to the user's bank
function copy_quiz_to_bank(int $quiz_id, int $user_id, mysqli $db): int {
    ensure_question_bank_schema($db);
    $count = 0;
    $qs = $db->query('SELECT * FROM questions WHERE quiz_id='.(int)$quiz_id.' ORDER BY q_order');
    while ($q = $qs->fetch_assoc()) {
        $stmt = $db->prepare('INSERT INTO bank_questions(user_id,text,image_path,type,correct_option,tiebreaker_answer) VALUES(?,?,?,?,?,?)');
        $stmt->bind_param('isssid', $user_id, $q['text'], $q['image_path'], $q['type'], $q['correct_option'], $q['tiebreaker_answer']);
        if ($stmt->execute()) {
            $bank_qid = $stmt->insert_id; $count++;
            if ($q['type']==='mcq') {
                $ops = $db->query('SELECT opt_order,text FROM options WHERE question_id='.(int)$q['id'].' ORDER BY opt_order');
                while ($op = $ops->fetch_assoc()) {
                    $st2 = $db->prepare('INSERT INTO bank_options(bank_question_id,opt_order,text) VALUES(?,?,?)');
                    $st2->bind_param('iis', $bank_qid, $op['opt_order'], $op['text']);
                    $st2->execute();
                }
            }
        }
    }
    return $count;
}

// Copy a bank question into a quiz with next order
function add_bank_question_to_quiz(int $bank_qid, int $quiz_id, mysqli $db): bool {
    ensure_question_bank_schema($db);
    $bq = $db->query('SELECT * FROM bank_questions WHERE id='.(int)$bank_qid.' LIMIT 1')->fetch_assoc();
    if (!$bq) return false;
    $next = (int)($db->query('SELECT COALESCE(MAX(q_order),0)+1 AS n FROM questions WHERE quiz_id='.(int)$quiz_id)->fetch_assoc()['n'] ?? 1);
    $stmt = $db->prepare('INSERT INTO questions(quiz_id,q_order,text,type,correct_option,tiebreaker_answer,image_path) VALUES(?,?,?,?,?,?,?)');
    $stmt->bind_param('iissids', $quiz_id, $next, $bq['text'], $bq['type'], $bq['correct_option'], $bq['tiebreaker_answer'], $bq['image_path']);
    if (!$stmt->execute()) return false;
    $new_qid = $stmt->insert_id;
    if ($bq['type']==='mcq') {
        $ops = $db->query('SELECT opt_order,text FROM bank_options WHERE bank_question_id='.(int)$bank_qid.' ORDER BY opt_order');
        while ($op = $ops->fetch_assoc()) {
            $st2 = $db->prepare('INSERT INTO options(question_id,opt_order,text) VALUES(?,?,?)');
            $st2->bind_param('iis', $new_qid, $op['opt_order'], $op['text']);
            $st2->execute();
        }
    }
    return true;
}

