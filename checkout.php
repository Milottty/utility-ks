<?php
declare(strict_types=1);
require __DIR__ . '/includes/site.php';

$items = cart_items();
if (!$items) {
    flash('Shporta jote është bosh.', 'err');
    redirect('cart');
}

$f = ['name' => '', 'email' => '', 'phone' => '', 'country' => 'Kosovo', 'city' => '', 'address' => '', 'postal_code' => '', 'notes' => ''];
$errors = [];

$subtotal = cart_subtotal($items);
$discountRow = null;
$discount = 0.0;
if (!empty($_SESSION['coupon'])) {
    [$discountRow, $err] = check_discount((string)$_SESSION['coupon'], $subtotal);
    if ($discountRow) {
        $discount = round($subtotal * (float)$discountRow['percent'] / 100, 2);
    } else {
        unset($_SESSION['coupon']);
    }
}
$shipping = shipping_for($subtotal - $discount);
$total = round($subtotal - $discount + $shipping, 2);

if (is_post()) {
    csrf_verify();
    foreach ($f as $k => $_) {
        $f[$k] = post($k);
    }
    if ($f['name'] === '') $errors[] = 'Shkruaj emrin.';
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Shkruaj një email të vlefshëm.';
    if ($f['phone'] === '') $errors[] = 'Shkruaj numrin e telefonit.';
    if ($f['country'] === '' || $f['city'] === '' || $f['address'] === '') $errors[] = 'Plotëso vendin, qytetin dhe adresën.';

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            // 1) stoku: zbrit atomikisht; dështon nëse s'ka mjaft
            $lines = [];
            foreach (cart_get() as $it) {
                $p = row('SELECT * FROM products WHERE id = ?', [(int)$it['product_id']]);
                $qty = (int)$it['qty'];
                if (!$p || !can_buy($p)) {
                    throw new RuntimeException('"' . ($p['name'] ?? 'Një produkt') . '" nuk është më i disponueshëm.');
                }
                $ok = q('UPDATE products SET stock = stock - ?, sold_count = sold_count + ? WHERE id = ? AND stock >= ? AND status = \'published\'', [$qty, $qty, $p['id'], $qty]);
                if ($ok->rowCount() < 1) {
                    throw new RuntimeException('Stoku për "' . $p['name'] . '" nuk mjafton (' . max(0, (int)$p['stock']) . ' të disponueshme).');
                }
                $lines[] = [
                    'product_id' => $p['id'], 'name' => $p['name'],
                    'image' => val('SELECT path FROM product_images WHERE product_id = ? ORDER BY sort, id LIMIT 1', [$p['id']]),
                    'size' => $it['size'] ?: null, 'color' => $it['color'] ?: null,
                    'qty' => $qty, 'price' => effective_price($p),
                ];
            }

            // 2) totalet nga çmimet aktuale (jo nga browser-i)
            $sub = 0.0;
            foreach ($lines as $l) {
                $sub += $l['price'] * $l['qty'];
            }
            $sub = round($sub, 2);
            $disc = 0.0;
            $code = null;
            if (!empty($_SESSION['coupon'])) {
                [$d, $err] = check_discount((string)$_SESSION['coupon'], $sub);
                if ($d) {
                    $disc = round($sub * (float)$d['percent'] / 100, 2);
                    $code = $d['code'];
                    $u = q('UPDATE discounts SET used = used + 1 WHERE id = ? AND (max_uses = 0 OR used < max_uses)', [$d['id']]);
                    if ($u->rowCount() < 1) {
                        throw new RuntimeException('Kodi i zbritjes nuk është më i vlefshëm.');
                    }
                }
            }
            $ship = shipping_for($sub - $disc);
            $grand = round($sub - $disc + $ship, 2);

            // 3) klienti
            $cust = row('SELECT id FROM customers WHERE email = ?', [strtolower($f['email'])]);
            if ($cust) {
                $cid = (int)$cust['id'];
                update('customers', ['name' => $f['name'], 'phone' => $f['phone']], 'id = ?', [$cid]);
            } else {
                $cid = insert('customers', ['name' => $f['name'], 'email' => strtolower($f['email']), 'phone' => $f['phone'], 'created_at' => now()]);
            }

            // 4) porosia
            $oid = insert('orders', [
                'customer_id' => $cid, 'customer_name' => $f['name'], 'email' => strtolower($f['email']), 'phone' => $f['phone'],
                'country' => $f['country'], 'city' => $f['city'], 'address' => $f['address'], 'postal_code' => $f['postal_code'],
                'notes' => $f['notes'], 'subtotal' => $sub, 'shipping' => $ship, 'discount' => $disc, 'total' => $grand,
                'discount_code' => $code, 'payment_method' => 'cod', 'payment_status' => 'unpaid', 'status' => 'pending', 'created_at' => now(),
            ]);
            foreach ($lines as $l) {
                $l['order_id'] = $oid;
                insert('order_items', $l);
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $ex instanceof RuntimeException ? $ex->getMessage() : 'Ndodhi një gabim gjatë ruajtjes së porosisë. Provo përsëri.';
            $items = cart_items();
        }

        if (!$errors) {
            unset($_SESSION['cart'], $_SESSION['coupon']);
            $_SESSION['last_order'] = $oid;
            redirect('order-success');
        }
    }
}

