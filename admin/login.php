<?php
declare(strict_types=1);
define('IN_ADMIN', true);
require dirname(__DIR__) . '/includes/bootstrap.php';

if (admin_logged()) {
    redirect('admin/dashboard');
}

$error = '';
$locked = ($_SESSION['login_lock'] ?? 0) > time();

if (is_post() && !$locked) {
    csrf_verify();
    $email = strtolower(post('email'));
    $admin = row('SELECT * FROM admins WHERE email = ?', [$email]);
    if ($admin && password_verify((string)($_POST['password'] ?? ''), $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$admin['id'];
        $_SESSION['login_fail'] = 0;
        redirect('admin/dashboard');
    }
    $_SESSION['login_fail'] = ($_SESSION['login_fail'] ?? 0) + 1;
    usleep(600000);
    if ($_SESSION['login_fail'] >= 5) {
        $_SESSION['login_lock'] = time() + 300;
        $_SESSION['login_fail'] = 0;
        $locked = true;
    }
    $error = 'Email ose fjalëkalim i pasaktë.';
}
if ($locked) {
    $error = 'Shumë përpjekje të dështuara. Provo përsëri pas 5 minutash.';
}
?>
<!doctype html>
<html lang="sq">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Admin Login — UTILITY.KS</title>
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
</head>
<body class="login-page">
<form class="login-box" method="post" autocomplete="on">
  <div class="login-brand">UTILITY.KS</div>
  <div class="login-sub">ADMIN LOGIN</div>
  <?php if (!empty($_GET['installed'])): ?><div class="flash ok">Instalimi u krye. Hyr me emailin dhe fjalëkalimin që zgjodhe.</div><?php endif; ?>
  <?php if ($error): ?><div class="flash err"><?= e($error) ?></div><?php endif; ?>
  <?= csrf_field() ?>
  <label>EMAIL</label>
  <input type="email" name="email" required autofocus>
  <label>PASSWORD</label>
  <input type="password" name="password" required>
  <button class="btn" type="submit">LOGIN</button>
</form>
</body>
</html>
