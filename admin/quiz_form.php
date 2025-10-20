<?php
require_once __DIR__ . '/../auth.php';
require_login();
$user = current_user();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$title = '';
$description = '';
$join_code = '';
$is_active = 1;

if ($id) {
    $stmt = $mysqli->prepare('SELECT * FROM quizzes WHERE id=? AND user_id=?');
    $stmt->bind_param('ii', $id, $user['id']);
    $stmt->execute();
    $quiz = $stmt->get_result()->fetch_assoc();
    if (!$quiz) { http_response_code(404); exit('Hittades inte.'); }
    $title = $quiz['title'];
    $description = $quiz['description'];
    $join_code = $quiz['join_code'];
    $is_active = (int)$quiz['is_active'];
}

$error='';
if (is_post()) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $join_code = trim($_POST['join_code'] ?? '');
    if ($title==='') $error='Titel krävs.';
    if ($join_code==='') $join_code = rand_code(6);

    if (!$error) {
        if ($id) {
            $stmt = $mysqli->prepare('UPDATE quizzes SET title=?, description=?, join_code=?, is_active=? WHERE id=? AND user_id=?');
            $stmt->bind_param('sssiii', $title, $description, $join_code, $is_active, $id, $user['id']);
            $ok = $stmt->execute();
        } else {
            $stmt = $mysqli->prepare('INSERT INTO quizzes(user_id,title,description,join_code,is_active) VALUES(?,?,?,?,?)');
            $stmt->bind_param('isssi', $user['id'], $title, $description, $join_code, $is_active);
            $ok = $stmt->execute();
            if ($ok) $id = $stmt->insert_id;
        }
        if ($ok) { header('Location: ' . base_url('/admin/questions.php?quiz_id='.$id)); exit; }
        $error = 'Kunde inte spara. Kontrollera att join‑kod är unik.';
    }
}
?>
<!doctype html>
<html lang="sv">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $id? 'Redigera' : 'Ny' ?> tipspromenad</title>
  <style>
    body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;margin:1.5rem;max-width:800px}
    input[type=text], textarea{width:100%;padding:.6rem;margin:.3rem 0}
    .err{color:#b00020;margin:.5rem 0}
    .row{display:flex;gap:1rem;align-items:center}
    .row>*{flex:1}
  </style>
</head>
<body>
  <h1><?= $id? 'Redigera' : 'Ny' ?> tipspromenad</h1>
  <?php if ($error): ?><div class="err"><?=h($error)?></div><?php endif; ?>
  <form method="post">
    <label>Titel
      <input type="text" name="title" value="<?=h($title)?>" required>
    </label>
    <label>Beskrivning
      <textarea name="description" rows="4"><?=h($description)?></textarea>
    </label>
    <div class="row">
      <label>Join‑kod (för QR/URL)
        <input type="text" name="join_code" value="<?=h($join_code?:rand_code(6))?>" maxlength="32" required>
      </label>
      <label style="flex:0 0 auto"><input type="checkbox" name="is_active" <?= $is_active? 'checked':'' ?>> Aktiv</label>
    </div>
    <button type="submit">Spara</button>
    <a href="<?=h(base_url('/admin/index.php'))?>">Tillbaka</a>
  </form>
</body>
</html>

