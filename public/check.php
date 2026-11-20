<?php
/**
 * Score a pasted message. Anonymous submission is allowed: there is no
 * require_login() on this page, but CSRF and server-side validation still
 * apply to everyone.
 *
 * Integration point with M3's engine (see docs/integration-contract.md):
 *   engine/evaluate.php defines  evaluate(string $text): array
 *   returning ['score' => int, 'band' => ?string,
 *              'signals' => [['rule' => string, 'weight' => int, 'match' => string], ...]]
 */
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

require_post();
csrf_verify();

$text = post_string('message');
$text = str_replace("\0", '', $text);

$error = null;
if ($text === '') {
    $error = 'Paste a message to check.';
} elseif (!mb_check_encoding($text, 'UTF-8')) {
    $error = 'That text could not be read. Try copying it again.';
} elseif (mb_strlen($text) > MESSAGE_MAX_LENGTH) {
    $error = 'That message is too long (maximum ' . number_format(MESSAGE_MAX_LENGTH) . ' characters).';
}

if ($error !== null) {
    flash('error', $error);
    redirect('index.php');
}

$enginePath = APP_ROOT . '/engine/evaluate.php';
if (is_file($enginePath)) {
    require_once $enginePath;
}
$result = function_exists('evaluate') ? evaluate($text) : null;
$viewer = current_user();

$title = 'Result';
require APP_ROOT . '/templates/header.php';
?>
<h1>Result</h1>

<?php if ($result === null): ?>
  <div class="card">
    <p><strong>The scoring engine is not connected yet.</strong></p>
    <p class="hint">Your message was received and validated. M3's <code>evaluate()</code> plugs in at <code>engine/evaluate.php</code>.</p>
  </div>
<?php else: ?>
  <div class="card">
    <p class="score"><?= e((string) (int) ($result['score'] ?? 0)) ?></p>
    <?php if (!empty($result['band'])): ?><p><strong><?= e($result['band']) ?></strong></p><?php endif; ?>
    <?php $signals = $result['signals'] ?? []; ?>
    <?php if ($signals): ?>
      <h2>Why</h2>
      <ul class="signals">
        <?php foreach ($signals as $s): ?>
          <li>
            <strong><?= e($s['rule'] ?? 'rule') ?></strong>
            (+<?= e((string) (int) ($s['weight'] ?? 0)) ?>):
            “<?= e($s['match'] ?? '') ?>”
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="hint">No warning signs matched.</p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<h2>Your message</h2>
<p class="message-echo"><?= e($text) ?></p>
<p class="hint">Checked as <?= $viewer ? e($viewer['display_name']) : 'an anonymous visitor' ?>.</p>
<p><a href="index.php">Check another message</a></p>
<?php require APP_ROOT . '/templates/footer.php'; ?>
