<?php
/**
 * Create a reporter account. Moderators are never self-service: they are
 * promoted by an existing admin (see docs/deploy-guide.md, step 8).
 */
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

if (is_logged_in()) {
    redirect('account.php');
}

$errors = [];
$email  = '';
$name   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email    = post_string('email');
    $name     = post_string('display_name');
    $password = post_raw('password');            // never trimmed: spaces are valid
    $confirm  = post_raw('password_confirm');

    $errors = validate_registration($email, $name, $password, $confirm);

    if (!$errors) {
        try {
            $userId = register_user($email, $name, $password);
            log_in_user(['id' => $userId]);   // regenerates the session ID
            flash('success', 'Welcome to SafeCedi. Your reporter account is ready.');
            redirect('account.php');
        } catch (DomainException) {
            $errors['email'] = 'That email is already registered. Try signing in instead.';
        }
    }
}

$title = 'Create account';
require APP_ROOT . '/templates/header.php';
?>
<h1>Create a reporter account</h1>
<p class="hint">You don't need an account to check a message. An account lets you report scams and flag wrong verdicts.</p>

<form class="card" method="post" action="register.php" novalidate>
  <?= csrf_field() ?>

  <label for="email">Email</label>
  <input id="email" name="email" type="email" autocomplete="email" required maxlength="254" value="<?= e($email) ?>">
  <?php if (isset($errors['email'])): ?><p class="field-error"><?= e($errors['email']) ?></p><?php endif; ?>

  <label for="display_name">Display name</label>
  <input id="display_name" name="display_name" type="text" autocomplete="nickname" required minlength="2" maxlength="60" value="<?= e($name) ?>">
  <p class="hint">Shown next to your reports on the scam wall.</p>
  <?php if (isset($errors['display_name'])): ?><p class="field-error"><?= e($errors['display_name']) ?></p><?php endif; ?>

  <label for="password">Password</label>
  <input id="password" name="password" type="password" autocomplete="new-password" required minlength="<?= PASSWORD_MIN_LENGTH ?>">
  <p class="hint">At least <?= PASSWORD_MIN_LENGTH ?> characters. A short sentence works well.</p>
  <?php if (isset($errors['password'])): ?><p class="field-error"><?= e($errors['password']) ?></p><?php endif; ?>

  <label for="password_confirm">Confirm password</label>
  <input id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" required>
  <?php if (isset($errors['password_confirm'])): ?><p class="field-error"><?= e($errors['password_confirm']) ?></p><?php endif; ?>

  <button class="primary" type="submit">Create account</button>
</form>
<p>Already have an account? <a href="login.php">Sign in</a></p>
<?php require APP_ROOT . '/templates/footer.php'; ?>
