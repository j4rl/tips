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
?>
<!doctype html>
<html lang="sv">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Frågor – <?=h($quiz['title'])?></title>
  <link rel="stylesheet" href="<?=h(base_url('/assets/style.css'))?>">
</head>
<body>
  <h1>Frågor – <?=h($quiz['title'])?></h1>
  <p><a class="btn" href="<?=h(base_url('/admin/quiz_form.php?id='.$quiz['id']))?>">Redigera tipspromenad</a>
     <a class="btn" href="<?=h(base_url('/admin/question_form.php?quiz_id='.$quiz['id']))?>">+ Lägg till fråga</a>
     <a class="btn" href="<?=h(base_url('/admin/submissions.php?quiz_id='.$quiz['id']))?>">Resultat</a>
     <a class="btn" href="<?=h(base_url('/admin/print.php?quiz_id='.$quiz['id']))?>">Utskrift (frågor)</a>
     <a class="btn" href="<?=h(base_url('/admin/print_qr.php?quiz_id='.$quiz['id']))?>">Utskrift (QR)</a>
     <a class="btn" href="<?=h(base_url('/admin/index.php'))?>">Tillbaka</a>
  </p>
  <p>Startlänk: <code><?=h((isset($_SERVER['HTTP_HOST'])?('http'.(!empty($_SERVER['HTTPS'])?'s':'').'://'.$_SERVER['HTTP_HOST']):'').base_url('/play.php').'?code='.h($quiz['join_code']))?></code></p>

  <?php if (!$questions): ?>
    <p>Inga frågor ännu. Lägg till minst en, och avsluta med en utslagsfråga.</p>
  <?php else: ?>
  <table id="qlist">
    <tr><th>Ordning</th><th>Typ</th><th>Fråga</th><th>Bild</th><th>Åtgärder</th></tr>
    <?php foreach ($questions as $q): ?>
      <tr draggable="true" data-id="<?= (int)$q['id'] ?>">
        <td class="ord">
          <?= (int)$q['q_order'] ?>
          <span class="drag-handle" title="Dra för att ändra" aria-label="Flytta" role="button">⋮⋮</span>
        </td>
        <td><?= $q['type']==='mcq'?'Flervalsfråga':'Utslagsfråga' ?></td>
        <td><?= nl2br(h($q['text'])) ?></td>
        <td><?php if ($q['image_path']): ?><img class="img" src="<?=h(base_url('/'.ltrim($q['image_path'],'/')))?>"><?php endif; ?></td>
        <td>
          <a class="btn" href="<?=h(base_url('/admin/question_form.php?id='.$q['id'].'&quiz_id='.$quiz['id']))?>">Redigera</a>
          <a class="btn" href="<?=h(base_url('/admin/question_form.php?delete=1&id='.$q['id'].'&quiz_id='.$quiz['id']))?>" onclick="return confirm('Radera frågan?');">Radera</a>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  <div id="saveMsg" class="small" style="margin-top:.5rem;color:#555;"></div>
  <script>
  (function(){
    var table = document.getElementById('qlist');
    if (!table) return;
    var dragging;
    var rows = Array.from(table.querySelectorAll('tr')).slice(1);

    function refreshRows(){ rows = Array.from(table.querySelectorAll('tr')).slice(1); }
    function indexOfRow(row){ return rows.indexOf(row); }

    function handleDragStart(e){
      // Only start dragging when starting from the handle
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
      rows.forEach(function(r, i){ var c=r.querySelector('.ord'); if(c){ c.firstChild.nodeValue = (i+1)+' '; } });
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
