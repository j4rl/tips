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

// Hämta frågor
$stmt = $mysqli->prepare('SELECT * FROM questions WHERE quiz_id=? ORDER BY q_order ASC');
$stmt->bind_param('i', $quiz_id);
$stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle add from bank
if (isset($_GET['add_bank_id'])) {
  $bank_id = (int)$_GET['add_bank_id'];
  if ($bank_id) { add_bank_question_to_quiz($bank_id, (int)$quiz['id'], $mysqli); }
  header('Location: ' . base_url('/admin/questions.php?quiz_id='.$quiz['id']));
  exit;
}
?>
<!doctype html>
<html lang="sv">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Frågor – <?=h($quiz['title'])?></title>
  <link rel="stylesheet" href="<?=h(base_url('/assets/style.css'))?>">
  <?php $prefs = get_user_settings((int)$user['id'], $mysqli); ?>
  <style>
    :root { <?php if (($prefs['theme_mode'] ?? 'system') === 'light'): ?>color-scheme: light;<?php elseif (($prefs['theme_mode'] ?? 'system') === 'dark'): ?>color-scheme: dark;<?php else: ?>color-scheme: light dark;<?php endif; ?> --accent: light-dark(<?=h($prefs['main_color_light'])?>, <?=h($prefs['main_color_dark'])?>); }
  </style>
