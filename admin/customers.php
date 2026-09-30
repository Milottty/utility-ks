<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_admin();

$search = trim((string)($_GET['q'] ?? ''));
$where = '1=1';
$par = [];
if ($search !== '') {
    $where = '(c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)';
    $par = ["%$search%", "%$search%", "%$search%"];
}
$total = (int)val("SELECT COUNT(*) FROM customers c WHERE $where", $par);
[$page, $pages, $offset] = paginate($total, 25, (int)($_GET['page'] ?? 1));
$list = rows("SELECT c.*,
    (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) AS orders_n,
    (SELECT COALESCE(SUM(o.total),0) FROM orders o WHERE o.customer_id = c.id AND o.status <> 'cancelled') AS spent,
    (SELECT MAX(o.created_at) FROM orders o WHERE o.customer_id = c.id) AS last_order
    FROM customers c WHERE $where ORDER BY c.id DESC LIMIT 25 OFFSET " . (int)$offset, $par);

admin_header('Customers', 'customers');
?>
<div class="toolbar">
  <form method="get" class="filters"><input type="search" name="q" value="<?= e($search) ?>" placeholder="Search name, email, phone..." id="live-search"><button class="btn ghost" type="submit">SEARCH</button></form>
</div>
<section class="card flush">
  <div class="table-scroll">
  <table class="tbl">
    <tr><th>Customer Name</th><th>Email</th><th>Phone</th><th>Orders</th><th>Total Spent</th><th>Last Order</th><th>Registered</th></tr>
    <?php foreach ($list as $c): ?>
      <tr>
        <td><a class="link" href="<?= url('admin/customer?id=' . $c['id']) ?>"><b><?= e($c['name']) ?></b></a></td>
        <td><?= e($c['email']) ?></td>
        <td><?= e($c['phone'] ?: '—') ?></td>
        <td><?= (int)$c['orders_n'] ?></td>
        <td><?= money($c['spent']) ?></td>
        <td><?= $c['last_order'] ? e(date('d M Y', strtotime($c['last_order']))) : '—' ?></td>
        <td><?= e(date('d M Y', strtotime($c['created_at']))) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="7" class="muted center">Nuk ka klientë.</td></tr><?php endif; ?>
  </table>
  </div>
</section>
<?= pager($page, $pages) ?>
<?php admin_footer(); ?>
