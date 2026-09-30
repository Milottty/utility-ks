<?php
declare(strict_types=1);

if (!defined('IN_ADMIN')) {
    define('IN_ADMIN', true);
}
require_once dirname(__DIR__) . '/includes/bootstrap.php';

function admin_header(string $title, string $active = ''): void
{
    require_admin();
    $nav = [
        ['DASHBOARD', 'admin/dashboard', 'dashboard'],
        ['PRODUCTS', 'admin/products', 'products'],
        ['ORDERS', 'admin/orders', 'orders'],
        ['CUSTOMERS', 'admin/customers', 'customers'],
        ['INVENTORY', 'admin/inventory', 'inventory'],
        ['CATEGORIES', 'admin/categories', 'categories'],
        ['DISCOUNTS', 'admin/discounts', 'discounts'],
        ['REVIEWS', 'admin/reviews', 'reviews'],
        ['HOMEPAGE', 'admin/homepage', 'homepage'],
        ['ANALYTICS', 'admin/analytics', 'analytics'],
        ['SETTINGS', 'admin/settings', 'settings'],
    ];
    $pending = (int)val("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
    ?>
<!doctype html>
<html lang="sq">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($title) ?> — <?= e(setting('store_name', 'UTILITY.KS')) ?> Admin</title>
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
</head>
<body>
<div class="shell">
  <aside class="side" id="side">
    <a class="brand" href="<?= url('admin/dashboard') ?>">UTILITY.KS<small>ADMIN</small></a>
    <nav>
      <?php foreach ($nav as [$label, $path, $key]): ?>
        <a href="<?= url($path) ?>" class="<?= $active === $key ? 'on' : '' ?>">
          <?= e($label) ?>
          <?php if ($key === 'orders' && $pending): ?><span class="pill"><?= $pending ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
      <a href="<?= url('') ?>" target="_blank" class="ext">VIEW STORE ↗</a>
      <a href="<?= url('admin/logout') ?>" class="logout">LOGOUT</a>
    </nav>
  </aside>
  <main class="main">
    <header class="top">
      <button class="burger" type="button" onclick="document.getElementById('side').classList.toggle('open')">☰</button>
      <h1><?= e($title) ?></h1>
    </header>
    <?php foreach (flashes() as [$t, $m]): ?><div class="flash <?= e($t) ?>"><?= e($m) ?></div><?php endforeach; ?>
    <?php
}

function admin_footer(): void
{
    ?>
  </main>
</div>
<div class="modal" id="confirm-modal" hidden>
  <div class="modal-box">
    <p id="confirm-msg">Are you sure?</p>
    <div class="modal-actions">
      <button type="button" class="btn ghost" id="confirm-cancel">CANCEL</button>
      <button type="button" class="btn danger" id="confirm-ok">DELETE</button>
    </div>
  </div>
</div>
<script src="<?= asset('js/admin.js') ?>"></script>
</body>
</html>
    <?php
}

/** Buton DELETE me konfirmim (modal). */
function delete_form(string $action, array $hidden, string $message, string $label = 'DELETE'): string
{
    $h = csrf_field();
    foreach ($hidden as $k => $v) {
        $h .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    }
    return '<form method="post" action="' . e($action) . '" class="inline" data-confirm="' . e($message) . '">' . $h .
        '<button class="link danger-t" type="submit">' . e($label) . '</button></form>';
}

function paginate(int $total, int $per, int $page): array
{
    $pages = max(1, (int)ceil($total / $per));
    $page = min(max(1, $page), $pages);
    return [$page, $pages, ($page - 1) * $per];
}

function pager(int $page, int $pages): string
{
    if ($pages <= 1) {
        return '';
    }
    $qs = $_GET;
    $out = '<div class="pager">';
    for ($i = 1; $i <= $pages; $i++) {
        $qs['page'] = $i;
        $out .= '<a class="' . ($i === $page ? 'on' : '') . '" href="?' . e(http_build_query($qs)) . '">' . $i . '</a>';
    }
    return $out . '</div>';
}
