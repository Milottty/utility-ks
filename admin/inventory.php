<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_admin();

if (is_post()) {
    csrf_verify();
    $id = (int)post('id');
    if ($id && isset($_POST['stock'])) {
        q('UPDATE products SET stock = ?, updated_at = ? WHERE id = ?', [max(0, (int)$_POST['stock']), now(), $id]);
        flash('Stock u përditësua.');
    }
    redirect('admin/inventory' . (!empty($_POST['back']) ? '?' . ltrim((string)$_POST['back'], '?') : ''));
}

$f = $_GET['state'] ?? '';
$search = trim((string)($_GET['q'] ?? ''));
$where = ["p.status <> 'draft'"];
$par = [];
if ($search !== '') {
    $where[] = '(p.name LIKE ? OR p.sku LIKE ?)';
    array_push($par, "%$search%", "%$search%");
}
if ($f === 'in') {
    $where[] = "p.stock >= p.low_stock_threshold AND p.status <> 'out_of_stock'";
} elseif ($f === 'low') {
    $where[] = 'p.stock > 0 AND p.stock < p.low_stock_threshold';
} elseif ($f === 'out') {
    $where[] = "(p.stock <= 0 OR p.status = 'out_of_stock')";
}
$W = implode(' AND ', $where);
$total = (int)val("SELECT COUNT(*) FROM products p WHERE $W", $par);
[$page, $pages, $offset] = paginate($total, 40, (int)($_GET['page'] ?? 1));
$list = rows("SELECT p.* FROM products p WHERE $W ORDER BY p.stock ASC, p.name LIMIT 40 OFFSET " . (int)$offset, $par);
$back = http_build_query($_GET);

admin_header('Inventory', 'inventory');
?>
<div class="toolbar">
  <form method="get" class="filters">
    <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search product or SKU..." id="live-search">
    <select name="state" onchange="this.form.submit()">
      <option value="">All</option>
      <option value="in" <?= $f === 'in' ? 'selected' : '' ?>>In stock</option>
      <option value="low" <?= $f === 'low' ? 'selected' : '' ?>>Low stock</option>
      <option value="out" <?= $f === 'out' ? 'selected' : '' ?>>Out of stock</option>
    </select>
  </form>
</div>
<section class="card flush">
  <div class="table-scroll">
  <table class="tbl">
    <tr><th>Product</th><th>SKU</th><th>Stock</th><th>Status</th><th>Quick update</th></tr>
    <?php foreach ($list as $p): $st = stock_state($p); ?>
      <tr>
        <td><a class="link" href="<?= url('admin/product-edit?id=' . $p['id']) ?>"><b><?= e($p['name']) ?></b></a></td>
        <td><?= e($p['sku'] ?: '—') ?></td>
        <td><?= (int)$p['stock'] ?></td>
        <td><?= status_badge(['in' => 'in_stock', 'low' => 'low_stock', 'out' => 'out_of_stock'][$st]) ?></td>
        <td>
          <form method="post" class="inline-form"><?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
            <input type="number" min="0" name="stock" value="<?= (int)$p['stock'] ?>" class="narrow">
            <button class="btn ghost sm" type="submit">SAVE</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="5" class="muted center">Nuk ka produkte.</td></tr><?php endif; ?>
  </table>
  </div>
</section>
<?= pager($page, $pages) ?>
<?php admin_footer(); ?>
