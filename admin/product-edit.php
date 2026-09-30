<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$product = $id ? row('SELECT * FROM products WHERE id = ?', [$id]) : null;
if ($id && !$product) {
    flash('Produkti nuk u gjet.', 'err');
    redirect('admin/products');
}

if (is_post()) {
    csrf_verify();
    $errors = [];
    $name = post('name');
    $price = (float)str_replace(',', '.', post('price', '0'));
    $saleRaw = str_replace(',', '.', post('sale_price'));
    $sale = $saleRaw !== '' ? (float)$saleRaw : null;
    $discount = (float)str_replace(',', '.', post('discount', '0'));
    if ($sale === null && $discount > 0 && $discount < 100) {
        $sale = round($price * (1 - $discount / 100), 2);
    }
    if ($sale !== null && ($sale <= 0 || $sale >= $price)) {
        $sale = null;
    }
    $status = in_array(post('status'), ['draft', 'published', 'out_of_stock'], true) ? post('status') : 'draft';
    if (post('stock_state') === 'out' && $status === 'published') {
        $status = 'out_of_stock';
    } elseif (post('stock_state') === 'in' && $status === 'out_of_stock') {
        $status = 'published';
    }

    if ($name === '') {
        $errors[] = 'Emri i produktit është i detyrueshëm.';
    }
    if ($price <= 0) {
        $errors[] = 'Çmimi duhet të jetë më i madh se 0.';
    }

    if (!$errors) {
        $data = [
            'name' => $name,
            'description' => post('description'),
            'category_id' => (int)post('category_id') ?: null,
            'subcategory_id' => (int)post('subcategory_id') ?: null,
            'brand' => post('brand') ?: null,
            'sku' => post('sku') ?: null,
            'price' => $price,
            'sale_price' => $sale,
            'stock' => max(0, (int)post('stock')),
            'low_stock_threshold' => max(1, (int)post('low_stock_threshold', '5')),
            'status' => $status,
            'badge_new' => isset($_POST['badge_new']) ? 1 : 0,
            'badge_sale' => (isset($_POST['badge_sale']) || $sale !== null) ? 1 : 0,
            'badge_best' => isset($_POST['badge_best']) ? 1 : 0,
            'badge_featured' => isset($_POST['badge_featured']) ? 1 : 0,
            'sizes' => post('sizes') ?: null,
            'colors' => post('colors') ?: null,
            'material' => post('material') ?: null,
            'weight' => post('weight') ?: null,
            'details' => post('details'),
            'updated_at' => now(),
        ];

        if ($product) {
            update('products', $data, 'id = ?', [$id]);
        } else {
            $data['created_at'] = now();
            $id = insert('products', $data);
        }

        // --- fotot ---
        // 1) fshi të zgjedhurat
        foreach ((array)($_POST['img_del'] ?? []) as $delId) {
            $im = row('SELECT * FROM product_images WHERE id = ? AND product_id = ?', [(int)$delId, $id]);
            if ($im) {
                q('DELETE FROM product_images WHERE id = ?', [$im['id']]);
                delete_image_file($im['path']);
            }
        }
        // 2) renditja e fotove ekzistuese
        $n = 10;
        foreach ((array)($_POST['img_order'] ?? []) as $imgId) {
            q('UPDATE product_images SET sort = ? WHERE id = ? AND product_id = ?', [$n, (int)$imgId, $id]);
            $n += 10;
        }
        // 3) main image e re (vendoset e para)
        $mainFiles = files_list('main_image');
        if ($mainFiles && ($path = save_upload($mainFiles[0]))) {
            insert('product_images', ['product_id' => $id, 'path' => $path, 'sort' => -1]);
        }
        // 4) foto shtesë (në fund)
        $extra = 100000;
        foreach (files_list('images') as $f) {
            if ($path = save_upload($f)) {
                insert('product_images', ['product_id' => $id, 'path' => $path, 'sort' => $extra++]);
            }
        }
        // 5) normalizo renditjen
        $i = 0;
        foreach (rows('SELECT id FROM product_images WHERE product_id = ? ORDER BY sort, id', [$id]) as $im) {
            q('UPDATE product_images SET sort = ? WHERE id = ?', [$i++, $im['id']]);
        }

        flash($product ? 'Ndryshimet u ruajtën.' : 'Produkti u shtua.');
        redirect('admin/product-edit?id=' . $id);
    }
    foreach ($errors as $er) {
        flash($er, 'err');
    }
    $product = array_merge($product ?? [], $_POST);
}

$p = $product ?? [
    'name' => '', 'description' => '', 'category_id' => '', 'subcategory_id' => '', 'brand' => 'UTILITY', 'sku' => '',
    'price' => '', 'sale_price' => '', 'stock' => 0, 'low_stock_threshold' => 5, 'status' => 'published',
    'badge_new' => 0, 'badge_sale' => 0, 'badge_best' => 0, 'badge_featured' => 0,
    'sizes' => '', 'colors' => '', 'material' => '', 'weight' => '', 'details' => '',
];
$images = $id ? rows('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort, id', [$id]) : [];
$cats = rows('SELECT * FROM categories WHERE parent_id IS NULL ORDER BY sort, name');
$subs = rows('SELECT * FROM categories WHERE parent_id IS NOT NULL ORDER BY name');
$isEdit = $id > 0;
$stockState = ($p['status'] ?? '') === 'out_of_stock' ? 'out' : 'in';

