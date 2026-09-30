<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_admin();

$editId = (int)($_GET['edit'] ?? 0);

if (is_post()) {
    csrf_verify();
    $action = post('action');
    $id = (int)post('id');

    if ($action === 'delete' && $id) {
        q('DELETE FROM discounts WHERE id = ?', [$id]);
        flash('Kodi u fshi.');
    } elseif ($action === 'toggle' && $id) {
        q('UPDATE discounts SET active = 1 - active WHERE id = ?', [$id]);
        flash('Statusi u ndryshua.');
    } elseif ($action === 'save') {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', post('code')));
        $percent = (float)str_replace(',', '.', post('percent'));
        if ($code === '' || $percent <= 0 || $percent > 100) {
            flash('Shkruaj kodin dhe një përqindje mes 1 dhe 100.', 'err');
            redirect('admin/discounts' . ($id ? '?edit=' . $id : ''));
        }
        $data = [
            'code' => $code,
            'percent' => $percent,
            'start_date' => post('start_date') ?: null,
            'end_date' => post('end_date') ?: null,
            'min_order' => (float)post('min_order', '0'),
            'max_uses' => max(0, (int)post('max_uses')),
            'active' => post('active') === '1' ? 1 : 0,
        ];
        if (val('SELECT COUNT(*) FROM discounts WHERE code = ? AND id <> ?', [$code, $id])) {
            flash('Ky kod ekziston tashmë.', 'err');
            redirect('admin/discounts' . ($id ? '?edit=' . $id : ''));
        }
        if ($id) {
            update('discounts', $data, 'id = ?', [$id]);
        } else {
            insert('discounts', $data);
        }
        flash('Discount code u ruajt.');
    }
    redirect('admin/discounts');
}

$list = rows('SELECT * FROM discounts ORDER BY id DESC');
$d = $editId ? row('SELECT * FROM discounts WHERE id = ?', [$editId]) : null;
$d = $d ?: ['id' => 0, 'code' => '', 'percent' => '', 'start_date' => '', 'end_date' => '', 'min_order' => 0, 'max_uses' => 0, 'active' => 1];

admin_header('Discounts', 'discounts');
?>
<div class="grid-2 wide-left">
  <section class="card flush">
    <div class="table-scroll">
    <table class="tbl">
      <tr><th>Code</th><th>Discount</th><th>Valid</th><th>Min order</th><th>Uses</th><th>Status</th><th>Actions</th></tr>
      <?php foreach ($list as $x): ?>
        <tr>
          <td><b><?= e($x['code']) ?></b></td>
          <td><?= rtrim(rtrim(number_format((float)$x['percent'], 2), '0'), '.') ?>% OFF</td>
          <td><small><?= e($x['start_date'] ?: '—') ?> → <?= e($x['end_date'] ?: '—') ?></small></td>
          <td><?= money($x['min_order']) ?></td>
          <td><?= (int)$x['used'] ?> / <?= (int)$x['max_uses'] ?: '∞' ?></td>
          <td><?= status_badge($x['active'] ? 'active' : 'inactive') ?></td>
          <td class="actions">
            <a class="link" href="?edit=<?= $x['id'] ?>">EDIT</a>
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $x['id'] ?>"><button class="link" type="submit"><?= $x['active'] ? 'DEACTIVATE' : 'ACTIVATE' ?></button></form>
            <?= delete_form(url('admin/discounts'), ['action' => 'delete', 'id' => $x['id']], 'Are you sure you want to delete this discount code?') ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$list): ?><tr><td colspan="7" class="muted center">Ende nuk ka kode zbritjeje.</td></tr><?php endif; ?>
    </table>
    </div>
  </section>
  <section class="card">
    <h2><?= $d['id'] ? 'EDIT CODE' : 'NEW DISCOUNT CODE' ?></h2>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
      <label>Code</label><input name="code" value="<?= e($d['code']) ?>" placeholder="UTILITY10" required>
      <label>Discount %</label><input name="percent" type="number" step="0.01" min="1" max="100" value="<?= e($d['percent']) ?>" required>
      <div class="row2">
        <div><label>Start date</label><input type="date" name="start_date" value="<?= e($d['start_date']) ?>"></div>
        <div><label>End date</label><input type="date" name="end_date" value="<?= e($d['end_date']) ?>"></div>
        <div><label>Minimum order (€)</label><input type="number" step="0.01" min="0" name="min_order" value="<?= e($d['min_order']) ?>"></div>
        <div><label>Maximum uses (0 = pa limit)</label><input type="number" min="0" name="max_uses" value="<?= (int)$d['max_uses'] ?>"></div>
      </div>
      <label>Status</label>
      <select name="active"><option value="1" <?= $d['active'] ? 'selected' : '' ?>>Active</option><option value="0" <?= !$d['active'] ? 'selected' : '' ?>>Inactive</option></select>
      <button class="btn block" type="submit">SAVE CODE</button>
      <?php if ($d['id']): ?><a class="btn ghost block" href="<?= url('admin/discounts') ?>">CANCEL</a><?php endif; ?>
    </form>
  </section>
</div>
<?php admin_footer(); ?>
