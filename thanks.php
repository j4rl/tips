<?php
require_once __DIR__ . '/auth.php';
$name = trim($_GET['name'] ?? '');
$score = (int)($_GET['score'] ?? 0);
?>
<!doctype html>
<html lang="sv">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?=h(base_url('/assets/style.css'))?>">
    <?php $prefs = get_user_settings((int)$user['id'], $mysqli); ?>
  <style>
    :root {
      <?php if (($prefs['theme_mode'] ?? 'system') === 'light'): ?>color-scheme: light;<?php elseif (($prefs['theme_mode'] ?? 'system') === 'dark'): ?>color-scheme: dark;<?php else: ?>color-scheme: light dark;<?php endif; ?>
      --accent: light-dark(<?=h($prefs['main_color_light'])?>, <?=h($prefs['main_color_dark'])?>);
    }
  </style>
  <title>Tack för ditt svar</title>
  <style>
    body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;margin:1rem;text-align:center}
    .card{display:inline-block;border:1px solid #ddd;border-radius:10px;padding:1.2rem;margin-top:2rem}
  </style>
</head>
<body>
  <div class="card text-center">
    <h2>Tack <?=h($name?:'!')?> 🎉</h2>
    <p>Ditt svar har registrerats.</p>
    <p>Poäng: <strong><?= (int)$score ?></strong></p>
  </div>
</body>
</html>
