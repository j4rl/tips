<?php
require_once __DIR__ . '/../auth.php';
require_login();
$user = current_user();
$id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);
if (!$id) { http_response_code(400); exit('Saknar id.'); }

$stmt = $mysqli->prepare('SELECT * FROM quizzes WHERE id=? AND user_id=?');
$stmt->bind_param('ii', $id, $user['id']);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();
if (!$quiz) { http_response_code(404); exit('Hittades inte.'); }

$error='';
if (is_post()) {
    $mode = $_POST['mode'] ?? '';
    if ($mode==='save_bank') {
        // copy questions to bank, then delete quiz
        copy_quiz_to_bank($id, (int)$user['id'], $mysqli);
    }
    // Delete quiz (CASCADE will remove questions/options)
    $stmt = $mysqli->prepare('DELETE FROM quizzes WHERE id=? AND user_id=?');
    $stmt->bind_param('ii', $id, $user['id']);
    if ($stmt->execute()) {
        header('Location: ' . base_url('/admin/index.php'));
        exit;
    }
    $error = 'Kunde inte radera.';
}
?>
<!doctype html>
<html lang="sv">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Radera – <?=h($quiz['title'])?></title>
  <link rel="stylesheet" href="<?=h(base_url('/assets/style.css'))?>">
</head>
<body>
  <h1>Radera tipspromenad</h1>
  <?php if ($error): ?><div class="err"><?=h($error)?></div><?php endif; ?>
  <p>Vill du spara frågorna i din frågebank innan radering?</p>
  <form method="post">
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <div class="row" style="align-items:flex-start">
      <button class="btn" type="submit" name="mode" value="delete_all" style="border-color:#c00;color:#c00">Radera utan att spara</button>
      <button class="btn" type="submit" name="mode" value="save_bank">Spara i frågebank och radera</button>
    </div>
    <p><a class="btn" href="<?=h(base_url('/admin/quiz_form.php?id='.$id))?>">Avbryt</a></p>
  </form>
</body>
</html>

