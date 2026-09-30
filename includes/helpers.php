<?php
declare(strict_types=1);

const ORDER_STATUSES = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
const PRODUCT_SELECT = "p.*, c.name AS category_name, (SELECT path FROM product_images i WHERE i.product_id = p.id ORDER BY i.sort, i.id LIMIT 1) AS img";

function e($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $f = ROOT . '/assets/' . $path;
    return url('assets/' . $path) . (is_file($f) ? '?v=' . filemtime($f) : '');
}

function upload_url(?string $path): string
{
    return $path ? url('uploads/' . $path) : '';
}

function money($n): string
{
    return '€' . number_format((float)$n, 2, '.', ',');
}

function order_no($id): string
{
    return '#' . (10000 + (int)$id);
}

function slugify(string $s): string
{
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim((string)$s, '-');
    return $s !== '' ? $s : 'item';
}

function redirect(string $path): never
{
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path)));
    exit;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

/* ---------- flash & csrf ---------- */

function flash(string $msg, string $type = 'ok'): void
{
    $_SESSION['flash'][] = [$type, $msg];
}

function flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $t = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        http_response_code(419);
        exit('Sesioni ka skaduar. Kthehu mbrapa dhe provo përsëri.');
    }
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

function post(string $k, string $d = ''): string
{
    $v = $_POST[$k] ?? $d;
    return is_string($v) ? trim($v) : $d;
}

/* ---------- settings ---------- */

function settings_all(bool $refresh = false): array
{
    static $cache = null;
    if ($cache === null || $refresh) {
        require_once ROOT . '/includes/schema.php';
        $cache = default_settings();
        try {
            foreach (rows('SELECT k, v FROM settings') as $r) {
                $cache[$r['k']] = (string)$r['v'];
            }
        } catch (Throwable $e) {
        }
    }
    return $cache;
}

function setting(string $k, string $d = ''): string
{
    $a = settings_all();
    return $a[$k] ?? $d;
}

function set_setting(string $k, string $v): void
{
    if (val('SELECT COUNT(*) FROM settings WHERE k = ?', [$k])) {
        q('UPDATE settings SET v = ? WHERE k = ?', [$v, $k]);
    } else {
        q('INSERT INTO settings (k, v) VALUES (?, ?)', [$k, $v]);
    }
    settings_all(true);
}

/* ---------- admin auth ---------- */

function admin_logged(): bool
{
    return !empty($_SESSION['admin_id']);
}

function require_admin(): void
{
    if (!admin_logged()) {
        redirect('admin/login');
    }
}

/* ---------- products ---------- */

function effective_price(array $p): float
{
    $sale = $p['sale_price'];
    if ($sale !== null && $sale !== '' && (float)$sale > 0 && (float)$sale < (float)$p['price']) {
        return (float)$sale;
    }
    return (float)$p['price'];
}

function on_sale(array $p): bool
{
    return effective_price($p) < (float)$p['price'];
}

/** IN STOCK / LOW STOCK / OUT OF STOCK */
function stock_state(array $p): string
{
    $stock = (int)$p['stock'];
    if ($stock <= 0 || $p['status'] === 'out_of_stock') {
        return 'out';
    }
    if ($stock < (int)($p['low_stock_threshold'] ?: 5)) {
        return 'low';
    }
    return 'in';
}

function stock_label(array $p): string
{
    return ['in' => 'IN STOCK', 'low' => 'LOW STOCK', 'out' => 'OUT OF STOCK'][stock_state($p)];
}

function can_buy(array $p): bool
{
    return $p['status'] === 'published' && (int)$p['stock'] > 0;
}

function split_list(?string $s): array
{
    $out = [];
    foreach (explode(',', (string)$s) as $x) {
        $x = trim($x);
        if ($x !== '') {
            $out[] = $x;
        }
    }
    return $out;
}

function best_seller_sql(): array
{
    $min = max(1, (int)setting('best_seller_min', '5'));
    return ['(p.badge_best = 1 OR p.sold_count >= ' . $min . ')', []];
}

/* ---------- uploads ---------- */

