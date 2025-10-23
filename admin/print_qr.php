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

$baseHost = isset($_SERVER['HTTP_HOST']) ? ('http' . (!empty($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST']) : '';
$playUrl = $baseHost . base_url('/play.php') . '?code=' . $quiz['join_code'];
?>
<!doctype html>
<html lang="sv">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>QR – <?=h($quiz['title'])?></title>
  <link rel="stylesheet" href="<?=h(base_url('/assets/style.css'))?>">
  <style>
    body{margin:1.5cm;}
    .wrap{display:flex;flex-direction:column;align-items:center;gap:1rem}
    #qrcode{width:14cm;max-width:100%;}
    .title{font-size:28pt;text-align:center}
    .url{font-size:12pt;color:#444;text-align:center;word-break:break-all}
    @media print { .noprint{display:none} }
  </style>
</head>
<body>
  <div class="noprint">
    <a href="<?=h(base_url('/admin/questions.php?quiz_id='.$quiz['id']))?>">Tillbaka</a>
    <button onclick="window.print()">Skriv ut</button>
  </div>
  <div class="wrap">
    <div class="title"><?=h($quiz['title'])?></div>
    <div id="qrcode" aria-label="QR till spel"></div>
    <div class="url"><?=h($playUrl)?></div>
  </div>
  <script src="<?=h(base_url('/assets/qrcode.min.js'))?>"></script>
  <script>
    (function(){
      var url = <?= json_encode($playUrl, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
      var el = document.getElementById('qrcode');
      // Render a large, crisp QR suitable for print
      var size = Math.min(el.clientWidth || 500, 800);
      try {
        new QRCode(el, {
          text: url,
          width: size,
          height: size,
          correctLevel: QRCode.CorrectLevel.H
        });
      } catch (e) {
        el.innerHTML = '<p>Kunde inte generera QR. URL:</p><code>'+url+'</code>';
      }
    })();
  </script>
</body>
</html>
