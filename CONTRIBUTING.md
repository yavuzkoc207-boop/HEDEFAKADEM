# Katkı rehberi

HedefAkademi'ye katkı vermek istediğiniz için teşekkürler!

## Nasıl katkı verebilirsiniz?

| Katkı türü | Nereye bakmalı | Kod bilgisi gerekir mi? |
|---|---|---|
| Seviye testine soru ekleme/düzeltme | `content/seviye-testleri.json` | Hayır |
| Yeni bir dil için seviye testi | `content/seviye-testleri.json` | Hayır |
| Müfredat ve ders içeriği | `public_html/app/install-data.json`, `docs/mufredat/` | Az |
| Rehber yazısı | `public_html/blog/` | HTML |
| Widget geliştirme | `embed/hedef-widget.js` | JavaScript |
| API / sunucu tarafı | `public_html/app/`, `public_html/api/` | PHP |

## Adımlar

1. Repoyu fork'layın ve yeni bir dal açın: `git checkout -b soru/almanca-a1`
2. Değişikliğinizi yapın.
3. Widget'a dokunduysanız `npx serve .` çalıştırıp `/embed/demo.html` sayfasında deneyin.
4. JSON dosyalarını düzenlediyseniz geçerli olduklarını kontrol edin:
   ```bash
   node -e "JSON.parse(require('fs').readFileSync('content/seviye-testleri.json','utf8'))"
   ```
5. Ne yaptığınızı ve nedenini açıklayan bir Pull Request açın.

## Kurallar

- **Gizli bilgi eklemeyin.** API anahtarı, veritabanı şifresi, kişisel e-posta veya telefon numarası commit etmeyin. `app/config.php` ve `app/ai-config.php` zaten `.gitignore` içinde.
- Soru eklerken her sorunun **tek bir** doğru cevabı olsun. `dogru` alanı 0'dan başlar.
- Test soruları kolaydan zora sıralanmalıdır, çünkü seviye hesaplaması buna dayanır.
- Var olan kod stiline uyun: PHP'de `declare(strict_types=1)`, widget'ta bağımlılık eklemeyin.
- Görsel eklerken lisansını `docs/GORSEL-KAYNAKLARI.md` dosyasına yazın.

## Proje sahibi için: canlı siteden repoya aktarma

Geliştirme canlı site klasöründe (`public_html`) yapılır. Değişiklikleri repoya aktarmak için:

```bash
node scripts/siteden-guncelle.mjs          # varsayılan: ../public_html
git add -A && git commit -m "Değişikliğin özeti" && git push
```

Betik gizli dosyaları (`config.php`, `ai-config.php`, loglar) dışarıda bırakır, kullanılmayan asset dosyalarını almaz, alan adını `example.com` yapar ve müfredatı JSON'a çevirir. Repoda API anahtarı, şifre ya da gerçek alan adı kalırsa **durur ve hiçbir şey commit edilmez**. `install.php` repoya özel sürümüyle korunur.

## Hata bildirme

[Issues](../../issues) sekmesinden "Hata bildirimi" şablonunu kullanın. Lütfen tarayıcı/PHP sürümünü ve hatayı yeniden oluşturma adımlarını ekleyin.

Güvenlik açığı bulduysanız herkese açık issue **açmayın**. GitHub'daki "Security → Report a vulnerability" özelliğiyle özel olarak bildirin.
