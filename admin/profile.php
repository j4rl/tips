<?php
require_once __DIR__ . '/../auth.php';
require_login();
$user = current_user();

$msg=''; $error='';
if (is_post()) {
    $theme = $_POST['theme_mode'] ?? 'system';
    $light = trim($_POST['main_color_light'] ?? '#0d6efd');
    $dark = trim($_POST['main_color_dark'] ?? '#0d6efd');
    $hex = '/^#([0-9a-fA-F]{6})$/';
    if (!preg_match($hex, $light)) $error = 'Ogiltig färg (ljus).';
    if (!$error && !preg_match($hex, $dark)) $error = 'Ogiltig färg (mörk).';
    if (!$error) {
        if (save_user_settings((int)$user['id'], $theme, strtoupper($light), strtoupper($dark), $mysqli)) {
            $msg = 'Inställningar sparade.';
        } else { $error = 'Kunde inte spara inställningar.'; }
    }
}

// Preferences and data for shortcuts
$prefs = get_user_settings((int)$user['id'], $mysqli);
$stmt = $mysqli->prepare('SELECT id,title FROM quizzes WHERE user_id=? ORDER BY created_at DESC');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$quizzes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
ensure_question_bank_schema($mysqli);
$bank = $mysqli->query('SELECT id,text,type,image_path,created_at FROM bank_questions WHERE user_id='.(int)$user['id'].' ORDER BY created_at DESC');

?>
<!doctype html>
<html lang="sv">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Profil – <?=h($user['name'])?></title>
  <link rel="stylesheet" href="<?=h(base_url('/assets/style.css'))?>">
  <style>
    :root { <?php if (($prefs['theme_mode'] ?? 'system') === 'light'): ?>color-scheme: light;<?php elseif (($prefs['theme_mode'] ?? 'system') === 'dark'): ?>color-scheme: dark;<?php else: ?>color-scheme: light dark;<?php endif; ?> --accent: light-dark(<?=h($prefs['main_color_light'])?>, <?=h($prefs['main_color_dark'])?>); }
    .stack{display:grid;gap:.6rem;max-width:520px}
  </style>
</head>
<body>
  <header class="site-header">
    <div><h1 style="margin:0">Profil</h1></div>
    <div>
      <a class="btn btn-accent" href="<?=h(base_url('/admin/quiz_form.php'))?>">+ Ny tipspromenad</a>
      <a class="btn" href="<?=h(base_url('/admin/logout.php'))?>">Logga ut</a>
      <a class="btn" href="<?=h(base_url('/admin/profile.php'))?>"><?="✦ ".h($user['name'])?></a>
    </div>
  </header>
  <nav><a class="btn" href="<?=h(base_url('/admin/index.php'))?>">Tillbaka</a></nav>
  <?php if ($msg): ?><div class="badge badge-success"><?=h($msg)?></div><?php endif; ?>
  <?php if ($error): ?><div class="err" style="color:var(--color-error)"><?=h($error)?></div><?php endif; ?>
  <form method="post" class="stack">
    <h2>Inställningar</h2>
    <label>Tema
      <select name="theme_mode">
        <option value="system" <?= $prefs['theme_mode']==='system'?'selected':'' ?>>System</option>
        <option value="light" <?= $prefs['theme_mode']==='light'?'selected':'' ?>>Ljust</option>
        <option value="dark" <?= $prefs['theme_mode']==='dark'?'selected':'' ?>>Mörkt</option>
      </select>
    </label>
    <fieldset>
    <legend>Färginställningar</legend>
    <div class="row">
      <label>Huvudfärg<br>(ljust läge)
        <input type="color" name="main_color_light" value="<?=h($prefs['main_color_light'])?>">
      </label>
      <label>Huvudfärg<br>(mörkt läge)
        <input type="color" name="main_color_dark" value="<?=h($prefs['main_color_dark'])?>">
      </label>
    </div>
    </fieldset>
    <div>
      <button type="submit" class="btn-accent">Spara</button>
      
    </div>
  </form>


  <h2>Mina frågor</h2>
  <p><a class="btn btn-accent" href="<?=h(base_url('/admin/bank_question_form.php'))?>">+ Ny bankfråga</a></p>

  <?php if ($bank && $bank->num_rows>0): ?>
    <div class="list-head">
      <div></div><div>Typ</div><div>Fråga</div><div>Bild</div><div>Åtgärder</div>
    </div>
    <div class="list" style="margin-top: .8rem;">
      <?php while ($bq = $bank->fetch_assoc()): ?>
        <div class="list-item"><div></div>
          <div class="list-col type" style="min-width:110px;">
            <?= $bq['type']==='mcq'?'Flervalsfråga':'Utslagsfråga' ?>
          </div>
          <div class="list-col text">
            <?= nl2br(h($bq['text'])) ?>
          </div>
          <div class="list-col img"><?= !empty($bq['image_path']) ? ('<img class="img" src="'.h(base_url('/'.ltrim($bq['image_path'],'/'))).'">') : '' ?></div>
          <div class="list-col actions">
            <a class="btn" href="<?=h(base_url('/admin/bank_question_form.php?id='.(int)$bq['id']))?>">Redigera</a>
            <a class="btn" href="<?=h(base_url('/admin/bank_delete.php?id='.(int)$bq['id']))?>" onclick="return confirm('Radera bankfrågan?');">Radera</a>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
  <?php else: ?>
    <p>Du har inga frågor i din frågebank ännu.</p>
  <?php endif; ?>
</body>
</html>

