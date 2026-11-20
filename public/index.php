<?php
/**
 * Home: paste a message. Open to EVERYONE — no require_login() here on
 * purpose. The person who most needs SafeCedi has just received a scary
 * SMS and will not stop to create an account first.
 */
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$title = 'Check a message';
require APP_ROOT . '/templates/header.php';
?>
<h1>Got a message you're not sure about?</h1>
<p>Paste the SMS or WhatsApp forward below. SafeCedi shows you which parts look like a scam, and why. No account needed.</p>

<form class="card" method="post" action="check.php">
  <?= csrf_field() ?>
  <label for="message">Message text</label>
  <textarea id="message" name="message" required maxlength="<?= MESSAGE_MAX_LENGTH ?>"
    placeholder="e.g. Dear customer, your MoMo wallet will be blocked in 24 hours. Dial *170# and enter your PIN to verify."></textarea>
  <p class="hint">Up to <?= number_format(MESSAGE_MAX_LENGTH) ?> characters. Don't paste your own PIN or password.</p>
  <button class="primary" type="submit">Check message</button>
</form>
<?php require APP_ROOT . '/templates/footer.php'; ?>
