<?php
declare(strict_types=1);
require __DIR__ . '/includes/site.php';

$where = [visible_products_sql()];
$par = [];
$title = 'Shop';

$catSlug = trim((string)($_GET['category'] ?? ''));
$cat = null;
if ($catSlug !== '') {
    $cat = row('SELECT * FROM categories WHERE slug = ?', [$catSlug]);
    if ($cat) {
        $title = $cat['name'];
        $where[] = '(p.category_id = ? OR p.subcategory_id = ?)';
        array_push($par, $cat['id'], $cat['id']);
    }
}
$q = trim((string)($_GET['q'] ?? ''));
if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.description LIKE ? OR p.brand LIKE ? OR p.sku LIKE ? OR c.name LIKE ?)';
    array_push($par, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%");
    $title = 'Search: ' . $q;
}
if (!empty($_GET['sale'])) {
    $where[] = 'p.sale_price IS NOT NULL AND p.sale_price > 0 AND p.sale_price < p.price';
    $title = 'Sale';
}
if (!empty($_GET['new'])) {
    $where[] = 'p.badge_new = 1';
    $title = 'New Arrivals';
}
if (!empty($_GET['best'])) {
    $where[] = best_seller_sql()[0];
    $title = 'Best Sellers';
}
if (!empty($_GET['instock'])) {
    $where[] = "p.stock > 0 AND p.status = 'published'";
}
if (($_GET['min'] ?? '') !== '') {
    $where[] = '(CASE WHEN p.sale_price > 0 AND p.sale_price < p.price THEN p.sale_price ELSE p.price END) >= ?';
    $par[] = (float)$_GET['min'];
}
if (($_GET['max'] ?? '') !== '') {
    $where[] = '(CASE WHEN p.sale_price > 0 AND p.sale_price < p.price THEN p.sale_price ELSE p.price END) <= ?';
    $par[] = (float)$_GET['max'];
}
$W = implode(' AND ', $where);

$sort = $_GET['sort'] ?? 'new';
$order = match ($sort) {
    'price_asc' => '(CASE WHEN p.sale_price > 0 AND p.sale_price < p.price THEN p.sale_price ELSE p.price END) ASC',
    'price_desc' => '(CASE WHEN p.sale_price > 0 AND p.sale_price < p.price THEN p.sale_price ELSE p.price END) DESC',
    'best' => 'p.sold_count DESC, p.id DESC',
    default => 'p.id DESC',
};

$per = 24;
$total = (int)val("SELECT COUNT(*) FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE $W", $par);
$pages = max(1, (int)ceil($total / $per));
$page = min(max(1, (int)($_GET['page'] ?? 1)), $pages);
$list = rows('SELECT ' . PRODUCT_SELECT . " FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE $W ORDER BY $order LIMIT $per OFFSET " . (($page - 1) * $per), $par);

$cats = rows('SELECT * FROM categories WHERE parent_id IS NULL ORDER BY sort, name');
$subs = $cat ? rows('SELECT * FROM categories WHERE parent_id = ? ORDER BY name', [$cat['parent_id'] ?: $cat['id']]) : [];

$qs = $_GET;
unset($qs['page']);

site_header($title);
?>
<div class="wrap">
  <div class="shop-head">
    <h1><?= e($title) ?></h1>
    <span class="count"><?= $total ?> <?= $total === 1 ? 'product' : 'products' ?></span>
  </div>

  <div class="shop-layout">
    <aside class="filters-side">
      <h4>CATEGORIES</h4>
      <a href="<?= url('shop') ?>" class="<?= !$cat ? 'on' : '' ?>">All</a>
      <?php foreach ($cats as $c): ?>
        <a href="<?= url('shop?category=' . urlencode($c['slug'])) ?>" class="<?= $cat && ((int)$cat['id'] === (int)$c['id'] || (int)$cat['parent_id'] === (int)$c['id']) ? 'on' : '' ?>"><?= e($c['name']) ?></a>
        <?php if ($cat && ((int)$cat['id'] === (int)$c['id'] || (int)$cat['parent_id'] === (int)$c['id'])): foreach ($subs as $s): ?>
          <a class="subl <?= (int)$cat['id'] === (int)$s['id'] ? 'on' : '' ?>" href="<?= url('shop?category=' . urlencode($s['slug'])) ?>"><?= e($s['name']) ?></a>
        <?php endforeach; endif; ?>
      <?php endforeach; ?>
      <form method="get" class="fform">
        <?php if ($catSlug): ?><input type="hidden" name="category" value="<?= e($catSlug) ?>"><?php endif; ?>
        <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
        <?php foreach (['sale', 'new', 'best'] as $k): if (!empty($_GET[$k])): ?><input type="hidden" name="<?= $k ?>" value="1"><?php endif; endforeach; ?>
        <h4>PRICE (€)</h4>
        <div class="two"><input type="number" name="min" min="0" placeholder="Min" value="<?= e($_GET['min'] ?? '') ?>"><input type="number" name="max" min="0" placeholder="Max" value="<?= e($_GET['max'] ?? '') ?>"></div>
        <label class="ck"><input type="checkbox" name="instock" value="1" <?= !empty($_GET['instock']) ? 'checked' : '' ?>> In stock only</label>
        <input type="hidden" name="sort" value="<?= e($sort) ?>">
        <button class="btn-b" type="submit">APPLY</button>
      </form>
    </aside>

    <section>
      <form method="get" class="sortbar">
        <?php foreach ($qs as $k => $v): if ($k !== 'sort' && is_string($v)): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endif; endforeach; ?>
        <label>SORT BY
          <select name="sort" onchange="this.form.submit()">
            <?php foreach (['new' => 'Newest', 'best' => 'Best selling', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low'] as $k => $l): ?>
              <option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </form>
      <?php if ($list): product_grid($list); else: ?>
        <p class="muted-s empty">Nuk u gjet asnjë produkt. <a href="<?= url('shop') ?>">Shiko të gjitha produktet</a></p>
      <?php endif; ?>
      <?php if ($pages > 1): ?>
        <div class="pg"><?php for ($i = 1; $i <= $pages; $i++): $qs['page'] = $i; ?><a class="<?= $i === $page ? 'on' : '' ?>" href="?<?= e(http_build_query($qs)) ?>"><?= $i ?></a><?php endfor; ?></div>
      <?php endif; ?>
    </section>
  </div>
</div>
<?php site_footer(); ?>
