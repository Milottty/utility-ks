<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require dirname(__DIR__) . '/includes/stats.php';
require_admin();

$s = dashboard_stats();
$aov = $s['orders'] ? $s['total_sales'] / max(1, (int)val("SELECT COUNT(*) FROM orders WHERE status <> 'cancelled'")) : 0;
$top = rows("SELECT oi.product_id, oi.name, SUM(oi.qty) AS units, SUM(oi.qty * oi.price) AS revenue FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE o.status <> 'cancelled' GROUP BY oi.product_id, oi.name ORDER BY units DESC LIMIT 10");
$byStatus = rows('SELECT status, COUNT(*) AS n, COALESCE(SUM(total),0) AS amount FROM orders GROUP BY status');
$byCat = rows("SELECT COALESCE(c.name, 'Pa kategori') AS name, SUM(oi.qty) AS units, SUM(oi.qty * oi.price) AS revenue
    FROM order_items oi JOIN orders o ON o.id = oi.order_id LEFT JOIN products p ON p.id = oi.product_id LEFT JOIN categories c ON c.id = p.category_id
    WHERE o.status <> 'cancelled' GROUP BY c.name ORDER BY revenue DESC");
$maxUnits = max(1, (int)($top[0]['units'] ?? 1));

admin_header('Analytics', 'analytics');
?>
<section class="cards four">
  <div class="card stat sm"><span>TOTAL SALES</span><b><?= money($s['total_sales']) ?></b></div>
  <div class="card stat sm"><span>ORDERS</span><b><?= $s['orders'] ?></b></div>
  <div class="card stat sm"><span>AVG. ORDER VALUE</span><b><?= money($aov) ?></b></div>
  <div class="card stat sm"><span>UNITS SOLD</span><b><?= $s['units_sold'] ?></b></div>
</section>

<section class="card">
  <div class="card-head"><h2>SALES OVER TIME</h2>
    <div class="tabs" id="range-tabs">
      <button data-range="today" type="button">TODAY</button><button data-range="7d" type="button">7 DAYS</button>
      <button data-range="30d" type="button" class="on">30 DAYS</button><button data-range="12m" type="button">12 MONTHS</button>
    </div></div>
  <div class="chart-meta"><span id="chart-total"></span></div>
  <div class="chart-wrap"><canvas id="sales-chart" height="280"></canvas><div class="chart-tip" id="chart-tip" hidden></div></div>
</section>

<div class="grid-2">
  <section class="card">
    <h2>TOP SELLING PRODUCTS</h2>
    <?php foreach ($top as $t): ?>
      <div class="bar-row">
        <div class="bar-label"><span><?= e($t['name']) ?></span><b><?= (int)$t['units'] ?> pcs · <?= money($t['revenue']) ?></b></div>
        <div class="bar"><i style="width:<?= round($t['units'] / $maxUnits * 100) ?>%"></i></div>
      </div>
    <?php endforeach; ?>
    <?php if (!$top): ?><p class="muted">Ende nuk ka shitje.</p><?php endif; ?>
  </section>
  <div>
    <section class="card">
      <h2>ORDERS BY STATUS</h2>
      <table class="tbl"><?php foreach ($byStatus as $b): ?><tr><td><?= status_badge($b['status']) ?></td><td><?= (int)$b['n'] ?></td><td class="r"><?= money($b['amount']) ?></td></tr><?php endforeach; ?>
      <?php if (!$byStatus): ?><tr><td class="muted">—</td></tr><?php endif; ?></table>
    </section>
    <section class="card">
      <h2>SALES BY CATEGORY</h2>
      <table class="tbl"><?php foreach ($byCat as $b): ?><tr><td><?= e($b['name']) ?></td><td><?= (int)$b['units'] ?> pcs</td><td class="r"><?= money($b['revenue']) ?></td></tr><?php endforeach; ?>
      <?php if (!$byCat): ?><tr><td class="muted">—</td></tr><?php endif; ?></table>
    </section>
  </div>
</div>
<script>window.SALES_API = <?= json_encode(url('admin/api-sales')) ?>;</script>
<?php admin_footer(); ?>
