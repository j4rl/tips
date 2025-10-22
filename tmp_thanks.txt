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
  <title>Tack för ditt svar</title>
  <style>
    body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;margin:1rem;text-align:center}
    .card{display:inline-block;border:1px solid #ddd;border-radius:10px;padding:1.2rem;margin-top:2rem}
  </style>
</head>
<body>
  <div class="card">
    <h2>Tack <?=h($name?:'!')?> 🎉</h2>
    <p>Ditt svar har registrerats.</p>
    <p>Poäng: <strong><?= (int)$score ?></strong></p>
  </div>
</body>
</html>

