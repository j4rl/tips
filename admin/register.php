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
  <link rel="stylesheet" href="<?=h(base_url('/assets/style.css'))?>">
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
    <button type="submit" class="btn-accent">Skapa konto</button>
  </form>
  <p>Har du konto? <a href="<?=h(base_url('/admin/login.php'))?>">Logga in</a></p>
</body>
</html>
