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

// Sort submissions: score DESC, tiebreaker diff ASC, created_at ASC (same as submissions.php)
$tbQ = $mysqli->query("SELECT tiebreaker_answer FROM questions WHERE quiz_id=".(int)$quiz_id." AND type='tiebreaker' ORDER BY q_order DESC LIMIT 1")->fetch_assoc();
$tbCorrect = $tbQ && $tbQ['tiebreaker_answer'] !== null ? (float)$tbQ['tiebreaker_answer'] : null;

$subs = [];
$res = $mysqli->query('SELECT id,participant_name,score,tiebreaker_value,created_at FROM submissions WHERE quiz_id='.(int)$quiz_id);
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
  <title>Utskrift resultat – <?=h($quiz['title'])?></title>
  <link rel="stylesheet" href="<?=h(base_url('/assets/style.css'))?>">
  <?php $prefs = get_user_settings((int)$user['id'], $mysqli); ?>
  <style>
    :root { <?php if (($prefs['theme_mode'] ?? 'system') === 'light'): ?>color-scheme: light;<?php elseif (($prefs['theme_mode'] ?? 'system') === 'dark'): ?>color-scheme: dark;<?php else: ?>color-scheme: light dark;<?php endif; ?> --accent: light-dark(<?=h($prefs['main_color_light'])?>, <?=h($prefs['main_color_dark'])?>); }
  </style>
  <style>
    body{margin:1.2cm}
    h1{margin:0}
    .plist{ margin-top: .8rem; }
    .plist li{ margin:.45rem 0; page-break-inside: avoid; }
    .meta{ color:#555; font-size: .95rem; }
    @media print{
      .noprint{display:none}
      body{margin:1.2cm}
      a{ color:#000; text-decoration:none }
    }
  </style>
</head>
<body>
  <div class="noprint">
    <a href="<?=h(base_url('/admin/submissions.php?quiz_id='.$quiz['id']))?>" class="back">Tillbaka</a>
    <button onclick="window.print()">Skriv ut</button>
  </div>
  <h1>Resultat – <?=h($quiz['title'])?></h1>
  <?php if (!$subs): ?>
    <p>Inga inlämningar ännu.</p>
  <?php else: ?>
    <ol class="plist">
      <?php foreach ($subs as $s): ?>
        <li>
          <strong><?= h($s['participant_name']) ?></strong> – <?= (int)$s['score'] ?> p
          <div class="meta">
            Utslagssvar: <?= $s['tiebreaker_value']!==null ? h((string)$s['tiebreaker_value']) : '-' ?>
            • Diff: <?= $s['tb_diff']!==999999999 ? h((string)$s['tb_diff']) : '-' ?>
            • Tid: <?= h($s['created_at']) ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ol>
  <?php endif; ?>
</body>
</html>
