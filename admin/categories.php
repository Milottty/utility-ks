<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_admin();

function unique_slug(string $name, int $exceptId = 0): string
{
    $base = slugify($name);
    $slug = $base;
    $i = 2;
    while (val('SELECT COUNT(*) FROM categories WHERE slug = ? AND id <> ?', [$slug, $exceptId])) {
        $slug = $base . '-' . $i++;
    }
    return $slug;
}

if (is_post()) {
    csrf_verify();
    $action = post('action');
    $name = post('name');
    $id = (int)post('id');

    if ($action === 'add' && $name !== '') {
        $parent = (int)post('parent_id') ?: null;
        insert('categories', ['parent_id' => $parent, 'name' => $name, 'slug' => unique_slug($name), 'sort' => (int)val('SELECT COALESCE(MAX(sort),0)+1 FROM categories')]);
        flash($parent ? 'Nënkategoria u shtua.' : 'Kategoria u shtua.');
    } elseif ($action === 'edit' && $id && $name !== '') {
        update('categories', ['name' => $name, 'slug' => unique_slug($name, $id)], 'id = ?', [$id]);
        flash('Kategoria u ndryshua.');
    } elseif ($action === 'delete' && $id) {
        $children = rows('SELECT id FROM categories WHERE parent_id = ?', [$id]);
        $ids = array_merge([$id], array_column($children, 'id'));
        foreach ($ids as $cid) {
            q('UPDATE products SET category_id = NULL WHERE category_id = ?', [$cid]);
            q('UPDATE products SET subcategory_id = NULL WHERE subcategory_id = ?', [$cid]);
            q('DELETE FROM categories WHERE id = ?', [$cid]);
        }
        flash('Kategoria u fshi. Produktet e saj mbeten pa kategori.');
    }
    redirect('admin/categories');
}

$top = rows('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS n FROM categories c WHERE parent_id IS NULL ORDER BY sort, name');
$subsByParent = [];
foreach (rows('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.subcategory_id = c.id) AS n FROM categories c WHERE parent_id IS NOT NULL ORDER BY name') as $s) {
    $subsByParent[$s['parent_id']][] = $s;
}

admin_header('Categories', 'categories');
?>
<div class="grid-2 wide-left">
  <section class="card">
    <h2>ALL CATEGORIES</h2>
    <?php foreach ($top as $c): ?>
      <div class="cat-row">
        <form method="post" class="inline-form grow"><?= csrf_field() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?= $c['id'] ?>">
          <input name="name" value="<?= e($c['name']) ?>"><span class="muted small"><?= (int)$c['n'] ?> produkte</span>
          <button class="btn ghost sm" type="submit">SAVE</button>
        </form>
        <?= delete_form(url('admin/categories'), ['action' => 'delete', 'id' => $c['id']], 'Are you sure you want to delete this category? Produktet nuk fshihen.') ?>
      </div>
      <?php foreach ($subsByParent[$c['id']] ?? [] as $s): ?>
        <div class="cat-row sub">
          <form method="post" class="inline-form grow"><?= csrf_field() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?= $s['id'] ?>">
            <span class="muted">↳</span><input name="name" value="<?= e($s['name']) ?>"><span class="muted small"><?= (int)$s['n'] ?> produkte</span>
            <button class="btn ghost sm" type="submit">SAVE</button>
          </form>
          <?= delete_form(url('admin/categories'), ['action' => 'delete', 'id' => $s['id']], 'Are you sure you want to delete this subcategory?') ?>
        </div>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <?php if (!$top): ?><p class="muted">Nuk ka kategori.</p><?php endif; ?>
  </section>
  <div>
    <section class="card">
      <h2>ADD CATEGORY</h2>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add">
        <label>Name</label><input name="name" required>
        <button class="btn block" type="submit">ADD CATEGORY</button>
      </form>
    </section>
    <section class="card">
      <h2>ADD SUBCATEGORY</h2>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add">
        <label>Parent category</label>
        <select name="parent_id" required><?php foreach ($top as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select>
        <label>Name</label><input name="name" required>
        <button class="btn block" type="submit">ADD SUBCATEGORY</button>
      </form>
    </section>
  </div>
</div>
<?php admin_footer(); ?>
