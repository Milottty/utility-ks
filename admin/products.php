<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_admin();

/* ---------- actions: delete / duplicate ---------- */
if (is_post()) {
    csrf_verify();
    $action = post('action');
    $id = (int)post('id');

    if ($action === 'delete' && $id) {
        $imgs = rows('SELECT path FROM product_images WHERE product_id = ?', [$id]);
        q('DELETE FROM product_images WHERE product_id = ?', [$id]);
        q('DELETE FROM reviews WHERE product_id = ?', [$id]);
        q('DELETE FROM products WHERE id = ?', [$id]);
        foreach ($imgs as $im) {
            delete_image_file($im['path']);
        }
        flash('Produkti u fshi.');
    }

    if ($action === 'duplicate' && $id) {
        $p = row('SELECT * FROM products WHERE id = ?', [$id]);
        if ($p) {
            unset($p['id']);
            $p['name'] .= ' (Copy)';
            $p['sku'] = $p['sku'] ? $p['sku'] . '-COPY' : null;
            $p['status'] = 'draft';
            $p['sold_count'] = 0;
            $p['created_at'] = $p['updated_at'] = now();
            $newId = insert('products', $p);
            foreach (rows('SELECT path, sort FROM product_images WHERE product_id = ?', [$id]) as $im) {
                insert('product_images', ['product_id' => $newId, 'path' => $im['path'], 'sort' => $im['sort']]);
            }
            flash('Produkti u dublikua (si Draft).');
            redirect('admin/product-edit?id=' . $newId);
        }
    }
    redirect('admin/products' . (!empty($_POST['back']) ? '?' . ltrim((string)$_POST['back'], '?') : ''));
}

/* ---------- filters ---------- */
$where = ['1=1'];
$par = [];
$search = trim((string)($_GET['q'] ?? ''));
if ($search !== '') {
    $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR c.name LIKE ?)';
    array_push($par, "%$search%", "%$search%", "%$search%");
}
if (!empty($_GET['category'])) {
    $where[] = '(p.category_id = ? OR p.subcategory_id = ?)';
    array_push($par, (int)$_GET['category'], (int)$_GET['category']);
}
switch ($_GET['stock'] ?? '') {
    case 'in':
        $where[] = "p.stock >= p.low_stock_threshold AND p.status <> 'out_of_stock'";
        break;
    case 'low':
        $where[] = 'p.stock > 0 AND p.stock < p.low_stock_threshold';
        break;
    case 'out':
        $where[] = "(p.stock <= 0 OR p.status = 'out_of_stock')";
        break;
}
if (in_array($_GET['status'] ?? '', ['draft', 'published', 'out_of_stock'], true)) {
    $where[] = 'p.status = ?';
    $par[] = $_GET['status'];
}
if (($_GET['price_min'] ?? '') !== '') {
    $where[] = 'p.price >= ?';
    $par[] = (float)$_GET['price_min'];
}
if (($_GET['price_max'] ?? '') !== '') {
    $where[] = 'p.price <= ?';
    $par[] = (float)$_GET['price_max'];
}
if (!empty($_GET['sale'])) {
    $where[] = 'p.sale_price IS NOT NULL AND p.sale_price > 0 AND p.sale_price < p.price';
}
if (!empty($_GET['new'])) {
    $where[] = 'p.badge_new = 1';
}
if (!empty($_GET['featured'])) {
    $where[] = 'p.badge_featured = 1';
}
$W = implode(' AND ', $where);

$total = (int)val("SELECT COUNT(*) FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE $W", $par);
[$page, $pages, $offset] = paginate($total, 25, (int)($_GET['page'] ?? 1));
$products = rows('SELECT ' . PRODUCT_SELECT . " FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE $W ORDER BY p.id DESC LIMIT 25 OFFSET " . (int)$offset, $par);
$cats = rows('SELECT * FROM categories ORDER BY parent_id IS NOT NULL, sort, name');
$back = http_build_query($_GET);

