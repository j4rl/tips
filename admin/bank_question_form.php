<?php
require_once __DIR__ . '/../auth.php';
require_login();
$user = current_user();
ensure_question_bank_schema($mysqli);
ensure_upload_dirs();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$type = 'mcq';
$text = '';
$image_path = '';
$correct_option = null;
$tiebreaker_answer = '';
$opts = ['', '', '', ''];

if ($id) {
    $stmt = $mysqli->prepare('SELECT * FROM bank_questions WHERE id=? AND user_id=?');
    $stmt->bind_param('ii', $id, $user['id']);
    $stmt->execute();
    $q = $stmt->get_result()->fetch_assoc();
    if (!$q) { http_response_code(404); exit('Bankfråga saknas.'); }
    $type = $q['type'];
    $text = $q['text'];
    $image_path = $q['image_path'];
    $correct_option = $q['correct_option'];
    $tiebreaker_answer = $q['tiebreaker_answer'];
    if ($type==='mcq') {
        $stmt = $mysqli->prepare('SELECT * FROM bank_options WHERE bank_question_id=? ORDER BY opt_order');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $opts = array_fill(0,4,'');
        foreach ($rows as $r) { $opts[(int)$r['opt_order']-1] = $r['text']; }
    }
}

$error='';
if (is_post()) {
    $type = ($_POST['type'] ?? 'mcq') === 'tiebreaker' ? 'tiebreaker' : 'mcq';
    $text = trim($_POST['text'] ?? '');
    $correct_option = null;
    $tiebreaker_answer = null;
    $opts = [
        trim($_POST['opt1'] ?? ''),
        trim($_POST['opt2'] ?? ''),
        trim($_POST['opt3'] ?? ''),
        trim($_POST['opt4'] ?? ''),
    ];
    if ($type==='mcq') {
        $co = (int)($_POST['correct_option'] ?? 0);
        if ($co>=1 && $co<=4) $correct_option = $co;
    } else {
        $tiebreaker_answer = $_POST['tiebreaker_answer'] !== '' ? (float)$_POST['tiebreaker_answer'] : null;
    }
    if ($text==='') $error='Frågetext krävs.';
    if (!$error && $type==='mcq') {
        $filled = array_values(array_filter($opts, fn($v)=>$v!==''));
        if (count($filled) < 2) $error='Minst två svarsalternativ krävs.';
        if (!$correct_option) $error='Markera korrekt alternativ.';
    }
    if (!$error && isset($_FILES['image']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            $error='Endast bildfiler (jpg,png,gif,webp) tillåts.';
        }
    }
    if (!$error) {
        if ($id) {
            $stmt = $mysqli->prepare('UPDATE bank_questions SET text=?, type=?, correct_option=?, tiebreaker_answer=? WHERE id=? AND user_id=?');
            $stmt->bind_param('ssdiis', $text, $type, $correct_option, $tiebreaker_answer, $id, $user['id']);
            $ok = $stmt->execute();
        } else {
            $stmt = $mysqli->prepare('INSERT INTO bank_questions(user_id,text,type,correct_option,tiebreaker_answer) VALUES(?,?,?,?,?)');
            $stmt->bind_param('issid', $user['id'], $text, $type, $correct_option, $tiebreaker_answer);
            $ok = $stmt->execute();
            if ($ok) $id = $stmt->insert_id;
        }
        if ($ok && isset($_FILES['image']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $fname = 'bank_'.$user['id'].'_q_'.$id.'_'.time().'.'.$ext;
            global $UPLOAD_DIR;
            if (!is_dir($UPLOAD_DIR.'/bank_'.$user['id'])) { @mkdir($UPLOAD_DIR.'/bank_'.$user['id'], 0777, true); }
            $dest = $UPLOAD_DIR.'/bank_'.$user['id'].'/'.$fname;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                $webPath = 'admin/uploads/bank_'.$user['id'].'/'.$fname;
                $stmt = $mysqli->prepare('UPDATE bank_questions SET image_path=? WHERE id=?');
                $stmt->bind_param('si',$webPath,$id);
                $stmt->execute();
                $image_path = $webPath;
            }
        }
        if ($ok && $type==='mcq') {
            // Replace options
            $mysqli->query('DELETE FROM bank_options WHERE bank_question_id='.(int)$id);
            $ord = 1;
            for ($i=0;$i<4;$i++) {
                $t = trim($opts[$i]);
                if ($t==='') continue;
                $stmt = $mysqli->prepare('INSERT INTO bank_options(bank_question_id,opt_order,text) VALUES(?,?,?)');
                $stmt->bind_param('iis', $id, $ord, $t);
                $stmt->execute();
                $ord++;
            }
        }
        if ($ok) { header('Location: ' . base_url('/admin/profile.php')); exit; }
        $error='Kunde inte spara bankfrågan.';
    }
}
?>
<!doctype html>
<html lang="sv">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $id? 'Redigera' : 'Ny' ?> bankfråga</title>
  <link rel="stylesheet" href="<?=h(base_url('/assets/style.css'))?>">
    <?php $prefs = get_user_settings((int)$user['id'], $mysqli); ?>
  <style>
    :root {
      <?php if (($prefs['theme_mode'] ?? 'system') === 'light'): ?>color-scheme: light;<?php elseif (($prefs['theme_mode'] ?? 'system') === 'dark'): ?>color-scheme: dark;<?php else: ?>color-scheme: light dark;<?php endif; ?>
      --accent: light-dark(<?=h($prefs['main_color_light'])?>, <?=h($prefs['main_color_dark'])?>);
    }
  </style>
