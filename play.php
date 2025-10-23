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

// Session state per quiz
$sessKey = 'play_'.$quiz_id;
if (!isset($_SESSION[$sessKey])) {
    $_SESSION[$sessKey] = [
        'idx' => 0,
        'answers' => [], // question_id => ['option_id'=>..] eller ['tb'=>value]
    ];
}
$state = &$_SESSION[$sessKey];

// Finns tiebreaker?
$tbIndex = null; foreach ($rows as $i=>$qr) { if ($qr['type']==='tiebreaker') { $tbIndex = $i; break; } }

// POST: spara svar och navigera
if (is_post()) {
    $qid = (int)($_POST['question_id'] ?? 0);
    $qIndex = null; foreach ($rows as $i=>$qq) { if ((int)$qq['id']===$qid) { $qIndex=$i; break; } }
    if ($qIndex===null) { http_response_code(400); exit('Ogiltig fråga.'); }
    $question = $rows[$qIndex];

    if ($question['type']==='mcq') {
        $opt = (int)($_POST['option_id'] ?? 0);
        if ($opt>0) {
            $state['answers'][$qid] = ['option_id'=>$opt];
            if ($qIndex >= $state['idx']) { $state['idx'] = min($qIndex+1, count($rows)-1); }
        }
        if (($_POST['go'] ?? '') === 'finish') {
            header('Location: '.base_url('/finish.php?code='.$code));
        } else {
            header('Location: '.base_url('/play.php?code='.$code.'&q='.(int)min($qIndex+1,count($rows)-1)));
        }
        exit;
    } else { // tiebreaker
        $val = (string)($_POST['tiebreaker_value'] ?? '');
        if ($val !== '' && is_numeric($val)) {
            $state['answers'][$qid] = ['tb'=>(float)$val];
            $state['idx'] = max($state['idx'], $qIndex);
            if (($_POST['go'] ?? '') === 'finish') {
                header('Location: '.base_url('/finish.php?code='.$code));
            } else {
                header('Location: '.base_url('/play.php?code='.$code.'&q='.(int)min($qIndex+1,count($rows)-1)));
            }
            exit;
        }
        header('Location: '.base_url('/play.php?code='.$code));
        exit;
    }
}

// GET-navigering via ?q=index
$reqIndex = isset($_GET['q']) ? (int)$_GET['q'] : $state['idx'];
$reqIndex = max(min($reqIndex, count($rows)-1), 0);
$state['idx'] = $reqIndex;

$idx = $state['idx'];
$q = $rows[$idx];

// Hämta alternativ för mcq
$options = [];
if ($q['type']==='mcq') {
    $res = $mysqli->query('SELECT id,opt_order,text FROM options WHERE question_id='.(int)$q['id'].' ORDER BY opt_order');
    while ($r = $res->fetch_assoc()) { $options[] = $r; }
}

?>
<!doctype html>
<html lang="sv">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?=h($quiz['title'])?></title>
  <link rel="stylesheet" href="<?=h(base_url('/assets/style.css'))?>">
</head>
<body>
  <div class="container">
    <div class="muted">Fråga <?= ($idx+1) ?> av <?= count($rows) ?></div>
    <h2><?= nl2br(h($q['text'])) ?></h2>
    <?php if ($q['image_path']): ?><img class="qimg" src="<?=h(base_url('/'.ltrim($q['image_path'],'/')))?>"><?php endif; ?>

    <form method="post" class="card">
      <input type="hidden" name="question_id" value="<?= (int)$q['id'] ?>">
      <?php if ($q['type']==='mcq'): ?>
        <?php $sel = $state['answers'][(int)$q['id']]['option_id'] ?? null; ?>
        <?php foreach ($options as $op): ?>
        <label class="opt">
          <input type="radio" name="option_id" value="<?= (int)$op['id'] ?>" <?= ($sel==(int)$op['id'])?'checked':'' ?> required> <?=h($op['text'])?>
        </label>
        <?php endforeach; ?>
        <div class="nav">
          <?php if ($idx>0): ?>
            <a class="btn" href="<?=h(base_url('/play.php?code='.$code.'&q='.($idx-1)))?>">Tillbaka</a>
          <?php else: ?><span></span><?php endif; ?>
          <?php if ($idx < count($rows)-1): ?>
            <button class="btn" type="submit" name="go" value="next">Nästa</button>
          <?php else: ?>
            <button class="btn" type="submit" name="go" value="finish">Skicka in</button>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <?php $tbs = $state['answers'][(int)$q['id']]['tb'] ?? ''; ?>
        <label>Utslagsfråga – ange ett numeriskt svar
          <input type="number" step="any" name="tiebreaker_value" value="<?=h((string)$tbs)?>" required>
        </label>
        <div class="nav">
          <?php if ($idx>0): ?>
            <a class="btn" href="<?=h(base_url('/play.php?code='.$code.'&q='.($idx-1)))?>">Tillbaka</a>
          <?php else: ?><span></span><?php endif; ?>
          <?php if ($idx < count($rows)-1): ?>
            <button class="btn" type="submit" name="go" value="next">Nästa</button>
          <?php else: ?>
            <button class="btn" type="submit" name="go" value="finish">Skicka in</button>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </form>
  </div>
</body>
</html>