site_header('Checkout');
?>
<div class="wrap cartpage">
  <h1 class="ph">CHECKOUT</h1>
  <?php foreach ($errors as $er): ?><div class="flash err"><?= e($er) ?></div><?php endforeach; ?>
  <div class="cart-layout">
    <form method="post" class="cform"><?= csrf_field() ?>
      <h3>CONTACT</h3>
      <div class="two"><input name="name" placeholder="Full name *" value="<?= e($f['name']) ?>" required><input name="phone" placeholder="Phone *" value="<?= e($f['phone']) ?>" required></div>
      <input type="email" name="email" placeholder="Email *" value="<?= e($f['email']) ?>" required>
      <h3>SHIPPING ADDRESS</h3>
      <div class="two"><input name="country" placeholder="Country *" value="<?= e($f['country']) ?>" required><input name="city" placeholder="City *" value="<?= e($f['city']) ?>" required></div>
      <div class="two"><input name="address" placeholder="Address *" value="<?= e($f['address']) ?>" required><input name="postal_code" placeholder="Postal code" value="<?= e($f['postal_code']) ?>"></div>
      <textarea name="notes" rows="2" placeholder="Order notes (optional)"><?= e($f['notes']) ?></textarea>
      <h3>PAYMENT</h3>
      <div class="paybox">Pagesa në dorëzim (Cash on delivery)</div>
      <button class="btn-w full" type="submit">PLACE ORDER — <?= e(money($total)) ?></button>
    </form>

    <aside class="summary">
      <h3>YOUR ORDER</h3>
      <?php foreach ($items as $it): ?>
        <div class="mini"><span><?= e($it['name']) ?><?= $it['size'] ? ' · ' . e($it['size']) : '' ?><?= $it['color'] ? ' · ' . e($it['color']) : '' ?> <i>×<?= (int)$it['qty'] ?></i></span><b><?= money($it['line']) ?></b></div>
      <?php endforeach; ?>
      <dl>
        <dt>Subtotal</dt><dd><?= money($subtotal) ?></dd>
        <?php if ($discount > 0): ?><dt>Discount (<?= e($_SESSION['coupon']) ?>)</dt><dd>−<?= money($discount) ?></dd><?php endif; ?>
        <dt>Shipping</dt><dd><?= $shipping > 0 ? money($shipping) : 'FREE' ?></dd>
        <dt class="tot">TOTAL</dt><dd class="tot"><?= money($total) ?></dd>
      </dl>
      <a class="lnk" href="<?= url('cart') ?>">← Back to bag</a>
    </aside>
  </div>
</div>
<?php site_footer(); ?>
