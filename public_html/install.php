<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
session_start();
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

$installed = app_is_installed();
$errors = [];
$success = null;
$values = [
    'db_host' => 'localhost',
    'db_port' => '3306',
    'db_name' => '',
    'db_user' => '',
    'founder_name' => '',
    'founder_username' => '',
    'founder_email' => '',
];

if (empty($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
}

if (!$installed && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach (array_keys($values) as $key) $values[$key] = trim((string) ($_POST[$key] ?? $values[$key]));
    $csrf = (string) ($_POST['csrf'] ?? '');
    $dbPassword = (string) ($_POST['db_password'] ?? '');
    $founderPassword = (string) ($_POST['founder_password'] ?? '');
    $confirmation = (string) ($_POST['founder_password_confirmation'] ?? '');

    if (!hash_equals((string) $_SESSION['install_csrf'], $csrf)) $errors[] = 'Kurulum oturumu geçersiz. Sayfayı yenileyip tekrar deneyin.';
    if (!extension_loaded('pdo_mysql')) $errors[] = 'Sunucuda PDO MySQL eklentisi etkin değil. DirectAdmin PHP ayarlarından pdo_mysql etkinleştirilmelidir.';
    if (version_compare(PHP_VERSION, '7.4.0', '<')) $errors[] = 'HedefAkademi için PHP 7.4 veya üzeri gerekir. PHP 8.1+ önerilir.';
    if ($values['db_host'] === '' || $values['db_name'] === '' || $values['db_user'] === '') $errors[] = 'Veritabanı sunucusu, adı ve kullanıcısı gereklidir.';
    if (!ctype_digit($values['db_port']) || (int) $values['db_port'] < 1 || (int) $values['db_port'] > 65535) $errors[] = 'Veritabanı portu geçersiz.';
    $values['founder_username'] = normalize_username($values['founder_username']);
    if (preg_match('/^.{1,80}$/u', $values['founder_name']) !== 1) $errors[] = 'Kurucu adı 1–80 karakter olmalıdır.';
    if (!valid_username($values['founder_username'])) $errors[] = 'Kurucu kullanıcı adı 3–32 karakter olmalı; yalnızca küçük harf, rakam, nokta, alt çizgi ve tire içerebilir.';
    if (!filter_var($values['founder_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Kurucu e-posta adresi geçersiz.';
    if (!valid_password($founderPassword)) $errors[] = 'Kurucu şifresi 8–128 karakter olmalıdır.';
    if (!hash_equals($founderPassword, $confirmation)) $errors[] = 'Kurucu şifreleri eşleşmiyor.';
    if (!is_writable(__DIR__ . '/app')) $errors[] = 'public_html/app klasörü yazılabilir değil. DirectAdmin dosya izinlerini kontrol edin.';

    if (!$errors) {
        try {
            $dsn = 'mysql:host=' . $values['db_host'] . ';port=' . (int) $values['db_port'] . ';dbname=' . $values['db_name'] . ';charset=utf8mb4';
            $pdo = new PDO($dsn, $values['db_user'], $dbPassword, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ]);
            $pdo->exec("SET time_zone = '+00:00'");
            $schema = file_get_contents(__DIR__ . '/app/schema.sql');
            if ($schema === false) throw new RuntimeException('Veritabanı şeması okunamadı.');
            $statements = preg_split('/;\s*(?:\r?\n|$)/', trim($schema)) ?: [];
            foreach ($statements as $statement) {
                if (trim($statement) !== '') $pdo->exec($statement);
            }
            assert_schema_compatible($pdo);

            if (!defined('HEDEF_INSTALLER')) define('HEDEF_INSTALLER', true);
            $data = require __DIR__ . '/app/install-data.php';
            if (!is_array($data) || !is_array($data['curriculum'] ?? null) || !is_array($data['tables'] ?? null)) throw new RuntimeException('Taşıma verisi okunamadı.');
            $counts = import_install_data($pdo, $data, ['name' => $values['founder_name'], 'username' => $values['founder_username'], 'email' => $values['founder_email']], $founderPassword);

            $config = [
                'db_host' => $values['db_host'],
                'db_port' => (int) $values['db_port'],
                'db_name' => $values['db_name'],
                'db_user' => $values['db_user'],
                'db_password' => $dbPassword,
                'app_key' => base64url_encode(random_bytes(32)),
                'installed_at' => gmdate(DATE_ATOM),
                'release' => HEDEF_RELEASE,
            ];
            $configBody = "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";
            $configTemp = __DIR__ . '/app/config.php.tmp';
            if (file_put_contents($configTemp, $configBody, LOCK_EX) === false || !rename($configTemp, __DIR__ . '/app/config.php')) throw new RuntimeException('Yapılandırma dosyası yazılamadı.');
            @chmod(__DIR__ . '/app/config.php', 0600);
            $lock = json_encode(['installed_at' => gmdate(DATE_ATOM), 'release' => HEDEF_RELEASE, 'counts' => $counts], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            if (file_put_contents(__DIR__ . '/app/installed.lock', $lock . "\n", LOCK_EX) === false) throw new RuntimeException('Kurulum kilidi yazılamadı.');
            @chmod(__DIR__ . '/app/installed.lock', 0600);
            session_regenerate_id(true);
            $success = $counts;
            $installed = true;
        } catch (Throwable $error) {
            if (is_file(__DIR__ . '/app/config.php.tmp')) @unlink(__DIR__ . '/app/config.php.tmp');
            $errors[] = 'Kurulum tamamlanamadı: ' . $error->getMessage();
        }
    }
}

function assert_schema_compatible(PDO $pdo): void
{
    $expected = [
        'users' => ['id', 'email', 'username', 'password_hash', 'password_salt', 'name', 'role', 'source_lang', 'target_lang', 'current_level', 'xp', 'hearts', 'streak', 'active', 'must_change_password', 'failed_login_count', 'locked_until', 'last_login_at', 'last_active_date', 'created_at', 'updated_at'],
        'curriculum' => ['id', 'target_lang', 'level', 'level_order', 'lesson_order', 'unit_title', 'unit_summary', 'title', 'summary', 'kind', 'payload', 'active', 'updated_at'],
        'sessions' => ['token_hash', 'user_id', 'expires_at', 'created_at', 'last_used_at'],
    ];
    $databaseName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $columns = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
    foreach ($expected as $table => $requiredColumns) {
        $columns->execute([$databaseName, $table]);
        $actualColumns = array_column($columns->fetchAll(), 'COLUMN_NAME');
        $missingColumns = array_values(array_diff($requiredColumns, $actualColumns));
        if ($missingColumns) {
            throw new RuntimeException(
                "Seçilen veritabanında HedefAkademi ile uyumsuz eski '{$table}' tablosu var. " .
                'Mevcut veriyi silmeyin; DirectAdmin\'de yeni ve boş bir MySQL veritabanı oluşturup kurulumu o veritabanıyla tekrar çalıştırın. ' .
                'Eksik alanlar: ' . implode(', ', $missingColumns)
            );
        }
    }
}

function import_install_data(PDO $pdo, array $data, array $founderAccount, string $founderPassword): array
{
    $curriculum = $data['curriculum'];
    $tables = $data['tables'];
    $counts = ['curriculum' => 0, 'users' => 0, 'lesson_progress' => 0, 'review_items' => 0, 'mastery' => 0, 'mistakes' => 0];
    $pdo->beginTransaction();
    try {
        $lesson = $pdo->prepare("INSERT INTO curriculum (id, target_lang, level, level_order, lesson_order, unit_title, unit_summary, title, summary, kind, payload, active, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE target_lang = VALUES(target_lang), level = VALUES(level), level_order = VALUES(level_order), lesson_order = VALUES(lesson_order), unit_title = VALUES(unit_title), unit_summary = VALUES(unit_summary), title = VALUES(title), summary = VALUES(summary), kind = VALUES(kind), payload = VALUES(payload), active = 1");
        foreach ($curriculum as $row) {
            $lesson->execute([$row['id'], $row['targetLang'], $row['level'], (int) $row['levelOrder'], (int) $row['lessonOrder'], $row['unitTitle'], $row['unitSummary'], $row['title'], $row['summary'], $row['kind'], $row['payload']]);
            $counts['curriculum']++;
        }

        $userStatement = $pdo->prepare("INSERT INTO users (id, email, username, password_hash, password_salt, name, role, source_lang, target_lang, current_level, xp, hearts, streak, active, must_change_password, failed_login_count, locked_until, last_login_at, last_active_date, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE email = VALUES(email), username = VALUES(username), password_hash = VALUES(password_hash), password_salt = VALUES(password_salt), name = VALUES(name), role = VALUES(role), source_lang = VALUES(source_lang), target_lang = VALUES(target_lang), current_level = VALUES(current_level), xp = VALUES(xp), hearts = VALUES(hearts), streak = VALUES(streak), active = VALUES(active), must_change_password = VALUES(must_change_password), failed_login_count = VALUES(failed_login_count), locked_until = VALUES(locked_until), last_login_at = VALUES(last_login_at), last_active_date = VALUES(last_active_date), updated_at = VALUES(updated_at)");
        foreach (($tables['users'] ?? []) as $row) {
            $active = empty($row['username']) ? 0 : (int) ($row['active'] ?? 1);
            $userStatement->execute([$row['id'], $row['email'], $row['username'], $row['password_hash'], $row['password_salt'], $row['name'], $row['role'], $row['source_lang'], $row['target_lang'], $row['current_level'], (int) $row['xp'], (int) $row['hearts'], (int) $row['streak'], $active, (int) $row['must_change_password'], (int) $row['failed_login_count'], $row['locked_until'], $row['last_login_at'], $row['last_active_date'], $row['created_at'], $row['updated_at']]);
            $counts['users']++;
        }

        $credentials = hash_password_value($founderPassword);
        $founder = $pdo->prepare("INSERT INTO users (id, email, username, password_hash, password_salt, name, role, source_lang, target_lang, current_level, active, must_change_password, failed_login_count, locked_until) VALUES ('founder', ?, ?, ?, ?, ?, 'founder', 'tr', 'en', 'A1', 1, 0, 0, NULL) ON DUPLICATE KEY UPDATE email = VALUES(email), username = VALUES(username), password_hash = VALUES(password_hash), password_salt = VALUES(password_salt), name = VALUES(name), role = 'founder', active = 1, must_change_password = 0, failed_login_count = 0, locked_until = NULL");
        $founder->execute([$founderAccount['email'], $founderAccount['username'], $credentials['hash'], $credentials['salt'], $founderAccount['name']]);
        $counts['users'] = 1;

        $progress = $pdo->prepare("INSERT INTO lesson_progress (user_id, lesson_id, status, score, attempts, completed_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status), score = GREATEST(score, VALUES(score)), attempts = GREATEST(attempts, VALUES(attempts)), completed_at = VALUES(completed_at), updated_at = VALUES(updated_at)");
        foreach (($tables['lesson_progress'] ?? []) as $row) { $progress->execute([$row['user_id'], $row['lesson_id'], $row['status'], (int) $row['score'], (int) $row['attempts'], $row['completed_at'], $row['updated_at']]); $counts['lesson_progress']++; }

        $review = $pdo->prepare("INSERT INTO review_items (user_id, lesson_id, exercise_key, prompt, expected, mistakes, streak, next_review_at, last_reviewed_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE prompt = VALUES(prompt), expected = VALUES(expected), mistakes = VALUES(mistakes), streak = VALUES(streak), next_review_at = VALUES(next_review_at), last_reviewed_at = VALUES(last_reviewed_at), updated_at = VALUES(updated_at)");
        $mastery = $pdo->prepare("INSERT INTO user_mastery (user_id, lesson_id, mastery, correct_count, wrong_count, next_review_at, last_review_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE mastery = GREATEST(mastery, VALUES(mastery)), correct_count = GREATEST(correct_count, VALUES(correct_count)), wrong_count = GREATEST(wrong_count, VALUES(wrong_count)), next_review_at = LEAST(COALESCE(next_review_at, VALUES(next_review_at)), VALUES(next_review_at)), updated_at = GREATEST(updated_at, VALUES(updated_at))");
        $mistake = $pdo->prepare("INSERT INTO user_mistakes (id, user_id, lesson_id, skill, prompt, expected, error_count, resolved_at, last_seen_at, created_at) VALUES (?, ?, ?, 'meaning', ?, ?, ?, NULL, ?, ?) ON DUPLICATE KEY UPDATE prompt = VALUES(prompt), expected = VALUES(expected), error_count = GREATEST(error_count, VALUES(error_count)), resolved_at = NULL, last_seen_at = GREATEST(last_seen_at, VALUES(last_seen_at))");
        foreach (($tables['review_items'] ?? []) as $row) {
            $review->execute([$row['user_id'], $row['lesson_id'], $row['exercise_key'], $row['prompt'], $row['expected'], (int) $row['mistakes'], (int) $row['streak'], $row['next_review_at'], $row['last_reviewed_at'], $row['created_at'], $row['updated_at']]);
            $masteryScore = max(20, min(90, 75 - (int) $row['mistakes'] * 10 + (int) $row['streak'] * 5));
            $mastery->execute([$row['user_id'], $row['lesson_id'], $masteryScore, (int) $row['streak'], (int) $row['mistakes'], $row['next_review_at'], $row['last_reviewed_at'] ?: $row['updated_at'], $row['updated_at']]);
            $mistakeId = 'mistake-' . hash('sha256', $row['user_id'] . '|' . $row['lesson_id'] . '|meaning|' . $row['expected']);
            $mistake->execute([$mistakeId, $row['user_id'], $row['lesson_id'], $row['prompt'], $row['expected'], max(1, (int) $row['mistakes']), $row['updated_at'], $row['created_at']]);
            $counts['review_items']++; $counts['mastery']++; $counts['mistakes']++;
        }

        $migration = $pdo->prepare('INSERT INTO app_migrations (`key`, applied_at) VALUES (?, ?) ON DUPLICATE KEY UPDATE applied_at = VALUES(applied_at)');
        foreach (($tables['app_migrations'] ?? []) as $row) $migration->execute([$row['key'], $row['applied_at']]);
        $migration->execute(['standalone-php-mysql-fresh-2026-08-30-2', gmdate('Y-m-d H:i:s')]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    return $counts;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>HedefAkademi Kurulumu</title>
  <style>
    :root{color-scheme:light;--ink:#17364a;--muted:#6e818d;--paper:#f6f3e9;--surface:#fffef9;--line:#dfe5df;--teal:#159b89;--teal-dark:#0b7468;--coral:#ff745a;--coral-pale:#fff0e9}*{box-sizing:border-box}body{margin:0;background:var(--paper);color:var(--ink);font-family:"Segoe UI",Arial,sans-serif}.wrap{width:min(920px,calc(100% - 28px));margin:35px auto 70px}.brand{display:flex;align-items:center;gap:12px;margin-bottom:22px}.mark{width:48px;height:48px;border:2px solid var(--ink);border-bottom-width:6px;border-radius:16px 16px 16px 6px;background:var(--coral);color:#fff;display:grid;place-items:center;font-size:23px;font-weight:900;transform:rotate(-3deg)}.brand strong,.brand small{display:block}.brand strong{font-size:22px}.brand small{margin-top:2px;color:var(--muted);font-size:10px;font-weight:700;letter-spacing:1px}.card{padding:clamp(22px,5vw,42px);border:2px solid var(--ink);border-bottom-width:8px;border-radius:28px;background:var(--surface);box-shadow:0 22px 50px rgba(23,54,74,.1)}.eyebrow{color:var(--teal-dark);font-size:10px;font-weight:900;letter-spacing:1.3px}.card h1{margin:8px 0 9px;font-size:clamp(28px,5vw,44px);line-height:1}.lead{margin:0 0 25px;color:var(--muted);line-height:1.6}.notice,.errors,.success{margin:16px 0;padding:15px 17px;border-radius:14px;font-size:13px;line-height:1.55}.notice{border:1px solid #a5d9d0;background:#e7f7f3}.errors{border:1px solid #efb3a5;background:var(--coral-pale);color:#a43f30}.errors strong{display:block;margin-bottom:5px}.errors ul{margin:0;padding-left:20px}.grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px}.wide{grid-column:1/-1}label{display:grid;gap:7px;color:var(--muted);font-size:11px;font-weight:800}input{width:100%;min-height:48px;padding:10px 13px;border:2px solid var(--line);border-bottom-width:4px;border-radius:12px;background:#fff;color:var(--ink);font:inherit;font-weight:700;outline:none}input:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(21,155,137,.12)}button,.button{display:inline-grid;place-items:center;min-height:50px;margin-top:20px;padding:0 20px;border:0;border-bottom:5px solid var(--teal-dark);border-radius:13px;background:var(--teal);color:#fff;text-decoration:none;font-size:13px;font-weight:900;cursor:pointer}.summary{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:22px 0}.summary div{padding:15px;border-radius:14px;background:#eef8f5;text-align:center}.summary strong,.summary small{display:block}.summary strong{font-size:24px}.summary small{margin-top:4px;color:var(--muted);font-size:9px}.ok{font-size:54px;color:var(--teal)}code{padding:2px 5px;border-radius:5px;background:#edf1ee}@media(max-width:650px){.wrap{margin-top:18px}.grid,.summary{grid-template-columns:1fr}.wide{grid-column:auto}.card{padding:23px 18px;border-radius:22px}.summary div{text-align:left}}
  </style>
</head>
<body><main class="wrap">
  <div class="brand"><span class="mark">H</span><div><strong>HedefAkademi</strong><small>BAĞIMSIZ HOSTING KURULUMU</small></div></div>
  <section class="card">
  <?php if ($installed): ?>
    <div class="ok">✓</div><span class="eyebrow">KURULUM TAMAMLANDI</span><h1>HedefAkademi kendi hostinginizde hazır.</h1>
    <p class="lead">ChatGPT ile giriş kaldırıldı. Girişler artık bu sunucudaki kullanıcı hesapları ve MySQL veritabanı üzerinden yapılır. Kurulum ekranı güvenlik için kilitlendi.</p>
    <?php if (is_array($success)): ?><div class="summary"><div><strong><?= (int) $success['users'] ?></strong><small>KULLANICI</small></div><div><strong><?= (int) $success['lesson_progress'] ?></strong><small>İLERLEME</small></div><div><strong><?= (int) $success['curriculum'] ?></strong><small>DERS İÇERİĞİ</small></div><div><strong><?= (int) $success['review_items'] ?></strong><small>TEKRAR KAYDI</small></div></div><?php endif; ?>
    <div class="success">Kurucu hesabınız oluşturuldu. Bir sonraki adımda siteyi açıp az önce belirlediğiniz şifreyle giriş yapın.</div>
    <a class="button" href="/">Siteyi aç →</a>
  <?php else: ?>
    <span class="eyebrow">TEK ADIMLIK KURULUM</span><h1>Kendi MySQL veritabanınıza bağlayın.</h1>
    <p class="lead">DirectAdmin’de oluşturduğunuz yeni ve boş veritabanının bilgilerini girin. 384 ders ve yalnızca kurucu hesabı oluşturulacaktır; eski kullanıcı ve ilerleme kayıtları aktarılmaz.</p>
    <div class="notice"><strong>Önemli:</strong> DirectAdmin veritabanı adı ve kullanıcısı çoğunlukla hesap adınızla başlar (ör. <code>hesapadi_hedef</code>). Bu bilgileri “Veri tabanları” bölümünden aynen kopyalayın.</div>
    <?php if ($errors): ?><div class="errors" role="alert"><strong>Düzeltmeniz gerekenler:</strong><ul><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= h((string) $_SESSION['install_csrf']) ?>">
      <div class="grid">
        <label>Veritabanı sunucusu<input name="db_host" required value="<?= h($values['db_host']) ?>" placeholder="localhost"></label>
        <label>Port<input name="db_port" inputmode="numeric" required value="<?= h($values['db_port']) ?>"></label>
        <label>Veritabanı adı<input name="db_name" required value="<?= h($values['db_name']) ?>" placeholder="hesapadi_hedef"></label>
        <label>Veritabanı kullanıcısı<input name="db_user" required value="<?= h($values['db_user']) ?>" placeholder="hesapadi_hedef"></label>
        <label class="wide">Veritabanı şifresi<input type="password" name="db_password" required autocomplete="new-password"></label>
        <label>Kurucu adı<input name="founder_name" required maxlength="80" value="<?= h($values['founder_name']) ?>" placeholder="Ad Soyad"></label>
        <label>Kurucu kullanıcı adı<input name="founder_username" required minlength="3" maxlength="32" pattern="[a-z0-9][a-z0-9._\-]{2,31}" value="<?= h($values['founder_username']) ?>" placeholder="admin"></label>
        <label class="wide">Kurucu e-posta<input type="email" name="founder_email" required value="<?= h($values['founder_email']) ?>" placeholder="ornek@alanadi.com"></label>
        <label>Kurucu şifresi<input type="password" name="founder_password" minlength="8" maxlength="128" required autocomplete="new-password"></label>
        <label>Kurucu şifresi tekrar<input type="password" name="founder_password_confirmation" minlength="8" maxlength="128" required autocomplete="new-password"></label>
      </div>
      <button type="submit">Verileri aktar ve kurulumu tamamla</button>
    </form>
  <?php endif; ?>
  </section>
</main></body></html>
