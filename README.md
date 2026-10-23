<p align="center">
  <img src="public_html/baris.webp" alt="HedefAkademi maskotu Barış" width="140">
</p>

<h1 align="center">HedefAkademi</h1>

<p align="center">
  İngilizce, Türkçe, Rusça ve Kazakça için A1–C2 seviyelerinde açık kaynak dil öğrenme platformu.<br>
  Kendi sitenize tek satırla gömün, kendi hostinginize kurun ya da içeriği kendi uygulamanızda kullanın.
</p>

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/lisans-MIT-green" alt="MIT lisansı"></a>
  <img src="https://img.shields.io/badge/PHP-7.4%2B-777bb4" alt="PHP 7.4+">
  <img src="https://img.shields.io/badge/diller-4-blue" alt="4 dil">
  <img src="https://img.shields.io/badge/ders-384-orange" alt="384 ders">
</p>

---

## Özellikler

- **384 derslik müfredat:** 4 dil × 6 CEFR seviyesi (A1–C2) × 16 ders, kontrol noktaları ve seviye sonu görevleri
- **Seviye testleri:** Her dil için 12 soruluk ücretsiz ön değerlendirme
- **Kişisel çalışma planı:** Süre ve güne göre haftalık plan oluşturucu
- **AI konuşma ve gramer koçu:** Groq üzerinden çalışır. Anahtar yoksa kural tabanlı koça geçer
- **Öğrenci paneli:** İlerleme, XP, seri, aralıklı tekrar, sertifika
- **Yönetim paneli:** Öğrenci/öğretmen yönetimi, canlı ders planlama, görevler, raporlar
- **SEO hazır içerik:** 28 dil/seviye sayfası, 28 rehber yazısı, sitemap ve RSS
- **Gömülebilir widget:** Herhangi bir siteye tek `<script>` ile eklenir

## Hızlı başlangıç: sitenize gömün

```html
<div data-hedef="seviye-testi" data-dil="ingilizce"></div>
<div data-hedef="calisma-plani"></div>

<script src="https://cdn.jsdelivr.net/gh/yavuzkoc207-boop/HEDEFAKADEM@main/embed/hedef-widget.js" defer></script>
```

Tema, renk, kendi sorularınız, sonuç olayları ve React kullanımı için → **[docs/ENTEGRASYON.md](docs/ENTEGRASYON.md)**

Yerelde denemek için:

```bash
npx serve .
# http://localhost:3000/embed/demo.html
```

## Tam platformu kurun

PHP + MySQL olan herhangi bir paylaşımlı hostingde (cPanel, DirectAdmin, Plesk) çalışır:

```bash
node scripts/configure.mjs --domain https://siteniz.com   # SEO etiketlerini alan adınıza ayarlar
```

Ardından `public_html/` klasörünün içeriğini sunucuya yükleyip `https://siteniz.com/install.php` adresini açın. Ayrıntılar için → **[docs/KURULUM.md](docs/KURULUM.md)**

## Proje yapısı

```
HEDEFAKADEM/
├── public_html/             # Sunucuya yüklenecek site
│   ├── api/index.php        # REST API yönlendiricisi
│   ├── app/                 # PHP çekirdeği (web erişimine kapalı)
│   │   ├── schema.sql       # Veritabanı şeması
│   │   ├── install-data.json# 384 derslik müfredat
│   │   ├── *.example.php    # Yapılandırma şablonları
│   │   └── ...
│   ├── assets/              # Derlenmiş JS/CSS ve görseller
│   ├── araclar/             # Seviye testleri ve çalışma planı sayfaları
│   ├── dil/                 # Dil ve seviye sayfaları
│   ├── blog/                # Rehber yazıları
│   └── install.php          # Kurulum sihirbazı
├── embed/                   # Gömülebilir widget + demo
├── content/                 # Düzenlenebilir içerik (seviye testi soruları)
├── docs/                    # Kurulum, entegrasyon, müfredat, görsel lisansları
└── scripts/configure.mjs    # Alan adı / marka ayarlayıcı
```

## Katkıda bulunun

Yeni dil, yeni soru, hata düzeltmesi ya da çeviri: her katkı memnuniyetle karşılanır. Başlamak için → **[CONTRIBUTING.md](CONTRIBUTING.md)**

## Lisans

Kod ve içerik [MIT lisansı](LICENSE) ile sunulur. Wikimedia Commons'tan yüklenen görsellerin kendi lisansları vardır → [docs/GORSEL-KAYNAKLARI.md](docs/GORSEL-KAYNAKLARI.md)
