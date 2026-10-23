#!/usr/bin/env node
// Canlı site klasöründeki (public_html) değişiklikleri gizli bilgileri ayıklayarak repoya aktarır.
// Kullanım: node scripts/siteden-guncelle.mjs [site_klasoru]   (varsayılan: ../public_html)
// Ardından: git add -A && git commit -m "..." && git push
import { readdirSync, readFileSync, writeFileSync, statSync, mkdirSync, copyFileSync, rmSync, existsSync } from 'node:fs';
import { join, dirname, extname, relative, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

const REPO = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const DEST = join(REPO, 'public_html');
const SITE = resolve(process.argv[2] || join(REPO, '..', 'public_html'));
const PLACEHOLDER = 'https://example.com';

// Sitede olup repoya asla girmeyecek yollar
const SECRET = ['app/config.php', 'app/config.php.tmp', 'app/ai-config.php', 'app/installed.lock', 'api/error_log', 'connection.json', 'current.json', '.gitignore'];
const SKIP_DIRS = ['.git', 'dokumantasyon', 'node_modules'];
// Repoya özel sürümleri olan dosyalar: siteden kopyalanmaz, silinmez
const REPO_ONLY = ['install.php', 'app/install-data.php', 'app/install-data.json', 'app/config.example.php', 'app/ai-config.example.php'];
const TEXT = new Set(['.html', '.xml', '.txt', '.js', '.css', '.php', '.json', '.webmanifest', '.sql', '.md']);

const rel = (root, p) => relative(root, p).split(sep).join('/');

function walk(root, dir = root, out = []) {
  for (const name of readdirSync(dir)) {
    const path = join(dir, name);
    const r = rel(root, path);
    if (SKIP_DIRS.includes(r.split('/')[0])) continue;
    if (statSync(path).isDirectory()) walk(root, path, out);
    else out.push(r);
  }
  return out;
}

if (!existsSync(join(SITE, 'index.html'))) {
  console.error(`Site klasörü bulunamadı: ${SITE}`);
  process.exit(1);
}

// Sitenin gerçek alan adı repoya yazılmasın diye canonical etiketinden okunur
const canonical = readFileSync(join(SITE, 'index.html'), 'utf8').match(/<link rel="canonical" href="https?:\/\/(?:www\.)?([^/"]+)/);
if (!canonical) {
  console.error('index.html içinde canonical etiketi bulunamadı; alan adı belirlenemedi.');
  process.exit(1);
}
const DOMAIN_HOST = canonical[1];
const SITE_DOMAIN = new RegExp(`https?://(?:www\\.)?${DOMAIN_HOST.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}`, 'g');

// 1. Sızıntı kontrolü için sitedeki gizli değerleri topla (sadece bellekte tutulur)
const secretValues = [];
for (const file of ['app/config.php', 'app/ai-config.php']) {
  const path = join(SITE, file);
  if (!existsSync(path)) continue;
  for (const m of readFileSync(path, 'utf8').matchAll(/'(\w+)'\s*=>\s*'([^']{6,})'/g)) {
    if (/pass|key|secret|token|user|name/.test(m[1]) && m[2] !== 'localhost') secretValues.push(m[2]);
  }
}

// 2. Yalnızca sayfalardan gerçekten çağrılan asset dosyalarını al
const siteFiles = walk(SITE).filter((f) => !SECRET.includes(f) && !REPO_ONLY.includes(f));
const assets = siteFiles.filter((f) => f.startsWith('assets/')).map((f) => f.slice(7));
const used = new Set();
const queue = siteFiles.filter((f) => !f.startsWith('assets/') && /\.(html|webmanifest|xml|js)$/.test(f));
for (const seen = new Set(); queue.length;) {
  const f = queue.shift();
  if (seen.has(f)) continue;
  seen.add(f);
  const text = readFileSync(join(SITE, f), 'utf8');
  for (const a of assets) if (!used.has(a) && text.includes(a)) { used.add(a); queue.push('assets/' + a); }
}
const wanted = siteFiles.filter((f) => !f.startsWith('assets/') || used.has(f.slice(7)));

// 3. Kopyala (alan adını yer tutucuya çevirerek)
let copied = 0;
for (const f of wanted) {
  const from = join(SITE, f), to = join(DEST, f);
  mkdirSync(dirname(to), { recursive: true });
  if (TEXT.has(extname(f))) {
    const text = readFileSync(from, 'utf8').replace(SITE_DOMAIN, PLACEHOLDER);
    if (!existsSync(to) || readFileSync(to, 'utf8') !== text) { writeFileSync(to, text); copied++; }
  } else if (!existsSync(to) || !readFileSync(to).equals(readFileSync(from))) {
    copyFileSync(from, to);
    copied++;
  }
}

// 4. Sitede artık olmayan dosyaları repodan kaldır
const keep = new Set([...wanted, ...REPO_ONLY]);
let removed = 0;
for (const f of walk(DEST)) {
  if (!keep.has(f)) { rmSync(join(DEST, f)); removed++; }
}

// 5. Müfredat değiştiyse okunabilir JSON'u yeniden üret
const siteData = join(SITE, 'app/install-data.php');
if (existsSync(siteData)) {
  const b64 = readFileSync(siteData, 'utf8').match(/base64_decode\('([^']+)'/);
  if (b64) {
    const data = JSON.parse(Buffer.from(b64[1], 'base64').toString('utf8'));
    for (const table of Object.keys(data.tables || {})) {
      if (data.tables[table].length) {
        console.error(`DURDURULDU: install-data.php içinde "${table}" tablosunda kayıt var (öğrenci/kullanıcı verisi olabilir).`);
        process.exit(1);
      }
    }
    const json = JSON.stringify(data, null, 2) + '\n';
    const target = join(DEST, 'app/install-data.json');
    if (readFileSync(target, 'utf8') !== json) { writeFileSync(target, json); copied++; }
  }
}

// 6. Son güvenlik taraması: gizli değer, API anahtarı veya gerçek alan adı kalmasın
const leaks = [];
for (const f of walk(DEST)) {
  if (!TEXT.has(extname(f))) continue;
  const text = readFileSync(join(DEST, f), 'utf8');
  if (secretValues.some((v) => text.includes(v))) leaks.push(`${f}: sitedeki gizli bir değer`);
  if (/gsk_[A-Za-z0-9]{10,}|sk-[A-Za-z0-9]{20,}|AKIA[0-9A-Z]{16}/.test(text)) leaks.push(`${f}: API anahtarına benzeyen metin`);
  if (text.includes(DOMAIN_HOST)) leaks.push(`${f}: gerçek alan adı`);
}
if (leaks.length) {
  console.error('DURDURULDU: Repoda gizli bilgi bulundu. Commit etmeyin!\n  ' + leaks.join('\n  '));
  process.exit(1);
}

console.log(`${copied} dosya güncellendi, ${removed} dosya kaldırıldı. Gizli bilgi taraması temiz.`);
console.log('install.php repodaki sürümüyle korundu; sitede değiştirdiyseniz değişikliği elle aktarın.');
