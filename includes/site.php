<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function site_header(string $title = '', string $meta = ''): void
{
    $store = setting('store_name', 'UTILITY.KS');
    $cats = rows('SELECT * FROM categories WHERE parent_id IS NULL ORDER BY sort, name');
    $count = cart_count();
    $full = ($title ? $title . ' — ' : '') . $store;
    ?>
<!doctype html>
<html lang="sq">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($full) ?></title>
<?php if ($meta): ?><meta name="description" content="<?= e($meta) ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= asset('css/site.css') ?>">
</head>
<body>
<div class="topbar">FALAS DORËZIM PËR POROSI MBI <?= e(money(setting('free_shipping_over', '100'))) ?></div>
<header class="hdr">
  <button class="menu-btn" type="button" aria-label="Menu" onclick="document.body.classList.toggle('nav-open')">☰</button>
  <a class="logo" href="<?= url('') ?>"><?= e($store) ?></a>
  <nav class="nav" id="nav">
    <a href="<?= url('shop?new=1') ?>">NEW</a>
    <?php foreach ($cats as $c): ?><a href="<?= url('shop?category=' . urlencode($c['slug'])) ?>"><?= e(strtoupper($c['name'])) ?></a><?php endforeach; ?>
    <a href="<?= url('shop?sale=1') ?>" class="sale">SALE</a>
  </nav>
  <div class="hdr-right">
    <form action="<?= url('shop') ?>" class="search" method="get"><input type="search" name="q" placeholder="SEARCH" value="<?= e($_GET['q'] ?? '') ?>" aria-label="Search"></form>
    <a class="cart-link" href="<?= url('cart') ?>">BAG<?= $count ? ' <span>' . $count . '</span>' : '' ?></a>
  </div>
</header>
<main>
<?php foreach (flashes() as [$t, $m]): ?><div class="flash <?= e($t) ?>"><?= e($m) ?></div><?php endforeach; ?>
    <?php
}

function site_footer(): void
{
    $store = setting('store_name', 'UTILITY.KS');
    ?>
</main>
<footer class="ftr">
  <div class="ftr-in">
    <div><div class="logo sm"><?= e($store) ?></div><p>Premium boxing, training &amp; utility wear.</p></div>
    <div><h4>SHOP</h4><a href="<?= url('shop') ?>">All products</a><a href="<?= url('shop?new=1') ?>">New arrivals</a><a href="<?= url('shop?sale=1') ?>">Sale</a></div>
    <div><h4>HELP</h4><a href="<?= url('cart') ?>">Shopping bag</a><?php if (setting('contact_email')): ?><a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(setting('contact_email')) ?></a><?php endif; ?></div>
  </div>
  <div class="copy">© <?= date('Y') ?> <?= e($store) ?>. All rights reserved.</div>
</footer>
<script src="<?= asset('js/site.js') ?>"></script>
</body>
</html>
    <?php
}

function product_card(array $p): void
{
    $price = effective_price($p);
    $out = stock_state($p) === 'out';
    ?>
<a class="pcard" href="<?= url('product?id=' . $p['id']) ?>">
  <div class="pimg">
    <?php if (!empty($p['img'])): ?><img loading="lazy" src="<?= e(upload_url($p['img'])) ?>" alt="<?= e($p['name']) ?>"><?php else: ?><div class="noimg"><?= e(setting('store_name', 'UTILITY.KS')) ?></div><?php endif; ?>
    <div class="tags">
      <?php if ($out): ?><span class="tag dark">SOLD OUT</span><?php endif; ?>
      <?php if (!$out && on_sale($p)): ?><span class="tag">SALE</span><?php endif; ?>
      <?php if (!$out && !empty($p['badge_new'])): ?><span class="tag">NEW</span><?php endif; ?>
      <?php if (!$out && !empty($p['badge_best'])): ?><span class="tag">BEST SELLER</span><?php endif; ?>
    </div>
  </div>
  <div class="pinfo">
    <div class="pbrand"><?= e($p['brand'] ?: ($p['category_name'] ?? '')) ?></div>
    <div class="pname"><?= e($p['name']) ?></div>
    <div class="pprice"><?php if (on_sale($p)): ?><b><?= money($price) ?></b> <s><?= money($p['price']) ?></s><?php else: ?><b><?= money($price) ?></b><?php endif; ?></div>
  </div>
</a>
    <?php
}

function product_grid(array $list): void
{
    echo '<div class="pgrid">';
    foreach ($list as $p) {
        product_card($p);
    }
    echo '</div>';
}

function visible_products_sql(): string
{
    return "p.status IN ('published','out_of_stock')";
}
