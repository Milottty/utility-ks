<?php
declare(strict_types=1);

define('INSTALLING', true);
define('ROOT', __DIR__);

if (is_file(ROOT . '/config.php')) {
    http_response_code(403);
    echo '<!doctype html><meta charset="utf-8"><body style="background:#000;color:#fff;font-family:system-ui;padding:40px">';
    echo '<h2>UTILITY.KS është instaluar tashmë.</h2><p>Për ta instaluar sërish, fshi skedarin <code>config.php</code> (kujdes: databaza mbetet).</p><p><a style="color:#fff" href="admin/login">Hyr në Admin</a></p>';
    exit;
}

require ROOT . '/includes/schema.php';

function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$errors = [];
$v = [
    'driver' => 'mysql', 'host' => 'localhost', 'name' => '', 'user' => 'root', 'pass' => '',
    'admin_email' => '', 'store_name' => 'UTILITY.KS', 'demo' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($v as $k => $_) {
        $v[$k] = trim((string)($_POST[$k] ?? ''));
    }
    $adminPass = (string)($_POST['admin_pass'] ?? '');

    if (!filter_var($v['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Shkruaj një email të vlefshëm për adminin.';
    }
    if (strlen($adminPass) < 8) {
        $errors[] = 'Fjalëkalimi i adminit duhet të ketë të paktën 8 karaktere.';
    }
    if ($v['driver'] === 'mysql' && $v['name'] === '') {
        $errors[] = 'Shkruaj emrin e databazës MySQL.';
    }
    if (!is_writable(ROOT)) {
        $errors[] = 'Folderi i projektit nuk është i shkrueshëm (nuk mund të krijohet config.php).';
    }

    if (!$errors) {
        try {
            $base = preg_replace('#/install(\.php)?/?$#', '', (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
            $config = [
                'driver' => $v['driver'],
                'host' => $v['host'], 'name' => $v['name'], 'user' => $v['user'], 'pass' => $v['pass'],
                'sqlite' => ROOT . '/data/utility.sqlite',
                'base' => $base,
            ];

            if ($v['driver'] === 'sqlite') {
                if (!is_dir(ROOT . '/data')) {
                    mkdir(ROOT . '/data', 0755, true);
                }
                $pdo = new PDO('sqlite:' . $config['sqlite']);
            } else {
                try {
                    $pdo = new PDO("mysql:host={$v['host']};dbname={$v['name']};charset=utf8mb4", $v['user'], $v['pass']);
                } catch (PDOException $ex) {
                    // provo ta krijosh databazën nëse nuk ekziston (p.sh. XAMPP lokalisht)
                    $pdo = new PDO("mysql:host={$v['host']};charset=utf8mb4", $v['user'], $v['pass']);
                    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $v['name']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                    $pdo = new PDO("mysql:host={$v['host']};dbname={$v['name']};charset=utf8mb4", $v['user'], $v['pass']);
                }
            }
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            foreach (schema_statements($v['driver']) as $sql) {
                $pdo->exec($sql);
            }

            $now = date('Y-m-d H:i:s');
            $pdo->prepare('INSERT INTO admins (email, password_hash, created_at) VALUES (?,?,?)')
                ->execute([strtolower($v['admin_email']), password_hash($adminPass, PASSWORD_DEFAULT), $now]);

            $st = $pdo->prepare('INSERT INTO settings (k, v) VALUES (?, ?)');
            foreach (default_settings() as $k => $val) {
                $st->execute([$k, $k === 'store_name' ? ($v['store_name'] ?: 'UTILITY.KS') : $val]);
            }
            $st = $pdo->prepare('INSERT INTO categories (name, slug, sort) VALUES (?,?,?)');
            foreach (['Boxing', 'Clothing', 'Bags', 'Training', 'Accessories'] as $i => $c) {
                $st->execute([$c, strtolower($c), $i]);
            }

            if ($v['demo'] === '1') {
                $cat = fn($n) => (int)$pdo->query("SELECT id FROM categories WHERE name = '$n'")->fetchColumn();
                $ins = $pdo->prepare('INSERT INTO products (name, description, category_id, brand, sku, price, sale_price, stock, status, badge_new, badge_featured, badge_best, sizes, colors, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $demo = [
                    ['UTILITY Boxing Gloves', 'Doreza boksi me mbushje të dendur.', 'Boxing', 'UTILITY', 'UT-BOX-001', 89.00, null, 3, 1, 1, 0, '10oz,12oz,14oz', 'Black'],
                    ['UTILITY Training Hoodie', 'Hoodie premium për stërvitje.', 'Clothing', 'UTILITY', 'UT-CLO-001', 65.00, 49.00, 25, 1, 0, 1, 'S,M,L,XL', 'Black,Grey'],
                    ['UTILITY Gym Bag', 'Çantë e madhe për gym.', 'Bags', 'UTILITY', 'UT-BAG-001', 55.00, null, 12, 0, 1, 0, '', 'Black'],
                    ['UTILITY Hand Wraps', 'Fasha dore 4.5m.', 'Training', 'UTILITY', 'UT-TRN-001', 15.00, null, 40, 1, 0, 0, '', 'Black,White'],
                ];
                foreach ($demo as $d) {
                    $ins->execute([$d[0], $d[1], $cat($d[2]), $d[3], $d[4], $d[5], $d[6], $d[7], 'published', $d[8], $d[9], $d[10], $d[11], $d[12], $now, $now]);
                }
            }

            foreach (['uploads', 'data'] as $dir) {
                if (!is_dir(ROOT . '/' . $dir)) {
                    mkdir(ROOT . '/' . $dir, 0755, true);
                }
            }

            file_put_contents(ROOT . '/config.php', "<?php\nreturn " . var_export($config, true) . ";\n");
            header('Location: ' . ($base ?: '') . '/admin/login?installed=1');
            exit;
        } catch (Throwable $ex) {
            $errors[] = 'Gabim gjatë instalimit: ' . $ex->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="sq">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instalimi — UTILITY.KS</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#000;color:#fff;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;padding:24px}
.box{max-width:520px;margin:40px auto;background:#111;border:1px solid #2a2a2a;padding:32px}
h1{letter-spacing:.3em;font-size:18px;margin:0 0 4px}p.s{color:#999;margin:0 0 24px;font-size:14px}
label{display:block;font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:#999;margin:16px 0 6px}
input,select{width:100%;padding:12px;background:#000;border:1px solid #2a2a2a;color:#fff;font-size:15px}
input:focus,select:focus{outline:none;border-color:#fff}
h2{font-size:13px;letter-spacing:.2em;margin:28px 0 0;padding-top:20px;border-top:1px solid #2a2a2a}
button{margin-top:28px;width:100%;padding:14px;background:#fff;color:#000;border:0;font-weight:700;letter-spacing:.2em;cursor:pointer}
.err{background:#2a1111;border:1px solid #5a2222;color:#ffb4b4;padding:12px;margin-bottom:16px;font-size:14px}
.hint{font-size:12px;color:#777;margin-top:6px}.chk{display:flex;gap:10px;align-items:center;margin-top:16px;font-size:14px}.chk input{width:auto}
#mysql-fields.hide{display:none}
</style>
</head>
<body>
<form class="box" method="post" autocomplete="off">
  <h1>UTILITY.KS</h1>
  <p class="s">Instalimi i dyqanit — kjo bëhet vetëm një herë.</p>
  <?php foreach ($errors as $er): ?><div class="err"><?= h($er) ?></div><?php endforeach; ?>

  <h2>1. DATABAZA</h2>
  <label>Lloji i databazës</label>
  <select name="driver" id="driver">
    <option value="mysql" <?= $v['driver'] === 'mysql' ? 'selected' : '' ?>>MySQL / MariaDB (rekomandohet)</option>
    <option value="sqlite" <?= $v['driver'] === 'sqlite' ? 'selected' : '' ?>>SQLite (pa konfigurim — vetëm për provë)</option>
  </select>
  <div id="mysql-fields">
    <label>Host</label><input name="host" value="<?= h($v['host']) ?>">
    <label>Emri i databazës</label><input name="name" value="<?= h($v['name']) ?>" placeholder="utility_ks">
    <label>Përdoruesi</label><input name="user" value="<?= h($v['user']) ?>">
    <label>Fjalëkalimi</label><input name="pass" type="password" value="<?= h($v['pass']) ?>">
    <div class="hint">XAMPP: host <b>localhost</b>, përdorues <b>root</b>, fjalëkalim bosh. Hosting: merri nga cPanel → MySQL Databases.</div>
  </div>

  <h2>2. ADMINI</h2>
  <label>Email i adminit</label><input name="admin_email" type="email" value="<?= h($v['admin_email']) ?>" required>
  <label>Fjalëkalimi (min. 8 karaktere)</label><input name="admin_pass" type="password" required>
  <label>Emri i dyqanit</label><input name="store_name" value="<?= h($v['store_name']) ?>">
  <label class="chk"><input type="checkbox" name="demo" value="1" <?= $v['demo'] === '1' ? 'checked' : '' ?>> Shto 4 produkte demo (për provë, fshiji më vonë)</label>

  <button type="submit">INSTALO</button>
</form>
<script>
const d=document.getElementById('driver'),m=document.getElementById('mysql-fields');
function t(){m.classList.toggle('hide',d.value==='sqlite')}d.addEventListener('change',t);t();
</script>
</body>
</html>
