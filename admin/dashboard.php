<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require dirname(__DIR__) . '/includes/stats.php';

$s = dashboard_stats();
[$labels, $values] = sales_series('7d');
$recent = rows('SELECT * FROM orders ORDER BY id DESC LIMIT 8');
$low = rows("SELECT p.id, p.name, p.sku, p.stock, p.low_stock_threshold, p.status, (SELECT path FROM product_images i WHERE i.product_id = p.id ORDER BY i.sort, i.id LIMIT 1) AS img FROM products p WHERE p.status <> 'draft' AND p.stock < p.low_stock_threshold ORDER BY p.stock ASC LIMIT 10");
$newOrders = (int)val("SELECT COUNT(*) FROM orders WHERE status = 'pending'");

admin_header('Dashboard', 'dashboard');
?>
<?php if ($newOrders): ?>
  <a class="alert-bar" href="<?= url('admin/orders?status=pending') ?>">NEW ORDER — <?= $newOrders ?> porosi në pritje të konfirmimit →</a>
<?php endif; ?>

<section class="cards">
  <div class="card stat"><span>TOTAL SALES</span><b><?= money($s['total_sales']) ?></b></div>
  <div class="card stat"><span>TOTAL ORDERS</span><b><?= $s['orders'] ?></b></div>
  <div class="card stat"><span>TOTAL PRODUCTS</span><b><?= $s['products'] ?></b></div>
  <div class="card stat"><span>TOTAL CUSTOMERS</span><b><?= $s['customers'] ?></b></div>
  <div class="card stat"><span>PENDING ORDERS</span><b><?= $s['pending'] ?></b></div>
  <div class="card stat <?= $s['low'] ? 'warn' : '' ?>"><span>LOW STOCK</span><b><?= $s['low'] ?></b></div>
</section>

<section class="cards four">
  <div class="card stat sm"><span>TODAY'S SALES</span><b><?= money($s['today']) ?></b></div>
  <div class="card stat sm"><span>THIS WEEK'S SALES</span><b><?= money($s['week']) ?></b></div>
  <div class="card stat sm"><span>THIS MONTH'S SALES</span><b><?= money($s['month']) ?></b></div>
  <div class="card stat sm"><span>UNITS SOLD</span><b><?= $s['units_sold'] ?></b></div>
</section>

<section class="card">
  <div class="card-head">
    <h2>SALES OVER TIME</h2>
    <div class="tabs" id="range-tabs">
      <button data-range="today" type="button">TODAY</button>
      <button data-range="7d" type="button" class="on">7 DAYS</button>
      <button data-range="30d" type="button">30 DAYS</button>
      <button data-range="12m" type="button">12 MONTHS</button>
    </div>
  </div>
  <div class="chart-meta"><span id="chart-total"></span></div>
  <div class="chart-wrap"><canvas id="sales-chart" height="280"></canvas><div class="chart-tip" id="chart-tip" hidden></div></div>
</section>

<div class="grid-2">
  <section class="card">
    <div class="card-head"><h2>RECENT ORDERS</h2><a class="link" href="<?= url('admin/orders') ?>">VIEW ALL →</a></div>
    <?php if (!$recent): ?><p class="muted">Ende nuk ka porosi.</p><?php else: ?>
    <table class="tbl">
      <tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th></tr>
      <?php foreach ($recent as $o): ?>
        <tr>
          <td><a class="link" href="<?= url('admin/order?id=' . $o['id']) ?>"><?= order_no($o['id']) ?></a><?= $o['status'] === 'pending' ? ' <span class="new">NEW</span>' : '' ?></td>
          <td><?= e($o['customer_name']) ?></td>
          <td><?= money($o['total']) ?></td>
          <td><?= status_badge($o['status']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="card-head"><h2>LOW STOCK PRODUCTS</h2><a class="link" href="<?= url('admin/inventory') ?>">INVENTORY →</a></div>
    <?php if (!$low): ?><p class="muted">Të gjitha produktet kanë stok të mjaftueshëm.</p><?php else: ?>
    <table class="tbl">
      <?php foreach ($low as $p): $st = stock_state($p); ?>
        <tr>
          <td><a class="link" href="<?= url('admin/product-edit?id=' . $p['id']) ?>"><?= e($p['name']) ?></a><br><small class="muted">Stock: <?= (int)$p['stock'] ?></small></td>
          <td class="r"><?= status_badge($st === 'out' ? 'out_of_stock' : 'low_stock') ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </section>
</div>
<script>window.SALES_API = <?= json_encode(url('admin/api-sales')) ?>;</script>
<?php admin_footer(); ?>
