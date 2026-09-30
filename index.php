<?php
declare(strict_types=1);
require __DIR__ . '/includes/site.php';

$limit = max(2, min(24, (int)setting('section_limit', '8')));
$base = 'SELECT ' . PRODUCT_SELECT . ' FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE ' . visible_products_sql();

$featured = rows($base . ' AND p.badge_featured = 1 ORDER BY p.updated_at DESC, p.id DESC LIMIT ' . $limit);
$new = rows($base . ' AND p.badge_new = 1 ORDER BY p.id DESC LIMIT ' . $limit);
[$bestCond] = best_seller_sql();
$best = rows($base . ' AND ' . $bestCond . ' ORDER BY p.sold_count DESC, p.id DESC LIMIT ' . $limit);
$banners = rows('SELECT * FROM banners WHERE active = 1 ORDER BY sort, id');
$cats = rows('SELECT * FROM categories WHERE parent_id IS NULL ORDER BY sort, name');
$hero = setting('hero_image');

site_header('', setting('hero_subtitle'));
?>
<section class="hero" <?= $hero ? 'style="background-image:linear-gradient(rgba(0,0,0,.45),rgba(0,0,0,.65)),url(\'' . e(upload_url($hero)) . '\')"' : '' ?>>
  <div class="hero-in">
    <h1><?= e(setting('hero_title')) ?></h1>
    <p><?= e(setting('hero_subtitle')) ?></p>
    <?php if (setting('hero_button_text')): ?><a class="btn-w" href="<?= e(preg_match('#^https?://#', setting('hero_button_link')) ? setting('hero_button_link') : url(setting('hero_button_link', 'shop'))) ?>"><?= e(setting('hero_button_text')) ?></a><?php endif; ?>
  </div>
</section>

<?php if ($banners): ?>
<section class="wrap banners">
  <?php foreach ($banners as $b): $href = $b['link'] ? (preg_match('#^https?://#', $b['link']) ? $b['link'] : url($b['link'])) : url('shop'); ?>
    <a class="banner" href="<?= e($href) ?>" <?= $b['image'] ? 'style="background-image:linear-gradient(rgba(0,0,0,.35),rgba(0,0,0,.6)),url(\'' . e(upload_url($b['image'])) . '\')"' : '' ?>>
      <span><?= e($b['title']) ?></span><small><?= e($b['subtitle']) ?></small>
    </a>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<?php if ($cats): ?>
<section class="wrap catstrip">
  <?php foreach ($cats as $c): ?><a href="<?= url('shop?category=' . urlencode($c['slug'])) ?>"><?= e(strtoupper($c['name'])) ?></a><?php endforeach; ?>
</section>
<?php endif; ?>

<?php foreach ([[setting('featured_title'), $featured, 'shop'], [setting('new_title'), $new, 'shop?new=1'], [setting('best_title'), $best, 'shop?best=1']] as [$title, $list, $more]): ?>
  <?php if ($list): ?>
  <section class="wrap sec">
    <div class="sec-head"><h2><?= e($title) ?></h2><a href="<?= url($more) ?>">VIEW ALL →</a></div>
    <?php product_grid($list); ?>
  </section>
  <?php endif; ?>
<?php endforeach; ?>

<?php if (!$featured && !$new && !$best): ?>
  <section class="wrap sec"><p class="muted-s">Produktet do të shfaqen këtu sapo të shtohen nga Admin Dashboard.</p></section>
<?php endif; ?>
<?php site_footer(); ?>
