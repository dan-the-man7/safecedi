<?php
$viewer = current_user();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'SafeCedi') ?> · SafeCedi</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>

<header class="site-header">
    <a class="brand" href="index.php">
        <span class="brand-icon">S</span>
        SafeCedi
    </a>

    <nav aria-label="Main">
        <a href="index.php">Check a Message</a>

        <?php if ($viewer === null): ?>
            <a href="login.php">Sign In</a>
            <a href="register.php" class="nav-cta">Create Account</a>
        <?php else: ?>

            <?php if ($viewer['role'] === ROLE_MODERATOR): ?>
                <a href="moderate.php">Moderate</a>
            <?php endif; ?>

            <a href="account.php"><?= e($viewer['display_name']) ?></a>

            <form class="inline" method="post" action="logout.php">
                <?= csrf_field() ?>
                <button type="submit" class="link-button">Sign Out</button>
            </form>

        <?php endif; ?>
    </nav>
</header>

<main class="container">

<?php foreach (take_flashes() as $f): ?>
    <div class="flash flash-<?= e($f['type']) ?>" role="status">
        <?= e($f['message']) ?>
    </div>
<?php endforeach; ?>