admin_header($isEdit ? 'Edit Product' : 'Add Product', 'products');
?>
<form method="post" enctype="multipart/form-data" class="pform">
  <?= csrf_field() ?>
  <div class="pform-main">

    <section class="card">
      <h2>BASIC INFORMATION</h2>
      <label>Product Name *</label>
      <input name="name" value="<?= e($p['name']) ?>" required>
      <label>Description</label>
      <textarea name="description" rows="5"><?= e($p['description']) ?></textarea>
      <div class="row2">
        <div><label>Category</label>
          <select name="category_id" id="cat-select">
            <option value="">— none —</option>
            <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= (int)$p['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
          </select></div>
        <div><label>Subcategory</label>
          <select name="subcategory_id" id="sub-select">
            <option value="">— none —</option>
            <?php foreach ($subs as $c): ?><option value="<?= $c['id'] ?>" data-parent="<?= (int)$c['parent_id'] ?>" <?= (int)$p['subcategory_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
          </select></div>
      </div>
      <div class="row2">
        <div><label>Brand</label><input name="brand" value="<?= e($p['brand']) ?>"></div>
        <div><label>SKU</label><input name="sku" value="<?= e($p['sku']) ?>"></div>
      </div>
    </section>

    <section class="card">
      <h2>PRICE</h2>
      <div class="row3">
        <div><label>Regular Price (€) *</label><input name="price" type="number" step="0.01" min="0" value="<?= e($p['price']) ?>" required></div>
        <div><label>Sale Price (€)</label><input name="sale_price" type="number" step="0.01" min="0" value="<?= e($p['sale_price']) ?>"></div>
        <div><label>Discount (%)</label><input name="discount" type="number" step="1" min="0" max="99" value="" placeholder="opsionale"></div>
      </div>
      <p class="muted small">Nëse nuk ka Sale Price, në website shfaqet vetëm çmimi normal. Mund të shkruash edhe vetëm Discount % dhe Sale Price llogaritet automatikisht.</p>
    </section>

    <section class="card">
      <h2>INVENTORY</h2>
      <div class="row3">
        <div><label>Stock Quantity</label><input name="stock" type="number" min="0" value="<?= (int)$p['stock'] ?>"></div>
        <div><label>Low Stock Threshold</label><input name="low_stock_threshold" type="number" min="1" value="<?= (int)$p['low_stock_threshold'] ?>"></div>
        <div><label>Availability</label>
          <select name="stock_state">
            <option value="in" <?= $stockState === 'in' ? 'selected' : '' ?>>In Stock</option>
            <option value="out" <?= $stockState === 'out' ? 'selected' : '' ?>>Out of Stock</option>
          </select></div>
      </div>
    </section>

    <section class="card">
      <h2>PRODUCT OPTIONS</h2>
      <div class="row2">
        <div><label>Size (ndaji me presje: S,M,L,XL)</label><input name="sizes" value="<?= e($p['sizes']) ?>"></div>
        <div><label>Color (ndaji me presje: Black,White)</label><input name="colors" value="<?= e($p['colors']) ?>"></div>
        <div><label>Material</label><input name="material" value="<?= e($p['material']) ?>"></div>
        <div><label>Weight</label><input name="weight" value="<?= e($p['weight']) ?>"></div>
      </div>
      <label>Product details</label>
      <textarea name="details" rows="4" placeholder="Një detaj për rresht"><?= e($p['details']) ?></textarea>
    </section>

    <section class="card">
      <h2>IMAGES</h2>
      <?php if ($images): ?>
        <p class="muted small">Fotoja e parë është Main Image. Përdor ↑ ↓ për renditjen.</p>
        <ul class="imglist" id="imglist">
          <?php foreach ($images as $im): ?>
            <li>
              <input type="hidden" name="img_order[]" value="<?= $im['id'] ?>">
              <img src="<?= e(upload_url($im['path'])) ?>" alt="">
              <div class="imgctl">
                <button type="button" class="mv" data-dir="-1">↑</button>
                <button type="button" class="mv" data-dir="1">↓</button>
                <label class="chip"><input type="checkbox" name="img_del[]" value="<?= $im['id'] ?>"> Remove</label>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <label>Main Image <?= $images ? '(e re — zëvendëson pozicionin e parë)' : '' ?></label>
      <input type="file" name="main_image" accept="image/*">
      <label>Additional Images (mund të zgjedhësh shumë)</label>
      <input type="file" name="images[]" accept="image/*" multiple>
    </section>
  </div>

  <aside class="pform-side">
    <section class="card sticky">
      <h2>PRODUCT STATUS</h2>
      <select name="status">
        <?php foreach (['published' => 'Published', 'draft' => 'Draft', 'out_of_stock' => 'Out of Stock'] as $k => $l): ?>
          <option value="<?= $k ?>" <?= $p['status'] === $k ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
      <h2 class="mt">BADGES</h2>
      <label class="check"><input type="checkbox" name="badge_new" <?= !empty($p['badge_new']) ? 'checked' : '' ?>> NEW</label>
      <label class="check"><input type="checkbox" name="badge_sale" <?= !empty($p['badge_sale']) ? 'checked' : '' ?>> SALE</label>
      <label class="check"><input type="checkbox" name="badge_best" <?= !empty($p['badge_best']) ? 'checked' : '' ?>> BEST SELLER</label>
      <label class="check"><input type="checkbox" name="badge_featured" <?= !empty($p['badge_featured']) ? 'checked' : '' ?>> FEATURED</label>
      <button class="btn block" type="submit"><?= $isEdit ? 'SAVE CHANGES' : 'SAVE PRODUCT' ?></button>
      <a class="btn ghost block" href="<?= url('admin/products') ?>">CANCEL</a>
      <?php if ($isEdit): ?><a class="link center-t" target="_blank" href="<?= url('product?id=' . $id) ?>">View on website ↗</a><?php endif; ?>
    </section>
  </aside>
</form>
<?php admin_footer(); ?>
