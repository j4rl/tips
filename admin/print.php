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

$stmt = $mysqli->prepare("SELECT * FROM questions WHERE quiz_id=? ORDER BY q_order");
$stmt->bind_param('i', $quiz_id);
$stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

function options_for($qid){
  global $mysqli; $o=[];
  $res=$mysqli->query('SELECT opt_order,text FROM options WHERE question_id='.(int)$qid.' ORDER BY opt_order');
  while($r=$res->fetch_assoc()){$o[]=$r;} return $o;
}
?>
<!doctype html>
<html lang="sv">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Utskrift – <?=h($quiz['title'])?></title>
  <link rel="stylesheet" href="<?=h(base_url('/assets/style.css'))?>">
  <style>
    body{font-family:Georgia, Cambria, 'Times New Roman', serif; margin:1cm}
    h1{font-size:20pt;margin-bottom:.2cm}
    .q{page-break-inside:avoid;margin:.6cm 0}
    /* One question per page */
    @media print {
      .q { break-after: page; page-break-after: always; }
      .q:last-child { break-after: auto; page-break-after: auto; }
      .noprint{display:none}
      body { margin: 1.2cm; font-family: Georgia, Cambria, 'Times New Roman', serif; }
      a { text-decoration: none; color: #000; }
      .img { max-width: 100%; height: auto; }
    }
    .img{max-width:100%;max-height:10cm;display:block;margin:.3cm 0}
    .opts{margin:.2cm 0 0 .5cm}
  </style>
</head>
<body>
  <div class="noprint">
    <a href="<?=h(base_url('/admin/questions.php?quiz_id='.$quiz['id']))?>" class="back">Tillbaka</a>
    <button onclick="window.print()">Skriv ut</button>
  </div>
  <h1><?=h($quiz['title'])?></h1>
  <?php foreach ($questions as $q): ?>
    <div class="q">
      <div><strong>Fråga <?= (int)$q['q_order'] ?><?= $q['type']==='tiebreaker'?' (Utslagsfråga)':'' ?>:</strong></div>
      <div><?= nl2br(h($q['text'])) ?></div>
      <?php if ($q['image_path']): ?><img class="img" src="<?=h(base_url('/'.ltrim($q['image_path'],'/')))?>" /><?php endif; ?>
      <?php if ($q['type']==='mcq'): $ops = options_for($q['id']); if ($ops): ?>
        <ol class="opts" type="A">
          <?php foreach ($ops as $op): ?>
            <li><?=h($op['text'])?></li>
          <?php endforeach; ?>
        </ol>
      <?php endif; endif; ?>
    </div>
  <?php endforeach; ?>
</body>
</html>
