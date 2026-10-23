<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/api-handlers.php';
require dirname(__DIR__) . '/app/grammar-module.php';

$route = trim((string) ($_GET['route'] ?? ''), '/');
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'OPTIONS') {
    http_response_code(204);
    header('Allow: GET, POST, PATCH, OPTIONS');
    exit;
}

try {
    if ($route === 'version' && $method === 'GET') api_version();
    if (!app_is_installed()) json_response(['error' => 'HedefAkademi henüz kurulmadı. /install.php adresini açın.'], 503);

    if ($route === 'auth/login' && $method === 'POST') api_auth_login();
    if ($route === 'auth/logout' && $method === 'POST') api_auth_logout();
    if ($route === 'auth/password' && $method === 'POST') api_auth_password();
    if ($route === 'bootstrap' && $method === 'GET') api_bootstrap();
    if ($route === 'profile' && $method === 'POST') api_profile();
    if ($route === 'progress' && $method === 'POST') api_progress();
    if ($route === 'coach' && $method === 'POST') api_coach();
    if ($route === 'grammar' && $method === 'GET') api_grammar_get();
    if ($route === 'grammar' && $method === 'POST') api_grammar_post();
    if ($route === 'certificate' && $method === 'GET') api_certificate();
    if ($route === 'admin' && $method === 'GET') api_admin_get();
    if ($route === 'admin' && $method === 'POST') api_admin_post();
    if ($route === 'admin' && $method === 'PATCH') api_admin_patch();
    if ($route === 'admin/live-lessons' && $method === 'GET') api_live_lessons_get();
    if ($route === 'admin/live-lessons' && $method === 'POST') api_live_lessons_post();
    if ($route === 'admin/live-lessons' && $method === 'PATCH') api_live_lessons_patch();
    if ($route === 'admin/operations' && $method === 'GET') api_operations_get();
    if ($route === 'admin/operations' && $method === 'POST') api_operations_post();
    if ($route === 'admin/operations' && $method === 'PATCH') api_operations_patch();
    if ($route === 'admin/students' && $method === 'GET') api_students_get();
    if ($route === 'admin/students' && $method === 'POST') api_students_post();
    if ($route === 'admin/students' && $method === 'PATCH') api_students_patch();
    if ($route === 'admin/reports' && $method === 'GET') api_reports_get();

    json_response(['error' => 'API adresi bulunamadı.'], 404);
} catch (PDOException $error) {
    error_log('HedefAkademi database error: ' . $error->getMessage());
    json_response(['error' => 'Veritabanı işlemi tamamlanamadı. Lütfen tekrar deneyin.'], 500);
} catch (Throwable $error) {
    error_log('HedefAkademi error: ' . $error->getMessage());
    json_response(['error' => $error->getMessage() !== '' ? $error->getMessage() : 'İşlem tamamlanamadı.'], 500);
}
