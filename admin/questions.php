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

// Hämta frågor
$stmt = $mysqli->prepare('SELECT * FROM questions WHERE quiz_id=? ORDER BY q_order ASC');
$stmt->bind_param('i', $quiz_id);
$stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="sv">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Frågor – <?=h($quiz['title'])?></title>
  <link rel="stylesheet" href="<?=h(base_url('/assets/style.css'))?>">
</head>
<body>
  <h1>Frågor – <?=h($quiz['title'])?></h1>
  <p><a class="btn" href="<?=h(base_url('/admin/quiz_form.php?id='.$quiz['id']))?>">Redigera tipspromenad</a>
     <a class="btn" href="<?=h(base_url('/admin/question_form.php?quiz_id='.$quiz['id']))?>">+ Lägg till fråga</a>
     <a class="btn" href="<?=h(base_url('/admin/submissions.php?quiz_id='.$quiz['id']))?>">Resultat</a>
     <a class="btn" href="<?=h(base_url('/admin/print.php?quiz_id='.$quiz['id']))?>">Utskrift</a>
     <a class="btn" href="<?=h(base_url('/admin/index.php'))?>">Tillbaka</a>
  </p>
  <p>Startlänk: <code><?=h((isset($_SERVER['HTTP_HOST'])?('http'.(!empty($_SERVER['HTTPS'])?'s':'').'://'.$_SERVER['HTTP_HOST']):'').base_url('/play.php').'?code='.h($quiz['join_code']))?></code></p>

  <?php if (!$questions): ?>
    <p>Inga frågor ännu. Lägg till minst en, och avsluta med en utslagsfråga.</p>
  <?php else: ?>
  <table>
    <tr><th>#</th><th>Typ</th><th>Fråga</th><th>Bild</th><th>Åtgärder</th></tr>
    <?php foreach ($questions as $q): ?>
      <tr>
        <td><?= (int)$q['q_order'] ?></td>
        <td><?= $q['type']==='mcq'?'Flervalsfråga':'Utslagsfråga' ?></td>
        <td><?= nl2br(h($q['text'])) ?></td>
        <td><?php if ($q['image_path']): ?><img class="img" src="<?=h(base_url('/'.ltrim($q['image_path'],'/')))?>"><?php endif; ?></td>
        <td>
          <a class="btn" href="<?=h(base_url('/admin/question_form.php?id='.$q['id'].'&quiz_id='.$quiz['id']))?>">Redigera</a>
          <a class="btn" href="<?=h(base_url('/admin/question_form.php?delete=1&id='.$q['id'].'&quiz_id='.$quiz['id']))?>" onclick="return confirm('Radera frågan?');">Radera</a>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</body>
</html>
