<?php
declare(strict_types=1);
require __DIR__ . '/includes/site.php';

$id = (int)($_GET['id'] ?? 0);
$p = row('SELECT ' . PRODUCT_SELECT . ' FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = ?', [$id]);
if (!$p || $p['status'] === 'draft') {
    http_response_code(404);
    site_header('Not found');
    echo '<div class="wrap"><p class="muted-s empty">Ky produkt nuk ekziston më. <a href="' . e(url('shop')) . '">Kthehu te dyqani</a></p></div>';
    site_footer();
    exit;
}

// review i ri
if (is_post() && post('action') === 'review') {
    csrf_verify();
    $name = post('name');
    $rating = max(1, min(5, (int)post('rating', '5')));
    $comment = post('comment');
    if ($name === '' || $comment === '') {
        flash('Shkruaj emrin dhe komentin.', 'err');
    } else {
        insert('reviews', ['product_id' => $id, 'customer_name' => mb_substr($name, 0, 80), 'rating' => $rating, 'comment' => mb_substr($comment, 0, 1500), 'approved' => 0, 'created_at' => now()]);
        flash('Faleminderit! Review-i yt do të shfaqet pasi të miratohet.');
    }
    redirect('product?id=' . $id . '#reviews');
}

$images = rows('SELECT path FROM product_images WHERE product_id = ? ORDER BY sort, id', [$id]);
$sizes = split_list($p['sizes']);
$colors = split_list($p['colors']);
$price = effective_price($p);
$state = stock_state($p);
$buyable = can_buy($p);
$reviews = rows('SELECT * FROM reviews WHERE product_id = ? AND approved = 1 ORDER BY id DESC', [$id]);
$avg = $reviews ? array_sum(array_column($reviews, 'rating')) / count($reviews) : 0;
$related = rows('SELECT ' . PRODUCT_SELECT . ' FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE ' . visible_products_sql() . ' AND p.id <> ? AND p.category_id ' . ($p['category_id'] ? '= ?' : 'IS NOT NULL') . ' ORDER BY p.id DESC LIMIT 4', $p['category_id'] ? [$id, $p['category_id']] : [$id]);
$details = array_filter(array_map('trim', preg_split('/\R/', (string)$p['details'])));

