<?php
declare(strict_types=1);
require __DIR__ . '/includes/site.php';

$oid = (int)($_SESSION['last_order'] ?? 0);
$o = $oid ? row('SELECT * FROM orders WHERE id = ?', [$oid]) : null;
if (!$o) {
    redirect('');
}
$items = rows('SELECT * FROM order_items WHERE order_id = ?', [$oid]);

site_header('Order confirmed');
?>
<div class="wrap cartpage" style="max-width:720px">
  <div class="ok-box">
    <div class="ok-mark">✓</div>
    <h1>FALEMINDERIT PËR POROSINË!</h1>
    <p class="muted-s">Porosia jote <b><?= e(order_no($oid)) ?></b> u pranua. Do të kontaktojmë në <b><?= e($o['email']) ?></b> / <b><?= e($o['phone']) ?></b> për konfirmim.</p>
  </div>
  <div class="summary" style="margin-top:24px">
    <h3>ORDER <?= e(order_no($oid)) ?></h3>
    <?php foreach ($items as $it): ?>
      <div class="mini"><span><?= e($it['name']) ?><?= $it['size'] ? ' · ' . e($it['size']) : '' ?><?= $it['color'] ? ' · ' . e($it['color']) : '' ?> <i>×<?= (int)$it['qty'] ?></i></span><b><?= money($it['price'] * $it['qty']) ?></b></div>
    <?php endforeach; ?>
    <dl>
      <dt>Subtotal</dt><dd><?= money($o['subtotal']) ?></dd>
      <?php if ((float)$o['discount'] > 0): ?><dt>Discount</dt><dd>−<?= money($o['discount']) ?></dd><?php endif; ?>
      <dt>Shipping</dt><dd><?= (float)$o['shipping'] > 0 ? money($o['shipping']) : 'FREE' ?></dd>
      <dt class="tot">TOTAL</dt><dd class="tot"><?= money($o['total']) ?></dd>
    </dl>
    <p class="muted-s">Dërgohet në: <?= e($o['address']) ?>, <?= e($o['city']) ?>, <?= e($o['country']) ?><br>Pagesa: në dorëzim</p>
    <a class="btn-w full" href="<?= url('shop') ?>">CONTINUE SHOPPING</a>
  </div>
</div>
<?php site_footer(); ?>
