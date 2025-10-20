<?php
require_once __DIR__ . '/../auth.php';
if (current_user()) { header('Location: ' . base_url('/admin/index.php')); exit; }

$error = '';
if (is_post()) {
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    if ($email === '' || $pass === '') {
        $error = 'Fyll i e‑post och lösenord.';
    } else {
        $stmt = $mysqli->prepare('SELECT id,name,email,password_hash FROM users WHERE email=? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res->fetch_assoc();
        if ($user && password_verify($pass, $user['password_hash'])) {
            login_user($user);
            header('Location: ' . base_url('/admin/index.php'));
            exit;
        }
        $error = 'Ogiltiga inloggningsuppgifter.';
    }
}
?>
<!doctype html>
<html lang="sv">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Logga in – Tipspromenad</title>
  <style>
    body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;margin:2rem;}
    form{max-width:360px}
    .err{color:#b00020;margin:.5rem 0}
    input,button{font-size:1rem;padding:.6rem;width:100%;margin:.4rem 0}
    a{color:#0366d6}
  </style>
  </head>
<body>
  <h1>Logga in</h1>
  <?php if ($error): ?><div class="err"><?=h($error)?></div><?php endif; ?>
  <form method="post">
    <label>E‑post
      <input type="email" name="email" required>
    </label>
    <label>Lösenord
      <input type="password" name="password" required>
    </label>
    <button type="submit">Logga in</button>
  </form>
  <p>Ingen användare? <a href="<?=h(base_url('/admin/register.php'))?>">Registrera</a></p>
</body>
</html>

