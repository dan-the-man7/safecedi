<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

if (is_logged_in()) {
    redirect('account.php');
}

$error = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email    = post_string('email');
    $password = post_raw('password');

    $result = attempt_login($email, $password);
    if ($result['ok']) {
        log_in_user($result['user']);
        flash('success', 'Signed in. Welcome back, ' . $result['user']['display_name'] . '.');
        redirect(take_intended_url());
    }
    $error = $result['error'];
}

$title = 'Sign in';
require APP_ROOT . '/templates/header.php';
?>
<h1>Sign in</h1>

<?php if (isset($_GET['signed_out'])): ?>
  <div class="flash flash-success" role="status">You have been signed out.</div>
<?php endif; ?>
<?php if ($error !== null): ?>
  <div class="flash flash-error" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<form class="card" method="post" action="login.php" novalidate>
  <?= csrf_field() ?>

  <label for="email">Email</label>
  <input id="email" name="email" type="email" autocomplete="username" required value="<?= e($email) ?>">

  <label for="password">Password</label>
  <input id="password" name="password" type="password" autocomplete="current-password" required>

  <button class="primary" type="submit">Sign in</button>
</form>
<p>New here? <a href="register.php">Create a reporter account</a> — or just <a href="index.php">check a message</a> without one.</p>
<?php require APP_ROOT . '/templates/footer.php'; ?>
