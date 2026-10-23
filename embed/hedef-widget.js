/*!
 * HedefAkademi Widget — seviye testi ve çalışma planı aracını herhangi bir sayfaya gömün.
 * Kullanım:
 *   <div data-hedef="seviye-testi" data-dil="ingilizce"></div>
 *   <div data-hedef="calisma-plani"></div>
 *   <script src="https://cdn.jsdelivr.net/gh/yavuzkoc207-boop/HEDEFAKADEM@main/embed/hedef-widget.js" defer></script>
 * Seçenekler (data-* öznitelikleri veya HedefWidget.mount(el, {...})):
 *   dil        ingilizce | turkce | rusca | kazakca (seviye testi için)
 *   tema       acik | koyu | otomatik (varsayılan: otomatik)
 *   renk       ana renk, ör. #159b89
 *   sorular    özel soru dosyası URL'si (content/seviye-testleri.json biçiminde)
 *   rotaUrl    sonuç bağlantısı şablonu, ör. https://site.com/dil/{dil}/{seviye}/
 * Lisans: MIT
 */
(function () {
  'use strict';

  var script = document.currentScript;
  var BASE = script && script.src ? new URL('../content/', script.src).href : '';
  var LEVELS = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];
  var questionCache = {};

  var CSS = [
    ':host{all:initial;display:block;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;',
    '--h-accent:#159b89;--h-bg:#ffffff;--h-surface:#f1f8fe;--h-text:#17354f;--h-muted:#687e91;--h-border:#dce8f1}',
    ':host([data-theme="koyu"]){--h-bg:#13202b;--h-surface:#1b2c3a;--h-text:#e7f0f6;--h-muted:#9db1c1;--h-border:#2a3f50}',
    '@media (prefers-color-scheme:dark){:host([data-theme="otomatik"]){--h-bg:#13202b;--h-surface:#1b2c3a;--h-text:#e7f0f6;--h-muted:#9db1c1;--h-border:#2a3f50}}',
    '.shell{box-sizing:border-box;max-width:850px;margin:0 auto;padding:24px;border:1px solid var(--h-border);border-radius:24px;background:var(--h-bg);color:var(--h-text)}',
    '.bar{height:8px;border-radius:999px;background:var(--h-surface);overflow:hidden}.bar i{display:block;height:100%;width:0;background:var(--h-accent);transition:width .25s}',
    'h2{margin:22px 0 14px;font-size:20px;line-height:1.35}.opts{display:grid;gap:10px}',
    'button{font:inherit;cursor:pointer}',
    '.opt{width:100%;padding:14px 16px;border:1px solid var(--h-border);border-radius:14px;background:var(--h-bg);color:var(--h-text);text-align:left;font-weight:600}',
    '.opt:hover{border-color:var(--h-accent)}.opt.sel{border-color:var(--h-accent);box-shadow:inset 0 0 0 1px var(--h-accent);background:var(--h-surface)}',
    '.actions{display:flex;justify-content:space-between;gap:12px;margin-top:20px}',
    '.btn{padding:12px 20px;border:0;border-radius:999px;background:var(--h-accent);color:#fff;font-weight:700;text-decoration:none;display:inline-flex}',
    '.btn.sec{background:var(--h-surface);color:var(--h-text);box-shadow:inset 0 0 0 1px var(--h-border)}.btn:disabled{opacity:.45;cursor:default}',
    '.result{padding:24px;border-radius:20px;background:var(--h-surface)}.result span{color:var(--h-muted);font-size:14px}',
    '.result strong{display:block;font-size:40px;margin:6px 0;color:var(--h-accent)}',
    '.note,.err{margin-top:16px;color:var(--h-muted);font-size:13px;line-height:1.6}.err{color:#c0392b}',
    '.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;align-items:end}',
    'label{display:grid;gap:6px;font-size:13px;color:var(--h-muted)}',
    'select{font:inherit;padding:10px;border:1px solid var(--h-border);border-radius:12px;background:var(--h-bg);color:var(--h-text)}',
    '.day{margin-top:10px;padding:12px 14px;border:1px solid var(--h-border);border-radius:14px;font-size:14px;line-height:1.5}.day b{display:block}'
  ].join('');

  function el(tag, attrs, text) {
    var node = document.createElement(tag);
    if (attrs) Object.keys(attrs).forEach(function (k) { node.setAttribute(k, attrs[k]); });
    if (text != null) node.textContent = text;
    return node;
  }

  function loadQuestions(url) {
    if (!questionCache[url]) {
      questionCache[url] = fetch(url).then(function (r) {
        if (!r.ok) throw new Error('Sorular yüklenemedi (' + r.status + ')');
        return r.json();
      });
    }
    return questionCache[url];
  }

  // Orijinal araçla aynı eşikler: 12 soruda 0–2 A1, 3–4 A2, 5–6 B1, 7–8 B2, 9–10 C1, 11–12 C2.
  function levelFromScore(score, total) {
    var scaled = total ? Math.round(score * 12 / total) : 0;
    return scaled <= 2 ? 'A1' : scaled <= 4 ? 'A2' : scaled <= 6 ? 'B1' : scaled <= 8 ? 'B2' : scaled <= 10 ? 'C1' : 'C2';
  }

  function levelTest(root, opts) {
    var url = opts.sorular || BASE + 'seviye-testleri.json';
    root.appendChild(el('p', { class: 'note' }, 'Yükleniyor…'));
    loadQuestions(url).then(function (data) {
      var test = data[opts.dil];
      if (!test) throw new Error('"' + opts.dil + '" için test bulunamadı.');
      var qs = test.sorular, i = 0, answers = [];
      root.textContent = '';
      var bar = el('div', { class: 'bar' }), fill = el('i');
      bar.appendChild(fill);
      var q = el('h2'), list = el('div', { class: 'opts' }), msg = el('p', { class: 'err', role: 'alert' });
      var actions = el('div', { class: 'actions' });
      var back = el('button', { class: 'btn sec', type: 'button' }, 'Geri');
      var next = el('button', { class: 'btn', type: 'button' }, 'Devam');
      actions.append(back, next);
      root.append(bar, q, list, msg, actions,
        el('p', { class: 'note' }, 'Bu test resmi CEFR sınavı veya sertifikasyon değildir; kısa bir ön değerlendirmedir.'));

      function render() {
        var item = qs[i];
        msg.textContent = '';
        q.textContent = (i + 1) + '. ' + item.soru;
        list.textContent = '';
        item.secenekler.forEach(function (text, n) {
          var b = el('button', { type: 'button', class: 'opt' + (answers[i] === n ? ' sel' : '') }, text);
          b.onclick = function () { answers[i] = n; render(); };
          list.appendChild(b);
        });
        fill.style.width = (i / qs.length) * 100 + '%';
        back.disabled = i === 0;
        next.textContent = i === qs.length - 1 ? 'Sonucu Gör' : 'Devam';
      }

      function result() {
        var score = qs.reduce(function (s, x, n) { return s + (answers[n] === x.dogru ? 1 : 0); }, 0);
        var level = levelFromScore(score, qs.length);
        root.textContent = '';
        var box = el('div', { class: 'result' });
        box.append(el('span', null, test.ad + ' · tahmini seviye'), el('strong', null, level),
          el('p', null, score + '/' + qs.length + ' doğru cevap.'));
        if (opts.rotaUrl) {
          var href = opts.rotaUrl.replace('{dil}', encodeURIComponent(opts.dil)).replace('{seviye}', level.toLowerCase());
          box.appendChild(el('a', { class: 'btn', href: href }, test.ad + ' ' + level + ' rotasını aç'));
        }
        var again = el('button', { class: 'btn sec', type: 'button', style: 'margin-left:8px' }, 'Tekrar çöz');
        again.onclick = function () { root.textContent = ''; levelTest(root, opts); };
        box.appendChild(again);
        root.appendChild(box);
        root.dispatchEvent(new CustomEvent('hedef:sonuc', { bubbles: true, composed: true, detail: { dil: opts.dil, seviye: level, dogru: score, toplam: qs.length } }));
      }

      next.onclick = function () {
        if (answers[i] == null) { msg.textContent = 'Devam etmek için bir cevap seç.'; return; }
        if (++i >= qs.length) result(); else render();
      };
      back.onclick = function () { if (i > 0) { i--; render(); } };
      render();
    }).catch(function (e) {
      root.textContent = '';
      root.appendChild(el('p', { class: 'err' }, e.message));
    });
  }

  function studyPlan(root) {
    function select(label, values, selected) {
      var wrap = el('label', null, label), s = el('select');
      values.forEach(function (v) {
        var o = el('option', { value: v[0] }, v[1]);
        if (v[0] === selected) o.selected = true;
        s.appendChild(o);
      });
      wrap.appendChild(s);
      return { wrap: wrap, input: s };
    }
    var lang = select('Dil', [['İngilizce', 'İngilizce'], ['Türkçe', 'Türkçe'], ['Rusça', 'Rusça'], ['Kazakça', 'Kazakça']]);
    var level = select('Seviye', LEVELS.map(function (l) { return [l, l]; }));
    var min = select('Günlük süre', [['15', '15 dakika'], ['25', '25 dakika'], ['40', '40 dakika'], ['60', '60 dakika']], '40');
    var days = select('Haftada gün', [['3', '3 gün'], ['4', '4 gün'], ['5', '5 gün'], ['6', '6 gün'], ['7', '7 gün']], '5');
    var go = el('button', { class: 'btn', type: 'button' }, 'Planı Oluştur');
    var grid = el('div', { class: 'grid' });
    grid.append(lang.wrap, level.wrap, min.wrap, days.wrap, go);
    var out = el('div');
    root.append(grid, out);

    go.onclick = function () {
      var m = +min.input.value, d = +days.input.value;
      var blocks = m <= 20 ? [['Tekrar', 5], ['Yeni konu', 7], ['Sesli üretim', 8]]
        : m <= 40 ? [['Tekrar', 8], ['Yeni konu', 12], ['Dinleme', 8], ['Konuşma/yazma', 12]]
        : [['Tekrar', 10], ['Yeni konu', 18], ['Dinleme', 12], ['Konuşma', 15], ['Hata analizi', 5]];
      var total = blocks.reduce(function (a, b) { return a + b[1]; }, 0);
      out.textContent = '';
      var box = el('div', { class: 'result', style: 'margin-top:18px' });
      box.append(el('span', null, lang.input.value + ' · ' + level.input.value), el('strong', null, d + ' günlük plan'),
        el('p', null, 'Günde yaklaşık ' + m + ' dakika. Her oturumda süreyi konu sayısından önce tutarlı uygulamaya ayır.'));
      out.appendChild(box);
      for (var n = 1; n <= d; n++) {
        var focus = n % 3 === 1 ? 'Yeni konu + üretim' : n % 3 === 2 ? 'Dinleme + tekrar' : 'Konuşma + kontrol';
        var day = el('div', { class: 'day' });
        day.append(el('b', null, 'Gün ' + n + ' · ' + focus),
          document.createTextNode(blocks.map(function (x) { return x[0] + ': yaklaşık ' + Math.round(x[1] * m / total) + ' dk'; }).join(' · ')));
        out.appendChild(day);
      }
      out.appendChild(el('p', { class: 'note' }, 'Hafta sonunda bir kayıt veya kısa yazı üret; zorlandığın iki noktayı sonraki haftanın tekrar listesine taşı.'));
    };
  }

  function mount(host, options) {
    if (!host || host.__hedef) return;
    var ds = host.dataset || {};
    var opts = Object.assign({
      arac: ds.hedef, dil: ds.dil || 'ingilizce', tema: ds.tema || 'otomatik',
      renk: ds.renk, sorular: ds.sorular, rotaUrl: ds.rotaUrl
    }, options || {});
    host.__hedef = true;
    host.setAttribute('data-theme', opts.tema);
    var shadow = host.attachShadow({ mode: 'open' });
    var style = el('style', null, CSS);
    var root = el('div', { class: 'shell' });
    if (opts.renk) root.style.setProperty('--h-accent', opts.renk);
    shadow.append(style, root);
    if (opts.arac === 'calisma-plani') studyPlan(root);
    else levelTest(root, opts);
  }

  function mountAll() {
    document.querySelectorAll('[data-hedef]').forEach(function (n) { mount(n); });
  }

  window.HedefWidget = { mount: mount, mountAll: mountAll };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mountAll);
  else mountAll();
})();
