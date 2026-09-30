<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_admin();

if (is_post()) {
    csrf_verify();
    $action = post('action');

    if ($action === 'store') {
        set_setting('store_name', post('store_name') ?: 'UTILITY.KS');
        set_setting('contact_email', post('contact_email'));
        set_setting('shipping_cost', (string)max(0, (float)str_replace(',', '.', post('shipping_cost', '0'))));
        set_setting('free_shipping_over', (string)max(0, (float)str_replace(',', '.', post('free_shipping_over', '0'))));
        set_setting('best_seller_min', (string)max(1, (int)post('best_seller_min', '5')));
        flash('Settings u ruajtën.');
    } elseif ($action === 'account') {
        $admin = row('SELECT * FROM admins WHERE id = ?', [(int)$_SESSION['admin_id']]);
        if (!$admin || !password_verify((string)($_POST['current'] ?? ''), $admin['password_hash'])) {
            flash('Fjalëkalimi aktual nuk është i saktë.', 'err');
        } else {
            $email = strtolower(post('email'));
            $new = (string)($_POST['new_password'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('Email i pavlefshëm.', 'err');
            } elseif ($new !== '' && strlen($new) < 8) {
                flash('Fjalëkalimi i ri duhet të ketë të paktën 8 karaktere.', 'err');
            } elseif (val('SELECT COUNT(*) FROM admins WHERE email = ? AND id <> ?', [$email, $admin['id']])) {
                flash('Ky email përdoret tashmë.', 'err');
            } else {
                $d = ['email' => $email];
                if ($new !== '') {
                    $d['password_hash'] = password_hash($new, PASSWORD_DEFAULT);
                }
                update('admins', $d, 'id = ?', [$admin['id']]);
                flash('Të dhënat e adminit u ruajtën.');
            }
        }
    }
    redirect('admin/settings');
}

$admin = row('SELECT * FROM admins WHERE id = ?', [(int)$_SESSION['admin_id']]);
admin_header('Settings', 'settings');
?>
<div class="grid-2">
  <section class="card">
    <h2>STORE</h2>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="store">
      <label>Store name</label><input name="store_name" value="<?= e(setting('store_name')) ?>">
      <label>Contact email</label><input type="email" name="contact_email" value="<?= e(setting('contact_email')) ?>">
      <div class="row2">
        <div><label>Shipping cost (€)</label><input type="number" step="0.01" min="0" name="shipping_cost" value="<?= e(setting('shipping_cost')) ?>"></div>
        <div><label>Free shipping over (€, 0 = kurrë)</label><input type="number" step="0.01" min="0" name="free_shipping_over" value="<?= e(setting('free_shipping_over')) ?>"></div>
      </div>
      <label>Best Seller after X sold units</label><input type="number" min="1" name="best_seller_min" value="<?= e(setting('best_seller_min')) ?>">
      <button class="btn block" type="submit">SAVE SETTINGS</button>
    </form>
  </section>
  <section class="card">
    <h2>ADMIN ACCOUNT</h2>
    <form method="post" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="action" value="account">
      <label>Email</label><input type="email" name="email" value="<?= e($admin['email'] ?? '') ?>" required>
      <label>New password (lëre bosh për të mos e ndryshuar)</label><input type="password" name="new_password" minlength="8">
      <label>Current password *</label><input type="password" name="current" required>
      <button class="btn block" type="submit">UPDATE ACCOUNT</button>
    </form>
  </section>
</div>
<?php admin_footer(); ?>
