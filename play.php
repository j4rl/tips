<?php
require_once __DIR__ . '/auth.php';

$code = trim($_GET['code'] ?? '');
if ($code==='') { http_response_code(400); exit('Saknar kod.'); }

// Hämta tipspromenad och frågor
$stmt = $mysqli->prepare('SELECT id,title,is_active FROM quizzes WHERE join_code=? LIMIT 1');
$stmt->bind_param('s', $code);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();
if (!$quiz || !$quiz['is_active']) { http_response_code(404); exit('Tipspromenaden är inte tillgänglig.'); }
$quiz_id = (int)$quiz['id'];

$rows = $mysqli->query('SELECT * FROM questions WHERE quiz_id='.(int)$quiz_id.' ORDER BY q_order')->fetch_all(MYSQLI_ASSOC);
if (!$rows) { exit('Inga frågor ännu.'); }

// session state per quiz
$sessKey = 'play_'.$quiz_id;
if (!isset($_SESSION[$sessKey])) {
    $_SESSION[$sessKey] = [
        'idx' => 0, // aktuell fråga index
        'answers' => [], // question_id => ['option_id'=>..] eller ['tb'=>value]
        'reached_tb' => false
    ];
}
$state = &$_SESSION[$sessKey];

// hitta tie-breaker index (sista frågan ska vara tiebreaker enligt krav)
$tbIndex = null;
foreach ($rows as $i=>$q) { if ($q['type']==='tiebreaker') $tbIndex = $i; }
if ($tbIndex === null) $tbIndex = count($rows)-1; // fallback

// Hantera POST (svara på fråga)
if (is_post()) {
    $qid = (int)($_POST['question_id'] ?? 0);
    $qIndex = null; foreach ($rows as $i=>$q) { if ((int)$q['id']===$qid) { $qIndex=$i; break; } }
    if ($qIndex===null) { http_response_code(400); exit('Ogiltig fråga.'); }

    // Tillåt svar bara på aktuell fråga eller tidigare (back), ej framtida
    $maxAllowed = $state['idx'];
    if ($state['reached_tb']) { $maxAllowed = max($state['idx'], $tbIndex); }
    if ($qIndex > $maxAllowed) { header('Location: '.base_url('/play.php?code='.$code)); exit; }

    $question = $rows[$qIndex];
    if ($question['type']==='mcq') {
        $opt = (int)($_POST['option_id'] ?? 0);
        if ($opt>0) {
            $state['answers'][$qid] = ['option_id'=>$opt];
            if ($qIndex >= $state['idx']) { $state['idx'] = min($qIndex+1, count($rows)-1); }
            if ($state['idx'] >= $tbIndex) { $state['reached_tb'] = true; }
        }
        header('Location: '.base_url('/play.php?code='.$code));
        exit;
    } else { // tiebreaker
        $val = (string)($_POST['tiebreaker_value'] ?? '');
        if ($val !== '' && is_numeric($val)) {
            $state['answers'][$qid] = ['tb'=>(float)$val];
            $state['idx'] = $tbIndex; $state['reached_tb'] = true;
            header('Location: '.base_url('/finish.php?code='.$code));
            exit;
        }
        header('Location: '.base_url('/play.php?code='.$code));
        exit;
    }
}

// Hantera navigering via ?q=index, men begränsa framåt och tillbaka på TB
$reqIndex = isset($_GET['q']) ? (int)$_GET['q'] : $state['idx'];
if ($state['reached_tb']) { $reqIndex = max($reqIndex, $tbIndex); }
$maxIndex = max(min($reqIndex, $state['idx']), 0);
$state['idx'] = $maxIndex; // håll i synk

$idx = $state['idx'];
$q = $rows[$idx];

// Hämta alternativ för mcq
$options = [];
if ($q['type']==='mcq') {
    $res = $mysqli->query('SELECT id,opt_order,text FROM options WHERE question_id='.(int)$q['id'].' ORDER BY opt_order');
    while ($r = $res->fetch_assoc()) { $options[] = $r; }
}

$atTB = ($idx === $tbIndex);
?>
<!doctype html>
<html lang="sv">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?=h($quiz['title'])?></title>
  <style>
    body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;margin:1rem}
    .container{max-width:720px;margin:0 auto}
    .qimg{max-width:100%;border-radius:8px;margin:.5rem 0}
    .card{border:1px solid #ddd;border-radius:10px;padding:1rem;margin-top:1rem}
    .opt{display:block;border:1px solid #bbb;border-radius:8px;padding:.7rem;margin:.5rem 0}
    .nav{display:flex;justify-content:space-between;margin-top:1rem}
    .btn{display:inline-block;padding:.6rem .9rem;border:1px solid #777;border-radius:8px;text-decoration:none}
    .muted{color:#666}
    input[type=number], input[type=text]{width:100%;padding:.7rem;border:1px solid #bbb;border-radius:8px}
  </style>
</head>
<body>
  <div class="container">
    <div class="muted">Fråga <?= ($idx+1) ?> av <?= count($rows) ?></div>
    <h2><?= nl2br(h($q['text'])) ?></h2>
    <?php if ($q['image_path']): ?><img class="qimg" src="<?=h(base_url('/'.ltrim($q['image_path'],'/')))?>"><?php endif; ?>

    <form method="post" class="card">
      <input type="hidden" name="question_id" value="<?= (int)$q['id'] ?>">
      <?php if ($q['type']==='mcq'): ?>
        <?php foreach ($options as $op): ?>
        <label class="opt">
          <input type="radio" name="option_id" value="<?= (int)$op['id'] ?>" required> <?=h($op['text'])?>
        </label>
        <?php endforeach; ?>
        <div class="nav">
          <?php if (!$atTB && $idx>0): ?>
            <a class="btn" href="<?=h(base_url('/play.php?code='.$code.'&q='.($idx-1)))?>">Tillbaka</a>
          <?php else: ?><span></span><?php endif; ?>
          <button class="btn" type="submit">Nästa</button>
        </div>
      <?php else: ?>
        <label>Utslagsfråga – ange ett numeriskt svar
          <input type="number" step="any" name="tiebreaker_value" required>
        </label>
        <div class="nav">
          <!-- När man kommer till utslagsfrågan kan man inte bläddra bakåt -->
          <span></span>
          <button class="btn" type="submit">Fortsätt</button>
        </div>
      <?php endif; ?>
    </form>
  </div>
</body>
</html>

