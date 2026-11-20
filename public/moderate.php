<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

// First line of real logic: the role check. Hiding the nav link from
// reporters is cosmetic; THIS is what actually stops them.
$user = require_role(ROLE_MODERATOR);

$title = 'Moderation';
require APP_ROOT . '/templates/header.php';
?>
<h1>Moderation</h1>
<div class="card">
  <p>Signed in as moderator <strong><?= e($user['display_name']) ?></strong>.</p>
  <p class="hint">The report queue, rule weights and allowlist land here in Phase 02.</p>
</div>
<?php require APP_ROOT . '/templates/footer.php'; ?>