site_header($p['name'], mb_substr(strip_tags((string)$p['description']), 0, 155));
?>
<div class="wrap pdp">
  <div class="crumbs"><a href="<?= url('') ?>">Home</a> / <a href="<?= url('shop') ?>">Shop</a><?php if ($p['category_name']): ?> / <a href="<?= url('shop?category=' . urlencode((string)val('SELECT slug FROM categories WHERE id = ?', [$p['category_id']]))) ?>"><?= e($p['category_name']) ?></a><?php endif; ?></div>
  <div class="pdp-grid">
    <div class="gallery">
      <div class="gmain">
        <?php if ($images): ?><img id="gmain" src="<?= e(upload_url($images[0]['path'])) ?>" alt="<?= e($p['name']) ?>"><?php else: ?><div class="noimg big"><?= e(setting('store_name', 'UTILITY.KS')) ?></div><?php endif; ?>
      </div>
      <?php if (count($images) > 1): ?>
      <div class="gthumbs">
        <?php foreach ($images as $i => $im): ?><button type="button" class="<?= $i === 0 ? 'on' : '' ?>" data-src="<?= e(upload_url($im['path'])) ?>"><img src="<?= e(upload_url($im['path'])) ?>" alt=""></button><?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="pinfo-big">
      <div class="pbrand"><?= e($p['brand'] ?: $p['category_name']) ?></div>
      <h1><?= e($p['name']) ?></h1>
      <div class="badges">
        <?php if (on_sale($p)): ?><span class="tag">SALE</span><?php endif; ?>
        <?php if ($p['badge_new']): ?><span class="tag">NEW</span><?php endif; ?>
        <?php if ($p['badge_best']): ?><span class="tag">BEST SELLER</span><?php endif; ?>
      </div>
      <div class="price-big"><?php if (on_sale($p)): ?><b><?= money($price) ?></b> <s><?= money($p['price']) ?></s> <span class="off">−<?= round((1 - $price / (float)$p['price']) * 100) ?>%</span><?php else: ?><b><?= money($price) ?></b><?php endif; ?></div>
      <?php if ($reviews): ?><a class="rating" href="#reviews"><span><?= stars($avg) ?></span> <?= number_format($avg, 1) ?> (<?= count($reviews) ?>)</a><?php endif; ?>

      <?php if ($buyable): ?>
        <form method="post" action="<?= url('cart') ?>" class="buy">
          <?= csrf_field() ?><input type="hidden" name="action" value="add"><input type="hidden" name="product_id" value="<?= $id ?>">
          <?php if ($sizes): ?>
            <div class="opt"><span>SIZE</span><div class="opts">
              <?php foreach ($sizes as $i => $s): ?><label><input type="radio" name="size" value="<?= e($s) ?>" required <?= count($sizes) === 1 ? 'checked' : '' ?>><i><?= e($s) ?></i></label><?php endforeach; ?>
            </div></div>
          <?php endif; ?>
          <?php if ($colors): ?>
            <div class="opt"><span>COLOR</span><div class="opts">
              <?php foreach ($colors as $i => $c): ?><label><input type="radio" name="color" value="<?= e($c) ?>" required <?= $i === 0 ? 'checked' : '' ?>><i><?= e($c) ?></i></label><?php endforeach; ?>
            </div></div>
          <?php endif; ?>
          <div class="opt"><span>QTY</span>
            <div class="qty"><button type="button" data-d="-1">−</button><input type="number" name="qty" value="1" min="1" max="<?= (int)$p['stock'] ?>"><button type="button" data-d="1">+</button></div>
          </div>
          <?php if ($state === 'low'): ?><p class="lowmsg">Vetëm <?= (int)$p['stock'] ?> copë të mbetura!</p><?php endif; ?>
          <button class="btn-w full" type="submit">ADD TO BAG</button>
        </form>
      <?php else: ?>
        <button class="btn-w full disabled" type="button" disabled>SOLD OUT</button>
        <p class="muted-s">Ky produkt nuk është aktualisht në stok.</p>
      <?php endif; ?>

      <?php if ($p['description']): ?><div class="desc"><h4>DESCRIPTION</h4><p><?= nl2br(e($p['description'])) ?></p></div><?php endif; ?>
      <?php if ($details || $p['material'] || $p['weight']): ?>
        <div class="desc"><h4>DETAILS</h4><ul>
          <?php foreach ($details as $d): ?><li><?= e($d) ?></li><?php endforeach; ?>
          <?php if ($p['material']): ?><li>Material: <?= e($p['material']) ?></li><?php endif; ?>
          <?php if ($p['weight']): ?><li>Weight: <?= e($p['weight']) ?></li><?php endif; ?>
          <?php if ($p['sku']): ?><li>SKU: <?= e($p['sku']) ?></li><?php endif; ?>
        </ul></div>
      <?php endif; ?>
    </div>
  </div>

  <section id="reviews" class="reviews">
    <h2>REVIEWS</h2>
    <?php foreach ($reviews as $r): ?>
      <div class="review"><div class="rt"><span class="stars"><?= stars((float)$r['rating']) ?></span> <b><?= e($r['customer_name']) ?></b> <small><?= e(date('d M Y', strtotime($r['created_at']))) ?></small></div><p><?= nl2br(e($r['comment'])) ?></p></div>
    <?php endforeach; ?>
    <?php if (!$reviews): ?><p class="muted-s">Ende nuk ka reviews.</p><?php endif; ?>
    <form method="post" class="rform">
      <?= csrf_field() ?><input type="hidden" name="action" value="review">
      <h4>SHKRUAJ NJË REVIEW</h4>
      <div class="two"><input name="name" placeholder="Emri" required maxlength="80">
        <select name="rating"><?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"><?= str_repeat('★', $i) ?></option><?php endfor; ?></select></div>
      <textarea name="comment" rows="3" placeholder="Komenti yt" required maxlength="1500"></textarea>
      <button class="btn-b" type="submit">SUBMIT</button>
    </form>
  </section>

  <?php if ($related): ?>
    <section class="sec"><div class="sec-head"><h2>YOU MAY ALSO LIKE</h2></div><?php product_grid($related); ?></section>
  <?php endif; ?>
</div>
<?php site_footer(); ?>
