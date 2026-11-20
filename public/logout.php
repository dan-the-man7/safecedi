<?php
/**
 * Logout is POST-only with a CSRF token. A GET logout link could be
 * triggered by any other site (e.g. <img src=".../logout.php">).
 */
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

require_post();
csrf_verify();

log_out_user();
redirect('login.php?signed_out=1');
