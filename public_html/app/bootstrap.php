<?php
declare(strict_types=1);

const HEDEF_RELEASE = '2026.08.31.7-grammar-center';
const HEDEF_SESSION_COOKIE = 'hedefakademi_session';
const HEDEF_SESSION_SECONDS = 1209600;
const HEDEF_PASSWORD_ITERATIONS = 100000;

date_default_timezone_set('UTC');

function app_root(): string
{
    return dirname(__DIR__);
}

function app_is_installed(): bool
{
    return is_file(app_root() . '/app/config.php') && is_file(app_root() . '/app/installed.lock');
}

function app_config(): array
{
    static $config = null;
    if (is_array($config)) {
        return $config;
    }
    $path = app_root() . '/app/config.php';
    if (!is_file($path)) {
        throw new RuntimeException('HedefAkademi henüz kurulmadı. /install.php adresini açın.');
    }
    $loaded = require $path;
    if (!is_array($loaded) || empty($loaded['db_name']) || empty($loaded['db_user'])) {
        throw new RuntimeException('Veritabanı yapılandırması geçersiz.');
    }
    $config = $loaded;
    return $config;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $config = app_config();
    $host = (string) ($config['db_host'] ?? 'localhost');
    $port = (int) ($config['db_port'] ?? 3306);
    $name = (string) $config['db_name'];
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    $pdo = new PDO($dsn, (string) $config['db_user'], (string) ($config['db_password'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ]);
    $pdo->exec("SET time_zone = '+00:00'");
    return $pdo;
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function request_json(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        json_response(['error' => 'Geçersiz JSON isteği.'], 400);
    }
    return $payload;
}

function require_method(string $expected): void
{
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== strtoupper($expected)) {
        header('Allow: ' . strtoupper($expected));
        json_response(['error' => 'Bu işlem için geçersiz istek yöntemi.'], 405);
    }
}

function require_same_origin(): void
{
    $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($origin !== '') {
        $originHost = strtolower((string) parse_url($origin, PHP_URL_HOST));
        $requestHost = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '');
        if ($originHost === '' || !hash_equals($requestHost, $originHost)) {
            json_response(['error' => 'Geçersiz istek kaynağı.'], 403);
        }
        return;
    }
    if (strtolower((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? 'same-origin')) === 'cross-site') {
        json_response(['error' => 'Geçersiz istek kaynağı.'], 403);
    }
}

function app_text($value, int $limit): string
{
    if (!is_string($value)) {
        return '';
    }
    $clean = trim($value);
    return function_exists('mb_substr') ? mb_substr($clean, 0, $limit, 'UTF-8') : substr($clean, 0, $limit);
}

function app_nullable_text($value, int $limit): ?string
{
    $text = app_text($value, $limit);
    return $text === '' ? null : $text;
}

function app_datetime($value): ?string
{
    $text = app_text($value, 40);
    if ($text === '') return null;
    try {
        $date = new DateTimeImmutable($text);
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    } catch (Throwable $error) {
        return null;
    }
}

function app_int($value, int $min, int $max, ?int $fallback = null): int
{
    if (!is_numeric($value)) {
        return $fallback ?? $min;
    }
    return max($min, min($max, (int) round((float) $value)));
}

function app_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
}

function base64url_encode(string $bytes): string
{
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}

function base64url_decode(string $value): string
{
    $padded = strtr($value, '-_', '+/');
    $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
    $decoded = base64_decode($padded, true);
    return $decoded === false ? '' : $decoded;
}

function hash_password_value(string $password, ?string $saltValue = null): array
{
    $salt = $saltValue === null ? random_bytes(16) : base64url_decode($saltValue);
    if ($salt === '') {
        throw new RuntimeException('Şifre tuzu geçersiz.');
    }
    $digest = hash_pbkdf2('sha256', $password, $salt, HEDEF_PASSWORD_ITERATIONS, 32, true);
    return ['hash' => base64url_encode($digest), 'salt' => base64url_encode($salt)];
}

