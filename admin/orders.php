<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_admin();

$status = $_GET['status'] ?? '';
$search = trim((string)($_GET['q'] ?? ''));
$where = ['1=1'];
$par = [];
if (in_array($status, ORDER_STATUSES, true)) {
    $where[] = 'o.status = ?';
    $par[] = $status;
}
if ($search !== '') {
    $num = (int)preg_replace('/\D/', '', $search) - 10000;
    $where[] = '(o.customer_name LIKE ? OR o.email LIKE ? OR o.id = ?)';
    array_push($par, "%$search%", "%$search%", $num);
}
$W = implode(' AND ', $where);
$total = (int)val("SELECT COUNT(*) FROM orders o WHERE $W", $par);
[$page, $pages, $offset] = paginate($total, 25, (int)($_GET['page'] ?? 1));
$orders = rows("SELECT o.*, (SELECT COALESCE(SUM(qty),0) FROM order_items i WHERE i.order_id = o.id) AS qty FROM orders o WHERE $W ORDER BY o.id DESC LIMIT 25 OFFSET " . (int)$offset, $par);

$names = [];
if ($orders) {
    $ids = implode(',', array_map('intval', array_column($orders, 'id')));
    foreach (rows("SELECT order_id, name, qty FROM order_items WHERE order_id IN ($ids)") as $it) {
        $names[$it['order_id']][] = $it['name'] . ' ×' . $it['qty'];
    }
}

admin_header('Orders', 'orders');
?>
<div class="toolbar">
  <form method="get" class="filters">
    <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search order #, name, email..." id="live-search">
    <select name="status" onchange="this.form.submit()">
      <option value="">All statuses</option>
      <?php foreach (ORDER_STATUSES as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
    </select>
    <button class="btn ghost" type="submit">FILTER</button>
  </form>
</div>
<section class="card flush">
  <div class="table-scroll">
  <table class="tbl">
    <tr><th>Order ID</th><th>Customer</th><th>Date</th><th>Products</th><th>Qty</th><th>Total</th><th>Payment</th><th>Order Status</th></tr>
    <?php foreach ($orders as $o): ?>
      <tr>
        <td><a class="link" href="<?= url('admin/order?id=' . $o['id']) ?>"><b><?= order_no($o['id']) ?></b></a><?= $o['status'] === 'pending' ? ' <span class="new">NEW</span>' : '' ?></td>
        <td><?= e($o['customer_name']) ?><br><small class="muted"><?= e($o['email']) ?></small></td>
        <td><?= e(date('d M Y, H:i', strtotime($o['created_at']))) ?></td>
        <td class="prodcell"><?= e(implode(', ', $names[$o['id']] ?? [])) ?></td>
        <td><?= (int)$o['qty'] ?></td>
        <td><b><?= money($o['total']) ?></b></td>
        <td><?= status_badge($o['payment_status']) ?></td>
        <td><?= status_badge($o['status']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$orders): ?><tr><td colspan="8" class="muted center">Nuk ka porosi.</td></tr><?php endif; ?>
  </table>
  </div>
</section>
<?= pager($page, $pages) ?>
<?php admin_footer(); ?>
