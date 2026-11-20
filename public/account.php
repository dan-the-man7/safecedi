<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$user = require_login();   // any signed-in role

$title = 'Your account';
require APP_ROOT . '/templates/header.php';
?>
<h1>Your account</h1>
<div class="card">
  <p><strong>Name:</strong> <?= e($user['display_name']) ?></p>
  <p><strong>Email:</strong> <?= e($user['email']) ?></p>
  <p><strong>Role:</strong> <?= e(ucfirst($user['role'])) ?></p>
  <p><strong>Member since:</strong> <?= e(date('j M Y', strtotime($user['created_at']))) ?></p>
</div>
<p class="hint">Your reports and feedback will appear here from Phase 02.</p>
<?php require APP_ROOT . '/templates/footer.php'; ?>
