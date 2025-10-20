<?php
require_once __DIR__ . '/../auth.php';
require_login();
ensure_upload_dirs();
$user = current_user();

$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : (int)($_POST['quiz_id'] ?? 0);
$stmt = $mysqli->prepare('SELECT id,user_id,title FROM quizzes WHERE id=? AND user_id=?');
$stmt->bind_param('ii', $quiz_id, $user['id']);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();
if (!$quiz) { http_response_code(404); exit('Hittades inte.'); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$delete = isset($_GET['delete']);

if ($delete && $id) {
    $stmt = $mysqli->prepare('DELETE FROM questions WHERE id=? AND quiz_id=?');
    $stmt->bind_param('ii', $id, $quiz_id);
    $stmt->execute();
    header('Location: ' . base_url('/admin/questions.php?quiz_id='.$quiz_id));
    exit;
}

$type = 'mcq';
$text = '';
$q_order = 1;
$image_path = '';
$correct_option = null;
$tiebreaker_answer = '';
$opts = ['', '', '', ''];

if ($id) {
    $stmt = $mysqli->prepare('SELECT * FROM questions WHERE id=? AND quiz_id=?');
    $stmt->bind_param('ii', $id, $quiz_id);
    $stmt->execute();
    $q = $stmt->get_result()->fetch_assoc();
    if (!$q) { http_response_code(404); exit('Fråga saknas.'); }
    $type = $q['type'];
    $text = $q['text'];
    $q_order = (int)$q['q_order'];
    $image_path = $q['image_path'];
    $correct_option = $q['correct_option'];
    $tiebreaker_answer = $q['tiebreaker_answer'];
    if ($type==='mcq') {
        $stmt = $mysqli->prepare('SELECT * FROM options WHERE question_id=? ORDER BY opt_order');
        $stmt->bind_param('i',$id);
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
    $q_order = (int)($_POST['q_order'] ?? 1);
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
        // Spara/uppdatera fråga
        if ($id) {
            $stmt = $mysqli->prepare('UPDATE questions SET q_order=?, text=?, type=?, correct_option=?, tiebreaker_answer=? WHERE id=? AND quiz_id=?');
            $stmt->bind_param('issdiii', $q_order, $text, $type, $correct_option, $tiebreaker_answer, $id, $quiz_id);
            $ok = $stmt->execute();
        } else {
            $stmt = $mysqli->prepare('INSERT INTO questions(quiz_id,q_order,text,type,correct_option,tiebreaker_answer) VALUES(?,?,?,?,?,?)');
            $stmt->bind_param('iissid', $quiz_id, $q_order, $text, $type, $correct_option, $tiebreaker_answer);
            $ok = $stmt->execute();
            if ($ok) $id = $stmt->insert_id;
        }
        if ($ok && isset($_FILES['image']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $fname = 'quiz_'.$quiz_id.'_q_'.$id.'_'.time().'.'.$ext;
            global $UPLOAD_DIR, $UPLOAD_URL;
            if (!is_dir($UPLOAD_DIR.'/quiz_'.$quiz_id)) {
                @mkdir($UPLOAD_DIR.'/quiz_'.$quiz_id, 0777, true);
            }
            $dest = $UPLOAD_DIR.'/quiz_'.$quiz_id.'/'.$fname;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                $webPath = 'admin/uploads/quiz_'.$quiz_id.'/'.$fname;
                $stmt = $mysqli->prepare('UPDATE questions SET image_path=? WHERE id=?');
                $stmt->bind_param('si',$webPath,$id);
                $stmt->execute();
                $image_path = $webPath;
            }
        }
        if ($ok && $type==='mcq') {
            // Spara alternativ
            $mysqli->query('DELETE FROM options WHERE question_id='.(int)$id);
            $ord = 1;
            for ($i=0;$i<4;$i++) {
                $t = trim($opts[$i]);
                if ($t==='') continue;
                $stmt = $mysqli->prepare('INSERT INTO options(question_id,opt_order,text) VALUES(?,?,?)');
                $stmt->bind_param('iis', $id, $ord, $t);
                $stmt->execute();
                $ord++;
            }
        }
        if ($ok) {
            header('Location: ' . base_url('/admin/questions.php?quiz_id='.$quiz_id));
            exit;
        }
        $error='Kunde inte spara frågan.';
    }
}
?>
<!doctype html>
<html lang="sv">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $id? 'Redigera' : 'Ny' ?> fråga – <?=h($quiz['title'])?></title>
  <style>
    body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;margin:1.5rem;max-width:900px}
    input[type=text],input[type=number], textarea{width:100%;padding:.6rem;margin:.3rem 0}
    .err{color:#b00020;margin:.5rem 0}
    fieldset{margin:1rem 0}
    .row{display:flex;gap:1rem}
    .row>div{flex:1}
    label{display:block}
  </style>
</head>
<body>
  <h1><?= $id? 'Redigera' : 'Ny' ?> fråga</h1>
  <?php if ($error): ?><div class="err"><?=h($error)?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="quiz_id" value="<?= (int)$quiz_id ?>">
    <div class="row">
      <div>
        <label>Ordning
          <input type="number" name="q_order" value="<?= (int)$q_order ?>" min="1" required>
        </label>
      </div>
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
      <label>Rätt svar (numeriskt, används för närmast vinner)
        <input type="number" step="any" name="tiebreaker_answer" value="<?=h((string)$tiebreaker_answer)?>">
      </label>
    </fieldset>

    <button type="submit">Spara</button>
    <a href="<?=h(base_url('/admin/questions.php?quiz_id='.$quiz_id))?>">Tillbaka</a>
  </form>

  <script>
    const radios = document.querySelectorAll('input[name=type]');
    const mcq = document.getElementById('mcq');
    const tb = document.getElementById('tb');
    radios.forEach(r=>r.addEventListener('change',()=>{
      if (document.querySelector('input[name=type]:checked').value==='mcq'){mcq.style.display='block';tb.style.display='none'}
      else {mcq.style.display='none';tb.style.display='block'}
    }));
  </script>
</body>
</html>

