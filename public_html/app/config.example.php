<?php
declare(strict_types=1);

// Bu dosya install.php tarafından otomatik olarak app/config.php adıyla oluşturulur.
// Elle kurulum yapıyorsanız bu dosyayı app/config.php olarak kopyalayıp doldurun.
// app/config.php asla Git'e eklenmemelidir (.gitignore içinde).
return array (
  'db_host' => 'localhost',
  'db_port' => 3306,
  'db_name' => 'veritabani_adi',
  'db_user' => 'veritabani_kullanicisi',
  'db_password' => 'veritabani_sifresi',
  'app_key' => 'php -r "echo rtrim(strtr(base64_encode(random_bytes(32)), \'+/\', \'-_\'), \'=\');" ile üretin',
  'installed_at' => '2026-01-01T00:00:00+00:00',
  'release' => '2026.08.29.2-standalone',
);
