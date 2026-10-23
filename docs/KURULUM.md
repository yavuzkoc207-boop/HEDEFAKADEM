# Tam platform kurulumu

## Gereksinimler

- PHP 7.4+ (8.1+ önerilir), `pdo_mysql` eklentisi
- MySQL 5.7+ / MariaDB 10.3+
- Apache + `mod_rewrite` (cPanel, DirectAdmin, Plesk gibi paylaşımlı hostinglerin çoğunda hazır gelir)
- (İsteğe bağlı) AI konuşma/gramer koçu için ücretsiz bir [Groq](https://console.groq.com) API anahtarı

## Adımlar

1. **Alan adını ayarlayın.** Statik sayfalardaki SEO etiketleri (canonical, sitemap, Open Graph) varsayılan olarak `https://example.com` adresini gösterir:
   ```bash
   node scripts/configure.mjs --domain https://siteniz.com
   # Marka adını da değiştirmek için:
   node scripts/configure.mjs --domain https://siteniz.com --name "Benim Akademim"
   ```
2. **Dosyaları yükleyin.** `public_html/` klasörünün **içeriğini** hostinginizin web köküne yükleyin.
3. **Boş bir veritabanı oluşturun.** Hosting panelinizden yeni bir MySQL veritabanı ve kullanıcı oluşturun.
4. **Kurulum sihirbazını çalıştırın.** `https://siteniz.com/install.php` adresini açın. Veritabanı bilgilerini ve kurucu (yönetici) hesabınızı girin. Kurulum sırasında:
   - tablolar `app/schema.sql` dosyasından oluşturulur,
   - 384 ders `app/install-data.json` dosyasından yüklenir,
   - `app/config.php` (veritabanı bilgileri + rastgele uygulama anahtarı) ve `app/installed.lock` dosyaları otomatik yazılır.
5. **AI koçunu etkinleştirin (isteğe bağlı).** Aşağıdakilerden birini yapın:
   - Sunucuda `GROQ_API_KEY` ortam değişkenini tanımlayın, **veya**
   - `app/ai-config.example.php` dosyasını `app/ai-config.php` olarak kopyalayıp anahtarınızı yazın.

   Anahtar tanımlanmazsa AI koçu yerine temel (kural tabanlı) koç devreye girer. Site çalışmaya devam eder.
6. **Giriş yapın.** `https://siteniz.com/ogrenci-girisi/` adresinden kurucu hesabınızla girip öğrenci ve öğretmen hesaplarını oluşturun.

## Güvenlik notları

- `app/` klasörüne web üzerinden erişim `.htaccess` ile engellenmiştir. Sunucunuz Apache değilse (ör. Nginx) `app/` dizinini erişime kapatın.
- `app/config.php` ve `app/ai-config.php` dosyalarını **asla** Git'e eklemeyin. `.gitignore` bunları zaten dışarıda tutar.
- Kurulumdan sonra `install.php` kilitlenir. İsterseniz dosyayı tamamen silebilirsiniz.

## API uç noktaları

Tüm istekler `/api/<route>` adresine gider (`.htaccess` bunları `api/index.php` dosyasına yönlendirir).

| Route | Yöntem | Açıklama |
|---|---|---|
| `version` | GET | Sürüm bilgisi |
| `auth/login`, `auth/logout`, `auth/password` | POST | Oturum işlemleri |
| `bootstrap` | GET | Giriş yapan kullanıcının profili, müfredatı ve ilerlemesi |
| `profile`, `progress` | POST | Profil ve ders ilerlemesi |
| `coach` | POST | AI konuşma koçu |
| `grammar` | GET/POST | Gramer merkezi ve AI gramer öğretmeni |
| `certificate` | GET | Seviye sertifikası |
| `admin`, `admin/students`, `admin/live-lessons`, `admin/operations`, `admin/reports` | GET/POST/PATCH | Yönetim paneli |

Roller: `founder`, `admin`, `teacher`, `content_editor`, `support`, `student`.
