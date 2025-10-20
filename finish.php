<?php
require_once __DIR__ . '/auth.php';

$code = trim($_GET['code'] ?? ($_POST['code'] ?? ''));
if ($code==='') { http_response_code(400); exit('Saknar kod.'); }

$stmt = $mysqli->prepare('SELECT id,title FROM quizzes WHERE join_code=? LIMIT 1');
$stmt->bind_param('s',$code);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();
if (!$quiz) { http_response_code(404); exit('Tipspromenad saknas.'); }
$quiz_id = (int)$quiz['id'];
$sessKey = 'play_'.$quiz_id;
if (!isset($_SESSION[$sessKey])) { header('Location: '.base_url('/play.php?code='.$code)); exit; }
$state = &$_SESSION[$sessKey];

// Samla frågor
$qs = $mysqli->query('SELECT * FROM questions WHERE quiz_id='.(int)$quiz_id.' ORDER BY q_order')->fetch_all(MYSQLI_ASSOC);

// Om inte tiebreaker besvarad, skicka tillbaka
$hasTB = false; $tbQ=null;
foreach ($qs as $q) if ($q['type']==='tiebreaker') { $hasTB=true; $tbQ=$q; break; }
if ($hasTB && (!isset($state['answers'][$tbQ['id']]) || !isset($state['answers'][$tbQ['id']]['tb']))) {
    header('Location: '.base_url('/play.php?code='.$code)); exit;
}

$error='';
if (is_post()) {
    $name = trim($_POST['name'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    if ($name==='') $error='Ange namn.';
    if (!$error) {
        // Räkna poäng och skapa submission
        $score = 0;
        foreach ($qs as $q) {
            if ($q['type']==='mcq') {
                $qid = (int)$q['id'];
                if (isset($state['answers'][$qid]['option_id'])) {
                    $sel = (int)$state['answers'][$qid]['option_id'];
                    // Hitta korrekt option_id via ordning
                    $co = (int)$q['correct_option'];
                    $optRow = $mysqli->query('SELECT id FROM options WHERE question_id='.$qid.' AND opt_order='.$co.' LIMIT 1')->fetch_assoc();
                    $correct_id = $optRow ? (int)$optRow['id'] : 0;
                    if ($sel === $correct_id) $score++;
                }
            }
        }
        $tbValue = null;
        if ($hasTB) { $tbValue = (float)$state['answers'][$tbQ['id']]['tb']; }

        $stmt = $mysqli->prepare('INSERT INTO submissions(quiz_id,participant_name,contact_info,score,tiebreaker_value) VALUES(?,?,?,?,?)');
        $stmt->bind_param('issid', $quiz_id, $name, $contact, $score, $tbValue);
        if ($stmt->execute()) {
            $sub_id = $stmt->insert_id;
            // Spara svaren
            foreach ($qs as $q) {
                $qid = (int)$q['id'];
                if ($q['type']==='mcq') {
                    $sel = $state['answers'][$qid]['option_id'] ?? null;
                    $isCorrect = null;
                    if ($sel) {
                        $co = (int)$q['correct_option'];
                        $optRow = $mysqli->query('SELECT id FROM options WHERE question_id='.$qid.' AND opt_order='.$co.' LIMIT 1')->fetch_assoc();
                        $correct_id = $optRow ? (int)$optRow['id'] : 0;
                        $isCorrect = ($sel == $correct_id) ? 1 : 0;
                    }
                    $stmt2 = $mysqli->prepare('INSERT INTO submission_answers(submission_id,question_id,selected_option_id,is_correct) VALUES(?,?,?,?)');
                    $stmt2->bind_param('iiii', $sub_id, $qid, $sel, $isCorrect);
                    $stmt2->execute();
                } else {
                    $tb = $state['answers'][$qid]['tb'] ?? null;
                    $stmt2 = $mysqli->prepare('INSERT INTO submission_answers(submission_id,question_id,text_answer) VALUES(?,?,?)');
                    $tb_str = $tb===null ? null : (string)$tb;
                    $stmt2->bind_param('iis', $sub_id, $qid, $tb_str);
                    $stmt2->execute();
                }
            }
            // Rensa session
            unset($_SESSION[$sessKey]);
            header('Location: '.base_url('/thanks.php?code='.$code.'&name='.urlencode($name).'&score='.$score));
            exit;
        } else {
            $error='Kunde inte spara. Försök igen.';
        }
    }
}
?>
<!doctype html>
<html lang="sv">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Slutför – <?=h($quiz['title'])?></title>
  <style>
    body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;margin:1rem}
    .container{max-width:640px;margin:0 auto}
    input{width:100%;padding:.7rem;border:1px solid #bbb;border-radius:8px;margin:.4rem 0}
    .err{color:#b00020;margin:.5rem 0}
    .btn{display:inline-block;padding:.6rem .9rem;border:1px solid #777;border-radius:8px;text-decoration:none}
  </style>
</head>
<body>
  <div class="container">
    <h2>Slutför din inlämning</h2>
    <?php if ($error): ?><div class="err"><?=h($error)?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="code" value="<?=h($code)?>">
      <label>Namn
        <input name="name" required>
      </label>
      <label>Kontaktuppgifter (telefon / e‑post)
        <input name="contact">
      </label>
      <button class="btn" type="submit">Lämna in</button>
    </form>
  </div>
</body>
</html>

