<?php
declare(strict_types=1);
if (!defined('HEDEF_INSTALLER')) { http_response_code(404); exit; }
$json = file_get_contents(__DIR__ . '/install-data.json');
if ($json === false) throw new RuntimeException('install-data.json okunamadı.');
return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
