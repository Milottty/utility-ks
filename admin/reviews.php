<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_admin();

if (is_post()) {
    csrf_verify();
    $id = (int)post('id');
    if (post('action') === 'approve' && $id) {
        q('UPDATE reviews SET approved = 1 WHERE id = ?', [$id]);
        flash('Review u aprovua.');
    } elseif (post('action') === 'unapprove' && $id) {
        q('UPDATE reviews SET approved = 0 WHERE id = ?', [$id]);
        flash('Review u fshi nga website (pending).');
    } elseif (post('action') === 'delete' && $id) {
        q('DELETE FROM reviews WHERE id = ?', [$id]);
        flash('Review u fshi.');
    }
    redirect('admin/reviews' . (!empty($_GET['f']) ? '?f=' . urlencode((string)$_GET['f']) : ''));
}

$f = $_GET['f'] ?? '';
$where = $f === 'pending' ? 'WHERE r.approved = 0' : ($f === 'approved' ? 'WHERE r.approved = 1' : '');
$list = rows("SELECT r.*, p.name AS product_name FROM reviews r LEFT JOIN products p ON p.id = r.product_id $where ORDER BY r.id DESC LIMIT 200");

admin_header('Reviews', 'reviews');
?>
<div class="toolbar">
  <div class="tabs">
    <a class="<?= $f === '' ? 'on' : '' ?>" href="<?= url('admin/reviews') ?>">ALL</a>
    <a class="<?= $f === 'pending' ? 'on' : '' ?>" href="?f=pending">PENDING</a>
    <a class="<?= $f === 'approved' ? 'on' : '' ?>" href="?f=approved">APPROVED</a>
  </div>
</div>
<section class="card flush">
  <div class="table-scroll">
  <table class="tbl">
    <tr><th>Customer</th><th>Product</th><th>Rating</th><th>Comment</th><th>Date</th><th>Status</th><th>Actions</th></tr>
    <?php foreach ($list as $r): ?>
      <tr>
        <td><?= e($r['customer_name']) ?></td>
        <td><?= e($r['product_name'] ?: '—') ?></td>
        <td class="stars"><?= stars((float)$r['rating']) ?></td>
        <td class="prodcell"><?= e($r['comment']) ?></td>
        <td><?= e(date('d M Y', strtotime($r['created_at']))) ?></td>
        <td><?= status_badge($r['approved'] ? 'approved' : 'pending') ?></td>
        <td class="actions">
          <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="action" value="<?= $r['approved'] ? 'unapprove' : 'approve' ?>"><button class="link" type="submit"><?= $r['approved'] ? 'UNAPPROVE' : 'APPROVE' ?></button></form>
          <?= delete_form(url('admin/reviews'), ['action' => 'delete', 'id' => $r['id']], 'Are you sure you want to delete this review?') ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="7" class="muted center">Nuk ka reviews.</td></tr><?php endif; ?>
  </table>
  </div>
</section>
<?php admin_footer(); ?>
