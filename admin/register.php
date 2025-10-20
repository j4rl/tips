<?php
require_once __DIR__ . '/../auth.php';
if (current_user()) { header('Location: ' . base_url('/admin/index.php')); exit; }

$error='';
if (is_post()) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    if ($name===''||$email===''||$password==='') {
        $error='Fyll i alla fält.';
    } elseif ($password !== $password2) {
        $error='Lösenorden matchar inte.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)');
        $stmt->bind_param('sss',$name,$email,$hash);
        if ($stmt->execute()) {
            $id = $stmt->insert_id;
            login_user(['id'=>$id,'name'=>$name,'email'=>$email]);
            header('Location: ' . base_url('/admin/index.php'));
            exit;
        } else {
            if ($mysqli->errno === 1062) $error='E‑posten är redan registrerad.'; else $error='Kunde inte skapa användare.';
        }
    }
}
?>
<!doctype html>
<html lang="sv">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Registrera – Tipspromenad</title>
  <style>
    body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;margin:2rem;}
    form{max-width:420px}
    .err{color:#b00020;margin:.5rem 0}
    input,button{font-size:1rem;padding:.6rem;width:100%;margin:.4rem 0}
    a{color:#0366d6}
  </style>
</head>
<body>
  <h1>Registrera</h1>
  <?php if ($error): ?><div class="err"><?=h($error)?></div><?php endif; ?>
  <form method="post">
    <label>Namn
      <input name="name" required>
    </label>
    <label>E‑post
      <input type="email" name="email" required>
    </label>
    <label>Lösenord
      <input type="password" name="password" minlength="6" required>
    </label>
    <label>Upprepa lösenord
      <input type="password" name="password2" minlength="6" required>
    </label>
    <button type="submit">Skapa konto</button>
  </form>
  <p>Har du konto? <a href="<?=h(base_url('/admin/login.php'))?>">Logga in</a></p>
</body>
</html>