</head>
<body>
  <header class="site-header">
    <div><h1 style="margin:0"><?= $id? 'Redigera' : 'Ny' ?> bankfråga</h1></div>
    <div>
      <a class="btn" href="<?=h(base_url('/admin/logout.php'))?>">Logga ut</a>
      <a class="btn" href="<?=h(base_url('/admin/profile.php'))?>">Till profil</a>
    </div>
  </header>
  <?php if ($error): ?><div class="err"><?=h($error)?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data" class="card" style="max-width:900px;margin:auto;">
    <div class="row">
      <div>
        <label>Typ</label>
        <label><input type="radio" name="type" value="mcq" <?= $type==='mcq'?'checked':'' ?>> Flervalsfråga</label>
        <label><input type="radio" name="type" value="tiebreaker" <?= $type==='tiebreaker'?'checked':'' ?>> Utslagsfråga</label>
      </div>
    </div>

    <label>Fråga
      <textarea name="text" rows="4" required><?=h($text)?></textarea>
    </label>
    <label>Bild (valfri)
      <input type="file" name="image" accept="image/*">
      <?php if ($image_path): ?><div>Nuvarande: <a href="<?=h(base_url('/'.ltrim($image_path,'/')))?>" target="_blank">Visa bild</a></div><?php endif; ?>
    </label>

    <fieldset id="mcq" <?= $type==='mcq'? '' : 'style="display:none"' ?>>
      <legend>Flervalsalternativ (2–4)</legend>
      <?php for ($i=1;$i<=4;$i++): ?>
        <div>
          <label>Alternativ <?= $i ?>
            <input type="text" name="opt<?= $i ?>" value="<?=h($opts[$i-1] ?? '')?>">
          </label>
          <label><input type="radio" name="correct_option" value="<?= $i ?>" <?= ((int)$correct_option === $i)?'checked':'' ?>> Korrekt</label>
        </div>
      <?php endfor; ?>
    </fieldset>

    <fieldset id="tb" <?= $type==='tiebreaker'? '' : 'style="display:none"' ?>>
      <legend>Utslagsfråga</legend>
      <label>Rätt svar (numeriskt)
        <input type="number" step="any" name="tiebreaker_answer" value="<?=h((string)$tiebreaker_answer)?>">
      </label>
    </fieldset>

    <button type="submit" class="btn-accent">Spara</button>
    <a class="btn" href="<?=h(base_url('/admin/profile.php'))?>">Tillbaka</a>
  </form>
</body>
</html>

