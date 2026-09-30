<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$c = row('SELECT * FROM customers WHERE id = ?', [$id]);
if (!$c) {
    flash('Klienti nuk u gjet.', 'err');
    redirect('admin/customers');
}
$orders = rows('SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC', [$id]);
$spent = (float)val("SELECT COALESCE(SUM(total),0) FROM orders WHERE customer_id = ? AND status <> 'cancelled'", [$id]);
$addresses = rows('SELECT DISTINCT country, city, address, postal_code FROM orders WHERE customer_id = ?', [$id]);

admin_header('Customer', 'customers');
?>
<p><a class="link" href="<?= url('admin/customers') ?>">← All customers</a></p>
<div class="grid-2">
  <section class="card">
    <h2>PERSONAL INFORMATION</h2>
    <dl class="kv stack">
      <dt>Name</dt><dd><?= e($c['name']) ?></dd>
      <dt>Email</dt><dd><a class="link" href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a></dd>
      <dt>Phone</dt><dd><?= e($c['phone'] ?: '—') ?></dd>
      <dt>Registered</dt><dd><?= e(date('d M Y, H:i', strtotime($c['created_at']))) ?></dd>
    </dl>
  </section>
  <section class="card">
    <h2>TOTAL SPENT</h2>
    <div class="bignum"><?= money($spent) ?></div>
    <p class="muted"><?= count($orders) ?> porosi</p>
  </section>
</div>
<section class="card">
  <h2>ADDRESSES</h2>
  <?php foreach ($addresses as $a): ?><p><?= e($a['address']) ?>, <?= e($a['postal_code']) ?> <?= e($a['city']) ?>, <?= e($a['country']) ?></p><?php endforeach; ?>
  <?php if (!$addresses): ?><p class="muted">—</p><?php endif; ?>
</section>
<section class="card">
  <h2>ORDER HISTORY</h2>
  <table class="tbl">
    <tr><th>Order</th><th>Date</th><th>Total</th><th>Payment</th><th>Status</th></tr>
    <?php foreach ($orders as $o): ?>
      <tr>
        <td><a class="link" href="<?= url('admin/order?id=' . $o['id']) ?>"><?= order_no($o['id']) ?></a></td>
        <td><?= e(date('d M Y, H:i', strtotime($o['created_at']))) ?></td>
        <td><?= money($o['total']) ?></td>
        <td><?= status_badge($o['payment_status']) ?></td>
        <td><?= status_badge($o['status']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</section>
<?php admin_footer(); ?>
