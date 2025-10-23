<?php
require_once __DIR__ . '/../auth.php';
require_login();
$user = current_user();
$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;

$stmt = $mysqli->prepare('SELECT * FROM quizzes WHERE id=? AND user_id=?');
$stmt->bind_param('ii', $quiz_id, $user['id']);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();
if (!$quiz) { http_response_code(404); exit('Hittades inte.'); }

// För sortering: score DESC, diff på utslagsfråga ASC (om finns), tid ASC
// Beräkna diff mot korrekt utslagssvar (sista frågan)
$tbQ = $mysqli->query("SELECT tiebreaker_answer FROM questions WHERE quiz_id=".(int)$quiz_id." AND type='tiebreaker' ORDER BY q_order DESC LIMIT 1")->fetch_assoc();
$tbCorrect = $tbQ && $tbQ['tiebreaker_answer'] !== null ? (float)$tbQ['tiebreaker_answer'] : null;

$subs = [];
$res = $mysqli->query('SELECT id,participant_name,contact_info,score,tiebreaker_value,created_at FROM submissions WHERE quiz_id='.(int)$quiz_id);
while ($row = $res->fetch_assoc()) {
    $row['tb_diff'] = ($tbCorrect !== null && $row['tiebreaker_value'] !== null) ? abs((float)$row['tiebreaker_value'] - $tbCorrect) : 999999999;
    $subs[] = $row;
}
usort($subs, function($a,$b){
    if ($a['score'] != $b['score']) return $a['score'] < $b['score'] ? 1 : -1;
    if ($a['tb_diff'] != $b['tb_diff']) return $a['tb_diff'] > $b['tb_diff'] ? 1 : -1;
    return strcmp($a['created_at'],$b['created_at']);
});
?>
<!doctype html>
<html lang="sv">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Resultat – <?=h($quiz['title'])?></title>
  <link rel="stylesheet" href="<?=h(base_url('/assets/style.css'))?>">
</head>
<body>
  <h1>Resultat – <?=h($quiz['title'])?></h1>
  <p>
    <a href="<?=h(base_url('/admin/index.php'))?>">Översikt</a> |
    <a href="<?=h(base_url('/admin/questions.php?quiz_id='.$quiz['id']))?>">Frågor</a> |
    <a href="<?=h(base_url('/admin/print_results.php?quiz_id='.$quiz['id']))?>">Utskrift (resultat)</a>
  </p>

  <?php if (!$subs): ?>
    <p>Inga inlämningar ännu.</p>
  <?php else: ?>
    <div class="list">
      <?php $i=1; foreach ($subs as $s): ?>
        <div class="result-item">
          <div class="result-rank">#<?= $i++ ?></div>
          <div>
            <div><strong><?= h($s['participant_name']) ?></strong> – <?= (int)$s['score'] ?> p</div>
            <div class="result-meta">
              Utslagssvar: <?= $s['tiebreaker_value']!==null ? h((string)$s['tiebreaker_value']) : '-' ?>
              • Diff: <?= $s['tb_diff']!==999999999 ? h((string)$s['tb_diff']) : '-' ?>
              • Tid: <?= h($s['created_at']) ?>
              <?php if (!empty($s['contact_info'])): ?> • Kontakt: <?= h((string)$s['contact_info']) ?><?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</body>
</html>

