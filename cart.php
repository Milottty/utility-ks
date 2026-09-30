<?php
declare(strict_types=1);
require __DIR__ . '/includes/site.php';

if (is_post()) {
    csrf_verify();
    $action = post('action');

    if ($action === 'add') {
        $pid = (int)post('product_id');
        $p = row('SELECT * FROM products WHERE id = ?', [$pid]);
        $qty = max(1, (int)post('qty', '1'));
        $size = post('size');
        $color = post('color');
        if (!$p || !can_buy($p)) {
            flash('Ky produkt nuk është në stok.', 'err');
            redirect('product?id=' . $pid);
        }
        if (split_list($p['sizes']) && !in_array($size, split_list($p['sizes']), true)) {
            flash('Zgjidh madhësinë.', 'err');
            redirect('product?id=' . $pid);
        }
        if (split_list($p['colors']) && !in_array($color, split_list($p['colors']), true)) {
            flash('Zgjidh ngjyrën.', 'err');
            redirect('product?id=' . $pid);
        }
        $key = $pid . '|' . $size . '|' . $color;
        $have = (int)($_SESSION['cart'][$key]['qty'] ?? 0);
        // stoku total i produktit (të gjitha variantet në shportë)
        $inCartOther = 0;
        foreach (cart_get() as $k => $it) {
            if ((int)$it['product_id'] === $pid && $k !== $key) {
                $inCartOther += (int)$it['qty'];
            }
        }
        $max = (int)$p['stock'] - $inCartOther;
        $newQty = min($have + $qty, $max);
        if ($newQty < $have + $qty) {
            flash('Stoku i disponueshëm është ' . (int)$p['stock'] . ' copë — shporta u përshtat.', 'err');
        }
        if ($newQty > 0) {
            $_SESSION['cart'][$key] = ['product_id' => $pid, 'size' => $size, 'color' => $color, 'qty' => $newQty];
            flash('U shtua në shportë.');
        }
        redirect('cart');
    }

    if ($action === 'update') {
        if (isset($_POST['remove'])) {
            unset($_SESSION['cart'][(string)$_POST['remove']]);
            redirect('cart');
        }
        foreach ((array)($_POST['qty'] ?? []) as $key => $qty) {
            if (!isset($_SESSION['cart'][$key])) {
                continue;
            }
            $qty = (int)$qty;
            if ($qty <= 0) {
                unset($_SESSION['cart'][$key]);
                continue;
            }
            $stock = (int)val('SELECT stock FROM products WHERE id = ?', [(int)$_SESSION['cart'][$key]['product_id']]);
            $_SESSION['cart'][$key]['qty'] = min($qty, $stock);
        }
        flash('Shporta u përditësua.');
    }

    if ($action === 'remove') {
        unset($_SESSION['cart'][post('key')]);
    }

    if ($action === 'coupon') {
        $code = strtoupper(post('code'));
        if ($code === '') {
            unset($_SESSION['coupon']);
        } else {
            [$d, $err] = check_discount($code, cart_subtotal(cart_items()));
            if ($err) {
                flash($err, 'err');
            } else {
                $_SESSION['coupon'] = $d['code'];
                flash('Kodi ' . $d['code'] . ' u aplikua.');
            }
        }
    }
    redirect('cart');
}

$items = cart_items();
$subtotal = cart_subtotal($items);
$discount = 0.0;
$couponErr = null;
if (!empty($_SESSION['coupon'])) {
    [$d, $couponErr] = check_discount((string)$_SESSION['coupon'], $subtotal);
    if ($d) {
        $discount = round($subtotal * (float)$d['percent'] / 100, 2);
    } else {
        unset($_SESSION['coupon']);
    }
}
$shipping = shipping_for($subtotal - $discount);
$total = round($subtotal - $discount + $shipping, 2);

site_header('Shopping bag');
?>
<div class="wrap cartpage">
  <h1 class="ph">SHOPPING BAG</h1>
  <?php if ($couponErr): ?><div class="flash err"><?= e($couponErr) ?></div><?php endif; ?>
  <?php if (!$items): ?>
    <p class="muted-s empty">Shporta jote është bosh. <a href="<?= url('shop') ?>">Vazhdo blerjen</a></p>
  <?php else: ?>
  <div class="cart-layout">
    <form method="post" class="cart-items"><?= csrf_field() ?><input type="hidden" name="action" value="update">
      <?php foreach ($items as $it): $out = (int)$it['stock'] < 1; ?>
        <div class="citem">
          <a class="cimg" href="<?= url('product?id=' . $it['id']) ?>"><?php if ($it['img']): ?><img src="<?= e(upload_url($it['img'])) ?>" alt=""><?php else: ?><div class="noimg"></div><?php endif; ?></a>
          <div class="cinfo">
            <a href="<?= url('product?id=' . $it['id']) ?>"><b><?= e($it['name']) ?></b></a>
            <div class="muted-s"><?= e(trim(($it['size'] ? 'Size: ' . $it['size'] : '') . ($it['color'] ? '  ·  ' . $it['color'] : ''), ' ·')) ?></div>
            <?php if ($out): ?><div class="lowmsg">Nuk ka më stok</div><?php endif; ?>
            <div class="muted-s"><?= money($it['unit']) ?> × <input class="qin" type="number" min="0" max="<?= (int)$it['stock'] ?>" name="qty[<?= e($it['cart_key']) ?>]" value="<?= (int)$it['qty'] ?>"></div>
          </div>
          <div class="cprice"><?= money($it['line']) ?>
            <button type="submit" class="rm" name="remove" value="<?= e($it['cart_key']) ?>">REMOVE</button>
          </div>
        </div>
      <?php endforeach; ?>
      <button class="btn-b" type="submit">UPDATE BAG</button>
    </form>

    <aside class="summary">
      <h3>ORDER SUMMARY</h3>
      <form method="post" class="coupon"><?= csrf_field() ?><input type="hidden" name="action" value="coupon">
        <input name="code" placeholder="Discount code" value="<?= e($_SESSION['coupon'] ?? '') ?>"><button class="btn-b" type="submit"><?= !empty($_SESSION['coupon']) ? 'CHANGE' : 'APPLY' ?></button>
      </form>
      <dl>
        <dt>Subtotal</dt><dd><?= money($subtotal) ?></dd>
        <?php if ($discount > 0): ?><dt>Discount (<?= e($_SESSION['coupon']) ?>)</dt><dd>−<?= money($discount) ?></dd><?php endif; ?>
        <dt>Shipping</dt><dd><?= $shipping > 0 ? money($shipping) : 'FREE' ?></dd>
        <dt class="tot">TOTAL</dt><dd class="tot"><?= money($total) ?></dd>
      </dl>
      <a class="btn-w full" href="<?= url('checkout') ?>">CHECKOUT</a>
      <a class="lnk" href="<?= url('shop') ?>">Continue shopping</a>
    </aside>
  </div>
  <?php endif; ?>
</div>
<?php site_footer(); ?>
