<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_admin();

if (is_post()) {
    csrf_verify();
    $action = post('action');

    if ($action === 'hero') {
        foreach (['hero_title', 'hero_subtitle', 'hero_button_text', 'hero_button_link', 'featured_title', 'new_title', 'best_title'] as $k) {
            set_setting($k, post($k));
        }
        set_setting('section_limit', (string)max(2, min(24, (int)post('section_limit', '8'))));
        $files = files_list('hero_image');
        if ($files && ($path = save_upload($files[0]))) {
            $old = setting('hero_image');
            set_setting('hero_image', $path);
            if ($old) {
                delete_image_file($old);
            }
        }
        if (isset($_POST['remove_hero'])) {
            $old = setting('hero_image');
            set_setting('hero_image', '');
            if ($old) {
                delete_image_file($old);
            }
        }
        flash('Hero dhe seksionet u ruajtën.');
    } elseif ($action === 'banner_save') {
        $id = (int)post('id');
        $data = [
            'title' => post('title'),
            'subtitle' => post('subtitle') ?: null,
            'link' => post('link') ?: null,
            'sort' => (int)post('sort'),
            'active' => post('active') === '1' ? 1 : 0,
        ];
        if ($data['title'] === '') {
            flash('Banner-i ka nevojë për titull.', 'err');
            redirect('admin/homepage');
        }
        $files = files_list('image');
        $path = $files ? save_upload($files[0]) : null;
        if ($id) {
            if ($path) {
                $old = val('SELECT image FROM banners WHERE id = ?', [$id]);
                $data['image'] = $path;
                update('banners', $data, 'id = ?', [$id]);
                if ($old) {
                    delete_image_file($old);
                }
            } else {
                update('banners', $data, 'id = ?', [$id]);
            }
        } else {
            $data['image'] = $path;
            insert('banners', $data);
        }
        flash('Banner-i u ruajt.');
    } elseif ($action === 'banner_delete') {
        $id = (int)post('id');
        $old = val('SELECT image FROM banners WHERE id = ?', [$id]);
        q('DELETE FROM banners WHERE id = ?', [$id]);
        if ($old) {
            delete_image_file($old);
        }
        flash('Banner-i u fshi.');
    }
    redirect('admin/homepage');
}

$banners = rows('SELECT * FROM banners ORDER BY sort, id');
$counts = [
    'featured' => (int)val('SELECT COUNT(*) FROM products WHERE badge_featured = 1 AND status <> \'draft\''),
    'new' => (int)val('SELECT COUNT(*) FROM products WHERE badge_new = 1 AND status <> \'draft\''),
];

admin_header('Homepage', 'homepage');
?>
<section class="card">
  <h2>HERO &amp; SECTIONS</h2>
  <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="hero">
    <div class="row2">
      <div><label>Hero title</label><input name="hero_title" value="<?= e(setting('hero_title')) ?>"></div>
      <div><label>Hero subtitle</label><input name="hero_subtitle" value="<?= e(setting('hero_subtitle')) ?>"></div>
      <div><label>Hero button text</label><input name="hero_button_text" value="<?= e(setting('hero_button_text')) ?>"></div>
      <div><label>Hero button link</label><input name="hero_button_link" value="<?= e(setting('hero_button_link')) ?>" placeholder="shop"></div>
    </div>
    <label>Hero image</label>
    <?php if (setting('hero_image')): ?>
      <div class="hero-prev"><img src="<?= e(upload_url(setting('hero_image'))) ?>" alt=""></div>
      <label class="chip"><input type="checkbox" name="remove_hero"> Remove hero image</label>
    <?php endif; ?>
    <input type="file" name="hero_image" accept="image/*">
    <p class="muted small">Rekomandim: foto horizontale, min. 1800px e gjerë.</p>

    <h2 class="mt">PRODUCT SECTIONS</h2>
    <div class="row2">
      <div><label>Featured — titulli</label><input name="featured_title" value="<?= e(setting('featured_title')) ?>"></div>
      <div><label>New Arrivals — titulli</label><input name="new_title" value="<?= e(setting('new_title')) ?>"></div>
      <div><label>Best Sellers — titulli</label><input name="best_title" value="<?= e(setting('best_title')) ?>"></div>
      <div><label>Produkte për seksion</label><input type="number" name="section_limit" min="2" max="24" value="<?= e(setting('section_limit', '8')) ?>"></div>
    </div>
    <p class="muted small">
      <b>Featured</b> = produktet me badge FEATURED (<?= $counts['featured'] ?> tani). <b>New Arrivals</b> = produktet me badge NEW (<?= $counts['new'] ?> tani).
      <b>Best Sellers</b> = badge BEST SELLER ose produktet me së paku <?= e(setting('best_seller_min')) ?> copë të shitura (ndryshohet te Settings).
      Zgjidh cilat produkte shfaqen duke ndryshuar badge-et te Products.
    </p>
    <button class="btn" type="submit">SAVE HOMEPAGE</button>
  </form>
</section>

<section class="card">
  <h2>PROMOTIONAL BANNERS</h2>
  <?php foreach ($banners as $b): ?>
    <form method="post" enctype="multipart/form-data" class="banner-row"><?= csrf_field() ?><input type="hidden" name="action" value="banner_save"><input type="hidden" name="id" value="<?= $b['id'] ?>">
      <?php if ($b['image']): ?><img class="bthumb" src="<?= e(upload_url($b['image'])) ?>" alt=""><?php else: ?><div class="bthumb empty"></div><?php endif; ?>
      <div class="bfields">
        <input name="title" value="<?= e($b['title']) ?>" placeholder="Title">
        <input name="subtitle" value="<?= e($b['subtitle']) ?>" placeholder="Subtitle">
        <input name="link" value="<?= e($b['link']) ?>" placeholder="Link (p.sh. shop?sale=1)">
        <div class="inline-form">
          <input type="number" name="sort" value="<?= (int)$b['sort'] ?>" class="narrow" title="Renditja">
          <select name="active"><option value="1" <?= $b['active'] ? 'selected' : '' ?>>Active</option><option value="0" <?= !$b['active'] ? 'selected' : '' ?>>Hidden</option></select>
          <input type="file" name="image" accept="image/*">
        </div>
      </div>
      <div class="bactions">
        <button class="btn ghost sm" type="submit">SAVE</button>
        <?= delete_form(url('admin/homepage'), ['action' => 'banner_delete', 'id' => $b['id']], 'Are you sure you want to delete this banner?') ?>
      </div>
    </form>
  <?php endforeach; ?>

  <h2 class="mt">ADD BANNER</h2>
  <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="banner_save"><input type="hidden" name="id" value="0">
    <div class="row2">
      <div><label>Title</label><input name="title" required></div>
      <div><label>Subtitle</label><input name="subtitle"></div>
      <div><label>Link</label><input name="link" placeholder="shop?category=boxing"></div>
      <div><label>Order</label><input type="number" name="sort" value="0"></div>
    </div>
    <input type="hidden" name="active" value="1">
    <label>Image</label><input type="file" name="image" accept="image/*">
    <button class="btn" type="submit">ADD BANNER</button>
  </form>
</section>
<?php admin_footer(); ?>
