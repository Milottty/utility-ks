<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$order = row('SELECT * FROM orders WHERE id = ?', [$id]);
if (!$order) {
    flash('Porosia nuk u gjet.', 'err');
    redirect('admin/orders');
}

if (is_post()) {
    csrf_verify();
    $new = post('status');
    $pay = post('payment_status');

    if (in_array($pay, ['unpaid', 'paid', 'refunded'], true) && $pay !== $order['payment_status']) {
        update('orders', ['payment_status' => $pay], 'id = ?', [$id]);
        flash('Payment status u ndryshua.');
    }

    if (in_array($new, ORDER_STATUSES, true) && $new !== $order['status']) {
        $items = rows('SELECT * FROM order_items WHERE order_id = ?', [$id]);
        $old = $order['status'];
        db()->beginTransaction();
        try {
            if ($new === 'cancelled' && $old !== 'cancelled') {
                // kthe stokun
                foreach ($items as $it) {
                    if ($it['product_id']) {
                        q('UPDATE products SET stock = stock + ?, sold_count = CASE WHEN sold_count >= ? THEN sold_count - ? ELSE 0 END WHERE id = ?', [$it['qty'], $it['qty'], $it['qty'], $it['product_id']]);
                    }
                }
            } elseif ($old === 'cancelled' && $new !== 'cancelled') {
                // riaktivizim: ule stokun përsëri
                foreach ($items as $it) {
                    if ($it['product_id']) {
                        $ok = q('UPDATE products SET stock = stock - ?, sold_count = sold_count + ? WHERE id = ? AND stock >= ?', [$it['qty'], $it['qty'], $it['product_id'], $it['qty']]);
                        if ($ok->rowCount() < 1) {
                            throw new RuntimeException('Stoku i pamjaftueshëm për "' . $it['name'] . '" — porosia nuk mund të riaktivizohet.');
                        }
                    }
                }
            }
            update('orders', ['status' => $new], 'id = ?', [$id]);
            db()->commit();
            flash('Order status u ndryshua në ' . strtoupper($new) . '.');
        } catch (Throwable $ex) {
            db()->rollBack();
            flash($ex->getMessage(), 'err');
        }
    }
    redirect('admin/order?id=' . $id);
}

$items = rows('SELECT * FROM order_items WHERE order_id = ?', [$id]);
admin_header('Order ' . order_no($id), 'orders');
?>
<p><a class="link" href="<?= url('admin/orders') ?>">← All orders</a></p>
<div class="grid-2 wide-left">
  <div>
    <section class="card">
      <h2>PRODUCTS</h2>
      <table class="tbl">
        <tr><th></th><th>Product</th><th>Size</th><th>Qty</th><th class="r">Price</th></tr>
        <?php foreach ($items as $it): ?>
          <tr>
            <td><?php if ($it['image']): ?><img class="thumb" src="<?= e(upload_url($it['image'])) ?>" alt=""><?php else: ?><div class="thumb empty"></div><?php endif; ?></td>
            <td><?= e($it['name']) ?><?= $it['color'] ? '<br><small class="muted">' . e($it['color']) . '</small>' : '' ?></td>
            <td><?= e($it['size'] ?: '—') ?></td>
            <td><?= (int)$it['qty'] ?></td>
            <td class="r"><?= money($it['price'] * $it['qty']) ?><br><small class="muted"><?= money($it['price']) ?> each</small></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </section>
    <section class="card">
      <h2>ORDER SUMMARY</h2>
      <dl class="kv">
        <dt>Subtotal</dt><dd><?= money($order['subtotal']) ?></dd>
        <dt>Shipping</dt><dd><?= money($order['shipping']) ?></dd>
        <dt>Discount<?= $order['discount_code'] ? ' (' . e($order['discount_code']) . ')' : '' ?></dt><dd>−<?= money($order['discount']) ?></dd>
        <dt class="big">TOTAL</dt><dd class="big"><?= money($order['total']) ?></dd>
      </dl>
    </section>
  </div>
  <div>
    <section class="card">
      <h2>ORDER <?= order_no($id) ?></h2>
      <p class="muted small"><?= e(date('d M Y, H:i', strtotime($order['created_at']))) ?></p>
      <form method="post">
        <?= csrf_field() ?>
        <label>Order Status</label>
        <select name="status">
          <?php foreach (ORDER_STATUSES as $s): ?><option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
        </select>
        <label>Payment Status (<?= e($order['payment_method'] === 'cod' ? 'Pagesa në dorëzim' : $order['payment_method']) ?>)</label>
        <select name="payment_status">
          <?php foreach (['unpaid' => 'Unpaid', 'paid' => 'Paid', 'refunded' => 'Refunded'] as $k => $l): ?><option value="<?= $k ?>" <?= $order['payment_status'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
        </select>
        <button class="btn block" type="submit">UPDATE</button>
      </form>
      <p class="muted small">Anulimi i porosisë e kthen automatikisht stokun.</p>
    </section>
    <section class="card">
      <h2>CUSTOMER</h2>
      <dl class="kv stack">
        <dt>Name</dt><dd><?= $order['customer_id'] ? '<a class="link" href="' . url('admin/customer?id=' . $order['customer_id']) . '">' . e($order['customer_name']) . '</a>' : e($order['customer_name']) ?></dd>
        <dt>Email</dt><dd><a class="link" href="mailto:<?= e($order['email']) ?>"><?= e($order['email']) ?></a></dd>
        <dt>Phone</dt><dd><?= e($order['phone'] ?: '—') ?></dd>
      </dl>
    </section>
    <section class="card">
      <h2>SHIPPING ADDRESS</h2>
      <dl class="kv stack">
        <dt>Country</dt><dd><?= e($order['country']) ?></dd>
        <dt>City</dt><dd><?= e($order['city']) ?></dd>
        <dt>Address</dt><dd><?= e($order['address']) ?></dd>
        <dt>Postal Code</dt><dd><?= e($order['postal_code'] ?: '—') ?></dd>
        <?php if ($order['notes']): ?><dt>Notes</dt><dd><?= nl2br(e($order['notes'])) ?></dd><?php endif; ?>
      </dl>
    </section>
  </div>
</div>
<?php admin_footer(); ?>