</head>
<body>
  <header class="site-header">
    <div><h1 style="margin:0">Frågor</h1></div>
    <div>
      <a class="btn btn-accent" href="<?=h(base_url('/admin/quiz_form.php'))?>">+ Ny tipspromenad</a>
      <a class="btn" href="<?=h(base_url('/admin/logout.php'))?>">Logga ut</a>
      <a class="btn" href="<?=h(base_url('/admin/profile.php'))?>"><?="✦ ".h($user['name'])?></a>
    </div>
  </header>
  <h1>Frågor – <?=h($quiz['title'])?></h1>
  <p>
     <a class="btn" href="<?=h(base_url('/admin/quiz_form.php?id='.$quiz['id']))?>">Redigera tipspromenad</a>
     <a class="btn" href="<?=h(base_url('/admin/question_form.php?quiz_id='.$quiz['id']))?>">+ Lägg till ny fråga</a>
     <a class="btn" href="<?=h(base_url('/admin/questions.php?quiz_id='.$quiz['id'].'&bank=1'))?>">Mina frågor</a>
     <a class="btn" href="<?=h(base_url('/admin/submissions.php?quiz_id='.$quiz['id']))?>">Resultat</a>
     <a class="btn" href="<?=h(base_url('/admin/print.php?quiz_id='.$quiz['id']))?>">Utskrift (frågor)</a>
     <a class="btn" href="<?=h(base_url('/admin/print_qr.php?quiz_id='.$quiz['id']))?>">Utskrift (QR)</a>
     <a class="btn" href="<?=h(base_url('/admin/index.php'))?>">Tillbaka</a>
  </p>
  <p>Startlänk: <code><?=h((isset($_SERVER['HTTP_HOST'])?('http'.(!empty($_SERVER['HTTPS'])?'s':'').'://'.$_SERVER['HTTP_HOST']):'').base_url('/play.php').'?code='.h($quiz['join_code']))?></code></p>

  <?php if (isset($_GET['bank'])): ?>
    <?php ensure_question_bank_schema($mysqli); $bank = $mysqli->query('SELECT * FROM bank_questions WHERE user_id='.(int)$user['id'].' ORDER BY created_at DESC'); ?>
    <h2>Mina frågor</h2>
    <?php if ($bank && $bank->num_rows>0): ?>
      <div class="list">
      <?php while ($bq = $bank->fetch_assoc()): ?>
        <div class="list-item">
          <div class="list-col type"><?= $bq['type']==='mcq'?'Flervalsfråga':'Utslagsfråga' ?></div>
          <div class="list-col text"><?= nl2br(h($bq['text'])) ?></div>
          <div class="list-col actions">
            <a class="btn" href="<?=h(base_url('/admin/questions.php?quiz_id='.$quiz['id'].'&add_bank_id='.(int)$bq['id']))?>">Lägg till i denna tipspromenad</a>
          </div>
        </div>
      <?php endwhile; ?>
      </div>
    <?php else: ?>
      <p>Inga frågor i din frågebank ännu.</p>
    <?php endif; ?>
  <?php elseif (!$questions): ?>
    <p>Inga frågor ännu. Lägg till minst en, och avsluta med en utslagsfråga.</p>
  <?php else: ?>
  <div class="list-head">
    <div>Ordning</div><div>Typ</div><div>Fråga</div><div>Bild</div><div>Åtgärder</div>
  </div>
  <div id="qlist" class="list">
    <?php foreach ($questions as $q): ?>
      <div class="list-item" draggable="true" data-id="<?= (int)$q['id'] ?>">
        <div class="list-col ord"><span class="ordnum"><?= (int)$q['q_order'] ?></span> <span class="drag-handle" title="Dra för att ändra" aria-label="Flytta" role="button">⋮⋮</span></div>
        <div class="list-col type"><?= $q['type']==='mcq'?'Flervalsfråga':'Utslagsfråga' ?></div>
        <div class="list-col text"><?= nl2br(h($q['text'])) ?></div>
        <div class="list-col img"><?php if ($q['image_path']): ?><img class="img" src="<?=h(base_url('/'.ltrim($q['image_path'],'/')))?>"><?php endif; ?></div>
        <div class="list-col actions">
          <a class="btn" href="<?=h(base_url('/admin/question_form.php?id='.$q['id'].'&quiz_id='.$quiz['id']))?>">Redigera</a>
          <a class="btn" href="<?=h(base_url('/admin/question_form.php?delete=1&id='.$q['id'].'&quiz_id='.$quiz['id']))?>" onclick="return confirm('Radera frågan?');">Radera</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <div id="saveMsg" class="small" style="margin-top:.5rem;color:#555;"></div>
  <script>
  // Add-from-bank handler (via GET param)
  (function(){
    var m = location.search.match(/add_bank_id=(\d+)/);
    if (!m) return;
    // Basic feedback
    var msg = document.getElementById('saveMsg'); if (msg) msg.textContent = 'Fråga tillagd från frågebank.';
  })();
  </script>
  <script>
  (function(){
    var list = document.getElementById('qlist');
    if (!list) return;
    var dragging;
    var rows = Array.from(list.querySelectorAll('.list-item'));

    function refreshRows(){ rows = Array.from(list.querySelectorAll('.list-item')); }
    function indexOfRow(row){ return rows.indexOf(row); }

    function handleDragStart(e){
      if (!e.target || !e.target.closest('.drag-handle')) { e.preventDefault(); return false; }
      dragging = this; e.dataTransfer.effectAllowed = 'move'; this.style.opacity = '0.4';
    }
    function handleDragEnd(){ this.style.opacity = ''; dragging = null; rows.forEach(function(r){ r.classList.remove('over'); }); }
    function handleDragOver(e){ if (!dragging) return; e.preventDefault(); e.dataTransfer.dropEffect = 'move'; this.classList.add('over'); }
    function handleDragLeave(){ this.classList.remove('over'); }
    function handleDrop(e){ e.stopPropagation(); this.classList.remove('over'); if (dragging === this) return;
      var from = indexOfRow(dragging); var to = indexOfRow(this);
      if (from < 0 || to < 0) return;
      if (from < to) { this.after(dragging); } else { this.before(dragging); }
      refreshRows();
      // Update visible order numbers
      rows.forEach(function(r, i){ var c=r.querySelector('.ordnum'); if(c){ c.textContent = (i+1); } });
      // Send to server
      var ids = rows.map(function(r){ return r.getAttribute('data-id'); });
      var msg = document.getElementById('saveMsg');
      msg.textContent = 'Sparar...';
      fetch('<?=h(base_url('/admin/reorder_questions.php'))?>', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ quiz_id: <?= (int)$quiz['id'] ?>, order: ids })
      }).then(function(r){ return r.json(); }).then(function(j){
        msg.textContent = j && j.ok ? 'Ordning uppdaterad.' : 'Kunde inte spara ordning.';
      }).catch(function(){ msg.textContent = 'Kunde inte spara ordning.'; });
    }

    rows.forEach(function(r){
      r.addEventListener('dragstart', handleDragStart);
      r.addEventListener('dragend', handleDragEnd);
      r.addEventListener('dragover', handleDragOver);
      r.addEventListener('dragleave', handleDragLeave);
      r.addEventListener('drop', handleDrop);
    });
  })();
  </script>
  <?php endif; ?>
</body>
</html>
