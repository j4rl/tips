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
$prefs = get_user_settings((int)$user['id'], $mysqli);
?>
<!doctype html>
<html lang="sv">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Profil – <?=h($user['name'])?></title>
  <link rel="stylesheet" href="<?=h(base_url('/assets/style.css'))?>">
  <style>
    .swatch{display:inline-block;width:1.25rem;height:1.25rem;border-radius:4px;border:1px solid var(--color-border)}
    .stack{display:grid;gap:.6rem;max-width:520px}
  </style>
</head>
<body>
  <h1>Profil</h1>
  <?php if ($msg): ?><div class="badge badge-success"><?=h($msg)?></div><?php endif; ?>
  <?php if ($error): ?><div class="err" style="color:var(--color-error)"><?=h($error)?></div><?php endif; ?>
  <form method="post" class="stack">
    <label>Tema
      <select name="theme_mode">
        <option value="system" <?= $prefs['theme_mode']==='system'?'selected':'' ?>>System</option>
        <option value="light" <?= $prefs['theme_mode']==='light'?'selected':'' ?>>Ljust</option>
        <option value="dark" <?= $prefs['theme_mode']==='dark'?'selected':'' ?>>Mörkt</option>
      </select>
    </label>
    <label>Huvudfärg (ljust läge)
      <input type="color" name="main_color_light" value="<?=h($prefs['main_color_light'])?>">
    </label>
    <label>Huvudfärg (mörkt läge)
      <input type="color" name="main_color_dark" value="<?=h($prefs['main_color_dark'])?>">
    </label>
    <div>
      <button type="submit">Spara</button>
      <a class="btn" href="<?=h(base_url('/admin/index.php'))?>">Tillbaka</a>
    </div>
  </form>
</body>
</html>