function verify_password_value(string $password, string $salt, string $expected): bool
{
    try {
        $actual = hash_password_value($password, $salt)['hash'];
        return hash_equals($expected, $actual);
    } catch (Throwable $error) {
        return false;
    }
}

function normalize_username(string $username): string
{
    return strtolower(trim($username));
}

function valid_username(string $username): bool
{
    return preg_match('/^[a-z0-9][a-z0-9._-]{2,31}$/D', $username) === 1;
}

function valid_password(string $password): bool
{
    return strlen($password) >= 8 && strlen($password) <= 128;
}

function is_secure_request(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function set_session_cookie(string $token, int $maxAge = HEDEF_SESSION_SECONDS): void
{
    setcookie(HEDEF_SESSION_COOKIE, $token, [
        'expires' => $maxAge > 0 ? time() + $maxAge : time() - 3600,
        'path' => '/',
        'secure' => is_secure_request(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function create_session(string $userId): string
{
    $token = base64url_encode(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', time() + HEDEF_SESSION_SECONDS);
    $database = db();
    $database->exec('DELETE FROM sessions WHERE expires_at <= NOW()');
    $statement = $database->prepare('INSERT INTO sessions (token_hash, user_id, expires_at) VALUES (?, ?, ?)');
    $statement->execute([$tokenHash, $userId, $expires]);
    set_session_cookie($token);
    return $token;
}

function destroy_session(): void
{
    $token = (string) ($_COOKIE[HEDEF_SESSION_COOKIE] ?? '');
    if ($token !== '' && app_is_installed()) {
        $statement = db()->prepare('DELETE FROM sessions WHERE token_hash = ?');
        $statement->execute([hash('sha256', $token)]);
    }
    set_session_cookie('', 0);
}

function destroy_user_sessions(string $userId): void
{
    $statement = db()->prepare('DELETE FROM sessions WHERE user_id = ?');
    $statement->execute([$userId]);
}

function current_user(): ?array
{
    $token = (string) ($_COOKIE[HEDEF_SESSION_COOKIE] ?? '');
    if ($token === '') {
        return null;
    }
    $statement = db()->prepare("SELECT u.* FROM sessions s INNER JOIN users u ON u.id = s.user_id
        WHERE s.token_hash = ? AND s.expires_at > NOW() AND u.active = 1 AND u.username IS NOT NULL LIMIT 1");
    $statement->execute([hash('sha256', $token)]);
    $user = $statement->fetch();
    if (!$user) {
        set_session_cookie('', 0);
        return null;
    }
    $touch = db()->prepare('UPDATE sessions SET last_used_at = NOW() WHERE token_hash = ?');
    $touch->execute([hash('sha256', $token)]);
    return $user;
}

function require_user(): array
{
    $user = current_user();
    if (!$user) {
        json_response(['error' => 'Oturum gerekli.'], 401);
    }
    return $user;
}

function role_permissions(string $role): array
{
    $all = ['admin:view', 'students:view', 'students:manage', 'student-notes:manage', 'roles:manage', 'sessions:view', 'sessions:manage', 'sessions:assign', 'tasks:view', 'tasks:manage', 'content:view', 'content:manage', 'reports:view', 'audit:view'];
    if ($role === 'founder') return $all;
    if ($role === 'admin') return array_values(array_filter($all, static fn(string $permission): bool => $permission !== 'roles:manage'));
    if ($role === 'teacher') return ['admin:view', 'students:view', 'student-notes:manage', 'sessions:view', 'sessions:manage', 'tasks:view', 'tasks:manage', 'reports:view'];
    if ($role === 'content_editor') return ['admin:view', 'content:view', 'content:manage'];
    if ($role === 'support') return ['admin:view', 'students:view', 'student-notes:manage', 'sessions:view', 'tasks:view', 'tasks:manage', 'reports:view'];
    return [];
}

function has_permission(array $user, string $permission): bool
{
    return in_array($permission, role_permissions((string) ($user['role'] ?? 'student')), true);
}

function require_permission(string $permission): array
{
    $user = require_user();
    if (!has_permission($user, $permission)) {
        json_response(['error' => 'Bu işlem için yetkiniz yok.'], 403);
    }
    return $user;
}

function can_access_student(array $viewer, string $studentId): bool
{
    if (in_array((string) $viewer['role'], ['founder', 'admin', 'support'], true)) {
        return true;
    }
    if ((string) $viewer['role'] !== 'teacher') {
        return false;
    }
    $statement = db()->prepare("SELECT 1 FROM users u LEFT JOIN student_profiles p ON p.user_id = u.id
        WHERE u.id = ? AND u.role = 'student' AND (p.assigned_teacher_id = ? OR EXISTS (
          SELECT 1 FROM live_lessons l WHERE l.user_id = u.id AND l.teacher_id = ?
        )) LIMIT 1");
    $statement->execute([$studentId, $viewer['id'], $viewer['id']]);
    return (bool) $statement->fetchColumn();
}

function write_audit(array $actor, string $action, string $entityType, string $entityId, string $summary, array $metadata = []): void
{
    $statement = db()->prepare('INSERT INTO admin_audit_logs (id, actor_id, action, entity_type, entity_id, summary, metadata) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $statement->execute([app_uuid(), $actor['id'], $action, $entityType, $entityId, $summary, $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null]);
}

function valid_language($value): bool
{
    return is_string($value) && in_array($value, ['tr', 'en', 'ru', 'kz'], true);
}

function valid_level($value): bool
{
    return is_string($value) && in_array($value, ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'], true);
}

function valid_role($value): bool
{
    return is_string($value) && in_array($value, ['founder', 'admin', 'teacher', 'content_editor', 'support', 'student'], true);
}

function personalize_text(string $value, string $name): string
{
    $safeName = app_text($name, 100);
    return str_replace('{{name}}', $safeName === '' ? 'Öğrenci' : $safeName, $value);
}

function personalize_value($value, string $name)
{
    if (is_string($value)) return personalize_text($value, $name);
    if (is_array($value)) {
        foreach ($value as $key => $item) $value[$key] = personalize_value($item, $name);
    }
    return $value;
}

function learning_snapshot(string $userId, string $targetLang, string $level, string $name): array
{
    $database = db();
    $reviews = $database->prepare("SELECT m.lesson_id, m.mastery, m.next_review_at, c.title, c.kind, c.payload
      FROM user_mastery m JOIN curriculum c ON c.id = m.lesson_id
      WHERE m.user_id = ? AND c.target_lang = ? AND c.level = ? AND c.active = 1
        AND m.next_review_at IS NOT NULL AND m.next_review_at <= NOW()
      ORDER BY m.next_review_at ASC, m.mastery ASC LIMIT 12");
    $reviews->execute([$userId, $targetLang, $level]);
    $dueReviews = [];
    foreach ($reviews->fetchAll() as $row) {
        $payload = json_decode((string) $row['payload'], true);
        $phrase = is_array($payload) ? (string) ($payload['translations'][$targetLang] ?? $row['title']) : (string) $row['title'];
        $dueReviews[] = ['lessonId' => $row['lesson_id'], 'title' => personalize_text((string) $row['title'], $name), 'kind' => $row['kind'], 'mastery' => (int) $row['mastery'], 'dueAt' => $row['next_review_at'], 'phrase' => personalize_text($phrase, $name)];
    }

    $mistakesStatement = $database->prepare("SELECT m.id, m.lesson_id, m.skill, m.prompt, m.expected, m.error_count, m.last_seen_at
      FROM user_mistakes m JOIN curriculum c ON c.id = m.lesson_id
      WHERE m.user_id = ? AND m.resolved_at IS NULL AND c.target_lang = ? AND c.level = ?
      ORDER BY m.last_seen_at DESC LIMIT 20");
    $mistakesStatement->execute([$userId, $targetLang, $level]);
    $mistakes = array_map(static function (array $row) use ($name): array {
        return ['id' => $row['id'], 'lessonId' => $row['lesson_id'], 'skill' => $row['skill'], 'prompt' => personalize_text((string) $row['prompt'], $name), 'expected' => personalize_text((string) $row['expected'], $name), 'errorCount' => (int) $row['error_count'], 'lastSeenAt' => $row['last_seen_at']];
    }, $mistakesStatement->fetchAll());

    $skills = $database->prepare("SELECT a.skill, COUNT(*) AS total, SUM(CASE WHEN a.correct = 1 THEN 1 ELSE 0 END) AS correct
      FROM exercise_attempts a JOIN curriculum c ON c.id = a.lesson_id
      WHERE a.user_id = ? AND c.target_lang = ? AND c.level = ? GROUP BY a.skill ORDER BY a.skill");
    $skills->execute([$userId, $targetLang, $level]);
    $skillStats = array_map(static function (array $row): array {
        $total = (int) $row['total'];
        $correct = (int) $row['correct'];
        return ['skill' => $row['skill'], 'total' => $total, 'correct' => $correct, 'percent' => $total ? (int) round($correct / $total * 100) : 0];
    }, $skills->fetchAll());

    $mastery = $database->prepare("SELECT COALESCE(ROUND(AVG(m.mastery)), 0) FROM user_mastery m JOIN curriculum c ON c.id = m.lesson_id WHERE m.user_id = ? AND c.target_lang = ? AND c.level = ?");
    $mastery->execute([$userId, $targetLang, $level]);
    $average = (int) $mastery->fetchColumn();
    $reviewCount = min(3, count($dueReviews));
    $mistakeBoost = count($mistakes) ? 1 : 0;
    return ['dueReviews' => $dueReviews, 'mistakes' => $mistakes, 'mistakeCount' => count($mistakes), 'skillStats' => $skillStats, 'masteryAverage' => $average, 'dailyPlan' => ['newLessons' => 1, 'reviews' => $reviewCount + $mistakeBoost, 'listening' => 1, 'speaking' => 1, 'minutes' => 8 + $reviewCount * 2 + $mistakeBoost * 2]];
}

function certificate_statuses(string $userId, string $targetLang): array
{
    $statement = db()->prepare("SELECT c.level, c.level_order, COUNT(*) AS total, SUM(CASE WHEN p.status = 'completed' THEN 1 ELSE 0 END) AS completed
      FROM curriculum c LEFT JOIN lesson_progress p ON p.lesson_id = c.id AND p.user_id = ?
      WHERE c.target_lang = ? AND c.active = 1 GROUP BY c.level, c.level_order ORDER BY c.level_order");
    $statement->execute([$userId, $targetLang]);
    return array_map(static function (array $row): array {
        $total = (int) $row['total']; $completed = (int) $row['completed'];
        return ['level' => $row['level'], 'eligible' => $total > 0 && $completed === $total, 'completed' => $completed, 'total' => $total];
    }, $statement->fetchAll());
}

function csv_cell($value): string
{
    $text = $value === null ? '' : (string) $value;
    if (preg_match('/^[=+\-@]/', $text)) $text = "'" . $text;
    return '"' . str_replace('"', '""', $text) . '"';
}

function csv_response(string $filename, array $rows): void
{
    $headers = $rows ? array_keys($rows[0]) : ['Kayıt'];
    $lines = [implode(';', array_map('csv_cell', $headers))];
    foreach ($rows as $row) {
        $values = [];
        foreach ($headers as $header) $values[] = csv_cell($row[$header] ?? null);
        $lines[] = implode(';', $values);
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
    header('Cache-Control: no-store');
    echo "\xEF\xBB\xBF" . implode("\r\n", $lines);
    exit;
}
