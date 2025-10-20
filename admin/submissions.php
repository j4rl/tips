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
  <style>
    body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;margin:1.5rem}
    table{border-collapse:collapse;width:100%;margin-top:1rem}
    th,td{border-bottom:1px solid #ddd;padding:.5rem;text-align:left}
    .small{color:#555}
  </style>
</head>
<body>
  <h1>Resultat – <?=h($quiz['title'])?></h1>
  <p>
    <a href="<?=h(base_url('/admin/index.php'))?>">Översikt</a> |
    <a href="<?=h(base_url('/admin/questions.php?quiz_id='.$quiz['id']))?>">Frågor</a> |
    <a href="<?=h(base_url('/admin/print.php?quiz_id='.$quiz['id']))?>">Utskrift</a>
  </p>

  <?php if (!$subs): ?>
    <p>Inga inlämningar ännu.</p>
  <?php else: ?>
    <table>
      <tr><th>Plac.</th><th>Namn</th><th>Kontakt</th><th>Poäng</th><th>Utslagssvar</th><th>Diff</th><th>Tid</th></tr>
      <?php $i=1; foreach ($subs as $s): ?>
        <tr>
          <td><?= $i++ ?></td>
          <td><?= h($s['participant_name']) ?></td>
          <td class="small"><?= h((string)$s['contact_info']) ?></td>
          <td><?= (int)$s['score'] ?></td>
          <td><?= $s['tiebreaker_value']!==null ? h((string)$s['tiebreaker_value']) : '-' ?></td>
          <td><?= $s['tb_diff']!==999999999 ? h((string)$s['tb_diff']) : '-' ?></td>
          <td class="small"><?= h($s['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</body>
</html>