function save_upload(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return null;
    }
    if ($file['size'] > 8 * 1024 * 1024) {
        flash('Fotoja "' . $file['name'] . '" është më e madhe se 8MB.', 'err');
        return null;
    }
    $info = @getimagesize($file['tmp_name']);
    $map = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    if (!$info || !isset($map[$info[2]])) {
        flash('"' . $file['name'] . '" nuk është foto e vlefshme (JPG, PNG, WEBP, GIF).', 'err');
        return null;
    }
    $dir = ROOT . '/uploads/' . date('Y-m');
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $name = date('Y-m') . '/' . bin2hex(random_bytes(10)) . '.' . $map[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], ROOT . '/uploads/' . $name)) {
        flash('Nuk u ruajt fotoja. Kontrollo të drejtat e folderit uploads.', 'err');
        return null;
    }
    return $name;
}

/** Normalizon $_FILES['x'] (multiple) në listë skedarësh. */
function files_list(string $key): array
{
    if (empty($_FILES[$key])) {
        return [];
    }
    $f = $_FILES[$key];
    if (!is_array($f['name'])) {
        return [$f];
    }
    $out = [];
    foreach ($f['name'] as $i => $n) {
        $out[] = ['name' => $n, 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    }
    return $out;
}

function delete_image_file(string $path): void
{
    if (!val('SELECT COUNT(*) FROM product_images WHERE path = ?', [$path])
        && !val("SELECT COUNT(*) FROM settings WHERE v = ?", [$path])
        && !val('SELECT COUNT(*) FROM banners WHERE image = ?', [$path])) {
        $f = ROOT . '/uploads/' . $path;
        if (is_file($f)) {
            @unlink($f);
        }
    }
}

/* ---------- cart ---------- */

function cart_get(): array
{
    return $_SESSION['cart'] ?? [];
}

function cart_count(): int
{
    $n = 0;
    foreach (cart_get() as $it) {
        $n += (int)$it['qty'];
    }
    return $n;
}

/** Kthen artikujt e shportës me të dhëna aktuale nga database. */
function cart_items(): array
{
    $out = [];
    foreach (cart_get() as $key => $it) {
        $p = row('SELECT ' . PRODUCT_SELECT . ' FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = ?', [(int)$it['product_id']]);
        if (!$p || $p['status'] === 'draft') {
            unset($_SESSION['cart'][$key]);
            continue;
        }
        $p['cart_key'] = $key;
        $p['qty'] = min((int)$it['qty'], max(0, (int)$p['stock']));
        $p['size'] = $it['size'];
        $p['color'] = $it['color'];
        $p['unit'] = effective_price($p);
        $p['line'] = $p['unit'] * $p['qty'];
        $out[] = $p;
    }
    return $out;
}

function cart_subtotal(array $items): float
{
    $t = 0.0;
    foreach ($items as $i) {
        $t += $i['line'];
    }
    return round($t, 2);
}

function shipping_for(float $subtotal): float
{
    if ($subtotal <= 0) {
        return 0.0;
    }
    $free = (float)setting('free_shipping_over', '0');
    if ($free > 0 && $subtotal >= $free) {
        return 0.0;
    }
    return (float)setting('shipping_cost', '0');
}

/** Validon kodin e zbritjes. Kthen [discountRow|null, error|null] */
function check_discount(string $code, float $subtotal): array
{
    $code = strtoupper(trim($code));
    if ($code === '') {
        return [null, null];
    }
    $d = row('SELECT * FROM discounts WHERE UPPER(code) = ?', [$code]);
    $today = date('Y-m-d');
    if (!$d || !(int)$d['active']) {
        return [null, 'Kodi i zbritjes nuk është i vlefshëm.'];
    }
    if ($d['start_date'] && $today < $d['start_date']) {
        return [null, 'Ky kod nuk ka filluar ende.'];
    }
    if ($d['end_date'] && $today > $d['end_date']) {
        return [null, 'Ky kod ka skaduar.'];
    }
    if ((int)$d['max_uses'] > 0 && (int)$d['used'] >= (int)$d['max_uses']) {
        return [null, 'Ky kod është shfrytëzuar plotësisht.'];
    }
    if ($subtotal < (float)$d['min_order']) {
        return [null, 'Porosia minimale për këtë kod është ' . money($d['min_order']) . '.'];
    }
    return [$d, null];
}

/* ---------- ui helpers ---------- */

function status_badge(string $s): string
{
    return '<span class="badge st-' . e($s) . '">' . e(strtoupper(str_replace('_', ' ', $s))) . '</span>';
}

function stars(float $r): string
{
    $full = (int)round($r);
    return str_repeat('★', $full) . str_repeat('☆', 5 - $full);
}
