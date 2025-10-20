<?php
require_once __DIR__ . '/../auth.php';
require_login();
$user = current_user();

// Hämta quizs
$stmt = $mysqli->prepare('SELECT id,title,join_code,is_active,created_at FROM quizzes WHERE user_id=? ORDER BY created_at DESC');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$quizzes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="sv">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mina tipspromenader</title>
  <style>
    body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;margin:1.5rem;}
    header{display:flex;justify-content:space-between;align-items:center;}
    table{border-collapse:collapse;width:100%;margin-top:1rem}
    th,td{border-bottom:1px solid #ddd;padding:.5rem;text-align:left}
    a.btn,button, .btn{display:inline-block;padding:.4rem .6rem;border:1px solid #999;border-radius:6px;text-decoration:none}
    .small{font-size:.85rem;color:#555}
    code{background:#f6f8fa;padding:.15rem .3rem;border-radius:4px}
  </style>
</head>
<body>
  <header>
    <h1>Mina tipspromenader</h1>
    <div>
      <a class="btn" href="<?=h(base_url('/admin/quiz_form.php'))?>">+ Ny tipspromenad</a>
      <a class="btn" href="<?=h(base_url('/admin/logout.php'))?>">Logga ut</a>
    </div>
  </header>

  <?php if (!$quizzes): ?>
    <p>Inga tipspromenader ännu.</p>
  <?php else: ?>
    <table>
      <tr><th>Titel</th><th>Startlänk</th><th>Status</th><th>Skapad</th><th>Åtgärder</th></tr>
      <?php foreach ($quizzes as $q): ?>
        <tr>
          <td><?=h($q['title'])?></td>
          <td>
            <div class="small">URL:</div>
            <code><?=h((isset($_SERVER['HTTP_HOST'])?('http'.(!empty($_SERVER['HTTPS'])?'s':'').'://'.$_SERVER['HTTP_HOST']):'').base_url('/play.php').'?code='.h($q['join_code']))?></code>
          </td>
          <td><?= $q['is_active'] ? 'Aktiv' : 'Inaktiv' ?></td>
          <td><?=h($q['created_at'])?></td>
          <td>
            <a class="btn" href="<?=h(base_url('/admin/questions.php?quiz_id='.$q['id']))?>">Frågor</a>
            <a class="btn" href="<?=h(base_url('/admin/submissions.php?quiz_id='.$q['id']))?>">Resultat</a>
            <a class="btn" href="<?=h(base_url('/admin/print.php?quiz_id='.$q['id']))?>">Utskrift</a>
            <a class="btn" href="<?=h(base_url('/admin/quiz_form.php?id='.$q['id']))?>">Redigera</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</body>
</html>

