# Kendi sitenize entegrasyon

HedefAkademi'yi kendi sitenizde üç farklı şekilde kullanabilirsiniz:

| Yöntem | Kurulum | Ne sağlar |
|---|---|---|
| **1. Widget** | Tek `<script>` satırı | Seviye testleri ve çalışma planı, mevcut sayfanızın içinde |
| **2. İçerik dosyaları** | Yok | Müfredat ve test sorularını kendi uygulamanızda JSON olarak kullanırsınız |
| **3. Tam platform** | PHP + MySQL hosting | Öğrenci girişi, ders rotaları, AI koç, yönetim paneli |

---

## 1. Widget (en kolay yol)

Herhangi bir HTML sayfasına (WordPress, Wix HTML bloğu, statik site, React vb.) ekleyin:

```html
<div data-hedef="seviye-testi" data-dil="ingilizce"></div>
<div data-hedef="calisma-plani"></div>

<script src="https://cdn.jsdelivr.net/gh/yavuzkoc207-boop/HEDEFAKADEM@main/embed/hedef-widget.js" defer></script>
```

> Üretimde `@main` yerine bir sürüm etiketi kullanın (ör. `@v1.0.0`). Böylece sonraki değişiklikler sitenizi beklenmedik şekilde etkilemez.

### Seçenekler

| Öznitelik | Değerler | Açıklama |
|---|---|---|
| `data-hedef` | `seviye-testi`, `calisma-plani` | Hangi aracın gösterileceği |
| `data-dil` | `ingilizce`, `turkce`, `rusca`, `kazakca` | Seviye testinin dili |
| `data-tema` | `otomatik` (varsayılan), `acik`, `koyu` | Renk teması |
| `data-renk` | ör. `#e91e63` | Butonların ve vurguların rengi |
| `data-sorular` | URL | Kendi soru dosyanız (aşağıya bakın) |
| `data-rota-url` | ör. `https://site.com/dil/{dil}/{seviye}/` | Sonuç ekranında gösterilecek bağlantı |

Widget, Shadow DOM içinde çalışır. Sitenizin CSS'i widget'ı bozmaz, widget'ın stili de sitenizi etkilemez.

### JavaScript ile kullanım

```js
HedefWidget.mount(document.querySelector('#test'), { arac: 'seviye-testi', dil: 'turkce', tema: 'koyu' });

document.addEventListener('hedef:sonuc', (e) => {
  console.log(e.detail); // { dil: 'turkce', seviye: 'B1', dogru: 6, toplam: 12 }
  // ör. sonucu kendi analitiğinize veya CRM'inize gönderin
});
```

### React içinde

```jsx
import { useEffect, useRef } from 'react';

export function SeviyeTesti({ dil = 'ingilizce' }) {
  const ref = useRef(null);
  useEffect(() => { window.HedefWidget?.mount(ref.current, { arac: 'seviye-testi', dil }); }, [dil]);
  return <div ref={ref} />;
}
```

### Kendi sorularınız / yeni dil

`content/seviye-testleri.json` dosyasını örnek alıp kendi dosyanızı oluşturun ve `data-sorular` ile gösterin:

```json
{
  "almanca": {
    "ad": "Almanca",
    "sorular": [
      { "soru": "Ich ___ aus der Türkei.", "secenekler": ["bin", "bist", "ist", "sind"], "dogru": 0 }
    ]
  }
}
```

```html
<div data-hedef="seviye-testi" data-dil="almanca" data-sorular="/sorularim.json"></div>
```

`dogru`, doğru seçeneğin 0'dan başlayan sırasıdır. Seviye, doğru cevap oranına göre A1–C2 arasında hesaplanır.

### Widget'ı kendiniz barındırmak

`embed/hedef-widget.js` ve `content/seviye-testleri.json` dosyalarını sitenize aynı göreli yapıda kopyalayın (`.../embed/` ve `.../content/`). Başka bir yapı kullanacaksanız `data-sorular` ile JSON adresini açıkça verin.

---

## 2. İçerik dosyaları

| Dosya | İçerik |
|---|---|
| `content/seviye-testleri.json` | 4 dil × 12 soruluk seviye testleri |
| `public_html/app/install-data.json` | 4 dil × 6 seviye × 16 ders = 384 dersin tam müfredatı |
| `docs/mufredat/MUFREDAT_A1_C2.md` | Müfredatın okunabilir özeti |
| `docs/mufredat/curriculum-map.json` | Seviye/ünite haritası |

`install-data.json` içindeki her dersin `payload` alanı, ders detaylarını içeren bir JSON metnidir (`JSON.parse` ile açın).

---

## 3. Tam platform

Kurulum için [KURULUM.md](KURULUM.md) dosyasına bakın. Platformu bir alt dizine (`site.com/dil-ogren/`) değil, kendi alan adına veya alt alan adına (`dil.site.com`) kurmanız önerilir. Uygulama kök yolu (`/`) varsayımıyla çalışır.
