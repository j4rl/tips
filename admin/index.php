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
  <link rel="stylesheet" href="<?=h(base_url('/assets/style.css'))?>">
  <?php $prefs = get_user_settings((int)$user['id'], $mysqli); ?>
  <style>
    :root {
      <?php if (($prefs['theme_mode'] ?? 'system') === 'light'): ?>
      color-scheme: light;
      <?php elseif (($prefs['theme_mode'] ?? 'system') === 'dark'): ?>
      color-scheme: dark;
      <?php else: ?>
      color-scheme: light dark;
      <?php endif; ?>
      --accent: light-dark(<?=h($prefs['main_color_light'])?>, <?=h($prefs['main_color_dark'])?>);
    }
  </style>
</head>
<body>
  <header class="site-header">
    <div style="display:flex;align-items:center;gap:.6rem;">
      <h1 style="margin:0">Mina tipspromenader</h1>
    </div>
    <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
      <a class="btn btn-accent" href="<?=h(base_url('/admin/quiz_form.php'))?>">+ Ny tipspromenad</a>
      <a class="btn" href="<?=h(base_url('/admin/logout.php'))?>">Logga ut</a>
      <a class="btn" href="<?=h(base_url('/admin/profile.php'))?>"><?="✦ ".h($user['name'])?></a>
    </div>
  </header>

  <?php if (!$quizzes): ?>
    <p>Inga tipspromenader ännu.</p>
  <?php else: ?>
    <div class="grid grid-auto">
      <?php foreach ($quizzes as $q): ?>
        <article class="card">
          <div class="card-header" style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem;">
            <h3 style="margin:.2rem 0; font-size:1.1rem;"><?=h($q['title'])?></h3>
            <span class="badge <?= $q['is_active'] ? 'badge-success' : 'badge-muted' ?>"><?= $q['is_active'] ? 'Aktiv' : 'Inaktiv' ?></span>
          </div>
          <div class="small">Startlänk</div>
          <code><?=h((isset($_SERVER['HTTP_HOST'])?('http'.(!empty($_SERVER['HTTPS'])?'s':'').'://'.$_SERVER['HTTP_HOST']):'').base_url('/play.php').'?code='.h($q['join_code']))?></code>
          <div class="small" style="margin-top:.4rem;">Skapad: <?=h($q['created_at'])?></div>
          <div class="actions">
            <a class="btn" href="<?=h(base_url('/admin/questions.php?quiz_id='.$q['id']))?>">Frågor</a>
            <a class="btn" href="<?=h(base_url('/admin/submissions.php?quiz_id='.$q['id']))?>">Resultat</a>
            <a class="btn" href="<?=h(base_url('/admin/print.php?quiz_id='.$q['id']))?>">Utskrift (frågor)</a>
            <a class="btn" href="<?=h(base_url('/admin/print_qr.php?quiz_id='.$q['id']))?>">Utskrift (QR)</a>
            <a class="btn" href="<?=h(base_url('/admin/print_results.php?quiz_id='.$q['id']))?>">Utskrift (resultat)</a>
            <a class="btn" href="<?=h(base_url('/admin/quiz_form.php?id='.$q['id']))?>">Redigera</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</body>
</html>