admin_header('Products', 'products');
?>
<div class="toolbar">
  <form method="get" class="filters" id="filter-form">
    <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search products..." id="live-search" autocomplete="off">
    <select name="category">
      <option value="">Category</option>
      <?php foreach ($cats as $c): ?>
        <option value="<?= $c['id'] ?>" <?= (string)($_GET['category'] ?? '') === (string)$c['id'] ? 'selected' : '' ?>><?= $c['parent_id'] ? '— ' : '' ?><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="stock">
      <option value="">Stock</option>
      <?php foreach (['in' => 'In stock', 'low' => 'Low stock', 'out' => 'Out of stock'] as $k => $l): ?>
        <option value="<?= $k ?>" <?= ($_GET['stock'] ?? '') === $k ? 'selected' : '' ?>><?= $l ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status">
      <option value="">Status</option>
      <?php foreach (['published' => 'Published', 'draft' => 'Draft', 'out_of_stock' => 'Out of stock'] as $k => $l): ?>
        <option value="<?= $k ?>" <?= ($_GET['status'] ?? '') === $k ? 'selected' : '' ?>><?= $l ?></option>
      <?php endforeach; ?>
    </select>
    <input type="number" step="0.01" min="0" name="price_min" value="<?= e($_GET['price_min'] ?? '') ?>" placeholder="Price min" class="narrow">
    <input type="number" step="0.01" min="0" name="price_max" value="<?= e($_GET['price_max'] ?? '') ?>" placeholder="Price max" class="narrow">
    <label class="chip"><input type="checkbox" name="sale" value="1" <?= !empty($_GET['sale']) ? 'checked' : '' ?>> Sale</label>
    <label class="chip"><input type="checkbox" name="new" value="1" <?= !empty($_GET['new']) ? 'checked' : '' ?>> New</label>
    <label class="chip"><input type="checkbox" name="featured" value="1" <?= !empty($_GET['featured']) ? 'checked' : '' ?>> Featured</label>
    <button class="btn ghost" type="submit">FILTER</button>
    <?php if ($_GET): ?><a class="link" href="<?= url('admin/products') ?>">Reset</a><?php endif; ?>
  </form>
  <a class="btn" href="<?= url('admin/product-edit') ?>">+ ADD PRODUCT</a>
</div>

<section class="card flush">
  <div class="table-scroll">
  <table class="tbl">
    <tr><th>Image</th><th>Product Name</th><th>Category</th><th>Price</th><th>Sale Price</th><th>Stock</th><th>Status</th><th>Actions</th></tr>
    <?php foreach ($products as $p): ?>
      <tr>
        <td><?php if ($p['img']): ?><img class="thumb" src="<?= e(upload_url($p['img'])) ?>" alt=""><?php else: ?><div class="thumb empty"></div><?php endif; ?></td>
        <td><a class="link" href="<?= url('admin/product-edit?id=' . $p['id']) ?>"><b><?= e($p['name']) ?></b></a><br><small class="muted"><?= e($p['sku'] ?: '—') ?></small></td>
        <td><?= e($p['category_name'] ?: '—') ?></td>
        <td><?= money($p['price']) ?></td>
        <td><?= on_sale($p) ? money($p['sale_price']) : '—' ?></td>
        <td><?= (int)$p['stock'] ?><br><?= status_badge(['in' => 'in_stock', 'low' => 'low_stock', 'out' => 'out_of_stock'][stock_state($p)]) ?></td>
        <td><?= status_badge($p['status']) ?></td>
        <td class="actions">
          <a class="link" href="<?= url('admin/product-edit?id=' . $p['id']) ?>">EDIT</a>
          <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="duplicate"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="link" type="submit">DUPLICATE</button></form>
          <form method="post" class="inline" data-confirm="Are you sure you want to delete this product?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>"><button class="link danger-t" type="submit">DELETE</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$products): ?><tr><td colspan="8" class="muted center">Nuk u gjet asnjë produkt.</td></tr><?php endif; ?>
  </table>
  </div>
</section>
<?= pager($page, $pages) ?>
<p class="muted small"><?= $total ?> produkte</p>
<?php admin_footer(); ?>
